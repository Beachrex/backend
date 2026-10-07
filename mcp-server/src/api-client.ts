/**
 * Beachrex API Client — wraps the Beachrex REST API (Laravel, /api/v1).
 * API key is stored server-side, never exposed to AI.
 *
 * Every response shape mapped here was taken from the PHP code, not guessed:
 *   /beaches                        BeachController::index + BeachResource
 *   /beaches/filters                FilterController::index
 *   /search                         SearchController::search
 *   /spatial/beaches/within-radius  PostGISController::beachesWithinRadius + PostGISService
 *   /beach-details/{id}             BeachDetailsController::show + BeachDetailsResource
 *   /beach-details/{id}/weather     BeachDetailsController::weather + ProvidesWeather + WeatherService
 */
/* eslint-disable @typescript-eslint/no-explicit-any */

import { normalizeLocale, frontendLocale, getBeachesWord, DEFAULT_LOCALE } from './locales.js';

// ───────────────────── config ─────────────────────

/** Read env lazily so tests (and pm2 env changes) are picked up. */
function cfg() {
  return {
    apiUrl: (process.env.BEACHREX_API_URL || 'https://dev-api.beachrex.com').replace(/\/+$/, ''),
    apiKey: process.env.BEACHREX_API_KEY || '',
    cdnBase: process.env.BEACHREX_CDN_URL || 'https://s3.beachrex.com/unsafe/fit-in/800x800/',
    cdnThumb: process.env.BEACHREX_CDN_THUMB || 'https://s3.beachrex.com/unsafe/fit-in/200x200/',
    // Direct DigitalOcean Spaces URL for paths with non-ASCII characters (Imagor can't serve them reliably).
    cdnAssets: process.env.BEACHREX_CDN_ASSETS || 'https://beachrex.fra1.digitaloceanspaces.com/public/',
    frontendUrl: (process.env.BEACHREX_FRONTEND_URL || 'https://dev.beachrex.com').replace(/\/+$/, ''),
    timeoutMs: Number(process.env.BEACHREX_API_TIMEOUT_MS) || 15000,
  };
}

/** MCP-side limits. */
export const DEFAULT_LIMIT = 20;
/** /beaches accepts any limit; we cap it so tool output stays readable. */
export const MAX_LIMIT = 100;
/** SearchController validates limit as integer 1..20. */
export const SEARCH_MAX_LIMIT = 20;
/** PostGISController::beachesWithinRadius validates radius 0.1..20000 (km). */
export const RADIUS_MIN_KM = 0.1;
export const RADIUS_MAX_KM = 20000;
export const DEFAULT_RADIUS_KM = 25;

// ───────────────────── types ─────────────────────

/** Geographic filters (BeachFilterService::applyFilters / buildRawFilters). */
export interface GeoFilters {
  continent_code?: string;
  country_code?: string;
  region_code?: string;
  island_code?: string;
  city_code?: string;
}

type Multi = string | string[];

/**
 * Attribute filters. Keys are the exact request param names read by
 * BeachFilterService (both applyFilters and buildRawFilters).
 * Several values in one filter = ANY of them (SQL IN); different filters = ALL (AND).
 */
export interface AttributeFilters {
  waterbody?: Multi;
  beach_type?: Multi;
  user_type?: Multi;
  location_infrastructure?: Multi;
  sporting_activity?: Multi;
  accessibility?: Multi;
  culinary_offer?: Multi;
  accommodation_around?: Multi;
  natural_location_condition?: Multi;
  underwater_characteristic?: Multi;
  occupancy_rate?: Multi;
  area_description?: Multi;
  beach_inclination?: Multi;
  swimming_depth?: Multi;
  parking_capacity?: Multi;
  recommended_transport_type?: Multi;
  parking?: boolean;
  has_swimming_pool?: boolean;
}

