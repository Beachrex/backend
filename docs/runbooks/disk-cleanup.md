# Runbook: disk cleanup after deploys (old Docker images, build cache)

Status: **Built** (ADR 2026-10-30 "Deploys prune old Docker images"). Owner 2026-10-30: the VPS disk (75 GB,
Hetzner) was 71 % full; "da deploy samodejno čisti stare Docker slike — ja".

Every deploy builds a new image (`beachrex/api:<env>`, `beachrex/front:<env>`). The old one lost its tag and stayed
on disk as a dangling `<none>` image, and BuildKit cache grew with every build.

## What runs

`deploy/scripts/docker-prune.sh <env> <api|front>` runs as the step **"Clean up old Docker images and build cache"**
at the end of every successful deploy, in both workflows (backend: after "Reload Nginx"; web-client: after
"Reload Nginx", only if the script is on the server). It covers the images of **all** environments with one rule.
The step has `continue-on-error`, the script always exits 0: the cleanup never fails a deploy.

### Retention rule

Per compose service that builds an image (`api_<env>`, `front_<env>`):

| Image | What happens |
|---|---|
| current (`beachrex/api:<env>`, `beachrex/front:<env>`) | kept |
| the newest other image of the service | kept and tagged `<tag>-previous` (e.g. `beachrex/front:prod-previous`) |
| any image a container uses (running or stopped: old API replica, scheduler/worker before their recreate, stopped `front_<env>_green`) | kept ("a container uses it" in the log) |
| everything older | `docker image rm <id>` (no `-f`: Docker refuses anything in use or multi-tagged; that is logged, never forced) |

"Our" images are found by the label `com.docker.compose.project=deploy` that `docker compose build` puts on every
image it builds (the compose file is in `/opt/beachrex/deploy`), or by the name `beachrex/*`. Base images (php, node,
postgis, redis, nginx), other projects' images, **volumes and networks are never touched**; there is no
`docker system prune`. Dangling images *without* our label (built by an older compose that did not label images, or
by someone else) are only counted in the notice.

BuildKit cache: `docker builder prune -f --filter until=72h`, then capped at 8 GB
(`--max-used-space`, or `--keep-storage` on an older Docker; least recently used entries go first).

### Not at the same time as a build

The API and the website deploy are in different repos, so they can overlap. Both build steps
(`api-rollout.sh`, `front-deploy.sh`) hold `/opt/beachrex/.docker-build.lock` **shared** while they build; the
cleanup takes it **exclusively** (waits up to 10 min, otherwise it skips and the next deploy cleans up). A build
waits at most 10 min for the lock and then builds anyway.

## Reading the result

The step prints one `::notice` titled **"Disk cleanup (<api|front> <env>)"** (readable through the check-run
annotations API): `df -h /` and `docker system df` before and after, "Freed on /: …", what was kept per service,
and a report of other disk users (nothing in the report is deleted):

- container logs per container with its json-file limit (`10m x 5` for every app service incl. the green ones;
  `NO-LIMIT` for `pgsql`/`redis` on purpose: changing their logging would recreate the shared database);
- Laravel `storage/logs` per environment (if it is large: `LOG_STACK=daily` keeps 14 days, `LOG_DAILY_DAYS`);
- `*.dump` files in `/root` and `/tmp` from manual backups, and `/tmp/*.dump` inside the pgsql container
  (the prod-launch runbook leaves `/tmp/uat.dump` there unless removed) — **the owner decides and deletes by hand**;
- container writable layers (Next.js `.next/cache` lives there; it is gone when the container is recreated);
- volume sizes, `/opt/beachrex/api_*`, `front_*`, MCP `node_modules`, `~/.npm`, journald.

## Quick rollback to the previous image (owner, on the VPS)

Normal rollback is still revert + deploy (zero-downtime, runbook `zero-downtime-deploys.md`). If a deploy broke the
website and a rebuild would take too long, the previous image is on disk:

```bash
DC="docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.prod --env-file /opt/beachrex/envs/.env.front.prod"
docker tag beachrex/front:prod-previous beachrex/front:prod
$DC up -d --no-deps front_prod_green && curl -s http://127.0.0.1:3011/api/health   # wait for 200
echo 'set $beachrex_front_pool beachrex_front_prod_green;' > /opt/beachrex/nginx-state/front_prod.conf
sudo nginx -t && sudo systemctl reload nginx
$DC up -d --no-deps --force-recreate front_prod                                       # blue on the old image
rm /opt/beachrex/nginx-state/front_prod.conf && sudo systemctl reload nginx && $DC stop front_prod_green
```

API: `docker tag beachrex/api:prod-previous beachrex/api:prod`, then the same steps as `api-rollout.sh` by hand is
risky (migrations already ran) — prefer revert + deploy. The next deploy builds a new image again either way.

## Run it by hand / dry run

```bash
BEACHREX_PRUNE_DRY_RUN=1 bash /opt/beachrex/deploy/scripts/docker-prune.sh dev api   # shows what it would do
```

Knobs (environment): `BEACHREX_BUILD_CACHE_UNTIL` (72h), `BEACHREX_BUILD_CACHE_MAX` (8GB),
`BEACHREX_PRUNE_LOCK_WAIT_S` (600).
