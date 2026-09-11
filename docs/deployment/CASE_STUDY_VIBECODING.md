# Case Study: Kako vibecoding skoraj uniči produkcijsko okolje — in zakaj inženirska izkušenost šteje

> **Avtor:** David Tacer  
> **Datum:** September 2026  
> **Projekt:** Beachrex — migracija Laravel 12 API + Next.js 14 frontend na Hetzner VPS  
> **Kontekst:** AI agent je zgradil MVP. Inženir ga je naredil skalabilnega.

---

## Povzetek

AI agent (Cursor/Claude) je v enem tednu zgradil CI/CD pipeline, Docker infrastrukturo in deploy sistem za 3 okolja (DEV/UAT/PROD) na enem VPS-u. Na prvi pogled — vse je delovalo. Na drugi — vsaka sprememba v DEV adminu je postala vidna na UAT in PROD frontendu, API je crashiral zaradi pomanjkanja pomnilnika, in deployi so si med seboj brisali kodo.

Ta case study dokumentira **10 kritičnih napak**, ki jih je AI agent naredil pri postavljanju infrastrukture, zakaj so nastale, in kako jih je inženir popravil. Namen je pokazati, da vibecoding brez inženirskega nadzora producira MVP-je, ne skalabilnih produktov.

---

## 1. Kontekst: Kaj je bil narejen z AI

AI agent je uspešno:
- Generiral `docker-compose.vps.yml` za 3 okolja na enem strežniku
- Napisal GitHub Actions deploy workflowe (backend + frontend)
- Postavil Nginx reverse proxy s SSL
- Migriral bazo iz Laravel Clouda na VPS (PostgreSQL + PostGIS)
- Generiral Filament admin panele
- Vzpostavil branch → okolje mapping (`dev`/`uat`/`main`)

**Rezultat:** Vse je delovalo. API je vračal 200. Frontend se je naložil. Admin panel je bil dostopen.

**Problem:** Pod površjem je bilo 10 kritičnih napak, od katerih bi vsaka na produkciji povzročila izpad, izgubo podatkov, ali varnostno incident.

---

## 2. Napake, ki jih je naredil AI agent

### Napaka 1: Vsaka okolja so delila isto kodo

**Kaj je AI naredil:** `docker-compose.vps.yml` je definiral `api_prod`, `api_uat`, `api_dev` kontejnerje, ampak vsi so imeli `build: context: ../api` — isti direktorij.

**Posledica:** Push na `dev` je prepisal kodo, ki so jo uporabljali tudi UAT in PROD kontejnerji. Zadnji deploy je "zmagal" in vsi so tekel enako kodo.

**Zakaj AI to ni ulovil:** AI rešuje trenutni problem (postavi kontejnerje), ne razmišlja o konceptu okoljske izolacije. Za AI je "api" ena stvar — ne razlikuje med "koda za DEV" in "koda za PROD".

### Napaka 2: Redis cache je bil deljen med okolji

**Kaj je AI naredil:** V `config/database.php` je Redis `cache` konekcija imela `env('REDIS_CACHE_DB', '1')`. Default vrednost 1 je veljala za vsa okolja.

**Posledica:** DEV (REDIS_DB=2) in UAT (REDIS_DB=1) sta delila isti Redis cache database. Sprememba v DEV adminu je šla v cache, UAT je prebral iz istega cachea — uporabnik je videl DEV podatke na UAT strani.

**Zakaj AI to ni ulovil:** AI pozna Laravel konvencijo (default Redis cache DB je 1), ampak ne razmišlja o multi-tenancy. V tutorialih je ena aplikacija = en Redis. Na enem VPS-u z 3 okolji je to katastrofa.

### Napaka 3: Hardcodiran DEV API URL v frontendu

**Kaj je AI naredil:** 6 datotek v frontendu je imelo `https://dev-api.beachrex.com` kot fallback za `NEXT_PUBLIC_API_ENDPOINT`.

**Posledica:** `NEXT_PUBLIC_*` variable se v Next.js inline-a ob build time. Ker fallback ni bil pravilno nastavljen v `.env.front.*`, je UAT frontend kliče DEV API. Sprememba v DEV adminu je postala vidna na UAT.

**Zakaj AI to ni ulovil:** AI dodaja fallback-e, da "koda ne crash-a". Ne razmišlja, da se ta fallback serializira v build in postane hardcoded v produkciji.

### Napaka 4: Cache-ali sorove Eloquent modele (28MB na ključ)

**Kaj je AI naredil:** `BeachController::index()` je cache-al `Beach::with([...8 relacij...])->limit(5000)->get()` v Redis preko `Cache::remember()`.

