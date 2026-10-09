# Beachrex MCP Server

Model Context Protocol (MCP) server za Beachrex API. Omogoča AI agentom (Claude Desktop, ChatGPT, Gemini, Cursor) iskanje plaž po lokaciji, imenu in lastnostih, pridobivanje podrobnosti, filtrov in vremena preko standardiziranega MCP vmesnika.

> **Status:** DEV okolje (`https://mcp-dev.beachrex.com`) je aktivno. Konfiguracija za UAT/PROD je pripravljena v `ecosystem.config.cjs` (ločen pm2 proces in port na okolje), Nginx/DNS/SSL za `mcp-uat` in `mcp` še nista urejena.

---

## Arhitektura

```
AI agent (Claude Desktop / ChatGPT / Cursor)
    ↓ MCP protocol (JSON-RPC)
MCP Server (Node.js, PM2 na VPS, en proces na okolje)
    ↓ HTTPS
Laravel API ({dev-,uat-,}api.beachrex.com)
    ↓
PostgreSQL + PostGIS, Meilisearch
```

### Transporta

| Transport | Kdaj | Konfiguracija |
|---|---|---|
| **stdio** | Lokalni razvoj (Cursor, Claude Desktop lokalno) | `node dist/index.js` brez `MCP_HTTP_PORT` |
| **Streamable HTTP** | Strežnik (Claude remote, ChatGPT) | `MCP_HTTP_PORT` (pm2 ga nastavi glede na `MCP_ENV`) |

MCP server samodejno izbere transport na podlagi env variable `MCP_HTTP_PORT`:
- Če je nastavljena → HTTP način (stateless, fresh server per request)
- Če ni nastavljena → stdio način (persistent server)

---

## Tool-i

Vsi tool-i sprejmejo `locale` (privzeto `en`): `en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq` (19 jezikov). `si` je sprejet kot alias za `sl`, prav tako `de-DE`, `pt_BR` ipd. API dobi `Accept-Language: sl`, frontend URL-ji pa uporabljajo `si` (npr. `/si/plaze/...`). Nepodprt jezik → `en`.

### Filtri lastnosti (skupni za `search_beaches` in `find_beaches_nearby`)

Imena parametrov so točno taka, kot jih bere `BeachFilterService` (`applyFilters` za `/beaches` in `buildRawFilters` za `/spatial/*`). Vrednosti so **kode** iz `get_beach_filters`. Vsak parameter sprejme niz (`"sand"`), niz z vejicami (`"sand,small_stones"`) ali seznam (`["sand","small_stones"]`). Več kod v istem parametru = **katerakoli** (SQL `IN`), različni parametri = **vsi** (AND).

| Parameter | Pomen | Primer kod (iz seederjev, preveri z `get_beach_filters`) |
|---|---|---|
| `waterbody` | vodno telo | `sea`, `lake`, `river`, `pool` |
| `beach_type` | tip plaže / podlaga | `sand`, `small_stones`, `big_stones`, `rock_beach`, `concrete` |
| `user_type` | komu je plaža namenjena | `family`, `youth`, `adventurer` |
| `location_infrastructure` | infrastruktura | `lifeguard`, `shower`, `wc`, `children_playground`, `access_for_disabled` |
| `sporting_activity` | športne aktivnosti (ednina!) | `snorkeling`, `diving`, `windsurfing` |
| `culinary_offer` | gostinstvo | `restaurant`, `beach_bar` |
| `accommodation_around` | nastanitve v bližini | `hotel`, `apartments`, `camp`, `naturist_camp` |
| `natural_location_condition` | naravne danosti | `natural_shade`, `white_sand` |
| `underwater_characteristic` | podvodne značilnosti | |
| `accessibility` | dostopnost (težavnost dostopa) | `easy`, `medium`, `difficult` |
| `occupancy_rate` | zasedenost | |
| `area_description` | tip okolice | |
| `beach_inclination` | naklon plaže | |
| `swimming_depth` | razdalja do 1 m globine (skrajšano ime!) | |
| `parking_capacity` | kapaciteta parkirišča | |
| `recommended_transport_type` | priporočen prevoz | |
| `parking` | boolean, parkirišče | `true` / `false` (API dobi `1`/`0`) |
| `has_swimming_pool` | boolean, bazen | `true` / `false` |

### 1. `search_beaches`

