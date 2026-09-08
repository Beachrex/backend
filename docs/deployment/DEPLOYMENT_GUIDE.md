# BeachRex — VPS Deployment Guide

> **Cilj tega dokumenta:** Vsak razvijalec, ki nadaljuje delo na projektu, ve natančno kje smo, kaj je narejeno in kako deployati.

**Zadnja posodobitev:** 2026-09-08
**Status:** ✅ DEV, UAT in PROD admin paneli delujejo. Skalabilnost cache popravljena. PROD frontend/API čakata na DNS preklop.

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
| `dev` | DEV | `https://dev-api.beachrex.com` | `https://dev.beachrex.com` | `https://dock-dev.beachrex.com` |
| `uat` | UAT | `https://uat-api.beachrex.com` | `https://uat.beachrex.com` | `https://dock-uat.beachrex.com` |
| `main` | PROD | `https://api.beachrex.com` | `https://beachrex.com` | `https://dock.beachrex.com` |

> **Pravilo:** Push na `dev` → deploy na DEV. Push na `uat` → deploy na UAT. Push na `main` → deploy na PROD (za PROD je priporočljiv manual trigger preko GitHub Actions UI).
>
> **Admin panel:** Vsako okolje ima svoj admin panel na lastni `dock*.beachrex.com` domeni (dostop na **root** domene, brez `/admin` končnice — npr. `https://dock-dev.beachrex.com/`), ki je domensko omejena v Filamentu preko `FILAMENT_DOMAIN` env variable. Sprememba v `dev` adminu **ne vpliva** na UAT ali PROD admin — vsak gre v svojo bazo (`beachrex_dev`/`beachrex_uat`/`beachrex_prod`).

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
   - `docker compose build` z `--env-file` za **oba** env file (backend `.env.{env}` + frontend `.env.front.{env}`), da se `NEXT_PUBLIC_*` variable pravilno substituirajo v `build.args`
   - `docker compose up -d --force-recreate`
   - Reload host Nginx

> **Kritično:** `NEXT_PUBLIC_*` env variable se v Next.js inline-a ob **build time**, ne runtime. Zato mora `docker-compose.vps.yml` vsebovati `build.args` sekcijo za vsak `front_*` service, ki sklicuje `${NEXT_PUBLIC_*}`. Frontend workflow naloži oba env file preko `--env-file`, da compose lahko substituira te variable. Brez tega bi vsi frontend kontejnerji uporabljali fallback `http://localhost:8000` kot API URL.

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

> **Zakaj skupni kontejner?** En PostgreSQL proces porabi manj RAM-a kot trije ločeni (~100–200 MB na proces). Podatki so med seboj popolnoma ločeni — vsaka aplikacija se poveče samo na svojo bazo. Za popolno fizično ločitev (compliance) bi razdelili na tri `pgsql_*` kontejnerje.
>
> **Kritično za Redis:** V `config/database.php` morata tako `default` kot `cache` Redis konekciji uporabljati `env('REDIS_DB')`, **ne** `env('REDIS_CACHE_DB', '1')`. Če je `cache` konekcija hardcodirana na DB 1, bosta DEV (REDIS_DB=2) in UAT (REDIS_DB=1) delila cache, kar povzroča cross-env podatke (sprememba v DEV adminu postane vidna na UAT frontendu).

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

## 8. Okoljska ločitev in Cache invalidacija

### Okoljska ločitev (3 sloji)

| Sloj | Kako je ločeno | Datoteka |
|---|---|---|
| **Koda** | Vsako okolje ima svoj direktorij na VPS (`api_{env}/`, `front_{env}/`) | `docker-compose.vps.yml` (build context) |
| **Baza** | 3 logične baze v enem PostgreSQL kontejnerju | `.env.{env}` (`DB_DATABASE=beachrex_{env}`) |
| **Redis cache** | 3 logični DB indeksi v enem Redis kontejnerju | `.env.{env}` (`REDIS_DB={0|1|2}`) + `config/database.php` |
| **Admin panel** | 3 ločene domene, vsaka domensko omejena v Filamentu | `.env.{env}` (`FILAMENT_DOMAIN=dock*.beachrex.com`) |
| **Frontend API URL** | `NEXT_PUBLIC_*` variable inline-ane ob build time | `.env.front.{env}` + `docker-compose.vps.yml` (build.args) |

