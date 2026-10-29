# Runbook: AI data quality (`content:ai`)

Owner request 2026-10-09: "uredi kakovost podatkov, z AI prevajaj". ADR "Data quality pass with Claude batches" in `docs/ai/implementation/DECISIONS.md`.

## What it fixes

| Part | Problem | What Claude returns | What is written |
|---|---|---|---|
| `names` | An import added the English word "Beach" to most beach names, in every language ("Beach Slanica") | The local name ("Slanica", "Vela plaža"), plus the Cyrillic (ua, ru, bg) and Greek (el) spelling | The name in every language that still has "Beach" or the plain source name; missing languages get a row |
| `regions` | Region names are English in most languages ("Dalmatia - Šibenik") | The name in all 19 languages ("Dalmacija - Šibenik", "Dalmatien - Šibenik") | Languages that are missing or still show the English name |
| `descriptions` | A description exists in one language but not in the others | A translation into each language without one | Only languages without a description |
| `generate` | About 79% of beaches have no description at all | Per language (TASK-007, owner 2026-10-21): a search snippet (≤ 155 characters), an intro without logistics (50–80 words; 90–150 for the `AI_CONTENT_FEATURED` most viewed beaches) and 3–4 FAQs (5 for featured), from the database facts, computed context (town centre distance, beaches in the town, nearest 3 beaches within 10 km) and well-known facts about the town or island; the opening angle rotates by beach id | Description, snippet and FAQ, each only where the language has none |
| `seo` | Beaches that already have a description have no snippet or FAQ | The snippet and FAQs from the facts and the existing description (TASK-007) | Snippet and FAQ only where empty; descriptions are never touched |

Safety rules in the code (`App\Services\AI\Content\BeachContentAi`), covered by `tests/Feature/ContentAiTest.php`:
- slugs never change, so no URL changes; new rows reuse the existing slug;
- text a person wrote is never overwritten (a name without "Beach" that differs from the source, a translated region name, any existing description);
- a returned name must share a word with the old one and may not keep the English "Beach" (except in English-speaking countries), otherwise it is refused;
- `apply` is a dry run until `--write` is given;
- (TASK-007) a snippet over 200 characters or an FAQ with fewer than two valid questions is left out for that language and printed as `[meta left out …]` / `[faq left out …]`; the `seo` part picks those beaches up again;
- (TASK-007) the snippet and FAQ can be edited in the admin (beach → Translations); a filled field is never overwritten.

Preview output (TASK-007): `sl: <intro>`, `sl: [meta] <snippet>`, `sl: [faq] <question> — <answer>`; `--locale=sl` keeps all three.

## Settings

`/opt/beachrex/envs/.env.<env>` (owner): `ANTHROPIC_API_KEY` (already set on dev for the chat). Optional: `AI_CONTENT_MODEL` (default `claude-opus-5-5`; `claude-sonnet-5-5` costs half), `AI_CONTENT_EFFORT` (default `low`), `AI_CONTENT_FEATURED` (default `200`: how many of the most viewed beaches get the longer text; `0` = none).

## Steps (dev)

Run on the VPS, one command at a time. Order: names, then regions, then descriptions, then generate, then seo.
The FAQ makes `generate` about 2.5× the old cost (19 languages × snippet + intro + FAQ); `plan` prints the estimate.

1. **Plan** (counts and rough cost, no AI calls):
   `docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan content:ai plan`
2. **Preview** a few items (asks Claude now, writes nothing):
   `docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan content:ai preview --part=names --sample=5`
   Same with `--part=regions`, `--part=descriptions`, `--part=generate`. `--id=172` previews one beach; `--full --locale=sl --locale=en` prints whole descriptions in those languages. Send the output to the agent before going on.
3. **Submit** one part as a batch (half price, finishes within 24 h, usually within an hour):
   `docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.dev exec -T api_dev php artisan content:ai submit --part=names`
   It prints the batch id (`msgbatch_…`). Large parts are split into batches of 5 000.
4. **Status**: `… php artisan content:ai status --batch=msgbatch_…` until it says `ended`.
5. **Dry run of the results**: `… php artisan content:ai apply --batch=msgbatch_…` prints the changes and a summary; nothing is written.
6. **Write**: `… php artisan content:ai apply --batch=msgbatch_… --write`
7. **Refresh caches and search**: `… php artisan cache:clear-api --type=all`, then `… php artisan search:index`.

## Interruptions and re-runs

- Each part selects only what is still left (names that still have "Beach", languages without a description, …), so after `apply --write` a new `submit` sends only the rest.
- If the Anthropic credit runs out in the middle of a batch, those requests come back as `errored`; `apply --write` writes the ones that succeeded, and a later `submit` of the same part sends only the failed ones.
- `submit` refuses a part whose previous batch was not applied with `--write` yet (it would pay twice for the same items); `--force` overrides.

## Reference data: `translations:ai` (owner 2026-10-12)

The small `*_translation` tables (beach attributes, badges, islands, lakes, rivers, countries, continents) miss whole languages (`translations:audit`: da, pt, nl, el, sq empty in most of them). `translations:ai` fills them the same way: `plan` → `preview --table=beach_type_translation` → `submit` → `status --batch=…` → `apply --batch=…` (dry run) → `apply --batch=… --write` → `cache:clear-api --type=all`.
- One request per table (chunks of 40 rows); beaches, cities, regions and long texts are not part of it.
- Writes only languages with no row or with the English name still in it; new rows copy the English row (same slug, so no URL changes); `full_name` and `filter_title` are translated with the name when the English row has them.
- Beach and city names (`beach_translation`, `city_translation`, active places only): only Greek, Ukrainian, Russian and Bulgarian, in their own script, with the country as context; Latin-script languages keep the local name through the English fallback. A new row never copies the English description.
- 2026-10-12 on dev (owner ran and checked both): `msgbatch_01LoA71A7oxGfwMbUQsqtR8t` reference tables, 36 requests, 3 563 names; `msgbatch_013mxKXsoN265PRZpKsmhGit` beach and city names in el/ua/ru/bg, 57 requests, 8 900 names. Then `cache:clear-api --type=all`, `search:index`, `front_dev` recreated.
- Before the launch uat/prod got these with the DB copy at launch; after the launch run `translations:ai` on prod (see below).

## Which environment (ADR 2026-10-23)

- **Before the prod launch:** run on dev; uat and prod got the data with the one-time DB copy at launch.
- **After the launch:** there is no DB promotion any more (prod holds real users). `content:ai` and `translations:ai` write only the environment they run on, so run them **on prod** (owner): `plan` → `preview` → `submit` → `status` → `apply` (dry run) → `apply --write` → `cache:clear-api --type=all` → `search:index`. Replace `.env.dev`/`api_dev` with `.env.prod`/`api_prod` in the commands above. Trying a new prompt is still done on dev with `preview` (it writes nothing).
- dev and uat may lag behind prod's content; that is fine. A data fix every environment needs is a migration, not a command.
