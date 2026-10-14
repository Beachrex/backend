# Mediji: fotografije, videi, 360°

> Velja od: 2026-10-14 · Kje: Admin → **Beaches** → **Edit** → razdelek **Media** (nalaganje) ter Admin → **Media** → **Media**, **Media Types**, **Media Collections** · Okolje: dev, uat, prod

## Čemu služi

Vsaka plaža ima fotografije (galerija, parkirišče, infrastruktura …), lahko tudi video in 360° panoramo. Admin hrani
za vsako datoteko en zapis »medij«: kje je datoteka, kakšne vrste je, v katero zbirko spada, ali je glavna in v
katerem vrstnem redu se prikaže.

Pomembno vedeti vnaprej:

- **Fotografije plaže nalagaš pri plaži** (**Beaches** → **Edit** → spodaj **Media**), ne v meniju **Media**.
  Meni **Media → Media** je seznam vseh medijev za pregled in popravke; nalaganja datotek tam ni.
- **Datoteke so v enem skupnem shranjevanju (DigitalOcean Spaces) za dev, uat in prod.** Če na dev izbrišeš
  fotografijo, izbrišeš datoteko, ki jo morda uporablja tudi uat ali prod. Brisanje glej spodaj.

## Kako se slike prikažejo na strani

Originalne datoteke so na Spaces. Spletna stran jih ne nalaga neposredno, ampak prek strežnika slik **Imagor**
(`s3.beachrex.com`), ki iz originala sproti naredi manjšo kopijo prave velikosti (in WebP). Zato:

- naloži največjo dobro fotografijo, ki jo imaš (do 10 MB); pomanjšanja naredi Imagor sam;
- urejevalnik slik v adminu (obrezovanje) spremeni original — uporabljaj ga samo, ko res želiš drugačen izrez.

## Nalaganje fotografij plaže

1. **Beaches** → poišči plažo → **Edit**.
2. Spodaj na strani je razdelek **Media** (seznam medijev te plaže). Klikni **New media**.
3. V polje **Upload Images** povleci fotografije ali klikni in jih izberi.
   - do **20 datotek** naenkrat, vsaka do **10 MB**, samo slike;
   - pod poljem piše, v katero mapo se shranijo (npr. `public/img/beaches/hr-69-o-pag/`);
   - z ikono svinčnika lahko sliko obrežeš (prosto, 16:9, 4:3, 1:1).
4. Stikalo **Our photo: write "© MOJA PLAŽA, d.o.o." into the file (JPEG)** pusti vklopljeno za naše fotografije.
   Izklopi ga za fotografije drugega avtorja (npr. prispevki uporabnikov). Glej »Avtorske pravice« spodaj.
5. Razdelek **Media Details**:
   - **Media type** (obvezno): za fotografije **Image**;
   - **Media collection**: za galerijo plaže **Beach Gallery**. Brez zbirke se fotografija **ne pokaže v galeriji**;
   - **Disk**: pusti **DigitalOcean Spaces**;
   - **Status**: **Active** (samo aktivni mediji se prikažejo na strani).
6. Razdelek **Image Properties** (zaprt; odpri ga po potrebi):
   - **Display Order**: manjša številka = prej v galeriji;
   - **Set as Primary Image**: glavna fotografija plaže.
   Polja **MIME Type**, **File Size (bytes)**, **Width (px)**, **Height (px)** se pri nalaganju izpolnijo sama.
7. Klikni **Create**. Prikaže se »Images uploaded successfully – Uploaded N image(s)«.

Pozor: nastavitve iz koraka 5 in 6 veljajo za **vse** datoteke, ki jih naložiš naenkrat. Če pri nalaganju 10 fotografij
vklopiš **Set as Primary Image**, postanejo glavne vse. Glavno fotografijo zato nastavi pozneje, pri eni sami
(**Edit** v vrstici).

### Imena datotek (samo angleška abeceda)

Sistem vsako naloženo datoteko preimenuje v naključno kodo + končnico (npr. `01JA6Z3K…X9.jpg`). Izvirno ime
(»Plaža Zlatni rat č.jpg«) se ne ohrani. Tako v potih nikoli ni šumnikov, presledkov ali drugih posebnih znakov,
ki so prej povzročali, da se slike niso prikazale. Tudi če pot ali ime ročno vpišeš, sistem ob shranjevanju
šumnike in posebne znake sam zamenja (č→c, š→s, ž→z, ć→c, đ→dj, presledek→`-`).

## Glavna fotografija in vrstni red

| Kaj | Kako deluje |
|---|---|
| **Primary** (glavna) | prikaže se prva v galeriji in je slika plaže na karticah, zemljevidu in pri deljenju povezave |
| **Order** / **Display Order** | znotraj galerije: najprej glavna, nato po številki od najmanjše, pri enakih po datumu nalaganja |
| Plaža brez glavne | kot slika plaže se vzame prva fotografija po vrstnem redu |
| Plaža brez **Beach Gallery** | galerija vzame fotografije iz zbirk Beach Spot Images, Infrastructure Images, Parking Images, Info Images |

