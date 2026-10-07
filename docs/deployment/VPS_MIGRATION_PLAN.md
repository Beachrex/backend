# Načrt migracije BeachRex infrastrukture na Hetzner VPS

> **2026-10-07:** Laravel Cloud no longer exists. Every step below that dumps from Laravel Cloud is **historical**. Prod launch follows [`docs/runbooks/prod-launch.md`](../runbooks/prod-launch.md), with the prod DB loaded from UAT.

> **Cilj:** Migracija celotne BeachRex infrastrukture (Frontend z Vercela, Backend z Laravel Clouda) na lasten Hetzner VPS strežnik.

**Datum:** 2026-09-01
**Status:** ✅ UAT in DEV okolje postavljena in delujeta (PROD sledi)
**Ciljni strežnik:** Hetzner CX33 (4 vCPU, 8 GB RAM, 80 GB SSD) — `2.28.76.142`

---

## 0. Povzetek trenutnega stanja

### Postavljena okolja (2026-09-01)

| Okolje | API | Frontend | SSL | Baza | Status |
|--------|-----|----------|-----|------|--------|
| **UAT** | `https://uat-api.beachrex.com` | `https://uat.beachrex.com` | ✅ | `beachrex_uat` (3.774 plaž, 42.359 medijev) | ✅ Deluje |
| **DEV** | `https://dev-api.beachrex.com` | `https://dev.beachrex.com` | ✅ | `beachrex_dev` (3.774 plaž, 42.359 medijev) | ✅ Deluje |
| **PROD** | `https://api.beachrex.com` | `https://beachrex.com` | čaka | `beachrex_prod` (prazna) | čaka DNS + migracijo |

### Izvedena dela
- ✅ Provisioning VPS (Ubuntu 26.04, Docker 29.7.2, Compose v5.5.0, Nginx 1.28.3, UFW, swap 4GB)
- ✅ SSL certifikati (Certbot) za uat-api, uat, dev-api, dev (veljavnost do 2026-11-30)
- ✅ PostgreSQL 15 + PostGIS 3.4 (3 baze: prod/uat/dev), Redis (3 logični DB-ji)
- ✅ `pg_dump` iz Laravel Clouda (PG17, 337 MB) → restore v `beachrex_uat` in `beachrex_dev`
- ✅ Meilisearch reindex (uat_ / dev_ prefix)
- ✅ GitLab CI/CD (`.gitlab-ci.yml`) za BR-api2 in Front/beachrex
- ✅ Deploy SSH ključ (`id_ed25519_gitlab_ci`) na VPS

### Komponente

| Komponenta | Trenutna lokacija | Tehnologija |
|------------|-------------------|-------------|
| Frontend | Vercel (čaka preklop) | Next.js 14, TypeScript, React 18, next-intl (14 jezikov), TanStack Query, Leaflet |
| Backend API | Laravel Cloud (čaka preklop) | Laravel 12, PHP 8.4, Filament 3 admin, Scout, Stripe, OpenAI, Socialite |
| Baza | Laravel Cloud (upravljan PostgreSQL 17) | PostgreSQL 15 + PostGIS 3.4 (na VPS) |
| Iskalnik | Namenski Hetzner CPX22 | Meilisearch na `https://search.beachrex.com` (obdržimo) |
| Statična hramba/slike | DigitalOcean Spaces | S3 + CDN (`s3.beachrex.com`, `beachrex.fra1.digitaloceanspaces.com`) (obdržimo) |
| Cache/Queue | VPS (Redis Alpine) | Redis (logični DB-ji: prod=0, uat=1, dev=2) |

**Obstoječa `docker-compose.yml`** (Laravel Sail) uporablja:
- `sail-8.4/app` image (PHP 8.4, build iz `vendor/laravel/sail/runtimes/8.4`)
- `postgis/postgis:15-3.4` z `init-postgis.sql` (PostGIS razširitve + prostorske funkcije `calculate_distance_km`, `beaches_within_radius`)
- `redis:alpine`
- volumne `sail-pgsql`, `sail-redis`
- omrežje `sail`

Nova postavitev mora ohraniti enake logične storitve in inicializacijske skripte (PostGIS).

---

## 1. Priprava strežnika (Provisioning)

