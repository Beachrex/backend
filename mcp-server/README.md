# Beachrex MCP Server

Model Context Protocol (MCP) server za Beachrex API. Omogoča AI agentom (Claude Desktop, ChatGPT, Gemini, Cursor) iskanje plaž, pridobivanje podrobnosti, filtrov in vremena preko standardiziranega MCP vmesnika.

> **Status:** DEV okolje (`https://mcp-dev.beachrex.com`). UAT/PROD še nista konfigurirana.

---

## Architektura

```
AI agent (Claude Desktop / ChatGPT / Cursor)
    ↓ MCP protocol (JSON-RPC)
MCP Server (Node.js, PM2 na VPS)
    ↓ HTTPS
Laravel API (dev-api.beachrex.com)
    ↓
PostgreSQL + PostGIS (beachrex_dev baza)
```

### Transporta

| Transport | Kdaj | Konfiguracija |
|---|---|---|
| **stdio** | Lokalni razvoj (Cursor, Claude Desktop lokalno) | `node dist/index.js` brez `MCP_HTTP_PORT` |
| **Streamable HTTP** | Produkcija (Claude Desktop remote, ChatGPT) | `MCP_HTTP_PORT=3100` v `.env` ali `ecosystem.config.cjs` |

MCP server samodejno izbere transport na podlagi env variable `MCP_HTTP_PORT`:
- Če je nastavljena → HTTP način (stateless, fresh server per request)
- Če ni nastavljena → stdio način (persistent server)

---

## Tool-i

### 1. `search_beaches`

Iskanje plaž po geografski lokaciji ali tipu vodnega telesa.

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `continent_code` | string | ne | Koda kontinenta (EU, AS, AF, NA, SA, OC) |
| `country_code` | string | ne | ISO koda države (HR, IT, ES, GR) |
| `region_code` | string | ne | Koda regije (npr. `dalmatia-zadar`) |
| `city_code` | string | ne | Koda mesta (npr. `zadar`) |
| `island_code` | string | ne | Koda otoka |
| `waterbody` | string | ne | Tip vodnega telesa: sea, lake, river, pool |
| `limit` | number | ne | Max rezultatov (1-100, default 20) |
| `locale` | string | ne | Jezik (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk) |

**Primer odgovora:**
```
🏖️ **Beach Bajlo** (Hrvaška, Zadar) — sea, 0 views
   🖼️ [View photo](https://s3.beachrex.com/unsafe/fit-in/800x800/.../sl8.jpg)
   🔗 [View on Beachrex](https://dev.beachrex.com/si/plaze/evropa/hrvaska/dalmatia-zadar/zadar/beach-bajlo)
```

### 2. `find_beaches_nearby`

Iskanje plaž v bližini GPS koordinat (PostGIS spatial search).

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `latitude` | number | **da** | Geografska širina (npr. 44.1197 za Zadar) |
| `longitude` | number | **da** | Geografska dolžina (npr. 15.2319 za Zadar) |
| `radius_km` | number | ne | Iskalni radius v km (default 50) |
| `limit` | number | ne | Max rezultatov (1-100, default 20) |
| `locale` | string | ne | Jezik |

**Primer odgovora:**
```
🏖️ **Beach Maestrala** (Hrvaška) — sea, 0 views (1.08 km away)
   🖼️ [View photo](https://s3.beachrex.com/...)
   🔗 [View on Beachrex](https://dev.beachrex.com/si/plaze/...)
```

### 3. `get_beach_details`

Podrobnosti o konkretni plaži (opis, lokacija, karakteristike, galerija).

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `identifier` | string \| number | **da** | Slug (npr. `beach-bajlo`) ali ID (npr. 125). Če se pošlje ime, MCP sam pretvori v slug. |
| `locale` | string | ne | Jezik |

