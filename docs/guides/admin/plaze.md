# Plaže

> Velja od: 2026-10-14 · Kje: Admin → **Beaches** · Okolje: dev, uat, prod (dock-dev / dock-uat / dock.beachrex.com)

## Čemu služi

Tu urejaš plaže (tudi bazene, kopališča in terme, ki so v isti tabeli). Za vsako plažo nastaviš:

- osnovne podatke: mapo za slike, lokacijo (GPS), status, državo, regijo, celino;
- lastnosti iz šifrantov (tip plaže, dostop, parkiranje, globina …) in podatke o bazenih;
- **prevode** (ime, opis in slug v vsakem jeziku);
- **fotografije** (mediji).

Spletna stran in mobilna aplikacija prikažeta samo plaže s statusom **Active**.

## Kje je

Admin → **Beaches** (v meniju je brez skupine, ikona s kupom pravokotnikov).

Plažo najdeš tudi z iskalnim poljem na vrhu admina (globalno iskanje). Išče po imenu plaže (vsi jeziki), **Folder Name**,
imenu države in imenu regije.

## Seznam plaž (stran »Beaches«)

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Image** | glavna fotografija plaže (aktivna in označena kot glavna). Če je ni, se prikaže nadomestna slika. |
| **Beach Name** | angleško ime; če angleškega prevoda ni, prvo ime, ki obstaja; sicer `N/A` |
| **Country** | država (angleško ime) |
| **Region** | regija (angleško ime); stolpec lahko skriješ |
| **Continent** | celina |
| **Waterbody** | morje / vodno telo |
| **Beach Type** | tip plaže; stolpec lahko skriješ |
| **GPS** | kljukica, če ima plaža shranjeno lokacijo (koordinate) |
| **Media** | kljukica, če ima plaža vsaj en medij (fotografijo, video …), ne glede na status medija |
| **Translations** | število prevodov, prikazano kot `n/14`. Zelena = 14, rumena = 10–13, rdeča = manj kot 10. **Pozor:** podprtih jezikov je 19, stolpec pa še vedno računa s 14 (glej Pogoste težave). |
| **Status** | status plaže. Zelena = Active, rumena = Inactive, rdeča = Deleted, siva = vse drugo (npr. Disabled, Draft). |

### Filtri

| Filter | Možnosti | Kaj pokaže |
|---|---|---|
| **Status** | vsi statusi iz šifranta | plaže z izbranim statusom |
| **GPS Location** | All beaches / Has GPS coordinates / Missing GPS coordinates | plaže z ali brez lokacije |
| **Media** | All beaches / Has photos/videos / Missing photos/videos | plaže z ali brez medijev |
| **Translations** | All beaches / Complete (all 14 languages) / Missing translations | točno 14 prevodov / manj kot 14. Plaža z vsemi 19 jeziki pri teh dveh možnostih **ne** pride v nobeno skupino. |
| **Country** | države | plaže v izbrani državi |
| **Region** | regije | plaže v izbrani regiji |
| **Continent** | celine | plaže na izbrani celini |

### Gumbi zgoraj desno

| Gumb | Kaj naredi |
|---|---|
| **Export to Excel** | prenese datoteko `beaches-<datum-čas>.xlsx` s plažami, ki ustrezajo trenutnim filtrom. Stolpci: ID, Beach Name (EN), Folder Name, Country, Region, City, Latitude, Longitude, Status, Beach Type, Accessibility, Occupancy Rate, Waterbody, User, Translations Count, Has GPS, Has Media, Created At, Updated At. |
| **New beach** | odpre obrazec za novo plažo |

### Akcije v vrstici

| Gumb | Kaj naredi |
|---|---|
| **Edit** | odpre urejanje plaže |

### Akcije za več plaž naenkrat

Označi plaže (kvadratki levo), nato odpri meni akcij nad tabelo.

