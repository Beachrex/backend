# Geografija (celine, države, regije, mesta, otoki, jezera, reke)

> Velja od: 2026-10-14 · Kje: Admin → **Continents**, **Countries**, **Regions** (brez skupine v meniju) in
> Admin → **Geography → Cities, Islands, Rivers, Lakes** · Okolje: vsa (dock-dev, dock-uat, dock.beachrex.com)

## Čemu služi

Tu urejaš kraje, v katere so razvrščene plaže: celino, državo, regijo, mesto, otok, jezero in reko. Iz teh zapisov
spletna stran gradi strani destinacij (npr. `/beaches/europe/croatia/...`), drobtinice (breadcrumbs), filtre po
kraju, iskalnik in pasico (banner) s sliko in značkami.

Vsak kraj ima:

- **kodo** (Code) – stalni notranji identifikator (npr. `HR`, `krk`, `dalmatia-split`); na strani se ne prikazuje,
- **prevode** (Translations) – ime in slug (del povezave) v vsakem jeziku; ime na strani pride **samo iz prevodov**,
- pri večini še **status** in **glavno sliko**.

Jezikov je 19 (`SetLocaleFromHeader::SUPPORTED_LOCALES`): en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk,
da, pt, nl, el, sq. Če prevoda v nekem jeziku ni, API pokaže angleškega; če ni niti angleškega, pokaže kodo.

## Kje v meniju

| Meni | Kaj je | Vrstni red |
|---|---|---|
| **Continents** | celine | brez skupine |
| **Countries** | države | brez skupine |
| **Regions** | regije (npr. Istra, Kvarner) | brez skupine |
| **Geography → Cities** | mesta in kraji | 1. v skupini |
| **Geography → Islands** | otoki | 2. |
| **Geography → Rivers** | reke | 3. |
| **Geography → Lakes** | jezera | 4. |

**Countries**, **Regions**, **Cities** in **Islands** najdeš tudi z globalnim iskanjem zgoraj (Ctrl+K / Cmd+K) po
kodi ali imenu.

## Skupni postopki (veljajo za vse strani)

### Dodaj nov kraj

1. Odpri stran v meniju in klikni **New …** zgoraj desno (npr. **New country**, **New city**).
2. Izpolni obrazec (polja so spodaj po straneh) in klikni **Create**. (**Create & create another** shrani in odpre
   prazen obrazec za naslednjega, **Cancel** prekliče.)
3. Odpre se urejanje zapisa. Pod obrazcem je razdelek **Translations** – tam dodaj ime v jezikih (glej spodaj).
   Brez vsaj angleškega prevoda se v adminu in na strani namesto imena kaže koda.

### Uredi kraj

1. V seznamu klikni **Edit** v vrstici.
2. Spremeni polja in klikni **Save changes**.
3. Prevode, značke in članke urejaš v razdelkih pod obrazcem (shranijo se takoj, brez **Save changes**).

### Prevodi (razdelek »Translations«)

| Gumb / polje | Kaj naredi |
|---|---|
| **New … translation** (zgoraj desno v razdelku) | doda prevod za en jezik |
| **Language** | jezik (19 možnosti); obvezno |
| **Name** | ime kraja v tem jeziku; obvezno |
| **Slug (URL-friendly)** | del spletne povezave v tem jeziku, npr. `hrvaska`; neobvezno |
| **Edit** / **Delete** v vrstici | uredi ali izbriše prevod |
| izbira več vrstic → **Delete selected** | izbriše več prevodov |

Stolpci tabele prevodov: **Language** (pri 14 jezikih z zastavico, pri da, pt, nl, el, sq samo koda), **Name**,
**Slug**. Na en kraj je dovoljen **en prevod na jezik** (baza ima to omejitev); drugega v istem jeziku ne moreš dodati,
obstoječega uredi.

Pozor pri slugu: slug je del povezave na spletni strani. Če ga spremeniš, se spremeni naslov strani in stare povezave
(Google, deljene povezave) prenehajo delovati. Ali stari slug kam preusmeri, ni jasno iz kode – preveri.

### Glavna slika (Main Image)

