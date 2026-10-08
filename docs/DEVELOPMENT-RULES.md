# Development Rules — Beachrex (AI-assisted engineering)

Rules for anyone (human or AI agent) changing the Beachrex backend (`Beachrex/backend`) or frontend
(`Beachrex/web-client`). Adapted from the owner's general rules file, trimmed to what fits this project.

- This file lives in the backend repo and covers **both** repos. `web-client/CLAUDE.md` points here.
- Decisions in the ADR log (`docs/ai/implementation/DECISIONS.md`) override this file. Newest decision wins.
- Current state, working mode and next steps: [`docs/HANDOFF.md`](HANDOFF.md). Read it first.

---

## 1. Roles and working mode

| Role | Who | Responsibilities |
|---|---|---|
| Owner | David Tacer | Product decisions, accounts, secrets, VPS/DNS/server actions, anything on uat/prod, anything irreversible |
| CTO + implementer | AI agent (Claude Code) | Plans, ADRs, implementation, tests, docs, PRs to `dev`, merging green PRs to `dev`, verifying the dev deploy |

**Mode B (autonomous)** is in use. Standing permissions are recorded in `docs/HANDOFF.md` with date and the
owner's words. Promotion `dev → uat → main` happens **only when the owner asks for it** in the conversation.

The agent asks the owner **only** for: secrets, accounts, server actions, product decisions, anything on
uat/prod, anything irreversible. Everything else it decides, does and documents.

---

## 2. Start of every session

1. Read `docs/HANDOFF.md` (state, mode, bootstrap, next steps) and this file.
2. Read the ADR log (`docs/ai/implementation/DECISIONS.md`) for the area you touch.
3. `git fetch origin dev` and branch fresh from `origin/dev` (both repos when the task spans them).
4. Bootstrap the local test environment per HANDOFF and prove tests run (one real command + output).
5. Summarise the state to the owner in a few lines, unless the owner already gave a concrete task.

---

## 3. Documentation (source of truth)

| File | Purpose |
|---|---|
| `docs/HANDOFF.md` | Current state, working mode, bootstrap, environments, owner's open actions, next steps |
| `docs/DEVELOPMENT-RULES.md` | This file |
| `docs/ai/implementation/DECISIONS.md` | ADR log for the whole project (append-only) |
| `DEV_HANDOFF.md` (both repos) | Dated change log: what changed, affected endpoints, how to test |
| `docs/tasks/TASK-XXX-*.md` | Task specs + `TASK-XXX.feedback.md` with real outputs (for non-trivial tasks) |
| `docs/runbooks/*.md` | Rare step-by-step procedures (prod launch, restore a backup) |
| `docs/qa/dev-review-tracker.md` | Tester findings from the owner's spreadsheet: verdict, fix PR, retest status per row |
| `docs/deployment/DEPLOYMENT_GUIDE.md` | Infrastructure as it is today (VPS, CI/CD, env isolation) |

Rules:
- **ADRs are append-only.** Never delete one; add a new ADR that supersedes it.
- **Status labels** in technical docs: **Live** (in use on an environment, with proof) · **Built** (in the repo,
  not yet exercised on the target) · **Planned** (spec/ADR only).
- Docs describe **reality**. A statement that is no longer true is corrected or deleted in the same PR.
- Language: code, commits, PRs and docs in **English**; conversation with the owner in **Slovenian**.

---

## 4. Task lifecycle

1. **Plan** — for non-trivial work, write `docs/tasks/TASK-XXX-name.md`: goal, scope, decisions made, tests,
   must-not-touch, acceptance criteria. Take the next free number from `docs/tasks/README.md`.
2. **Implement** — only that scope. Unrelated issues are reported (HANDOFF "Open items"), not fixed on the side.
3. **Prove** — run every check; real outputs go in the feedback file / PR.
4. **Document** — `DEV_HANDOFF.md`, technical docs, OpenAPI, HANDOFF (§7).
5. **Ship** — PR → CI green → merge to `dev` → verify the Deploy run for the merge SHA → note "Live on dev".
6. **QA rows** — a fix for a row of the dev review tracker (`docs/qa/dev-review-tracker.md`) references `QA #<row>` in the PR and, after the dev deploy, sets the row to **Retest** with the PR and deploy run.

A task that needs an owner step (secret, server, DNS) stays **PARTIAL** until the owner confirms it.

---

## 5. Git, PRs and CI

- Branches: `feat/short-name`, `fix/short-name`, `docs/short-name`, `chore/short-name` — from `origin/dev`.
- Flow: `branch → PR → dev → (owner asks) → uat → (owner asks) → main`. Never push directly to `dev`, `uat` or `main`.
- Merge `dev → uat` and `uat → main` via PR with a merge commit; never cherry-pick around `dev`.
- One task = one branch = one PR. Don't mix a feature, a fix and a refactor. Aim for ≤ ~800 changed lines.
- Conventional Commits (`feat:`, `fix:`, `test:`, `refactor:`, `docs:`, `chore:`, `ci:`).
- AI-generated commits end with a `Co-Authored-By:` trailer.
- Stage files by name. Check `git status` for secrets and build output before every commit.
  (Frontend: the husky pre-commit hook runs `git add --all` — only commit with a clean working tree.)
- Never rewrite shared history (no force-push, no rebase of pushed branches).
- **Merge only on green.** A flaky job may be re-run **once**; a second failure is a bug.
- After merge: find the Deploy run for the merge SHA, verify it is green, smoke-check dev (`/api/v1/health`).

---

## 6. Testing and proof (non-negotiable)

