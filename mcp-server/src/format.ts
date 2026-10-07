/**
 * Plain-text/markdown rendering of API results for the MCP tools.
 * Kept separate from index.ts so it can be unit-tested.
 */

import type {
  BeachSummary, BeachDetail, MatchInfo, SearchResult, NearbyResult,
  NameSearchResult, FilterGroup, WeatherResult,
} from './api-client.js';
import { GEO_FILTER_KEYS, ATTRIBUTE_FILTER_PARAMS } from './api-client.js';

const MAX_DESCRIPTION_CHARS = 2000;
const MAX_GEO_OPTIONS = 150;

function beachLine(b: BeachSummary, opts: { showDistance?: boolean } = {}): string {
  const place = [b.city, b.island, b.region, b.country].filter(Boolean);
  // de-duplicate (city and island are sometimes the same name)
  const where = [...new Set(place)].join(', ');
  const dist = opts.showDistance && b.distance_km !== undefined ? ` — ${b.distance_km} km away` : '';
  const ids = ` [id ${b.id}${b.slug ? `, slug ${b.slug}` : ''}]`;
  const tags = b.tags.length ? `\n   Tags: ${b.tags.join(', ')}` : '';
  const img = b.image_url ? `\n   🖼️ [View photo](${b.image_url})` : '';
  const url = b.beachrex_url ? `\n   🔗 [View on Beachrex](${b.beachrex_url})` : '';
  return `🏖️ **${b.name}**${where ? ` (${where})` : ''}${dist}${ids}${tags}${img}${url}`;
}

export function formatSearch(r: SearchResult): string {
  if (r.beaches.length === 0) {
    return 'No beaches match these filters in the Beachrex database. Try fewer filters, check the codes with get_beach_filters, or use find_beach_by_name.';
  }
  const more = r.beaches.length >= r.limit
    ? `\n\n(Showing the first ${r.limit}. More beaches may match: raise "limit" (max 100) or add filters. Results are not ranked.)`
    : '';
  return `Showing ${r.beaches.length} beach(es):\n\n${r.beaches.map((b) => beachLine(b)).join('\n\n')}${more}`;
}

export function formatNearby(r: NearbyResult, lat: number, lng: number): string {
  if (r.beaches.length === 0) {
    return `No beaches in the Beachrex database within ${r.radius_km} km of ${lat}, ${lng}${r.radius_km < 100 ? '. Try a larger radius_km' : ''}.`;
  }
  const more = r.beaches.length >= r.limit ? `\n\n(Showing the closest ${r.limit}. Raise "limit" (max 100) for more.)` : '';
  return `${r.beaches.length} beach(es) within ${r.radius_km} km of ${lat}, ${lng}, closest first (straight-line distance):\n\n${r.beaches.map((b) => beachLine(b, { showDistance: true })).join('\n\n')}${more}`;
}

function matchNote(m: MatchInfo): string {
  if (m.kind === 'closest') {
    const alt = m.alternatives.length
      ? `\nOther candidates:\n${m.alternatives.map((a) => `  - ${a.name}${a.city || a.country ? ` (${[a.city, a.country].filter(Boolean).join(', ')})` : ''} [id ${a.id}]`).join('\n')}`
      : '';
    return `ℹ️ No beach is named exactly "${m.query}". Showing the closest match.${alt}\n\n`;
  }
  if (m.kind === 'exact' && m.alternatives.length) {
    return `ℹ️ ${m.alternatives.length + 1} beaches are named "${m.query}". Showing the first; the others:\n${m.alternatives.map((a) => `  - ${a.name} (${[a.city, a.region, a.country].filter(Boolean).join(', ')}) [id ${a.id}]`).join('\n')}\nCall get_beach_details with the id to see another.\n\n`;
  }
  return '';
}

function truncate(s: string, n: number): string {
  return s.length > n ? `${s.slice(0, n).replace(/\s+\S*$/, '')} …` : s;
}

