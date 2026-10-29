#!/usr/bin/env bash
# Zero-downtime website deploy on the VPS: blue/green (ADR 2026-10-29 "Zero-downtime deploys").
# Runbook: docs/runbooks/zero-downtime-deploys.md
#
# Called by the web-client deploy workflow over SSH, after it synced the code to /opt/beachrex/front_<env>/:
#   bash /opt/beachrex/deploy/scripts/front-deploy.sh <dev|uat|prod>
#
# Two containers per environment, same image beachrex/front:<env>:
#   blue  = front_<env>        port 3001 / 3002 / 3003 (prod / uat / dev) — the normal, long-running one
#   green = front_<env>_green  port 3011 / 3012 / 3013                    — runs only during a deploy
# Host nginx (deploy/nginx/beachrex.conf) sends the site to blue, or to green while
# /opt/beachrex/nginx-state/front_<env>.conf says so. Every switch is a graceful nginx reload.
#
# Steps: build the image (the live container is untouched) → start the idle color with it → wait for
# /api/health → warm the main pages → switch nginx to it → if that was green: recreate blue the same way and
# switch back → stop green. The end state is always blue only, the same as before blue/green existed.
#
# While it runs, a probe asks the site through host nginx every 0.2 s (Host header, 127.0.0.1) and the
# script fails at the end if any answer was a 5xx or no answer at all: that log is the proof of zero downtime.
# (404 is not a failure: an image older than the health route answers 404, which still proves it is up.)
set -euo pipefail

ENV_NAME="${1:?usage: front-deploy.sh <dev|uat|prod>}"
case "$ENV_NAME" in
  prod) BLUE_PORT=3001; GREEN_PORT=3011; SITE_HOST=beachrex.com ;;
  uat)  BLUE_PORT=3002; GREEN_PORT=3012; SITE_HOST=uat.beachrex.com ;;
  dev)  BLUE_PORT=3003; GREEN_PORT=3013; SITE_HOST=dev.beachrex.com ;;
  *) echo "Unknown environment: $ENV_NAME" >&2; exit 2 ;;
esac

# Overridable only for the local test (deploy/scripts/tests/); the defaults are the VPS layout.
ROOT="${BEACHREX_ROOT:-/opt/beachrex}"
COMPOSE_FILE="${BEACHREX_COMPOSE_FILE:-$ROOT/deploy/docker-compose.vps.yml}"
STATE_DIR="${BEACHREX_NGINX_STATE_DIR:-$ROOT/nginx-state}"
RELOAD_NGINX="${BEACHREX_RELOAD_NGINX:-sudo systemctl reload nginx}"
PROBE_HTTPS_PORT="${BEACHREX_PROBE_HTTPS_PORT:-443}"
WARM_PATHS="${BEACHREX_WARM_PATHS:-/ /en /si /de /hr /it /sitemap.xml}"
HEALTH_TIMEOUT_S="${BEACHREX_HEALTH_TIMEOUT_S:-180}"

STATE_FILE="$STATE_DIR/front_${ENV_NAME}.conf"
BLUE="front_${ENV_NAME}"
GREEN="front_${ENV_NAME}_green"
HEALTH_PATH=/api/health

# Every log line is also kept for one GitHub annotation at the end: the run log itself is not always
# readable (the agent sandbox cannot download Actions logs), annotations are (API check-run annotations).
SUMMARY_FILE="$(mktemp)"
log() { echo "[$(date -u +%H:%M:%S)] $*" | tee -a "$SUMMARY_FILE"; }
annotate() {
  [ -s "$SUMMARY_FILE" ] || return 0
  local body
  body="$(sed -e 's/%/%25/g' "$SUMMARY_FILE" | awk 'BEGIN{ORS="%0A"} {print}')"
  echo "::notice title=Website deploy ${ENV_NAME}::${body}"
  rm -f "$SUMMARY_FILE"
}

dc() {
  local env_args=()
  [ -f "$ROOT/envs/.env.${ENV_NAME}" ] && env_args+=(--env-file "$ROOT/envs/.env.${ENV_NAME}")
  [ -f "$ROOT/envs/.env.front.${ENV_NAME}" ] && env_args+=(--env-file "$ROOT/envs/.env.front.${ENV_NAME}")
  docker compose -f "$COMPOSE_FILE" "${env_args[@]}" "$@"
}