### Cache invalidacija (admin → frontend)

Ko administrator shrani spremembo v Filament adminu, se Redis cache avtomatsko počisti preko model eventov:

```
Admin save → FirstPageContentTranslation::saved() event
           → Cache::forget("first_page_content:index:{locale}") za vseh 14 jezikov
           → Cache::forget("first_page_content:show:{code}:{locale}") za vseh 14 jezikov
           → Naslednji API klic pridobi sveže podatke iz baze
```

**Modeli z cache invalidacijo:**
- `FirstPageContent` — `Cacheable` trait (čisti reference cache)
- `FirstPageContentTranslation` — `booted()` saved/deleted eventi (čisti index/show cache)
- `StaticPage` — `booted()` saved/deleted eventi (čisti footer/show cache)
- `StaticPageTranslation` — `booted()` saved/deleted eventi (delegira na StaticPage)

### Next.js renderiranje

| Stran | Način | Zakaj |
|---|---|---|
| Homepage (`/`) | `force-dynamic` (SSR vsak request) | Admin spremembe morajo biti takoj vidne |
| Beach detail | ISR `revalidate = 3600` (1h) | Plaže se redko spreminjajo |
| Continent/Country | ISR `revalidate = 3600` (1h) | Geografski podatki so stabilni |
| Sitemap | ISR `revalidate = 86400` (24h) | SEO, redko se spreminja |

### React Query (client-side cache)

`useFirstPageContent` hook ima `staleTime: 0` + `refetchOnMount: true`, kar pomeni, da brskalnik vedno pridobi sveže podatke ob obisku strani. Laravel API še vedno cache-a DB query v Redis (invalidiran ob admin save), tako da je response hiter.

> **Pomembno:** V frontend kodi **ne sme** biti hardcodiran `https://dev-api.beachrex.com` kot fallback za `NEXT_PUBLIC_API_ENDPOINT`. Vsi fallback-i morajo biti `http://localhost:8000` (samo za lokalni dev). Hardcodiran DEV API URL se inline-a v build UAT in PROD kontejnerjev, kar povzroča cross-env API klice.

---

## 8b. Cache skalabilnost (Redis + PHP memory)

### Problem

`BeachController::index()` je cache-a **celotne Eloquent kolekcije** z 8+ eager loading relacijami v Redis. Laravel `Cache::remember` uporabi `serialize()`, ki shrani cele model objekte z vsemi relacijami, atributi, metapodatki.

**Pred popravkom:**
| Metrika | Vrednost | Problem |
|---|---|---|
| Največji Redis ključ | 28 MB (`beaches:index:en:continent_code=EU:country=ES`) | Eloquent model + 8 relacij serializirano |
| Skupna Redis poraba (DEV) | 204 MB (652 ključev) | 14 jezikov × več filter kombinacij |
| PHP `unserialize()` poraba | 200MB+ na ključ | 28MB serializirano → 200MB+ v PHP pomnilniku |
| Rezultat | `Allowed memory size of 268435456 bytes exhausted` | HTTP 500, API crash, frontend nedostopen |

### Rešitev

Cache-a se **že transformiran JSON array** (preko `BeachResource::collection()->resolve()`), ne pa surovih Eloquent modelov.

```php
// PREJ (ne-skalabilno):
$beaches = $this->cacheRemember($cacheKey, $ttl, function () {
    return Beach::with([...8 relacij...])->limit(5000)->get();
    // ← serialize() shrani CELE Eloquent modele → 28MB na ključ
});
return BeachResource::collection($beaches); // ← transformacija šele po cache read

// SEDAJ (skalabilno):
$data = $this->cacheRemember($cacheKey, $ttl, function () {
    $beaches = Beach::with([...8 relacij...])->limit(1000)->get();
    return BeachResource::collection($beaches)->resolve();
    // ← resolve() ZNOTRAJ callbacka → Redis shrani flat array → 1.4MB na ključ
});
return response()->json(['data' => $data['beaches'], ...]);
```