| Gumb | Kaj naredi |
|---|---|
| **Change Status** | izbereš **New Status**; vse izbrane plaže dobijo ta status. Obvestilo: »Status Updated«. |
| **Change Beach Type** | izbereš **New Beach Type** za vse izbrane |
| **Change Accessibility** | izbereš **New Accessibility** za vse izbrane |
| **Change Occupancy Rate** | izbereš **New Occupancy Rate** za vse izbrane |
| **Change Waterbody** | izbereš **New Waterbody** za vse izbrane |
| **Translate to All Languages** | z umetno inteligenco doda manjkajoče prevode izbranih plaž (gumb **Start Translation**). Lahko traja nekaj minut. Podrobnosti v razdelku »Prevodi«. |
| **Delete selected** | trajno izbriše izbrane plaže (glej »Brisanje«) |

## Nova plaža — korak za korakom

1. Admin → **Beaches** → **New beach**.
2. **Folder Name** (obvezno): ime mape za slike, oblika `država-id-regija-kraj`, npr. `hr-69-o-pag`.
   Šumnike in presledke sistem ob shranjevanju sam zamenja (č/ć/š/ž/đ → c/c/s/z/dj, presledek → `-`).
3. **Status** (obvezno): privzeto prvi status (Active). Če plaža še ni pripravljena, izberi drug status (npr. Draft),
   sicer bo takoj vidna na strani.
4. Vpiši **Latitude** in **Longitude** (decimalno, npr. `44.4459` in `15.0521`).
5. Izberi **Country**, **Region**, **Continent** in ostale lastnosti (tabela spodaj). Nobena ni obvezna.
6. Klikni **Create**. Odpre se stran za urejanje te plaže.
7. Plaža še **nima imena**. Spodaj v zavihku **Translations** dodaj vsaj angleški prevod (glej »Prevodi«).
8. Po želji klikni **Translate Beach**, da AI doda ostale jezike.
9. V zavihku **Media** naloži fotografije.

Gumb **Create & create another** shrani plažo in odpre prazen obrazec za naslednjo.

## Urejanje plaže

Admin → **Beaches** → **Edit** pri plaži. Naslov strani je `Edit Beach: <ime>`.

Stran ima tri dele:

1. **Obrazec** z osnovnimi podatki in lastnostmi (en sam del, brez zavihkov). Shraniš z **Save changes**.
2. Pod obrazcem zavihka **Translations** in **Media**. Spremembe tam se shranijo takoj, ločeno od **Save changes**.
3. Na dnu zemljevid **GPS Location Map**.

Zgoraj desno sta gumba **Translate Beach** in **Delete**.

### Polja obrazca

| Polje | Pomen |
|---|---|
| **Beach Name** | samo prikaz. Ime urejaš v zavihku **Translations** (tako piše tudi pod poljem). Pri novi plaži piše »New Beach«, pri plaži brez prevoda »No name set«. |
| **Folder Name** | mapa za slike na strežniku (`public/img/beaches/<Folder Name>/`). Obvezno. Šumniki in presledki se ob shranjevanju zamenjajo. Glej opozorilo spodaj. |
| **User** | uporabnik, ki mu plaža pripada (avtor). Ni jasno iz kode, kje se to prikaže na strani — preveri. |
| **Latitude** / **Longitude** | koordinate plaže. Iz njih baza ob shranjevanju sama izračuna lokacijo (**Location**). |
| **Status** | obvezno. Samo **Active** je vidna na strani, v seznamih, na zemljevidu in v iskanju. |
| **Country** | država (prikazana z angleškim imenom) |
| **Region** | regija; v oklepaju je koda države. Seznam pokaže **vse** regije, ne samo regij izbrane države — pazi, da izbereš pravo. |
| **Continent** | celina |
| **Location** | samo prikaz (PostGIS polje, ni ga mogoče urejati). Nastavi se samo iz Latitude/Longitude. |
| **Waterbody** | morje / jezero / vodno telo |
| **Beach type** | tip plaže (peščena, prodnata …) |
| **Area description** | opis okolice |
| **Accessibility** | dostopnost |
| **Road to parking type** | vrsta ceste do parkirišča |
| **Parking** | parkirišče: Undefined / No / Yes |
| **Parking capacity** | velikost parkirišča |
| **Parking beach distance** | razdalja parkirišče–plaža |
| **Parking beach height** | višinska razlika parkirišče–plaža |
| **Location length** | dolžina plaže |
| **Bay length** | dolžina zaliva |
| **Beach inclination** | naklon plaže |
| **Swimming depth of one m** | kje je globina 1 m |
| **Occupancy rate** | zasedenost |
| **Water body type** | vrsta vode |
| **Island** | otok, na katerem je plaža |
| **Indoor pools count** / **Outdoor pools count** | število notranjih / zunanjih bazenov |
| **Indoor area m2** / **Outdoor area m2** | notranja / zunanja površina v m² |
| **Has swimming pool** | ima bazen |
| **Has indoor swimming lanes** / **Has outdoor swimming lanes** | plavalne steze notri / zunaj |
| **Has indoor children pool** / **Has outdoor children pool** | otroški bazen notri / zunaj |
| **Has indoor diving board** / **Has outdoor diving board** | skakalnica notri / zunaj |
| **Indoor water slides count** / **Outdoor water slides count** | število toboganov notri / zunaj |
| **Has thermal pool** | termalni bazen |
| **Has indoor climbing wall** / **Has outdoor climbing wall** | plezalna stena notri / zunaj |
| **Has indoor lazy river** / **Has outdoor lazy river** | »lena reka« notri / zunaj |
| **Has indoor wave pool** / **Has outdoor wave pool** | bazen z valovi notri / zunaj |
| **Has indoor whirlpool** / **Has outdoor whirlpool** | masažni bazen notri / zunaj |
| **Has indoor surfing** / **Has outdoor surfing** | surfanje notri / zunaj |
| **Has sauna** | savna |
| **Has wellness** | wellness |