Sistem **ne prepreči** več glavnih fotografij hkrati (pod stikalom piše le »Only one image should be marked as
primary«). Ko nastaviš novo glavno, pri stari ročno izklopi **Set as Primary Image**.

Kako zamenjati glavno fotografijo:

1. Pri plaži v razdelku **Media** klikni **Edit** pri trenutni glavni → izklopi **Set as Primary Image** → **Save changes**.
2. **Edit** pri novi → vklopi **Set as Primary Image** → **Save changes**.

### Seznam medijev pri plaži

| Stolpec | Pomen |
|---|---|
| **Preview** | sličica |
| **File name** | ime datoteke |
| **Type** | vrsta medija |
| **Collection** | zbirka |
| **Dimensions** | širina × višina v pikslih |
| **Size** | velikost datoteke |
| **Primary** | glavna fotografija |
| **Order** | vrstni red |
| **Status** | zeleno Active, rdeče vse ostalo |

Filtri: **Type**, **Collection**, **Primary Only**. Akcije v vrstici: **View** (večji predogled), **Edit**, **Delete**.

## Video in 360°

Polje **Upload Images** sprejme samo slike. Videa in 360° panorame v adminu **ne moreš naložiti**.

- **Video**: zapis lahko dodaš ročno (**New media**, brez datoteke v **Upload Images**): v **File Information**
  vpiši **File Name** in **File Path** (pot do že obstoječe datoteke na Spaces), **Media type** = **Video**,
  **Media collection** = **Beach Videos**. Kako datoteko spraviti na Spaces, ni opisano v kodi admina — preveri.
- **360°**: na strani se pokaže virtualni ogled iz mape `img/beaches/<mapa plaže>/vtour/tour.html` (pri plažah iz
  mobilne aplikacije `img/beaches_mobile/…`), če ima plaža vsaj en aktiven medij v zbirki **Beach 360° Spheres**.
  Sam ogled (mapa `vtour`) se v adminu ne nalaga — ni jasno iz kode, kako se pripravi — preveri.

## Brisanje medija

**Delete** pri mediju:

- zapis dobi status **Deleted** in se na strani ne prikaže več;
- **datoteka se takoj izbriše s Spaces**. Ker je Spaces skupen za dev, uat in prod, izgine tudi tam, kjer jo
  uporablja zapis v drugi bazi. Fotografij plaž zato ne briši na dev, če jih uat ali prod še uporabljata.

Če želiš fotografijo samo skriti, ji namesto brisanja nastavi **Status** = **Disabled**.

## Meni Media → Media

Seznam vseh medijev (plaže, države, mesta …), najnovejši na vrhu.

| Stolpec | Pomen |
|---|---|
| **Preview** | sličica |
| **File name** | ime datoteke |
| **Type** | vrsta (Image, Video, 360° Sphere …) |
| **Collection** | zbirka |
| **Entity** | komu pripada (npr. `App\Models\Beach`) |
| **Primary** | glavna |
| **Status** | status |

Filtri: **Media type**, **Media collection**, **Status**.

Obrazec (**Edit**) pokaže surove podatke: **File name**, **File path**, **Media type**, **Media collection**,
**Entity Type**, **Entity ID**, **Disk**, **Mime type**, **Size (bytes)**, **Width**, **Height**, **Primary Media**,
**Order**, **Status**. Tu ne nalagaš datotek; sprememba **File path** datoteke ne premakne, samo spremeni, kje jo
stran išče. Za običajno delo uporabljaj razdelek **Media** pri plaži.

## Meni Media → Media Types (vrste medijev)

Šifrant vrst: **Image**, **Video**, **360° Sphere**, **Icon**, **Banner**, **Document**.

| Polje | Pomen |
|---|---|
| **Code** | interna koda (`image`, `video`, `sphere_360` …) — **ne spreminjaj**, koda se uporablja v programu |
| **Name** | ime, ki ga vidiš v adminu |
| **Allowed MIME Types** | dovoljene vrste datotek (npr. `image/jpeg`) |
| **Max File Size (bytes)** | največja velikost |
| **Status** | Active / … |

Stolpci seznama: **Code**, **Name**, **Media count** (koliko medijev je te vrste), **Status**.
Omejitvi **Allowed MIME Types** in **Max File Size** veljata za nalaganje prek API (mobilna aplikacija), ne za
nalaganje v adminu (tam velja 10 MB in samo slike).

## Meni Media → Media Collections (zbirke)

Zbirka pove, kje na strani se medij prikaže: **Beach Gallery**, **Beach Videos**, **Beach 360° Spheres**,
**Parking Images**, **Beach Spot Images**, **Infrastructure Images**, **Info Images**, **Country Hero Image**,
**Continent Hero Image**, **Promotional Banners**, **Feature Icons**, **Beach Documents** …

