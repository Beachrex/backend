/**
 * Beachrex API Client — wraps the Beachrex REST API.
 * API key is stored server-side, never exposed to AI.
 */
/* eslint-disable @typescript-eslint/no-explicit-any */

const BEACHREX_API_URL = process.env.BEACHREX_API_URL || 'https://dev-api.beachrex.com';
const BEACHREX_API_KEY = process.env.BEACHREX_API_KEY || '';
const CDN_BASE = process.env.BEACHREX_CDN_URL || 'https://s3.beachrex.com/unsafe/fit-in/800x800/';
const FRONTEND_URL = process.env.BEACHREX_FRONTEND_URL || 'https://dev.beachrex.com';
const DEFAULT_LOCALE = 'en';
const DEFAULT_LIMIT = 20;
const MAX_LIMIT = 100;

export interface BeachSearchFilters {
  continent_code?: string;
  country_code?: string;
  region_code?: string;
  city_code?: string;
  island_code?: string;
  waterbody?: string;
  beach_type?: string;
  limit?: number;
}

export interface BeachSummary {
  id: number;
  name: string;
  slug: string;
  country: string;
  region: string;
  city: string;
  waterbody: string;
  latitude: number;
  longitude: number;
  beach_views: number;
  distance_km?: number;
  image_url: string;
  beachrex_url: string;
}

export interface BeachDetail extends BeachSummary {
  description: string;
  continent: string;
  island: string | null;
  media: Array<{ url: string; is_primary: boolean }>;
}

export interface FilterOption { code: string; name: string; beach_count: number; }
export interface SearchResult { total: number; beaches: BeachSummary[]; meta?: any; }

async function apiCall<T>(path: string, params?: Record<string, string>, locale?: string): Promise<T> {
  const url = new URL(`${BEACHREX_API_URL}/api/v1${path}`);
  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value) url.searchParams.set(key, value);
    }
  }
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'Accept-Language': locale || DEFAULT_LOCALE,
  };
  if (BEACHREX_API_KEY) headers['Authorization'] = `Bearer ${BEACHREX_API_KEY}`;
  const response = await fetch(url.toString(), { headers });
  if (!response.ok) throw new Error(`Beachrex API error: ${response.status} ${response.statusText} for ${url}`);
  return response.json() as Promise<T>;
}

/** Build full CDN image URL from relative path */
function imageUrl(relPath: string | undefined): string {
  if (!relPath) return '';
  return `${CDN_BASE}${relPath}`;
}

/** Localized "beaches" word for URL (matches frontend routing) */
const BEACHES_WORD: Record<string, string> = {
  en: 'beaches', si: 'plaze', sl: 'plaze', de: 'strande', hr: 'plaze',
  fr: 'plages', es: 'playas', it: 'spiagge', ua: 'plyazhi',
  ru: 'plyazhi', pl: 'plaze', cs: 'plaze', hu: 'strandok',
  bg: 'plyazhove', sk: 'plaze',
};
function getBeachesWord(locale: string): string {
  // Normalize: API uses 'sl' for Slovenian, frontend uses 'si'
  const normalized = locale === 'sl' ? 'si' : locale;
  return BEACHES_WORD[normalized] ?? 'beaches';
}

/** Check if value is numeric */
function isNumeric(val: string | number): boolean {
  return typeof val === 'number' || /^\d+$/.test(String(val));
}

/** Build full beachrex.com URL from slugs */function beachUrl(b: any, locale: string): string {
  // Normalize: API uses 'sl', frontend URL uses 'si'
  const urlLocale = locale === 'sl' ? 'si' : locale;
  const beachesWord = getBeachesWord(locale);
  const parts = [
    b.continent_slug, b.country_slug, b.region_slug,
    b.island_slug || b.city_slug, b.slug,
  ].filter(Boolean);
  return `${FRONTEND_URL}/${urlLocale}/${beachesWord}/${parts.join('/')}`;
}

function mapBeach(b: any, locale?: string): BeachSummary {
  return {
    id: b.id,
    name: b.name || `Beach #${b.id}`,
    slug: b.slug || '',
    country: b.country_name || b.country_code || '',
    region: b.region_slug || '',
    city: b.city_name || '',
    waterbody: b.waterbody_name || 'sea',
    latitude: parseFloat(b.latitude) || 0,
    longitude: parseFloat(b.longitude) || 0,
    beach_views: b.beach_views || 0,
    distance_km: b.distance_km ? parseFloat(Number(b.distance_km).toFixed(2)) : undefined,
    image_url: imageUrl(b.main_image),
    beachrex_url: beachUrl(b, locale || DEFAULT_LOCALE),
  };
}

