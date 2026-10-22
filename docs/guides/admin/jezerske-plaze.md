# Jezerske plaže s stare baze (popravek vodne površine)

> Velja od: 2026-10-22 · Kje: ukaz na strežniku `beaches:fix-lake-waterbody` · Okolje: dev, uat, prod (vsako posebej)

## Čemu služi

V stari bazi je imela **vsaka** plaža vodno površino »morje«. Da je plaža ob jezeru, je stara baza zapisala samo z
lokacijo »jezero« in imenom jezera. Uvoz stare baze je vodno površino prepisal, zato je 42 jezerskih plaž
(Nizozemska, Belgija, Lac de Cazaux, Vouliagmeni) v novi bazi označenih kot **morje**: ne pokažejo se pod filtrom
**Jezero**, na strani plaže pa piše **Sea**.

Ukaz tem 42 plažam nastavi vodno površino **Lake** in preveri, da je vsaka povezana s svojim jezerom
(**Geography → Lakes**). Če jezera še ni, ga ustvari (angleško ime iz stare baze). Druge plaže ostanejo nespremenjene.
Ukaz ne uvaža ničesar znova.

Seznam 42 plaž je v repozitoriju (`database/legacy/lake-beaches.json`, izvleček iz `database/sql/beachrex_old.sql`).

## Ukaz na strežniku

Pred vsak ukaz dodaj (namesto `<okolje>` vpiši `dev`, `uat` ali `prod`):

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.<okolje> exec -T api_<okolje>
```

| Korak | Ukaz | Kaj naredi |
|---|---|---|
| 1 | `php artisan beaches:fix-lake-waterbody` | **predogled**, nič ne zapiše |
| 2 | `php artisan beaches:fix-lake-waterbody --apply` | zapiše popravek in počisti predpomnilnik API |
| 3 | `php artisan search:index Beach` | osveži plaže v iskalniku |

Pozor: ta ukaz je **brez zastavice predogled privzeto** (drugi ukazi imajo `--dry-run`). Brez `--apply` nikoli nič ne
zapiše.

## Kaj pokaže izpis

Tabela z vrstico za vsako plažo s seznama:

| Stolpec | Pomen |
|---|---|
| **Beach ID**, **Name** | plaža |
| **Waterbody before** / **after** | številka vodne površine pred in po popravku (3 = morje, 4 = jezero) |
| **Result** | `waterbody -> lake` (spremeni se v jezero), `link lake '…'` (poveže z jezerom), `create lake '…'` (jezera še ni, ustvari se), `ok, nothing to do` (že pravilno), `beach not found - skipped` (plaže v tej bazi ni) |

Spodaj je povzetek: koliko plaž je na seznamu, koliko jih ni v bazi, koliko se spremeni v jezero, koliko se jih
poveže z jezerom, koliko novih jezer nastane in koliko je že pravilnih. Predogled se konča z
`DRY RUN COMPLETE - run with --apply to write the changes`, pravi zagon z `COMPLETE - lake beaches updated`.

## Postopek

1. Poženi predogled (korak 1). Pričakuj okoli 42 vrstic `waterbody -> lake`.
2. Preveri vrstice z `create lake` in `Lake name differs` (glej spodaj).
3. Poženi korak 2, nato korak 3.
4. Na spletni strani odpri npr. Strand Schildmeer (Steendam): pri vodni površini mora pisati **Lake**. Filter
   **Jezero** mora pokazati te plaže. Spletna stran se osveži v največ eni uri.
5. Ponovni zagon ne škodi: drugič vse vrstice pokažejo `ok, nothing to do`.

Ker dev, uat in prod nimajo iste baze, ukaz poženi na vsakem okolju posebej (uat dobi podatke z dev ob promociji baze).

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| `Lake name differs` | plaža je že povezana z jezerom z drugim imenom; ukaz povezave ne spremeni. Obrazec plaže (**Beaches → Edit**) polja za jezero nima, zato pošlji ID plaže razvijalcu. |
| Dve jezeri za isto jezero (npr. »Grevelingenmeer« in »Gravelingenmeer«) | tipkarska napaka v stari bazi. Ime jezera lahko popraviš v **Geography → Lakes**; združitev dveh jezer (prestavitev plaže) zdaj naredi razvijalec, ker obrazec plaže polja za jezero nima. |
| `beach not found - skipped` | plaže na tem okolju ni (npr. izbrisana); nič ni narobe. |
| `Waterbody with code 'lake' does not exist` | v **Waterbodies** manjka vrstica s kodo `lake`; ukaz ne naredi ničesar. |
| Na strani še piše **Sea** | počakaj do 1 ure (predpomnilnik spletne strani). |

## Za razvijalce

Ukaz: `app/Console/Commands/FixLakeBeachWaterbody.php`, test `tests/Feature/FixLakeBeachWaterbodyTest.php`.
Plaže shrani prek Eloquenta (počisti predpomnilnik modela in posodobi iskalnik), na koncu še `CacheService::clearAll()`.
Ozadje: ADR »Lake beaches from the old database« v `docs/ai/implementation/DECISIONS.md`.
