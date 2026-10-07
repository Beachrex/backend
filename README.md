# Beachrex API

Backend for **Beachrex** ([beachrex.com](https://beachrex.com)), a multilingual beach discovery platform. It serves beach data (around 3,774 beaches in the UAT/DEV DB as of Sept 2026, 40+ attributes each, with media and 360° tours), geographic navigation, spatial search, weather, CMS content, AI chat and paid AI itineraries to the Next.js frontend and to AI agents (via the [MCP server](mcp-server/README.md)). The API is versioned under `/api/v1` and localized through `Accept-Language` (19 locales).

## Stack

- **Laravel 12**, **PHP 8.4** (Sail runtime and `Dockerfile.prod`)
- **PostgreSQL 15 + PostGIS** (`postgis/postgis:15-3.4`), **Redis** (cache)
- **Meilisearch** via Laravel Scout (global search). Not part of `docker-compose.yml`; see [docs/MEILISEARCH_SETUP.md](docs/MEILISEARCH_SETUP.md)
- **Filament 3** admin panel
- **Sanctum** tokens + **Google OAuth** (Socialite)
- **Stripe** (itinerary payments), **OpenAI** (AI chat/itineraries)
- OpenAPI docs via l5-swagger (`/api/documentation`)

## Local development (Sail)

```bash
composer install                      # provides vendor/bin/sail
cp .env.example .env                  # then switch DB_* to pgsql (see comments in .env.example)
./vendor/bin/sail up -d               # app, pgsql (PostGIS), redis
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan l5-swagger:generate   # regenerate OpenAPI docs
```

External keys (OpenAI, OpenWeather, Stripe, Google, DigitalOcean Spaces, Meilisearch) go in `.env`. See `.env.example`.

## Tests

```bash
./vendor/bin/sail artisan test
```

Known environment-dependent failures: **`PlanEnforcementTest`** (needs an OpenAI API key) and **`CountrySlugSupportTest`** weather tests (need an OpenWeather API key). CI tolerates exactly these two (`.github/workflows/deploy.yml`). Any other failure blocks the deploy.

## Development Setup

### Git Hooks

This project uses Git hooks to ensure code quality. To install the hooks:

```bash
./hooks/install.sh
```

The pre-commit hook will automatically run all tests before allowing a commit. If any tests fail, the commit will be aborted.

To run tests manually:
```bash
./vendor/bin/sail artisan test
```

See [hooks/README.md](hooks/README.md) for more details.

## Environments

All environments run on one Hetzner VPS and are deployed by GitHub Actions on push.

| Branch | Env  | API                              | Admin (Filament, at domain root) |
|--------|------|----------------------------------|----------------------------------|
| `dev`  | DEV  | https://dev-api.beachrex.com     | https://dock-dev.beachrex.com    |
| `uat`  | UAT  | https://uat-api.beachrex.com     | https://dock-uat.beachrex.com    |
| `main` | PROD | https://api.beachrex.com         | https://dock.beachrex.com        |

Each environment has its own code directory, database (`beachrex_{env}`), Redis DB (`REDIS_DB`), Scout prefix and admin domain (`FILAMENT_DOMAIN`).

## Rules

1. **PR flow:** `feature/xxx` → PR → `dev` → PR → `uat` → PR → `main`. Never push directly to `uat` or `main`.
2. **Never copy files to the VPS manually** (`scp`/`rsync`/`ssh`). All changes go through the CI/CD pipeline.
3. **API changes require docs:** update the OpenAPI annotations/schemas (`app/Swagger/`, controller `@OA` blocks), regenerate with `l5-swagger:generate`, and add a dated entry to [DEV_HANDOFF.md](DEV_HANDOFF.md).
4. **Never cache Eloquent models.** Resolve the Resource inside the cache callback (`Resource::collection($models)->resolve()`) so Redis stores plain arrays.
5. **Data-mutating API routes are admin-only.** Put them under `auth:sanctum` + `admin` (`EnsureUserIsAdmin`). Anyone can get a Sanctum token via `/register` or Google sign-in, so a token alone is not authorization.
6. **Locales:** never hardcode a locale list. Use `SetLocaleFromHeader::SUPPORTED_LOCALES` (DB codes; Slovenian is `sl`, the frontend uses `si`).

## Key docs

- [DEV_HANDOFF.md](DEV_HANDOFF.md): change log for API/backend changes (newest first)
- [docs/ai/AI_INDEX.md](docs/ai/AI_INDEX.md): product/AI context and doc index
- [docs/deployment/DEPLOYMENT_GUIDE.md](docs/deployment/DEPLOYMENT_GUIDE.md): VPS, CI/CD, env isolation (authoritative infra doc)
- [mcp-server/README.md](mcp-server/README.md): Beachrex MCP server
- [docs/README.md](docs/README.md): index of the remaining technical docs