export async function searchBeaches(filters: BeachSearchFilters, locale?: string): Promise<SearchResult> {
  const limit = Math.min(filters.limit || DEFAULT_LIMIT, MAX_LIMIT);
  const params: Record<string, string> = { limit: String(limit) };
  if (filters.continent_code) params.continent_code = filters.continent_code;
  if (filters.country_code) params.country_code = filters.country_code;
  if (filters.region_code) params.region_code = filters.region_code;
  if (filters.city_code) params.city_code = filters.city_code;
  if (filters.island_code) params.island_code = filters.island_code;
  if (filters.waterbody) params.waterbody = filters.waterbody;
  if (filters.beach_type) params.beach_type = filters.beach_type;
  const data = await apiCall<any>('/beaches', params, locale);
  const beaches: BeachSummary[] = (data.data || []).map((b: any) => mapBeach(b, locale));
  return { total: beaches.length, beaches, meta: data.meta };
}

export async function findBeachesNearby(
  latitude: number, longitude: number, radiusKm?: number, limit?: number, locale?: string,
): Promise<SearchResult> {
  const params: Record<string, string> = {
    latitude: String(latitude), longitude: String(longitude),
    limit: String(Math.min(limit || DEFAULT_LIMIT, MAX_LIMIT)),
  };
  if (radiusKm) params.radius = String(radiusKm);
  const data = await apiCall<any>('/spatial/beaches/nearest', params, locale);
  const beaches: BeachSummary[] = (data.data || []).map((b: any) => mapBeach(b, locale));
  return { total: beaches.length, beaches };
}

export async function getBeachDetails(identifier: string | number, locale?: string): Promise<BeachDetail> {
  let data: any;
  let b: any;

  // If identifier is a string (beach name like "Beach Bajlo"), try slug first, then search
  if (typeof identifier === 'string' && !isNumeric(identifier)) {
    // Replace spaces with hyphens for slug matching
    const possibleSlug = identifier.toLowerCase().replace(/\s+/g, '-');
    try {
      data = await apiCall<any>(`/beach-details/${possibleSlug}`, undefined, locale);
      b = data.data;
    } catch {
      // Slug didn't work — search by name and get the real slug
      const searchResult = await searchBeaches({ limit: 1 }, locale);
      const match = searchResult.beaches.find((beach) =>
        beach.name.toLowerCase() === identifier.toLowerCase() ||
        beach.slug === possibleSlug,
      );
      if (match) {
        data = await apiCall<any>(`/beach-details/${match.slug}`, undefined, locale);
        b = data.data;
      } else {
        throw new Error(`Beach "${identifier}" not found in Beachrex database. Use search_beaches to find beaches.`);
      }
    }
  } else {
    // Numeric ID or exact slug — direct call
    data = await apiCall<any>(`/beach-details/${identifier}`, undefined, locale);
    b = data.data;
  }

  // beach-details API nests data under basic_info, location, characteristics
  const info = b.basic_info || {};
  const loc = b.location || {};
  const chars = b.characteristics || {};
  const gallery = b.media?.gallery || [];
  return {
    id: info.id,
    name: info.name || `Beach #${info.id}`,
    slug: info.slug || '',
    country: loc.country?.name || loc.country?.code || '',
    region: loc.region?.slug || '',
    continent: loc.continent?.name || loc.continent?.code || '',
    city: loc.city?.name || '',
    island: loc.island?.slug || null,
    waterbody: chars.waterbody?.name || 'sea',
    description: info.description || '',
    latitude: parseFloat(info.latitude) || 0,
    longitude: parseFloat(info.longitude) || 0,
    beach_views: 0,
    image_url: imageUrl(gallery.find((m: any) => m.is_primary)?.url || gallery[0]?.url),
    beachrex_url: beachUrl({
      continent_slug: loc.continent?.slug,
      country_slug: loc.country?.slug,
      region_slug: loc.region?.slug,
      island_slug: loc.island?.slug,
      city_slug: loc.city?.slug,
      slug: info.slug,
    }, locale || DEFAULT_LOCALE),
    media: gallery.map((m: any) => ({
      url: imageUrl(m.url), is_primary: m.is_primary || false,
    })),
  };
}

export async function getBeachFilters(locale?: string): Promise<{
  waterbodies: FilterOption[]; beach_types: FilterOption[]; continents: FilterOption[];
}> {
  const data = await apiCall<any>('/beaches/filters', undefined, locale);
  const options: FilterOption[] = (data.data || []).map((f: any) => ({
    code: f.code || '', name: f.name || f.code, beach_count: f.beaches_count || 0,
  }));
  return { waterbodies: options, beach_types: [], continents: [] };
}

export async function getBeachWeather(identifier: string | number): Promise<{
  temperature: number; condition: string; humidity: number; wind_speed: number;
}> {
  const data = await apiCall<any>(`/beach-details/${identifier}/weather`);
  const w = data.data;
  return {
    temperature: w.temperature || 0, condition: w.condition || 'unknown',
    humidity: w.humidity || 0, wind_speed: w.wind_speed || 0,
  };
}