**Posledica:** Laravel `serialize()` shrani cele Eloquent modele z relacijami — 28MB na ključ. Pri `unserialize()` PHP porabi 200MB+. Z 14 jezikov × več filter kombinacij = 204MB v Redis. PHP je crashiral z "Allowed memory size exhausted".

**Zakaj AI to ni ulovil:** AI ve, da `Cache::remember()` cach-a rezultat. Ne razmišlja o tem, KAJ se serializira in KOLIKO pomnilnika potrebuje `unserialize()`. Za AI je cache = hitro, ne cache = pomnilniška bomba.

### Napaka 5: Ni bilo cache invalidacije

**Kaj je AI naredil:** `FirstPageContent` in `FirstPageContentTranslation` modela nista imela cache invalidacije. Controller je cache-a podatke, ampak admin save ni čistil cachea.

**Posledica:** Admin spremembe niso bile vidne na frontendu, dokler cache ni potekel (30 dni TTL).

**Zakaj AI to ni ulovil:** AI dodaja caching, ker je to "best practice". Ne razmišlja o invalidacijski strategiji — to je arhitekturna odločitev, ne implementacijska.

### Napaka 6: Next.js ISR 1 ura na homepage

**Kaj je AI naredil:** `export const revalidate = 3600` na homepage.

**Posledica:** Admin spremembe so bile vidne šele po 1 uri. Za CMS- upravljano vsebino je to nesprejemljivo.

**Zakaj AI to ni ulovil:** ISR je "best practice" za Next.js. AI ga aplikira povsod, ne razmišlja o tem, da je homepage CMS-upravljan.

### Napaka 7: Laravel ni zaupal reverse proxy

**Kaj je AI naredil:** Ni dodal `$middleware->trustProxies(at: '*')` v `bootstrap/app.php`.

**Posledica:** Laravel je ignoriral `X-Forwarded-Proto: https` in generiral `http://` URL-je za assete. Brskalnik je blokiral kot Mixed Content.

**Zakaj AI to ni ulovil:** AI ne razmišlja o SSL termination na reverse proxy. V tutorialih je Laravel na portu 443, ne za Nginx-om.

### Napaka 8: Admin panel domena hardcoded

**Kaj je AI naredil:** `BrAdminPanelProvider::getDomain()` je vračal `dock.beachrex.com` kadar je `APP_ENV=production`.

**Posledica:** Vsa 3 okolja na VPS imajo `APP_ENV=production` — vsi so uporabljali `dock.beachrex.com` kot admin domeno. Admin spremembe so šle v napačno bazo.

**Zakaj AI to ni ulovil:** AI logika: "production = dock.beachrex.com". Ne razmišlja, da so na VPS vsi env-i production.

### Napaka 9: Docker Compose brez build.args

**Kaj je AI naredil:** `front_*` servisi v `docker-compose.vps.yml` niso imeli `build.args` sekcije.

**Posledica:** `NEXT_PUBLIC_*` variable niso bile podane ob buildu — vsi frontend kontejnerji so uporabljali fallback URL-je.

**Zakaj AI to ni ulovil:** AI razlikuje med `env_file` (runtime) in `build.args` (build-time), ampak ne ve, da Next.js inline-a `NEXT_PUBLIC_*` ob buildu.

### Napaka 10: Hkratni deployi brez serializacije

**Kaj je AI naredil:** `concurrency.group: deploy-${{ github.ref }}` — per-branch, ne globalni.

**Posledica:** Push na `dev` in `uat` hkrati je sprozil dva rsync-a v isti direktorij — race condition, rsync error code 23.

**Zakaj AI to ni ulovil:** AI pozna GitHub Actions concurrency, ampak ne razmišlja, da rsync ni thread-safe.

### Napaka 11: Ročno kopiranje datotek na VPS mimo CI/CD pipeline

**Kaj je AI naredil:** Pri postavljanju MCP serverja je AI ročno kopiral datoteke na VPS preko `scp` in restartal procese z `ssh`, namesto da bi vse spremembe šle skozi GitHub Actions deploy workflow.

**Posledica:** Trije kaskadni problemi:
1. **Lastništvo `root` namesto `beachrex`** — `scp` kot root je ustvaril datoteke v lasti `root`. Naslednji `rsync` iz CI/CD (ki teče kot `beachrex`) ni mogel brisati ali prepisovati teh datotek → `Permission denied` → deploy faila.
2. **`dist/` pobrisan** — `dist/` direktorij (kompilirana TypeScript koda) ni v gitu (je v `.gitignore`). `rsync --delete` ga je pobrisal. MCP server je crknil, ker `dist/index.js` manjka.
3. **Koda ni v `dev` branchu** — spremembe so bile samo na `feature` branchu. Naslednji deploy iz `dev` je prepisal MCP server s staro kodo, ki ni imela popravkov.

