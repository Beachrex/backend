/**
 * API response fixtures. Shapes are copied from the PHP code (not from live calls):
 *   beachesIndex       BeachController::index        -> { data: BeachResource[], meta }
 *   filters            FilterController::index       -> { data: { <key>: { title, icon, input_type, options } } }
 *   search             SearchController::search      -> { success, data: { beaches, cities, islands, regions, countries }, meta, message }
 *   withinRadius       PostGISController::beachesWithinRadius -> { data: formatBeachResults[], meta }
 *   beachDetails       BeachDetailsController::show  -> { data: BeachDetailsResource + navigation }
 *   weather*           BeachDetailsController::weather / ProvidesWeather::buildWeatherResponse
 * Codes come from database/seeders (BeachTypeSeeder, LocationInfrastructureSeeder, …).
 */

export const beachResource = (over: Record<string, unknown> = {}) => ({
  id: 125,
  name: 'Beach Bajlo',
  slug: 'beach-bajlo',
  latitude: '44.1197000',
  longitude: '15.2319000',
  country_code: 'HR',
  country_name: 'Croatia',
  country_slug: 'croatia',
  continent_code: 'EU',
  continent_slug: 'europe',
  region_slug: 'dalmatia-zadar',
  island_slug: null,
  city_name: 'Zadar',
  city_slug: 'zadar',
  main_image: 'img/beaches/beach-bajlo/sl8.jpg',
  media: { gallery: [{ url: 'img/beaches/beach-bajlo/sl8.jpg', is_primary: true, order: 1 }], embeds: { video_url: null, webcam: null } },
  tags: ['Swimming', 'Beach bar'],
  ...over,
});

export const beachesIndex = {
  data: [
    beachResource(),
    beachResource({
      id: 126, name: 'Zlatni rat', slug: 'zlatni-rat-beach', region_slug: 'dalmatia-split', island_slug: 'brac',
      city_name: 'Bol', city_slug: 'bol', main_image: null, media: { embeds: { video_url: null, webcam: null } }, tags: [],
    }),
  ],
  meta: {
    center: { latitude: 44.0, longitude: 15.5 },
    bounding_box: { min_latitude: 43.3, min_longitude: 15.2, max_latitude: 44.2, max_longitude: 16.7 },
    total_beaches: 2,
  },
};

const opt = (id: number, code: string, name: string) => ({ id, code, name, slug: code.replace(/_/g, '-') });

export const filters = {
  data: {
    country: { title: 'Country', icon: null, input_type: 'select', options: [opt(1, 'HR', 'Croatia'), opt(2, 'SI', 'Slovenia')] },
    region: { title: 'Region', icon: null, input_type: 'select', options: [opt(10, 'dalmatia-split', 'Split-Dalmatia')] },
    island: { title: 'Island', icon: null, input_type: 'select', options: [opt(20, 'brac', 'Brač')] },
    city: { title: 'City', icon: null, input_type: 'select', options: [opt(30, 'makarska', 'Makarska')] },
    waterbody: { title: 'Water Body', icon: 'icons/filters/waterbody.svg', input_type: 'checkbox', options: [opt(1, 'sea', 'Sea'), opt(2, 'lake', 'Lake'), opt(3, 'river', 'River'), opt(4, 'pool', 'Pool')] },
    user_type: { title: 'User Type', icon: null, input_type: 'checkbox', options: [opt(1, 'adventurer', 'Adventurer'), opt(2, 'family', 'Family'), opt(3, 'youth', 'Youth')] },
    beach_type: { title: 'Beach Type', icon: null, input_type: 'checkbox', options: [opt(1, 'sand', 'Sand'), opt(4, 'small_stones', 'Small stones'), opt(6, 'rock_beach', 'Rock beach')] },
    location_infrastructure: { title: 'Infrastructure', icon: null, input_type: 'checkbox', options: [opt(1, 'wc', 'WC'), opt(2, 'shower', 'Shower'), opt(10, 'children_playground', "Children's playground"), opt(15, 'lifeguard', 'Lifeguard'), opt(17, 'access_for_disabled', 'Access for disabled')] },
    sporting_activities: { title: 'Activities', icon: null, input_type: 'checkbox', options: [opt(2, 'snorkeling', 'Snorkeling'), opt(4, 'diving', 'Diving')] },
    swimming_depth_of_one_m: { title: 'Swimming Depth', icon: null, input_type: 'checkbox', options: [opt(1, 'close', 'Close')] },
    parking: { title: 'Parking', icon: null, input_type: 'checkbox', options: [{ value: '1', label: 'Yes', icon: null }] },
    has_swimming_pool: { title: 'Swimming Pool', icon: null, input_type: 'checkbox', options: [{ value: '1', label: 'Yes', icon: null }] },
  },
};