**Po popravku:**
| Metrika | Vrednost | Izboljšava |
|---|---|---|
| Največji Redis ključ | 1.4 MB | **20x manj** |
| Skupna Redis poraba (DEV) | 5.4 MB | **38x manj** |
| Cache MISS (prvi klic) | 2.6s | DB query + Resource transform |
| Cache HIT (drugi klic) | 0.36s | **70x hitreje** od prej (30s/timeout) |
| PHP memory poraba na cache read | ~5-10MB | **20x manj** |

### Popravljene metode v `BeachController.php`

| Metoda | Route | Popravek |
|---|---|---|
| `index()` | `GET /api/v1/beaches` | `BeachResource::collection()->resolve()` znotraj callbacka |
| `mostVisited()` | `GET /api/v1/beaches/most-visited` | isto |
| `footerMostVisited()` | `GET /api/v1/beaches/footer` | `BeachFooterResource::collection()->resolve()` znotraj callbacka |

### PHP memory_limit

| Setting | Vrednost | Zakaj |
|---|---|---|
| `memory_limit` | 512M | Varnostna mriža — koreni problem je popravljen (manjši cache), ampak posamezni query-ji lahko še vedno zahtevni |
| `max_execution_time` | 120s | Varnostna mriža za počasne PostGIS query-je |

> **Pravilo za prihodnje controllerje:** Nikoli ne cache-aj surovih Eloquent modelov v Redis. Vedno kliči `Resource::collection($models)->resolve()` znotraj cache callbacka, tako da Redis shrani flat JSON-able array. To velja za vse controllerje, ki uporabljajo `cacheRemember()`.

---

## 8c. Changelog (vse spremembe)

### Backend (`Beachrex/backend`)

#### 2026-09-01: Provisioning in UAT
- VPS provisioned (Ubuntu 26.04, Docker, Nginx, UFW, swap)
- SSL certifikati pridobljeni za UAT in DEV domene
- PostgreSQL + PostGIS + Redis kontejnerji zagnani
- Baza migrirana iz Laravel Clouda (337 MB pg_dump)
- UAT API in Frontend postavljena in verificirana
- DEV okolje postavljena iz UAT

#### 2026-09-02: CI/CD
- Začetni poskus z GitLab CI/CD — odpovedano zaradi SSH key težav
- Preklop na GitHub Actions (bolj zanesljivo)
- SSH ključ generiran (RSA 2048 PEM), dodan v GitHub Secrets
- Backend pipeline: test (PostGIS service) + deploy
- Frontend pipeline: deploy (standalone build)
- Popravki: env_file poti, Dockerfile.prod commit, output:standalone commit, robots.ts env fallback

#### 2026-09-03: Branch struktura, okoljska ločitev in dokumentacija
- `dev`, `uat`, `main` branchi vzpostavljeni za oba repozitorija
- Branch → Okolje mapping implementiran v GitHub Actions
- **Per-okoljska ločitev kode:** vsako okolje ima svoj direktorij (`api_prod/uat/dev`, `front_prod/uat/dev`)
- `concurrency.group` spremenjen v globalnega (`deploy-backend` / `deploy-front`)
- `docker-compose.vps.yml` build contexti posodobljeni na `../api_{env}` in `../front_{env}`
- GitHub Actions `rsync` cilji posodobljeni na `/opt/beachrex/api_{env}/` in `/opt/beachrex/front_{env}/`
- VPS direktoriji ustvarjeni
- **TrustProxies fix:** `$middleware->trustProxies(at: '*')` v `bootstrap/app.php` (Mixed Content fix)
- **Per-okoljski admin paneli:** `FILAMENT_DOMAIN` env variable
- SSL certifikati pridobljeni za `dock*.beachrex.com` domene
- Nginx `beachrex.conf` posodobljen z HTTPS server bloki za admin domene
- Dokumentacija ustvarjena

