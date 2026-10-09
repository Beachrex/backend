# Beachrex backend — agent instructions

**Role:** if the owner says you are the **mobile agent**, read `docs/AGENT-ROLES.md` and `docs/mobile/MOBILE-PLAN.md` first and work in `Beachrex/mobile-app`. Otherwise you are the **web agent** (this repo and web-client).

Before any task, read:
1. `docs/HANDOFF.md` — current state, working mode, bootstrap, next steps
2. `docs/DEVELOPMENT-RULES.md` — how we work (git, tests, docs, security)
3. The ADR log `docs/ai/implementation/DECISIONS.md` for the area you touch

Short version:
- Branch from `origin/dev`, PR into `dev`, merge only on green CI, then verify the dev deploy. Never push to `dev`/`uat`/`main` directly; promote to uat/prod only when the owner asks.
- Never touch the VPS by hand (no ssh/scp/rsync). Never read or print secrets.
- Every API change: OpenAPI annotations + `php artisan l5-swagger:generate` + a dated entry in `DEV_HANDOFF.md`.
- Mutating routes: `auth:sanctum` + `admin` (or an ownership check), with 401/403 tests.
- Never cache Eloquent models (cache `Resource::...->resolve()`); never hardcode locales (`SetLocaleFromHeader::SUPPORTED_LOCALES`).
- Proof by real command output; every bug fix gets a regression test.
- Code/docs/commits in English; talk to the owner in Slovenian.