export function formatDetails(b: BeachDetail & { match: MatchInfo }): string {
  const lines: string[] = [];
  lines.push(`${matchNote(b.match)}🏖️ **${b.name}** [id ${b.id}${b.slug ? `, slug ${b.slug}` : ''}]`);
  const location = [b.continent, b.country, b.region, b.island, b.city].filter(Boolean);
  if (location.length) lines.push(`Location: ${[...new Set(location)].join(' > ')}`);
  if (b.latitude || b.longitude) lines.push(`Coordinates: ${b.latitude}, ${b.longitude}`);
  if (b.waterbody) lines.push(`Waterbody: ${b.waterbody}`);
  if (b.beach_type) lines.push(`Beach type: ${b.beach_type}`);
  if (b.accessibility) lines.push(`Access: ${b.accessibility}`);
  if (b.parking !== null) lines.push(`Parking: ${b.parking ? 'yes' : 'no'}`);
  if (b.facilities.length) lines.push(`Facilities: ${b.facilities.join(', ')}`);
  if (b.culinary_offers.length) lines.push(`Food & drink: ${b.culinary_offers.join(', ')}`);
  if (b.accommodation_around.length) lines.push(`Accommodation nearby: ${b.accommodation_around.join(', ')}`);
  if (b.sporting_activities.length) lines.push(`Activities: ${b.sporting_activities.join(', ')}`);
  if (b.user_types.length) lines.push(`Suitable for: ${b.user_types.join(', ')}`);
  if (b.natural_conditions.length) lines.push(`Natural conditions: ${b.natural_conditions.join(', ')}`);
  if (b.views) lines.push(`Views on Beachrex: ${b.views}`);
  if (b.image_url) lines.push(`🖼️ [View photo](${b.image_url})`);
  if (b.beachrex_url) lines.push(`🔗 [View on Beachrex](${b.beachrex_url})`);
  if (b.description) lines.push('', truncate(b.description, MAX_DESCRIPTION_CHARS));
  const others = b.media.filter((m) => !m.is_primary || m.url !== b.image_url);
  if (others.length) {
    lines.push('', 'Gallery:', ...others.slice(0, 12).map((m) => `  - [${m.is_primary ? 'Primary photo' : 'Photo'}](${m.url})`));
    if (others.length > 12) lines.push(`  … and ${others.length - 12} more on the Beachrex page`);
  }
  return lines.join('\n');
}

export function formatNameSearch(r: NameSearchResult): string {
  const parts: string[] = [];
  if (r.effective_query !== r.query) parts.push(`(No results for "${r.query}"; searched for "${r.effective_query}" instead.)`);
  if (r.beaches.length === 0 && r.places.length === 0) {
    return `${parts.join('\n')}${parts.length ? '\n' : ''}Nothing in the Beachrex database matches "${r.query}". Try another spelling, the local name, or search_beaches / find_beaches_nearby.`;
  }
  if (r.beaches.length) {
    parts.push(`Beaches matching "${r.effective_query}" (best match first):\n\n${r.beaches.map((b) => beachLine(b)).join('\n\n')}`);
  } else {
    parts.push(`No beach is named "${r.effective_query}".`);
  }
  if (r.places.length) {
    parts.push(`Places matching "${r.effective_query}". To list ALL beaches in one of them, call search_beaches with the given parameter:\n${r.places.map((p) => {
      const ctx = [p.island_name, p.region_name, p.country_name].filter((x) => x && x !== p.name).join(', ');
      return `  - ${p.type} **${p.name}**${ctx ? ` (${ctx})` : ''} → ${p.filter_param}="${p.code}"${p.filter_param !== 'country_code' && p.country_code ? `, country_code="${p.country_code}"` : ''}`;
    }).join('\n')}`);
  }
  return parts.join('\n\n');
}