svc_of()  { if [ "$1" = blue ]; then echo "$BLUE"; else echo "$GREEN"; fi; }
port_of() { if [ "$1" = blue ]; then echo "$BLUE_PORT"; else echo "$GREEN_PORT"; fi; }

active_color() {
  if grep -qs "beachrex_front_${ENV_NAME}_green;" "$STATE_FILE"; then echo green; else echo blue; fi
}

# ---------- probe (proof of zero downtime) ----------
PROBE_PID=""
PROBE_LOG="$(mktemp)"
PROBE_STOP="$(mktemp -u)"

probe_once() {
  # Prints "<http code> <color>"; the color comes from the health JSON ({"status":"ok","color":"blue"}).
  local out
  out="$(curl -sk --max-time 10 -w ' %{time_total}s %{http_code}' \
    --resolve "${SITE_HOST}:${PROBE_HTTPS_PORT}:127.0.0.1" \
    "https://${SITE_HOST}:${PROBE_HTTPS_PORT}${HEALTH_PATH}" 2>/dev/null || true)"
  local code="${out##* }" rest="${out% *}"
  local color
  color="$(printf '%s' "$out" | sed -n 's/.*"color":"\([a-z]*\)".*/\1/p')"
  echo "${code:-000} ${color:--} ${rest##* }"
}

start_probe() {
  local first
  first="$(probe_once)"
  if is_bad "${first%% *}"; then
    log "Probe: https://${SITE_HOST}${HEALTH_PATH} via 127.0.0.1 answered '${first}' before the switch; probe off for this deploy."
    return 0
  fi
  (
    while [ ! -e "$PROBE_STOP" ]; do
      echo "$(date -u +%H:%M:%S.%3N) $(probe_once)"
      sleep 0.2
    done
  ) > "$PROBE_LOG" 2>&1 &
  PROBE_PID=$!
  log "Probe started (every 0.2 s, https://${SITE_HOST}${HEALTH_PATH} via host nginx)."
}

# A failed request: no answer (000) or a server error (5xx, e.g. the 502 nginx returns without an upstream).
is_bad() { case "$1" in 000 | 5??) return 0 ;; *) return 1 ;; esac; }

# Prints the summary and returns the number of failed requests (capped at 255 for the exit code).
stop_probe() {
  [ -n "$PROBE_PID" ] || return 0
  touch "$PROBE_STOP"
  wait "$PROBE_PID" 2>/dev/null || true
  PROBE_PID=""
  rm -f "$PROBE_STOP"
  local total bad
  total="$(wc -l < "$PROBE_LOG" | tr -d ' ')"
  bad="$(awk '$2 == "000" || $2 ~ /^5/' "$PROBE_LOG" | wc -l | tr -d ' ')"
  log "Probe: ${total} requests during the deploy, ${bad} failed (000 or 5xx). Status codes (count code):"
  awk '{print $2}' "$PROBE_LOG" | sort | uniq -c | sed 's/^/    /' | tee -a "$SUMMARY_FILE"
  log "Probe: who answered, in order (count code color):"
  awk '{print $2, $3}' "$PROBE_LOG" | uniq -c | sed 's/^/    /' | tee -a "$SUMMARY_FILE"
  if [ "$bad" -gt 0 ]; then
    log "Probe: failed requests:"
    awk '$2 == "000" || $2 ~ /^5/' "$PROBE_LOG" | head -50 | sed 's/^/    /' | tee -a "$SUMMARY_FILE"
  fi
  rm -f "$PROBE_LOG"
  [ "$bad" -gt 255 ] && bad=255
  return "$bad"
}