Seznam plaž po lokacijskih kodah in/ali lastnostih (`GET /api/v1/beaches`).

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `continent_code` | string | ne | EU, AS, AF, NA, SA, OC |
| `country_code` | string | ne | ISO koda države (HR, IT, ES, GR). MCP pošlje **oba** parametra, `country` in `country_code`, da deluje s staro in novo verzijo API-ja |
| `region_code` | string | ne | Beachrex koda regije (iz `find_beach_by_name` ali `get_beach_filters` s `country_code`) |
| `island_code` | string | ne | Beachrex koda otoka |
| `city_code` | string | ne | Beachrex koda mesta (npr. `makarska`) |
| filtri lastnosti | glej zgoraj | ne | |
| `limit` | number | ne | 1–100, privzeto 20 (višje vrednosti MCP omeji na 100) |
| `locale` | string | ne | Jezik |

Rezultati **niso razvrščeni** (ne po priljubljenosti ne po kakovosti). Ko je rezultatov toliko kot `limit`, tool to pove.

**Primer odgovora:**
```
🏖️ **Beach Bajlo** (Zadar, Croatia) [id 125, slug beach-bajlo]
   Tags: Swimming, Beach bar
   🖼️ [View photo](https://s3.beachrex.com/unsafe/fit-in/800x800/img/beaches/.../sl8.jpg)
   🔗 [View on Beachrex](https://dev.beachrex.com/si/plaze/evropa/hrvaska/dalmatia-zadar/zadar/beach-bajlo)
```

### 2. `find_beach_by_name` (novo)

Prosto iskanje po imenu preko Meilisearch (`GET /api/v1/search`). Tolerira tipkarske napake, manjkajoče šumnike in druge jezike. Vrne (1) plaže, najboljši zadetek prvi, in (2) mesta, otoke in regije z **kodo**, ki jo podaš v `search_beaches` (`city_code` / `island_code` / `region_code`), da dobiš vse plaže v tem kraju.

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `query` | string | **da** | Ime plaže ali kraja: `"Zlatni rat"`, `"Bačvice"`, `"Makarska"` |
| `country_code` | string (2) | ne | Omeji na državo |
| `limit` | number | ne | 1–20 na tip (API max 20), privzeto 10 |
| `locale` | string | ne | Jezik |

Če poizvedba nima zadetkov, MCP poskusi še enkrat brez splošnih besed (`"beaches in Makarska"` → `"Makarska"`).

### 3. `find_beaches_nearby`

Plaže v radiju okoli GPS koordinat (`GET /api/v1/spatial/beaches/within-radius`), najbližje prve.

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `latitude` | number | **da** | Geografska širina (npr. 43.5081 za Split) |
| `longitude` | number | **da** | Geografska dolžina (npr. 16.4402 za Split) |
| `radius_km` | number | ne | Radij v km (zračna črta), 0.1–20000, privzeto 25 |
| filtri lastnosti | glej zgoraj | ne | |
| `limit` | number | ne | 1–100, privzeto 20 |
| `locale` | string | ne | Jezik |

URL-ji do plaž so polni hierarhični frontend URL-ji (enako pravilo kot `buildBeachUrlFromSchema` v web-clientu: `/{locale}/{plaze}/{kontinent}/{država}[/{regija}][/{otok}][/{mesto}]/{plaža}`).

**Razdalja:** API meri razdaljo v projekciji EPSG:3857 (Web Mercator), ki razdalje poveča za faktor 1/cos(širina) (~1,37× na 43° S). MCP zato API-ju pošlje `radius / cos(lat)`, nato za vsako plažo izračuna pravo (haversine) razdaljo, izloči plaže izven zahtevanega radija in razvrsti po pravi razdalji.

### 4. `get_beach_details`

Podrobnosti ene plaže (`GET /api/v1/beach-details/{id|slug}`): opis, lokacija z lokaliziranimi imeni (kontinent > država > regija > otok > mesto), koordinate, tip plaže, vodno telo, dostop, parkirišče, infrastruktura, gostinstvo, aktivnosti, komu je namenjena, galerija, URL.

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `identifier` | string \| number | **da** | ID (najbolje), slug ali **ime** (`"zlatni rat"`) |
| `locale` | string | ne | Jezik |

Postopek za ime: ID → direktno; sicer poskusi kot slug (brez šumnikov, `Bačvice` → `bacvice`); če API vrne 404, poišče ime preko `/search`. Točno ujemanje imena → ta plaža (če je več plaž z enakim imenom, jih našteje). Brez točnega ujemanja → najboljši zadetek z oznako **"closest match"** in seznamom drugih kandidatov.