### 1.1 Ustvarjanje strežnika
1. V Hetzner Cloud konzoli ustvari strežnik **CX33** (4 vCPU / 8 GB / 80 GB SSD), image **Ubuntu 24.04 LTS**, lokacija **Nürnberg/Falkenstein** (blizu DigitalOcean Spaces fra1 in obstoječega Meilisearch strežnika).
2. Dodaj SSH ključ; onemogoči geselski login.
3. Dodeli IPv4 (in po želji IPv6).

### 1.2 Bazna konfiguracija OS
```bash
ssh root@<VPS_IP>
apt update && apt upgrade -y
# Ustvari non-root uporabnika
adduser beachrex
usermod -aG sudo beachrex
rsync --archive --chown=beachrex:beachrex ~/.ssh /home/beachrex
# Onemogoči root login in geselski login
sed -i 's/^PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd
```

### 1.3 Požarni zid (UFW)
```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status verbose
```
> Porti 5432 (PostgreSQL), 6379 (Redis), 3000-3099 (Node SSR) naj NE bodo izpostavljeni javno — dostopni le znotraj Docker omrežij.

### 1.4 Swap (varovalo za 8 GB RAM pri 3 okoljih)
```bash
fallocate -l 4G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
sysctl vm.swappiness=10
echo 'vm.swappiness=10' >> /etc/sysctl.conf
```

### 1.5 Namestitev Docker + Docker Compose v2
```bash
# Docker Engine
curl -fsSL https://get.docker.com | sh
usermod -aG docker beachrex
# Preveri
docker --version
docker compose version
```

### 1.6 Optimizacija Docker daemon-a
Ustvari/uredi `/etc/docker/daemon.json` (omeji loge, da ne zapolnimo 80 GB SSD):
```json
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" },
  "default-address-pools": [{ "base": "172.20.0.0/16", "size": 24 }]
}
```
```bash
systemctl restart docker
```

### 1.7 Nginx reverse proxy + Certbot
Nginx teče **na host-u** (en primerek) kot vhodni proxy za vsa okolja in SSL terminacijo:
```bash
apt install -y nginx certbot python3-certbot-nginx
systemctl enable nginx
```

Struktura mape za deploy na strežniku:
```
/opt/beachrex/
├── docker-compose.yml        # skupne storitve (pg, redis, omrežja)
├── docker-compose.prod.yml   # prod override
├── docker-compose.stage.yml  # stage override
├── docker-compose.dev.yml    # dev override
├── envs/
│   ├── .env.prod
│   ├── .env.stage
│   └── .env.dev
├── api/                      # klon BR-api2 (backend)
├── front/                    # klon Front/beachrex (frontend)
└── nginx/                    # konfiguracije reverse proxy-ja
    ├── dev-api.conf
    ├── stage-api.conf
    ├── prod-api.conf
    ├── dev-front.conf
    ├── stage-front.conf
    └── prod-front.conf
```


### 1.8 Avtomatsko obnavljanje SSL
Certbot pridobi certifikate za vseh 6 poddomen (DNS A zapisi morajo kazati na VPS):
- `api.beachrex.com`, `stage-api.beachrex.com`, `dev-api.beachrex.com`
- `beachrex.com` / `www.beachrex.com`, `stage.beachrex.com`, `dev.beachrex.com`

```bash
certbot --nginx -d api.beachrex.com -d stage-api.beachrex.com -d dev-api.beachrex.com \
  -d beachrex.com -d www.beachrex.com -d stage.beachrex.com -d dev.beachrex.com \
  --agree-tos -m ops@beachrex.com --redirect --non-interactive
systemctl status certbot.timer
```

> Za `search.beachrex.com` (Meilisearch) certifikat ostane na obstoječem Meilisearch strežniku — se ne dotikamo.

---

## 2. Arhitektura okolij (Dev, Stage, Prod)

