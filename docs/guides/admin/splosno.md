# Splošno: prijava, meni, nadzorna plošča

> Velja od: 2026-10-14 · Kje: Admin → **Dashboard** in levi meni · Okolje: dev, uat, prod

## Čemu služi

Admin je nadzorna plošča, kjer urejaš plaže, kraje, fotografije, uporabnike in ostalo vsebino Beachrex. Ta navodila
opisujejo, kako se prijaviš, kako je urejen meni, kaj pomenijo številke na začetni strani in kdaj na strežniku
poženeš dva splošna ukaza (`cache:clear-api` in `search:index`).

## Naslovi admina

Vsako okolje ima svoj admin in svojo bazo. Kar spremeniš na dev, se na uat in prod ne pokaže samo od sebe.

| Okolje | Admin | Spletna stran | API |
|---|---|---|---|
| dev | https://dock-dev.beachrex.com | https://dev.beachrex.com | https://dev-api.beachrex.com |
| uat | https://dock-uat.beachrex.com | https://uat.beachrex.com | https://uat-api.beachrex.com |
| prod | https://dock.beachrex.com | beachrex.com (še ni zagnana, DNS kaže na staro stran) | https://api.beachrex.com |

Admin je na korenu domene (brez `/admin`). Domeno za vsako okolje določa spremenljivka `FILAMENT_DOMAIN` v
`/opt/beachrex/envs/.env.<okolje>`.

## Prijava

1. Odpri naslov admina za želeno okolje.
2. Vpiši **Email address** in **Password**, po želji obkljukaj **Remember me**.
3. Klikni **Sign in**.

Kdo se lahko prijavi: samo uporabnik, ki ima v adminu vklopljeno **Admin Access** (v bazi `is_admin`). Vsak drug
uporabnik dobi enako sporočilo kot pri napačnem geslu: »These credentials do not match our records.« Kako nekomu
dodeliš dostop, je v [uporabniki.md](uporabniki.md).

Prijava v admin je samo z e-pošto in geslom. Prijave z Googlom v adminu ni. Uporabnik, ki se na spletni strani
prijavlja samo z Googlom, ima naključno geslo, ki ga nihče ne pozna; za admin mu moraš geslo nastaviti ročno
(**Users** → **Edit** → **Password**).

Na prijavni strani admina ni povezave »Forgot password?« (v nastavitvah panela ni vklopljena). Če pozabiš geslo, ga
mora nastaviti drug administrator — ni jasno iz kode, ali obstaja drug postopek — preveri.

## Meni (levo)

Na vrhu je vedno **Dashboard** (nadzorna plošča). Pod njim so postavke brez skupine in nato skupine:

| Skupina | Kaj je v njej |
|---|---|
| (brez skupine) | **Articles**, **Badges**, **Beaches**, **Beach Types**, **Continents**, **Countries**, **Regions**, **Waterbodies** |
| **Content** | **First Page Content**, **First Page Items**, **Static Pages**, **Newsletter Subscribers**, **Webcam review** |
| **Geography** | **Cities**, **Islands**, **Rivers**, **Lakes** |
| **Beach Characteristics** | šifranti lastnosti plaže (opis območja, dostopnost, parkiranje, dolžina, naklon, globina, zasedenost …) |
| **Beach Features** | šifranti ponudbe (namestitev, kulinarika, infrastruktura, naravni pogoji, prevoz, športi, tip obiskovalca …) |
| **Media** | **Media**, **Media Types**, **Media Collections** — glej [mediji.md](mediji.md) |
| **User Management** | **Users**, **Genders** — glej [uporabniki.md](uporabniki.md) |
| **System** | **Statuses**, **Fix Duplicate Slugs** — glej [podvojeni-slugi.md](podvojeni-slugi.md) |

Vrstni red skupin določi Filament sam; na zaslonu je lahko drugačen kot v tabeli.

**Iskanje po vsem adminu:** zgoraj je iskalno polje; odpreš ga tudi s tipkama **Ctrl+K** (na Macu **Cmd+K**).

### Skupni gumbi na vseh seznamih

| Gumb | Kaj naredi |
|---|---|
| **New …** (zgoraj desno, npr. **New user**) | odpre obrazec za nov zapis |
| **Edit** (v vrstici) | odpre zapis za urejanje |
| **Save changes** (na obrazcu) | shrani spremembe |
| **Create** / **Create & create another** | shrani nov zapis (in odpre prazen obrazec za naslednjega) |
| **Delete** (na obrazcu zgoraj desno) | izbriše zapis (vpraša za potrditev) |
| Izbira več vrstic → **Delete selected** | izbriše več zapisov naenkrat |

