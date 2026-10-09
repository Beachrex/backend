import { test, beforeEach, afterEach, describe } from 'node:test';
import assert from 'node:assert/strict';
import { mockFetch } from './helpers.js';
import * as fx from './fixtures.js';
import {
  searchBeaches, findBeachesNearby, findBeachByName, getBeachDetails, getBeachFilters,
  getBeachWeather, mercatorRadiusKm, haversineKm, beachUrl, slugify, MAX_LIMIT, imageUrl, imageThumbUrl, nameKey,
} from '../src/api-client.js';
import {
  formatSearch, formatNearby, formatDetails, formatNameSearch, formatFilters, formatWeather,
} from '../src/format.js';

const API = 'https://api.test';
const WEB = 'https://web.test';
const CDN = 'https://cdn.test/fit-in/800x800/';

let m: ReturnType<typeof mockFetch>;
beforeEach(() => {
  process.env.BEACHREX_API_URL = API;
  process.env.BEACHREX_FRONTEND_URL = WEB;
  process.env.BEACHREX_CDN_URL = CDN;
  m = mockFetch();
});
afterEach(() => {
  m.restore();
});

describe('get_beach_filters', () => {
  test('maps the object-shaped /beaches/filters response (no more .map crash)', async () => {
    m.on('/beaches/filters', fx.filters);
    const groups = await getBeachFilters({}, 'en');
    const byKey = Object.fromEntries(groups.map((g) => [g.key, g]));

    assert.equal(byKey.waterbody.param, 'waterbody');
    assert.deepEqual(byKey.waterbody.options.map((o) => o.code), ['sea', 'lake', 'river', 'pool']);
    assert.equal(byKey.beach_type.options[1].code, 'small_stones');
    assert.equal(byKey.location_infrastructure.param, 'location_infrastructure');
    assert.ok(byKey.location_infrastructure.options.some((o) => o.code === 'lifeguard' && o.name === 'Lifeguard'));
    assert.equal(byKey.user_type.options[1].code, 'family');
    // response key differs from request param name
    assert.equal(byKey.sporting_activities.param, 'sporting_activity');
    assert.equal(byKey.swimming_depth_of_one_m.param, 'swimming_depth');
    assert.equal(byKey.country.param, 'country_code');
    assert.equal(byKey.parking.boolean, true);
    assert.equal(byKey.has_swimming_pool.boolean, true);

    const text = formatFilters(groups);
    assert.match(text, /parameter "sporting_activity"/);
    assert.match(text, /lifeguard: Lifeguard/);
    assert.match(text, /family: Family/);
    assert.match(text, /HR: Croatia/);
    assert.match(text, /parameter "parking" \(boolean\)/);
    // regions/cities are only summarised without a country
    assert.doesNotMatch(text, /makarska: Makarska/);
    assert.match(text, /Call get_beach_filters with country_code/);
  });

  test('passes country to the cascading filter endpoint and lists cities', async () => {
    m.on('/beaches/filters', fx.filters);
    const groups = await getBeachFilters({ country_code: 'hr' }, 'en');
    assert.equal(m.calls[0].url.searchParams.get('country'), 'HR');
    assert.match(formatFilters(groups, 'hr'), /makarska: Makarska/);
  });

  test('groups the tools cannot pass are hidden; swimming_depth is shown under its request name', async () => {
    m.on('/beaches/filters', { data: { ...fx.filters.data, future_filter: { title: 'Future', options: [{ id: 1, code: 'x', name: 'X' }] } } });
    const text = formatFilters(await getBeachFilters({}, 'en'));
    assert.doesNotMatch(text, /Future/);
    assert.match(text, /parameter "swimming_depth"/);
  });

  test('legacy array / empty data does not crash', async () => {
    m.on('/beaches/filters', { data: [] });
    assert.deepEqual(await getBeachFilters({}, 'en'), []);
  });
});