const searchBeach = (over: Record<string, unknown>) => ({
  id: 126,
  name: 'Zlatni rat',
  slug: 'zlatni-rat-beach',
  country_code: 'HR',
  country_name: 'Croatia',
  region_name: 'Split-Dalmatia',
  city_name: 'Bol',
  island_name: 'Brač',
  main_image: 'img/beaches/zlatni-rat/main.jpg',
  type: 'beach',
  continent_slug: 'europe',
  country_slug: 'croatia',
  region_slug: 'dalmatia-split',
  city_slug: 'bol',
  island_slug: 'brac',
  ...over,
});

export const searchResponse = (beaches: unknown[], places: Partial<Record<'cities' | 'islands' | 'regions' | 'countries', unknown[]>> = {}) => {
  const data = { beaches, cities: [], islands: [], regions: [], countries: [], ...places };
  const counts = Object.fromEntries(Object.entries(data).map(([k, v]) => [k, (v as unknown[]).length]));
  return {
    success: true,
    data,
    meta: { query: 'q', total_results: Object.values(counts).reduce((a, b) => a + b, 0), results_by_type: counts },
    message: 'Search completed successfully',
  };
};

export const searchZlatniRat = searchResponse([
  searchBeach({}),
  searchBeach({ id: 300, name: 'Zlatni rat Mini', slug: 'zlatni-rat-mini', city_name: 'Supetar', city_slug: 'supetar' }),
]);

export const searchClosest = searchResponse([
  searchBeach({ id: 126, name: 'Zlatni rat' }),
  searchBeach({ id: 401, name: 'Zlatna luka', slug: 'zlatna-luka', island_name: '', island_slug: null, city_name: 'Sukošan', city_slug: 'sukosan', region_name: 'Zadar', region_slug: 'dalmatia-zadar' }),
]);

export const searchDuplicates = searchResponse([
  searchBeach({ id: 501, name: 'Sunset Beach', slug: 'sunset-beach', city_name: 'Pula', city_slug: 'pula', island_name: '', island_slug: null }),
  searchBeach({ id: 502, name: 'Sunset beach', slug: 'sunset-beach-2', city_name: 'Rovinj', city_slug: 'rovinj', island_name: '', island_slug: null }),
]);

export const searchMakarska = searchResponse(
  [searchBeach({ id: 700, name: 'Makarska Beach', slug: 'makarska-beach', city_name: 'Makarska', city_slug: 'makarska', island_name: '', island_slug: null })],
  {
    cities: [{
      id: 30, code: 'makarska', name: 'Makarska', slug: 'makarska', country_code: 'HR', country_name: 'Croatia',
      region_name: 'Split-Dalmatia', island_name: '', main_image: null, type: 'city',
      continent_slug: 'europe', country_slug: 'croatia', region_slug: 'dalmatia-split', island_slug: null,
    }],
  },
);

export const searchEmpty = searchResponse([]);

const radiusRow = (over: Record<string, unknown>) => ({
  id: 900,
  name: 'Bačvice',
  slug: 'bacvice',
  description: 'City beach.',
  latitude: '43.5025',
  longitude: '16.4485',
  country_code: 'HR',
  country_name: 'Croatia',
  country_slug: 'croatia',
  continent_code: 'EU',
  continent_slug: 'europe',
  region_slug: 'dalmatia-split',
  island_slug: null,
  city_name: 'Split',
  city_slug: 'split',
  main_image: 'img/beaches/bacvice/1.jpg',
  media: { embeds: { video_url: null, webcam: null } },
  tags: ['Swimming'],
  distance_km: 1.3, // EPSG:3857 distance, overstated
  ...over,
});

export const withinRadius = {
  data: [
    radiusRow({}),
    // island AND city present: frontend puts both in the URL
    radiusRow({
      id: 901, name: 'Beach on Brač', slug: 'beach-on-brac', latitude: '43.3800', longitude: '16.5500',
      region_slug: 'dalmatia-split', island_slug: 'brac', city_name: 'Supetar', city_slug: 'supetar',
      main_image: null, main_image_path: 'img/beaches/brac/raw.jpg', distance_km: 22,
    }),
    // ~40 km real distance: outside a 25 km radius once measured correctly
    radiusRow({ id: 902, name: 'Far Beach', slug: 'far-beach', latitude: '43.1500', longitude: '16.4485', distance_km: 49 }),
  ],
  meta: {
    search_center: { latitude: 43.5081, longitude: 16.4402 },
    results_center: { latitude: 43.4, longitude: 16.5 },
    radius_km: 34.46,
    count: 3,
    location: { city_name: 'Split', city_slug: 'split', region_slug: 'dalmatia-split', country_name: 'Croatia', country_slug: 'croatia', country_code: 'HR', continent_slug: 'europe' },
  },
};

