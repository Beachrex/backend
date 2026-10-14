# Šifranti lastnosti plaž in statusi

> Velja od: 2026-10-14 · Kje: Admin → **Beach Types**, **Waterbodies** (brez skupine), **Beach Characteristics → …**,
> **Beach Features → …**, **System → Statuses** · Okolje: vsa (dock-dev, dock-uat, dock.beachrex.com)

## Čemu služi

Šifrant je seznam dovoljenih vrednosti za eno lastnost plaže, npr. tip plaže (pesek, prodnik …), dostop (lahek,
zahteven …) ali ponudba hrane. Pri plaži nato izbereš vrednost iz šifranta. Iz šifrantov spletna stran gradi:

- **filtre** na zemljevidu in v oknu filtrov (»kakšno plažo iščeš«),
- **podatke na strani plaže** (razdelki z lastnostmi, ponudbo, parkiranjem),
- nekatere **ikone** na strani plaže in na karticah.

Vsi šifranti delujejo enako, razlike so v tabeli [Kaj ureja kateri šifrant](#kaj-ureja-kateri-šifrant).

## Skupni vzorec

Vsaka vrednost ima:

| Lastnost | Kje jo urejaš | Pomen |
|---|---|---|
| **Code** | obrazec | stalni notranji identifikator (npr. `sand`, `easy`, `parking`). Spletna stran nanj veže ikone in povezave, zato ga po prvem vnosu **ne spreminjaj**. Enoličen. |
| **Status** | obrazec | samo **Active** (ID 1) šteje kot aktiven; v filtrih se pokažejo samo aktivne vrednosti |
| prevodi | razdelek **Translations** pod obrazcem | ime vrednosti v vsakem jeziku |
| **Show filter** | obrazec, **samo** pri **Beach Types** in **Waterbodies** | ali se vrednost pokaže v filtrih |
| ikona, vrstni red v filtru, naslov filtra | **ni v adminu** | glej [Ikone, vrstni red in naslov filtra](#ikone-vrstni-red-in-naslov-filtra) |

### Prevodi

Razdelek **Translations** pod obrazcem (pri vseh šifrantih razen **Statuses**):

| Gumb / polje | Kaj naredi |
|---|---|
| **New … translation** (zgoraj desno v razdelku) | doda prevod za en jezik |
| **Language** | jezik, obvezno; 19 jezikov: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq |
| **Name** | ime vrednosti v tem jeziku, obvezno (to se pokaže na strani) |
| **Slug (URL-friendly)** | del povezave, neobvezno |
| **Edit** / **Delete** v vrstici, **Delete selected** | uredi ali izbriše prevode |

En prevod na jezik (baza drugega ne dovoli). Če prevoda v jeziku obiskovalca ni, stran pokaže angleškega; če ni niti
angleškega, pokaže kodo (npr. `small_stones`). Za množično dopolnjevanje manjkajočih jezikov je ukaz
`translations:ai` (glej [ai-vsebina.md](ai-vsebina.md)).

### Dodaj vrednost

1. Odpri šifrant v meniju in klikni **New …** zgoraj desno (npr. **New beach type**, **New accessibility**).
2. Vpiši **Code**, izberi **Status** (privzeto Active) in pri **Beach Types** / **Waterbodies** še **Show filter**.
3. Klikni **Create** (ali **Create & create another**).
4. V razdelku **Translations** dodaj ime vsaj v angleščini, najbolje v vseh 19 jezikih.

### Uredi vrednost

V seznamu klikni **Edit**, spremeni polja in klikni **Save changes**. Prevodi se v razdelku **Translations** shranijo
takoj.

### Seznam

| Šifranti | Stolpci | Filtri |
|---|---|---|
| **Beach Types**, **Waterbodies** | **Code**, **Status**, **Show filter** (kljukica), skrita **Created at** / **Updated at** | ni |
| **Accessibilities**, **Area Descriptions** | **Code**, **Name**, **Beaches**, **Status** | **Status** |
| vsi ostali v **Beach Characteristics** in **Beach Features** | **Code**, **Name**, **Beaches count**, **Status** | **Status** |

**Name** je angleško ime (iz prevodov). **Beaches** / **Beaches count** je število plaž s to vrednostjo (vse, ne glede
na status plaže). Status je zelen (Active), rdeč (Inactive) ali siv (drugo). Seznami razen **Beach Types** in
**Waterbodies** so razvrščeni po kodi.

Akcije: **Edit** v vrstici; **Delete selected** pri izbiri več vrstic; **Delete** zgoraj desno v urejanju.

## Kaj ureja kateri šifrant

Stolpec »Filter« pove, ali API vrednosti ponudi kot filter (ključ filtra v oklepaju). »Okno filtrov« je okno na
domači strani, ki pokaže samo 8 izbranih filtrov; stranski filtri na zemljevidu pokažejo vse, ki jih vrne API.
»Plaža« je razdelek s podatki na strani plaže.

| Meni | Kaj pomeni | Pri plaži | Filter | Kje na strani |
|---|---|---|---|---|
| **Beach Types** | tip/površina plaže (pesek, prodnik, skale, trava, beton …) | ena vrednost | da (`beach_type`), okno filtrov | Plaža (tip); ikona v vrsti značk na strani plaže in na karticah; 3 hitri izbori na domači strani (`sand`, `small_stones`, `grass_beach`) |
| **Waterbodies** | vrsta vode: morje, jezero, reka, bazen (`sea`, `lake`, `river`, `pool`) | ena vrednost | da (`waterbody`), okno filtrov | Plaža |
| **Beach Characteristics → Area Descriptions** | opis okolice (npr. majhna, srednja, velika) | ena | da (`area_description`) | Plaža |
| **Beach Characteristics → Accessibilities** | dostopnost plaže (npr. lahek, zahteven) | ena | da (`accessibility`) | Plaža; koda `easy` doda značko »lahek dostop« na strani plaže |
| **Beach Characteristics → Road To Parking Types** | vrsta ceste do parkirišča | ena | ne | Plaža (pot do parkirišča) |
| **Beach Characteristics → Parking Capacities** | število parkirnih mest | ena | da (`parking_capacity`) | Plaža (parkirna mesta); vpliva na značko parkirišča (kodi `0` in `private_parking_only` pomenita »ni javnega parkirišča«) |
| **Beach Characteristics → Parking Beach Distances** | razdalja parkirišče–plaža | ena | ne | Plaža (razdalja) |
| **Beach Characteristics → Parking Beach Heights** | višinska razlika parkirišče–plaža | ena | ne | Plaža (višina) |
| **Beach Characteristics → Location Lengths** | dolžina plaže | ena | ne | Plaža (dolžina) |
| **Beach Characteristics → Bay Lengths** | širina zaliva/plaže | ena | ne | Plaža (širina) |
| **Beach Characteristics → Beach Inclinations** | naklon plaže | ena | da (`beach_inclination`) | Plaža (naklon) |
| **Beach Characteristics → Swimming Depth Of One Ms** | kako daleč od obale je globina 1 m | ena | da (`swimming_depth_of_one_m`) | Plaža (območje za plavanje) |
| **Beach Characteristics → Occupancy Rates** | obiskanost | ena | da (`occupancy_rate`) | Plaža (obiskanost) |
| **Beach Characteristics → Beach Location Types** | vrsta lokacije (kode npr. `LK`, `T`, `PB`, `AQ`) | več vrednosti | ne | API jih vrne pri plaži, spletna stran jih trenutno ne prikazuje |
| **Beach Characteristics → Water Body Types** | vrsta vodnega telesa (`lake`, `sea`, `river`) | ena | ne | uporabljeno v samodejnem opisu strani plaže (SEO), ko plaža nima opisa |
| **Beach Features → Accommodation Arounds** | nastanitve v okolici | več | da (`accommodation_around`), okno filtrov | Plaža (nastanitev); hitri izbor `autocamping` na domači strani |
| **Beach Features → Culinary Offers** | gostinska ponudba | več | da (`culinary_offer`), okno filtrov | Plaža (hrana) |
| **Beach Features → Location Infrastructures** | infrastruktura (parkirišče, bazen, tobogan, dostop za invalide, psi …) | več | da (`location_infrastructure`), okno filtrov | Plaža; ikone za kode `access_for_disabled`, `dog_friendly`, `swimming_pool`, `waterslide`, `dance_floor`, `parking`; hitri izbori na domači strani |
| **Beach Features → Natural Location Conditions** | naravne danosti (npr. naravna senca) | več | da (`natural_location_condition`), okno filtrov | Plaža (naravne značilnosti); hitri izbor `natural_shade` |
| **Beach Features → Recommended Transport Types** | priporočen način prihoda | več | da (`recommended_transport_type`) | Plaža (priporočen dostop) |
| **Beach Features → Sporting Activities** | športne aktivnosti | več | da (`sporting_activities`), okno filtrov | Plaža (šport); ikona za `windsurfing` |
| **Beach Features → Underwater Characteristics** | morsko dno / podvodne značilnosti | več | da (`underwater_characteristic`) | Plaža (dno) |
| **Beach Features → User Types** | komu je plaža namenjena (družine, mladi, pustolovci …) | več | da (`user_type`), okno filtrov | Plaža; ikone in značke za kode `family`, `adventurer`, `youth` |
| **System → Statuses** | statusi zapisov (plaže, kraji, šifranti, značke, mediji) | – | – | glej [Statuses](#system--statuses) |

»Ena vrednost« pomeni, da v obrazcu plaže (**Beaches**) izbereš eno vrednost iz spustnega seznama (prikazano je
angleško ime ali koda). Pri šifrantih z »več« vrednostmi v obrazcu plaže ni polja za izbiro; kje se urejajo, ni jasno
iz kode – preveri.

## Ikone, vrstni red in naslov filtra

V adminu teh nastavitev **ni**. Kje so:

- **Ikone na strani plaže in na karticah** so vezane na **kodo** in zapisane v kodi spletne strani: tip plaže in
  tipi uporabnikov uporabljajo slike z DigitalOcean Spaces (`js/assets/images/categories_icons/`, npr.
  `beach_type-sand.png`, `user_type-family.png`, `parking.png`), infrastruktura in šport pa slike iz spletne strani
  (`/images/categories/…`). Nova vrednost zato **nima ikone**, dokler je razvijalec ne doda; sprememba kode obstoječe
  vrednosti ikono izgubi.
- **Hitri izbori na domači strani** (12 ikon) so prav tako zapisani v kodi spletne strani po kodah
  (`web-client/src/config/topFilterPicks.ts`).
- **Ikona naslova filtra** (stranski filtri na zemljevidu) je polje `icon` v bazi; API vzame ikono prve vrednosti
  šifranta (po vrstnem redu). V adminu je ne moreš nastaviti.
- **Vrstni red vrednosti v filtru** je polje `filter_order` v bazi (nato po kodi); v adminu ga ni.
- **Naslov filtra** (npr. »Beach Type«) je polje `filter_title` v prevodu; v adminu ga ni. Če ga ni, API pokaže
  angleški privzeti naslov.
- **Prikaz v filtru** (`show_filter`) lahko vklopiš/izklopiš samo pri **Beach Types** in **Waterbodies**; pri ostalih
  šifrantih je privzeto vklopljen in ga v adminu ne moreš spremeniti.

Za spremembo teh polj pri drugih šifrantih potrebuješ razvijalca.

## System → Statuses

Seznam statusov, ki jih izbiraš v poljih **Status** po celem adminu.

**Obrazec:** samo **Name** (obvezno). **Seznam:** **Id**, **Name** (zelen = Active, rumen = Inactive, rdeč = Deleted,
siv = drugo), **Beaches count** (število plaž s tem statusom). Razvrščeno po ID. Prevodov ni.

Začetni statusi: 1 Active, 2 Disabled, 3 Deleted, 4 Draft, 5 Local review, 6 Main Review, 7 Payment waiting,
8 Payment canceled, 9 Payment received, 10 Demo apartment - for preview, 11 Mobile review, 12 Mobile active,
13 Media Uploaded.

Pomembno:

- Koda povsod preverja **ID 1** kot »aktivno« (plaže, kraji, šifranti v filtrih, značke). Ime lahko spremeniš, a
  statusa 1 ne briši in mu ne spreminjaj pomena.
- Novi zapisi v šifrantih in krajih dobijo privzeto status 1.
- Statusa, ki ga uporablja katerikoli zapis (plaža, kraj, šifrant, značka, medij, uporabnik …), baza **ne pusti
  izbrisati** (tuji ključ brez pravila za brisanje); admin pokaže napako.

## Kaj se zgodi po shranjevanju

- **Admin:** spremembe so v bazi takoj.
- **Filtri:** seznam filtrov se v API hrani do **30 dni** po jeziku in kraju. Shranjevanje šifranta ga **ne
  počisti**.
- **Seznami šifrantov v API** (`/api/v1/accessibilities` ipd.): tudi do 30 dni, se ne počistijo sami.
  **Waterbodies** ima samodejno čiščenje ob shranjevanju, vendar briše ključe brez jezika, API pa jih shranjuje z
  jezikom, zato verjetno ne učinkuje – ni jasno iz kode, preveri.
- **Stran plaže:** podatki plaže se v API hranijo do **8 ur**, spletna stran pa stran dodatno do 1 ure.

Spremembe se zanesljivo pokažejo po naslednjem deployu (deploy požene `cache:clear`) ali ko na strežniku poženeš
(pred ukaz dodaj
`docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje>`):

```
php artisan cache:clear-api
```

## Status vrednosti

- **Filtri** pokažejo samo vrednosti s statusom Active (ID 1) in vklopljenim **Show filter**.
- **Na strani plaže** se vrednost pokaže ne glede na svoj status (API pri podatkih plaže statusa šifranta ne
  preverja). Če vrednosti ne želiš več kazati pri plaži, jo plaži odstrani.

## Kaj se zgodi ob brisanju

Pravila so v bazi (migracije):

| Šifrant | Plaže, ki imajo to vrednost |
|---|---|
| šifranti z eno vrednostjo (**Beach Types**, **Waterbodies**, **Area Descriptions**, **Accessibilities**, **Road To Parking Types**, **Parking Capacities**, **Parking Beach Distances**, **Parking Beach Heights**, **Location Lengths**, **Bay Lengths**, **Beach Inclinations**, **Swimming Depth Of One Ms**, **Occupancy Rates**, **Water Body Types**) | plaža ostane, polje se **izprazni** (lastnost ni več nastavljena) |
| šifranti z več vrednostmi (vsi v **Beach Features** in **Beach Location Types**) | plaža ostane, povezava z vrednostjo se **izbriše** |
| **Statuses** | brisanje ni mogoče, dokler status kdorkoli uporablja |

Prevodi izbrisane vrednosti se izbrišejo samodejno. Brisanje se ne da razveljaviti in plažam ne vrne podatka. Preden
izbrišeš, poglej stolpec **Beaches** / **Beaches count**; če vrednosti samo ne želiš v filtrih, ji raje spremeni
status.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Na strani je namesto imena koda (npr. `small_stones`) | ni prevoda v tem jeziku in ni angleškega; dodaj ga v **Translations** |
| Nova vrednost se ne pokaže v filtrih | status ni Active, **Show filter** je izklopljen (Beach Types, Waterbodies) ali je filter še v predpomnilniku (do 30 dni; `cache:clear-api`) |
| Nova vrednost nima ikone | ikone so vezane na kodo v kodi spletne strani; potrebuješ razvijalca |
| Po spremembi kode je ikona izginila ali hitri izbor ne deluje | spletna stran išče ikono in hitri izbor po kodi; vrni staro kodo |
| Vrednost je v filtrih na napačnem mestu | vrstni red (`filter_order`) v adminu ni nastavljiv; potrebuješ razvijalca |
| Izbrisana vrednost se še vidi na strani plaže | predpomnilnik (do 8 ur v API, do 1 ure na strani) |
| Statusa ne morem izbrisati | status uporablja vsaj en zapis; to je namenoma |
| Pri šifrantu z več vrednostmi plaži ne najdem polja za izbiro | v obrazcu plaže ga ni – preveri, kje se urejajo |
| Iskanje ali razvrščanje po stolpcu **Name** javi napako | ime je v prevodih, ne v glavni tabeli; ni jasno iz kode, ali deluje – preveri. Išči po kodi |

## Za razvijalce

Viri: `app/Filament/BrAdmin/Resources/*Resource.php` (BeachType, Waterbody, Accessibility, AreaDescription, BayLength,
BeachInclination, BeachLocationType, LocationLength, OccupancyRate, ParkingBeachDistance, ParkingBeachHeight,
ParkingCapacity, RoadToParkingType, SwimmingDepthOfOneM, WaterBodyType, AccommodationAround, CulinaryOffer,
LocationInfrastructure, NaturalLocationCondition, RecommendedTransportType, SportingActivities,
UnderwaterCharacteristic, UserType, Status) in `*/RelationManagers/TranslationsRelationManager.php`. Filtri:
`app/Http/Controllers/Api/V1/FilterController.php` (`active()`, `showInFilter()`, `filter_order`, `filter_title`,
`icon` prve vrednosti). Podatki plaže: `app/Http/Resources/BeachDetailsResource.php`. Polja `show_filter`, `icon`,
`filter_order`, `input_type`: `database/migrations/2025_12_19_000001_add_filter_metadata_to_all_filter_tables.php`.
Ikone na spletu: `web-client/src/lib/utils/beach-badges.ts`, `web-client/src/config/topFilterPicks.ts`,
`web-client/src/components/Map/LeftSidebarFilters.tsx`. Predpomnilnik: `app/Traits/Cacheable.php` (od šifrantov samo
`Waterbody`), `app/Services/CacheService.php`; odprta točka v `docs/deployment/DEPLOYMENT_GUIDE.md` §9.