Seznami (Waterbody, Beach type, Accessibility …) prihajajo iz šifrantov v meniju **Beach Characteristics** in
podobnih (navodila `sifranti.md`).

**Česa tu ni:** mesto (City), reka, jezero, video, spletna kamera, »samo s čolnom« in dodatne lastnosti (namestitev v
okolici, kulinarika, infrastruktura, naravne danosti, priporočen prevoz, športne aktivnosti, podvodne značilnosti,
tip obiskovalca, tip lokacije). Ti podatki obstajajo v bazi, a jih ta obrazec ne ureja. Ni jasno iz kode, kje se v
adminu urejajo — preveri. Spletne kamere: `spletne-kamere.md`.

> **Opozorilo — Folder Name pri obstoječi plaži:** sprememba **ne premakne** že naloženih slik. Stare slike ostanejo
> na stari poti in delujejo naprej, nove gredo v novo mapo. 360° ogled (`img/beaches/<Folder Name>/vtour/…`) pa se
> išče po trenutnem Folder Name, zato po spremembi ne bo več deloval, dokler se mapa ne premakne. Folder Name pri
> obstoječih plažah raje ne spreminjaj.

## Lokacija in zemljevid

- Vpiši **Latitude** in **Longitude** in klikni **Save changes**.
- Baza iz njiju sama nastavi lokacijo. Če eno od njiju izbrišeš, se izbriše tudi lokacija (plaža izgine z zemljevida in
  iz iskanja »v bližini«).
- Na dnu strani **GPS Location Map** pokaže točko na zemljevidu (OpenStreetMap) in gumba **Open in Google Maps** in
  **Open in OpenStreetMap**. Zemljevid kaže **shranjene** koordinate; po spremembi najprej shrani.
- Brez koordinat piše: »No GPS coordinates set. Add latitude and longitude to see the map.«
- Plaže brez lokacije najdeš s filtrom **GPS Location** → **Missing GPS coordinates**.

## Statusi in vidnost

| Status | Kaj pomeni za stran |
|---|---|
| **Active** | plaža je vidna: stran plaže, seznami, zemljevid, iskanje |
| vsi drugi (npr. Disabled, Draft, Deleted) | plaže ni nikjer na strani; stran plaže vrne »ni najdeno« (404); odstrani se iz iskalnika |

Statusi so iz skupnega šifranta **System → Statuses**. Za skrivanje plaže uporabi drug status, ne brisanja —
status lahko kadarkoli vrneš na Active.

Več plažam hkrati spremeniš status z akcijo **Change Status**.

## Prevodi (zavihek »Translations«)