### 2.1 Strategija izolacije
1. **Ločena Docker omrežja** — `net_prod`, `net_stage`, `net_dev` (bridge). Kontejnerji enega okolja ne morejo dostopati do servisov drugega.
2. **Ločene baze znotraj istega PostgreSQL kontejnerja** — en `pgsql` kontejner (varčujemo RAM), tri baze: `beachrex_prod`, `beachrex_stage`, `beachrex_dev`. PostGIS razširitev se omogoči v vsaki posebej.
3. **Ločeni Redis DB-ji** — en `redis` kontejner z 16 logičnimi DB-ji; prod=0, stage=1, dev=2.
4. **Ločeni aplikacijski kontejnerji** — trije Laravel (php-fpm) in trije Node (Next.js) kontejnerji, vsak na svojem internem portu.
5. **Poddomene** — Nginx na hostu usmerja promet po `server_name` v ustrezen upstream.

### 2.2 Tabela internih portov (ne-izpostavljeni javno)

| Okolje | API (php-fpm) | Front (Node) | PgSQL baza | Redis DB |
|---------|---------------|--------------|------------|----------|
| Prod | 9001 | 3001 | beachrex_prod | 0 |
| Stage | 9002 | 3002 | beachrex_stage | 1 |
| Dev | 9003 | 3003 | beachrex_dev | 2 |

> Na hostu so odprti samo 80/443 (UFW). Vsi interni porti so znotraj Docker omrežij.

### 2.3 Skladnost z obstoječo logiko
- Backend še vedno uporablja `postgis/postgis:15-3.4` in `database/postgis/init-postgis.sql` (PostGIS + postgis_topology + funkciji `calculate_distance_km`, `beaches_within_radius`). Skripto prilagodimo, da iterira po vseh treh bazah (glej 2.5).
- Meilisearch ni v `docker-compose.yml` — ohranjamo povezavo do `https://search.beachrex.com`.
- `docker-compose.override.yml` (AMD64 za Apple Silicon) je **samo lokalni** razvoj; na strežniku se ne uporablja.

### 2.4 Upravljanje `.env` datotek
- Na strežniku: `/opt/beachrex/envs/.env.{prod,stage,dev}` — **nikoli** v git repu.
- `APP_URL` in `L5_SWAGGER_CONST_HOST` (že podprto glej `staging-server-config.md`):
  - prod: `https://api.beachrex.com`
  - stage: `https://stage-api.beachrex.com`
  - dev: `https://dev-api.beachrex.com`
- Skupne nastavitve (DigitalOcean Spaces, Meilisearch host) enake v vseh treh; razlikujejo se: `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `DB_DATABASE`, `REDIS_DB`, `SCOUT_PREFIX` (`prod_`, `stage_`, `dev_`), `MEILISEARCH_KEY` (ločen ključ na Meilisearch za vsako okolje).
- `SESSION_DOMAIN=.beachrex.com`, `SESSION_SECURE=true` za prod/stage (pripravljeno v `.env.example`).
- Tajnosti hranimo v **GitHub Actions secrets**, sinhroniziramo na VPS preko SSH (glej §4).
- Frontend `.env` vsebuje `NEXT_PUBLIC_API_ENDPOINT` in `NEXT_PUBLIC_BASE_URL` glede na `Front/beachrex/src/env.ts`: prod `beachrex.com`, stage `stage.beachrex.com`, dev `dev.beachrex.com`.

### 2.5 Skupni `docker-compose.yml` na VPS (osnovne storitve)
> Naslednja datoteka **nadomešča** Sail-ov `docker-compose.yml` za produkcijsko okolje. Lokalni razvoj še naprej uporablja obstoječi Sail compose + override.

```yaml
# /opt/beachrex/docker-compose.yml
services:
  pgsql:
    image: postgis/postgis:15-3.4
    restart: unless-stopped
    env_file: [envs/.env.prod]
    environment:
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      POSTGRES_DB: beachrex_prod
    volumes:
      - pgsql-data:/var/lib/postgresql/data
      - ./api/database/postgis/init-postgis-multi.sql:/docker-entrypoint-initdb.d/20-init-postgis.sql
    networks: [net_prod, net_stage, net_dev]
    healthcheck:
      test: ["CMD", "pg_isready", "-q", "-U", "${DB_USERNAME}"]
      retries: 5
      timeout: 5s

  redis:
    image: redis:alpine
    restart: unless-stopped
    command: redis-server --databases 16 --save 60 1 --loglevel warning
    volumes: [redis-data:/data]
    networks: [net_prod, net_stage, net_dev]
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      retries: 3
      timeout: 5s

  # ---- PROD ----
  api_prod:
    build: { context: ./api, dockerfile: Dockerfile.prod }
    restart: unless-stopped
    env_file: [envs/.env.prod]
    environment: { DB_DATABASE: beachrex_prod, REDIS_DB: 0 }
    networks: [net_prod]
    depends_on: [pgsql, redis]

  front_prod:
    image: node:20-alpine
    working_dir: /app
    command: ["yarn", "start", "-p", "3001"]
    env_file: [envs/.env.front.prod]
    volumes: ["./front:/app"]
    networks: [net_prod]
    restart: unless-stopped

  # ---- STAGE ----
  api_stage:
    build: { context: ./api, dockerfile: Dockerfile.prod }
    restart: unless-stopped
    env_file: [envs/.env.stage]
    environment: { DB_DATABASE: beachrex_stage, REDIS_DB: 1 }
    networks: [net_stage]
    depends_on: [pgsql, redis]
  front_stage:
    image: node:20-alpine
    working_dir: /app
    command: ["yarn", "start", "-p", "3002"]
    env_file: [envs/.env.front.stage]
    volumes: ["./front:/app"]
    networks: [net_stage]
    restart: unless-stopped

  # ---- DEV ----
  api_dev:
    build: { context: ./api, dockerfile: Dockerfile.prod }
    restart: unless-stopped
    env_file: [envs/.env.dev]
    environment: { DB_DATABASE: beachrex_dev, REDIS_DB: 2 }
    networks: [net_dev]
    depends_on: [pgsql, redis]
  front_dev:
    image: node:20-alpine
    working_dir: /app
    command: ["yarn", "start", "-p", "3003"]
    env_file: [envs/.env.front.dev]
    volumes: ["./front:/app"]
    networks: [net_dev]
    restart: unless-stopped

