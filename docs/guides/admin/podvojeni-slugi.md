# Podvojeni slugi plaž

> Velja od: 2026-10-14 · Kje: Admin → **System → Fix Duplicate Slugs** · Okolje: dev, uat, prod

## Čemu služi

»Slug« je del spletnega naslova plaže, npr. `beach-solaris` v `…/istra/vabriga/beach-solaris`. Vsaka plaža ima
slug v vsakem jeziku. Če imata dve plaži enako ime (npr. »Beach Solaris« v Vabrigi in v Šibeniku), imata lahko
enak slug in ob odprtju naslova se pokaže napačna plaža.

Ta stran pokaže, koliko podvojenih slugov je, in jih zna popraviti: podvojenim plažam doda ime mesta
(`beach-solaris` → `beach-solaris-vabriga` in `beach-solaris-sibenik`). Stari naslov še naprej deluje (preusmeritev
se shrani v bazo).

> **Trenutno (2026-10-14) gumba za predogled in popravek ne delujeta.** Stran kliče ukaz z napačnim imenom
> (`beach:fix-duplicate-slugs`, pravilno je `beaches:fix-duplicate-slugs`), zato po kliku dobiš rdeče sporočilo
> **Error** (»… The command "beach:fix-duplicate-slugs" does not exist.«). Štetje podvojenih slugov deluje.
> Dokler napaka ni popravljena, popravek poženi z ukazom na strežniku (spodaj).

## Stran »Fix Duplicate Slugs«

### Stanje (zgoraj)

Kartica **Duplicate Beach Slugs Status**:

| Prikaz | Pomen |
|---|---|
| rdeče **N duplicate(s) found** | koliko slugov se ponovi; šteje se **po jezikih** (isti slug v 19 jezikih = 19) |
| zeleno **No duplicates** | vsi slugi so edinstveni |
| **Duplicates by Language:** | za vsak jezik (EN, SL, DE …) število podvojenih slugov |

Pod naslovom piše »across all 14 languages«, preverja pa vse podprte jezike (19).

### Gumbi (razdelek **Actions**)

| Gumb | Kaj naredi |
|---|---|
| **Refresh Status** | ponovno prešteje podvojene sluge |
| **Preview Changes (Dry Run)** | pokaže, kaj bi se spremenilo, nič ne zapiše; izpis se prikaže spodaj v **Command Output** |
| **Fix All Duplicates** | zares popravi vse podvojene sluge v vseh jezikih, shrani preusmeritve in počisti predpomnilnik |

**Preview Changes** in **Fix All Duplicates** sta onemogočena, ko podvojenih slugov ni. Med izvajanjem se pokaže
**Processing...**. **Fix All Duplicates** ne vpraša za potrditev — najprej vedno **Preview Changes**.

Spodaj je še modra kartica **How It Works** s kratkim opisom (v angleščini).

### Kako postopaš (ko bodo gumbi delovali)

1. Odpri **System → Fix Duplicate Slugs**. Če je zeleno **No duplicates**, ni dela.
2. Klikni **Preview Changes (Dry Run)**. V **Command Output** za vsak jezik vidiš tabelo plaž z istim slugom
   (**Beach ID**, **Name**, **Region**, **City**, **Current Slug**) in vrstice
   `[DRY RUN] Would update Beach 323: beach-solaris → beach-solaris-vabriga`.
3. Preveri, ali so to res različne plaže. Če sta to **ista plaža dvakrat** (dvojnik v bazi), slugov ne popravljaj,
   ampak eno od plaž izbriši ali ji nastavi drug status.
4. Klikni **Fix All Duplicates**. Pokaže se **Slugs Fixed Successfully!**, števec se osveži.
5. Odpri nekaj novih naslovov na spletni strani in en stari naslov (mora odpreti plažo).

## Kaj se zgodi po popravku

- Vsaka plaža z istim slugom dobi `-<mesto>` (slug mesta v tem jeziku). Če tak slug že obstaja, še `-2`, `-3` …
- Spremenijo se **vse** plaže s tem slugom, tudi prva.
- Plaža **brez mesta** se preskoči (`Beach N has no city - skipping`) in obdrži stari slug. Takim plažam najprej
  določi mesto (**Beaches** → **Edit** → **City**) in ponovi.
- Za vsak stari slug se shrani preusmeritev. Ker sta stari slug imeli dve plaži, stari naslov odpre **samo eno**
  (zadnjo obdelano). To je pričakovano.
- Po popravku sistem počisti predpomnilnik aplikacije. Spletna stran se osveži v največ eni uri. Iskalnik osvežiš
  s `search:index` ([splosno.md](splosno.md)).
- Popravka ni mogoče razveljaviti z gumbom. Stari slugi so zapisani v preusmeritvah, nazaj pa jih je treba vrniti
  ročno.

## Ukaz na strežniku (deluje vedno)

Pred vsak ukaz dodaj (namesto `<okolje>` vpiši `dev`, `uat` ali `prod`):

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje>
```

| Korak | Ukaz | Kaj naredi |
|---|---|---|
| 1 | `php artisan beaches:fix-duplicate-slugs --dry-run` | predogled, nič ne zapiše |
| 2 | `php artisan beaches:fix-duplicate-slugs` | popravi vse jezike |
| 3 | `php artisan cache:clear-api --type=all` | počisti predpomnilnik API |
| 4 | `php artisan search:index Beach` | osveži plaže v iskalniku |

Samo en jezik: dodaj `--locale=sl` (koda jezika). Na koncu izpiše
`COMPLETE - Fixed N duplicate slugs across all languages!` (pri predogledu `DRY RUN COMPLETE - Would fix N …`).

Ker dev, uat in prod nimajo iste baze, popravek velja samo za okolje, kjer ga poženeš. uat dobi podatke z dev ob
promociji baze.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Po kliku **Error** »… "beach:fix-duplicate-slugs" does not exist« | znana napaka strani (napačno ime ukaza); uporabi ukaz na strežniku |
| Po popravku je še vedno nekaj podvojenih | plaže brez mesta se preskočijo; dodaj jim mesto in ponovi |
| Stari naslov odpre napačno od dveh plaž | pričakovano: preusmeritev lahko kaže samo na eno plažo |
| Nov naslov na strani še ne deluje | počakaj do 1 ure ali poženi `cache:clear-api --type=all` |
| Gumba sta siva | podvojenih slugov ni (ali ukaz še teče) |

## Za razvijalce

Stran: `app/Filament/BrAdmin/Pages/FixDuplicateSlugs.php`, pogled
`resources/views/filament/br-admin/pages/fix-duplicate-slugs.blade.php`. Ukaz:
`app/Console/Commands/FixDuplicateBeachSlugs.php` (`beaches:fix-duplicate-slugs`); stran kliče
`Artisan::call('beach:fix-duplicate-slugs')` — napačno ime, popravek + regresijski test sta potrebna. Preusmeritve:
tabela `beach_slug_redirects`, branje v `BeachDetailsController`. Ozadje: `docs/FIX_DUPLICATE_SLUGS.md` (deloma
zastarel: omenja SSH in skripto `fix-duplicate-slugs.sh`, kar po pravilih ni dovoljeno).
