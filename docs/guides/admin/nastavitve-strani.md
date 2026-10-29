# Nastavitve strani (stikala)

> Velja od: 2026-10-29 · Kje: Admin → **System → Site settings** · Okolje: dev, uat, prod (vsako ima svoja stikala)

## Čemu služi

Stikala, s katerimi vklopiš ali izklopiš del strani **brez novega deploya**. Vsako okolje (dev, uat, prod) ima svojo
vrednost: če nekaj izklopiš na prod, na dev ostane vklopljeno.

Trenutno so tri stikala, vsa **neodvisna** drugo od drugega:

- **AI chat** — klepet z AI vodičem (stran klepeta in gumb za klepet na vseh straneh).
- **AI trip planner (paid)** — plačljivi AI načrtovalec potovanja (načrti potovanj, »Moji načrti«, nakup kreditov, strani po plačilu).
- **Google ads (AdSense)** — Googlovi oglasi na spletni strani (razdelek »Advertising«).

## Stikalo »AI chat«

| Stanje | Kaj se zgodi |
|---|---|
| **vklopljeno** (privzeto) | klepet deluje kot do zdaj |
| **izklopljeno** | na spletni strani izgine vnos »AI klepet o plažah« v meniju in gumb za klepet na vseh straneh, stran `/ai/chat` vrne »stran ne obstaja« (404); API zavrne vprašanja v klepetu (sporočilo `ai_disabled`, velja tudi za mobilno aplikacijo). Načrtovalec potovanja deluje naprej. |

## Stikalo »AI trip planner (paid)«

| Stanje | Kaj se zgodi |
|---|---|
| **vklopljeno** (privzeto) | načrtovalec deluje kot do zdaj |
| **izklopljeno** | na spletni strani izgine vnos »AI načrtovalec potovanj« v meniju in povezave nanj, strani `/ai/itinerary` (tudi »Moji načrti«) in strani po plačilu (`/payment/...`) vrnejo 404, Google jih ne indeksira; API zavrne načrte potovanj, cenik kreditov in nakup kreditov (sporočilo `ai_trip_planner_disabled`, velja tudi za mobilno aplikacijo). Klepet deluje naprej. |

Če izklopiš **obe** stikali, iz menija izgine cela skupina »AI pomočnik za plaže« in tudi stran `/ai` vrne 404.

Izklop ne izbriše ničesar: shranjeni načrti in kupljeni krediti ostanejo; ko stikalo spet vklopiš, so na voljo. Plačilo,
ki se je začelo pred izklopom, Stripe vseeno zaključi (povratni klic Stripe ni nikoli izklopljen).

## Stikalo »Google ads (AdSense)«

| Stanje | Kaj se zgodi |
|---|---|
| **izklopljeno** (privzeto) | spletna stran ne naloži Googlove skripte za oglase (AdSense) in ne pokaže nobenega oglasa ali praznega prostora za oglas. Google Analytics in pasica za piškotke delujeta naprej. |
| **vklopljeno** | oglasi delujejo kot prej: samo na produkciji (beachrex.com, nikoli na dev ali uat) in v skladu s privolitvijo obiskovalca za piškotke (CookieYes). |

Datoteka `ads.txt` ostane objavljena v obeh primerih (brez skripte ne naredi ničesar, Google pa jo potrebuje, ko oglase spet vklopiš).

Če spletna stran stikal ne more prebrati (API ne odgovori), oglasov ni.

## Kako izklopiš ali vklopiš

1. Admin → **System → Site settings**.
2. Premakni stikalo **AI chat**, **AI trip planner (paid)** ali **Google ads (AdSense)** (sivo = izklopljeno, modro = vklopljeno).
3. Klikni **Save**. Zgoraj desno se pokaže stanje vseh treh, npr. »AI chat is ON, AI trip planner is OFF, Google ads are OFF«.
4. API upošteva spremembo **takoj**; spletna stran v največ **5 minutah** (meni in strani se osvežijo po tem času).

## Kdaj ga uporabiš

- **Produkcija pred zagonom plačil:** dokler Stripe na prod ni nastavljen, naj bo na prod izklopljen **AI trip planner
  (paid)**, **AI chat** pa vklopljen (odločitev lastnika 2026-10-29).
- **Oglasi na produkciji:** po deployu z 2026-10-29 so na prod **izklopljeni**, dokler jih ne vklopiš tukaj (odločitev lastnika 2026-10-29).
- **Težava z AI (stroški, napačni odgovori):** izklop je takojšen za API, ne potrebuje razvijalca.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Izklopil sem, v meniju je še vedno | spletna stran se osveži v do 5 minutah; v brskalniku osveži stran |
| Vklopil sem, klepet javlja napako »service_unavailable« | na strežniku je izklopljen tudi `AI_ENABLED` v `.env` okolja ali je dosežena dnevna omejitev stroškov; to ureja razvijalec |
| Vklopil sem oglase, a jih ni | spletna stran se osveži v do 5 minutah; oglase prikaže samo produkcija, in to šele po privolitvi v piškotke ter ko jih Google odobri in postavi (AdSense Auto ads) |
| Stikala ne vidim | v admin se prijavi z računom, ki ima pravice skrbnika |

## Za razvijalce

- Tabela `site_settings` (`key`, `value`), model `App\Models\SiteSetting` (predpomnilnik `site_settings:all`, briše se ob shranjevanju).
  Ključi: `ai_assistant_enabled` (klepet; ime ostane zaradi združljivosti API), `ai_trip_planner_enabled` (načrtovalec)
  in `ads_enabled` (oglasi; privzeto **izklopljeno**, manjkajoč ključ = izklopljeno).
- Stran: `app/Filament/BrAdmin/Pages/SiteSettings.php`. API: `GET /api/v1/site-settings` → `data.ai_assistant_enabled`, `data.ai_trip_planner_enabled`, `data.ads_enabled`.
- Zapora klepeta: `App\Http\Middleware\EnsureAiAssistantEnabled` na `POST /api/v1/ai/chat` in `/ai/test` (503 `ai_disabled`).
- Zapora načrtovalca: `App\Http\Middleware\EnsureAiTripPlannerEnabled` na `POST /ai/itineraries`, `POST /ai/itinerary-plans`,
  `GET /payments/products`, `POST /payments/stripe/checkout` (503 `ai_trip_planner_disabled`).
- Nikoli zaprto: `POST /payments/stripe/webhook`, `GET /me/entitlements`, `GET /me/itinerary-plans`.
- `AI_ENABLED` v `.env` ostane kot strežniško varovalo (če je `false`, AI ne deluje ne glede na stikali).
- Oglasi (web): `src/lib/analytics/adsense.ts` (`shouldLoadAdsense`: produkcija **in** `ads_enabled`), `src/components/Analytics/ThirdPartyScripts.tsx`; oglasnih mest v kodi ni (Auto ads).
- Web: `src/lib/site-settings.ts`, meni, `app/[locale]/ai/…/layout.tsx`, `app/[locale]/payment/layout.tsx`, `llms.txt`.
- Spec: `docs/tasks/TASK-009-ai-assistant-switch.md`, ADR 2026-10-29 (z dopolnitvama).