# ---------- steps ----------
wait_healthy() {
  local color="$1" port i code
  port="$(port_of "$color")"
  for ((i = 1; i <= HEALTH_TIMEOUT_S * 2; i++)); do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 2 "http://127.0.0.1:${port}${HEALTH_PATH}" || true)"
    if [ "$code" = 200 ]; then
      log "$color ($(svc_of "$color"), port $port) answers ${HEALTH_PATH} after ~$((i / 2)) s."
      return 0
    fi
    sleep 0.5
  done
  log "ERROR: $color ($(svc_of "$color"), port $port) did not answer ${HEALTH_PATH} within ${HEALTH_TIMEOUT_S} s."
  dc logs --tail 40 "$(svc_of "$color")" 2>&1 | sed 's/^/    /' || true
  return 1
}

# First requests after a start render cold (Next.js keeps its ISR/fetch cache inside the container).
warm() {
  local port path
  port="$(port_of "$1")"
  for path in $WARM_PATHS; do
    curl -s -o /dev/null --max-time 60 -H "Host: ${SITE_HOST}" -H 'X-Forwarded-Proto: https' \
      -w "    warm ${path} -> %{http_code} in %{time_total} s\n" "http://127.0.0.1:${port}${path}" \
      || echo "    warm ${path} failed (ignored)"
  done
}

switch_to() {
  # Called from `roll_to ... || ...`, where bash ignores `set -e`: every step checks its own result.
  local color="$1" backup=""
  mkdir -p "$STATE_DIR" || return 1
  if [ -f "$STATE_FILE" ]; then backup="$(cat "$STATE_FILE")"; fi
  if [ "$color" = green ]; then
    printf '# Written by deploy/scripts/front-deploy.sh at %s: %s serves %s.\nset $beachrex_front_pool beachrex_front_%s_green;\n' \
      "$(date -u +%FT%TZ)" "$GREEN" "$SITE_HOST" "$ENV_NAME" > "$STATE_FILE.tmp" || return 1
    mv "$STATE_FILE.tmp" "$STATE_FILE" || return 1
  else
    rm -f "$STATE_FILE" || return 1
  fi
  if ! $RELOAD_NGINX; then
    log "ERROR: nginx reload failed; restoring the previous state file."
    if [ -n "$backup" ]; then printf '%s\n' "$backup" > "$STATE_FILE"; else rm -f "$STATE_FILE"; fi
    $RELOAD_NGINX || true
    return 1
  fi
  # The reload is graceful: old workers finish their requests on the old color, new ones use $color.
  sleep 3
  log "nginx sends ${SITE_HOST} to ${color} ($(svc_of "$color"), port $(port_of "$color"))."
}

# Start <color> with the freshly built image, wait until it answers, warm it, send the traffic to it.
roll_to() {
  local color="$1" svc
  svc="$(svc_of "$color")"
  log "Starting $color ($svc) with the new image."
  dc up -d --no-deps --force-recreate "$svc" || return 1
  if ! wait_healthy "$color"; then
    dc stop "$svc" || true
    return 1
  fi
  log "Warming $color:"
  warm "$color"
  switch_to "$color" || return 1
}

finish() {
  local rc=$?
  stop_probe || true
  annotate
  exit "$rc"
}
trap finish EXIT

# ---------- main ----------
log "Website deploy of ${ENV_NAME} (${SITE_HOST}); $(docker compose version 2>/dev/null || echo 'docker compose ?')"
log "Building beachrex/front:${ENV_NAME} (the live container keeps serving)."
dc build "$BLUE"

ACTIVE="$(active_color)"
log "Active color before the deploy: ${ACTIVE}."
start_probe

if [ "$ACTIVE" = blue ]; then
  roll_to green || { log "ERROR: green did not start; blue keeps serving the old version. Nothing switched."; exit 1; }
fi
roll_to blue || {
  log "ERROR: blue did not start; green keeps serving the NEW version (state file says green). Fix and redeploy."
  exit 1
}
log "Stopping green (graceful: Next.js finishes open requests)."
dc stop -t 30 "$GREEN"

trap - EXIT
BAD=0
stop_probe || BAD=$?
if [ "$BAD" -gt 0 ]; then
  annotate
  echo "::error::Website deploy of ${ENV_NAME} finished, but the probe saw ${BAD} failed requests (see the notice / log)."
  exit 1
fi
log "Website deploy of ${ENV_NAME} complete: blue serves the new version, green stopped."
annotate
