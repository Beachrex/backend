# TASK-007 — SEO/GEO beach texts: meta, intro, FAQ, computed context

Status: **Built** (code), data not generated yet (owner runs `content:ai` on dev).
Owner 2026-10-21: reviewed two `content:ai preview --part=generate` texts (Livade i Obatnice, Ušće Neretve) and agreed they
are weak for SEO/GEO; approved all six proposals ("za opise se strinjam, čimveč"). Mode B.

## Problem

The generated description repeats the database fields in a fixed order (surface → seabed → depth → crowds → parking →
transport). On ~2 800 beaches that is one template with swapped values, it repeats what the page already shows as icons,
the first sentence does not match search intent, and there is nothing an AI answer engine can quote as a direct answer.

## Scope

1. **Meta description** per language (≤ 155 characters), written for search intent: what kind of beach, where, who it suits.
2. **Intro** (the description) without logistics: character, setting, who it suits; parking, road type, transport and the
   facility list stay out of the prose (they are icons and FAQ answers).
3. **FAQ**: 3–4 questions (5 for featured beaches) chosen from the facts a visitor asks about (children, depth, shade, parking,
   dogs, food, snorkelling, crowds, nearby beaches). Each answer starts with the direct answer and names the beach.
   A question is asked only when a fact answers it. Shown on the beach page and as `FAQPage` JSON-LD.
4. **Computed context** from our own data (not invented): distance to the town centre, number of beaches in the town,
   the nearest 3 active beaches within 10 km with distance and surface.
5. **Varied structure** by beach profile (family, wild/natural, town, sporty, rocky/deep): the prompt names an angle per profile.
6. **Two tiers**: the top `AI_CONTENT_FEATURED` beaches by views (default 200) get a longer intro (90–150 words), more
   area context and 5 FAQs; the rest 50–80 words and 3–4 FAQs.

Plus: part `seo` writes meta + FAQ for beaches that already have a description (human text stays; it is the source).

## Storage and API (additive)

- `beach_translation.meta_description` (varchar 320, null) and `beach_translation.faq` (jsonb, null: `[{q, a}]`).
- Written only where empty; a person's text is never overwritten. Admin: both fields in the beach Translations form.
- `GET /beach-details` `basic_info.meta_description` and `basic_info.faq` for the requested language only
  (no English fallback: a wrong-language snippet or FAQ is worse than none).

## Web

- `generateMetadata` uses `meta_description` when present (else the current behaviour).
- Beach page: FAQ section (heading in 19 languages) and `FAQPage` JSON-LD, only when the language has FAQ items.

## Not in scope

Links inside the AI text, rewriting human descriptions, uat/prod (they get the data by DB promotion).

## Run (owner, dev)

`plan --part=generate --part=seo` → `preview --part=generate --id=… --full --locale=sl --locale=en` →
`submit --part=generate` → `apply` dry run → `apply --write` → then the same for `seo` → `cache:clear-api --type=all`.
Runbook: `docs/runbooks/content-ai.md`.