**Primer odgovora:**
```
🏖️ **Beach Kolovare**
location: Evropa > Hrvaška > dalmatia-zadar > Zadar
waterbody: Morje
views: 0
🖼️ [View photo](https://s3.beachrex.com/...)
🔗 [View on Beachrex](https://dev.beachrex.com/si/plaze/...)

Gallery:
  - [Primary photo](https://s3.beachrex.com/...)
  - [Photo](https://s3.beachrex.com/...)
```

### 4. `get_beach_filters`

Razpoložljivi filtri za iskanje plaž.

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `locale` | string | ne | Jezik |

### 5. `get_beach_weather`

Trenutno vreme na plaži.

| Parameter | Tip | Obvezno | Opis |
|---|---|---|---|
| `identifier` | string \| number | **da** | Slug ali ID plaže |

---

## Resources

| Resource | URI | Vsebina |
|---|---|---|
| `beachrex-context` | `beachrex://context` | Kontekst platforme: 3.774 plaž, 14 jezikov, top destinacije, pravila uporabe toolov (NE uporabljaj Google Images) |

AI agenti **morajo** prebrati ta resource pred odgovarjanjem na vprašanja o plažah.


## URL-ji

### MCP endpoint (HTTP transport)

| Okolje | URL | Status |
|---|---|---|
| DEV | `https://mcp-dev.beachrex.com/mcp` | ✅ aktiven |
| UAT | `https://mcp-uat.beachrex.com/mcp` | ⏳ ni konfiguriran |
| PROD | `https://mcp.beachrex.com/mcp` | ⏳ ni konfiguriran |

### Health check

```
GET https://mcp-dev.beachrex.com/health
→ {"status":"ok","server":"beachrex-mcp"}
```

### Beachrex frontend URL-ji (v tool odgovorih)

MCP gradi URL-je do plaž na beachrex.com glede na locale:

| Jezik | URL format | Primer |
|---|---|---|
| en | `/en/beaches/{continent}/{country}/...` | `/en/beaches/europe/croatia/...` |
| si (sl) | `/si/plaze/{continent}/{country}/...` | `/si/plaze/evropa/hrvaska/...` |
| de | `/de/strande/...` | `/de/strande/europa/kroatien/...` |
| hr | `/hr/plaze/...` | `/hr/plaze/evropa/hrvatska/...` |

**Opomba:** API uporablja `sl` kot Accept-Language za slovenščino, frontend pa `si` v URL-jih. MCP normalizira `sl` → `si` v URL-jih.

### CDN slik (v tool odgovorih)

Slike so na DigitalOcean Spaces preko Imagor CDN:

```
https://s3.beachrex.com/unsafe/fit-in/800x800/{relative_path}
```

MCP vrača sliko URL v tekstu kot markdown link:
```
🖼️ [View photo](https://s3.beachrex.com/unsafe/fit-in/800x800/...)
```

---

## Environment spremenljivke

| Variable | Default | Opis |
|---|---|---|
| `BEACHREX_API_URL` | `https://dev-api.beachrex.com` | URL Beachrex REST API |
| `BEACHREX_API_KEY` | (prazno) | API key (za endpoints, ki zahtevajo auth) |
| `BEACHREX_CDN_URL` | `https://s3.beachrex.com/unsafe/fit-in/800x800/` | CDN base za slike |
| `BEACHREX_CDN_THUMB` | `https://s3.beachrex.com/unsafe/fit-in/200x200/` | CDN base za thumbnail slike |
| `BEACHREX_FRONTEND_URL` | `https://dev.beachrex.com` | Frontend URL za "View on Beachrex" linke |
| `MCP_HTTP_PORT` | (ni nastavljena) | Če je nastavljena → HTTP transport, sicer stdio |

Glej `.env.example` za predlogo.

---

## Namestitev

### Lokalni razvoj (stdio)

```bash
cd mcp-server
npm install
npm run build
```

Dodaj v `~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "beachrex": {
      "command": "node",
      "args": ["/path/to/BR-api2/mcp-server/dist/index.js"],
      "env": {
        "BEACHREX_API_URL": "https://dev-api.beachrex.com"
      }
    }
  }
}
```

