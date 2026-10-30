#!/usr/bin/env bash
# Zero-downtime API deploy on the VPS: rolling replacement of api_<env> (ADR 2026-10-29 "Zero-downtime deploys").
# Runbook: docs/runbooks/zero-downtime-deploys.md
#
# Called by the backend deploy workflow over SSH, after the code and deploy/ sync:
#   bash /opt/beachrex/deploy/scripts/api-rollout.sh <dev|uat|prod>
#
# Before: `up -d --force-recreate web_<env>` recreated the nginx container (port 8081/8082/8083 closed) and,
# through depends_on, the PHP-FPM container, so the API answered 502 for a few seconds on every deploy.
# Now:
#   1. build the image; the old api_<env> keeps serving;
#   2. start a SECOND api_<env> container with the new image (compose --scale N+1 --no-recreate);
#      web_<env> resolves api_<env> through Docker DNS on every request (valid=10s) and gets both addresses;
#      a refused connection is retried on the other one (fastcgi_next_upstream error timeout, the default);
#   3. wait until PHP-FPM listens, run migrate / config:cache / route:cache / cache:clear in the NEW container;
#   4. wait out web_<env>'s DNS cache, then stop the old container gracefully (php-fpm SIGQUIT: open requests
#      finish) and remove it;
#   web_<env> itself is kept and only reloaded (see below).
# A probe asks https://<api host>/api/v1/health through host nginx every 0.2 s during all of this; if any
# request got no answer or a 5xx the script exits with 3 after finishing the rollout (the workflow then
# runs its remaining steps and fails at the end). Other failures exit 1 right away.
set -euo pipefail

ENV_NAME="${1:?usage: api-rollout.sh <dev|uat|prod>}"
case "$ENV_NAME" in
  prod) API_HOST=api.beachrex.com ;;
  uat)  API_HOST=uat-api.beachrex.com ;;
  dev)  API_HOST=dev-api.beachrex.com ;;
  *) echo "Unknown environment: $ENV_NAME" >&2; exit 2 ;;
esac

# Overridable only for a local test; the defaults are the VPS layout.
ROOT="${BEACHREX_ROOT:-/opt/beachrex}"
PROBE_HTTPS_PORT="${BEACHREX_PROBE_HTTPS_PORT:-443}"
COMPOSE_FILE="${BEACHREX_COMPOSE_FILE:-$ROOT/deploy/docker-compose.vps.yml}"
NGINX_DIR="$(dirname "$COMPOSE_FILE")/nginx"
API="api_${ENV_NAME}"
WEB="web_${ENV_NAME}"
HEALTH_PATH=/api/v1/health
# web_<env> caches DNS for 10 s (resolver ... valid=10s in deploy/nginx/api.<env>.conf).
DNS_CACHE_WAIT_S=12

# Every log line is also kept for one GitHub annotation at the end: the run log itself is not always
# readable (the agent sandbox cannot download Actions logs), annotations are (API check-run annotations).
SUMMARY_FILE="$(mktemp)"
log() { echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$SUMMARY_FILE"; }
annotate() {
  [ -s "$SUMMARY_FILE" ] || return 0
  local body
  body="$(sed -e 's/%/%25/g' "$SUMMARY_FILE" | awk 'BEGIN{ORS="%0A"} {print}')"
  echo "::notice title=API rollout ${ENV_NAME}::${body}"
  rm -f "$SUMMARY_FILE"
}
dc() { docker compose -f "$COMPOSE_FILE" --env-file "$ROOT/envs/.env.${ENV_NAME}" "$@"; }

# ---------- probe (proof of zero downtime) ----------
PROBE_PID=""
PROBE_LOG="$(mktemp)"
PROBE_STOP="$(mktemp -u)"
is_bad() { case "$1" in 000 | 5??) return 0 ;; *) return 1 ;; esac; }
probe_once() {
  curl -sk -o /dev/null --max-time 10 -w '%{http_code} %{time_total}s' --resolve "${API_HOST}:${PROBE_HTTPS_PORT}:127.0.0.1" \
    "https://${API_HOST}:${PROBE_HTTPS_PORT}${HEALTH_PATH}" 2>/dev/null || true
}
start_probe() {
  local first
  first="$(probe_once)"
  if is_bad "${first%% *}"; then
    log "Probe: https://${API_HOST}${HEALTH_PATH} via 127.0.0.1 answered '${first}' before the rollout; probe off."
    return 0
  fi
  (
    while [ ! -e "$PROBE_STOP" ]; do
      echo "$(date -u +%H:%M:%S.%3N) $(probe_once)"
      sleep 0.2
    done
  ) > "$PROBE_LOG" 2>&1 &
  PROBE_PID=$!
  log "Probe started (every 0.2 s, https://${API_HOST}${HEALTH_PATH} via host nginx)."
}
stop_probe() {
  [ -n "$PROBE_PID" ] || return 0
  touch "$PROBE_STOP"
  wait "$PROBE_PID" 2>/dev/null || true
  PROBE_PID=""
  rm -f "$PROBE_STOP"
  local total bad
  total="$(wc -l < "$PROBE_LOG" | tr -d ' ')"
  bad="$(awk '$2 == "000" || $2 ~ /^5/' "$PROBE_LOG" | wc -l | tr -d ' ')"
  log "Probe: ${total} requests during the rollout, ${bad} failed (000 or 5xx). Status codes (count code):"
  awk '{print $2}' "$PROBE_LOG" | sort | uniq -c | sed 's/^/    /' | tee -a "$SUMMARY_FILE"
  if [ "$bad" -gt 0 ]; then
    log "Probe: failed requests:"
    awk '$2 == "000" || $2 ~ /^5/' "$PROBE_LOG" | head -50 | sed 's/^/    /' | tee -a "$SUMMARY_FILE"
  fi
  rm -f "$PROBE_LOG"
  [ "$bad" -gt 255 ] && bad=255
  return "$bad"
}
finish() {
  local rc=$?
  stop_probe || true
  annotate
  exit "$rc"
}
trap finish EXIT

