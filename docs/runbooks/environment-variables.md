# Runbook — Environment files on the VPS (names only)

**Status:** checked by the owner on 2026-10-29 with the comparison script below (prod launch day).
Values are never written here or printed by the scripts; only variable **names** and whether they are set.

Files on the VPS (`/opt/beachrex/envs/`):

| Environment | API (Laravel) | Website (Next.js) |
|---|---|---|
| dev | `.env.dev` | `.env.front.dev` |
| uat | `.env.uat` | `.env.front.uat` |
| prod | `.env.prod` | `.env.front.prod` |

After **any** edit of an env file, the environment must be redeployed (the deploy recreates the containers and
caches the config): GitHub → `Beachrex/backend` or `Beachrex/web-client` → Actions → **Deploy to VPS** → Run workflow →
branch `dev` / `uat` / `main` with the matching environment. `NEXT_PUBLIC_*` values are baked into the website build,
so a website change needs a website deploy; API values need an API deploy.

## Intended differences (2026-10-29)

| Variable(s) | dev | uat | prod | Why |
|---|---|---|---|---|
| `BACKUP_ENABLED`, `BACKUP_S3_KEY`, `BACKUP_S3_SECRET`, `BACKUP_S3_BUCKET` | — | `BACKUP_ENABLED=false` | on | Only prod is backed up (owner 2026-10-07, confirmed 2026-10-29). `docs/runbooks/restore-backup.md` |
| `TURNSTILE_SECRET_KEY` (API), `NEXT_PUBLIC_TURNSTILE_SITE_KEY` (website) | — | — | set | Cloudflare Turnstile on the AI chat only on prod (owner 2026-10-29: "Turnstile ne bomo na dev in uat"); dev/uat are closed by the preview gate |
| `AI_ALERT_EMAIL` | — | — | set | daily cost-cap alert mail (optional) |
| `PREVIEW_GATE_ENABLED` (API + website), `PREVIEW_GATE_SECRET` (website), `PREVIEW_ALLOWED_EMAILS` (API, optional) | on | on | — | dev/uat websites closed behind an e-mail magic link; never active on prod. `docs/guides/admin/zaprta-testna-stran.md` |
| `SESSION_COOKIE` | `beachrex_dev_session` | `beachrex_uat_session` | `beachrex_prod_session` | the admins share `.beachrex.com`; each environment needs its own session cookie name (dev/uat added 2026-10-29) |
| `SESSION_SECURE_COOKIE` | `true` | `true` | `true` | all environments run on HTTPS (dev/uat added 2026-10-29) |
| `OPENAI_ORGANIZATION` | empty | empty | empty | optional |

## Known gaps (owner actions)

- **`OPEN_METEO_API_KEY` is set in no environment** (checked 2026-10-29). Without a key the code uses the free
  Open-Meteo endpoint, which is licensed for **non-commercial use only**. Before Beachrex earns money (ads, paid trip
  planner), take an Open-Meteo API plan and set `OPEN_METEO_API_KEY` in `.env.prod`, then redeploy the API. dev/uat may
  stay on the free endpoint.
- **Stripe:** `STRIPE_ENABLED=true` on prod. The paid trip planner is switched off in the admin (Site settings), which
  also closes checkout. Decide test vs live Stripe keys before switching the planner on.

## Prod values checked on 2026-10-29 (non-secret)

`APP_ENV=production`, `APP_DEBUG=false`, `SCOUT_PREFIX=prod_`, `AI_CHAT_ENGINE=agent`, `AI_CHAT_PROVIDER=anthropic`,
`STRIPE_ENABLED=true`. Set (values not shown): `APP_KEY`, `APP_URL`, `FRONTEND_URL`, `FILAMENT_DOMAIN`, `MEILISEARCH_*`,
`ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `OPENWEATHER_API_KEY`, `GOOGLE_CLIENT_*`, `STRIPE_SECRET_KEY`,
`STRIPE_WEBHOOK_SECRET`, `MAIL_*`, `DO_SPACES_*`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`.
Fixed that day: `MEILISEARCH_KEY` (was invalid → prod search failed), `DO_SPACES_KEY/SECRET` (were placeholders →
uploads failed), `NEXT_PUBLIC_BASE_URL` / `NEXT_PUBLIC_API_ENDPOINT` in `.env.front.prod` (pointed to uat).

## Comparison script (names only, run on the VPS)

Shows only the variables that are missing, empty or a placeholder in at least one file:

```bash
cd /opt/beachrex/envs
cmp_envs () {
  local files=("$@")
  printf '%-40s' "VARIABLE"; for f in "${files[@]}"; do printf '%-18s' "$f"; done; echo
  cat "${files[@]}" 2>/dev/null | grep -oE '^[A-Za-z_][A-Za-z0-9_]*=' | tr -d '=' | sort -u | while read -r v; do
    line=$(printf '%-40s' "$v"); seen=""
    for f in "${files[@]}"; do
      if ! grep -q "^$v=" "$f" 2>/dev/null; then s="MISSING"
      else val=$(grep "^$v=" "$f" | tail -1 | cut -d= -f2-)
        if [ -z "$val" ]; then s="empty"
        elif echo "$val" | grep -qi placeholder; then s="PLACEHOLDER"
        else s="ok"; fi
      fi
      line="$line$(printf '%-18s' "$s")"; seen="$seen $s"
    done
    echo "$seen" | grep -qE 'MISSING|empty|PLACEHOLDER' && echo "$line"
  done
}
echo "=== API ==="; cmp_envs .env.dev .env.uat .env.prod
echo "=== Website ==="; cmp_envs .env.front.dev .env.front.uat .env.front.prod
```

A variable missing in **all** files is not listed by the comparison; check the important prod names explicitly:

```bash
for v in APP_KEY APP_URL APP_DEBUG FRONTEND_URL FILAMENT_DOMAIN SCOUT_PREFIX MEILISEARCH_HOST MEILISEARCH_KEY ANTHROPIC_API_KEY AI_CHAT_PROVIDER OPENAI_API_KEY OPENWEATHER_API_KEY OPEN_METEO_API_KEY GOOGLE_CLIENT_ID GOOGLE_CLIENT_SECRET STRIPE_ENABLED STRIPE_SECRET_KEY STRIPE_WEBHOOK_SECRET MAIL_HOST MAIL_PASSWORD DO_SPACES_KEY SANCTUM_STATEFUL_DOMAINS SESSION_DOMAIN BACKUP_ENABLED BACKUP_S3_BUCKET TURNSTILE_SECRET_KEY; do grep -q "^$v=.\+" /opt/beachrex/envs/.env.prod && echo "ok      $v" || echo "MISSING $v"; done
```