### Produkcija (HTTP, PM2 na VPS)

MCP server teče na VPS kot PM2 proces na portu 3100. Nginx reverse proxy na `mcp-dev.beachrex.com`.

**PM2 konfiguracija:** `ecosystem.config.cjs`
```javascript
module.exports = {
  apps: [{
    name: 'beachrex-mcp',
    script: 'dist/index.js',
    cwd: '/opt/beachrex/api_dev/mcp-server',
    env: {
      BEACHREX_API_URL: 'https://dev-api.beachrex.com',
      MCP_HTTP_PORT: '3100',
    },
  }],
};
```

**Nginx:** `mcp-dev.beachrex.com` → `127.0.0.1:3100` (glej `deploy/nginx/beachrex.conf`)

---

## Deploy

MCP server je del backend repozitorija (`BR-api2/mcp-server/`). Deploy poteka skozi GitHub Actions:

1. `rsync` kode na `/opt/beachrex/api_{env}/mcp-server/`
2. **Samodejni rebuild** (ker `dist/` ni v gitu):
   ```bash
   cd /opt/beachrex/api_{env}/mcp-server
   npm install
   npm run build
   pm2 restart beachrex-mcp
   ```
3. SSL cert preko Certbot
4. Nginx reverse proxy

> ⚠️ **Nikoli ne kopiraj ročno na VPS!** Vse skozi CI/CD pipeline. Glej `DEPLOYMENT_GUIDE.md` pravila.

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

# Search beaches
curl -X POST https://mcp-dev.beachrex.com/mcp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"search_beaches","arguments":{"city_code":"zadar","limit":3,"locale":"sl"}}}'
```

---

## Datoteke

```
mcp-server/
├── package.json              # Odvisnosti (@modelcontextprotocol/sdk, zod)
├── tsconfig.json            # TypeScript konfiguracija
├── ecosystem.config.cjs     # PM2 konfiguracija (produkcija)
├── .env.example             # Predloga za environment spremenljivke
├── .gitignore               # Ignorira dist/, node_modules/, .env
├── README.md                 # Ta datoteka
└── src/
    ├── index.ts              # MCP server (5 toolov + 1 resource + transport)
    └── api-client.ts        # Beachrex API klient (preslikava API polj, URL gradnja)
```

---

## Varnost

- **API key** je shranjen v `.env` na serverju — AI agent nikoli ne vidi ključa
- MCP server je **proxy** med AI in Beachrex API-jem
- **Rate limiting** je implementiran na Laravel API strani (throttle middleware)
- **Stateless HTTP** — vsak request dobi fresh server instanco (no session leakage)
- **Nginx SSL** — vsi MCP klici so preko HTTPS
- **Tool opisi** eksplicitno prepovedujejo Google Images in web search

---

## Znane omejitve

1. **Claude Desktop ne prikazuje inline slik iz remote MCP-jev** — zato MCP vrača sliko URL kot markdown link (klikabilen), ne kot base64 image content
2. **Region/city slugi niso prevedeni** — `dalmatia-zadar` in `zadar` so angleško tudi v slovenskih URL-jih (database issue, ne MCP issue)
3. **`beach_views` je vedno 0** — API ne vrača števila ogledov v list endpoint-u

---

## Naslednji koraki

- [ ] Konfigurirati UAT MCP (`mcp-uat.beachrex.com`) ko bo UAT baza popolna
- [ ] Konfigurirati PROD MCP (`mcp.beachrex.com`) po DNS preklopu
- [ ] Dodati API key v `.env` ko bodo endpoint-i zahtevali auth
- [ ] Preveriti ali `beach_views` pride iz API-ja ali je treba dodati v BeachResource
- [ ] Dodati slovenske slug-e za regije in mesta v bazo (da bodo URL-ji popolnoma slovenski)