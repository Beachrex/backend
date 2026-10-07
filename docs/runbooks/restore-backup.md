# Runbook — Database backups and restore

**Status:** Built (code in repo). **Live** only after the owner completes "Enable" below and the first
nightly run shows up in `/api/v1/health` (`backup.last_success_at`).

## How it works

- `php artisan db:backup` (`app/Console/Commands/BackupDatabase.php`):
  1. runs `pg_dump --format=custom --no-owner --no-privileges` of this environment's database;
  2. checks the file is non-empty and starts with `PGDMP`;
  3. uploads it to the **private** `backups` disk as `<prefix>/<database>-YYYYMMDD-HHMMSS.dump` (prefix = DB name, e.g. `beachrex_uat`);
  4. verifies the uploaded size, then writes the timestamp to `storage/app/backup-last-success.txt`;
  5. deletes this prefix's backups older than `BACKUP_KEEP_DAYS` (default 14).
- On any error, nothing is uploaded or pruned, the error is logged, and the command exits non-zero.
- Scheduled daily at `BACKUP_TIME` (UTC, default 02:30) by `routes/console.php`. It runs only when
  `BACKUP_ENABLED=true`, inside the `scheduler_<env>` container (`php artisan schedule:work`).
- Second layer (owner action): enable **Hetzner Cloud Backups** for the VPS (daily server snapshots, 7 kept).

## Enable (owner, once per environment)

> **Policy (owner, 2026-10-07):** only **production** is backed up. UAT is backed up **temporarily**, until prod
> is live, because it holds the curated content prod will be created from. After the prod launch, set
> `BACKUP_ENABLED=false` in `.env.uat` (prod launch runbook, phase D5). Dev is never backed up.

1. DigitalOcean → Spaces → create a **new private bucket**, e.g. `beachrex-backups` in `fra1`. File listing: **restricted**.
2. DigitalOcean → API → Spaces Keys → create a key **only for backups**. Store your own copy, because DO shows the secret once.
3. On the VPS, add to `/opt/beachrex/envs/.env.prod` (until the prod launch: `.env.uat`):
   `BACKUP_ENABLED=true`, `BACKUP_S3_KEY`, `BACKUP_S3_SECRET`, `BACKUP_S3_BUCKET=beachrex-backups`
   (region and endpoint default to fra1).
4. Redeploy the environment: re-run the latest "Deploy to VPS" workflow for that branch. That recreates the containers with the new env.
5. Test once by hand, then check:
   `docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.uat exec -T api_uat php artisan db:backup --no-prune`
   → `Uploaded beachrex_uat/beachrex_uat-....dump (N bytes).` and `https://uat-api.beachrex.com/api/v1/health` shows `backup.last_success_at`.

## Restore (owner, with the agent's help)

> Restoring overwrites data. Always restore into a **new** database first, verify, then switch.

```bash
# 1. Download the dump from the private bucket (DO web UI or s3cmd/aws-cli) to the VPS, e.g. /tmp/restore.dump
# 2. Copy it into the pgsql container
docker cp /tmp/restore.dump $(docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml ps -q pgsql):/tmp/restore.dump
# 3. Restore into a scratch database
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml exec -T pgsql \
  sh -c 'createdb -U "$POSTGRES_USER" beachrex_restore && psql -U "$POSTGRES_USER" -d beachrex_restore -c "CREATE EXTENSION IF NOT EXISTS postgis" \
         && pg_restore -U "$POSTGRES_USER" -d beachrex_restore --no-owner --no-privileges /tmp/restore.dump'
# 4. Verify counts (example)
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml exec -T pgsql \
  psql -U "$POSTGRES_USER" -d beachrex_restore -c "select count(*) from beach; select count(*) from media;"
# 5. Switch: rename databases (stop api_<env> + scheduler_<env> first), or point DB_DATABASE at the restored DB.
```

A restore drill (steps 1–4 into `beachrex_restore`, then `DROP DATABASE beachrex_restore`) must be done once
after enabling. Record the date in `docs/HANDOFF.md`.

## Notes

- `pg_dump` is the Debian bookworm `postgresql-client` (v15) from `Dockerfile.prod`, which matches the `postgis/postgis:15-3.4` server.
- Dumps contain user emails and password hashes. Keep the bucket private and the key scoped to it.
