# Članki

> Velja od: 2026-10-14 · Kje: Admin → **Articles** (v meniju brez skupine) · Okolje: dev, uat, prod

## Čemu služi

Članki so kratka besedila o krajih (zgodovina, kultura, kulinarika, znamenitosti, narava, aktivnosti), npr. »Zgodovina
otoka Krk«. Članek ima nastavitve (koda, kategorija, slika, status) in prevode v jezike. Članek pripneš na državo,
regijo, mesto ali otok.

## Ali se članki vidijo na spletni strani?

**Blog ne obstaja.** Gumb »Blog« na domači strani je skrit (`FEATURES.blog = false`, seznam skritih funkcij H9), strani
`/blog` in `/articles/…` na spletu ni.

Članki se lahko pokažejo samo kot **kartice pod pasico** na straneh **države, regije, mesta in otoka**, in to samo,
če so izpolnjeni vsi trije pogoji:

1. članek je pripet na to državo/regijo/mesto/otok (glej [Pripenjanje](#pripenjanje-clanka-na-kraj));
2. prevod (v jeziku strani ali angleški) ima **Title** in **Excerpt**;
3. članek ima vpisano **Feature Image**.

Kartica pokaže sliko, naslov, izvleček in povezavo »Preberi več«. **Povezava vodi na `/<jezik>/articles/<slug>`,
kjer strani ni** — obiskovalec dobi »stran ne obstaja«. Celotno besedilo (**Content**) se zato nikjer ne prikaže.

Dokler blog ni narejen, članke raje ne pripenjaj na kraje oziroma jim ne vpisuj **Feature Image**, da se kartice z
mrtvo povezavo ne pojavijo. Ali so na dev/uat/prod članki že pripeti in imajo slike, ni jasno iz kode — preveri na
strani kakšne regije.

**Status članka kartice ne skrije.** Spletna stran dobi vse pripete članke ne glede na status (**Active**,
**Disabled** …). Če članka ne želiš na strani kraja, ga od kraja **odpni** (**Detach**).

## Seznam

Admin → **Articles**. Razvrščeno po kodi.

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Code** | stalna oznaka članka, npr. `krk-history` |
| **Article Title** | angleški naslov (če ga ni, koda) |
| **Category** | kategorija (barvna oznaka) |
| **Translations** | število prevodov kot `x/14`. Pozor: sistem ima 19 jezikov, zato je lahko tudi `19/14`. Zelena = točno 14, rumena = 10 ali več, rdeča = manj kot 10. |
| **Status** | **Active** zelena, drugo sivo |
| **Created at**, **Updated at** | skrita stolpca |

### Filtri

| Filter | Kaj naredi |
|---|---|
| **Category** | samo izbrana kategorija |
| **Status** | samo izbrani status |
| **Translations** | **All articles** / **Complete (all 14 languages)** / **Missing translations**. Pozor: »Complete« pomeni točno 14 prevodov; članek z vsemi 19 jeziki tukaj ni »Complete«. |

Iskanje zgoraj išče po kodi, naslovu in kategoriji. Članke najdeš tudi v globalnem iskanju (zgoraj v adminu).

### Gumbi

| Gumb | Kaj naredi |
|---|---|
| **Export to Excel** (zgoraj desno) | prenese seznam (z upoštevanimi filtri) kot `articles-<datum>.xlsx`: ID, koda, angleški naslov in izvleček, kategorija, status, število prevodov, datumi |
| **New article** (zgoraj desno) | nov članek |
| **Edit** (v vrstici) | uredi članek |
| označi vrstice → **Change Status** | vsem izbranim nastavi **New Status** |
| označi vrstice → **Change Category** | vsem izbranim vpiše **New Category** — to je prosto besedilo; vpiši natanko eno od kategorij spodaj (z veliko začetnico), sicer filter **Category** članka ne najde |
| označi vrstice → **Delete selected** | trajno izbriše izbrane (s prevodi in pripetji) |

## Nov članek / urejanje

### Nastavitve

| Polje | Obvezno | Pomen |
|---|---|---|
| **Article Code** | da | enolična oznaka (največ 100 znakov), npr. `krk-cuisine`. Admin dvojnika ne preveri vnaprej — če koda že obstaja, se ne shrani (napaka baze). |
| **Category** | ne | **History**, **Culture**, **Cuisine**, **Sights**, **Nature**, **Activities** |
| **Feature Image** | ne | pot do slike na CDN (npr. `img/articles/krk.jpg`). Slike se tukaj ne nalagajo, datoteka mora že biti na CDN. Ko je polje izpolnjeno, se lahko članek pokaže kot kartica (glej zgoraj). |
| **Status** | da | privzeto **Active**; na spletni strani trenutno nima učinka |

Shrani z **Create** (nov) ali **Save changes** (urejanje). Na strani urejanja je zgoraj desno še **Delete**.

### Prevodi (zavihek **Translations**)

En prevod na jezik. Gumb **New article translation**, v vrstici **Edit** in **Delete**.
Jezikov je 19: `en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq`. Če jezika ni, se
uporabi angleški prevod.

| Polje | Obvezno | Pomen |
|---|---|---|
| **Language** | da | vsak jezik samo enkrat (drugi prevod istega jezika se ne shrani) |
| **Title** | da | naslov |
| **Slug (URL-friendly)** | ne | del naslova URL za prihodnji blog; male črke, brez šumnikov, z vezaji. Admin oblike ne preverja. |
| **Excerpt** | ne | kratek povzetek (2–3 vrstice) — potreben, da se kartica pokaže |
| **Content** | ne | celotno besedilo (urejevalnik z oblikovanjem); trenutno se nikjer ne prikaže |

V seznamu prevodov so stolpci **Language**, **Title**, **Slug**, **Excerpt**.

## Pripenjanje članka na kraj

Članka ne pripneš v **Articles**, ampak pri kraju:

- **Countries** → država → zavihek **Articles**
- **Regions** → regija → zavihek **Articles**
- **Geography → Cities** → mesto → zavihek **Articles**
- **Geography → Islands** → otok → zavihek **Articles**

V zavihku: **Attach** → izberi članek (prikazan kot »naslov (koda)«) → potrdi. Za odstranitev **Detach** v vrstici
ali označi več → **Detach selected**. Odpenjanje članka ne izbriše.

Stolpci v zavihku: **Code**, **Article Title**, **Category**, **Status**.

## Kdaj se spremembe vidijo na spletu

Strani držav, regij, mest in otokov so predpomnjene v API (do 30 dni) in na spletni strani (do 1 ure). Spremembe
članka ali pripetja **ne počistijo** predpomnilnika API teh strani. Da se spremembe pokažejo prej, poženi na strežniku:

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje> php artisan cache:clear-api
```

Ukaz nima `--dry-run`; počisti ves predpomnilnik API tega okolja (podatki se ne izgubijo), nato počakaj do 1 ure.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Članek pripet, a kartice ni | manjka **Feature Image** ali **Excerpt** v jeziku strani (in v angleščini); ali predpomnilnik (glej zgoraj) |
| Klik »Preberi več« vrne »stran ne obstaja« | blog še ni narejen; to je pričakovano |
| Članek z **Disabled** se še vedno vidi | status ga ne skrije; odpni ga od kraja (**Detach**) |
| **Translations** kaže `19/14` ali filter »Complete« ne najde članka | števec in filter sta še nastavljena na 14 jezikov |
| Članek po **Change Category** ni v filtru **Category** | kategorija je vpisana drugače (npr. z malo začetnico); popravi jo v članku |
| Shranjevanje novega članka ne uspe | koda že obstaja |

## Za razvijalce

- Admin: `app/Filament/BrAdmin/Resources/ArticleResource.php` (+ `Pages/ListArticles.php` z izvozom
  `App\Exports\ArticlesExport`, `RelationManagers/TranslationsRelationManager.php`); pripenjanje:
  `{Country,Region,City,Island}Resource/RelationManagers/ArticlesRelationManager.php`.
- API: članki so samo v `show` odgovorih držav/regij/mest/otokov (`ArticleResource`; `articles.status` se naloži,
  a ne filtrira). Javne poti `/api/v1/articles` ni (`articles.show` ne obstaja v `routes/api.php`).
- Web: `src/lib/mappers/mapArticlesToInfoCards.ts` (pogoj title + excerpt + feature_image, povezava
  `/{locale}/articles/{slug}`), `src/components/BannerSection/InfoCardsSection.tsx`; blog skrit:
  `src/lib/constants/features.ts` (`blog: false`), `docs/features/hidden-for-launch.md` H9.
- Znane neskladnosti: števec/filter prevodov na 14 namesto 19 jezikov; `Change Category` je prosto besedilo.