Vsak prevod ima jezik, ime, opis in slug (del povezave). Na plažo je lahko **en prevod na jezik**.

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Language** | jezik (angleščina zelena, slovenščina modra). Za da, pt, nl, el in sq je prikazana samo koda (npr. `DA`). |
| **Name** | ime plaže v tem jeziku |
| **Description** | opis (prvih 50 znakov) |
| **Slug** | del povezave do strani plaže v tem jeziku |

### Dodajanje ali urejanje prevoda

1. V zavihku **Translations** klikni **New beach translation** (ali **Edit** pri obstoječem).
2. **Language** (obvezno): izberi jezik. Ponujenih je vseh 19 podprtih jezikov: en, sl, de, hr, fr, es, it, ua, ru,
   pl, cs, hu, bg, sk, da, pt, nl, el, sq.
3. **Name** (obvezno): ime plaže v tem jeziku.
4. **Description**: opis.
5. **Slug (URL-friendly)**: del povezave, npr. `zlatni-rat`. Samo male črke brez šumnikov, številke in `-`.
   Admin slug-a **ne** naredi sam iz imena — vpisati ga moraš. Ni jasno iz kode, kako se plaža prikaže v jeziku brez
   sluga — preveri; zato slug vedno vpiši.
6. Klikni **Create** (oz. **Save changes**).

Prevod izbrišeš z **Delete** v vrstici, več naenkrat z **Delete selected**.

> **Pravilo lastnika: slugov ne spreminjaj.** Slug je del naslova strani. Če ga spremeniš, stara povezava (Google,
> deljene povezave, zaznamki) ne deluje več. Admin pri ročni spremembi **ne** naredi preusmeritve. Obstaja le tabela
> preusmeritev, ki jo polni orodje **System → Fix Duplicate Slugs** (`podvojeni-slugi.md`). Tudi AI orodja slugov
> nikoli ne spreminjajo.

### Samodejni prevod z AI (»Translate Beach«)

Gumb **Translate Beach** na vrhu strani za urejanje. Značka na gumbu kaže **Complete** ali **`n` missing**.

1. Klikni **Translate Beach** → **Translate Now**.
2. AI (OpenAI) za vsak manjkajoči jezik naredi ime in opis ter slug iz prevedenega imena.
3. Obvestilo: »Translation Completed!« (koliko novih jezikov) ali »No Translation Needed«.

Pomembno:

- Prevaja **samo manjkajoče** jezike. Obstoječih prevodov ne spreminja.
- Izvor je **angleški** prevod, če ga ni, **hrvaški**. Plaža brez enega od teh dveh se ne prevede.
- Prevaja **samo plaže s statusom Active**. Pri plaži z drugim statusom se ne zgodi nič (pokaže »No Translation
  Needed«). Najprej nastavi Active ali prevedi ročno.
- Ista pravila veljajo za akcijo **Translate to All Languages** na seznamu.
- Prevodi so strojni; preveri vsaj slovenskega in angleškega.

## Fotografije (zavihek »Media«)

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Preview** | sličica |
| **File name** | ime datoteke |
| **Type** | vrsta medija (slika, video, 360°) |
| **Collection** | zbirka (npr. galerija, parkirišče, infrastruktura) |
| **Dimensions** | širina × višina v pikslih |
| **Size** | velikost datoteke |
| **Primary** | glavna slika plaže |
| **Order** | vrstni red (manjša številka = prej) |
| **Status** | zelena = Active, rdeča = vse drugo |

Filtri: **Type**, **Collection**, **Primary Only**.

### Nalaganje fotografij

1. V zavihku **Media** klikni **New media**.
2. **Upload Images**: povleci ali izberi slike. Največ 20 naenkrat, vsaka do 10 MB, samo slike. Sliko lahko pred
   nalaganjem obrežeš (urejevalnik: prosto, 16:9, 4:3, 1:1). Pod poljem piše, v katero mapo se shranijo.
3. **Our photo: write "© MOJA PLAŽA, d.o.o." into the file (JPEG)**: privzeto vklopljeno. Sistem v JPEG zapiše avtorske
   pravice podjetja. Izklopi za fotografije drugega avtorja (npr. prispevki uporabnikov).
