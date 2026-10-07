/** fetch mock: routes are matched in order against the request path (/api/v1 stripped). */

export interface Call { url: URL; path: string; headers: Record<string, string>; }

type Route = { match: (path: string, url: URL) => boolean; status: number; body: unknown };

export function mockFetch() {
  const routes: Route[] = [];
  const calls: Call[] = [];
  const original = globalThis.fetch;

  const fake = async (input: string | URL | Request, init?: RequestInit): Promise<Response> => {
    const url = new URL(typeof input === 'string' ? input : input instanceof URL ? input.href : input.url);
    const path = url.pathname.replace(/^\/api\/v1/, '');
    calls.push({ url, path, headers: (init?.headers ?? {}) as Record<string, string> });
    const i = routes.findIndex((r) => r.match(path, url));
    if (i === -1) throw new Error(`Unexpected fetch: ${url}`);
    const [route] = routes.splice(i, 1); // each route answers once
    return new Response(JSON.stringify(route.body), { status: route.status, headers: { 'Content-Type': 'application/json' } });
  };
  globalThis.fetch = fake as typeof fetch;

  const api = {
    calls,
    on(match: string | RegExp | ((path: string, url: URL) => boolean), body: unknown, status = 200) {
      const m = typeof match === 'function'
        ? match
        : typeof match === 'string' ? (p: string) => p === match : (p: string) => match.test(p);
      routes.push({ match: m, status, body });
      return api;
    },
    restore() { globalThis.fetch = original; },
    pending() { return routes.length; },
  };
  return api;
}