export function formatFilters(groups: FilterGroup[], country?: string): string {
  if (groups.length === 0) return 'The Beachrex API returned no filter options.';
  // Groups the tools cannot pass (a filter added to the API later) are left out.
  const attr = groups.filter((g) => !GEO_FILTER_KEYS.includes(g.key) && ATTRIBUTE_FILTER_PARAMS.includes(g.param));
  const geo = groups.filter((g) => GEO_FILTER_KEYS.includes(g.key));
  const out: string[] = [
    'Beachrex filter codes. Use the CODE (left of the colon) as the value of the named parameter in search_beaches or find_beaches_nearby. Several codes in one parameter match beaches with ANY of them; different parameters must ALL match.',
  ];
  for (const g of attr) {
    const opts = g.boolean
      ? '  true / false'
      : g.options.map((o) => `  ${o.code}: ${o.name}`).join('\n');
    out.push(`${g.title} → parameter "${g.param}"${g.boolean ? ' (boolean)' : ''}\n${opts}`);
  }
  for (const g of geo) {
    if (g.key !== 'country' && !country) {
      out.push(`${g.title} → parameter "${g.param}": ${g.options.length} options. Call get_beach_filters with country_code to list them for one country, or use find_beach_by_name to look up a place.`);
      continue;
    }
    const shown = g.options.slice(0, MAX_GEO_OPTIONS);
    const rest = g.options.length - shown.length;
    out.push(`${g.title} → parameter "${g.param}"\n${shown.map((o) => `  ${o.code}: ${o.name}`).join('\n')}${rest > 0 ? `\n  … and ${rest} more (use find_beach_by_name to look one up)` : ''}`);
  }
  return out.join('\n\n');
}

function fmt(n: number | null, unit: string): string | null {
  return n === null ? null : `${n}${unit}`;
}

const COMPASS = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];

export function formatWeather(w: WeatherResult, identifier: string | number): string {
  if (!w.available) {
    return `Weather unavailable for "${identifier}": ${w.reason}. Do not guess the weather; say it is currently unavailable.`;
  }
  const lines: string[] = [];
  if (w.match.kind === 'closest') lines.push(`ℹ️ No beach is named exactly "${w.match.query}"; showing weather for the closest match (${w.beach_slug || `id ${w.beach_id}`}).`);
  lines.push(`Current weather at ${w.beach_slug || `beach ${w.beach_id}`}${w.location_name ? ` (station: ${w.location_name})` : ''}:`);
  const add = (label: string, v: string | null) => { if (v !== null && v !== '') lines.push(`   ${label}: ${v}`); };
  add('Condition', [w.condition, w.description].filter(Boolean).join(' — ') || null);
  add('Air temperature', fmt(w.temperature_c, ' °C'));
  add('Feels like', fmt(w.feels_like_c, ' °C'));
  if (w.temp_min_c !== null && w.temp_max_c !== null) add('Min / max', `${w.temp_min_c} / ${w.temp_max_c} °C`);
  add('Humidity', fmt(w.humidity_pct, ' %'));
  if (w.wind_speed_ms !== null) {
    const dir = w.wind_deg !== null ? ` from ${COMPASS[Math.round(((w.wind_deg % 360) / 45)) % 8]} (${w.wind_deg}°)` : '';
    add('Wind', `${w.wind_speed_ms} m/s (${Math.round(w.wind_speed_ms * 3.6)} km/h)${dir}`);
  }
  add('Clouds', fmt(w.clouds_pct, ' %'));
  add('Visibility', w.visibility_m !== null ? `${Math.round(w.visibility_m / 100) / 10} km` : null);
  add('Pressure', fmt(w.pressure_hpa, ' hPa'));
  add('Sunrise / sunset (UTC)', w.sunrise_utc && w.sunset_utc ? `${w.sunrise_utc} / ${w.sunset_utc}` : null);
  lines.push(`   Water temperature: ${w.water_temperature_c !== null ? `${w.water_temperature_c} °C` : 'not available'}`);
  if (w.fetched_at) lines.push(`   (Source: OpenWeatherMap via Beachrex, cached up to 1 hour; served ${w.fetched_at})`);
  return lines.join('\n');
}