4. **Media Details**:
   - **Media type** (obvezno): vrsta (za fotografije slika).
   - **Media collection**: zbirka. Za galerijo plaže izberi galerijo (`beach_gallery`). Če plaža nima nobene slike v
     galeriji, stran pokaže slike iz zbirk spot, infrastruktura, parkirišče, info.
   - **Disk**: privzeto **DigitalOcean Spaces**. Pusti tako.
   - **Status** (obvezno): privzeto Active. Na strani so vidni samo aktivni mediji.
5. **Image Properties** (zaprto): **Display Order** in **Set as Primary Image**. Te nastavitve veljajo za **vse**
   slike v tem nalaganju. Če vklopiš Primary pri nalaganju več slik, postanejo vse »glavne«. Glavno sliko raje nastavi
   pozneje z **Edit** pri eni sliki.
6. Klikni **Create**. Obvestilo: »Images uploaded successfully — Uploaded `n` image(s)«.

Datoteke dobijo naključno ime (npr. `01J….jpg`), mapa je `public/img/beaches/<Folder Name>/`. Velikost in dimenzije se
izpolnijo same.

### Urejanje in brisanje

- **View**: ogled medija.
- **Edit**: sprememba podatkov (zbirka, status, vrstni red, glavna slika …). Nalaganje tu ni mogoče.
  **Set as Primary Image**: »Only one image should be marked as primary« — sistem tega ne preverja, zato pred
  vklopom pri novi sliki izklopi staro.
- **Delete** / **Delete selected**: medij dobi status Deleted **in datoteka se izbriše s strežnika**. Tega ne moreš
  razveljaviti iz admina. Če želiš sliko samo skriti, ji v **Edit** nastavi drug status.

Podrobneje o medijih: `mediji.md`.

## Brisanje plaže

**Delete** (na strani urejanja) ali **Delete selected** (na seznamu) plažo **trajno** izbriše:

- skupaj z njo se izbrišejo prevodi, dodatne lastnosti, preusmeritve slugov in spletne kamere te plaže;
- plaža se odstrani iz iskalnika;
- zapisov o medijih koda ob tem ne briše in tudi datotek ne. Ni jasno iz kode, ali kaj drugega počisti njene
  fotografije — preveri.

Brisanja ni mogoče razveljaviti. Če plaže ne želiš na strani, ji raje nastavi status (npr. Disabled).

## Kaj se zgodi po shranjevanju

Ob vsakem shranjevanju plaže (**Create**, **Save changes** ali akcija za več plaž) sistem:

1. **Folder Name** pretvori v znake brez šumnikov (samo če si ga spremenil).
2. Iz Latitude/Longitude izračuna lokacijo.
3. Posodobi **iskalnik** (Meilisearch): aktivna plaža se doda/posodobi, neaktivna se odstrani.
4. Počisti del predpomnilnika API: števce plaž, podatke o celini, državi, vodnem telesu in tipih plaž.

Kdaj sprememba vidna na strani:

| Kaj | Kdaj |
|---|---|
| Spletna stran plaže | stran je predpomnjena **do 1 ure**; potem jo spletna stran znova prebere iz API |
| Podrobnosti plaže v API (`beach-details`) | predpomnjene **do 8 ur**. Shranjevanje v adminu tega predpomnilnika **ne** počisti (koda ga briše samo pri spletnih kamerah). V najslabšem primeru je sprememba vidna šele po ~9 urah. |
| Seznami plaž v API (seznami, zemljevid, najbolj obiskane, noga) | predpomnjeni od 24 ur do 30 dni. V kodi nisem našel, da bi jih shranjevanje plaže počistilo — preveri. Ob vsakem deployu se ves predpomnilnik počisti. |
| Iskanje | takoj ali v nekaj sekundah (odvisno od nastavitve čakalne vrste) |

Spremembe **prevodov** (zavihek Translations) ne počistijo nobenega predpomnilnika in **ne** posodobijo iskalnika
(ta se posodobi šele, ko plažo shraniš z **Save changes**). Po spremembi imena zato klikni še **Save changes** na
plaži.

