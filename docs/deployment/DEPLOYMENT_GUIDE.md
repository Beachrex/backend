# BeachRex — VPS Deployment Guide

> **Cilj tega dokumenta:** Vsak razvijalec, ki nadaljuje delo na projektu, ve natančno kje smo, kaj je narejeno in kako deployati.

**Zadnja posodobitev:** 2026-09-03
**Status:** ✅ UAT in DEV delujeta. PROD čaka na DNS preklop.

---

## 1. Architektura pregled

### Strežnik
- **Hetzner CX33:** 4 vCPU, 8 GB RAM, 80 GB SSD
- **IP:** `2.28.76.142`
- **OS:** Ubuntu 26.04
- **SSH:** `ssh beachrex@2.28.76.142` (ključ `id_rsa_github` za CI, ročni dostop preko root)

### Komponente na VPS
| Komponenta | Tehnologija | Port | Opomba |
|---|---|---|---|
| PostgreSQL + PostGIS | `postgis/postgis:15-3.4` | 5432 (interni) | 3 baze: `beachrex_prod`, `beachrex_uat`, `beachrex_dev` |
| Redis | `redis:alpine` | 6379 (interni) | 3 logični DB-ji: prod=0, uat=1, dev=2 |
| API (PHP 8.4-fpm) | Debian Bookworm + Nginx | 8081/8082/8083 | En image, 3 kontejnerji (`api_prod`, `api_uat`, `api_dev`) |
| Frontend (Next.js 14) | Node 20 standalone | 3001/3002/3003 | En image, 3 kontejnerji (`front_prod`, `front_uat`, `front_dev`) |
| Nginx (host) | SSL termination + reverse proxy | 80/443 | Certbot certifikati |

### Zunanje storitve (obdržane)
| Storitev | URL | Opomba |
|---|---|---|
| Meilisearch | `https://search.beachrex.com` | Namenski Hetzner CPX22 |
| DigitalOcean Spaces | `s3.beachrex.com` | CDN za slike, bucket `beachrex` v `fra1` |

---

## 2. GitHub repozitoriji in branch struktura

| Repo | URL | Namenska |
|---|---|---|
| Backend | `https://github.com/Beachrex/backend` | Laravel API |
| Frontend | `https://github.com/Beachrex/web-client` | Next.js frontend |

### Branch → Okolje mapping
| Branch | Okolje | API URL | Frontend URL |
|---|---|---|---|
| `dev` | DEV | `https://dev-api.beachrex.com` | `https://dev.beachrex.com` |
| `uat` | UAT | `https://uat-api.beachrex.com` | `https://uat.beachrex.com` |
| `main` | PROD | `https://api.beachrex.com` | `https://beachrex.com` |

> **Pravilo:** Push na `dev` → deploy na DEV. Push na `uat` → deploy na UAT. Push na `main` → deploy na PROD (za PROD je priporočljiv manual trigger preko GitHub Actions UI).

---

## 3. CI/CD (GitHub Actions)

### Workflow datoteke
- Backend: `.github/workflows/deploy.yml`
- Frontend: `.github/workflows/deploy.yml`

### Backend pipeline
1. **Test job:**
   - PHP 8.4 + PostGIS 15-3.4 service container
   - `composer install` → `php artisan test`
   - 4 znane okoljske napake (OpenAI, OpenWeather) so dovoljene
2. **Deploy job** (po uspešnem testu):
   - `rsync` kode na `/opt/beachrex/api/`
   - `rsync` deploy datotek na `/opt/beachrex/deploy/`
   - `docker compose build` + `up -d` + `migrate` + `cache:clear`
   - Reload host Nginx

### Frontend pipeline
1. **Deploy job** (brez test joba):
   - `rsync` kode na `/opt/beachrex/front/`
   - `docker compose build` (Next.js standalone z `NEXT_PUBLIC_*` build args iz `.env.front.*`)
   - `docker compose up -d --force-recreate`
   - Reload host Nginx

### GitHub Secrets (oba repozitorija)
| Secret | Vrednost | Opomba |
|---|---|---|
| `VPS_HOST` | `2.28.76.142` | |
| `VPS_USER` | `beachrex` | |
| `VPS_SSH_KEY` | RSA 2048 PEM private key | Base64-encoded enovrstični niz |

> SSH ključ je generiran lokalno (macOS) z `ssh-keygen -t rsa -b 2048 -m PEM`. Public key je dodan v `/home/beachrex/.ssh/authorized_keys` na VPS.

---

## 4. Direktorijska struktura na VPS

```
/opt/beachrex/
├── api/                    # Backend koda (Laravel)
│   ├── app/
│   ├── Dockerfile.prod
│   ├── composer.json
│   └── ...
├── front/                  # Frontend koda (Next.js)
│   ├── src/
│   ├── Dockerfile.prod
│   ├── next.config.js      # MORA imeti output: "standalone"
│   └── ...
├── deploy/                 # Docker Compose + Nginx configs
│   ├── docker-compose.vps.yml
│   └── nginx/
│       ├── beachrex.conf   # Host Nginx reverse proxy (SSL)
│       ├── api.uat.conf
│       ├── api.dev.conf
│       └── api.prod.conf
├── envs/                   # Environment datoteke (NI v git!)
│   ├── .env.dev
│   ├── .env.uat
│   ├── .env.prod
│   ├── .env.front.dev
│   ├── .env.front.uat
│   └── .env.front.prod
└── backups/                # DB backup datoteke
```

