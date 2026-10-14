# TASK-006 feedback — Windy webcams (2026-10-14)

Implementer report against the task's §10 "Proof required". All outputs are from the agent session of 2026-10-14.

## Delivered

| Step | PR | Deploy (dev) |
|---|---|---|
| Spec, Phase 0, ADR | Beachrex/backend#114 | 38582762471 ✅ |
| Tables, config, extractor, scorer, `webcams:match` | Beachrex/backend#115 | 38584442226 ✅ |
| `webcams:import`, `webcams:embed-check` | Beachrex/backend#116 | 38586183544 ✅ |
| `webcams:legacy-extract`, `webcams:refresh` (+ schedule, off) | Beachrex/backend#117 | 38587631077 ✅ |
| Admin "Webcam review", API fields | Beachrex/backend#118 | 38589616635 ✅ |
| Beach page iframe, cards/map | Beachrex/web-client#75 | 38590703623 ✅ |

## Proof that ran

- Backend: `php artisan test` → `4 failed, 6 skipped, 624 passed (3146 assertions)`; the 4 failures are the known env-dependent ones (`PlanEnforcementTest`, `CountrySlugSupportTest`). Webcam tests: 60 across `tests/Unit/Webcams` and `tests/Feature/Webcams`.
- Web: `tsc` exit 0, `yarn lint` clean, `268 passed` unit specs; PR check "Lint, typecheck, unit tests" green.
- Deliberate breaks (each made exactly the guarding test fail; two tests were too weak on the first run and were strengthened): one-webcam-one-place, rejected pairs, image URLs in `raw`, missing-marking after truncation, budget check, legacy radius downgrade, legacy "shown elsewhere", primary hand-over, unusable webcams in the API, details cache clear, admin "shown elsewhere".
- Real bug caught by a test before merge: PostgreSQL unique index with a NULL `region_code` let a resumed import create a duplicate country slice → `COALESCE(region_code, '')` index (#116).
- Legacy extractor over all 3 774 `beach.webcam` values (`database/sql/beachrex_old.sql`): empty 1 586, placeholder 1 934, lookr_embed 184, windy_embed 66, unparseable 4 → 250 ids / 237 unique, identical to Phase 0.
- Liveness of all 237 legacy ids via the public player (no API key): 73 alive, 69 embedding disabled, 95 dead (`TASK-006-legacy-webcams-2026-10-14.csv`).
- Server render of the beach page section (sl): heading "Spletna kamera v živo", Windy link and credit; empty webcams → empty output.

## NOT RUN — reasons

| Required proof | Reason |
|---|---|
| Import dry run / one-country import / API requests used | The agent sandbox cannot reach `api.windy.com` (egress policy 403) and there is no `WINDY_API_KEY`. Owner step 3 in HANDOFF. |
| Legacy-extract report and match summary for one country on real data | Needs the import first (same reason). |
| 10 example matches with breakdowns | Needs real `windy_webcams` data. Examples of loose legacy assignments found in Phase 0: "Ponta" (Viganj) → "Camping Antony Boy"; "Sunčana uvala" (Mali Lošinj) → "beach Veli Žal"; "Strand Thiessow" → a kite school in Mönchgut. |
| Neum (1616668973) | Not in our DB (no beaches in Bosnia and Herzegovina). Its player was checked live: renders ("Webcam player - Neum"). |
| Screenshot of a beach page with a working widget and of the admin page | The sandbox cannot reach dev; owner step 4. |

## Decisions made by the agent (ADR 2026-10-14 records the owner's)

- Category points apply once each (best bonus + worst penalty), no stacking; Windy has no `water`/`bay`/`spa` categories, so coast/lake/river/port carry the +10.
- A webcam must pass the public player check before it is matched or shown (69 legacy cameras exist on Windy with embedding disabled).
- Places with an approved or manual webcam are skipped by the matcher; legacy rows keep their source.
- Refresh runs daily but only re-imports every 30 days (or resumes a budget-paused run); it is off until `WEBCAMS_REFRESH_ENABLED=true`.
- Cards and the map link to the Windy page; only the beach page embeds the player.