Če mora biti sprememba vidna takoj, razvijalca prosi za čiščenje predpomnilnika (`php artisan cache:clear-api
--type=all`; spletna stran se kljub temu osveži v največ eni uri).

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Nova plaža v seznamu nima imena (`N/A`) | ime je v prevodih; v zavihku **Translations** dodaj vsaj angleški prevod |
| **Translate Beach** pravi »No Translation Needed«, čeprav prevodi manjkajo | plaža ni Active ali nima angleškega ali hrvaškega prevoda |
| Značka na **Translate Beach** kaže negativno število (npr. `-5 missing`) ali stolpec **Translations** `19/14` v rumeni barvi | admin še računa s 14 jeziki, podprtih je 19. Plaža je v resnici popolna. Filter **Translations** zato ne loči plaž z vsemi 19 jeziki. Popravek je naloga za razvijalca. |
| Napaka pri dodajanju prevoda | za ta jezik prevod že obstaja (en prevod na jezik); uredi obstoječega |
| Plaža se ne prikaže na strani | status ni Active, ni prevoda/sluga v tem jeziku ali pa stran še kaže staro predpomnjeno verzijo (glej zgoraj) |
| Plaže ni na zemljevidu ali v »v bližini« | manjka Latitude ali Longitude (filter **GPS Location**) |
| Stara povezava do plaže ne deluje več | slug je bil spremenjen; vrni starega |
| Na seznamu ni slike (**Image**), čeprav ima plaža fotografije | nobena aktivna slika ni označena kot glavna (**Set as Primary Image**) |
| Po spremembi **Folder Name** ne deluje 360° ogled | 360° ogled se išče v mapi po trenutnem Folder Name; vrni staro ime |
| V seznamu **Region** je ista regija večkrat | seznam kaže regije vseh držav; poglej kodo države v oklepaju |
| Status v seznamu je siv | barve so nastavljene samo za Active, Inactive in Deleted; drugi statusi (Disabled, Draft …) so sivi, kar je pričakovano |

## Za razvijalce

- Admin: `app/Filament/BrAdmin/Resources/BeachResource.php`, `…/BeachResource/Pages/{ListBeaches,CreateBeach,EditBeach}.php`,
  `…/BeachResource/RelationManagers/{TranslationsRelationManager,MediaRelationManager}.php`,
  `…/BeachResource/Widgets/BeachMapWidget.php` (+ `resources/views/filament/widgets/beach-map-widget.blade.php`).
- Izvoz: `app/Exports/BeachesExport.php`.
- Model: `app/Models/Beach.php` (`Cacheable` → `clearModelCache()`, Scout `Searchable` z `shouldBeSearchable()` =
  `status_id === 1`, `HasAsciiPaths` za `folder_name`), `app/Models/BeachTranslation.php` (brez dogodkov),
  `app/Models/Media.php` (soft delete + brisanje datoteke, `HasAsciiPaths`).
- ASCII poti: `app/Models/Traits/HasAsciiPaths.php`, `app/Support/AsciiPath.php` (TASK-002). Copyright: `app/Support/ImageCopyright.php`.
- Lokacija: trigger `beach_location_trigger` (migracija `2025_10_23_182615_add_spatial_columns_to_beach_table.php`).
- AI prevod: `app/Console/Commands/TranslateBeaches.php` (`beaches:translate`, samo `status_id = 1`, vir en/hr, OpenAI
  `gpt-4o-mini`, slug = `Str::slug(name)`).
- Predpomnilnik: `app/Services/CacheService.php` (`BEACH_DETAILS_TTL` 8 h), `BeachDetailsController` (ključ
  `beach_details:{locale}:{id|slug}`), `BeachController` (`beaches:index:*`, `beaches:map_bounds`, …); spletna stran
  `revalidate = 3600` v `web-client/src/app/[locale]/beaches/[continent]/[country]/[...segments]/page.tsx`.
- Znane pomanjkljivosti: števec `14` v `BeachResource` (stolpec/filter Translations) in `EditBeach` (Translate Beach)
  namesto 19 (`SetLocaleFromHeader::SUPPORTED_LOCALES`); zastave v `TranslationsRelationManager` samo za 14 jezikov;
  shranjevanje plaže/prevoda ne počisti `beach_details:*` in `beaches:*`.