**Zakaj AI to ni ulovil:** AI rešuje trenutni problem ("dobi MCP server na VPS delujoč") in ne razmišlja o:
- Kaj se zgodi pri naslednjem deploy-u? Ali bo `rsync --delete` pobrisal moje datoteke?
- Ali je lastništvo pravilno? `scp` kot root ustvari `root` lastništvo, CI/CD teče kot `beachrex`.
- Ali so spremembe v `dev` branchu? Če niso, jih naslednji deploy bo prepisal.

Inženir bi vedel, da na produkcijskem strežniku **nikoli** ne kopiraš datotek ročno — vse gre skozi CI/CD pipeline, da je stanje vedno sinhronizirano in reproducibilno.

**Kaj bi se zgodilo, če uporabnik tega ne bi opazil:**
- Naslednji push na `dev` bi deploy failal (Permission denied) — **celoten dev environment bi bil nedelujoč**
- MCP server bi bil nedelujoč po vsakem deploy-u, ker `dist/` manjka
- Vsak restart VPS-a bi pomenil ročno rebuild MCP serverja — brez dokumentacije tega postopka
- Nov razvijalec ne bi vedel, zakaj MCP ne deluje in bi izgubil ure iskanja
- V najslabšem primeru: `uat` in `main` deployi bi prav tako failali, če bi MCP bil dodan tam — **popoln izpad vseh okolij**

---

## 3. Zakaj se to zgodi — vzroki vibecodinga

### Vzrok 1: AI rešuje trenutni problem, ne sistemske zahtevke

AI agent dobi ukaz "postavi 3 okolja na VPS" in to naredi. Ne razmišlja o:
- Kaj se zgodi, če push-aš na `dev` in `uat` hkrati?
- Kaj se zgodi, če je 100 sočasnih uporabnikov?
- Kaj se zgodi, če admin spremeni vsebino — ali se cache počisti?

Inženir pa vpraša: "Kaj se zgodi v najslabšem scenariju?" preden napiše vrstico kode.

### Vzrok 2: AI pozna "best practices", ne pa arhitekturne odločitve