Število ogledov se pokaže samo, če je večje od 0. Opis (`basic_info.description`) je očiščen HTML oznak.

### 5. `get_beach_filters`

Veljavne kode filtrov z lokaliziranimi imeni (`GET /api/v1/beaches/filters`) in za vsako skupino ime parametra, ki ga sprejmeta `search_beaches` in `find_beaches_nearby`. Odgovor API-ja je objekt (`{ data: { waterbody: { title, options: [{id, code, name, slug}] }, … } }`), ne seznam.

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `country_code` | string | ne | Če je podan, izpiše tudi regije, otoke in mesta te države s kodami (do 150 na skupino) |
| `continent_code` | string | ne | Zoži seznam držav |
| `locale` | string | ne | Jezik |

Brez `country_code` so regije/otoki/mesta samo prešteti (seznam bi bil predolg).

### 6. `get_beach_weather`

Trenutno vreme na plaži (`GET /api/v1/beach-details/{id|slug}/weather`, OpenWeatherMap, cache 1 h): stanje in opis, temperatura zraka, občutek, min/max, vlaga, veter (m/s, km/h, smer), oblačnost, vidljivost, tlak, sončni vzhod/zahod (UTC).

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `identifier` | string \| number | **da** | ID, slug ali ime plaže |
| `locale` | string | ne | Jezik |

Če API vrne napako (503 = vremenski servis ni dosegljiv ali ni API ključa, 400 = plaža nima koordinat) ali `weather: null`, tool vrne **"Weather unavailable"** in ne izmišljenih ničel.

---

## Resources

| Resource | URI | Vsebina |
|---|---|---|
| `beachrex-context` | `beachrex://context` | Kontekst platforme: ~3.800 plaž, 19 jezikov, kateri tool za katero vprašanje, pravilo o slikah (ne Google Images) |

---

## URL-ji

### MCP endpoint (HTTP transport)

| Okolje | URL | pm2 proces | Port | Status |
|---|---|---|---|---|
| DEV | `https://mcp-dev.beachrex.com/mcp` | `beachrex-mcp-dev` | 3100 | ✅ aktiven |
| UAT | `https://mcp-uat.beachrex.com/mcp` | `beachrex-mcp-uat` | 3101 | ⏳ Nginx/DNS/SSL ni konfiguriran |
| PROD | `https://mcp.beachrex.com/mcp` | `beachrex-mcp-prod` | 3102 | ⏳ Nginx/DNS/SSL ni konfiguriran |

### Health check

```
GET https://mcp-dev.beachrex.com/health
→ {"status":"ok","server":"beachrex-mcp","env":"dev"}
```

### Beachrex frontend URL-ji (v tool odgovorih)

`/{locale}/{beseda-za-plaže}/{kontinent}/{država}[/{regija}][/{otok}][/{mesto}]/{plaža}` — beseda za "plaže" je iz `web-client/src/i18n/routing.ts`:

| Jezik | Beseda | Jezik | Beseda | Jezik | Beseda |
|---|---|---|---|---|---|
| en | `beaches` | si (sl) | `plaze` | de | `strande` |
| hr | `plaze` | fr | `plages` | es | `playas` |
| it | `spiagge` | ua | `plyazhi` | ru | `plyazhi` |
| pl | `plaze` | cs | `plaze` | hu | `strandok` |
| bg | `plyazhove` | sk | `plaze` | da | `straande` |
| pt | `praias` | nl | `stranden` | el | `paralies` |
| sq | `plazhet` | | | | |

Če API ne vrne slugov za kontinent, državo ali plažo, MCP URL-ja ne prikaže (pokvarjena povezava je slabša od nobene).

### CDN slik (v tool odgovorih)

API vrača relativne poti (`img/beaches/...`). MCP doda CDN predpono:

```
https://s3.beachrex.com/unsafe/fit-in/800x800/{relative_path}
```

Polja za sliko po endpointih: `/beaches` → `main_image`, `/search` → `main_image`, `/spatial/beaches/within-radius` → `main_image` (MCP sprejme tudi `main_image_path`), `/beach-details` → `media.gallery[].url` (primarna slika ima `is_primary: true`).

---

## Environment spremenljivke

