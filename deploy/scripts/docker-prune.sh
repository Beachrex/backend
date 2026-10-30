#!/usr/bin/env bash
# Disk cleanup after a deploy on the VPS: old Docker images of our services and old BuildKit cache
# (ADR 2026-10-30 "Deploys prune old Docker images"; runbook docs/runbooks/disk-cleanup.md).
#
# Called at the end of a successful deploy by BOTH workflows (backend: API, web-client: website):
#   bash /opt/beachrex/deploy/scripts/docker-prune.sh <dev|uat|prod> <api|front>
# The arguments only name the run in the log; the cleanup covers the images of every environment with the same rule.
#
# Retention rule (per compose service that builds an image: api_<env>, front_<env>):
#   - keep the CURRENT image: the one with the service's tag (beachrex/api:<env>, beachrex/front:<env>);
#   - keep ONE PREVIOUS image: the newest other image of that service; it is tagged <tag>-previous
#     (beachrex/api:dev-previous) so it is never dangling and a quick rollback can use it;
#   - remove the others, but never an image a container uses (running or stopped, incl. the blue/green
#     front_<env>_green and the scheduler/worker): such an image is skipped, and `docker image rm` without -f
#     refuses anyway.
#   Our images = images labelled com.docker.compose.project=deploy by `docker compose build` (the compose file
#   lives in /opt/beachrex/deploy) or tagged beachrex/*. Images of other projects, base images (php, node,
#   postgis, redis, nginx), volumes and networks are never touched. Dangling images WITHOUT our label are only
#   counted in the report.
#   BuildKit cache: entries unused for 72 h are removed, then the cache is capped at BEACHREX_BUILD_CACHE_MAX.
#
# It also reports (does not delete) other disk users: container logs, Laravel logs, *.dump files from manual
# backups, the MCP npm cache, journald. Everything ends up in one ::notice annotation (the agent reads
# annotations through the API, not the run log).
#
# This script never fails the deploy: it always exits 0 and logs what did not work.
set -uo pipefail

ENV_NAME="${1:-?}"
KIND="${2:-?}"
ROOT="${BEACHREX_ROOT:-/opt/beachrex}"
PROJECT_LABEL="${BEACHREX_COMPOSE_PROJECT:-deploy}"
CACHE_UNTIL="${BEACHREX_BUILD_CACHE_UNTIL:-72h}"
CACHE_MAX="${BEACHREX_BUILD_CACHE_MAX:-8GB}"
# Shared with the image builds in api-rollout.sh / front-deploy.sh (they hold it shared while they build):
# the cleanup takes it exclusively, so it never runs while a deploy of the other repo builds.
LOCK_FILE="${BEACHREX_DOCKER_LOCK:-$ROOT/.docker-build.lock}"
LOCK_WAIT_S="${BEACHREX_PRUNE_LOCK_WAIT_S:-600}"
DRY_RUN="${BEACHREX_PRUNE_DRY_RUN:-0}"

SUMMARY_FILE="$(mktemp)"
log() { echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$SUMMARY_FILE"; }
indent() { sed 's/^/    /' | tee -a "$SUMMARY_FILE"; }
# shellcheck disable=SC2329 # used by the EXIT trap
annotate() {
  [ -s "$SUMMARY_FILE" ] || return 0
  local body
  body="$(sed -e 's/%/%25/g' "$SUMMARY_FILE" | awk 'BEGIN{ORS="%0A"} {print}')"
  echo "::notice title=Disk cleanup (${KIND} ${ENV_NAME})::${body}"
  rm -f "$SUMMARY_FILE"
}
trap 'annotate; exit 0' EXIT

used_bytes() { df -B1 --output=used / 2> /dev/null | tail -1 | tr -d ' '; }
human() { numfmt --to=iec --suffix=B "${1:-0}" 2> /dev/null || echo "${1:-0} B"; }
disk_report() {
  df -h / 2> /dev/null | indent
  docker system df 2> /dev/null | indent
}
# du as root when allowed (container logs, /root), else as the deploy user; prints nothing on failure.
SUDO=""
sudo -n true > /dev/null 2>&1 && SUDO="sudo -n"
sdu() { $SUDO du "$@" 2> /dev/null || true; }

if ! command -v docker > /dev/null 2>&1; then
  log "docker not found; nothing to do."
  exit 0
fi

log "Disk cleanup after the ${KIND} deploy of ${ENV_NAME}. Rule: per service keep the current image + 1 previous" \
  "(tag <env>-previous); BuildKit cache unused > ${CACHE_UNTIL} removed, capped at ${CACHE_MAX}.$([ "$DRY_RUN" = 1 ] && echo ' DRY RUN.')"
log "Before:"
disk_report
USED_BEFORE="$(used_bytes)"

# ---------- lock: never while a build of the other repo runs ----------
if command -v flock > /dev/null 2>&1 && exec 9>> "$LOCK_FILE"; then
  if ! flock -x -w "$LOCK_WAIT_S" 9; then
    log "Another deploy is still building after ${LOCK_WAIT_S} s: cleanup skipped (the next deploy does it)."
    exit 0
  fi
else
  log "No lock (flock or ${LOCK_FILE} not available); continuing."
fi

run() {
  if [ "$DRY_RUN" = 1 ]; then echo "dry-run: $*"; else "$@"; fi
}

# ---------- 1. old images of our services ----------
# Image IDs any container (running or stopped) uses.
IN_USE="$(docker ps -aq 2> /dev/null | xargs -r docker inspect --format '{{.Image}}' 2> /dev/null | sort -u)"
in_use() { printf '%s\n' "$IN_USE" | grep -qx "$1"; }

# shellcheck disable=SC2016 # Go template, not shell
IMAGE_FORMAT='{{.Created}} {{.Id}} {{with index .Config.Labels "com.docker.compose.service"}}{{.}}{{else}}-{{end}} {{range $i, $t := .RepoTags}}{{if $i}},{{end}}{{$t}}{{end}}'
# One line per image: <created> <id> <service label or -> <tags,comma-separated or ->
OURS="$(
  {
    docker image ls -a -q --no-trunc --filter "label=com.docker.compose.project=${PROJECT_LABEL}" 2> /dev/null
    docker image ls -a -q --no-trunc --filter 'reference=beachrex/*' 2> /dev/null
  } | sort -u | xargs -r docker image inspect --format "$IMAGE_FORMAT" 2> /dev/null
)"

