/**
 * Beachrex MCP Server
 *
 * Exposes Beachrex beach search, details, filters, and weather
 * to AI agents (Claude, ChatGPT, Gemini) via the Model Context Protocol.
 *
 * Transport: Streamable HTTP (production) / stdio (local dev)
 * Auth: API key stored server-side, never exposed to AI
 *
 * Usage:
 *   BEACHREX_API_URL=https://dev-api.beachrex.com \
 *   BEACHREX_API_KEY=your-key \
 *   node dist/index.js
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import {
  searchBeaches,
  getBeachDetails,
  getBeachFilters,
  getBeachWeather,
} from './api-client.js';

const server = new McpServer({
  name: 'beachrex',
  version: '1.0.0',
});

// ───────────────────────── TOOLS ─────────────────────────

// Tool 1: search_beaches
server.tool(
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
        `🏖️ ${b.name} (${b.country}) — ${b.waterbody}, ${b.beach_views} views\n   📍 ${b.latitude}, ${b.longitude}\n   🔗 /beaches/${b.slug}`
      ).join('\n\n');

      return {
        content: [{
          type: 'text' as const,
          text: `Found ${result.total} beach(es):\n\n${formatted}`,
        }],
      };
    } catch (error) {
      return {
        content: [{
          type: 'text' as const,
          text: `Error searching beaches: ${error instanceof Error ? error.message : 'Unknown error'}`,
        }],
        isError: true,
      };
    }
  },
);

// Tool 2: get_beach_details
server.tool(
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

      const text = `🏖️ ${beach.name}
📍 ${beach.continent} > ${beach.country} > ${beach.region}${beach.city ? ' > ' + beach.city : ''}${beach.island ? ' > ' + beach.island : ''}
💧 ${beach.waterbody}
👁️ ${beach.beach_views} views

📝 ${beach.description || 'No description available.'}

📷 Media:
${mediaList}`;

      return {
        content: [{ type: 'text' as const, text }],
      };
    } catch (error) {
      return {
        content: [{
          type: 'text' as const,
          text: `Error getting beach details: ${error instanceof Error ? error.message : 'Unknown error'}`,
        }],
        isError: true,
      };
    }
  },
);

// Tool 3: get_beach_filters
server.tool(
  'get_beach_filters',
  'Get available filter options for beach searches — waterbody types (sea, lake, river, pool), beach types, and continents with beach counts. Use this to help a user narrow down their beach search.',
  {
    locale: z.string().optional().default('en').describe('Language code'),
  },
  async (params) => {
    try {
      const filters = await getBeachFilters(params.locale);

      const waterbodies = filters.waterbodies
        .map((w) => `  ${w.code}: ${w.name} (${w.beach_count} beaches)`).join('\n');

      const text = `Available beach filters:

💧 Waterbody types:
${waterbodies}

Use these codes as the "waterbody" parameter in search_beaches.`;

      return {
        content: [{ type: 'text' as const, text }],
      };
    } catch (error) {
      return {
        content: [{
          type: 'text' as const,
          text: `Error getting filters: ${error instanceof Error ? error.message : 'Unknown error'}`,
        }],
        isError: true,
      };
    }
  },
);

// Tool 4: get_beach_weather
server.tool(
  'get_beach_weather',
  'Get current weather conditions for a specific beach. Returns temperature, condition, humidity, and wind speed. Use this when a user asks about weather at a beach.',
  {
    identifier: z.union([z.string(), z.number()]).describe('Beach ID (number) or slug (string)'),
  },
  async (params) => {
    try {
      const weather = await getBeachWeather(params.identifier);

      const text = `🌤️ Weather at this beach:
   🌡️ Temperature: ${weather.temperature}°C
   ☁️ Condition: ${weather.condition}
   💧 Humidity: ${weather.humidity}%
   💨 Wind: ${weather.wind_speed} km/h`;

      return {
        content: [{ type: 'text' as const, text }],
      };
    } catch (error) {
      return {
        content: [{
          type: 'text' as const,
          text: `Error getting weather: ${error instanceof Error ? error.message : 'Unknown error'}`,
        }],
        isError: true,
      };
    }
  },
);

// ───────────────────── RESOURCES ─────────────────────

server.resource(
  'beachrex-context',
  'beachrex://context',
  { mimeType: 'text/plain', description: 'Beachrex platform context — total beaches, supported languages, top destinations, and beach types. AI agents should read this before answering questions about beaches.' },
  () => ({
    contents: [{
      uri: 'beachrex://context',
      mimeType: 'text/plain',
      text: `Beachrex is a beach discovery platform covering 3,774 beaches across the world.

Supported languages (14): English (en), Slovenian (sl), German (de), Croatian (hr), French (fr), Spanish (es), Italian (it), Ukrainian (ua), Russian (ru), Polish (pl), Czech (cs), Hungarian (hu), Bulgarian (bg), Slovak (sk).

Top destinations:
- Croatia (HR): 127 beaches
- Italy (IT): 89 beaches
- Spain (ES): 76 beaches
- Greece (GR): 64 beaches
- France (FR): 58 beaches

Beach types (waterbody):
- sea: coastal beaches along seas and oceans
- lake: beaches on lakes
- river: beaches along rivers
- pool: outdoor/indoor pools

When a user asks about beaches, use the search_beaches tool with country_code and/or waterbody filters.
For detailed info about a specific beach, use get_beach_details with the beach slug or ID.
For weather at a beach, use get_beach_weather.`,
    }],
  }),
);

// ───────────────────── START ─────────────────────

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error('Beachrex MCP Server running on stdio');
}

main().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});
