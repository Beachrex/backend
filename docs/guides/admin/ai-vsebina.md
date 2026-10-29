# AI vsebina in prevodi

> Velja od: 2026-10-14 · Kje: ukazi na strežniku (`content:ai`, `translations:audit`, `translations:ai`), rezultat v adminu pri **Beaches**, **Regions** in šifrantih · Okolje: **samo dev** (uat in prod dobita podatke s promocijo baze)

## Čemu služi

V bazi je veliko podatkov slabe kakovosti: imena plaž z angleško besedo »Beach« v vseh jezikih (»Beach Slanica«),
angleška imena regij v vseh jezikih (»Dalmatia - Šibenik«), opisi samo v enem jeziku in plaže brez opisa. Lastnosti
plaž (tip plaže, ponudba …) v nekaterih jezikih (da, pt, nl, el, sq) sploh nimajo prevodov.

Trije ukazi to popravijo s pomočjo Claude (Anthropic):

| Ukaz | Kaj naredi | Stane? |
|---|---|---|
| `translations:audit` | samo poročilo: katerih prevodov v šifrantih manjka | ne |
| `translations:ai` | prevede imena v šifrantih (lastnosti plaž, značke, otoki, jezera, reke, države, celine) v vse jezike; imena plaž in mest v grščino, ukrajinščino, ruščino, bolgarščino | da |
| `content:ai` | popravi imena plaž (brez »Beach«), prevede imena regij in opise, napiše opise plažam brez opisa | da |

Varnost (vgrajeno v program):

- **slugi (naslovi strani) se nikoli ne spremenijo**;
- **besedila, ki jih je napisal človek, se ne prepišejo** (ime brez »Beach«, ki se razlikuje od izvirnika,
  preveden naziv regije, obstoječ opis, ročno preveden naziv v šifrantu);
- **nič se ne zapiše brez `--write`**; `apply` brez `--write` je samo predogled.

## Strošek — preberi pred začetkom

Vsak `preview` in `submit` porabi denar na Anthropic računu (ključ `ANTHROPIC_API_KEY` v
`/opt/beachrex/envs/.env.dev`).

- `content:ai plan` izpiše **okvirno ceno** (stolpec **Rough cost**, skupaj **Rough total**). To je ocena; pravi
  strošek vidiš v Anthropic konzoli.
- `translations:ai plan` izpiše samo, koliko imen bo prevedenih (brez cene).
- `submit` pošlje paketno obdelavo (»batch«), ki stane **pol** običajne cene in je končana v 24 urah, običajno v
  eni uri.
- Model izbereš z `AI_CONTENT_MODEL` v `.env.dev` (privzeto `claude-opus-5-5`; `claude-sonnet-5-5` stane pol manj).
  `AI_CONTENT_EFFORT` (privzeto `low`) povečaj samo, če je predogled slab.
- `submit` zavrne isti del, če prejšnji paket še ni zapisan z `--write` (da ne plačaš dvakrat). `--force` to obide —
  uporabi ga samo, če veš, zakaj.
- Če kredit med paketom poide, se del zahtev vrne kot `errored`. `apply --write` zapiše uspešne, naslednji `submit`
  pošlje samo manjkajoče.

## Kako poganjaš ukaze