networks:
  net_prod:  { driver: bridge }
  net_stage: { driver: bridge }
  net_dev:   { driver: bridge }
volumes:
  pgsql-data: { driver: local }
  redis-data: { driver: local }
```

`init-postgis-multi.sql` (nova različica obstoječe skripte `database/postgis/init-postgis.sql`):
```sql
\connect beachrex_prod
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
-- + funkciji calculate_distance_km, beaches_within_radius iz obstoječe skripte
\connect beachrex_stage
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
-- + funkciji ...
\connect beachrex_dev
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS postgis_topology;
-- + funkciji ...
```
> Ker `docker-entrypoint-initdb.d` teče le ob prvem zagonu PGSQL, morajo biti baze `beachrex_stage`/`beachrex_dev` ustvarjene v isti inicializacijski skripti (`CREATE DATABASE beachrex_stage;`) **pred** `\connect`. Drugače jih ustvarimo ročno po prvem zagonu in nato ročno omogočimo PostGIS.

### 2.6 Backend `Dockerfile.prod` (novo, v `api/`)
```dockerfile
FROM php:8.4-fpm-alpine
RUN apk add --no-cache libpq-dev postgresql-dev git unzip libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql zip pcntl opcache
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
```
> PHP 8.4 (skladno z `composer.json` `php: ^8.2` in Sail runtime 8.4), opcache za produkcijo.

### 2.7 Nginx reverse-proxy primer (host, `/etc/nginx/sites-available/prod-api.conf`)
```nginx
server {
    listen 443 ssl http2;
    server_name api.beachrex.com;
    ssl_certificate     /etc/letsencrypt/live/api.beachrex.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.beachrex.com/privkey.pem;

    client_max_body_size 25M;            # za upload slik v Filament
    location / {
        proxy_pass http://127.0.0.1:9001;  # api_prod php-fpm (ali nginx v kontejnerju)
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```
> Za php-fpm potrebujemo nginx **znotraj** `api_*` kontejnerja ali poseben web kontejner; alternativno uporabimo `php:8.4-apache` ali dodamo nginx service na 9001. Priporočeno: dodati nginx service v vsako okolje (gleda statične Laravel public datoteke + proxy na php-fpm).

---

## 3. Integracija z zunanjimi storitvami

### 3.1 Meilisearch (obstoječi namenski Hetzner strežnik)
- **Host:** `https://search.beachrex.com` (nepremenjen, glej `MEILISEARCH_DEPLOYMENT_SUMMARY.md`).
- V `.env.{prod,stage,dev}`:
  ```env
  SCOUT_DRIVER=meilisearch
  SCOUT_QUEUE=true                # produkcija/stage; false za dev
  SCOUT_PREFIX=prod_              # oziroma stage_, dev_
  MEILISEARCH_HOST=https://search.beachrex.com
  MEILISEARCH_KEY=<KEY>           # ločen API ključ na Meilisearch za vsako okolje
  MEILISEARCH_NO_ANALYTICS=true
  MEILISEARCH_ENV=production
  ```
- Po prvem deploy-u vsakega okolja: `docker compose exec api_prod php artisan search:index`.
- Preveri: `curl https://search.beachrex.com/health` → `{"status":"available"}`.
- Mreža: VPS → `search.beachrex.com` čez javni HTTPS (Meilisearch že sluša le na 443 preko Nginx + SSL, varno).

### 3.2 DigitalOcean Spaces (S3/CDN) — nespremenjeno
- V `.env` (vsa tri okolja, vrednosti iz `.env.example`):
  ```env
  DO_SPACES_KEY=...
  DO_SPACES_SECRET=...
  DO_SPACES_ENDPOINT=https://fra1.digitaloceanspaces.com
  DO_SPACES_REGION=fra1
  DO_SPACES_BUCKET=beachrex
  DO_SPACES_CDN_ENDPOINT=https://s3.beachrex.com
  DO_SPACES_CDN_ENDPOINT_ADMIN=https://beachrex.fra1.digitaloceanspaces.com/public
  ```
- Frontend `next.config.js` že dovoljuje `beachrex.fra1.digitaloceanspaces.com`, `s3.beachrex.com`, `dev-api.beachrex.com` v `images.remotePatterns` — ohranjamo.
- `images.unoptimized: true` (Imagor) — ohranjamo.
- Upload poteka preko Filament admin panela; povezava S3 med API kontejnerjem in DigitalOcean je neposredna (fra1 blizu Nürnberga).

---

## 4. CI/CD cevovod (GitHub Actions)

### 4.1 Strategija vej
| Veja | Ciljno okolje | Domene |
|------|---------------|--------|
| `dev` | dev | `dev-api.beachrex.com`, `dev.beachrex.com` |
| `stage` | stage | `stage-api.beachrex.com`, `stage.beachrex.com` |
| `main` | prod | `api.beachrex.com`, `beachrex.com` |

> Backend in frontend lahko v istem repozitoriju (monorepo `v2`) ali dveh. Tukaj predpostavimo **monorepo** z mapama `BR-api2` in `Front/beachrex`; cevovod se sproži glede na spremembe v posamezni mapi (`paths:` filter).

### 4.2 Tajnosti (GitHub Secrets)
| Secret | Opis |
|--------|------|
| `VPS_HOST` | IP/hostname VPS |
| `VPS_SSH_KEY` | privatni SSH ključ deploy uporabnika |
| `VPS_USER` | `beachrex` |
| `ENV_PROD_API`, `ENV_STAGE_API`, `ENV_DEV_API` | vsebina `.env` za API po okoljih |
| `ENV_PROD_FRONT`, `ENV_STAGE_FRONT`, `ENV_DEV_FRONT` | `.env` za frontend |
| `MEILISEARCH_KEY_PROD`, `MEILISEARCH_KEY_STAGE`, `MEILISEARCH_KEY_DEV` | ključi |
| `DO_SPACES_KEY`, `DO_SPACES_SECRET` | S3 |
| `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | plačila |
| `OPENAI_API_KEY`, `GOOGLE_CLIENT_*` | integracije |

### 4.3 Workflow datoteka `.github/workflows/deploy.yml` (osnova)
```yaml
name: Deploy
on:
  push:
    branches: [dev, stage, main]
    paths:
      - "BR-api2/**"
      - "Front/beachrex/**"
      - ".github/workflows/deploy.yml"

concurrency:
  group: deploy-${{ github.ref }}
  cancel-in-progress: false

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Map branch -> environment
        id: env
        run: |
          case "${GITHUB_REF##*/}" in
            main)  echo "env=prod"  >> $GITHUB_OUTPUT; echo "api=api_prod"  >> $GITHUB_OUTPUT; echo "front=front_prod"  >> $GITHUB_OUTPUT ;;
            stage) echo "env=stage" >> $GITHUB_OUTPUT; echo "api=api_stage" >> $GITHUB_OUTPUT; echo "front=front_stage" >> $GITHUB_OUTPUT ;;
            dev)   echo "env=dev"   >> $GITHUB_OUTPUT; echo "api=api_dev"   >> $GITHUB_OUTPUT; echo "front=front_dev"   >> $GITHUB_OUTPUT ;;
          esac

      - name: Write API .env from secret
        run: printf '%s' "${{ secrets[format('ENV_{0}_API', steps.env.outputs.env)] }}" > api.env

      - name: Setup SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.VPS_SSH_KEY }}" > ~/.ssh/id_ed25519
          chmod 600 ~/.ssh/id_ed25519
          ssh-keyscan -H ${{ secrets.VPS_HOST }} >> ~/.ssh/known_hosts

      - name: Sync code to VPS
        run: |
          rsync -az --delete \
            --exclude '.git' --exclude 'node_modules' --exclude 'vendor' \
            --exclude 'storage/logs/*' --exclude 'storage/framework/cache/*' \
            BR-api2/ ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }}:/opt/beachrex/api/
          rsync -az --delete \
            --exclude '.git' --exclude 'node_modules' --exclude '.next' \
            Front/beachrex/ ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }}:/opt/beachrex/front/

      - name: Upload .env
        run: scp api.env ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }}:/opt/beachrex/envs/.env.${{ steps.env.outputs.env }}

      - name: Build & migrate on VPS
        run: |
          ssh ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }} << EOF
            set -e
            cd /opt/beachrex
            docker compose build ${{ steps.env.outputs.api }} ${{ steps.env.outputs.front }}
            docker compose up -d pgsql redis
            docker compose up -d ${{ steps.env.outputs.api }} ${{ steps.env.outputs.front }}

            # frontend build (zapolni .next) pred zagonom
            docker compose exec ${{ steps.env.outputs.front }} yarn install --frozen-lockfile
            docker compose exec ${{ steps.env.outputs.front }} yarn build

            # backend
            docker compose exec ${{ steps.env.outputs.api }} composer install --no-dev --optimize-autoloader
            docker compose exec ${{ steps.env.outputs.api }} php artisan migrate --force
            docker compose exec ${{ steps.env.outputs.api }} php artisan config:cache
            docker compose exec ${{ steps.env.outputs.api }} php artisan route:cache
            docker compose exec ${{ steps.env.outputs.api }} php artisan cache:clear
            docker compose exec ${{ steps.env.outputs.api }} php artisan search:index || true
            docker compose exec ${{ steps.env.outputs.api }} php artisan l5-swagger:generate || true
          EOF

      - name: Reload Nginx
        run: ssh ${{ secrets.VPS_USER }}@${{ secrets.VPS_HOST }} "sudo systemctl reload nginx"
