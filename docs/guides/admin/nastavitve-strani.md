# Nastavitve strani (stikala)

> Velja od: 2026-10-29 · Kje: Admin → **System → Site settings** · Okolje: dev, uat, prod (vsako ima svoje stikalo)

## Čemu služi

Stikala, s katerimi vklopiš ali izklopiš del strani **brez novega deploya**. Vsako okolje (dev, uat, prod) ima svojo
vrednost: če vodič izklopiš na prod, na dev ostane vklopljen.

Trenutno je eno stikalo: **AI guide on** — AI vodič po plažah (klepet, načrt potovanja, nakup kreditov).

## Stikalo »AI guide on«

| Stanje | Kaj se zgodi |
|---|---|
| **vklopljeno** (privzeto) | AI vodič deluje kot do zdaj |
| **izklopljeno** | na spletni strani izgine iz menija (»AI pomočnik za plaže«), strani `/ai`, `/ai/chat`, `/ai/itinerary` in strani po plačilu (`/payment/...`) vrnejo »stran ne obstaja« (404), Google jih ne indeksira; API zavrne klepet, načrte potovanj in nakup kreditov (sporočilo `ai_disabled`, velja tudi za mobilno aplikacijo) |

Izklop ne izbriše ničesar: shranjeni načrti in kupljeni krediti ostanejo; ko vodič spet vklopiš, so na voljo.

## Kako izklopiš ali vklopiš

1. Admin → **System → Site settings**.
2. Pri **AI guide on** premakni stikalo (sivo = izklopljeno, modro = vklopljeno).
3. Klikni **Save**. Zgoraj desno se pokaže »AI guide is OFF« ali »AI guide is ON«.
4. API upošteva spremembo **takoj**; spletna stran v največ **5 minutah** (meni in strani se osvežijo po tem času).

## Kdaj ga uporabiš

- **Produkcija pred zagonom plačil:** dokler Stripe na prod ni nastavljen in vodič ni preizkušen, naj bo na prod
  izklopljen (odločitev lastnika 2026-10-29).
- **Težava z AI (stroški, napačni odgovori):** izklop je takojšen za API, ne potrebuje razvijalca.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Izklopil sem, v meniju je še vedno | spletna stran se osveži v do 5 minutah; v brskalniku osveži stran |
| Vklopil sem, klepet javlja napako »service_unavailable« | na strežniku je izklopljen tudi `AI_ENABLED` v `.env` okolja ali je dosežena dnevna omejitev stroškov; to ureja razvijalec |
| Stikala ne vidim | v admin se prijavi z računom, ki ima pravice skrbnika |

## Za razvijalce

- Tabela `site_settings` (`key`, `value`), model `App\Models\SiteSetting` (predpomnilnik `site_settings:all`, briše se ob shranjevanju).
- Stran: `app/Filament/BrAdmin/Pages/SiteSettings.php`. API: `GET /api/v1/site-settings` → `data.ai_assistant_enabled`.
- Zapora: `App\Http\Middleware\EnsureAiAssistantEnabled` na skupini `/api/v1/ai/*` in na `POST /payments/stripe/checkout` (503 `ai_disabled`).
- `AI_ENABLED` v `.env` ostane kot strežniško varovalo (če je `false`, AI ne deluje ne glede na stikalo).
- Web: `src/lib/site-settings.ts`, meni, `app/[locale]/ai/layout.tsx`, `app/[locale]/payment/layout.tsx`, `llms.txt`.
- Spec: `docs/tasks/TASK-009-ai-assistant-switch.md`, ADR 2026-10-29.