1. **Proof by output.** A claim without a real command output from this session counts as not done.
   Anything that could not run is written as `NOT RUN — <reason>`.
2. **Tests must be able to fail.** No silent skips; a missing dependency (DB, env var, binary) fails the test.
3. **Deliberate break** for every guard/critical test: run it against the old/broken code, show it fails.
4. **Never weaken a test to get green.** The spec decides whether code or test is wrong.
5. **Exact boundaries:** empty, the exact limit, ±1, repeated calls.
6. **Authorization tests** for every mutating route: guest → 401, wrong user/non-admin → 403, data unchanged.
7. **Real dependencies** for integration tests (PostgreSQL + PostGIS). Mock only paid external APIs
   (OpenAI, OpenWeather, Stripe, Google).
8. **Every bug fix gets a regression test.**

Known environment-dependent failures (need API keys): `PlanEnforcementTest`, `CountrySlugSupportTest` weather
tests. CI tolerates exactly these. Any other failure blocks.

---

## 7. Documentation per change

Every PR updates, in the same PR:
- `DEV_HANDOFF.md` — dated entry (Overview / Changes / Affected endpoints / Testing) for any API, DB or behaviour change;
- OpenAPI annotations (`app/Swagger/`, `@OA` blocks) + regenerated `storage/api-docs/api-docs.json` for API changes;
- frontend: `yarn generate:api` after backend API changes that the frontend uses;
- `.env.example` for every new env var (name + comment, **never a value**);
- the ADR log when a decision was made (including the agent's own reading of an ambiguous request);
- `docs/HANDOFF.md` at the end of each larger step.

---

## 8. Security and secrets

- Secrets live only in `/opt/beachrex/envs/.env.*` on the VPS (outside git), GitHub Actions secrets, and the
  owner's own store. **Never** in the repo, chat, logs or agent context.
- The agent never reads or prints secret values. It **names** the variable and says where the owner sets it.
- A secret that appeared in chat, a log or a commit is compromised → rotate it.
- **Never copy files to the VPS by hand** (`scp`/`rsync`/`ssh`). Every change goes through CI/CD.
- Every mutating API route: `auth:sanctum` + an authorization check (`admin` middleware or ownership).
  Anyone can get a Sanctum token via `/register` or Google sign-in — a token alone is not authorization.
- Validate all input server-side; rate limits on public write endpoints; no tokens/emails/PII in logs.

---

## 9. Environments, deploy and data

- `local` (Sail) · `dev` · `uat` · `prod` — each with its own DB, Redis DB, Scout prefix and admin domain.
- Branch → environment: `dev → dev`, `uat → uat`, `main → prod`, deployed by `.github/workflows/deploy.yml`.
- `NEXT_PUBLIC_*` values are baked in at frontend build time; everything else is runtime env.
- **Migrations are backward compatible** (expand → migrate → contract). Never edit an applied migration.
- **Caching:** never cache Eloquent models — cache `Resource::...->resolve()` arrays. Every cached model
  needs invalidation on save/delete.
- **Locales:** never hardcode a locale list — use `SetLocaleFromHeader::SUPPORTED_LOCALES` (DB code `sl`,
  frontend URL code `si`).
- **Visibility (owner, 2026-10-08):** only the production website (`beachrex.com`) may be visible to search engines,
  AI crawlers or anyone else. dev and uat frontends: `robots.txt` `Disallow: /`, `X-Robots-Tag: noindex` and meta
  `noindex`; no analytics, ads or `llms.txt`. The backend (API, admin, docs) is never indexed on any environment
  (`NoIndex` middleware + `robots.txt`). Any new environment or domain starts hidden.
- Backups: daily `db:backup` to a private bucket (see HANDOFF for status) + Hetzner server backups.
  A restore drill is done at least once (`docs/runbooks/restore-backup.md`).
- Irreversible data steps (prod DB load, data migrations on prod): dry run → real run → verification,
  only with the owner.

---

## 10. Code quality bar

- Lint, typecheck, tests and build pass. New logic has tests.
- No new dependencies without a reason written in the PR; no framework substitutions without an ADR.
- Money in integer minor units (cents). Never floats for money.
- UI: all strings through next-intl, in **all 19** `messages/*.json` files.
- Reuse existing patterns (URL builders, `CacheService`, Resources) before creating new ones.
- Docker/deploy changes: say exactly what was and was not verified (no Docker in the agent sandbox).

---

## 11. Checklists

**Before merge**
- [ ] Branch based on latest `dev`; one task; scope matches the plan.
- [ ] CI green; new tests can fail (deliberate break shown).
- [ ] Mutating routes have auth + authorization tests.
- [ ] New env vars in `.env.example` and named in HANDOFF owner actions (no values).
- [ ] OpenAPI + `DEV_HANDOFF.md` + HANDOFF updated.

**After merge**
- [ ] Deploy run for the merge SHA green; `/api/v1/health` on dev shows the merge SHA.
- [ ] HANDOFF "Done" updated with PR number and deploy run ID.

---

## 12. Golden rules

1. Plan before code.
2. Proof by real output; `NOT RUN` when it didn't run.
3. Tests can fail — show it.
4. Every decision is written down (ADR) — every change in `DEV_HANDOFF.md`.
5. Merge only on green; verify the deploy.
6. Secrets never in chat, repo, logs or agent context. Never touch the VPS by hand.
7. Ask the owner only for secrets, accounts, servers, product decisions, uat/prod and irreversible steps.
8. HANDOFF is always current — the next session starts from it.
