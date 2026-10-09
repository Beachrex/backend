# Agent roles

Owner, 2026-10-09: two agents work on Beachrex in parallel. The owner says which one a session is.

| Role | When | Repos | Start here | Scope |
|---|---|---|---|---|
| **Web agent** (default) | Any session where the owner does **not** say "mobile agent" | `Beachrex/backend`, `Beachrex/web-client` | `docs/HANDOFF.md` | Website (desktop + mobile web), backend API, admin, MCP, infrastructure docs. Also the backend/web tasks the app needs (`docs/mobile/MOBILE-TASKS.md` §B) |
| **Mobile agent** | The owner says "ti si mobile agent" / "mobile agent" | `Beachrex/mobile-app` (code); reads `Beachrex/backend` docs and API | `docs/mobile/MOBILE-PLAN.md`, then `docs/mobile/MOBILE-TASKS.md` | The iOS/Android app (Expo, React Native, NativeWind, EAS) |

Shared rules for both: `docs/DEVELOPMENT-RULES.md` (git flow, proof by output, tests, secrets, owner decides
promotion and releases), talk to the owner in Slovenian, code/docs/commits in English.

## Boundaries

- The mobile agent does **not** change backend or web-client code. When the app needs an API change, it writes
  the need into `docs/mobile/MOBILE-TASKS.md` §B (docs-only PR in the backend repo) and tells the owner; the web
  agent implements it.
- The web agent does **not** change the mobile repo. Every `/api/v1` change keeps old fields working
  (additive only) because installed apps cannot be forced to update (`DEVELOPMENT-RULES.md` §9).
- Both keep `docs/HANDOFF.md` current: the web agent in its sections, the mobile agent in "Mobile app".
- The ADR log `docs/ai/implementation/DECISIONS.md` is shared; mobile decisions are titled "Mobile: …".

## Who edits which doc (owner, 2026-10-09: no conflicts between the two agents)

Both agents edit docs in `Beachrex/backend` through their own small docs PRs into `dev`. Each file or section has one owner, so two open PRs never change the same lines:

| Doc / section | Owner (edits) | The other agent |
|---|---|---|
| `docs/mobile/MOBILE-PLAN.md`, `docs/mobile/MOBILE-TASKS.md` (all M rows, notes) | Mobile agent | Reads only |
| `docs/mobile/MOBILE-TASKS.md` §B: new rows / what the app needs | Mobile agent | — |
| `docs/mobile/MOBILE-TASKS.md` §B: the **Status** cell of a B-M row | Web agent (when it ships the API) | — |
| `docs/HANDOFF.md` section "Mobile app" | Mobile agent | Web agent only adds one line per shipped B-M task |
| `docs/HANDOFF.md`, every other section; `DEV_HANDOFF.md`; `docs/DEVELOPMENT-RULES.md`; runbooks | Web agent | Mobile agent asks the owner |
| `docs/ai/implementation/DECISIONS.md` | Both: append only, mobile entries titled "Mobile: …" | Never edit the other's entries |
| Mobile app changelog / release notes | Mobile agent, in `Beachrex/mobile-app` | — |

To avoid conflicts:
- branch from the latest `origin/dev` right before editing a doc;
- keep docs PRs small and merge them soon;
- never reformat or reorder another owner's sections.

If a conflict still happens, merge `origin/dev` into the branch, keep both sides and re-run CI.