```

### 4.4 Testi pred deploy-jem (job `test` pred `deploy`)
- Backend: `php artisan test` (PHPUnit, glej `phpunit.xml`); posebej `NearbyCitiesApiTest`, `RegionApiTest`.
- Frontend: `yarn lint` + `yarn test:e2e --project=chromium` (Playwright, glej `playwright.config.ts`).
- Backend build: `composer install` v CI pred rsync, da preverimo odvisnosti.
- Rezultat: deploy se sproži šele, ko `test` job uspe.

### 4.5 Rollback
- Vsak deploy ustvari tag `deploy-<env>-<sha>` na git-u (znotraj workflowa).
- Rollback: checkout prejšnjega tagga + ponoven deploy; `php artisan migrate:rollback` ročno, če so bile migrations.
- Slike: `docker compose run --rm api_prod php artisan migrate:status` za preverjanje.

---

## 5. Migracija podatkov (PostgreSQL z Laravel Clouda)

### 5.1 Cilj
Varn prenos produkcijske baze iz Laravel Cloud PostgreSQL na novi VPS `beachrex_prod` z **minimalnim downtime-om** (< 5 minut). PostGIS podatki (geometrije) morajo ostati nedotaknjeni.

### 5.2 Pred-priprava
1. **Popolna prva kopija (topli podatki)** — iz Laravel Cloud:
   ```bash
   # z lokalnega računalnika (z dostopom do Laravel Cloud DB)
   pg_dump --format=custom --no-owner --no-privileges \
     --host=<LC_DB_HOST> --port=5432 --username=<LC_DB_USER> --dbname=<LC_DB_NAME> \
     --file=beachrex_prod.dump
   ```
   > `--format=custom` (pg_dump -Fc) omogoči paralelen restore in selektivno obnavljanje. PostGIS geometrije se serializirajo pravilno.
2. Preveri velikost dump-a: `ls -lh beachrex_prod.dump`.
3. Prenesi na VPS: `scp beachrex_prod.dump beachrex@<VPS>:/tmp/`.

### 5.3 Pripravitev ciljne baze na VPS
```bash
ssh beachrex@<VPS>
cd /opt/beachrex
docker compose up -d pgsql
# Ustvari produkcijsko bazo + uporabnika (če še ne obstaja)
docker compose exec pgsql psql -U $DB_USERNAME -d postgres -c \
  "CREATE DATABASE beachrex_prod;"
