# Značke (Badges)

> Velja od: 2026-10-14 · Kje: Admin → **Badges** (brez skupine v meniju) · Okolje: vsa (dock-dev, dock-uat,
> dock.beachrex.com)

## Čemu služi

Značka je kratka oznaka z ikono, ki opiše posebnost kraja, npr. »Krško jagnje« ali »Žlahtina«. Značke se prikažejo v
pasici (banner) strani **države, regije, mesta ali otoka** – kot vrsta zaobljenih oznak z ikono in imenom (na
telefonu do 6 vidnih, na večjem zaslonu vse).

Značko najprej ustvariš tukaj (koda, ikona, status, prevodi), potem jo **pripneš** kraju na strani tega kraja
(razdelek **Badges** v **Countries**, **Regions**, **Cities** ali **Islands**, glej [geografija.md](geografija.md)).
Ena značka je lahko pripeta več krajem.

Značke na strani plaže (tip plaže, družinska plaža, parkirišče …) **niso** te značke; tiste nastanejo iz lastnosti
plaže (glej [sifranti.md](sifranti.md)).

## Dodaj značko

1. Admin → **Badges** → **New badge**.
2. Izpolni obrazec:

   | Polje | Pomen |
   |---|---|
   | **Badge Code** | stalna koda, obvezno, do 100 znakov, enolična (npr. `krk-lamb`, `zlahtina-wine`) |
   | **Icon** | izbira ikone iz nabora Font Awesome (solid, regular, brands); neobvezno |
   | **Status** | status; privzeto prvi (Active) |

3. Klikni **Create**.
4. V razdelku **Translations** pod obrazcem klikni **New badge translation** in za vsak jezik vpiši:
   **Language**, **Name** (ime značke, obvezno) in **Slug (URL-friendly)** (neobvezno). En prevod na jezik.
5. Odpri državo, regijo, mesto ali otok, v razdelku **Badges** klikni **Attach**, izberi značko in potrdi.

## Uredi značko

1. V seznamu klikni **Edit**.
2. Spremeni polja in klikni **Save changes**.
3. Prevode urejaš v razdelku **Translations** (**Edit** / **Delete** v vrstici; shranijo se takoj).

## Seznam

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Code** | koda značke |
| **Badge Name** | angleško ime (iz prevodov; če ga ni, koda) |
| **Icon** | izbrana ikona |
| **Translations** | število prevodov, prikazano kot `N/14` (glej opombo spodaj) |
| **Status** | zelen = Active, rdeč = Inactive, siv = drugo |
| **Created at**, **Updated at** | skrita; vklopiš ju pri ikoni stolpcev |

Privzeto razvrščeno po kodi.

### Filtri

| Filter | Možnosti |
|---|---|
| **Status** | status značke |
| **Translations** | **All badges** · **Complete (all 14 languages)** (točno 14 prevodov) · **Missing translations** (manj kot 14) |

Opomba: števec in filter računata s **14** jeziki, jezikov pa je **19** (en, sl, de, hr, fr, es, it, ua, ru, pl, cs,
hu, bg, sk, da, pt, nl, el, sq). Značka z vsemi 19 prevodi je v stolpcu `19/14` in je filter **Complete** ne najde.
Za preverjanje, ali so vsi jeziki prevedeni, odpri značko in poglej razdelek **Translations**.

### Akcije

| Gumb | Kaj naredi |
|---|---|
| **New badge** (zgoraj desno) | nova značka |
| **Edit** v vrstici | uredi značko |
| izbira več vrstic → **Change Status** | izbereš **New Status** in ga nastaviš vsem izbranim značkam; sporočilo »Status Updated« |
| izbira več vrstic → **Delete selected** | izbriše izbrane značke |
| **Delete** (v urejanju, zgoraj desno) | izbriše značko |

## Kaj se pokaže na spletni strani

Za vsako pripeto značko API vrne kodo, ime in slug v jeziku obiskovalca (če prevoda ni, angleško; če ni niti
angleškega, `Unknown`) in vrednost polja **Icon**.

- Spletna stran pokaže značko le, če ima ime.
- Ikono stran prikaže kot **sliko** iz CDN (vrednost **Icon** obravnava kot pot do datoteke). Ikona Font Awesome iz
  izbirnika se shrani kot ime (npr. `fas-wine-glass`), ne kot pot do slike, zato se na strani verjetno ne prikaže
  pravilno – ni jasno iz kode, preveri na dev.
- Ali stran skrije značke s statusom, ki ni Active, ni jasno iz kode – preveri. API status naloži, a ga v odgovoru
  ne uporabi.

## Kaj se zgodi po shranjevanju

- **Admin:** spremembe so v bazi takoj.
- **API:** značke so del podatkov strani kraja, ki se v API hranijo do **30 dni**. Shranjevanje značke ali pripenjanje
  predpomnilnika **ne počisti**; sprememba se pokaže po naslednjem deployu (deploy požene `cache:clear`) ali ko na
  strežniku poženeš `php artisan cache:clear-api` (pred ukaz dodaj
  `docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje>`).
- **Spletna stran:** stran države se dodatno hrani do 1 ure (ISR).

## Brisanje

Ko izbrišeš značko, baza samodejno izbriše tudi vse njene prevode in jo odpne od vseh držav, regij, mest in otokov.
Kraji ostanejo. Brisanje se ne da razveljaviti; če značke ne želiš začasno kazati, ji raje odpni kraj (**Detach**).

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Značke ni na strani kraja | ni pripeta temu kraju (razdelek **Badges** pri kraju), nima imena (prevoda) ali je še v predpomnilniku (do 30 dni; `cache:clear-api`) |
| Na strani je ime značke v angleščini | ni prevoda v jeziku obiskovalca; dodaj ga v **Translations** |
| Na strani piše `Unknown` | značka nima niti angleškega prevoda |
| Ikona se na strani ne prikaže | glej zgoraj: ikona iz izbirnika ni pot do slike – preveri na dev |
| Filter **Complete (all 14 languages)** ne najde popolnoma prevedenih značk | filter šteje točno 14 prevodov, jezikov je 19 |
| Pri shranjevanju: koda že obstaja | **Badge Code** mora biti enolična |
| **Attach** pri kraju javi napako ali ne najde značke | ni jasno iz kode, ali iskanje po imenu deluje – preveri na dev |

## Za razvijalce

Viri: `app/Filament/BrAdmin/Resources/BadgeResource.php`, `BadgeResource/RelationManagers/TranslationsRelationManager.php`,
`*Resource/RelationManagers/BadgesRelationManager.php` (Country, Region, City, Island), `app/Models/Badge.php`,
`app/Http/Resources/BadgeResource.php`. Spletna stran: `web-client/src/components/BannerSection/BottomBadges.tsx`
(ikona prek `buildCdnUrl(icon, 'icon')`). Tuji ključi s `cascadeOnDelete`:
`database/migrations/2025_11_03_17*_create_badge_*` in `2025_11_03_181406_create_badge_city_table.php`. Število
jezikov 14 je zapisano v `BadgeResource::table()` (stolpec in filter **Translations**); `SUPPORTED_LOCALES` ima 19.
