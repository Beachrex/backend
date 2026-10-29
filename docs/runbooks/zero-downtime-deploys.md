# Runbook: zero-downtime deploys (website blue/green, API rolling)

Status: **Built** (ADR 2026-10-29 "Zero-downtime deploys"). Live per environment once its backend **and**
web-client deploys ran with this change; the deploy log shows the probe summary (below).

Before: every website deploy recreated `front_<env>` in place, so Next.js did not listen for a few seconds and
Cloudflare showed "502 Bad Gateway / Host Error" (prod 2026-10-29 20:27:23 UTC, run 40948577676). Every API deploy
recreated `web_<env>` and `api_<env>` the same way.

## Website: blue/green

| Env | blue (normal) | green (only during a deploy) | Site |
|---|---|---|---|
| prod | `front_prod` :3001 | `front_prod_green` :3011 | beachrex.com |
| uat | `front_uat` :3002 | `front_uat_green` :3012 | uat.beachrex.com |
| dev | `front_dev` :3003 | `front_dev_green` :3013 | dev.beachrex.com |

- Both colors use the same image `beachrex/front:<env>`. Green has the compose profile `green`, so a plain
  `docker compose up -d` never starts it.
- Host nginx (`deploy/nginx/beachrex.conf`) has two upstreams per environment: `beachrex_front_<env>` (blue first,
  green as `backup`) and `beachrex_front_<env>_green` (green first, blue as `backup`). The site uses the blue one
  unless `/opt/beachrex/nginx-state/front_<env>.conf` (written by the deploy) switches it to green. That file lives
  outside `deploy/` because every deploy syncs `deploy/` with `--delete`. No file = blue.
- `GET /api/health` on the website (web-client `src/app/api/health/route.ts`) answers `{"status":"ok","color":"blue"}`
  without calling the API; the deploy waits for it and the compose healthcheck uses it.

What `deploy/scripts/front-deploy.sh <env>` does (called by the web-client workflow):

1. `docker compose build front_<env>` — the live container keeps serving.
2. Start green with the new image, wait for `/api/health` = 200 (max 180 s), warm `/`, `/en`, `/si`, `/de`, `/hr`,
   `/it`, `/sitemap.xml` (the ISR cache lives in the container, so a new one renders cold).
3. Write the state file → `systemctl reload nginx` (graceful: open requests finish on blue).
4. Recreate blue with the new image, wait, warm, delete the state file → reload nginx.
5. `docker compose stop -t 30 front_<env>_green` (Next.js closes gracefully on SIGTERM).

End state: blue only, on the port every older config knows. If green fails to start, nothing was switched (blue
keeps the old version, the run fails). If blue fails to start in step 4, green keeps serving the new version, the
state file stays on green and the run fails; the next deploy starts from green and ends on blue.

**Proof in every run:** the script probes `https://<site>/api/health` through host nginx (`--resolve <site>:443:127.0.0.1`)
every 0.2 s and prints e.g.

```
Probe: 58 requests during the deploy, 0 failed (000 or 5xx). Status codes (count code):
     58 200
Probe: who answered, in order (count code color):
     15 200 blue
     29 200 green
     14 200 blue
```

Any `000` (no answer) or `5xx` makes the step fail after the deploy finished. (A `404` is not a failure: an image
from before the health route answers 404 on the first blue/green deploy of an environment.)

### Which color is live?

```bash
cat /opt/beachrex/nginx-state/front_prod.conf 2>/dev/null || echo "no state file: blue"
docker ps --filter name=front_prod --format '{{.Names}} {{.Status}}'
curl -sk --resolve beachrex.com:443:127.0.0.1 https://beachrex.com/api/health   # {"status":"ok","color":"blue"}
```

### Roll back

The previous version is not kept in a container (both colors end on the new image). Roll back by reverting the
PR on the branch and letting the deploy run again — it switches with zero downtime like any deploy.

Emergency (owner, on the VPS) — blue misbehaves right after a deploy and green was stopped: start green and switch
to it (it runs the same image, so this helps only when the blue *container* is broken, not the code):

```bash
DC="docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.prod --env-file /opt/beachrex/envs/.env.front.prod"
$DC up -d --no-deps front_prod_green
curl -s http://127.0.0.1:3011/api/health                                # wait for 200
echo 'set $beachrex_front_pool beachrex_front_prod_green;' > /opt/beachrex/nginx-state/front_prod.conf
sudo nginx -t && sudo systemctl reload nginx
# back to blue: rm /opt/beachrex/nginx-state/front_prod.conf && sudo systemctl reload nginx; $DC stop front_prod_green
```

## API: rolling replacement

`deploy/scripts/api-rollout.sh <env>` (called by the backend workflow):

1. `docker compose build api_<env>`; `up -d pgsql redis`.
2. `web_<env>` (the nginx in front of PHP-FPM) is kept: its `api.<env>.conf` is synced with `rsync --inplace`
   (a file bind mount only sees in-place changes) and reloaded gracefully. If the container still shows an older
   file (it was replaced by a sync without `--inplace`), it is recreated: a ~1 s blip, only then.
3. `up -d --no-deps --no-recreate --scale api_<env>=2` starts a second PHP-FPM container with the new image next to
   the old one. `web_<env>` resolves `api_<env>` per request (Docker DNS, cached 10 s) and gets both; a refused or
   unreachable address is retried on the other (`fastcgi_next_upstream error timeout`, `fastcgi_connect_timeout 1s`).
4. Wait until PHP-FPM listens in the new container, then `migrate --force`, `config:cache`, `route:cache`,
   `cache:clear` **in the new container**.
5. Wait 12 s (web's DNS cache), `docker stop -t 70` the old container (php-fpm SIGQUIT: open requests finish), remove it.
6. The workflow continues as before (scheduler, watermark, worker, search index, MCP).

The same probe runs against `https://<api host>/api/v1/health`; failed requests make the run fail at the end
(exit 3 from the script, after the remaining steps).

After a rollout the API container is called `deploy-api_<env>-2`, `-3`, … (compose numbers new replicas).
`docker compose exec api_<env> …` still works (it picks the running one; compose ≥ 2.20). Rollback = revert + deploy.

## Promotion order (shared `deploy/` folder)

`/opt/beachrex/deploy/` is synced by **every** backend deploy from its own branch. The website script, the green
services and the nginx state include all come from that folder, together:

1. Backend first: dev → uat → main, one after the other, so every branch ships the same `deploy/`.
2. Then web-client dev → uat → main. Its workflow uses the script only if `/opt/beachrex/deploy/scripts/front-deploy.sh`
   exists; otherwise it falls back to the old in-place recreate (with the old short 502).

Safe in between: the old nginx config only knows blue, and every blue/green deploy ends on blue. The one unsafe
moment is a backend deploy **from a branch without this change** running exactly while a website deploy has
switched to green (the old config would send traffic to the blue that is being recreated): promote the backend to
uat and main soon, and avoid website deploys while an old-branch backend deploy runs.