describe('search_beaches', () => {
  test('sends both country and country_code, clamps limit, joins filter codes', async () => {
    m.on('/beaches', fx.beachesIndex);
    await searchBeaches({
      country_code: 'hr', limit: 500, beach_type: ['sand', 'small_stones'], location_infrastructure: 'lifeguard',
      user_type: 'family', sporting_activity: ['snorkeling'], parking: true, island_code: 'brac',
      swimming_depth: 'close', continent_code: 'eu',
    }, 'en');
    const q = m.calls[0].url.searchParams;
    assert.equal(m.calls[0].path, '/beaches');
    assert.equal(q.get('country'), 'HR');
    assert.equal(q.get('country_code'), 'HR');
    assert.equal(q.get('limit'), String(MAX_LIMIT));
    assert.equal(q.get('beach_type'), 'sand,small_stones');
    assert.equal(q.get('location_infrastructure'), 'lifeguard');
    assert.equal(q.get('user_type'), 'family');
    assert.equal(q.get('sporting_activity'), 'snorkeling');
    assert.equal(q.get('parking'), '1');
    assert.equal(q.get('island_code'), 'brac');
    assert.equal(q.get('swimming_depth'), 'close');
    assert.equal(q.get('continent_code'), 'EU');
    assert.equal(q.has('region_code'), false);
  });

  test('maps BeachResource rows: image, hierarchical URL, no fake views', async () => {
    m.on('/beaches', fx.beachesIndex);
    const r = await searchBeaches({ country_code: 'HR' }, 'en');
    const [bajlo, rat] = r.beaches;
    assert.equal(bajlo.image_url, `${CDN}img/beaches/beach-bajlo/sl8.jpg`);
    assert.equal(bajlo.beachrex_url, `${WEB}/en/beaches/europe/croatia/dalmatia-zadar/zadar/beach-bajlo`);
    assert.equal(rat.beachrex_url, `${WEB}/en/beaches/europe/croatia/dalmatia-split/brac/bol/zlatni-rat-beach`);
    assert.equal(rat.image_url, '');
    const text = formatSearch(r);
    assert.doesNotMatch(text, /views/);
    assert.doesNotMatch(text, /dalmatia-zadar \)/); // region slug is not shown as a name
    assert.match(text, /\*\*Beach Bajlo\*\* \(Zadar, Croatia\)/);
  });

  test('every API call identifies the MCP server (not counted as a beach page view)', async () => {
    m.on('/beaches', { data: [] });
    await searchBeaches({}, 'en');
    assert.equal(m.calls[0].headers['X-Beachrex-Client'], 'mcp');
  });

  test('default limit is 20', async () => {
    m.on('/beaches', { data: [] });
    const r = await searchBeaches({}, 'en');
    assert.equal(m.calls[0].url.searchParams.get('limit'), '20');
    assert.match(formatSearch(r), /No beaches match/);
  });
});