export const MULTI_FILTER_KEYS = [
  'waterbody', 'beach_type', 'user_type', 'location_infrastructure', 'sporting_activity',
  'accessibility', 'culinary_offer', 'accommodation_around', 'natural_location_condition',
  'underwater_characteristic', 'occupancy_rate', 'area_description', 'beach_inclination',
  'swimming_depth', 'parking_capacity', 'recommended_transport_type',
] as const;
export const BOOLEAN_FILTER_KEYS = ['parking', 'has_swimming_pool'] as const;
/** Every attribute param the tools accept (used to hide filter groups the tools cannot pass). */
export const ATTRIBUTE_FILTER_PARAMS: readonly string[] = [...MULTI_FILTER_KEYS, ...BOOLEAN_FILTER_KEYS];

export interface BeachSearchFilters extends GeoFilters, AttributeFilters {
  limit?: number;
}

export interface NearbyParams extends AttributeFilters {
  latitude: number;
  longitude: number;
  radius_km?: number;
  limit?: number;
}

export interface BeachSummary {
  id: number;
  name: string;
  slug: string;
  country: string;
  country_code: string;
  region: string;
  island: string;
  city: string;
  latitude: number;
  longitude: number;
  distance_km?: number;
  tags: string[];
  image_url: string;
  image_thumb: string;
  beachrex_url: string;
}

export interface NamedCode { code: string; name: string; }

export interface BeachDetail extends BeachSummary {
  description: string;
  continent: string;
  views: number | null;
  beach_type: string;
  waterbody: string;
  accessibility: string;
  parking: boolean | null;
  facilities: string[];
  culinary_offers: string[];
  accommodation_around: string[];
  sporting_activities: string[];
  user_types: string[];
  natural_conditions: string[];
  media: Array<{ url: string; is_primary: boolean }>;
}

export interface SearchResult {
  total: number;
  limit: number;
  beaches: BeachSummary[];
  meta?: any;
}

export interface NearbyResult extends SearchResult {
  radius_km: number;
  /** Radius actually sent to the API (see findBeachesNearby). */
  api_radius_km: number;
}

// ───────────────────── HTTP ─────────────────────

export class ApiError extends Error {
  constructor(public status: number, public body: any, url: string) {
    super(`Beachrex API error: ${status}${body?.message ? ` (${body.message})` : ''} for ${url}`);
    this.name = 'ApiError';
  }
}

type Params = Record<string, string | number | boolean | Array<string | number> | undefined | null>;

/** Build the full request URL (exported for tests). */
export function buildUrl(path: string, params?: Params): string {
  const url = new URL(`${cfg().apiUrl}/api/v1${path}`);
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value === undefined || value === null || value === '') continue;
      if (Array.isArray(value)) {
        if (value.length === 0) continue;
        url.searchParams.set(key, value.join(','));
      } else if (typeof value === 'boolean') {
        url.searchParams.set(key, value ? '1' : '0');
      } else {
        url.searchParams.set(key, String(value));
      }
    }
  }
  return url.toString();
}

async function apiCall<T = any>(path: string, params?: Params, locale?: string): Promise<T> {
  const c = cfg();
  const url = buildUrl(path, params);
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'Accept-Language': normalizeLocale(locale),
    // Identifies the MCP server, so AI lookups are not counted as beach page views.
    'X-Beachrex-Client': 'mcp',
  };
  if (c.apiKey) headers.Authorization = `Bearer ${c.apiKey}`;
  const response = await fetch(url, { headers, signal: AbortSignal.timeout(c.timeoutMs) });
  if (!response.ok) {
    let body: any = null;
    try { body = await response.json(); } catch { /* not JSON */ }
    throw new ApiError(response.status, body, url);
  }
  return response.json() as Promise<T>;
}

// ───────────────────── helpers ─────────────────────

function clamp(n: number, min: number, max: number): number {
  return Math.min(Math.max(n, min), max);
}

function clampLimit(limit: number | undefined, max: number, def = DEFAULT_LIMIT): number {
  const n = Math.floor(Number(limit));
  if (!Number.isFinite(n) || n < 1) return Math.min(def, max);
  return Math.min(n, max);
}

function str(v: any): string {
  return typeof v === 'string' ? v : (v === null || v === undefined ? '' : String(v));
}