fpm_ready() {
  docker exec "$1" php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);' 2>/dev/null
}

# Builds hold the Docker lock shared; docker-prune.sh (end of every deploy, both repos) takes it exclusively,
# so an image cleanup never runs while the other repo's deploy builds. Never blocks a deploy for long.
build_locked() {
  local lock="${BEACHREX_DOCKER_LOCK:-$ROOT/.docker-build.lock}"
  if command -v flock > /dev/null 2>&1 && exec 8>> "$lock"; then
    flock -s -w 600 8 || log "Docker lock busy for 600 s; building anyway."
  fi
  local rc=0
  dc build "$@" || rc=$?
  flock -u 8 2> /dev/null || true
  return "$rc"
}

# ---------- main ----------
log "API rollout of ${ENV_NAME} (${API_HOST}); $(docker compose version 2>/dev/null || echo 'docker compose ?')"
log "Building ${API} (the running container keeps serving)."
build_locked "$API"
dc up -d pgsql redis
start_probe

# web_<env> (nginx) is kept, not recreated. It mounts deploy/nginx/api.<env>.conf as a single file, which the
# workflow syncs with rsync --inplace so the container sees changes; a graceful reload applies them. If the
# file was replaced instead (a sync from a branch without --inplace), the container still shows the old file
# and only a new container can load the new one: a short blip, and only in that case.
dc up -d --no-deps "$WEB"
WEB_ID="$(dc ps -q "$WEB" | head -1)"
if docker exec "$WEB_ID" cat /etc/nginx/conf.d/default.conf | cmp -s - "$NGINX_DIR/api.${ENV_NAME}.conf"; then
  docker exec "$WEB_ID" nginx -t -q
  docker exec "$WEB_ID" nginx -s reload
  log "${WEB} kept; nginx config reloaded (graceful)."
else
  log "${WEB} shows an older api.${ENV_NAME}.conf than deploy/ (file was replaced): recreating ${WEB}."
  dc up -d --no-deps --force-recreate "$WEB"
fi

OLD_IDS="$(dc ps -a -q "$API" | sort)"
OLD_COUNT="$(printf '%s\n' "$OLD_IDS" | grep -c . || true)"
log "Old ${API} containers: ${OLD_COUNT}."

log "Starting a new ${API} container next to the old one."
dc up -d --no-deps --no-recreate --scale "${API}=$((OLD_COUNT + 1))" "$API"
NEW_ID="$(comm -13 <(printf '%s\n' "$OLD_IDS") <(dc ps -a -q "$API" | sort) | head -1)"
if [ -z "$NEW_ID" ]; then
  log "ERROR: could not find the new ${API} container."
  exit 1
fi
NEW_NAME="$(docker inspect -f '{{.Name}}' "$NEW_ID" | sed 's#^/##')"
log "New container: ${NEW_NAME}."

for i in $(seq 1 60); do
  if fpm_ready "$NEW_ID"; then log "PHP-FPM in ${NEW_NAME} listens after ~${i} s."; break; fi
  if [ "$i" = 60 ]; then
    log "ERROR: PHP-FPM in ${NEW_NAME} did not start; removing it, the old container keeps serving."
    docker logs --tail 40 "$NEW_ID" 2>&1 | sed 's/^/    /' || true
    docker rm -f "$NEW_ID" > /dev/null || true
    exit 1
  fi
  sleep 1
done

log "Migrations and caches (in ${NEW_NAME})."
docker exec "$NEW_ID" php artisan migrate --force
docker exec "$NEW_ID" php artisan config:cache
docker exec "$NEW_ID" php artisan route:cache
docker exec "$NEW_ID" php artisan cache:clear

if [ "$OLD_COUNT" -gt 0 ]; then
  log "Waiting ${DNS_CACHE_WAIT_S} s so ${WEB} knows the new address before the old one goes away."
  sleep "$DNS_CACHE_WAIT_S"
  for id in $OLD_IDS; do
    log "Stopping old container $(docker inspect -f '{{.Name}}' "$id" | sed 's#^/##') (graceful, open requests finish)."
    # request_terminate_timeout is 60 s, so 70 s covers the longest request.
    docker stop -t 70 "$id" > /dev/null
    docker rm "$id" > /dev/null
  done
fi

trap - EXIT
BAD=0
stop_probe || BAD=$?
if [ "$BAD" -gt 0 ]; then
  annotate
  echo "::error::API rollout of ${ENV_NAME} finished, but the probe saw ${BAD} failed requests (see the notice / log)."
  exit 3
fi
log "API rollout of ${ENV_NAME} complete: ${NEW_NAME} serves the new version."
annotate