> **Lastništvo:** Vsi direktoriji so v lasti `beachrex:beachrex`. To je ključno za `rsync` iz CI/CD.

---

## 5. Docker Compose

### `deploy/docker-compose.vps.yml`
- Ena datoteka za vsa tri okolja
- Skupne storitve: `pgsql` (PostGIS), `redis`
- Per-okolje: `api_{env}`, `web_{env}`, `front_{env}`
- `env_file` poti so `../envs/.env.{env}` (relativno od `deploy/`)
- Build context: `../api` in `../front`
- Porti: API 8081-8083, Frontend 3001-3003 (vsi na `127.0.0.1`)

### Ročni deploy na VPS
```bash
cd /opt/beachrex/deploy
ENV=uat  # ali dev/prod
docker compose -f docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.${ENV} build api_${ENV} front_${ENV}
docker compose -f docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.${ENV} up -d
```

---

## 6. Nginx konfiguracija

### Host Nginx (`/etc/nginx/sites-available/beachrex`)
- SSL certifikati preko Certbot (Let's Encrypt)
- UAT: `uat-api.beachrex.com` + `uat.beachrex.com` (cert v `uat-api.beachrex.com`)
- DEV: `dev-api.beachrex.com` + `dev.beachrex.com` (cert v `dev-api.beachrex.com`)
- PROD: `api.beachrex.com` + `beachrex.com` (čaka certbot)
- HTTP → HTTPS redirect za vse domene
- Proxy na Docker porte (8081-8083 za API, 3001-3003 za frontend)

### SSL certifikati
```bash
# UAT (že pridobljen)
certbot --nginx -d uat-api.beachrex.com -d uat.beachrex.com

# DEV (že pridobljen)
certbot --nginx -d dev-api.beachrex.com -d dev.beachrex.com

# PROD (ko DNS preklopi)
certbot --nginx -d api.beachrex.com -d beachrex.com -d www.beachrex.com
```

---

## 7. Baza in PostGIS

### Shema
- PostgreSQL 15 + PostGIS 3.4
- 3 baze: `beachrex_prod`, `beachrex_uat`, `beachrex_dev`
- PostGIS razširitve + prostorske funkcije (`calculate_distance_km`, `beaches_within_radius`)
- Init skripta: `database/postgis/init-postgis.sql` in `init-postgis-multi.sql`

### Migracija baze
- `pg_dump` iz Laravel Clouda (PG17) → restore v VPS (PG15)
- UAT in DEV bazi imata polne podatke (3.774 plaž, 42.359 medijev)
- PROD baza je prazna — čaka na finalno migracijo

### Backup
- `/opt/beachrex/backups/` — ročni backupi
- Priporočeno: cron job za dnevni `pg_dump`

---

## 8. Kaj je narejeno ( Kronologija)

### 2026-09-01: Provisioning in UAT
- VPS provisioned (Ubuntu 26.04, Docker, Nginx, UFW, swap)
- SSL certifikati pridobljeni za UAT in DEV domene
- PostgreSQL + PostGIS + Redis kontejnerji zagnani
- Baza migrirana iz Laravel Clouda (337 MB pg_dump)
- UAT API in Frontend postavljena in verificirana
- DEV okolje postavljena iz UAT

### 2026-09-02: CI/CD
- Začetni poskus z GitLab CI/CD — odpovedano zaradi SSH key težav
- Preklop na GitHub Actions (bolj zanesljivo)
- SSH ključ generiran (RSA 2048 PEM), dodan v GitHub Secrets
- Backend pipeline: test (PostGIS service) + deploy
- Frontend pipeline: deploy (standalone build)
- Popravki: env_file poti, Dockerfile.prod commit, output:standalone commit, robots.ts env fallback

### 2026-09-03: Branch struktura in dokumentacija
- `dev`, `uat`, `main` branchi vzpostavljeni za oba repozitorija
- Branch → Okolje mapping implementiran v GitHub Actions
- Dokumentacija ustvarjena

---

## 9. Kaj manjka ( Naslednji koraki)

### PROD migracija
1. **DNS preklop:** `beachrex.com` in `api.beachrex.com` A record → `2.28.76.142`
2. **SSL certifikat:** `certbot --nginx -d api.beachrex.com -d beachrex.com -d www.beachrex.com`
3. **Nginx config:** Posodobiti `beachrex.conf` z PROD SSL bloki (trenutno samo HTTP)
4. **Baza:** Finalni `pg_dump` iz Laravel Clouda → restore v `beachrex_prod`
5. **`.env.prod`:** Posodobiti s pravimi PROD tajnostmi
6. **Deploy:** Push na `main` branch → GitHub Actions deploy na PROD
7. **Preverba:** `https://beachrex.com` in `https://api.beachrex.com/api/v1/beaches?limit=1`

### Ostalo
- **Backup cron:** Dnevni `pg_dump` za `beachrex_uat` in `beachrex_prod`
- **Frontend UAT/DEV branchi:** Push na `uat`/`dev` branch v frontend repu za testiranje
- **Monitoring:** Razmisliti o uptime monitoring (npr. UptimeRobot)
- **Log rotation:** Docker logs lahko zasedejo disk — nastaviti log rotation
