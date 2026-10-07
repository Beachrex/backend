import { test, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { API_LOCALES, BEACHES_WORD, normalizeLocale, frontendLocale, getBeachesWord } from '../src/locales.js';
import { mockFetch } from './helpers.js';
import * as fx from './fixtures.js';
import { searchBeaches } from '../src/api-client.js';

let restore: (() => void) | undefined;
afterEach(() => restore?.());

test('19 API locales', () => {
  assert.deepEqual([...API_LOCALES], ['en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk', 'da', 'pt', 'nl', 'el', 'sq']);
});

test('every frontend locale has a "beaches" URL word (web-client routing.ts)', () => {
  for (const l of API_LOCALES) {
    assert.ok(BEACHES_WORD[frontendLocale(l)], `missing ${l}`);
  }
  assert.equal(getBeachesWord('da'), 'straande');
  assert.equal(getBeachesWord('pt'), 'praias');
  assert.equal(getBeachesWord('nl'), 'stranden');
  assert.equal(getBeachesWord('el'), 'paralies');
  assert.equal(getBeachesWord('sq'), 'plazhet');
  assert.equal(getBeachesWord('sl'), 'plaze');
  assert.equal(getBeachesWord('si'), 'plaze');
});

test('normalizeLocale accepts si, case and region variants', () => {
  assert.equal(normalizeLocale('si'), 'sl');
  assert.equal(normalizeLocale('SL'), 'sl');
  assert.equal(normalizeLocale('de-DE'), 'de');
  assert.equal(normalizeLocale('pt_BR'), 'pt');
  assert.equal(normalizeLocale('cz'), 'cs');
  assert.equal(normalizeLocale('xx'), 'en');
  assert.equal(normalizeLocale(undefined), 'en');
  assert.equal(frontendLocale('sl'), 'si');
  assert.equal(frontendLocale('si'), 'si');
});

test('"si" input: Accept-Language sl, frontend URL /si/plaze/', async () => {
  process.env.BEACHREX_API_URL = 'https://api.test';
  process.env.BEACHREX_FRONTEND_URL = 'https://web.test';
  const m = mockFetch();
  restore = () => m.restore();
  m.on('/beaches', fx.beachesIndex);
  const r = await searchBeaches({}, normalizeLocale('si'));
  assert.equal(m.calls[0].headers['Accept-Language'], 'sl');
  assert.match(r.beaches[0].beachrex_url, /^https:\/\/web\.test\/si\/plaze\/europe\//);
});