| Variable | Default | Opis |
|---|---|---|
| `MCP_ENV` | `dev` | Samo za `ecosystem.config.cjs`: `dev`, `uat` ali `prod` |
| `BEACHREX_API_URL` | `https://dev-api.beachrex.com` | URL Beachrex REST API |
| `BEACHREX_API_KEY` | (prazno) | API key (za endpoints, ki zahtevajo auth) |
| `BEACHREX_API_TIMEOUT_MS` | `15000` | Timeout za klice API-ja |
| `BEACHREX_CDN_URL` | `https://s3.beachrex.com/unsafe/fit-in/800x800/` | CDN base za slike |
| `BEACHREX_CDN_THUMB` | `https://s3.beachrex.com/unsafe/fit-in/200x200/` | CDN base za thumbnail slike |
| `BEACHREX_FRONTEND_URL` | `https://dev.beachrex.com` | Frontend URL za "View on Beachrex" linke |
| `MCP_HTTP_PORT` | (ni nastavljena) | Če je nastavljena → HTTP transport, sicer stdio |

Server sam ne bere `.env` datoteke. Glej `.env.example` za predlogo.

---

## Namestitev

### Lokalni razvoj (stdio)

```bash
cd mcp-server
npm install
npm run build
npm test
```

Dodaj v `~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "beachrex": {
      "command": "node",
      "args": ["/path/to/backend/mcp-server/dist/index.js"],
      "env": {
        "BEACHREX_API_URL": "https://dev-api.beachrex.com",
        "BEACHREX_FRONTEND_URL": "https://dev.beachrex.com"
      }
    }
  }
}
```

### Strežnik (HTTP, PM2 na VPS)

Vsako okolje ima svoj pm2 proces, svoj port in svoj `cwd` (`/opt/beachrex/api_<env>/mcp-server`). `ecosystem.config.cjs` vse izpelje iz `MCP_ENV`:

| `MCP_ENV` | pm2 ime | Port | `BEACHREX_API_URL` | `BEACHREX_FRONTEND_URL` |
|---|---|---|---|---|
| `dev` (privzeto) | `beachrex-mcp-dev` | 3100 | `https://dev-api.beachrex.com` | `https://dev.beachrex.com` |
| `uat` | `beachrex-mcp-uat` | 3101 | `https://uat-api.beachrex.com` | `https://uat.beachrex.com` |
| `prod` | `beachrex-mcp-prod` | 3102 | `https://api.beachrex.com` | `https://beachrex.com` |

`cwd` je `__dirname`, zato vsak proces teče iz svoje kopije kode. Neznan `MCP_ENV` vrže napako (namesto da bi tiho zagnal dev).

```bash
cd /opt/beachrex/api_${ENV_NAME}/mcp-server
MCP_ENV=${ENV_NAME} pm2 startOrReload ecosystem.config.cjs --update-env
```

**Nginx:** `mcp-dev.beachrex.com` → `127.0.0.1:3100` (glej `deploy/nginx/beachrex.conf`). Za UAT/PROD je treba dodati upstream na 3101/3102.

---

## Deploy

MCP server je del backend repozitorija (`backend/mcp-server/`). Deploy poteka skozi GitHub Actions (`.github/workflows/deploy.yml`):

1. `rsync` kode na `/opt/beachrex/api_{env}/mcp-server/`
2. **Samodejni rebuild** (ker `dist/` ni v gitu):
   ```bash
   cd /opt/beachrex/api_${ENV_NAME}/mcp-server
   npm install
   npm run build
   # enkratni prehod: stari skupni proces "beachrex-mcp" je dev in zaseda port 3100
   if [ "${ENV_NAME}" = "dev" ]; then pm2 delete beachrex-mcp || true; fi
   MCP_ENV=${ENV_NAME} pm2 startOrReload ecosystem.config.cjs --update-env
   pm2 save
   ```
3. SSL cert preko Certbot
4. Nginx reverse proxy

> ⚠️ **Nikoli ne kopiraj ročno na VPS!** Vse skozi CI/CD pipeline. Glej `DEPLOYMENT_GUIDE.md` pravila.

---

## Testi

```bash
npm test                # node:test + tsx, fetch je mockan (brez omrežja)
npm run typecheck:test  # tsc nad src/ in test/
```

Fixture-i v `test/fixtures.ts` imajo obliko, prepisano iz PHP kode (Resources, controllerji, `PostGISService::formatBeachResults`, `ProvidesWeather`), kode filtrov so iz seederjev. Testi pokrivajo: mapiranje filtrov, iskanje z državo (`country` + `country_code`), iskanje po imenu preko `/search` (točno, "closest match", več enakih imen, ponovni poskus brez splošnih besed), URL-je v `find_beaches_nearby` (hierarhični, `radius`, omejitev `limit`), vreme in "weather unavailable", jezike (`si` → `sl`, 19 besed za URL).

