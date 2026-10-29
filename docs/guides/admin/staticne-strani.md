# Statične strani (noga, pogoji, o nas)

> Velja od: 2026-10-14 · Kje: Admin → **Content → Static Pages** in ukaz `php artisan static-pages:sync` · Okolje: dev, uat, prod

## Čemu služi

Statične strani so besedila, ki se ne spreminjajo pogosto: **O nas**, **Kontakt**, **Politika zasebnosti**,
**Politika piškotkov**, **Pogoji uporabe**, **Pravila za prispevke**, **Beachrex v AI pomočniku** in **Media kit**.
Prikazane so v nogi spletne strani (stolpec s ključnimi informacijami) in na naslovu `/<jezik>/p/<slug>`, npr.
`/si/p/politika-piskotkov`.

Vsaka stran ima:

- **nastavitve strani** (koda, vrstni red v nogi, ali je v nogi, status) in
- **prevode** — za vsak jezik svoj naslov, slug (del naslova URL), besedilo in podatke za Google (meta).

Besedila teh osmih strani v vseh 19 jezikih so shranjena tudi v repozitoriju (`database/content/static-pages/{jezik}.json`)
in jih v bazo naloži ukaz `static-pages:sync`. **Ukaz lahko prepiše tvoje popravke iz admina** — preberi razdelek
[Admin ali ukaz?](#admin-ali-ukaz-sync) preden kar koli spreminjaš.

Jeziki (19): `en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq`
(slovenščina je v bazi `sl`, na spletu `/si/`; ukrajinščina je `ua`).

## Seznam strani

Admin → **Content → Static Pages**. Razvrščeno po vrstnem redu v nogi.

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Code** | stalna oznaka strani, npr. `privacy-policy` (glej spodaj, zakaj je ne spreminjaj) |
| **Title (EN)** | angleški naslov; če angleškega prevoda ni, se pokaže koda |
| **Order** | vrstni red v nogi (manjša številka = višje) |
| **Footer** | kljukica = stran je v nogi |
| **Translations** | koliko jezikov ima stran, npr. `19/19`: zelena = vseh 19, rumena = 10–18, rdeča = manj kot 10 |
| **Status** | **Active** (zelena) = stran je objavljena; **Disabled** (rdeča) in vsi drugi statusi = stran ni dostopna |

### Filtri

| Filter | Kaj naredi |
|---|---|
| **Status** | samo strani z izbranim statusom |
| **In Footer** | samo strani v nogi / samo strani, ki niso v nogi |

### Gumbi

| Gumb | Kaj naredi |
|---|---|
| **New static page** (zgoraj desno) | nova stran |
| **Edit** (v vrstici) | odpre stran in njene prevode |
| Označi vrstice → **Delete selected** | izbriše označene strani (glej [Brisanje](#brisanje-strani)) |

## Urejanje strani

### Nastavitve strani (razdelek **Page Settings**)

| Polje | Pomen |
|---|---|
| **Page Code** | obvezno, največ 100 znakov, mora biti enolično. Npr. `privacy-policy`, `terms-of-use`. |
| **Footer Order** | vrstni red v nogi (manjše številke so prve) |
| **Show in Footer** | izklopi, če strani ne želiš v nogi |
| **Status** | **Active** = objavljena. Kateri koli drug status (npr. **Disabled**, **Draft**) stran skrije: ni v nogi in njen naslov vrne »stran ne obstaja«. |

Po **Save changes** se spremembe v API pokažejo takoj (predpomnilnik se sam počisti).

**Kode ne spreminjaj pri obstoječih straneh.** Spletna stran nekatere strani išče po kodi:

| Koda | Kje jo spletna stran uporablja |
|---|---|
| `about-us` | povezava »O nas« v meniju in preusmeritev z `/<jezik>/about` |
| `contact` | povezava »Kontakt« v meniju |
| `privacy-policy` | povezava na politiko zasebnosti ob prijavi na novice |
| `terms-of-use` | povezava na pogoje pri AI načrtovalniku potovanj |
| `media-kit` | pod besedilom se doda razdelek za prenos logotipov |

Če kodo spremeniš, te povezave ne delujejo več, ukaz `static-pages:sync` pa bo ob naslednjem zagonu ustvaril
**novo** stran s staro kodo (dvojnik).

### Prevodi (zavihek **Translations** pod nastavitvami)

Vsak jezik je ena vrstica. Stolpci: **Language** (z zastavico), **Title**, **Slug**.

- **New static page translation** — dodaj jezik, ki ga še ni. Vsak jezik je lahko samo enkrat; drugi prevod v istem
  jeziku se ne shrani (napaka baze).
- **Edit** — popravi obstoječi prevod. **Delete** — izbriše prevod (stran v tem jeziku potem pokaže angleško besedilo).

Polja prevoda:

| Polje | Obvezno | Pomen |
|---|---|---|
| **Language** | da | jezik prevoda |
| **Title** | da | naslov strani (v nogi in kot glavni naslov na strani) |
| **Slug** | da | del naslova URL: `/<jezik>/p/<slug>`. Samo male črke brez šumnikov in vezaji, npr. `politika-zasebnosti`. |
| **Content** | ne | besedilo; orodna vrstica ima krepko, ležeče, podčrtano, naslov, sezname, povezavo in citat |
| **Meta Title** | ne | naslov, ki ga pokaže Google |
| **Meta Description** | ne | kratek opis za Google (največ 500 znakov) |

Pozor pri slugu:

- Admin ne preverja oblike sluga ne tega, ali ga v istem jeziku že uporablja druga stran (ukaz `static-pages:sync`
  to preverja). Kaj se zgodi, če imata dve strani v istem jeziku enak slug, ni jasno iz kode — preveri; raje tega ne počni.
- Če slug spremeniš, stari naslov URL ne deluje več (ni samodejne preusmeritve).

Pozor pri besedilu: spletna stran iz besedila odstrani vse, kar ni osnovno oblikovanje (dovoljeni so odstavki,
naslovi, seznami, povezave, krepko/ležeče/podčrtano, citati, `span`, `div` in atribut `class`; tabele in slike ne). Politika piškotkov vsebuje skrit element
`<div class="cky-audit-table-element"></div>`, na katerega mesto CookieYes vstavi tabelo piškotkov. Ali urejevalnik
v adminu ta element ob shranjevanju ohrani, ni jasno iz kode — preveri. Varneje je politiko piškotkov urejati prek
datotek in ukaza (spodaj).

### Brisanje strani

Gumb **Delete** (zgoraj desno na strani urejanja) ali **Delete selected** na seznamu. Stran izgine z noge in z
naslova URL. V bazi ostane označena kot izbrisana, zato je ukaz `static-pages:sync` **ne obnovi** (izpiše
`skip <koda>: the page is deleted in the admin`). Obnovitve v adminu ni; za to prosi razvijalca.

Če želiš stran samo začasno skriti, raje nastavi **Status** na **Disabled** ali izklopi **Show in Footer**.

## Kdaj se spremembe vidijo na spletu

| Kaj | Kdaj |
|---|---|
| API (`/api/v1/static-pages`) | takoj po shranjevanju |
| Stran `/<jezik>/p/<slug>` | v največ **1 uri** (spletna stran je predpomnjena) |
| Seznam v nogi in meniju | ob naslednjem nalaganju strani, lahko do 1 ure (predpomnilnik brskalnika) |
| Zemljevid strani za Google (sitemap) | v največ 1 dnevu |

## Ukaz `static-pages:sync`

Ukaz prebere datoteke `database/content/static-pages/{jezik}.json` (ena datoteka na jezik, v njej vse strani) in
besedila zapiše v bazo. Datoteke so del kode: spremeni jih razvijalec (ali AI agent) s PR, po deployu pa ti poženeš ukaz.

Ukaz poganjaš na strežniku. Zamenjaj `<okolje>` z `dev`, `uat` ali `prod`:

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje> php artisan static-pages:sync --dry-run
```

**Vedno najprej `--dry-run`** (nič ne spremeni, samo izpiše, kaj bi naredil). Ko si zadovoljen, isti ukaz brez `--dry-run`.

| Možnost | Pomen |
|---|---|
| `--dry-run` | pokaže spremembe, ničesar ne zapiše |
| `--locale=sl` | samo ta jezik; lahko večkrat, npr. `--locale=sl --locale=en` |
| `--path=…` | druga mapa z datotekami (privzeto `database/content/static-pages`; običajno ne rabiš) |

### Kaj ukaz naredi

1. **Preveri datoteke.** Če kaj ni v redu, izpiše napake in **ne spremeni ničesar**: neznan jezik, manjkajoče polje
   (title, slug, meta_title, meta_description, content), slug ni iz malih črk in vezajev, isti slug pri dveh straneh,
   slug že ima druga stran v bazi.
2. **Za vsako stran iz datotek** (išče jo po kodi):
   - stran ne obstaja → jo ustvari: **Active**, **v nogi**, vrstni red po vnaprej določenem zaporedju
     (about-us 1, privacy-policy 2, terms-of-use 3, contribution-rules 4, contact 5, cookie-policy 6,
     ai-assistant-connector 7, media-kit 8; druge 99);
   - stran je bila izbrisana v adminu → jo preskoči;
   - stran obstaja → **pusti** njen status, **Show in Footer** in **Footer Order**.
3. **Za vsak jezik te strani:**
   - prevoda ni → ga ustvari (tudi če si ga prej v adminu izbrisal);
   - prevod je enak datoteki → ga ne spremeni;
   - prevod se razlikuje → **prepiše polja Title, Slug, Meta Title, Meta Description in Content z besedilom iz datoteke**.
4. Ničesar ne briše. Strani, ki jih ni v datotekah (npr. stran, ki si jo sam dodal v adminu), ne spremeni.
5. Vse naredi naenkrat (če se kaj zalomi, ne spremeni ničesar). Predpomnilnik se počisti sam.

### Kako brati izpis

| Vrstica | Pomen |
|---|---|
| `create page cookie-policy` | ustvaril bo novo stran |
| `  + about-us [sl] /p/o-nas` | ustvaril bo manjkajoči prevod |
| `  ~ terms-of-use [sl] content` | **prepisal bo** navedena polja tega prevoda |
| `skip … the page is deleted in the admin` | stran je izbrisana v adminu, ukaz je ne bo obnovil |
| `[dry run] Pages created: 0 · translations created: 0, updated: 3, unchanged: 149` | povzetek |

Vsaka vrstica z `~` pomeni, da se besedilo v bazi razlikuje od datoteke. Če si to stran v tem jeziku popravljal v
adminu, **se bo tvoj popravek izgubil**.

## Admin ali ukaz (sync)?

| Želiš … | Uporabi |
|---|---|
| hitro popraviti tipkarsko napako ali stavek | admin (Edit prevoda) — **in razvijalcu sporoči isti popravek za datoteke**, sicer ga naslednji sync prepiše |
| spremeniti pogoje uporabe, politiko zasebnosti ali piškotkov (pravna besedila) | datoteke + ukaz (en popravek v vseh 19 jezikih, zgodovina sprememb v gitu) |
| dodati novo stran v vseh jezikih | datoteke + ukaz |
| skriti stran, spremeniti vrstni red v nogi | admin (**Status**, **Show in Footer**, **Footer Order**) — ukaz teh nastavitev pri obstoječih straneh ne spreminja |
| dodati stran samo v nekaj jezikih ali za kratek čas | admin (nova koda, ki je ni v datotekah — ukaz je ne bo nikoli spremenil) |

Zlato pravilo: **besedila osmih strani iz datotek popravljaj v datotekah.** Če jih popraviš v adminu, je to začasno.
Pred vsakim pravim zagonom ukaza poglej `--dry-run`: če vidiš `~` pri strani in jeziku, ki si ga popravljal v adminu,
ustavi se in popravek najprej prenesi v datoteko.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Popravek v adminu je izginil | po popravku je nekdo pognal `static-pages:sync`; popravek prenesi v `database/content/static-pages/{jezik}.json` |
| Stran se v nekem jeziku kaže v angleščini | ta jezik nima prevoda (stolpec **Translations** < 19/19); dodaj prevod ali poženi sync |
| Stran ni v nogi | **Status** ni **Active** ali je **Show in Footer** izklopljen; ali pa še ni minila ura (predpomnilnik spletne strani) |
| `/p/<slug>` vrne »stran ne obstaja« | slug v tem jeziku je drugačen, stran ni **Active** ali je izbrisana |
| Sync izpiše `slug '…' already belongs to another page` | slug iz datoteke v tem jeziku že uporablja druga stran v bazi (verjetno dodana v adminu); spremeni slug ene od njiju |
| Sync izpiše `missing content` ali `unknown locale` | napaka v datoteki; popravi jo razvijalec |
| Sync ne obnovi izbrisane strani | to je namenoma (`skip … deleted in the admin`); obnovo naredi razvijalec |
| Ob dodajanju prevoda napaka | ta jezik že ima prevod; uredi obstoječega |

## Za razvijalce

- Admin: `app/Filament/BrAdmin/Resources/StaticPageResource.php` (+ `StaticPageResource/RelationManagers/TranslationsRelationManager.php`).
- Ukaz: `app/Console/Commands/SyncStaticPages.php`; vsebina: `database/content/static-pages/{locale}.json`; testi:
  `tests/Feature/SyncStaticPagesTest.php`, `tests/Feature/StaticPageApiTest.php`.
- API: `GET /api/v1/static-pages` (Active + v nogi), `GET /api/v1/static-pages/{slug}` (Active, s padcem na `en`);
  predpomnilnik 30 dni, ključi se brišejo v `StaticPage` / `StaticPageTranslation` (model events).
- Web: `src/app/[locale]/p/[slug]/page.tsx` (ISR 3600 s), `src/hooks/useStaticPages.ts` (staleTime 1 h),
  `src/components/StaticPage/StaticPageContent.tsx` (DOMPurify).
- Ozadje: `DEV_HANDOFF.md` 2026-10-08 »Footer pages: content in 19 languages + `static-pages:sync` (TASK-004 step 3)«.
