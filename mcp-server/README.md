# Beachrex MCP Server

Model Context Protocol (MCP) server za Beachrex API. Omogoča AI agentom (Claude, ChatGPT, Gemini) iskanje plaž, pridobivanje podrobnosti, filtrov in vremena preko standardiziranega MCP vmesnika.

## Namestitev (lokalni razvoj)

```bash
cd mcp-server
npm install
npm run build
```

## Konfiguracija

```bash
cp .env.example .env
# Uredi .env — nastavi BEACHREX_API_URL in BEACHREX_API_KEY
```

## Uporaba v Cursor/Claude Desktop (stdio)

Dodaj v `claude_desktop_config.json` ali Cursor MCP config:

```json
{
  "mcpServers": {
    "beachrex": {
      "command": "node",
      "args": ["/path/to/BR-api2/mcp-server/dist/index.js"],
      "env": {
        "BEACHREX_API_URL": "https://dev-api.beachrex.com",
        "BEACHREX_API_KEY": ""
      }
    }
  }
}
```

## Uporaba z MCP Inspector (debugiranje)

```bash
npm run inspect
```

## Razpoložljivi tool-i

| Tool | Namenska | Parametri |
|---|---|---|
| `search_beaches` | Iskanje plaž po lokaciji ali tipu | `continent_code`, `country_code`, `waterbody`, `limit`, `locale` |
| `get_beach_details` | Podrobnosti o konkretni plaži | `identifier` (ID ali slug), `locale` |
| `get_beach_filters` | Razpoložljivi filtri (tipi vodnih teles) | `locale` |
| `get_beach_weather` | Trenutno vreme na plaži | `identifier` (ID ali slug) |

## Resources

| Resource | URI | Vsebina |
|---|---|---|
| `beachrex-context` | `beachrex://context` | Kontekst platforme: število plaž, jeziki, top destinacije |

## Varnost

- API key je shranjen v `.env` na serverju — AI agent nikoli ne vidi ključa
- MCP server je proxy med AI in Beachrex API-jem
- Rate limiting je implementiran na Laravel API strani (throttle middleware)

## Deploy na VPS (prihodnje)

```bash
# Na VPS:
cd /opt/beachrex/mcp-server
npm install --production
npm run build
# Zaženi s PM2 ali kot Docker kontejner
```