Polja **Continent Main Image**, **Country Main Image**, **Region Main Image**, **City Main Image**,
**Island Main Image**: naloži sliko (največ 10 MB). Vgrajen urejevalnik omogoča obrezovanje na prosto razmerje,
16:9, 4:3 ali 1:1. Slika se shrani na DigitalOcean Spaces (mapa `public/img/<continents|countries|regions|cities|islands>`),
ime datoteke se samodejno pretvori v znake brez šumnikov. Spletna stran jo uporabi kot pasico (banner) na strani
destinacije in kot sliko na karticah. Jezera in reke slike nimajo.

### Brisanje

Brisanje je mogoče na dva načina: v urejanju gumb **Delete** zgoraj desno ali v seznamu izbereš vrstice →
**Delete selected**. V seznamu v vrstici ni gumba za brisanje. Kaj se zgodi s plažami, je v poglavju
[Kaj se zgodi ob brisanju](#kaj-se-zgodi-ob-brisanju).

## Continents (celine)

**Obrazec**

| Polje | Pomen |
|---|---|
| **Code** | koda celine, obvezno, največ 2 znaka (npr. `EU`) |
| **Continent Main Image** | glavna slika celine |

**Seznam:** stolpca **Code** in **Continent Name**. Filtrov ni.
**Pod obrazcem:** samo **Translations**. Celina nima statusa.

## Countries (države)

**Obrazec**

| Polje | Pomen |
|---|---|
| **Code** | dvočrkovna koda države, obvezno (npr. `HR`) |
| **Iso3** | trimestna ISO koda (npr. `HRV`), neobvezno |
| **Number** | številčna ISO koda (do 3 znake), neobvezno |
| **Continent code** | koda celine, **vpišeš jo ročno** (npr. `EU`); mora obstajati v **Continents** |
| **Country Main Image** | glavna slika države |

**Seznam**

| Stolpec | Pomen |
|---|---|
| **Image** | sličica glavne slike (lahko jo skriješ) |
| **Code** | koda |
| **Country Name** | angleško ime (iz prevodov) |
| **ISO3**, **Number** | ISO kodi (lahko ju skriješ) |
| **Continent** | koda celine |

Filtrov ni. Država nima statusa.
**Pod obrazcem:** **Translations**, **Badges**, **Articles** (glej [Značke in članki](#značke-in-članki-pri-kraju)).

## Regions (regije)

**Obrazec**

| Polje | Pomen |
|---|---|
| **Region Code** | koda regije, obvezno, do 100 znakov (npr. `dalmatia-split`) |
| **Country** | država, ki ji regija pripada (izbira »Ime (KODA)«); obvezno |
| **Status** | status; privzeto prvi (Active) |
| **Region Main Image** | glavna slika regije |

**Seznam**

| Stolpec | Pomen |
|---|---|
| **Code** | koda |
| **Region Name** | angleško ime, pod njim država |
| **Country** | država (lahko skriješ) |
| **Beaches** | število plaž v regiji (vse, ne glede na status plaže) |
| **Status** | zelen = Active, rdeč = Inactive, siv = drugo |
| **Created at**, **Updated at** | skrita; vklopiš jih pri ikoni stolpcev |

**Filtri:** **Country**, **Status**. Privzeto razvrščeno po kodi.
**Pod obrazcem:** **Translations**, **Badges**, **Articles**.

## Geography → Cities (mesta)

**Obrazec**

| Polje | Pomen |
|---|---|
| **City Code** | koda mesta, obvezno, do 100 znakov (npr. `split`) |
| **Country** | država; obvezno. Ko jo izbereš, se napolni seznam regij |
| **Region** | regija izbrane države; neobvezno |
| **Status** | status; privzeto prvi (Active) |
| **City Main Image** | glavna slika mesta |

Otoka mestu v tem obrazcu ne moreš določiti (v bazi polje obstaja, v obrazcu ga ni).

**Seznam:** **Code**, **City Name** (pod imenom regija ali država), **Country**, **Region**, **Beaches**, **Status**,
skrita **Created at** / **Updated at**.
**Filtri:** **Country**, **Region** (prikazuje kode regij), **Status**.
**Pod obrazcem:** **Translations**, **Badges**, **Articles**.

## Geography → Islands (otoki)

**Obrazec**

| Polje | Pomen |
|---|---|
| **Island Code** | koda otoka, obvezno, do 100 znakov (npr. `krk`) |
| **Status** | status; privzeto prvi (Active) |
| **Island Main Image** | glavna slika otoka |

Države in regije otoku v tem obrazcu ne moreš določiti (v bazi polji obstajata in ju spletna stran uporablja, v
obrazcu ju ni).

**Seznam:** **Code**, **Island Name**, **Beaches**, **Status**, skrita **Created at** / **Updated at**.
**Filter:** **Status**.
**Pod obrazcem:** **Translations**, **Badges**, **Articles**.

## Geography → Lakes in Rivers (jezera, reke)

Obe strani sta enaki.

**Obrazec**

| Polje | Pomen |
|---|---|
| **Lake Code** / **River Code** | koda, obvezno, do 100 znakov (npr. `bled`, `sava`) |
| **Status** | status; privzeto prvi (Active) |

**Seznam:** **Code**, **Lake Name** / **River Name**, **Beaches**, **Status**, skrita **Created at** / **Updated at**.
**Filter:** **Status**.
**Pod obrazcem:** samo **Translations**. Slike, značk in člankov nimata.

Na strani plaže se ime jezera ali reke vrne v podatkih plaže (lokacija).

## Značke in članki pri kraju

Pri **Countries**, **Regions**, **Cities** in **Islands** sta pod obrazcem razdelka **Badges** in **Articles**. Tu
kraju **pripneš** obstoječe značke in članke (ustvariš jih v **Badges** oz. **Articles**).

| Gumb | Kaj naredi |
|---|---|
| **Attach** (zgoraj desno v razdelku) | izbereš značko/članek in ga pripneš kraju |
| **Detach** v vrstici | odpne (značka/članek ostane, samo ni več pri tem kraju) |
| izbira več vrstic → **Detach selected** | odpne več naenkrat |

Stolpci **Badges**: **Code**, **Badge Name**, **Icon**, **Status**.
Stolpci **Articles**: **Code**, **Article Title**, **Category**, **Status**.

Pripete značke se na spletni strani pokažejo v pasici strani kraja. Več v [znacke.md](znacke.md).

## Status

**Status** (Regions, Cities, Islands, Lakes, Rivers) izbiraš iz seznama **System → Statuses**. Koda šteje kot
aktiven samo status z ID 1 (**Active**). Kaj natančno status skrije:

- regije, mesta in otoki s statusom, ki ni Active, se ne dodajo v iskalnik (Meilisearch) in se ne pojavijo kot
  »prejšnji/naslednji« pri navigaciji med kraji;
- seznami krajev na spletni strani pokažejo samo kraje, ki imajo vsaj eno aktivno plažo;
- ali status kraja skrije tudi samo stran kraja, ni jasno iz kode – preveri.

Celine in države statusa nimajo; država je v iskalniku, če ima vsaj eno aktivno plažo.

## Kaj se zgodi po shranjevanju

- **Admin:** spremembe so v bazi takoj.
- **API predpomnilnik:** podatki krajev se v API hranijo do 30 dni. Samodejno se ob shranjevanju počisti samo seznam
  celin (shranjevanje v **Continents**). Pri državah, regijah, mestih, otokih, jezerih, rekah ter pri prevodih in
  pripetih značkah se predpomnilnik **ne počisti sam**: sprememba se v API pokaže po naslednjem deployu (deploy vedno
  požene `cache:clear`) ali ko ga počistiš ročno (glej spodaj).
- **Spletna stran:** strani celin in držav se dodatno hranijo do 1 ure (ISR), domača stran do 5 minut.
- **Iskalnik:** sprememba kode, slike ali statusa države, regije, mesta ali otoka se v iskalnik prenese ob
  shranjevanju. Ali se novo ime iz prevoda prenese brez ponovnega indeksiranja, ni jasno iz kode – preveri; deploy
  vedno požene `search:index`.

Ročno čiščenje predpomnilnika (na strežniku, pred ukaz dodaj
`docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje>`):

```
php artisan cache:clear-api
```

## Kaj se zgodi ob brisanju

Pravila so v bazi (migracije). Plaže se nikoli ne izbrišejo, izgubijo pa povezavo s krajem:

| Izbrišeš | Plaže | Drugo |
|---|---|---|
| celino | polje celine pri plažah se izprazni | državam se izprazni **Continent code**; prevodi celine se izbrišejo |
| državo | polje države pri plažah se izprazni | regijam, mestom in otokom se izprazni država; uporabnikom se izprazni država; prevodi, pripete značke in članki se odpnejo/izbrišejo |
| regijo | polje regije se izprazni | mestom in otokom se izprazni regija; prevodi se izbrišejo, značke in članki se odpnejo |
| mesto | polje mesta se izprazni | prevodi se izbrišejo, značke in članki se odpnejo |
| otok | polje otoka se izprazni | mestom se izprazni otok; prevodi se izbrišejo, značke in članki se odpnejo |
| jezero / reko | polje jezera/reke se izprazni | prevodi se izbrišejo |

Brisanje se ne da razveljaviti. Če kraja ne želiš več prikazovati, raje spremeni status (kjer ga ima).

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| V seznamu ali na strani je namesto imena koda (npr. `krk`) | ni angleškega prevoda; dodaj ga v **Translations** |
| Na strani je v nekem jeziku angleško ime | prevoda za ta jezik ni; dodaj ga (ali ukaz `translations:ai`, glej [ai-vsebina.md](ai-vsebina.md)) |
| Prevoda v jeziku ne morem dodati, javi napako | prevod v tem jeziku že obstaja; uredi obstoječega |
| Iskanje v seznamu (polje Search) javi napako | znana napaka (preverjeno 2026-10-14 na **Countries**: ime ni v glavni tabeli, ampak v prevodih). Verjetno enako na **Regions**, **Cities**, **Islands**, **Lakes**, **Rivers**. Dokler ni popravljeno, išči z globalnim iskanjem (Ctrl+K) ali uporabi filtre |
| Shranjevanje države javi napako pri **Continent code** | vpisane kode celine ni v **Continents**; vpiši obstoječo (npr. `EU`) |
| Sprememba se na spletni strani ne vidi | predpomnilnik (do 30 dni v API, do 1 ure na strani); počakaj na deploy ali poženi `cache:clear-api` |
| Mesto ne pripada otoku / otok ni pri pravi državi | teh polj v obrazcu ni; popravek zahteva razvijalca |
| Spremenil sem kodo države ali celine in shranjevanje ne uspe | na kodo so vezane plaže in drugi zapisi; ali baza spremembo dovoli, ni jasno iz kode – kode raje ne spreminjaj |
| **Attach** pri značkah ali člankih javi napako ali ne najde ničesar | seznam išče po stolpcu imena oz. naslova, ki je v prevodih; ni jasno iz kode, ali deluje – preveri na dev |

## Za razvijalce

Viri: `app/Filament/BrAdmin/Resources/{Continent,Country,Region,City,Island,Lake,River}Resource.php` in podmape
(`RelationManagers/{Translations,Badges,Articles}RelationManager.php`). Predpomnilnik: `app/Traits/Cacheable.php`
(samo `Continent`, `Waterbody`, `Beach`, `FirstPageContent`), `app/Services/CacheService.php`, ključi v
`app/Http/Controllers/Api/V1/*Controller.php` (`REFERENCE_DATA_TTL` = 30 dni). Odprta točka »cache invalidacija za
ostale modele«: `docs/deployment/DEPLOYMENT_GUIDE.md` §9. Iskalnik: `toSearchableArray()` / `shouldBeSearchable()` v
modelih `Country`, `Region`, `City`, `Island`. Tuji ključi: `database/migrations/*add_*_to_beach_table.php`,
`2025_11_02_070421_add_id_to_continents_table.php`, `2025_11_02_070424_add_id_to_countries_table.php`.
