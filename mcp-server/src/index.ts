/**
 * Beachrex MCP Server
 *
 * Exposes Beachrex beach search, details, filters, and weather
 * to AI agents (Claude, ChatGPT, Gemini) via the Model Context Protocol.
 *
 * Transport: auto-detects based on env
 *   - MCP_HTTP_PORT set → Streamable HTTP (cloud AI, production, stateless)
 *   - no MCP_HTTP_PORT   → stdio (local dev, Cursor/Claude Desktop)
 *
 * Auth: API key stored server-side, never exposed to AI
 *
 * Usage (stdio — local dev):
 *   BEACHREX_API_URL=https://dev-api.beachrex.com node dist/index.js
 *
 * Usage (HTTP — cloud AI):
 *   MCP_HTTP_PORT=3100 BEACHREX_API_URL=https://dev-api.beachrex.com node dist/index.js
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import { z } from 'zod';
import {
  searchBeaches,
  getBeachDetails,
  getBeachFilters,
  getBeachWeather,
} from './api-client.js';

/**
 * Factory: creates a fresh McpServer with all tools/resources registered.
 * In stateless HTTP mode, a new server instance is created per request
 * to avoid session conflicts (per MCP SDK pattern).
 */
function createServer(): McpServer {
  const mcpServer = new McpServer({
    name: 'beachrex',
    version: '1.0.0',
  });

  // ─── Tool 1: search_beaches ───
  mcpServer.tool(
    'search_beaches',
    'Search for beaches by geographic location (continent, country, region, city, island) or by waterbody type (sea, lake, river, pool). Returns a compact list of matching beaches with name, country, coordinates, and popularity. Use this when a user asks to find or discover beaches in a specific area.',
    {
      continent_code: z.string().optional().describe('Continent code (e.g., EU, AS, AF, NA, SA, OC)'),
      country_code: z.string().optional().describe('ISO country code (e.g., HR, IT, ES, GR)'),
      region_code: z.string().optional().describe('Region code within a country'),
      city_code: z.string().optional().describe('City code'),
      island_code: z.string().optional().describe('Island code'),
      waterbody: z.string().optional().describe('Type of waterbody: sea, lake, river, pool'),
      limit: z.number().optional().default(20).describe('Max results (1-100, default 20)'),
      locale: z.string().optional().default('en').describe('Language code (en, sl, de, hr, fr, es, it, ru, pl, cs, hu, bg, sk, ua)'),
    },
    async (params) => {
      try {
        const result = await searchBeaches(params, params.locale);
        const formatted = result.beaches.map((b) =>
          `beach ${b.name} (${b.country}) — ${b.waterbody}, ${b.beach_views} views\n   coords: ${b.latitude}, ${b.longitude}\n   slug: ${b.slug}`
        ).join('\n\n');
        return {
          content: [{ type: 'text' as const, text: `Found ${result.total} beach(es):\n\n${formatted}` }],
        };
      } catch (error) {
        return {
          content: [{ type: 'text' as const, text: `Error: ${error instanceof Error ? error.message : 'Unknown error'}` }],
          isError: true,
        };
      }
    },
  );

  // ─── Tool 2: get_beach_details ───
  mcpServer.tool(
    'get_beach_details',
    'Get comprehensive details about a specific beach, including description, location hierarchy (continent, country, region, city, island), media/images, characteristics, and nearby activities. Use this when a user asks for more info about a specific beach.',
    {
      identifier: z.union([z.string(), z.number()]).describe('Beach ID (number) or slug (string, e.g., "zlatni-rat")'),
      locale: z.string().optional().default('en').describe('Language code'),
    },
    async (params) => {
      try {
        const beach = await getBeachDetails(params.identifier, params.locale);
        const mediaList = beach.media.length > 0
          ? beach.media.map((m) => `  - ${m.type}: ${m.url}${m.is_primary ? ' (primary)' : ''}`).join('\n')
          : '  No media available';
        const text = `beach ${beach.name}\nlocation: ${beach.continent} > ${beach.country} > ${beach.region}${beach.city ? ' > ' + beach.city : ''}${beach.island ? ' > ' + beach.island : ''}\nwaterbody: ${beach.waterbody}\nviews: ${beach.beach_views}\n\n${beach.description || 'No description available.'}\n\nMedia:\n${mediaList}`;
        return { content: [{ type: 'text' as const, text }] };
      } catch (error) {
        return {
          content: [{ type: 'text' as const, text: `Error: ${error instanceof Error ? error.message : 'Unknown error'}` }],
          isError: true,
        };
      }
    },
  );

  // ─── Tool 3: get_beach_filters ───
  mcpServer.tool(
    'get_beach_filters',
    'Get available filter options for beach searches — waterbody types (sea, lake, river, pool), beach types, and continents with beach counts. Use this to help a user narrow down their beach search.',
    { locale: z.string().optional().default('en').describe('Language code') },
    async (params) => {
      try {
        const filters = await getBeachFilters(params.locale);
        const waterbodies = filters.waterbodies.map((w) => `  ${w.code}: ${w.name} (${w.beach_count} beaches)`).join('\n');
        return { content: [{ type: 'text' as const, text: `Available beach filters:\n\nWaterbody types:\n${waterbodies}\n\nUse these codes as the "waterbody" parameter in search_beaches.` }] };
      } catch (error) {
        return {
          content: [{ type: 'text' as const, text: `Error: ${error instanceof Error ? error.message : 'Unknown error'}` }],
          isError: true,
        };
      }
    },
  );

  // ─── Tool 4: get_beach_weather ───
  mcpServer.tool(
    'get_beach_weather',
    'Get current weather conditions for a specific beach. Returns temperature, condition, humidity, and wind speed. Use this when a user asks about weather at a beach.',
    { identifier: z.union([z.string(), z.number()]).describe('Beach ID (number) or slug (string)') },
    async (params) => {
      try {
        const weather = await getBeachWeather(params.identifier);
        return { content: [{ type: 'text' as const, text: `Weather at this beach:\n   Temperature: ${weather.temperature}C\n   Condition: ${weather.condition}\n   Humidity: ${weather.humidity}%\n   Wind: ${weather.wind_speed} km/h` }] };
      } catch (error) {
        return {
          content: [{ type: 'text' as const, text: `Error: ${error instanceof Error ? error.message : 'Unknown error'}` }],
          isError: true,
        };
      }
    },
  );

  // ─── Resource: beachrex-context ───
  mcpServer.resource(
    'beachrex-context',
    'beachrex://context',
    { mimeType: 'text/plain', description: 'Beachrex platform context — total beaches, supported languages, top destinations, and beach types. AI agents should read this before answering questions about beaches.' },
    () => ({
      contents: [{
        uri: 'beachrex://context',
        mimeType: 'text/plain',
        text: `Beachrex is a beach discovery platform covering 3,774 beaches across the world.\n\nSupported languages (14): en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk.\n\nTop destinations:\n- Croatia (HR): 127 beaches\n- Italy (IT): 89 beaches\n- Spain (ES): 76 beaches\n\nBeach types (waterbody): sea, lake, river, pool.\n\nUse search_beaches to find beaches. Use get_beach_details for info about a specific beach. Use get_beach_weather for weather.`,
      }],
    }),
  );

  return mcpServer;
}

