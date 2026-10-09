# Hidden for launch — features to build later

Owner, 2026-10-08: "vse kar boš skril si moraš nekje zabeležit, da bomo vedeli kaj je treba razvit."

Everything the pre-launch review (TASK-004) hides from visitors is listed here: what it was, where the code is,
and what is needed to bring it back. When a feature is built, move its row to "Brought back" with the PR.

| # | Feature | Where it was visible | Code (web-client unless noted) | Why hidden | Needed to bring it back | Hidden in |
|---|---|---|---|---|---|---|
| H1 | Accommodation (menu CTA) | Burger menu "Accommodation" → `/beach-accommodation` | `src/components/Header/BurgerMenu.tsx` | No route (404) | An accommodation source (partner API or own data), a route and a listing page | TASK-004 (PR tbd) |
| H2 | Accommodation (search bar icon) | Header search bar "Accommodations" icon | `src/components/Header/SearchBar.tsx` | Button without action | Same as H1 | TASK-004 (PR tbd) |
| H3 | Accommodation (home) | Home: accommodations button, `HomeAccommodationSection` CTA, hero stat "+6250 accommodations" | `src/app/[locale]/page.tsx`, `Home/HomeAccommodationSection.tsx`, `Home/HeroSection.tsx`, `messages/*.json` | 404 / fake number | Same as H1, plus a real count from the API | TASK-004 (PR tbd) |
| H4 | Accommodation (footer) | Footer browse column `/accommodations` | `messages/*.json` `Footer.browse.links` | 404 | Same as H1 | TASK-004 (PR tbd) |
| H5 | Accommodation sections on pages | Beach, city, region, island, country pages ("Apartma Pehta", "569 accommodations") | `AccommodationCard/AccommodationsSection.tsx`, `Pages/BeachDetailPage.tsx`, listing pages | Fake data | Same as H1 | TASK-004 (PR tbd) |
| H6 | Favorites | Top bar heart button | `src/components/Header/TopBar.tsx` | Button without action | Favorites API (per user) + UI | TASK-004 (PR tbd) |
| H7 | My account | Top bar account button | `src/components/Header/TopBar.tsx` | Button without action | Account page (profile, AI credits, purchases, favorites, sign-out) | TASK-004 (PR tbd) |
| H8 | Shop | Header search bar "Shop" icon | `src/components/Header/SearchBar.tsx` | Button without action | Shop concept and route | TASK-004 (PR tbd) |
| H9 | Blog | Home "Blog" button → `/blog` | `src/app/[locale]/page.tsx`, `Home/ExploreSection.tsx` | No route (404) | Blog (CMS posts in 19 locales) and a route | TASK-004 (PR tbd) |
| H10 | GetYourGuide activities | Beach page activities section (heading + filters, no content) | `src/components/Beach/GetYourGuideSection.tsx` | Empty shell | GetYourGuide partner integration | TASK-004 (PR tbd) |
| H11 | Beach type pages | `/beach-type`, `/beach-type/[type]` (stub, debug heading) | `src/app/[locale]/beach-type/**`, `src/app/sitemap/static.xml` | Placeholder content | Real pages built on the map/search filters | TASK-004 (PR tbd): removed from the sitemap, `noindex` |
| H12 | User type pages | `/user-type`, `/user-type/[type]` (stub) | `src/app/[locale]/user-type/**`, `src/app/sitemap/static.xml` | Placeholder content | Define user types (families, surfers, …) and their content | TASK-004 (PR tbd): removed from the sitemap, `noindex` |
| H13 | Map quick filters: free beach, parking nearby, wide beach, naturist beach | Beach map sidebar tiles (design "basic filters") | web-client `src/config/mapQuickFilters.ts` | Free beach, parking nearby and wide beach have no backend filter; naturist (`location_infrastructure.naturist_friendly`) has no icon | Backend filters (free entry, parking distance, beach width) and icons in `public/images/categories/` | Map quick filters PR (2026-10-08) |
| H14 | Beach banner: Google ratings and save | Beach page banner buttons "Google ocene 7.2" and "shrani" | web-client `src/components/Beach/BeachBanner/TopBarBeachRight.tsx` | Rating was a hardcoded 7.2; save needs favorites (H-favorites) | Real Google rating source (Places API) and favorites | web-client share menu PR (2026-10-08) |
| H15 | City page category tiles | City page tiles "Peščene plaže, Kamnite plaže, Bazen, Tobogani, Primerne za pse, …" (`FEATURES.cityCategoryTiles`) | web-client `src/components/CityPage/CityCategories.tsx`, `src/components/Pages/CityListingPage.tsx` | No click action at all (owner 2026-10-09) | Each tile sets the matching filter of the "Browse all beaches" list (server-side filters, see `GET /beaches`) | Beachrex/web-client#52 |

## Brought back

| # | Feature | PR |
|---|---|---|
