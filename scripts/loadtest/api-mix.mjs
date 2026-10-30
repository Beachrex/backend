#!/usr/bin/env node
/**
 * API load test: replays the endpoint mix of the prod incident of 2026-10-30 (web_prod access log, 5 min,
 * 10 400 requests) against a Beachrex API and prints p50/p95/p99 per endpoint.
 * Runbook: docs/runbooks/performance-and-bots.md ("Load test").
 *
 *   node scripts/loadtest/api-mix.mjs --base http://127.0.0.1:8090 --fixture fixture.json \
 *        [--duration 60] [--concurrency 32] [--rate 0] [--health-every 0.5] [--mix incident|reference]
 *
 * --fixture: JSON {beaches: [[id, lat, lng, country], ...], countries: [...], regions: [...], cities: [...]}
 *            (export it from the database you test against; see the runbook).
 * --rate:    0 = closed loop (each of --concurrency workers sends the next request when the last one answered);
 *            N = open loop, N requests per second whatever the latency (queues build up like in production).
 * A separate probe asks GET /api/v1/health every --health-every seconds and is reported as its own row.
 * Never point this at production.
 */
import { readFileSync } from 'node:fs';
import http from 'node:http';
import https from 'node:https';

const args = Object.fromEntries(
  process.argv.slice(2).reduce((acc, cur, i, all) => {
    if (cur.startsWith('--')) acc.push([cur.slice(2), all[i + 1] && !all[i + 1].startsWith('--') ? all[i + 1] : 'true']);
    return acc;
  }, []),
);
const BASE = args.base ?? 'http://127.0.0.1:8090';
const DURATION_S = Number(args.duration ?? 60);
const CONCURRENCY = Number(args.concurrency ?? 32);
const RATE = Number(args.rate ?? 0);
const HEALTH_EVERY_S = Number(args['health-every'] ?? 0.5);
const MIX = args.mix ?? 'incident';
const fixture = JSON.parse(readFileSync(args.fixture, 'utf8'));
if (/beachrex\.com/.test(BASE) && !args['i-know']) {
  console.error('Refusing to load-test a beachrex.com host.');
  process.exit(2);
}

const lib = BASE.startsWith('https') ? https : http;
const agent = new lib.Agent({ keepAlive: true, maxSockets: CONCURRENCY + 4 });
function get(url, headers) {
  return new Promise((resolve, reject) => {
    const req = lib.get(url, { agent, headers, timeout: 120_000 }, (res) => {
      res.on('data', () => {});
      res.on('end', () => resolve(res));
      res.on('error', reject);
    });
    req.on('timeout', () => req.destroy(new Error('timeout')));
    req.on('error', reject);
  });
}