describe('name lookup through /search', () => {
  test('get_beach_details("zlatni rat"): slug 404 -> /search -> details by id (exact match)', async () => {
    m.on('/beach-details/zlatni-rat', fx.notFound, 404)
      .on('/search', fx.searchZlatniRat)
      .on('/beach-details/126', fx.beachDetails());
    const d = await getBeachDetails('zlatni rat', 'en');
    assert.equal(m.calls.length, 3);
    const sq = m.calls[1].url.searchParams;
    assert.equal(sq.get('q'), 'zlatni rat');
    assert.equal(sq.get('types'), 'beach');
    assert.equal(d.id, 126);
    assert.equal(d.match.kind, 'exact');
    const text = formatDetails(d);
    assert.doesNotMatch(text, /closest match/);
  });

  test('not exact -> "closest match" with candidates', async () => {
    m.on('/beach-details/zlatni-ratt', fx.notFound, 404)
      .on('/search', fx.searchClosest)
      .on('/beach-details/126', fx.beachDetails());
    const d = await getBeachDetails('Zlatni Ratt', 'en');
    assert.equal(d.match.kind, 'closest');
    const text = formatDetails(d);
    assert.match(text, /closest match/);
    assert.match(text, /Zlatna luka/);
  });

  test('"Zlatni rat" matches "Beach Zlatni Rat" exactly (owner feedback 2026-10-07)', async () => {
    m.on('/beach-details/zlatni-rat', fx.notFound, 404)
      .on('/search', fx.searchResponse([fx.searchBeach({ id: 126, name: 'Beach Zlatni Rat' })]))
      .on('/beach-details/126', fx.beachDetails());
    const d = await getBeachDetails('Zlatni rat', 'en');
    assert.equal(d.match.kind, 'exact');
    assert.doesNotMatch(formatDetails(d), /closest match|No beach is named exactly/);
  });

  test('nameKey ignores case, diacritics and a leading or trailing beach word', () => {
    for (const name of ['Beach Zlatni Rat', 'Plaža Zlatni rat', 'zlatni rat', 'ZLATNI RAT beach', 'Spiaggia Zlatni Rat']) {
      assert.equal(nameKey(name), 'zlatni rat', name);
    }
    assert.equal(nameKey('Čiovo'), 'ciovo');
    // A name that is only the generic word keeps it; inner words are kept.
    assert.equal(nameKey('Beach'), 'beach');
    assert.equal(nameKey('Long Beach Bay'), 'long beach bay');
    assert.notEqual(nameKey('Zlatni rat Mini'), nameKey('Zlatni rat'));
  });

  test('several beaches with the same name are listed', async () => {
    m.on('/beach-details/sunset-beach', fx.notFound, 404)
      .on('/search', fx.searchDuplicates)
      .on('/beach-details/501', fx.beachDetails({ basic_info: { id: 501, name: 'Sunset Beach' } }));
    const d = await getBeachDetails('Sunset Beach', 'en');
    assert.equal(d.match.kind, 'exact');
    assert.equal(d.match.alternatives.length, 1);
    assert.match(formatDetails(d), /2 beaches are named "Sunset Beach"[\s\S]*Rovinj[\s\S]*id 502/);
  });

  test('diacritics: "Bačvice" is tried as slug "bacvice" first', async () => {
    m.on('/beach-details/bacvice', fx.beachDetails({ basic_info: { id: 900, name: 'Bačvice', slug: 'bacvice' } }));
    const d = await getBeachDetails('Bačvice', 'hr');
    assert.equal(m.calls.length, 1);
    assert.equal(d.match.kind, 'direct');
    assert.equal(m.calls[0].headers['Accept-Language'], 'hr');
  });

  test('nothing found -> clear error', async () => {
    m.on('/beach-details/nowhere-beach', fx.notFound, 404)
      .on((p, u) => p === '/search' && u.searchParams.get('q') === 'nowhere beach', fx.searchEmpty)
      .on((p, u) => p === '/search' && u.searchParams.get('q') === 'nowhere', fx.searchEmpty);
    await assert.rejects(getBeachDetails('nowhere beach', 'en'), /not found in the Beachrex database/);
  });

  test('find_beach_by_name returns beaches and place codes for search_beaches', async () => {
    m.on('/search', fx.searchMakarska);
    const r = await findBeachByName('Makarska', { country_code: 'hr', limit: 50 }, 'en');
    const q = m.calls[0].url.searchParams;
    assert.equal(q.get('country_code'), 'HR');
    assert.equal(q.get('limit'), '20'); // SearchController max
    assert.equal(q.get('types'), 'beach,city,island,region');
    const text = formatNameSearch(r);
    assert.match(text, /Makarska Beach/);
    assert.match(text, /city \*\*Makarska\*\*.*city_code="makarska", country_code="HR"/);
  });

  test('"beaches in Makarska" is retried as "Makarska" when nothing matches', async () => {
    m.on((p, u) => p === '/search' && u.searchParams.get('q') === 'beaches in Makarska', fx.searchEmpty)
      .on((p, u) => p === '/search' && u.searchParams.get('q') === 'Makarska', fx.searchMakarska);
    const r = await findBeachByName('beaches in Makarska', {}, 'en');
    assert.equal(r.effective_query, 'Makarska');
    assert.equal(r.beaches.length, 1);
    assert.match(formatNameSearch(r), /searched for "Makarska" instead/);
  });
});