// ───────────────────── START ─────────────────────

async function main() {
  const httpPort = process.env.MCP_HTTP_PORT;

  if (httpPort) {
    // HTTP transport — stateless mode (one server per request)
    const port = parseInt(httpPort, 10);
    const http = await import('http');

    const httpServer = http.createServer(async (req, res) => {
      // Health check
      if (req.url === '/health') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'ok', server: 'beachrex-mcp' }));
        return;
      }

      // MCP endpoint — fresh server + transport per request (stateless pattern)
      if (req.url === '/mcp' || req.url === '/') {
        // Read request body
        let body = '';
        req.on('data', (chunk) => { body += chunk; });
        req.on('end', async () => {
          try {
            const parsedBody = body ? JSON.parse(body) : undefined;
            const mcpServer = createServer();
            const transport = new StreamableHTTPServerTransport({
              sessionIdGenerator: undefined,
            });
            await mcpServer.connect(transport);
            await transport.handleRequest(req, res, parsedBody);
            res.on('close', () => {
              transport.close();
              mcpServer.close();
            });
          } catch (error) {
            console.error('MCP request error:', error);
            if (!res.headersSent) {
              res.writeHead(500, { 'Content-Type': 'application/json' });
              res.end(JSON.stringify({ jsonrpc: '2.0', error: { code: -32603, message: 'Internal server error' }, id: null }));
            }
          }
        });
        return;
      }

      // 404
      res.writeHead(404, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: 'Not found' }));
    });

    httpServer.listen(port);
    console.log(`Beachrex MCP Server running on HTTP port ${port}`);
    console.log(`  Health check: http://localhost:${port}/health`);
    console.log(`  MCP endpoint:  http://localhost:${port}/mcp`);
  } else {
    // stdio transport — for local dev (Cursor, Claude Desktop)
    // Single persistent server for stdio
    const mcpServer = createServer();
    const transport = new StdioServerTransport();
    await mcpServer.connect(transport);
    console.error('Beachrex MCP Server running on stdio');
  }
}

main().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});