## Nadzorna plošča (Dashboard)

Na začetni strani so kartice s številkami, dva grafa in tabela. Skoraj vsaka kartica je klikljiva in odpre ustrezen
seznam (pogosto že s filtrom).

### 1. vrstica — plaže

| Kartica | Velika številka | Opis pod njo | Klik odpre |
|---|---|---|---|
| **Total Beaches** | vse plaže v bazi (vsi statusi) | — | seznam **Beaches** |
| **Active Beaches** | plaže s statusom **Active** | koliko je ostalih (»inactive«) | **Beaches**, filtrirane na statuse Disabled, Draft, Local review, Main Review, Mobile review |
| **GPS Location** | *plaže brez GPS* / vse plaže | koliko jih nima lokacije in % dokončanosti | **Beaches** s filtrom **GPS Location** = **Missing GPS coordinates** |
| **Media Files** | *plaže brez fotografije* / vse plaže | koliko jih nima aktivne fotografije/videa in % | **Beaches** s filtrom **Media** = **Missing photos/videos** |

Pozor: pri **GPS Location** in **Media Files** je prva številka tisto, kar **manjka** (ni »imamo X od Y«).
»Inactive« pri **Active Beaches** šteje vse, kar ni Active (tudi npr. Mobile active), seznam po kliku pa pokaže samo
pet naštetih statusov, zato se števili lahko razlikujeta.

### 2. vrstica — vsebina in prevodi

| Kartica | Pomen | Klik odpre |
|---|---|---|
| **Articles** | vsi članki; pod številko koliko je aktivnih | **Articles** |
| **Badges** | vse značke | **Badges** |
| **Media Files** | vsi zapisi medijev (vse entitete); pod številko koliko je aktivnih | **Media** |
| **Users** | vsi uporabniki | **Users** |
| **Beach Translations** | *plaže, ki nimajo vseh jezikov* / vse plaže; % = koliko prevodov obstaja glede na plaže × vsi jeziki | **Beaches** s filtrom **Translations** = **Missing translations** |
| **Article Translations** | isto za članke | **Articles** |
| **Badge Translations** | isto za značke | **Badges** |

Kartice s prevodi štejejo vse podprte jezike (trenutno 19: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk,
da, pt, nl, el, sq). Filter **Translations** na seznamu plaž pa še vedno preverja 14 jezikov (»Complete (all 14
languages)«), zato seznam po kliku lahko pokaže manj plaž, kot jih navaja kartica.

### 3. vrstica — geografija

| Kartica | Pomen | Klik odpre |
|---|---|---|
| **Continents** | število celin | **Continents** |
| **Countries** | število držav | **Countries** |
| **Regions** | število regij | **Regions** |
| **Cities & Islands** | mesta + otoki skupaj; pod številko oboje posebej | **Cities** |

### Grafi

| Graf | Kaj kaže |
|---|---|
| **Beach Creation Trends** | koliko plaž je bilo dodanih v bazo po mesecih (zadnjih 12 mesecev). Šteje datum nastanka zapisa, zato uvoz velikega števila plaž naenkrat pokaže en visok vrh. |
| **Top 10 Countries by Beach Count** | kolobar z 10 državami z največ plažami (vsi statusi) |

### Tabela »Beaches Needing Attention«

Pokaže do **15** plaž, ki jim manjka kaj pomembnega: GPS koordinate, fotografije ali prevodi.

| Stolpec | Pomen |
|---|---|
| **Beach Name** | angleško ime plaže (ali prvo, ki obstaja) |
| **Country** | država |
| **GPS** | zelena kljukica = ima koordinate, rdeči križec = nima |
| **Media** | ali ima vsaj en medij |
| **Translations** | število prevodov / 14 (zeleno 14, rumeno 10–13, rdeče manj) |
| **Status** | status plaže |

Gumb **Fix** odpre urejanje te plaže. Tabela vedno kaže samo prvih 15 — ko jih popraviš, se prikažejo naslednje.
Tudi tu je »/14« zastarelo (jezikov je 19).

## Ukaza za lastnika: `cache:clear-api` in `search:index`