describe('get_beach_details mapping', () => {
  test('localized region name, description, no "0 views", gallery', async () => {
    m.on('/beach-details/126', fx.beachDetails());
    const d = await getBeachDetails(126, 'en');
    assert.equal(d.region, 'Split-Dalmatia');
    assert.equal(d.image_url, `${CDN}img/beaches/zlatni-rat/1.jpg`);
    assert.equal(d.beachrex_url, `${WEB}/en/beaches/europe/croatia/dalmatia-split/brac/bol/zlatni-rat-beach`);
    const text = formatDetails(d);
    assert.match(text, /Location: Europe > Croatia > Split-Dalmatia > Brač > Bol/);
    assert.doesNotMatch(text, /dalmatia-split >/);
    assert.match(text, /Famous beach on Brač\.\n\nChanges shape with the wind\./);
    assert.doesNotMatch(text, /<p>/);
    assert.doesNotMatch(text, /views/i);
    assert.match(text, /Facilities: Shower, Lifeguard/);
    assert.match(text, /Suitable for: Family/);
    assert.match(text, /Gallery:\n {2}- \[Photo\]\(https:\/\/cdn\.test\/fit-in\/800x800\/img\/beaches\/zlatni-rat\/2\.jpg\)/);
  });

  test('views shown when > 0; missing relations serialized as {} are skipped', async () => {
    m.on('/beach-details/126', fx.beachDetails({ meta: { views: 42 }, location: { region: {}, island: {} } }));
    const text = formatDetails(await getBeachDetails('126', 'en'));
    assert.match(text, /Views on Beachrex: 42/);
    assert.match(text, /Location: Europe > Croatia > Bol/);
  });
});

describe('find_beaches_nearby', () => {
  test('calls within-radius with radius + clamped limit + filters', async () => {
    m.on('/spatial/beaches/within-radius', fx.withinRadius);
    await findBeachesNearby({ latitude: 43.5081, longitude: 16.4402, radius_km: 25, limit: 500, beach_type: 'sand', parking: false }, 'en');
    const c = m.calls[0];
    assert.equal(c.path, '/spatial/beaches/within-radius');
    const q = c.url.searchParams;
    assert.equal(q.get('latitude'), '43.5081');
    assert.equal(q.get('longitude'), '16.4402');
    assert.equal(q.get('limit'), '100');
    assert.equal(q.get('beach_type'), 'sand');
    assert.equal(q.get('parking'), '0');
    // EPSG:3857 compensation: 25 / cos(43.5081°) ≈ 34.46
    assert.equal(Number(q.get('radius')), Math.ceil(mercatorRadiusKm(25, 43.5081) * 100) / 100);
    assert.equal(q.get('radius'), '34.47');
  });

  test('hierarchical URLs, real distances, trims beyond radius, image fallback', async () => {
    m.on('/spatial/beaches/within-radius', fx.withinRadius);
    const r = await findBeachesNearby({ latitude: 43.5081, longitude: 16.4402, radius_km: 25 }, 'en');
    assert.deepEqual(r.beaches.map((b) => b.id), [900, 901]); // 902 is ~40 km away
    const [bacvice, brac] = r.beaches;
    assert.equal(bacvice.beachrex_url, `${WEB}/en/beaches/europe/croatia/dalmatia-split/split/bacvice`);
    assert.notEqual(bacvice.beachrex_url, `${WEB}/en/beaches/bacvice`);
    assert.equal(brac.beachrex_url, `${WEB}/en/beaches/europe/croatia/dalmatia-split/brac/supetar/beach-on-brac`);
    assert.equal(brac.image_url, `${CDN}img/beaches/brac/raw.jpg`);
    assert.ok(bacvice.distance_km! < 1.3 && bacvice.distance_km! > 0.8, `got ${bacvice.distance_km}`);
    const text = formatNearby(r, 43.5081, 16.4402);
    assert.match(text, /closest first/);
    assert.match(text, /km away/);
    assert.doesNotMatch(text, /views/);
  });

  test('radius is clamped to the API range', () => {
    assert.equal(mercatorRadiusKm(50000, 10), 20000);
    assert.equal(mercatorRadiusKm(0.01, 0), 0.1);
  });
});