docker compose exec pgsql psql -U $DB_USERNAME -d beachrex_prod -c \
  "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS postgis_topology;"
```

### 5.4 Prvi restore (test, še ne v produkciji)
```bash
docker cp /tmp/beachrex_prod.dump pgsql:/tmp/dump
docker compose exec pgsql pg_restore --dbname=beachrex_prod --no-owner --no-privileges \
  --jobs=4 --clean --if-exists /tmp/dump
# Preveri
docker compose exec pgsql psql -U $DB_USERNAME -d beachrex_prod -c \
  "SELECT count(*) FROM beach;"
docker compose exec pgsql psql -U $DB_USERNAME -d beachrex_prod -c \
  "SELECT PostGIS_Version();"
```
> Če `pg_restore` javi napake o obstoječih objektih, dodaj `--create` ali najprej `DROP DATABASE beachrex_prod; CREATE DATABASE ...`.

### 5.5 Replikacija "delta" (minimalni downtime)
1. Na Laravel Cloud DB omogoči logično replikacijo ali pa naredi **drugi pg_dump tik pred stikom**. Najenostavneje: naredi replay z `pg_dump` z `--start-time` ali ročno zaznaj čas zadnje spremembe.
2. **Alternativa (priporočeno za manjše baze ~50 MB):**
   - Po prvi kopiji zaznaj `max(id)` ključnih tabel (`beach`, `media`, ...).
   - Pred stikom naredi drugi dump, ga restore-aj v začasno bazo `beachrex_prod_new`.
   - Preveri integralnost: `SELECT count(*)`, `SELECT PostGIS_Version();`, nekaj prostorskih poizvedb (`ST_Distance`).
   - V nadzorovanem oknu (načrtovani downtime):
     ```bash
     docker compose exec pgsql psql -U $DB_USERNAME -d postgres << SQL
     ALTER DATABASE beachrex_prod  RENAME TO beachrex_prod_old;
     ALTER DATABASE beachrex_prod_new RENAME TO beachrex_prod;
     SQL
     docker compose restart api_prod
     ```
   - Po uspehu zbriši `beachrex_prod_old` po nekaj urah opazovanja.

### 5.6 Preklop DNS
1. Trenutno `api.beachrex.com` kaže na Laravel Cloud.
2. Po uspešnem restore-ju in testu na VPS spremeni A zapis v DNS (Cloudflare) na VPS IP.
3. **Pomembno:** Po `DEV_HANDOFF.md` za Meilisearch je Cloudflare proxy status "DNS only" (siva oblak) — enako velja tukaj, saj Certbot na VPS-u prevzame SSL.
4. TTL čim krajši (60s) nekaj ur pred preklopom; po preklopu povrni na 1h.
5. Po preklopu:
   - `curl https://api.beachrex.com/api/v1/beaches?limit=1 | jq '.data[0] | {id, name, city_name}'`
   - `curl -H "Accept-Language: hr" https://api.beachrex.com/api/v1/spatial/beaches/within-radius?latitude=43.5&longitude=16.4&radius=50 | jq '.meta.location'`
   - Preveri `php artisan beaches:check-missing-images` (glej `DEV_HANDOFF.md`), da se slike še vedno berejo z DigitalOcean Spaces.
   - Re-index: `docker compose exec api_prod php artisan search:index --flush`.

