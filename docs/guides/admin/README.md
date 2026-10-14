# Navodila za admin — kazalo

Navodila za uporabo Beachrex admina (dock-dev / dock-uat / dock.beachrex.com) in ukazov, ki jih poganja lastnik.
Napisana so v slovenščini (odločitev lastnika 2026-10-14). Vsak PR, ki doda ali spremeni stran v adminu ali ukaz za
lastnika, v istem PR posodobi tudi ta navodila (`docs/DEVELOPMENT-RULES.md` §7).

Iz tega kazala se na koncu zgenerira enoten priročnik (vrstni red = vrstni red v tabelah).

Stanje: ✅ napisano · ⏳ še ni napisano

## Splošno

| Tema | Navodila | Stanje |
|---|---|---|
| Prijava, meni, nadzorna plošča (statistike) | [splosno.md](splosno.md) | ⏳ |

## Vsebina (Content)

| Funkcija | Kje v adminu | Navodila | Stanje |
|---|---|---|---|
| Spletne kamere (Windy) | Content → Webcam review | [spletne-kamere.md](spletne-kamere.md) | ✅ |
| Statične strani (noga, pogoji, o nas) | Content → Static Pages | [staticne-strani.md](staticne-strani.md) | ⏳ |
| Vsebina prve strani | Content → First Page Content / First Page Items | [prva-stran.md](prva-stran.md) | ⏳ |
| Prijave na novice | Content → Newsletter Subscribers | [novice.md](novice.md) | ⏳ |
| Članki | Articles | [clanki.md](clanki.md) | ⏳ |

## Plaže in kraji

| Funkcija | Kje v adminu | Navodila | Stanje |
|---|---|---|---|
| Plaže (urejanje, prevodi, lastnosti) | Beaches | [plaze.md](plaze.md) | ⏳ |
| Celine, države, regije | Continents, Countries, Regions | [geografija.md](geografija.md) | ⏳ |
| Mesta, otoki, jezera, reke | Geography → Cities, Islands, Lakes, Rivers | [geografija.md](geografija.md) | ⏳ |
| Značke | Badges | [znacke.md](znacke.md) | ⏳ |
| Šifranti lastnosti plaž (tip plaže, dostop, parkiranje, globina …) | Beach Types, Waterbodies, Beach Characteristics, Beach Features | [sifranti.md](sifranti.md) | ⏳ |

## Mediji

| Funkcija | Kje v adminu | Navodila | Stanje |
|---|---|---|---|
| Fotografije, videi, 360° | Media → Media, Media Collections, Media Types | [mediji.md](mediji.md) | ⏳ |

## Uporabniki in sistem

| Funkcija | Kje v adminu | Navodila | Stanje |
|---|---|---|---|
| Uporabniki, administratorji, uvoženi s stare strani | User Management → Users, Genders | [uporabniki.md](uporabniki.md) | ⏳ |
| Podvojeni slugi | System → Fix Duplicate Slugs | [podvojeni-slugi.md](podvojeni-slugi.md) | ⏳ |
| Statusi | System → Statuses | [sifranti.md](sifranti.md) | ⏳ |

## Ukazi za lastnika (na strežniku)

Ukazi se poganjajo na strežniku z `docker compose … exec -T api_<okolje> php artisan …` (točen začetek je v vsakih
navodilih). Vsak ukaz, ki spreminja podatke, ima `--dry-run`; vedno ga poženi najprej.

| Ukaz | Čemu služi | Navodila | Stanje |
|---|---|---|---|
| `webcams:import`, `webcams:embed-check`, `webcams:legacy-extract`, `webcams:match`, `webcams:refresh` | spletne kamere | [spletne-kamere.md](spletne-kamere.md) | ✅ |
| `static-pages:sync` | naloži besedila strani v nogi v vseh jezikih | [staticne-strani.md](staticne-strani.md) | ⏳ |
| `content:ai` | AI popravek imen, regij in opisov plaž | [ai-vsebina.md](ai-vsebina.md) | ⏳ |
| `translations:audit`, `translations:ai` | manjkajoči prevodi in AI prevodi | [ai-vsebina.md](ai-vsebina.md) | ⏳ |
| `media:embed-copyright` | avtorske pravice v starih fotografijah | [mediji.md](mediji.md) | ⏳ |
| `users:migrate-from-old-db` | uvoz uporabnikov s stare strani | [uporabniki.md](uporabniki.md) | ⏳ |
| `cache:clear-api`, `search:index` | osvežitev predpomnilnika in iskalnika | [splosno.md](splosno.md) | ⏳ |

Enkratni tehnični postopki (zagon produkcije, obnova varnostne kopije, ASCII poti) ostanejo v angleških runbookih v
`docs/runbooks/`.