// `when()` without resolve(): a missing relation is serialized as {} (MissingValue), see BeachDetailsController::show
export const beachDetails = (over: { basic_info?: object; location?: object; meta?: object } = {}) => ({
  data: {
    basic_info: {
      id: 126, name: 'Zlatni rat', slug: 'zlatni-rat-beach',
      description: '<p>Famous beach on <strong>Brač</strong>.</p><p>Changes shape with the wind.</p>',
      latitude: '43.2560', longitude: '16.6340', folder_name: 'zlatni-rat', mobile_upload: false,
      ...(over.basic_info ?? {}),
    },
    location: {
      continent: { code: 'EU', name: 'Europe', slug: 'europe' },
      country: { code: 'HR', name: 'Croatia', full_name: 'Republic of Croatia', slug: 'croatia' },
      region: { id: 10, name: 'Split-Dalmatia', slug: 'dalmatia-split' },
      city: { id: 31, name: 'Bol', slug: 'bol' },
      island: { id: 20, name: 'Brač', slug: 'brac' },
      river: {},
      lake: {},
      ...(over.location ?? {}),
    },
    characteristics: {
      beach_type: { id: 4, code: 'small_stones', name: 'Small stones' },
      waterbody: { id: 1, code: 'sea', name: 'Sea' },
      water_body_type: {},
      area_description: {},
      location_types: [],
      boat_only: false,
    },
    accessibility: {
      accessibility: { id: 1, code: 'easy', name: 'Easy' },
      parking: true,
      road_to_parking_type: {}, parking_capacity: {}, parking_beach_distance: {}, parking_beach_height: {},
      recommended_transport_types: [],
    },
    dimensions: {},
    facilities: {
      accommodation_around: [{ id: 2, code: 'hotel', name: 'Hotel' }],
      culinary_offers: [{ id: 9, code: 'beach_bar', name: 'Beach bar' }],
      location_infrastructure: [{ id: 2, code: 'shower', name: 'Shower' }, { id: 15, code: 'lifeguard', name: 'Lifeguard' }],
    },
    activities: {
      sporting_activities: [{ id: 6, code: 'windsurfing', name: 'Windsurfing' }],
      user_types: [{ id: 2, code: 'family', name: 'Family' }],
    },
    natural_conditions: { natural_location_conditions: [{ id: 8, code: 'natural_shade', name: 'Natural shade' }], underwater_characteristics: [] },
    media: {
      gallery: [
        { id: 1, file_name: '2.jpg', url: 'img/beaches/zlatni-rat/2.jpg', thumbnail_url: 'img/beaches/zlatni-rat/2.jpg', tour_url: null, mime_type: 'image/jpeg', width: 800, height: 600, duration: null, is_primary: false, order: 2 },
        { id: 2, file_name: '1.jpg', url: 'img/beaches/zlatni-rat/1.jpg', thumbnail_url: 'img/beaches/zlatni-rat/1.jpg', tour_url: null, mime_type: 'image/jpeg', width: 800, height: 600, duration: null, is_primary: true, order: 1 },
      ],
      videos: [], spheres_360: [], parking_images: [], spot_images: [], infrastructure_images: [], info_images: [],
    },
    embeds: { video_url: null, webcam: null, map_url: null, weather: null, water_temperature: null },
    swimming_pool_features: { has_swimming_pool: false },
    meta: { status: 'Active', views: 0, created_by: null, created_at: null, updated_at: null, ...(over.meta ?? {}) },
    navigation: { previous: null, next: null },
  },
});

export const weatherOk = {
  success: true,
  data: {
    beach_id: 126,
    beach_slug: 'zlatni-rat-beach',
    coordinates: { latitude: '43.2560', longitude: '16.6340' },
    weather: {
      temperature: 24.3, feels_like: 24.8, temp_min: 23.1, temp_max: 25.0, pressure: 1015, humidity: 60,
      condition: 'Clear', description: 'clear sky', icon: '01d', icon_url: 'https://openweathermap.org/img/wn/01d@2x.png',
      wind_speed: 5.2, wind_deg: 315, clouds: 0, visibility: 10000, sunrise: '04:58', sunset: '17:12', location_name: 'Bol',
    },
    sea_temperature: 24.3, // ProvidesWeather copies the air temperature here
    cached_at: '2026-10-07T10:00:00+00:00',
  },
};

export const weatherUnavailable = {
  success: false,
  message: 'Weather data temporarily unavailable',
  data: { beach_id: 126, beach_slug: 'zlatni-rat-beach', coordinates: { latitude: '43.2560', longitude: '16.6340' }, weather: null, sea_temperature: null },
};

export const notFound = { message: 'Beach not found' };