const LOCALES = ['en', 'si', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq'];
const pick = (a) => a[Math.floor(Math.random() * a.length)];
const beach = () => pick(fixture.beaches);

// Weights = requests per endpoint in the incident's 5-minute access log.
const INCIDENT = [
  ['filters', 1852, () => '/api/v1/beaches/filters'],
  ['countries', 1071, () => '/api/v1/countries'],
  ['continents', 1026, () => '/api/v1/continents'],
  ['static-pages', 556, () => '/api/v1/static-pages'],
  ['footer', 555, () => '/api/v1/beaches/footer'],
  ['map-bounds', 501, () => '/api/v1/beaches/map-bounds'],
  ['within-radius', 413, () => {
    const [, lat, lng] = beach();
    return `/api/v1/spatial/beaches/within-radius?latitude=${lat}&longitude=${lng}&radius=10`;
  }],
  ['most-visited', 383, () => (Math.random() < 0.5 ? '/api/v1/beaches/most-visited?limit=8' : `/api/v1/beaches/most-visited?country_code=${beach()[3]}&limit=8`)],
  ['beaches', 378, () => {
    const r = Math.random();
    if (r < 0.4) return `/api/v1/beaches?country=${beach()[3]}&page=${1 + Math.floor(Math.random() * 3)}`;
    if (r < 0.7) return `/api/v1/beaches?page=${1 + Math.floor(Math.random() * 20)}`;
    return `/api/v1/beaches?country=${beach()[3]}&beach_type=sand&parking=1`;
  }],
  ['legacy-redirect', 118, () => `/api/v1/legacy-redirect?path=${encodeURIComponent('/plaze/' + Math.random().toString(36).slice(2, 10))}`],
  ['cities', 89, () => `/api/v1/cities?country=${beach()[3]}`],
  ['islands', 85, () => '/api/v1/islands'],
  ['site-settings', 48, () => '/api/v1/site-settings'],
  ['regions', 43, () => `/api/v1/regions?country=${beach()[3]}`],
  ['first-page-content', 35, () => '/api/v1/first-page-content'],
];
// Only the global reference endpoints (the target: >= 200 req/s with p95 < 200 ms).
const REFERENCE = INCIDENT.filter(([n]) =>
  ['filters', 'countries', 'continents', 'static-pages', 'footer', 'map-bounds', 'site-settings', 'first-page-content', 'islands'].includes(n),
);
const ENDPOINTS = MIX === 'reference' ? REFERENCE : INCIDENT;
const TOTAL_W = ENDPOINTS.reduce((s, [, w]) => s + w, 0);
function nextRequest() {
  let r = Math.random() * TOTAL_W;
  for (const [name, w, path] of ENDPOINTS) {
    if ((r -= w) <= 0) return [name, path()];
  }
  return [ENDPOINTS[0][0], ENDPOINTS[0][2]()];
}

const stats = new Map();
function record(name, ms, status, cache) {
  let s = stats.get(name);
  if (!s) stats.set(name, (s = { lat: [], codes: {}, hit: 0, miss: 0 }));
  s.lat.push(ms);
  s.codes[status] = (s.codes[status] ?? 0) + 1;
  if (cache === 'HIT') s.hit++;
  else if (cache === 'MISS') s.miss++;
}

async function fire(name, path) {
  const t0 = performance.now();
  try {
    const res = await get(BASE + path, { 'accept-language': pick(LOCALES), accept: 'application/json', 'accept-encoding': 'identity' });
    // nginx micro-cache status when present (HIT/STALE/UPDATING = answered without PHP), else Laravel's X-Cache-Status.
    const micro = res.headers['x-micro-cache'];
    record(name, performance.now() - t0, res.statusCode, micro ? (['HIT', 'STALE', 'UPDATING'].includes(micro) ? 'HIT' : 'MISS') : res.headers['x-cache-status']);
  } catch (e) {
    record(name, performance.now() - t0, 'ERR', null);
  }
}

const end = Date.now() + DURATION_S * 1000;
const inflight = new Set();
const started = Date.now();

const health = (async () => {
  while (Date.now() < end) {
    await fire('(health)', '/api/v1/health');
    await new Promise((r) => setTimeout(r, HEALTH_EVERY_S * 1000));
  }
})();

if (RATE > 0) {
  const interval = 1000 / RATE;
  let next = performance.now();
  while (Date.now() < end) {
    const p = fire(...nextRequest());
    inflight.add(p);
    p.finally(() => inflight.delete(p));
    next += interval;
    const wait = next - performance.now();
    if (wait > 0) await new Promise((r) => setTimeout(r, wait));
  }
} else {
  await Promise.all(
    Array.from({ length: CONCURRENCY }, async () => {
      while (Date.now() < end) await fire(...nextRequest());
    }),
  );
}
await Promise.all([...inflight, health]);
const elapsed = (Date.now() - started) / 1000;

const q = (sorted, p) => sorted[Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length))] ?? 0;
const rows = [];
let total = 0;
for (const [name, s] of [...stats.entries()].sort((a, b) => b[1].lat.length - a[1].lat.length)) {
  const sorted = s.lat.slice().sort((a, b) => a - b);
  if (name !== '(health)') total += sorted.length;
  rows.push({
    endpoint: name,
    n: sorted.length,
    p50: q(sorted, 50).toFixed(1),
    p95: q(sorted, 95).toFixed(1),
    p99: q(sorted, 99).toFixed(1),
    max: sorted[sorted.length - 1].toFixed(1),
    codes: Object.entries(s.codes).map(([c, n]) => `${c}:${n}`).join(' '),
    hit: s.hit + s.miss ? `${Math.round((100 * s.hit) / (s.hit + s.miss))}%` : '-',
  });
}
const all = [...stats.entries()].filter(([n]) => n !== '(health)').flatMap(([, s]) => s.lat).sort((a, b) => a - b);
console.log(`base=${BASE} mix=${MIX} duration=${elapsed.toFixed(1)}s ${RATE > 0 ? `rate=${RATE}/s` : `concurrency=${CONCURRENCY}`}`);
console.log(`throughput=${(total / elapsed).toFixed(1)} req/s  all: p50=${q(all, 50).toFixed(1)} p95=${q(all, 95).toFixed(1)} p99=${q(all, 99).toFixed(1)} ms`);
console.log('| endpoint | n | p50 ms | p95 ms | p99 ms | max ms | status | cache HIT |');
console.log('|---|---:|---:|---:|---:|---:|---|---:|');
for (const r of rows) console.log(`| ${r.endpoint} | ${r.n} | ${r.p50} | ${r.p95} | ${r.p99} | ${r.max} | ${r.codes} | ${r.hit} |`);