---

## Debugiranje

### MCP Inspector

```bash
cd mcp-server
npm run inspect
```

### Ročni test (HTTP)

```bash
# Initialize
curl -X POST https://mcp-dev.beachrex.com/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"test","version":"1.0.0"}}}'

# Iskanje po imenu
curl -X POST https://mcp-dev.beachrex.com/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"get_beach_details","arguments":{"identifier":"zlatni rat","locale":"si"}}}'

# Plaže v mestu z lastnostmi
curl -X POST https://mcp-dev.beachrex.com/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"search_beaches","arguments":{"city_code":"zadar","beach_type":"sand","limit":3,"locale":"sl"}}}'
```

---

## Datoteke

```
mcp-server/
├── package.json              # Odvisnosti (@modelcontextprotocol/sdk, zod), skripte build/test
├── tsconfig.json             # TypeScript konfiguracija (src → dist)
├── tsconfig.test.json        # Typecheck za src + test
├── ecosystem.config.cjs      # PM2 konfiguracija, en proces na MCP_ENV
├── .env.example              # Predloga za environment spremenljivke
├── .gitignore                # Ignorira dist/, node_modules/, .env
├── README.md                 # Ta datoteka
├── src/
│   ├── index.ts              # MCP server (6 toolov + 1 resource + transport)
│   ├── api-client.ts         # Beachrex API klient (klici, preslikava polj, URL gradnja, razreševanje imen)
│   ├── format.ts             # Besedilni odgovori toolov
│   └── locales.ts            # 19 jezikov, si↔sl, lokalizirana beseda za "plaže"
└── test/
    ├── fixtures.ts           # Odgovori API-ja (oblike iz PHP kode)
    ├── helpers.ts            # fetch mock
    ├── api-client.test.ts
    └── locales.test.ts
```

---

## Varnost

- **API key** je v okolju procesa na strežniku — AI agent nikoli ne vidi ključa
- MCP server je **proxy** med AI in Beachrex API-jem
- **Rate limiting** je implementiran na Laravel API strani (throttle middleware)
- **Stateless HTTP** — vsak request dobi fresh server instanco (no session leakage)
- **Nginx SSL** — vsi MCP klici so preko HTTPS
- **Timeout** 15 s na klic API-ja

---

## Znane omejitve

1. **Claude Desktop ne prikazuje inline slik iz remote MCP-jev** — zato MCP vrača sliko URL kot markdown link (klikabilen), ne kot base64 image content.
2. **Region/city slugi niso prevedeni** — `dalmatia-zadar` in `zadar` so angleško tudi v slovenskih URL-jih (stanje v bazi, ne MCP).
3. **`search_beaches` rezultati niso razvrščeni** — `/beaches` nima sortiranja; `meta.total_beaches` je število vrnjenih, ne vseh ujemajočih se plaž.
4. **Seznam plaž (`/beaches`, `/spatial/*`) nima imena regije** — samo slug, zato ga MCP v seznamih ne prikaže. Ime regije je v `get_beach_details` in `find_beach_by_name`.
5. **Ogledi** so na voljo samo v `get_beach_details` (`meta.views`). Vsak klic `get_beach_details` jih na API-ju poveča za 1 (tudi klici AI agentov).
6. **Temperatura morja ni na voljo** — API v `sea_temperature` vrača temperaturo zraka, zato je MCP ne prikaže kot temperaturo vode.
7. **Filtri:** `get_beach_filters` vrne samo vrednosti z `show_filter=true`; neznana koda (npr. "dog-friendly", če ne obstaja) vrne 0 zadetkov. V seederjih ni kode za "dog-friendly" ali "naturist" (obstaja samo `naturist_camp` med nastanitvami).
8. **`/search`** vrne največ 20 zadetkov na tip; ujemanje je odvisno od Meilisearch indeksa (`php artisan search:index`).
9. **Razdalje v API-ju** so v EPSG:3857 (prevelike); MCP jih popravi sam (glej `find_beaches_nearby`).

---

## Naslednji koraki

- [ ] Nginx/DNS/SSL za `mcp-uat.beachrex.com` (→ 3101) in `mcp.beachrex.com` (→ 3102)
- [ ] Dodati API key v okolje pm2, ko bodo endpoint-i zahtevali auth
- [ ] Dodati slovenske slug-e za regije in mesta v bazo (da bodo URL-ji popolnoma slovenski)