AI ve, da je caching dober (`Cache::remember`), da je ISR dober (`revalidate = 3600`), da je eager loading dober (`with()`. Ne ve pa, kdaj so te "best practices" napačne:
- Cache-ati 5000 Eloquent modelov z 8 relacijami = 28MB na ključ
- ISR na CMS-upravljeni strani = 1 ura zamude
- Eager loading brez limit = OOM

### Vzrok 3: AI ne razmišlja o okoljski izolaciji

V tutorialih je ena aplikacija, ena baza, en Redis. AI ne razmišlja o multi-tenancy. Na enem VPS-u z 3 okolji pa je izolacija kritična:
- Ločeni direktoriji za kodo
- Ločene Redis DB-je
- Ločene admin domene
- Ločeni API URL-ji v buildu

### Vzrok 4: AI ne testira robnih pogojev

AI ne pošlje 100 sočasnih zahtevkov. Ne push-a na 3 branche hkrati. Ne spremeni admin vsebine in preveri, ali se pojavi na frontendu. Ne preveri, kaj se zgodi pri naslednjem deploy-u. Inženir pa točno to počne — testira robne pogoje, ker ve, da so tam težave.

### Vzrok 5: AI ne spoštuje CI/CD pipeline

AI rešuje trenutni problem ("dobi to na VPS delujoč") in ne razmišlja o:
- Kaj se zgodi pri naslednjem deploy-u? Ali bo `rsync --delete` pobrisal moje datoteke?
- Ali je lastništvo pravilno? `scp` kot root ustvari `root` lastništvo, CI/CD teče kot `beachrex`.
- Ali so spremembe v `dev` branchu? Če niso, jih naslednji deploy bo prepisal.

Inženir ve, da na produkcijskem strežniku **nikoli** ne kopiraš datotek ročno — vse gre skozi CI/CD pipeline, da je stanje vedno sinhronizirano in reproducibilno.

---

## 4. Kako inženir popravi

### Pristop

Inženir ne popravlja simptomov — išče korenite vzroke. Za vsak problem:
1. **Diagnoza:** Preveri dejansko stanje na strežniku (logi, Redis, DB, Nginx)
2. **Koreni vzrok:** Ugotovi, ZAKAJ se to dogaja (ne samo KAJ)
3. **Popravek:** Popravi vzrok, ne simptom
4. **Preverba:** Meri pred in po (velikost cachea, odzivni čas, memory poraba)
5. **Dokumentacija:** Zapiše, kaj je bilo narobe in zakaj, da se ne ponovi

### Rezultat popravkov

| Metrika | Pred (AI) | Po (inženir) | Izboljšava |
|---|---|---|---|
| Največji Redis ključ | 28 MB | 1.4 MB | 20x manj |
| Skupna Redis poraba | 204 MB | 5.4 MB | 38x manj |
| Cache HIT čas | 30s / timeout | 0.36s | 70x hitreje |
| Admin → frontend zamuda | 1 ura | takoj | ∞ |
| Cross-env cache leak | da | ne | popravljeno |
| Cross-env API klici | da | ne | popravljeno |
| Koda izolacija | ne | da | popravljeno |
| PHP OOM crash | da | ne | popravljeno |
| Ročno kopiranje na VPS | da | ne | popravljeno |
| Deploy po MCP spremembi | faila | samodejen rebuild | popravljeno |

---

## 5. Zaključek — VMP ni skalabilni produkt

AI agent je v enem tednu naredil MVP, ki je deloval na prvi pogled. Za demo, prototip ali hackathon je to odlično. Za produkcijski sistem z realnimi uporabniki ne.

**VMP (Vibecoding Minimum Product) značilnosti:**
- ✅ Deluje na prvi pogled
- ✅ Hitro postavljeno
- ❌ Ni okoljsko izolirano
- ❌ Ni skalabilno
- ❌ Ni varno za sočasnost
- ❌ Nima cache invalidacije
- ❌ Crash-a pod obremenitvijo

**Skalabilni produkt značilnosti:**
- ✅ Okoljsko izolirano (koda, baza, cache, admin, API URL)
- ✅ Cache z invalidacijo (model eventi)
- ✅ Cache z razumno velikostjo (Resource::resolve(), ne Eloquent modeli)
- ✅ Sočasno varno (concurrency serializacija)
- ✅ Proxy-aware (TrustProxies)
- ✅ Monitorirano (logi, metrike, alarmi)

### Spodnja črta

**Vibecoding je orodje, ne nadomestek inženirstva.** AI agent je odličen za:
- Generiranje boilerplate
- Hitre iteracije prototipa
- Razširjanje obstoječe kode

**AI agent NI odličen za:**
- Arhitektura sistema
- Okoljska izolacija
- Cache strategija
- Skalabilnost
- Varnost
- Robni pogoji
- CI/CD pipeline integriteta — nikoli ne kopiraj ročno na strežnik, kar lahko prepiše CI/CD

**Inženir vodi AI agenta, ne obratno.** AI generira, inženir odobri. AI predlaga, inženir odloči. AI implementira, inženir preveri.

> "AI je gradbeni delavec. Inženir je arhitekt. Lahko imaš najboljšega delavca na svetu — brez arhitekta bo zgradba crashala."

---

## 6. Pravila za vibecoding pri produkcijskih sistemih

1. **AI generira, inženir arhitektira** — AI naj implementira, ne odloča o arhitekturi
2. **Preveri okoljsko izolacijo** — vsako okolje mora imeti ločeno kodo, bazo, cache, admin
3. **Preveri cache velikost** — nikoli ne cache-aj Eloquent modelov, vedno Resource::resolve()
4. **Preveri cache invalidacijo** — vsak model, ki se cache-a, mora imeti saved/deleted event
5. **Preveri build-time vs runtime env** — NEXT_PUBLIC_* se inline-a ob buildu, ne runtime
6. **Preveri concurrency** — rsync in docker compose nista thread-safe
7. **Preveri proxy awareness** — za vsakim reverse proxyjem mora aplikacija zaupati proxy
8. **Meri pred in po** — brez metrik ne veš, ali je popravek res popravek
9. **Dokumentiraj korenite vzroke** — ne samo KAJ je narobe, ampak ZAKAJ
10. **Testiraj robne pogoje** — sočasnost, obremenitev, admin spremembe, cross-env
11. **Nikoli ne kopiraj ročno na VPS** — vse spremembe gredo skozi CI/CD pipeline. Ročno `scp`/`rsync` povzroči lastništvo `root`, `dist/` pobrise, in deploy faila.

---

*Avtor je inženir, ki popravlja, kar AI zgradi. Ta case study je temeljen na resničnih dogodkih pri migraciji Beachrex na VPS.*