/** Join relative media path onto a CDN base. API paths are relative ("img/beaches/..."). */
function cdn(base: string, relPath: string | null | undefined): string {
  if (!relPath) return '';
  if (/^https?:\/\//i.test(relPath)) return relPath;
  const path = relPath.replace(/^\/+/, '');
  // Same rule as the web-client (src/lib/utils/beach-images.ts buildCdnUrl): Imagor does not handle
  // UTF-8 paths (č, š, ž …) reliably, so bypass it and link the original on Spaces. Object keys were
  // uploaded in NFD form (c + combining caron), so each segment is NFD-normalised before encoding.
  if (!/^[\x00-\x7F]*$/.test(path)) {
    const assets = cfg().cdnAssets;
    const encoded = path.split('/').map((seg) => (/^[\x00-\x7F]*$/.test(seg) ? seg : encodeURIComponent(seg.normalize('NFD')))).join('/');
    return `${assets.endsWith('/') ? assets : `${assets}/`}${encoded}`;
  }
  return `${base.endsWith('/') ? base : `${base}/`}${path}`;
}
export function imageUrl(relPath: string | null | undefined): string { return cdn(cfg().cdnBase, relPath); }
export function imageThumbUrl(relPath: string | null | undefined): string { return cdn(cfg().cdnThumb, relPath); }

/** Is the value an ID (digits only)? */
export function isNumeric(val: string | number): boolean {
  return typeof val === 'number' || /^\d+$/.test(String(val).trim());
}

/** Lower-case, strip diacritics, collapse non-alphanumerics to "-". */
export function slugify(s: string): string {
  return s
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/đ/gi, 'd').replace(/ł/gi, 'l').replace(/ß/g, 'ss')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/**
 * Generic "beach" words (slugified) that legacy names carry as a prefix or suffix,
 * e.g. "Beach Zlatni Rat" in every locale, "Plaža Zlatni rat", "Spiaggia dei Conigli".
 */
const BEACH_WORDS = new Set(['beach', 'plaza', 'plaz', 'plazha', 'plage', 'playa', 'praia', 'spiaggia', 'strand', 'paralia']);

/**
 * Name comparison key: case- and diacritics-insensitive, ignoring a leading or trailing
 * generic beach word, so "Zlatni rat" matches "Beach Zlatni Rat" and "Plaža Zlatni rat".
 */
export function nameKey(s: string): string {
  const words = slugify(s).split('-').filter(Boolean);
  if (words.length > 1 && BEACH_WORDS.has(words[0])) words.shift();
  if (words.length > 1 && BEACH_WORDS.has(words[words.length - 1])) words.pop();
  return words.join(' ');
}

/**
 * Full hierarchical frontend URL, same rules as web-client
 * src/lib/utils/beach-urls.ts buildBeachUrlFromSchema():
 *   /{locale}/{beaches-word}/{continent}/{country}[/{region}][/{island}][/{city}]/{beach}
 * Continent, country and beach slug are required; returns '' if any is missing
 * (a broken link is worse than no link).
 */
export function beachUrl(b: {
  continent_slug?: string | null; country_slug?: string | null; region_slug?: string | null;
  island_slug?: string | null; city_slug?: string | null; slug?: string | null;
}, locale: string): string {
  if (!b.continent_slug || !b.country_slug || !b.slug) return '';
  const parts = [b.continent_slug, b.country_slug, b.region_slug, b.island_slug, b.city_slug, b.slug]
    .filter((p): p is string => typeof p === 'string' && p.length > 0);
  return `${cfg().frontendUrl}/${frontendLocale(locale)}/${getBeachesWord(locale)}/${parts.join('/')}`;
}

/** Great-circle distance in km. */
export function haversineKm(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const R = 6371.0088;
  const toRad = (d: number) => (d * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
}

/**
 * Map a beach row from /beaches (BeachResource), /search (beaches[]) or
 * /spatial/beaches/within-radius (PostGISService::formatBeachResults).
 * Field names per endpoint:
 *   image:  main_image (all three); main_image_path is the raw SQL column and is
 *           accepted as a fallback in case the spatial endpoint ever returns it unformatted.
 *   region: region_name only in /search; the other two only have region_slug,
 *           which is NOT shown (it is not a human-readable name).
 *   island: island_name only in /search.
 */
export function mapBeach(b: any, locale?: string): BeachSummary {
  const loc = normalizeLocale(locale);
  const img = b.main_image ?? b.main_image_path ?? null;
  const dist = b.distance_km !== undefined && b.distance_km !== null ? Number(b.distance_km) : undefined;
  return {
    id: Number(b.id),
    name: str(b.name) || `Beach #${b.id}`,
    slug: str(b.slug),
    country: str(b.country_name) || str(b.country_code),
    country_code: str(b.country_code),
    region: str(b.region_name),
    island: str(b.island_name),
    city: str(b.city_name),
    latitude: parseFloat(b.latitude) || 0,
    longitude: parseFloat(b.longitude) || 0,
    distance_km: dist !== undefined && Number.isFinite(dist) ? Math.round(dist * 100) / 100 : undefined,
    tags: Array.isArray(b.tags) ? b.tags.filter((t: any) => typeof t === 'string') : [],
    image_url: imageUrl(img),
    image_thumb: imageThumbUrl(img),
    beachrex_url: beachUrl(b, loc),
  };
}

function filterParams(f: AttributeFilters): Params {
  const out: Params = {};
  for (const key of MULTI_FILTER_KEYS) {
    const v = f[key];
    if (v === undefined || v === null) continue;
    const arr = (Array.isArray(v) ? v : String(v).split(','))
      .map((s) => String(s).trim())
      .filter(Boolean);
    if (arr.length) out[key] = arr;
  }
  for (const key of BOOLEAN_FILTER_KEYS) {
    if (typeof f[key] === 'boolean') out[key] = f[key];
  }
  return out;
}

// ───────────────────── /beaches ─────────────────────

export async function searchBeaches(filters: BeachSearchFilters, locale?: string): Promise<SearchResult> {
  const limit = clampLimit(filters.limit, MAX_LIMIT);
  const country = filters.country_code ? filters.country_code.trim().toUpperCase() : undefined;
  const params: Params = {
    limit,
    continent_code: filters.continent_code?.trim().toUpperCase(),
    // Old API versions read only `country`; newer ones read `country` OR `country_code`.
    // Send both so either version filters by country.
    country,
    country_code: country,
    region_code: filters.region_code?.trim(),
    island_code: filters.island_code?.trim(),
    city_code: filters.city_code?.trim(),
    ...filterParams(filters),
  };
  const data = await apiCall('/beaches', params, locale);
  const rows = Array.isArray(data?.data) ? data.data : [];
  const beaches = rows.map((b: any) => mapBeach(b, locale));
  return { total: beaches.length, limit, beaches, meta: data?.meta };
}

// ───────────────────── /spatial/beaches/within-radius ─────────────────────

/**
 * PostGISService::getBeachesWithinRadius measures distance in EPSG:3857 (Web Mercator)
 * metres, which overstates real distance by 1/cos(latitude) (~1.37x at 43°N).
 * To honour a TRUE radius, we send radius / cos(lat) to the API, recompute the real
 * (haversine) distance for every beach, and drop beaches outside the requested radius.
 * If the backend switches to geography distances, this still returns correct results
 * (the API just returns a few more rows that we trim).
 */
export function mercatorRadiusKm(radiusKm: number, latitude: number): number {
  const cos = Math.cos((clamp(latitude, -85, 85) * Math.PI) / 180);
  return clamp(radiusKm / cos, RADIUS_MIN_KM, RADIUS_MAX_KM);
}

export async function findBeachesNearby(p: NearbyParams, locale?: string): Promise<NearbyResult> {
  const limit = clampLimit(p.limit, MAX_LIMIT);
  const radius = clamp(Number(p.radius_km) || DEFAULT_RADIUS_KM, RADIUS_MIN_KM, RADIUS_MAX_KM);
  // round UP so rounding never shrinks the area
  const apiRadius = Math.min(Math.ceil(mercatorRadiusKm(radius, p.latitude) * 100) / 100, RADIUS_MAX_KM);
  const params: Params = {
    latitude: p.latitude,
    longitude: p.longitude,
    radius: apiRadius,
    limit,
    ...filterParams(p),
  };
  const data = await apiCall('/spatial/beaches/within-radius', params, locale);
  const rows = Array.isArray(data?.data) ? data.data : [];
  const beaches = rows
    .map((b: any) => {
      const m = mapBeach(b, locale);
      if (m.latitude || m.longitude) {
        m.distance_km = Math.round(haversineKm(p.latitude, p.longitude, m.latitude, m.longitude) * 100) / 100;
      }
      return m;
    })
    .filter((b: BeachSummary) => b.distance_km === undefined || b.distance_km <= radius)
    .sort((a: BeachSummary, b: BeachSummary) => (a.distance_km ?? 0) - (b.distance_km ?? 0));
  return { total: beaches.length, limit, beaches, meta: data?.meta, radius_km: radius, api_radius_km: apiRadius };
}

// ───────────────────── /search ─────────────────────

export interface PlaceHit {
  type: 'city' | 'island' | 'region' | 'country';
  id: number;
  code: string;
  name: string;
  country_code: string;
  country_name: string;
  region_name: string;
  island_name: string;
  /** The search_beaches param that takes `code`. */
  filter_param: 'city_code' | 'island_code' | 'region_code' | 'country_code';
}

export interface NameSearchResult {
  query: string;
  /** The query actually sent (differs from `query` when we retried a simplified one). */
  effective_query: string;
  beaches: BeachSummary[];
  places: PlaceHit[];
}

const FILLER_WORDS = new Set([
  'beach', 'beaches', 'in', 'at', 'near', 'on', 'the', 'of', 'around',
  'plaza', 'plaze', 'plaža', 'plaže', 'strand', 'strande', 'strände', 'spiaggia', 'spiagge',
  'playa', 'playas', 'plage', 'plages', 'v', 'na', 'u', 'pri',
]);

/** Drop generic words ("beaches in Makarska" -> "Makarska"). */
export function simplifyQuery(q: string): string {
  return q.split(/\s+/).filter((w) => w && !FILLER_WORDS.has(w.toLowerCase())).join(' ');
}

function mapPlace(p: any, type: PlaceHit['type']): PlaceHit {
  const param = ({ city: 'city_code', island: 'island_code', region: 'region_code', country: 'country_code' } as const)[type];
  return {
    type,
    id: Number(p.id),
    code: str(p.code),
    name: str(p.name),
    country_code: str(p.country_code),
    country_name: str(p.country_name),
    region_name: str(p.region_name),
    island_name: str(p.island_name),
    filter_param: param,
  };
}

async function rawSearch(q: string, opts: { limit: number; country_code?: string; types: string }, locale?: string) {
  const data = await apiCall('/search', {
    q,
    limit: opts.limit,
    types: opts.types,
    country_code: opts.country_code?.trim().toUpperCase(),
  }, locale);
  const d = data?.data ?? {};
  return {
    beaches: (Array.isArray(d.beaches) ? d.beaches : []).map((b: any) => mapBeach(b, locale)) as BeachSummary[],
    places: [
      ...(Array.isArray(d.cities) ? d.cities : []).map((x: any) => mapPlace(x, 'city')),
      ...(Array.isArray(d.islands) ? d.islands : []).map((x: any) => mapPlace(x, 'island')),
      ...(Array.isArray(d.regions) ? d.regions : []).map((x: any) => mapPlace(x, 'region')),
      ...(Array.isArray(d.countries) ? d.countries : []).map((x: any) => mapPlace(x, 'country')),
    ] as PlaceHit[],
  };
}

/**
 * Free-text search (Meilisearch via /api/v1/search). Matches beach names in all
 * languages, descriptions and the beach's city/region/island/country names.
 */
export async function findBeachByName(
  query: string,
  opts: { country_code?: string; limit?: number; include_places?: boolean } = {},
  locale?: string,
): Promise<NameSearchResult> {
  const q = query.trim();
  if (!q) throw new Error('query must not be empty');
  const limit = clampLimit(opts.limit, SEARCH_MAX_LIMIT, 10);
  const types = opts.include_places === false ? 'beach' : 'beach,city,island,region';
  let effective = q;
  let res = await rawSearch(q, { limit, country_code: opts.country_code, types }, locale);
  if (res.beaches.length === 0 && res.places.length === 0) {
    const simple = simplifyQuery(q);
    if (simple && simple !== q) {
      effective = simple;
      res = await rawSearch(simple, { limit, country_code: opts.country_code, types }, locale);
    }
  }
  return { query: q, effective_query: effective, ...res };
}

// ───────────────────── name / slug resolution ─────────────────────

export interface MatchInfo {
  /** direct: the identifier was a valid ID or slug. exact: search found the exact name. closest: best search hit, name differs. */
  kind: 'direct' | 'exact' | 'closest';
  query: string;
  /** Other candidates (same name elsewhere, or other close hits). */
  alternatives: BeachSummary[];
}

/** A slug-shaped string: lower-case ASCII letters, digits, hyphens. */
function looksLikeSlug(s: string): boolean {
  return /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(s);
}

/**
 * Resolve a beach name to a beach via /search.
 * Throws if nothing is found.
 */
export async function resolveBeachByName(name: string, locale?: string): Promise<{ beach: BeachSummary; match: MatchInfo }> {
  const res = await findBeachByName(name, { limit: 10, include_places: false }, locale);
  const hits = res.beaches.filter((b) => b.slug || b.id);
  if (hits.length === 0) {
    throw new Error(`Beach "${name}" was not found in the Beachrex database. Try find_beach_by_name with a shorter or differently spelled name, or search_beaches by location.`);
  }
  const key = nameKey(name);
  const exact = hits.filter((b) => nameKey(b.name) === key);
  if (exact.length > 0) {
    const [first, ...rest] = exact;
    return { beach: first, match: { kind: 'exact', query: name, alternatives: rest } };
  }
  const [first, ...rest] = hits;
  return { beach: first, match: { kind: 'closest', query: name, alternatives: rest.slice(0, 5) } };
}

/**
 * Call `fetcher` with the identifier; if it is not an ID/slug, or the API says 404,
 * resolve it by name through /search and call `fetcher` with the found beach ID.
 */
async function withBeachResolution<T>(
  identifier: string | number,
  locale: string | undefined,
  fetcher: (id: string | number) => Promise<T>,
): Promise<{ value: T; match: MatchInfo }> {
  const raw = String(identifier).trim();
  if (!raw) throw new Error('identifier must not be empty');
  const direct: MatchInfo = { kind: 'direct', query: raw, alternatives: [] };

  if (isNumeric(raw)) {
    return { value: await fetcher(raw), match: direct };
  }

  // Try as slug first (the API resolves slugs in any language).
  const candidate = looksLikeSlug(raw) ? raw : slugify(raw);
  if (candidate) {
    try {
      return { value: await fetcher(candidate), match: direct };
    } catch (e) {
      if (!(e instanceof ApiError && e.status === 404)) throw e;
    }
  }

  // Fall back to free-text search (handles "zlatni rat", "Bačvice", typos, other languages).
  const { beach, match } = await resolveBeachByName(raw.replace(/-/g, ' '), locale);
  return { value: await fetcher(beach.id || beach.slug), match };
}

// ───────────────────── /beach-details/{id} ─────────────────────

function named(x: any): string {
  return x && typeof x === 'object' && typeof x.name === 'string' ? x.name : '';
}
function namedList(x: any): string[] {
  return Array.isArray(x) ? x.map(named).filter(Boolean) : [];
}

/** Strip HTML tags/entities that may be stored in descriptions. */
export function plainText(s: string): string {
  return s
    .replace(/<\s*br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#39;|&apos;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/[ \t]+/g, ' ')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

/** Map BeachDetailsResource (the `data` object of /beach-details/{id}). */
export function mapBeachDetail(b: any, locale?: string): BeachDetail {
  const loc = normalizeLocale(locale);
  const info = b?.basic_info ?? {};
  const l = b?.location ?? {};
  const chars = b?.characteristics ?? {};
  const acc = b?.accessibility ?? {};
  const fac = b?.facilities ?? {};
  const act = b?.activities ?? {};
  const nat = b?.natural_conditions ?? {};
  const gallery: any[] = Array.isArray(b?.media?.gallery) ? b.media.gallery : [];
  const primary = gallery.find((m) => m?.is_primary)?.url ?? gallery[0]?.url ?? null;
  const viewsRaw = b?.meta?.views;
  const views = typeof viewsRaw === 'number' ? viewsRaw : (viewsRaw ? Number(viewsRaw) || null : null);
  return {
    id: Number(info.id),
    name: str(info.name) || `Beach #${info.id}`,
    slug: str(info.slug),
    description: plainText(str(info.description)),
    continent: named(l.continent),
    country: named(l.country) || str(l.country?.code),
    country_code: str(l.country?.code),
    region: named(l.region),
    island: named(l.island),
    city: named(l.city),
    latitude: parseFloat(info.latitude) || 0,
    longitude: parseFloat(info.longitude) || 0,
    tags: [],
    views,
    beach_type: named(chars.beach_type),
    waterbody: named(chars.waterbody),
    accessibility: named(acc.accessibility),
    parking: typeof acc.parking === 'boolean' ? acc.parking : (acc.parking === 1 ? true : acc.parking === 0 ? false : null),
    facilities: namedList(fac.location_infrastructure),
    culinary_offers: namedList(fac.culinary_offers),
    accommodation_around: namedList(fac.accommodation_around),
    sporting_activities: namedList(act.sporting_activities),
    user_types: namedList(act.user_types),
    natural_conditions: namedList(nat.natural_location_conditions),
    image_url: imageUrl(primary),
    image_thumb: imageThumbUrl(primary),
    beachrex_url: beachUrl({
      continent_slug: l.continent?.slug,
      country_slug: l.country?.slug,
      region_slug: l.region?.slug,
      island_slug: l.island?.slug,
      city_slug: l.city?.slug,
      slug: info.slug,
    }, loc),
    media: gallery
      .filter((m) => m?.url)
      .map((m) => ({ url: imageUrl(m.url), is_primary: Boolean(m.is_primary) })),
  };
}

export async function getBeachDetails(
  identifier: string | number,
  locale?: string,
): Promise<BeachDetail & { match: MatchInfo }> {
  const { value, match } = await withBeachResolution(identifier, locale, (id) =>
    apiCall(`/beach-details/${encodeURIComponent(String(id))}`, undefined, locale));
  return { ...mapBeachDetail(value?.data ?? {}, locale), match };
}

// ───────────────────── /beaches/filters ─────────────────────

export interface FilterGroup {
  /** Key in the /beaches/filters response. */
  key: string;
  /** Param name to use in search_beaches / find_beaches_nearby. */
  param: string;
  title: string;
  boolean: boolean;
  options: Array<{ code: string; name: string; id?: number }>;
}

/**
 * FilterController::index response keys -> request param names.
 * Two differ from the response key (see DEV_HANDOFF 2026-03-06):
 * sporting_activities -> sporting_activity, swimming_depth_of_one_m -> swimming_depth.
 * Geography maps to the MCP *_code params.
 */
export const FILTER_KEY_TO_PARAM: Record<string, string> = {
  country: 'country_code',
  region: 'region_code',
  island: 'island_code',
  city: 'city_code',
  sporting_activities: 'sporting_activity',
  swimming_depth_of_one_m: 'swimming_depth',
};

export const GEO_FILTER_KEYS = ['country', 'region', 'island', 'city'];

export function mapFilters(data: any): FilterGroup[] {
  if (!data || typeof data !== 'object' || Array.isArray(data)) return [];
  const groups: FilterGroup[] = [];
  for (const [key, g] of Object.entries<any>(data)) {
    if (!g || typeof g !== 'object') continue;
    const opts: any[] = Array.isArray(g.options) ? g.options : [];
    const isBool = opts.length > 0 && opts.every((o) => o && 'value' in o && !('code' in o));
    groups.push({
      key,
      param: FILTER_KEY_TO_PARAM[key] ?? key,
      title: str(g.title) || key,
      boolean: isBool,
      options: isBool
        ? [{ code: 'true', name: 'yes' }, { code: 'false', name: 'no' }]
        : opts
          .filter((o) => o && (o.code || o.id !== undefined))
          .map((o) => ({ code: str(o.code) || String(o.id), name: str(o.name) || str(o.code), id: typeof o.id === 'number' ? o.id : undefined })),
    });
  }
  return groups;
}

export async function getBeachFilters(
  opts: { country_code?: string; continent_code?: string } = {},
  locale?: string,
): Promise<FilterGroup[]> {
  // FilterController reads `country` (code or ID) and `continent_code` for cascading.
  const country = opts.country_code?.trim().toUpperCase();
  const data = await apiCall('/beaches/filters', {
    continent_code: opts.continent_code?.trim().toUpperCase(),
    country,
    country_code: country,
  }, locale);
  return mapFilters(data?.data);
}

// ───────────────────── /beach-details/{id}/weather ─────────────────────

export type WeatherResult =
  | {
    available: true;
    beach_id: number | null;
    beach_slug: string;
    temperature_c: number | null;
    feels_like_c: number | null;
    temp_min_c: number | null;
    temp_max_c: number | null;
    condition: string;
    description: string;
    humidity_pct: number | null;
    pressure_hpa: number | null;
    wind_speed_ms: number | null;
    wind_deg: number | null;
    clouds_pct: number | null;
    visibility_m: number | null;
    sunrise_utc: string;
    sunset_utc: string;
    location_name: string;
    /** Only set when the API value is a real sea temperature (see mapWeather). */
    water_temperature_c: number | null;
    fetched_at: string;
    match: MatchInfo;
  }
  | { available: false; reason: string; match?: MatchInfo };

function num(v: any): number | null {
  if (v === null || v === undefined || v === '') return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

/**
 * Map the success body of /beach-details/{id}/weather:
 * { success: true, data: { beach_id, beach_slug, coordinates, weather: {...WeatherService::formatWeatherData}, sea_temperature, cached_at } }
 * Note: ProvidesWeather sets sea_temperature = weather.temperature (the AIR temperature),
 * so we only report it as water temperature when it differs from the air temperature.
 */
export function mapWeather(body: any, match: MatchInfo): WeatherResult {
  const d = body?.data;
  const w = d?.weather;
  if (!body || body.success === false || !w || typeof w !== 'object') {
    return { available: false, reason: str(body?.message) || 'the weather service returned no data', match };
  }
  const temp = num(w.temperature);
  const sea = num(d.sea_temperature);
  return {
    available: true,
    beach_id: num(d.beach_id),
    beach_slug: str(d.beach_slug),
    temperature_c: temp,
    feels_like_c: num(w.feels_like),
    temp_min_c: num(w.temp_min),
    temp_max_c: num(w.temp_max),
    condition: str(w.condition),
    description: str(w.description),
    humidity_pct: num(w.humidity),
    pressure_hpa: num(w.pressure),
    wind_speed_ms: num(w.wind_speed),
    wind_deg: num(w.wind_deg),
    clouds_pct: num(w.clouds),
    visibility_m: num(w.visibility),
    sunrise_utc: str(w.sunrise),
    sunset_utc: str(w.sunset),
    location_name: str(w.location_name),
    water_temperature_c: sea !== null && sea !== temp ? sea : null,
    fetched_at: str(d.cached_at),
    match,
  };
}

export async function getBeachWeather(identifier: string | number, locale?: string): Promise<WeatherResult> {
  // Weather endpoint shares findBeachByIdentifier with /beach-details: 404 = unknown beach,
  // 400 = no coordinates, 503 = weather provider down / no API key.
  let resolved: { value: any; match: MatchInfo };
  try {
    resolved = await withBeachResolution(identifier, locale, (id) =>
      apiCall(`/beach-details/${encodeURIComponent(String(id))}/weather`, undefined, locale));
  } catch (e) {
    if (e instanceof ApiError && e.status !== 404) {
      return { available: false, reason: str(e.body?.message) || `API returned HTTP ${e.status}` };
    }
    throw e;
  }
  return mapWeather(resolved.value, resolved.match);
}

export { DEFAULT_LOCALE };
