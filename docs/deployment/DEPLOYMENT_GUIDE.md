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
| Branch | Okolje | API URL | Frontend URL | Admin URL |
|---|---|---|---|---|
| `dev` | DEV | `https://dev-api.beachrex.com` | `https://dev.beachrex.com` | `https://dock-dev.beachrex.com/admin` |
| `uat` | UAT | `https://uat-api.beachrex.com` | `https://uat.beachrex.com` | `https://dock-uat.beachrex.com/admin` |
| `main` | PROD | `https://api.beachrex.com` | `https://beachrex.com` | `https://dock.beachrex.com/admin` |

> **Pravilo:** Push na `dev` → deploy na DEV. Push na `uat` → deploy na UAT. Push na `main` → deploy na PROD (za PROD je priporočljiv manual trigger preko GitHub Actions UI).
>
> **Admin panel:** Vsako okolje ima svoj admin panel na lastni `dock*.beachrex.com` domeni, ki je domensko omejena v Filamentu preko `FILAMENT_DOMAIN` env variable. Sprememba v `dev` adminu **ne vpliva** na UAT ali PROD admin — vsak gre v svojo bazo (`beachrex_dev`/`beachrex_uat`/`beachrex_prod`).

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
   - `rsync` kode na `/opt/beachrex/api_{env}/` (npr. `api_dev/`, `api_uat/`, `api_prod/`)
   - `rsync` deploy datotek na `/opt/beachrex/deploy/` (skupno)
   - `docker compose build` + `up -d` + `migrate` + `cache:clear`
   - Reload host Nginx

### Frontend pipeline
1. **Deploy job** (brez test joba):
   - `rsync` kode na `/opt/beachrex/front_{env}/` (npr. `front_dev/`, `front_uat/`, `front_prod/`)
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

> **POMEMBNO:** Vsako okolje ima svoj direktorij z lastno kodo. Sprememba na `dev` branchu **ne vpliva** na UAT ali PROD. Prej so vsa okolja delila isti `api/` in `front/` direktorij — to je bilo popravljeno 2026-09-03.

```
/opt/beachrex/
├── api_prod/               # Backend koda — PROD (main branch)
├── api_uat/                # Backend koda — UAT (uat branch)
├── api_dev/                # Backend koda — DEV (dev branch)
├── front_prod/             # Frontend koda — PROD (main branch)
├── front_uat/              # Frontend koda — UAT (uat branch)
├── front_dev/              # Frontend koda — DEV (dev branch)
├── deploy/                 # Docker Compose + Nginx configs (skupno)
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
>
> **rsync cilji (GitHub Actions):**
> - Backend `dev` push → `rsync` v `/opt/beachrex/api_dev/`
> - Backend `uat` push → `rsync` v `/opt/beachrex/api_uat/`
> - Backend `main` push → `rsync` v `/opt/beachrex/api_prod/`
> - Frontend analogno v `front_{env}/`

---

## 5. Docker Compose

### `deploy/docker-compose.vps.yml`
- Ena datoteka za vsa tri okolja
- Skupne storitve: `pgsql` (PostGIS), `redis`
- Per-okolje: `api_{env}`, `web_{env}`, `front_{env}`
- `env_file` poti so `../envs/.env.{env}` (relativno od `deploy/`)
- **Build context (ločen per okolje):** `../api_{env}` in `../front_{env}`
- Porti: API 8081-8083, Frontend 3001-3003 (vsi na `127.0.0.1`)
- **Concurrency:** GitHub Actions `concurrency.group: deploy-backend` / `deploy-front` serializira deploye, da prepreči hkratni rsync v isti direktorij

### Ločitev baz in Redis
| Resource | Kontejner | Ločitev |
|---|---|---|
| PostgreSQL + PostGIS | 1 skupni `pgsql` | 3 logične baze: `beachrex_prod`, `beachrex_uat`, `beachrex_dev` |
| Redis | 1 skupni `redis` | 3 logični DB indeksi: prod=0, uat=1, dev=2 |

> **Zakaj skupni kontejner?** En PostgreSQL proces porabi manj RAM-a kot trije ločeni (~100–200 MB na proces). Podatki so med seboj popolnoma ločeni — vsaka aplikacija se poveže samo na svojo bazo. Za popolno fizično ločitev (compliance) bi razdelili na tri `pgsql_*` kontejnerje.

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
- Admin paneli: `dock.beachrex.com` (PROD), `dock-uat.beachrex.com` (UAT), `dock-dev.beachrex.com` (DEV)
- HTTP → HTTPS redirect za vse domene
- Proxy na Docker porte (8081-8083 za API, 3001-3003 za frontend)

### SSL certifikati
```bash
# UAT (že pridobljen)
certbot --nginx -d uat-api.beachrex.com -d uat.beachrex.com

# DEV (že pridobljen)
certbot --nginx -d dev-api.beachrex.com -d dev.beachrex.com

# Admin paneli (že pridobljeni)
certbot --nginx -d dock.beachrex.com
certbot --nginx -d dock-uat.beachrex.com
certbot --nginx -d dock-dev.beachrex.com

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

### 2026-09-03: Branch struktura, okoljska ločitev in dokumentacija
- `dev`, `uat`, `main` branchi vzpostavljeni za oba repozitorija
- Branch → Okolje mapping implementiran v GitHub Actions
- **Per-okoljska ločitev kode:** vsako okolje ima svoj direktorij (`api_prod/uat/dev`, `front_prod/uat/dev`) — prej so vsa okolja delila isti `api/` in `front/` direktorij, kar je povzročalo race condition (zadnji deploy je prepisal kodo vseh okolij)
- `concurrency.group` spremenjen v globalnega (`deploy-backend` / `deploy-front`), da prepreči hkratne rsync deploye
- `docker-compose.vps.yml` build contexti posodobljeni na `../api_{env}` in `../front_{env}`
- GitHub Actions `rsync` cilji posodobljeni na `/opt/beachrex/api_{env}/` in `/opt/beachrex/front_{env}/`
- VPS direktoriji ustvarjeni (`cp -a api api_{env}`, `cp -a front front_{env}`)
- **TrustProxies fix:** `$middleware->trustProxies(at: '*')` dodan v `bootstrap/app.php`, da Laravel upošteva `X-Forwarded-Proto` in generira HTTPS URL-je za assete (popravljeno Mixed Content napako v Filament adminu)
- **Per-okoljski admin paneli:** `BrAdminPanelProvider::getDomain()` spremenjen, da bere `FILAMENT_DOMAIN` env variable namesto hardcoded `dock.beachrex.com` pri `APP_ENV=production`. Vsako okolje ima svojo admin domeno:
  - `dock-dev.beachrex.com` → DEV admin → `beachrex_dev` baza
  - `dock-uat.beachrex.com` → UAT admin → `beachrex_uat` baza
  - `dock.beachrex.com` → PROD admin → `beachrex_prod` baza
- SSL certifikati pridobljeni za `dock.beachrex.com`, `dock-uat.beachrex.com`, `dock-dev.beachrex.com`
- Nginx `beachrex.conf` posodobljen z HTTPS server bloki za vse tri admin domene
- Dokumentacija ustvarjena in posodobljena

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