### 5.7 Varnostne kopije na VPS (cron)
```bash
# /etc/cron.d/beachrex-pgdump
15 3 * * * beachrex cd /opt/beachrex && \
  docker compose exec -T pgsql pg_dump -U $DB_USERNAME -Fc beachrex_prod \
  > /var/backups/beachrex/pgdump-$(date +\%F).dump && \
  find /var/backups/beachrex -name 'pgdump-*.dump' -mtime +7 -delete
```
> Poleg tega priporočamo **Hetzner Storage Box** (samodejno dnevno backup-ovanje celotnega VPS-a preko HetznerCloud backup funkcije ~20 % cene strežnika).

### 5.8 Po-migracijski pregled
- [ ] `php artisan migrate:status` — vse migracije označene kot "Ran".
- [ ] `php artisan tinker >>> PostGIS_Version();` deluje.
- [ ] Spatial endpointi vračajo rezultate (within-radius, bounding-box, nearest, nearby-cities).
- [ ] Filament admin (`/admin`) dostopen in login deluje (Sanctum/Google OAuth — preveri `GOOGLE_REDIRECT_URI=https://api.beachrex.com/api/v1/auth/google/callback` v `.env.prod`).
- [ ] Stripe webhook URL posodobljen v Stripe nadzorni plošči na `https://api.beachrex.com/api/v1/stripe/webhook`.
- [ ] Frontend na Vercelu še vedno deluje, dokler ne preklopimo tudi `beachrex.com` na VPS (enako DNS-tar cesto).
- [ ] SSL certifikati veljavni za vseh 6 poddomen (`curl -vI https://api.beachrex.com`).