| Polje | Pomen |
|---|---|
| **Code** | interna koda (`beach_gallery`, `beach_videos`, `beach_360_sphere` …) — **ne spreminjaj** |
| **Name** | ime v adminu |
| **Description** | opis |
| **Allowed Media Type Codes** | katere vrste medijev spadajo vanjo (kode iz Media Types) |
| **Max Items per Entity** | največ medijev na plažo/državo (prazno = brez omejitve) |
| **Status** | Active / … |

Stolpci seznama: **Code**, **Name**, **Media count**, **Max items**, **Status**.
Tudi te omejitve preverja samo nalaganje prek API, ne admin.

## Avtorske pravice v fotografijah (IPTC)

Fotografije na Beachrex so delo podjetja MOJA PLAŽA, d.o.o. (pogoji uporabe §6). Ob nalaganju z vklopljenim
stikalom **Our photo: …** sistem v datoteko JPEG zapiše podatke o avtorju (IPTC): avtor »MOJA PLAŽA, d.o.o.«,
vir »Beachrex« in »© <leto> MOJA PLAŽA, d.o.o.«. Slika se pri tem ne spremeni (ni ponovnega stiskanja).
PNG in WebP ostaneta brez teh podatkov.

Kdor prenese original, vidi lastnika. Ali tudi pomanjšane kopije iz Imagorja obdržijo te podatke, je odvisno od
nastavitev Imagorja — ni jasno iz kode — preveri.

### Ukaz `media:embed-copyright` (stare fotografije)

Fotografije, naložene pred to funkcijo, podatkov o avtorju nimajo. Ukaz jih doda naknadno.

**Kdaj:** ko se odločiš ti; priporočilo je **na prod po zagonu**. Ukaz prepiše originalne datoteke na Spaces
(skupne vsem okoljem), zato ga je dovolj pognati enkrat.

Kaj naredi:

- obdela samo fotografije plaž v mapi `img/beaches/` (naložene v adminu) in samo JPEG;
- mape `img/beaches_mobile/` ne obdela (tam so lahko fotografije drugih avtorjev);
- datotek, ki podatek že imajo, ne spreminja — ukaz lahko varno ponoviš;
- vsako spremenjeno datoteko prepiše na mestu in v bazi popravi velikost.

Ukaz poganjaš na strežniku. Pred ukaz dodaj (za prod):

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.prod exec -T api_prod
```

| Korak | Ukaz | Kaj naredi |
|---|---|---|
| 1 | `php artisan media:embed-copyright --dry-run` | samo prešteje, nič ne zapiše |
| 2 (neobvezno) | `php artisan media:embed-copyright --limit=20` | zapiše v prvih 20 datotek; preveri nekaj fotografij na strani |
| 3 | `php artisan media:embed-copyright` | zapiše v vse preostale |

Izpis: `Checked: N · written: N · already had it: N · missing on Spaces: N · not JPEG: N`
(pri `--dry-run` z oznako `[dry run]` in »would write«). »missing on Spaces« pomeni, da zapis v bazi kaže na
datoteko, ki je ni.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Fotografija je naložena, a je ni v galeriji | ni izbrana **Media collection** = **Beach Gallery**, ali **Status** ni Active |
| Napačna slika na kartici plaže | glavna je druga fotografija ali jih je več; preveri stolpec **Primary** |
| Več glavnih fotografij | nastale pri nalaganju več datotek z vklopljenim **Set as Primary Image**; pri odvečnih izklopi |
| Ne morem naložiti videa | admin sprejme samo slike; glej »Video in 360°« |
| Datoteka je prevelika | največ 10 MB; pomanjšaj fotografijo pred nalaganjem |
| Fotografija je izginila tudi na drugem okolju | izbrisana je bila na dev/uat/prod — Spaces je skupen |
| Sprememba se na strani ne vidi | stran se osveži v največ 1 uri; po potrebi `cache:clear-api --type=all` ([splosno.md](splosno.md)) |
| **Preview** je prazen | ni jasno iz kode — preveri (naslov predogleda v adminu je odvisen od `DO_SPACES_CDN_ENDPOINT_ADMIN`) |

## Za razvijalce

Nalaganje: `app/Filament/BrAdmin/Resources/BeachResource/RelationManagers/MediaRelationManager.php`.
Viri: `MediaResource`, `MediaTypeResource`, `MediaCollectionResource` (+ `Pages/`). Model `App\Models\Media`
(brisanje izbriše datoteko na disku). ASCII poti: TASK-002, `App\Support\AsciiPath`,
`App\Models\Traits\HasAsciiPaths`, ozadje `docs/runbooks/ascii-storage-paths.md`. IPTC: `App\Support\ImageCopyright`,
ukaz `app/Console/Commands/EmbedImageCopyright.php`. Galerija in glavna slika v API: `App\Http\Resources\BeachResource`,
`App\Support\BeachGallery`. Odprto: `HANDOFF.md` → »Copyright backfill«.