#### 2026-09-04: Cross-env popravki, admin root domene, cache invalidacija
- **Admin na root domeni:** `BrAdminPanelProvider` path spremenjen iz `'admin'` na `''`
- **Cross-env API klici (frontend):** 6 datotek imelo hardcodiran `https://dev-api.beachrex.com` fallback — spremenjeno v `http://localhost:8000`
- **Docker Compose build.args:** `front_*` service dodana `build.args` sekcija za `NEXT_PUBLIC_*`
- **Frontend workflow:** `deploy.yml` naloži oba env file preko `--env-file`
- **Redis cache deljenje (KORENITI PROBLEM):** `config/database.php` `REDIS_CACHE_DB` → `REDIS_DB`
- **Cache invalidacija:** `FirstPageContent` + `FirstPageContentTranslation` dodana cache invalidacija preko model eventov
- **Next.js ISR → force-dynamic:** Homepage `revalidate = 3600` → `dynamic = 'force-dynamic'`
- **React Query staleTime:** `useFirstPageContent` `staleTime: 0` + `refetchOnMount: true`

#### 2026-09-08: Skalabilnost fix (Redis cache + PHP memory)
- **PHP memory_limit:** 256M → 512M, `max_execution_time` 60s → 120s (`Dockerfile.prod`)
- **Cache skalabilnost (KORENITI POPRAVEK):** `BeachController::index()`, `mostVisited()`, `footerMostVisited()` so cache-ali surove Eloquent kolekcije z 8+ relacijami v Redis (28MB na ključ). Popravljeno: `BeachResource::collection()->resolve()` znotraj cache callbacka — Redis sedaj hrani flat array (1.4MB na ključ, 20x manj)
- Redis poraba DEV: 204MB → 5.4MB (38x manj)
- Cache HIT čas: 30s/timeout → 0.36s (70x hitreje)
- **Pravilo dodano v dokumentacijo:** Nikoli ne cache-aj surovih Eloquent modelov v Redis — vedno uporabi `Resource::resolve()` znotraj callbacka

### Frontend (`Beachrex/web-client`)

#### 2026-09-03
- `output: "standalone"` dodan v `next.config.js`
- `robots.ts` popravljen za env fallback
- Branch struktura: `dev`, `uat`, `main` vzpostavljeni
- `concurrency.group: deploy-front` (globalni)

#### 2026-09-04
- `src/app/[locale]/page.tsx`: `revalidate = 3600` → `dynamic = 'force-dynamic'`
- `src/hooks/useFirstPageContent.ts`: `staleTime: 0` + `refetchOnMount: true`
- 6 datotek: hardcodiran `https://dev-api.beachrex.com` fallback → `http://localhost:8000`
- `deploy.yml`: `--env-file` za oba env file (backend + frontend)

---

## 9. Kaj manjka ( Naslednji koraki)

### PROD migracija
1. **DNS preklop:** `beachrex.com` in `api.beachrex.com` A record → `2.28.76.142`
2. **SSL certifikat:** `certbot --nginx -d api.beachrex.com -d beachrex.com -d www.beachrex.com`
3. **Nginx config:** Posodobiti `beachrex.conf` z PROD SSL bloki (trenutno samo HTTP)
4. **Baza:** Finalni `pg_dump` iz Laravel Clouda → restore v `beachrex_prod`
5. **`.env.prod`:** Posodobiti s pravimi PROD tajnostmi (trenutno kaže na UAT API za frontend)
6. **Frontend rebuild:** Po DNS preklopu push na `main` → build z `.env.front.prod` (ki mora kaže na `https://api.beachrex.com`)
7. **Preverba:** `https://beachrex.com` in `https://api.beachrex.com/api/v1/beaches?limit=1`

### Ostalo
- **Backup cron:** Dnevni `pg_dump` za `beachrex_uat` in `beachrex_prod`
- **Monitoring:** Razmisliti o uptime monitoring (npr. UptimeRobot)
- **Log rotation:** Docker logs lahko zasedejo disk — nastaviti log rotation
- **Cache invalidacija za ostale modele:** Preveriti, ali tudi drugi Filament resource-i (Beach, Country, itd.) potrebujejo `Cacheable` trait ali `booted()` eventi za cache invalidacijo