---

## 6. Zaporedje izvedbe migracije (povzetek)

1. **Teden 0 — Priprava**
   - Provisioning VPS (§1.1–1.7), SSL (§1.8), DNS A zapisi za dev/stage poddomene.
   - Pushaj `Dockerfile.prod`, `docker-compose.yml` (VPS različica) in `init-postgis-multi.sql` v `dev` vejo.
2. **Faza 1 — Dev okolje**
   - CI deploy na `dev` vejo → `dev-api.beachrex.com` in `dev.beachrex.com` delujeta.
   - Test: API, admin, frontend, iskanje.
3. **Faza 2 — Stage okolje + prva migracija baze**
   - Restore prve kopije baze v `beachrex_stage` (§5.4).
   - Deploy na `stage` vejo → `stage-api.beachrex.com` in `stage.beachrex.com`.
   - UAT + Playwright E2E na stage domeni.
4. **Faza 3 — Produkcija (z načrtovanim downtime-om)**
   - Drugi dump (§5.5), restore v `beachrex_prod_new`, preimenuj v `beachrex_prod`.
   - Preklop DNS za `api.beachrex.com` in `beachrex.com` na VPS (§5.6).
   - Opazovanje 24–48 h; Vercel in Laravel Cloud ohranimo kot toplo rezervo teden dni.
5. **Faza 4 — Dekomisija**
   - Po uspešnem tednu deaktiviraj Vercel projekt in Laravel Cloud aplikacijo.
   - Ohrani Hetzner Meilisearch + DigitalOcean Spaces nespremenjena.

---

## 7. Stroški

| Komponenta | Pred (mesečno) | Po (mesečno) |
|------------|----------------|--------------|
| Frontend | Vercel Pro (~€20) | vključeno v VPS |
| Backend | Laravel Cloud (~€20–40) | vključeno v VPS |
| PostgreSQL | v Laravel Cloud | vključeno v VPS |
| Redis | v Laravel Cloud | vključeno v VPS |
| Meilisearch | Hetzner CPX22 (~€6) | Hetzner CPX22 (~€6) |
| VPS CX33 | — | ~€7 |
| Hetzner backup | — | ~€1,5 |
| DigitalOcean Spaces | ~€5 | ~€5 |
| **Skupaj** | **~€51–71** | **~€19,5** |

> Neto prihravek ~€30–50/mesečno + poln nadzor nad okolji in podatki.

---

**Avtor:** Augment Agent
**Verzija:** 1.0
**Zadnja posodobitev:** 2026-09-01