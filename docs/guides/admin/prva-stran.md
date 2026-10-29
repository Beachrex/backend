# Vsebina prve strani

> Velja od: 2026-10-14 (primer plaže v `beach_views`: 2026-10-28) · Kje: Admin → **Content → First Page Content** in **Content → First Page Items** · Okolje: dev, uat, prod

## Čemu služi

Domača stran (`/<jezik>`) je sestavljena iz **razdelkov** (sections). Za nekatere razdelke naslove, opise, slike in
kartice urejaš v adminu:

- **First Page Content** — razdelki prve strani (en razdelek = ena vrstica, npr. `hero_banner`).
- **First Page Items** — kartice znotraj razdelkov (npr. štiri kartice »fotografije, video, 360°« v razdelku
  `beach_views`). Iste kartice vidiš tudi v zavihku **Items** pri razdelku.

Postavitev strani, gumbi in njihove povezave so v kodi spletne strani; v adminu menjaš samo besedila in slike.

## Kateri razdelek je kaj na domači strani

Spletna stran razdelke išče po **kodi** (**Code**). Razdelek z drugo kodo se na domači strani ne pokaže. Kode zato
**ne spreminjaj** in ne dodajaj novih razdelkov v pričakovanju, da se bodo pokazali.

Od zgoraj navzdol:

| Koda | Kje na strani | Kaj se uporabi iz admina |
|---|---|---|
| `hero_banner` | velika slika na vrhu z glavnim naslovom | **Title** prevoda = glavni naslov strani (pomemben za Google); slika ozadja iz zavihka **Media** (glej spodaj). **Description** se ne uporablja. |
| `browse_map` | razdelek z zemljevidom sveta | **Title**, **Description**, slika zemljevida (slika z oznako **Set as Primary**, sicer prva). Gumb »Zemljevid plaž« je v kodi. |
| `find_beach` | »Poišči plažo po svojem okusu« | **Title**, **Description**. Gumbi kategorij (morske, jezerske, rečne plaže, bazeni) so v kodi. |
| `beach_views` | kartice »oglej si plaže v vseh pogledih« | **Title** razdelka in **primer plaže** (`showcase_beach_id` v **Additional Configuration**, glej [Primer plaže](#primer-plaže-v-razdelku-beach_views)). Kartice (**Items**: **Title**, ikona, slika ozadja) se uporabijo samo, če se primer plaže ne naloži. |
| `accommodation` | razdelek z nastanitvami | **trenutno skrit** (nastanitve so skrite za zagon) |
| *(seznam držav)* | najbolj priljubljene destinacije | ni iz admina, podatki pridejo iz plaž |
| `featured_sections` | štiri velike slikovne ploščice spodaj | za vsako kartico (**Items**, po vrstnem redu): **Title** in slika (medij tipa **Image**). Uporabijo se samo prve **4** kartice. |
| `footer_text` | stavek pod štirimi ploščicami | **Title** |

Gumbi na štirih ploščicah `featured_sections` so določeni po **položaju** kartice, ne v adminu:

| Položaj kartice | Gumb |
|---|---|
| 1. | Zemljevid plaž |
| 2. | Blog — trenutno brez gumba (blog je skrit) |
| 3. | Nastanitve — trenutno brez gumba (nastanitve so skrite) |
| 4. | Zemljevid plaž |

Če spremeniš vrstni red kartic, se gumbi ne premaknejo z njimi.

Ploščice `featured_sections` se pokažejo samo, če obstaja (in je **Active**) tudi razdelek `footer_text`.

Razdelek `coastal_destinations` (če obstaja v bazi) se na domači strani **ne prikazuje**.

Slika ozadja `hero_banner` se uporablja tudi kot pasica na strani **Vse plaže** (`/<jezik>/beaches`).

## Seznam razdelkov (First Page Content)

Admin → **Content → First Page Content**. Razvrščeno po **Order**.

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Code** | oznaka razdelka (glej tabelo zgoraj) |
| **Title (EN)** | angleški naslov ali `-` |
| **Type** | **Section Type**: banner, text_image, repeater, text_only (samo opis, na strani ne vpliva) |
| **Order** | vrstni red v adminu |
| **Items** | število kartic v razdelku |
| **Status** | **Active** (zelena) = prikazan; vse drugo = skrit |
| **Created**, **Updated** | skrita stolpca (vklopiš ju z ikono stolpcev) |

### Filtri in gumbi

| Element | Kaj naredi |
|---|---|
| filter **Section Type** | samo razdelki izbrane vrste |
| filter **Status** | samo razdelki z izbranim statusom |
| **New first page content** (zgoraj desno) | nov razdelek (na strani se ne pokaže, če spletna stran ne pozna kode) |
| **Edit** / **Delete** (v vrstici) | uredi / izbriše razdelek |
| označi vrstice → **Delete selected** | izbriše več razdelkov |

## Urejanje razdelka

Odpri razdelek z **Edit**. Na vrhu so nastavitve, spodaj trije zavihki: **Translations**, **Items**, **Media**.

### Nastavitve (razdelka **Basic Information** in **Configuration**)

| Polje | Pomen |
|---|---|
| **Code** | obvezno, enolično. Ne spreminjaj. |
| **Section Type** | obvezno; samo za pregled v adminu, spletna stran ga ne upošteva |
| **Display Order** | obvezno; vrstni red v adminu in v API. Postavitev domače strani je v kodi, zato premik razdelka na strani ne spremeni vrstnega reda. |
| **Status** | **Active** = razdelek se prikaže. Kateri koli drug status ga skrije. |
| **Additional Configuration** | pari ključ–vrednost. Spletna stran uporablja samo `showcase_beach_id` v razdelku `beach_views` (glej spodaj); ostale ključe ignorira. |

Shrani z **Save changes**. Po ustvarjanju novega razdelka te admin pošlje na urejanje, da dodaš prevode in medije.

### Primer plaže v razdelku `beach_views`

Razdelek »View beaches from all angles« na domači strani pokaže **eno pravo plažo** kot primer: štiri kartice s
fotografijami te plaže, klik odpre galerijo, video, 360° ogled ali spletno kamero v živo. Pod naslovom piše
»Primer: <plaža>, <kraj>, <država>« s povezavo na stran plaže.

Katera plaža je primer, določa ključ **`showcase_beach_id`** v **Additional Configuration** razdelka `beach_views`.
Vrednost je **ID plaže** (številka), npr. `989` = Svetilnik, Izola (privzeto od 2026-10-28).

**Kako zamenjaš plažo**

1. Poišči plažo v Admin → **Beaches** in si zapiši njen **ID** (stolpec ID ali številka v naslovu strani pri urejanju).
2. Preveri, da ima plaža vse, kar želiš pokazati: fotografije, video (YouTube), 360° ogled in spletno kamero.
   Vrsta, ki je plaža nima, se v primeru ne pokaže (npr. brez kamere so samo tri kartice).
3. Admin → **Content → First Page Content** → razdelek `beach_views` → **Edit**.
4. V **Additional Configuration** v vrstici `showcase_beach_id` vpiši novi ID (če vrstice ni: **Add row**, ključ
   `showcase_beach_id`, vrednost ID). Shrani z **Save changes**.
5. Domača stran pokaže novo plažo v največ **5 minutah**.

**Kaj če …**

| Primer | Kaj se zgodi |
|---|---|
| vrstico `showcase_beach_id` izbrišeš ali pustiš prazno | razdelek je spet statičen: štiri kartice iz **Items** brez klika |
| vpišeš ID, ki ne obstaja, ali plaža ni aktivna | prav tako statični razdelek (stran se ne pokvari) |
| plaža nima nobene fotografije, videa, 360° ali kamere | statični razdelek |
| plaži kasneje dodaš video ali kamero | pojavi se v primeru, ko se osveži predpomnilnik podrobnosti plaže (do 8 ur) |

Naslov kartic (»Gallery«, »Video« …) v primeru pride iz prevodov spletne strani, ne iz **Items**.

### Zavihek Translations — besedila

En prevod na jezik (jezikov je 19: `en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq`).
Če jezika ni, spletna stran pokaže angleško besedilo.

| Polje | Pomen |
|---|---|
| **Language** | obvezno; vsak jezik samo enkrat |
| **Title** | naslov razdelka |
| **Description** | daljše besedilo (navaden tekst, brez oblikovanja) |
| **Additional Metadata** | pari ključ–vrednost; spletna stran jih trenutno ne uporablja |

Gumbi: **New first page content translation**, v vrstici **Edit** in **Delete**.

### Zavihek Items — kartice

Kartice razdelka (enako kot meni **First Page Items**, glej spodaj). Gumbi: **New first page content item**,
**Edit**, **Delete**. Stolpci: **Order**, **Title (EN)**, **Translations**, **Media**, **Status**.

### Zavihek Media — slike in ikone

Slik ne nalagaš tukaj: datoteka mora biti že na CDN (DigitalOcean Spaces), ti vpišeš samo njeno pot.

| Polje | Pomen |
|---|---|
| **File Name** | obvezno; ime datoteke za pregled |
| **File Path** | obvezno; pot na CDN, npr. `img/first-page/hero-bg.jpg` |
| **Media type** | obvezno; **Image** za slike, **Icon** za ikone (pri karticah `beach_views` to odloči, ali je datoteka ikona ali ozadje) |
| **Media collection** | neobvezno; pri `hero_banner` ima prednost medij z zbirko s kodo `background_image` (če taka zbirka obstaja) |
| **Disk** | obvezno; privzeto **DigitalOcean Spaces** — pusti tako |
| **Status** | obvezno |
| **Image Properties** (zaprto) | **MIME Type**, **File Size (bytes)**, **Width (px)**, **Height (px)**, **Display Order**, **Set as Primary** |

Katera slika se uporabi:

- `hero_banner`: medij v zbirki `background_image`; če ga ni, tisti z **Set as Primary**; sicer prvi po **Display Order**.
- `browse_map`: tisti z **Set as Primary**, sicer prvi.
- `accommodation`: vse slike (razdelek je trenutno skrit).

Stolpci: **Preview**, **File name**, **Type**, **Collection**, **Dimensions**, **Primary**, **Order**, **Status**.
Filtri: **Type**, **Collection**, **Primary Only**. Ali predogled (**Preview**) v adminu prikaže sliko, ni jasno iz
kode — preveri.

**Pozor:** **Status** medija na spletni strani nič ne skrije — API vrne vse medije razdelka ne glede na status.
Medij, ki ga ne želiš prikazati, izbriši.

## Kartice (First Page Items)

Admin → **Content → First Page Items** — vse kartice vseh razdelkov na enem mestu.

### Stolpci, filtri, gumbi

| Element | Pomen |
|---|---|
| **Section** | koda razdelka, ki mu kartica pripada |
| **Title (EN)** | angleški naslov ali `-` |
| **Order** | vrstni red v razdelku (to spletna stran upošteva) |
| **Translations**, **Media** | število prevodov / medijev |
| **Status** | glej opozorilo spodaj |
| filter **Section**, filter **Status** | zoži seznam |
| **New first page content item**, **Edit**, **Delete**, **Delete selected** | standardno |

### Urejanje kartice

| Polje | Pomen |
|---|---|
| **First page content** | obvezno; razdelek, kateremu kartica pripada |
| **Display Order** | obvezno; vrstni red znotraj razdelka |
| **Status** | obvezno |
| **Additional Configuration** | pari ključ–vrednost; spletna stran jih ne uporablja |

Pod obrazcem sta zavihka **Translations** (**Language**, **Title**, **Description**, **Additional Metadata** — na
domači strani se uporablja samo **Title**) in **Media** (enaka polja kot pri razdelku).

**Pozor:** **Status** kartice je ne skrije — API vrne vse kartice razdelka ne glede na status. Če kartice ne želiš na
strani, jo **izbriši**.

## Kdaj se spremembe vidijo na spletu

Spremembe gredo skozi dva predpomnilnika: **API** (backend) in **spletno stran**.

| Kaj spremeniš | API | Domača stran |
|---|---|---|
| prevod **razdelka** (Title, Description) | takoj | v največ **5 minutah**; glavni naslov na vrhu se v brskalniku osveži že ob naslednjem nalaganju |
| nastavitve razdelka (Status, Order, **Additional Configuration**) | takoj (od 2026-10-28) | v največ **5 minutah** |
| kartice, prevodi kartic, mediji | **ne osveži se samodejno** — glej spodaj | ko se osveži API, še do 5 minut |
| slika ozadja na strani **Vse plaže** | kot zgoraj | do 1 ure |

Ko spreminjaš kartice, njihove prevode ali medije, API lahko še dolgo (do 30 dni) vrača staro
vsebino. Najlažja rešitev: na koncu shrani (**Save changes**) katerikoli prevod razdelka v zavihku **Translations**
(tudi brez sprememb) — to počisti predpomnilnik prve strani v vseh jezikih. Druga možnost je ukaz na strežniku:

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje> php artisan cache:clear-api
```

Ta ukaz nima `--dry-run`; počisti **ves** predpomnilnik API tega okolja (stran je nekaj minut počasnejša, podatki se
ne izgubijo).

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Spremenil sem kartico, na strani je stara | API predpomnilnik se ni počistil; shrani prevod razdelka ali poženi `cache:clear-api`, nato počakaj do 5 minut |
| Razdelek sem nastavil na **Disabled**, pa je še vedno viden | počakaj do 5 minut (spletna stran); API se od 2026-10-28 osveži takoj |
| Primer plaže v »View beaches from all angles« se ne pokaže, so samo statične kartice | `showcase_beach_id` manjka, je prazen ali plaža s tem ID-jem ne obstaja / ni aktivna |
| Kartica s statusom **Disabled** je vidna | status kartice ne skrije; izbriši kartico |
| Nov razdelek se ne pokaže | spletna stran pozna samo kode iz tabele zgoraj |
| Naslov v nekem jeziku je angleški | ta jezik nima prevoda; dodaj ga v **Translations** |
| Slika se ne prikaže | **File Path** ne obstaja na CDN ali je napačen **Media type** (ikona/slika) |
| Gumb na ploščici vodi drugam, kot pričakujem | gumbi so vezani na položaj kartice (1.–4.), ne na kartico |
| Ploščice spodaj izginejo | razdelek `footer_text` ni **Active** ali nima kartic `featured_sections` |

## Za razvijalce

- Admin: `app/Filament/BrAdmin/Resources/FirstPageContentResource.php`, `FirstPageContentItemResource.php` (+ podmape `RelationManagers`).
- API: `GET /api/v1/first-page-content` in `/first-page-content/{code}` (`FirstPageContentController`, samo
  `status_id = 1` za razdelke; kartice in mediji niso filtrirani po statusu). Predpomnilnik 30 dni, ključa
  `first_page_content:index:{locale}` in `first_page_content:show:{code}:{locale}`; briše ju samo
  `FirstPageContentTranslation` in od 2026-10-28 tudi `FirstPageContent` (saved/deleted, TASK-008); item, item
  translation in media nimajo invalidacije.
- Primer plaže (TASK-008): `config.showcase_beach_id` → web naloži `GET /beach-details/{id}`; komponenta
  `src/components/Home/BeachMediaShowcase.tsx`, privzeta vrednost iz migracije `2026_10_28_120000_set_homepage_showcase_beach`.
- Web: `src/app/[locale]/page.tsx` (ISR `revalidate = 300`), `src/components/Home/HeroSection.tsx` +
  `src/hooks/useFirstPageContent.ts` (`staleTime: 0`, samo hero), `src/lib/utils/hero-image.ts`,
  `src/app/[locale]/beaches/page.tsx` (hero slika, ISR 3600). Skrite funkcije: `src/lib/constants/features.ts`.
- Ozadje: `DEV_HANDOFF.md` 2026-09-03 »FirstPageContent Cache Invalidation« in popravek 2026-10-07 (vseh 19 jezikov).
