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

/** Build full beachrex.com URL from slugs */
function beachUrl(b: any, locale: string): string {
  const localePath = locale && locale !== 'en' ? `/${locale}` : '';
  const parts = [
    b.continent_slug, b.country_slug, b.region_slug,
    b.island_slug || b.city_slug, b.slug,
  ].filter(Boolean);
  return `${FRONTEND_URL}${localePath}/beaches/${parts.join('/')}`;
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
  const data = await apiCall<any>(`/beach-details/${identifier}`, undefined, locale);
  const b = data.data;
  return {
    ...mapBeach(b, locale),
    description: b.description || '',
    continent: b.continent_name || b.continent_code || '',
    island: b.island_name || null,
    media: (b.media?.gallery || []).map((m: any) => ({
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
