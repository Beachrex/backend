/**
 * Beachrex API Client
 *
 * Wraps the Beachrex REST API. The API key is stored server-side
 * and never exposed to the AI agent.
 */

/* eslint-disable @typescript-eslint/no-explicit-any */

const BEACHREX_API_URL = process.env.BEACHREX_API_URL || 'https://dev-api.beachrex.com';
const BEACHREX_API_KEY = process.env.BEACHREX_API_KEY || '';
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
  waterbody: string;
  latitude: number;
  longitude: number;
  beach_views: number;
}

export interface BeachDetail extends BeachSummary {
  description: string;
  continent: string;
  city: string;
  island: string | null;
  media: Array<{
    id: number;
    url: string;
    type: string;
    is_primary: boolean;
  }>;
  characteristics: Record<string, string>;
  activities: string[];
  weather?: {
    temperature: number;
    condition: string;
  };
}

export interface FilterOption {
  code: string;
  name: string;
  beach_count: number;
}

export interface SearchResult {
  total: number;
  beaches: BeachSummary[];
  meta?: {
    center?: { latitude: number; longitude: number };
    bounding_box?: {
      min_latitude: number;
      min_longitude: number;
      max_latitude: number;
      max_longitude: number;
    };
  };
}

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

  if (BEACHREX_API_KEY) {
    headers['Authorization'] = `Bearer ${BEACHREX_API_KEY}`;
  }

  const response = await fetch(url.toString(), { headers });

  if (!response.ok) {
    throw new Error(`Beachrex API error: ${response.status} ${response.statusText} for ${url}`);
  }

  return response.json() as Promise<T>;
}

/**
 * Search beaches with optional filters.
 * Returns a compact summary suitable for AI consumption.
 */
export async function searchBeaches(filters: BeachSearchFilters, locale?: string): Promise<SearchResult> {
  const limit = Math.min(filters.limit || DEFAULT_LIMIT, MAX_LIMIT);

  const params: Record<string, string> = {
    limit: String(limit),
  };

  if (filters.continent_code) params.continent_code = filters.continent_code;
  if (filters.country_code) params.country_code = filters.country_code;
  if (filters.region_code) params.region_code = filters.region_code;
  if (filters.city_code) params.city_code = filters.city_code;
  if (filters.island_code) params.island_code = filters.island_code;
  if (filters.waterbody) params.waterbody = filters.waterbody;
  if (filters.beach_type) params.beach_type = filters.beach_type;

  const data = await apiCall<any>('/beaches', params, locale);

  const beaches: BeachSummary[] = (data.data || []).map((b: any) => ({
    id: b.id as number,
    name: b.translations?.[0]?.name || b.name || `Beach #${b.id}`,
    slug: b.translations?.[0]?.slug || '',
    country: b.country?.translations?.[0]?.name || b.country?.code || '',
    region: b.region?.translations?.[0]?.name || '',
    waterbody: b.waterbody?.translations?.[0]?.name || 'sea',
    latitude: b.latitude as number,
    longitude: b.longitude as number,
    beach_views: b.beach_views || 0,
  }));

  return {
    total: beaches.length,
    beaches,
    meta: data.meta,
  };
}

/**
 * Get detailed information about a specific beach by ID or slug.
 */
export async function getBeachDetails(identifier: string | number, locale?: string): Promise<BeachDetail> {
  const data = await apiCall<any>(`/beach-details/${identifier}`, undefined, locale);

  const b = data.data;
  return {
    id: b.id as number,
    name: b.translations?.[0]?.name || b.name || `Beach #${b.id}`,
    slug: b.translations?.[0]?.slug || '',
    country: b.country?.translations?.[0]?.name || '',
    region: b.region?.translations?.[0]?.name || '',
    continent: b.continent?.translations?.[0]?.name || '',
    city: b.city?.translations?.[0]?.name || '',
    island: b.island?.translations?.[0]?.name || null,
    waterbody: b.waterbody?.translations?.[0]?.name || 'sea',
    description: b.translations?.[0]?.description || '',
    latitude: b.latitude as number,
    longitude: b.longitude as number,
    beach_views: b.beach_views || 0,
    media: (b.media || []).map((m: any) => ({
      id: m.id as number,
      url: m.url as string,
      type: m.type as string,
      is_primary: m.is_primary as boolean,
    })),
    characteristics: {},
    activities: [],
  };
}

/**
 * Get available beach filters (waterbody types, beach types, etc.)
 */
export async function getBeachFilters(locale?: string): Promise<{
  waterbodies: FilterOption[];
  beach_types: FilterOption[];
  continents: FilterOption[];
}> {
  const data = await apiCall<any>('/beaches/filters', undefined, locale);

  const options: FilterOption[] = (data.data || []).map((f: any) => ({
    code: f.code as string,
    name: f.translations?.[0]?.name || f.name || f.code,
    beach_count: f.beaches_count || 0,
  }));

  return {
    waterbodies: options,
    beach_types: [],
    continents: [],
  };
}

/**
 * Get weather for a beach by identifier.
 */
export async function getBeachWeather(identifier: string | number): Promise<{
  temperature: number;
  condition: string;
  humidity: number;
  wind_speed: number;
}> {
  const data = await apiCall<any>(`/beach-details/${identifier}/weather`);

  const w = data.data;
  return {
    temperature: w.temperature || 0,
    condition: w.condition || 'unknown',
    humidity: w.humidity || 0,
    wind_speed: w.wind_speed || 0,
  };
}