Ukaze poganjaš na strežniku, **samo na dev**, enega za drugim. Pred vsak ukaz dodaj:

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.dev exec -T api_dev
```

V spodnjih tabelah je zaradi preglednosti napisan samo del od `php artisan` naprej.

## 1. `translations:audit` — kaj manjka (brezplačno)

```
php artisan translations:audit
```

Izpiše tabelo za vsak šifrant (`…_translation`) in jezik: **Table**, **Locale**, **Rows** (koliko zapisov ima
šifrant), **Missing** (koliko prevodov manjka), **Same as en** (koliko jih je še v angleščini). Nič ne spremeni.

| Možnost | Kaj naredi |
|---|---|
| `--table=beach_type_translation` | samo ta tabela (lahko večkrat) |
| `--examples=5` | izpiše do 5 primerov neprevedenih imen |

Poženi ga pred `translations:ai` in po njem (preveriš, da se je **Missing** zmanjšal).

## 2. `translations:ai` — prevodi šifrantov

| Korak | Ukaz | Kaj naredi | Stane? |
|---|---|---|---|
| 1 | `translations:ai plan` | tabele, število imen in jeziki za prevod | ne |
| 2 | `translations:ai preview --table=beach_type_translation` | prevede prvi kos (do 40 vrstic) ene tabele in ga izpiše; nič ne zapiše | malo |
| 3 | `translations:ai submit` | pošlje vse tabele kot paket; izpiše oznako paketa `msgbatch_…` | da |
| 4 | `translations:ai status --batch=msgbatch_…` | stanje paketa; ponavljaj, dokler ni `ended` | ne |
| 5 | `translations:ai apply --batch=msgbatch_…` | pokaže, kaj bi zapisal; nič ne zapiše | ne |
| 6 | `translations:ai apply --batch=msgbatch_… --write` | zapiše v bazo | ne |
| 7 | `cache:clear-api --type=all` in `search:index` | osveži predpomnilnik in iskalnik | ne |

Pri koraku 2 izberi tabelo iz izpisa koraka 1 (preview potrebuje točno eno `--table`). Pri `submit` lahko z
`--table=…` pošlješ samo nekatere tabele.

Kaj se zapiše: samo jeziki, ki prevoda nimajo ali imajo še angleško ime. Nova vrstica prevzame slug angleške.
Imena plaž in mest se prevedejo samo v grščino, ukrajinščino, ruščino in bolgarščino (v njihovi pisavi); ostali
jeziki ohranijo krajevno ime. Opisi plaž se tu ne prevajajo (to dela `content:ai`).

Na dev je bilo to 2026-10-12 že narejeno (3 563 imen v šifrantih in 8 900 imen plaž in mest). Ponovni zagon
pošlje samo, kar je od takrat novega ali še manjka.

## 3. `content:ai` — imena, regije, opisi plaž

Štirje deli, **vedno v tem vrstnem redu**:

| Del (`--part=`) | Kaj popravi |
|---|---|
| `names` | imena plaž brez angleškega »Beach« (»Slanica«, »Vela plaža«) + cirilica in grščina |
| `regions` | imena regij v vseh 19 jezikih (»Dalmacija - Šibenik«) |
| `descriptions` | opis obstaja v enem jeziku → prevod v jezike brez opisa |
| `generate` | plaže brez opisa: nov opis (50–110 besed) iz podatkov v bazi in znanih dejstev o kraju, v vseh jezikih |

Za vsak del posebej:

| Korak | Ukaz | Kaj naredi | Stane? |
|---|---|---|---|
| 1 | `content:ai plan` | število za vsak del in okvirna cena | ne |
| 2 | `content:ai preview --part=names --sample=5` | 5 primerov, nič ne zapiše. **Izpis pošlji razvijalcu** in počakaj na potrditev. | malo |
| 3 | `content:ai submit --part=names` | pošlje paket; izpiše `msgbatch_…` (velike dele razdeli na pakete po 5 000) | da |
| 4 | `content:ai status --batch=msgbatch_…` | ponavljaj, dokler ni `ended` | ne |
| 5 | `content:ai apply --batch=msgbatch_…` | izpiše spremembe in povzetek; nič ne zapiše | ne |
| 6 | `content:ai apply --batch=msgbatch_… --write` | zapiše v bazo | ne |
| 7 | `cache:clear-api --type=all`, nato `search:index` | osveži predpomnilnik in iskalnik | ne |

Nato isto za `regions`, `descriptions` in `generate`.

Uporabne možnosti pri predogledu:

| Možnost | Kaj naredi |
|---|---|
| `--id=172` | samo plaža (ali regija) s to številko |
| `--full --locale=sl --locale=en` | izpiše cele opise v teh jezikih (sicer samo prvih 80 znakov) |
| `--limit=100` | pri `submit`: samo prvih 100 — dober prvi poskus pred celotnim delom |
| `--show=100` | pri `apply`: koliko sprememb izpiše |

Vsak del vedno izbere samo, kar je še ostalo, zato drugi `submit` po `apply --write` pošlje le preostanek.

## Po zagonu

1. V adminu odpri nekaj plaž (**Beaches** → **Edit** → **Translations**) in regij — ali so imena in opisi pravi?
2. Odpri nekaj strani plaž na https://dev.beachrex.com v več jezikih (stran se osveži v največ eni uri).
3. Z `translations:audit` preveri, da je manj manjkajočih prevodov.
4. Na uat in prod se popravki prenesejo s **promocijo baze** (dev → uat → prod); tam teh ukazov ne poganjaš.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Napaka o manjkajočem ključu | `ANTHROPIC_API_KEY` ni v `.env.dev` ali dev po spremembi ni bil ponovno nameščen |
| `preview needs exactly one --table=<name>_translation.` | pri `translations:ai preview` dodaj točno eno `--table=…` |
| `--batch=<id> is required.` | pri `status` / `apply` dodaj `--batch=msgbatch_…` iz izpisa `submit` |
| `submit` zavrne pošiljanje | prejšnji paket istega dela še ni zapisan; najprej `apply --batch=… --write` |
| Del zahtev `errored` | npr. zmanjkalo je kredita; zapiši uspešne (`--write`), napolni kredit, ponovi `submit` |
| Status dolgo ni `ended` | paket ima do 24 ur časa; počakaj |
| Na strani se nič ne spremeni | pozabil si `--write`, ali `cache:clear-api --type=all` / počakaj do 1 ure |
| Prevod ti ni všeč | ročno ga popravi v adminu; ukazi ročnih popravkov ne prepišejo |

## Za razvijalce

Podrobnosti in ozadje (angleško): [`docs/runbooks/content-ai.md`](../../runbooks/content-ai.md).
Ukazi: `app/Console/Commands/ContentAi.php`, `TranslationsAudit.php`, `TranslationsAi.php`; pravila
`App\Services\AI\Content\BeachContentAi`; nastavitve `config/ai.php` (`ai.content.model`, `ai.content.effort`).
Testi: `tests/Feature/ContentAiTest.php`, `TranslationsAiTest.php`, `TranslationsAuditTest.php`.
Zgodovina: `DEV_HANDOFF.md` 2026-10-12, ADR »Data quality pass with Claude batches«.
