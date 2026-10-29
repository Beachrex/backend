# TASK-008 — Interactive "View beaches from all angles" on the homepage

**Owner request (2026-10-28):** the homepage section "View beaches from all angles" only describes which media a
beach can have (four static cards). Make it interactive, so a visitor sees on one real beach what it looks like.

## Goal

The section shows one real beach as an example: four cards with that beach's own photos; a click opens the real
gallery, video, 360° tour or live webcam in the same popup as the beach page. A caption names the beach and links
to its page.

## Decisions (agent, owner may change)

- **Which beach:** `config.showcase_beach_id` of the `beach_views` section (admin KeyValue field that already exists;
  no new admin field, no schema change). Default set by migration: **989 Svetilnik, Izola** — on dev 2026-10-28 it
  has 10 photos, a YouTube video, a 360° tour and a Windy webcam pointed at the beach itself. 255 beaches on dev
  have all four media types.
- **Presentation:** cards + the existing beach-page popup (`MediaOverlay`), not an inline player: no iframe loads
  until the visitor clicks, so the homepage stays fast.
- **Card images:** different photos of the same beach (gallery 1, gallery 2, 360° panorama, gallery 3), so the four
  cards read as one beach. Live card gets a "LIVE" badge.
- **Fallback:** no id, unknown or inactive beach, failed request or a beach with no media → today's static section
  from admin Items. The homepage never loses the section.
- **Only available types:** a beach without e.g. a webcam shows three cards.
- **Not a page view:** the homepage request sends `NOT_A_VIEW_HEADERS`, so the example beach's view counter is not
  inflated.

## Scope

Backend: migration (default id, idempotent, keeps admin edits), homepage cache cleared when a section is saved (was
only on translation save, so a config edit stayed hidden for 30 days), OpenAPI description, admin guide.
Web-client: `BeachMediaShowcase` component, shared media popup content extracted from `BeachPageView`, i18n
(19 languages), unit spec.

## Must not change

Beach page behaviour; `/api/v1` fields (additive only); admin Items stay the fallback.

## Acceptance

- dev homepage in en/si/de/el shows Svetilnik with four cards; each opens real content; the caption link opens the
  beach page in that language.
- Changing `showcase_beach_id` in admin shows the new beach within 5 minutes.
- Removing the key brings back the static section.
- Beach page media works as before.