describe('get_beach_weather', () => {
  test('maps the real weather payload', async () => {
    m.on('/beach-details/126/weather', fx.weatherOk);
    const w = await getBeachWeather(126, 'en');
    assert.equal(w.available, true);
    if (!w.available) return;
    assert.equal(w.temperature_c, 24.3);
    assert.equal(w.humidity_pct, 60);
    assert.equal(w.wind_speed_ms, 5.2);
    assert.equal(w.water_temperature_c, null); // API copies air temp into sea_temperature
    const text = formatWeather(w, 126);
    assert.match(text, /Condition: Clear — clear sky/);
    assert.match(text, /Air temperature: 24\.3 °C/);
    assert.match(text, /Humidity: 60 %/);
    assert.match(text, /Wind: 5\.2 m\/s \(19 km\/h\) from NW/);
    assert.match(text, /Water temperature: not available/);
    assert.doesNotMatch(text, /: 0(C| km\/h|%)/);
  });

  test('503 -> "weather unavailable", no zeros', async () => {
    m.on('/beach-details/126/weather', fx.weatherUnavailable, 503);
    const w = await getBeachWeather('126', 'en');
    assert.equal(w.available, false);
    const text = formatWeather(w, '126');
    assert.match(text, /Weather unavailable/);
    assert.match(text, /temporarily unavailable/);
    assert.doesNotMatch(text, /0 ?°?C/);
  });

  test('200 with weather: null is also unavailable', async () => {
    m.on('/beach-details/126/weather', { success: true, data: { weather: null } });
    const w = await getBeachWeather(126, 'en');
    assert.equal(w.available, false);
  });

  test('weather by name resolves through /search', async () => {
    m.on('/beach-details/zlatni-rat/weather', fx.notFound, 404)
      .on('/search', fx.searchZlatniRat)
      .on('/beach-details/126/weather', fx.weatherOk);
    const w = await getBeachWeather('Zlatni rat', 'en');
    assert.equal(w.available, true);
    assert.equal(m.pending(), 0);
  });
});

describe('helpers', () => {
  test('beachUrl needs continent, country and slug', () => {
    assert.equal(beachUrl({ slug: 'beach-bacvice' }, 'en'), '');
  });
  test('slugify strips diacritics', () => {
    assert.equal(slugify('Bačvice'), 'bacvice');
    assert.equal(slugify('  Zlatni   Rat '), 'zlatni-rat');
    assert.equal(slugify('Plaža Đurđevac'), 'plaza-durdevac');
  });
  test('haversine sanity: Split -> Zadar ≈ 118 km', () => {
    const d = haversineKm(43.5081, 16.4402, 44.1197, 15.2319);
    assert.ok(d > 115 && d < 122, String(d));
  });
});

describe('image URLs (Imagor only, TASK-002)', () => {
  test('ASCII paths go through Imagor', () => {
    assert.equal(
      imageUrl('img/beaches/hr-540-k-odalmacija/hr-540-k-odalmacija-sl9.jpg'),
      `${CDN}img/beaches/hr-540-k-odalmacija/hr-540-k-odalmacija-sl9.jpg`,
    );
  });

  test('non-ASCII paths get no URL and never a Spaces link', () => {
    // The old non-ASCII keys were deleted (TASK-002 step 6); such a path cannot resolve.
    assert.equal(imageUrl('img/beaches/hr-114-o-brač/hr-114-o-brač-sl17.jpg'), '');
    assert.equal(imageThumbUrl('img/beaches/hr-114-o-brač/x.jpg'), '');
    assert.equal(imageUrl('img/beaches/hr-114-o-brac\u030C/x.jpg'), '', 'NFD form too');
  });
});