# Group key: the compose service label; for an unlabelled tagged image, its tag without -previous.
group_of() {
  local svc="$1" tags="$2"
  if [ "$svc" != "-" ]; then echo "$svc"; return; fi
  if [ -n "$tags" ] && [ "$tags" != "-" ]; then echo "tag:${tags%%,*}" | sed 's/-previous$//'; return; fi
  echo ""
}
is_current() { # has a tag that is not *-previous
  local t
  for t in ${1//,/ }; do case "$t" in *-previous) ;; *) return 0 ;; esac; done
  return 1
}

REMOVED=0
KEPT_IN_USE=0
declare -A IMG_GROUPS=()
while read -r created id svc tags; do
  [ -n "${id:-}" ] || continue
  tags="${tags:--}"
  g="$(group_of "$svc" "$tags")"
  [ -n "$g" ] || continue
  IMG_GROUPS["$g"]+="${created} ${id} ${tags}"$'\n'
done <<< "$OURS"

for g in $(printf '%s\n' "${!IMG_GROUPS[@]}" | sort); do
  lines="$(printf '%s' "${IMG_GROUPS[$g]}" | sort -r)" # newest first (RFC 3339 timestamps sort as text)
  current_id=""
  current_tag=""
  while read -r _ id tags; do
    if [ -z "$current_id" ] && is_current "$tags"; then
      current_id="$id"
      for t in ${tags//,/ }; do case "$t" in *-previous) ;; *) current_tag="$t"; break ;; esac; done
    fi
  done <<< "$lines"
  if [ -z "$current_id" ]; then
    log "${g}: no current tagged image found; nothing removed."
    continue
  fi
  previous_id=""
  while read -r _ id tags; do
    [ "$id" = "$current_id" ] && continue
    if [ -z "$previous_id" ]; then
      previous_id="$id"
      if ! printf '%s' "$tags" | grep -q -- "${current_tag}-previous"; then
        run docker tag "$id" "${current_tag}-previous" > /dev/null 2>&1 || log "${g}: could not tag ${id:7:12} as ${current_tag}-previous."
      fi
      continue
    fi
    if in_use "$id"; then
      KEPT_IN_USE=$((KEPT_IN_USE + 1))
      log "${g}: ${id:7:12} (${tags}) kept: a container uses it."
      continue
    fi
    # Without -f: Docker refuses an image in use or with several tags; that is logged, never forced.
    # The -previous tag that just moved: `rmi <tag>` would only untag, so remove by ID.
    if out="$(run docker image rm "$id" 2>&1)"; then
      REMOVED=$((REMOVED + 1))
    else
      log "${g}: ${id:7:12} (${tags}) not removed: $(printf '%s' "$out" | tail -1)"
    fi
  done <<< "$lines"
  prev_desc="none"
  [ -n "$previous_id" ] && prev_desc="${previous_id:7:12} (${current_tag}-previous)"
  log "${g}: current ${current_id:7:12} (${current_tag}), previous ${prev_desc}."
done
log "Images: removed ${REMOVED} old image(s) of our services; ${KEPT_IN_USE} old image(s) kept because a container uses them."

# Dangling images with our label (e.g. a failed build's leftovers); in-use ones are skipped by Docker.
out="$(run docker image prune -f --filter "label=com.docker.compose.project=${PROJECT_LABEL}" 2>&1 | tail -1)"
log "Dangling images of our project: ${out:-nothing}"
FOREIGN="$(docker image ls -a -q --no-trunc --filter dangling=true 2> /dev/null | sort -u | while read -r id; do
  [ -n "$id" ] || continue
  docker image inspect --format '{{index .Config.Labels "com.docker.compose.project"}} {{.Size}}' "$id" 2> /dev/null
done | awk -v p="$PROJECT_LABEL" '$1 != p {n++; s += $NF} END {printf "%d %d", n, s}')"
if [ "${FOREIGN%% *}" != "0" ] && [ -n "$FOREIGN" ]; then
  log "Dangling images WITHOUT our label (not removed; older builds or another project): ${FOREIGN%% *}, $(human "${FOREIGN##* }") (shared layers counted in full)."
fi

# ---------- 2. BuildKit cache ----------
out="$(run docker builder prune -f --filter "until=${CACHE_UNTIL}" 2>&1 | tail -1)"
log "Build cache unused for > ${CACHE_UNTIL}: ${out:-nothing}"
# Docker 28+: --max-used-space; older: --keep-storage (same meaning: keep at most this much, least recently used go).
if out="$(run docker builder prune -f --max-used-space "$CACHE_MAX" 2>&1)"; then
  log "Build cache cap ${CACHE_MAX}: $(printf '%s' "$out" | tail -1)"
elif out="$(run docker builder prune -f --keep-storage "$CACHE_MAX" 2>&1)"; then
  log "Build cache cap ${CACHE_MAX} (--keep-storage): $(printf '%s' "$out" | tail -1)"
else
  log "Build cache cap failed: $(printf '%s' "$out" | tail -1)"
fi

flock -u 9 2> /dev/null || true

# ---------- 3. report other disk users (nothing deleted) ----------
log "Other disk users (report only):"
{
  echo "container logs (json-file; limit per container):"
  docker ps -aq 2> /dev/null | xargs -r docker inspect \
    --format '{{.Name}} {{.LogPath}} {{with index .HostConfig.LogConfig.Config "max-size"}}{{.}}{{else}}NO-LIMIT{{end}}x{{with index .HostConfig.LogConfig.Config "max-file"}}{{.}}{{else}}1{{end}}' 2> /dev/null \
    | while read -r name path limit; do
      size="$(sdu -sh "$path" | cut -f1)"
      echo "  ${name#/} ${size:-?} (limit ${limit})"
    done
  echo "Laravel logs (storage/logs in the api container):"
  for e in prod uat dev; do
    cid="$(docker ps -q --filter "label=com.docker.compose.service=api_${e}" 2> /dev/null | head -1)"
    [ -n "$cid" ] || continue
    echo "  ${e}: $(docker exec "$cid" sh -c 'du -sh storage/logs 2>/dev/null | cut -f1; ls -S storage/logs 2>/dev/null | head -3 | tr "\n" " "' 2> /dev/null | tr '\n' ' ')"
  done
  echo "*.dump files from manual backups (delete by hand if no longer needed):"
  dumps="$($SUDO sh -c 'ls -1 /root/*.dump /tmp/*.dump 2>/dev/null' 2> /dev/null || true)"
  if [ -n "$dumps" ]; then
    printf '%s\n' "$dumps" | while read -r f; do echo "  $(sdu -sh "$f" | tr '\t' ' ')"; done
  else
    echo "  none found (or /root not readable)"
  fi
  pg="$(docker ps -q --filter 'label=com.docker.compose.service=pgsql' 2> /dev/null | head -1)"
  if [ -n "$pg" ]; then
    pgd="$(docker exec "$pg" sh -c 'du -ch /tmp/*.dump 2>/dev/null | tail -1' 2> /dev/null || true)"
    [ -n "$pgd" ] && echo "  inside the pgsql container /tmp/*.dump: ${pgd}"
  fi
  echo "container writable layers (Next.js cache etc., gone on recreate):"
  docker ps -a --size --filter "label=com.docker.compose.project=${PROJECT_LABEL}" --format '  {{.Names}} {{.Size}}' 2> /dev/null
  echo "volumes:"
  docker system df -v 2> /dev/null | awk '/^Local Volumes space usage/{f=1; next} /^Build cache usage/{f=0} f && NF >= 3 && $1 != "VOLUME" {print "  " $1, $NF}'
  echo "host folders:"
  for d in "$ROOT"/api_* "$ROOT"/front_* "$ROOT"/api_*/mcp-server/node_modules "$HOME/.npm"; do
    [ -e "$d" ] && sdu -sh "$d" | sed 's/^/  /'
  done
  j="$(journalctl --disk-usage 2> /dev/null | grep -o '[0-9.]*[KMGT]\?B\?' | head -1)"
  [ -n "$j" ] && echo "  journald ${j}"
} 2>&1 | indent

log "After:"
disk_report
USED_AFTER="$(used_bytes)"
if [ -n "$USED_BEFORE" ] && [ -n "$USED_AFTER" ]; then
  log "Freed on /: $(human $((USED_BEFORE - USED_AFTER)))."
fi
exit 0