Ukaze poganjaš na strežniku. Pred vsak ukaz dodaj (namesto `<okolje>` vpiši `dev`, `uat` ali `prod`):

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje>
```

### `cache:clear-api` — počisti predpomnilnik API

API si nekatere odgovore (šifranti, prevodi, števila plaž) zapomni, da je hitrejši. Ko v adminu urediš plažo ali
šifrant, se večina tega počisti sama. Ročno ga počistiš, ko so se podatki spremenili **mimo admina** (ukazi, ki
pišejo neposredno v bazo, uvozi, AI prevodi) ali ko spletna stran po urejanju še vedno kaže stare podatke.

```
php artisan cache:clear-api --type=all
```

| Možnost | Kaj počisti |
|---|---|
| `--type=all` (privzeto) | ves predpomnilnik aplikacije |
| `--type=reference` | šifrante in referenčne podatke |
| `--type=beach-counts` | števila plaž (po državah, regijah …) |
| `--type=translations` | prevode — po kodi ta možnost verjetno javi napako; ni jasno iz kode — preveri. Uporabi `--type=all`. |

Če okolje ne uporablja Redis predpomnilnika (`CACHE_STORE`), vsaka možnost počisti ves predpomnilnik.
Ukaz ne briše podatkov, samo začasne kopije; prvi obiski po njem so malo počasnejši.

Spletna stran (Next.js) ima svoj predpomnilnik strani in se osveži v največ eni uri, ne glede na ta ukaz.

### `search:index` — osveži iskalnik

Iskalno polje na strani (iskanje plaž, mest, otokov, regij, držav) uporablja ločen iskalnik (Meilisearch).
Ko v adminu shraniš eno plažo ali mesto, se iskalnik posodobi sam. Ročno ga osvežiš po **množičnih spremembah mimo
admina** (npr. `content:ai`, `translations:ai`, uvoz, migracija) ali ko iskanje ne najde nečesa, kar v adminu obstaja.

```
php artisan search:index
```

| Različica | Kaj naredi |
|---|---|
| `search:index` | ponovno pošlje v iskalnik vse plaže, mesta, otoke, regije in države |
| `search:index Beach` | samo plaže (možno: `Beach`, `City`, `Island`, `Region`, `Country`) |
| `search:index --flush` | najprej izprazni indeks, potem ga napolni na novo (med tem iskanje za kratek čas ne najde vsega) |

Ukaz traja nekaj minut, ne spremeni podatkov v bazi. Običajno vrstni red: najprej `cache:clear-api --type=all`,
potem `search:index`.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| »These credentials do not match our records.« | napačno geslo **ali** uporabnik nima **Admin Access** |
| Uporabnik se prijavlja z Googlom, v admin pa ne more | admin ne pozna prijave z Googlom; nastavi mu geslo v **Users** |
| Kartica kaže drugo število kot seznam po kliku | kartica in filter ne štejeta enako (glej opombe pri karticah: statusi, 14 proti 19 jezikov) |
| Sprememba v adminu se na strani ne vidi | počakaj do 1 ure (predpomnjena stran); če se tudi API ne spremeni, poženi `cache:clear-api --type=all` |
| Iskanje na strani ne najde plaže, ki obstaja | `search:index` (po potrebi `search:index Beach`) |
| Na dev urejeno, na uat/prod ni | vsako okolje ima svojo bazo; uat dobi podatke z dev ob promociji baze |

## Za razvijalce

Panel: `app/Providers/Filament/BrAdminPanelProvider.php` (`->path('')`, domena iz `FILAMENT_DOMAIN`).
Dostop: `App\Models\User::canAccessPanel()` → `is_admin === true`. Gradniki: `app/Filament/BrAdmin/Widgets/*`.
Ukaza: `app/Console/Commands/ClearApiCache.php` (`App\Services\CacheService`), `IndexSearchableModels.php` (Scout).
Okolja: `docs/HANDOFF.md` → »Environments«. Znane neskladnosti: »/14« v `CoverageGapsWidget`, `GenderResource` in
filtru `missing_translations` v `BeachResource` (podprtih jezikov je 19, `SetLocaleFromHeader::SUPPORTED_LOCALES`);
`cache:clear-api --type=translations` kliče `clearTranslationCache(null)`, ki zahteva `string`.


## Prenos datoteke s strežnika na Mac

Ukazi, ki naredijo datoteko (npr. `webcams:export`, `webcams:legacy-extract`), jo shranijo **v kontejner** `api_<okolje>`
(pot se začne z `/var/www/html/…`). Na računalnik jo preneseš v dveh korakih:

1. Na strežniku jo prekopiraj iz kontejnerja v `/root` (zamenjaj ime datoteke s tistim, ki ga je izpisal ukaz):
   ```
   cd /opt/beachrex/deploy
   docker compose -f docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.dev cp api_dev:/var/www/html/storage/app/private/webcams/<ime>.csv /root/<ime>.csv
   ```
2. Na Mac-u (v Terminalu na svojem računalniku, ne na strežniku):
   ```
   scp root@<naslov-strežnika>:/root/<ime>.csv ~/Downloads/
   ```
   `<naslov-strežnika>` je isti naslov, s katerim se prijaviš na strežnik (`ssh root@…`).
