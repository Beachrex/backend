# Spletne kamere (Windy)

> Velja od: 2026-10-14 (TASK-006) · Kje: Admin → **Content → Webcam review** · Okolje: dev (uat/prod ob promociji)

## Čemu služi

Na strani plaže se prikaže **spletna kamera v živo**, vendar samo kamera, ki je res na tej plaži in res deluje.
Kamere prihajajo z Windy.com. Sistem jih enkrat prenese v našo bazo, jih sam poveže s plažami in tiste, pri katerih
ni prepričan, da jih prikaže ti v pregled. Na strani se nikoli ne prikaže kamera, ki je nisi odobril ti ali sistem z
visoko oceno.

Stare kamere (stolpec, prilepljen v starem adminu) se ne prikazujejo več. Iz njih sistem samo enkrat izlušči
uporabne Windy kamere.

## Kako poteka (pregled)

1. **Uvoz** — sistem z Windy API prenese podatke o kamerah (ime, lokacija, kategorije). Slike se ne shranjujejo.
2. **Preverjanje predvajalnika** — za vsako kamero blizu naših plaž preveri, ali se Windy predvajalnik res prikaže
   (nekatere kamere obstajajo, a lastnik ne dovoli vgradnje).
3. **Stare kamere** — iz starega stolpca izlušči ID-je in jih oceni po enakih pravilih.
4. **Ujemanje** — vsaki plaži poišče kamere v radiju **3 km** in jih točkuje.
5. **Pregled** — ti v adminu potrdiš ali zavrneš negotove.
6. **Prikaz** — stran plaže pokaže odobrene kamere; kartice in zemljevid imajo gumb, ki odpre kamero na windy.com.

## Prvi zagon (enkrat, na dev)

Potrebuješ: `WINDY_API_KEY` v `/opt/beachrex/envs/.env.dev` (ključ z https://api.windy.com/keys) in redeploy dev.
Neobvezno: `WINDY_DAILY_REQUEST_BUDGET` (privzeto 500 klicev na dan).

Ukaze poganjaš na strežniku. Pred vsak ukaz spodaj dodaj:

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.dev exec -T api_dev
```

Vsak ukaz najprej poženi z `--dry-run` (nič ne spremeni), potem brez. Začni z eno državo:

| Korak | Ukaz | Kaj naredi | Porabi Windy klice? |
|---|---|---|---|
| 1 | `php artisan webcams:import --country=HR` | prenese kamere za Hrvaško | da (~1 na 50 kamer) |
| 2 | `php artisan webcams:embed-check` | preveri predvajalnik za kamere blizu plaž (~1 na sekundo) | ne |
| 3 | `php artisan webcams:legacy-extract --country=HR` | reši stare kamere, naredi CSV poročilo | da, malo (1 na 50 neznanih ID-jev) |
| 4 | `php artisan webcams:match --country=HR` | poveže kamere s plažami | ne |

Ko si zadovoljen s Hrvaško, ponovi korake 1–4 brez `--country` (vse države). Če uvoz doseže dnevno omejitev, se
ustavi in izpiše »Daily budget reached«. **Isti ukaz naslednji dan nadaljuje, kjer je ostal.**

Poročilo stare kamere: `storage/app/webcams/legacy-extract-<datum>.csv` (stolpci: plaža, stari format, ID, izid,
razlog, razdalja, ocena). Izidi: `kept` (prikazana), `pending` (čaka pregled), `below_threshold` (prenizka ocena),
`dead` (ne deluje), `unparseable` (ni Windy kamera, npr. WhatsUpCams).

## Seznam plaž s kamerami (CSV)

Ukaz `webcams:export` (samo bere, nič ne spremeni) naredi CSV z vsemi plažami, ki imajo na strani prikazano kamero:
ID, država, ime plaže, **povezava na plažo na spletni strani**, Windy ID, naslov kamere, povezava na windy.com,
status, glavna, vir, ocena, razdalja.

```
php artisan webcams:export --base-url=https://dev.beachrex.com
```

| Možnost | Pomen |
|---|---|
| `--base-url=…` | naslov spletne strani v povezavah (dev: `https://dev.beachrex.com`) |
| `--all` | vse dodelitve, tudi Pending, Rejected in kamere, ki se ne predvajajo |
| `--country=HR` | samo izbrana država (lahko večkrat) |
| `--locale=si` | povezave v drugem jeziku (privzeto `en`) |

Ukaz izpiše pot do datoteke, npr. `/var/www/html/storage/app/private/webcams/export-<datum>.csv` (v kontejnerju).
Na svoj računalnik jo preneseš v dveh korakih — na strežniku jo prekopiraš iz kontejnerja, nato jo z Mac-a preneseš
s `scp` (točna ukaza sta v [splosno.md](splosno.md) pod »Prenos datoteke s strežnika na Mac«).

## Stran »Webcam review«

Admin → **Content → Webcam review**. Privzeto kaže samo **Pending** (čaka pregled), najvišje ocene na vrhu.

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Place** | ime plaže, pod njim `#ID · država`; klik odpre lokacijo plaže na zemljevidu (OpenStreetMap) |
| **Webcam** | naslov kamere na Windy, pod njim Windy ID; klik odpre kamero na windy.com |
| **Distance** | razdalja kamera–plaža v metrih |
| **Score** | ocena 0–95: zelena ≥ 70, rumena 40–69 |
| **Why** | zakaj taka ocena, npr. `distance +40 · category +25 · name +30 (0.92) · runner-up 50` |
| **Status** | Pending / Auto-approved / Approved / Rejected |
| **Source** | `matcher` (sistem), `legacy` (stara kamera), `manual` (dodal ročno) |
| **Primary** | glavna kamera plaže (prikazana prva) |

### Filtri

Status, država (**Country**), tip kraja (**Place type**: sea, lake, river, pool) in vir (**Source**).
Za vse prikazane kamere izberi Status = Approved ali Auto-approved.

### Akcije

| Gumb | Kaj naredi |
|---|---|
| **Preview** | odpre kamero točno tako, kot bo na strani plaže, in povezavo na windy.com |
| **Approve** | prikaže kamero na strani. Prva odobrena kamera plaže postane glavna. |
| **Reject** | kamera se na tej plaži ne prikaže **in je sistem tej plaži nikoli več ne predlaga**. Če je bila glavna, glavna postane naslednja odobrena. |
| **Set primary** | ta kamera se na strani prikaže prva (samo pri odobrenih) |
| Izbira več vrstic → **Approve / Reject** | isto za več vrstic naenkrat |
| **Add webcam by hand** (zgoraj desno) | izberi plažo in prilepi Windy ID, povezavo z windy.com ali embed kodo. Sistem preveri, da kamera obstaja in se predvaja, in jo takoj odobri. |

### Kako se odločiti

1. Klikni **Preview** — ali kamera kaže morje/plažo, ne parkirišča ali mesta?
2. Klikni ime plaže — ali je kamera res pri tej plaži (stolpec Distance, zemljevid)?
3. Če je prava: **Approve**. Če ni: **Reject**. Če nisi prepričan, pusti Pending.

Dobro pravilo: raje brez kamere kot napačna kamera.

### Pravila, ki jih sistem upošteva

- Ena kamera je lahko prikazana samo pri **eni** plaži. Če jo želiš odobriti pri drugi, jo najprej zavrni pri prvi
  (sistem ti to napiše).
- Tvoje odločitve (Approved, Rejected, ročno dodane) sistem **nikoli ne spremeni**, tudi ob ponovnem ujemanju.
- Spremembe so v API takoj; spletna stran plaže se osveži v največ eni uri (predpomnjena stran).

## Kako sistem točkuje

| Znak | Točke |
|---|---|
| Razdalja do 300 m / do 1 km / do 3 km | +40 / +25 / +10 |
| Kategorija »beach« | +25 |
| Kategorija coast, lake, river ali port | +10 |
| Ime plaže v naslovu kamere | +30 |
| Samo ime kraja v naslovu | +5 |
| Kategorija city, traffic, mountain, airport, indoor, building, square | −20 |

Kamere dlje od 3 km, neaktivne ali brez predvajalnika sploh niso kandidati.

- **≥ 70 točk in vsaj 15 pred naslednjo** → samodejno odobrena (**Auto-approved**).
- **40–69** (ali ≥ 70, a tesno ob drugi kameri) → **Pending**, odločiš ti.
- **Pod 40** → plaža ostane brez kamere.

Bazeni, kopališča in terme imajo svoja pravila (kategoriji indoor in sportArea štejeta plus).

## Mesečna osvežitev

Ko je prvi uvoz končan, v `.env.dev` (kasneje uat/prod) nastavi:

- `WEBCAMS_REFRESH_ENABLED=true`
- `WEBCAMS_ALERT_EMAIL=` tvoj e-naslov (neobvezno)

Sistem vsak dan ob 04:20 preveri, ali je minilo 30 dni od zadnjega uvoza (ali pa je treba nadaljevati prekinjenega).
Kamere, ki ne delujejo več, izgubijo oznako glavne; glavna postane naslednja delujoča. Plaže z novimi kamerami v
bližini se ponovno točkujejo. Če več kot 10 % plaž izgubi glavno kamero, dobiš e-pošto.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| `WINDY_API_KEY is not set.` | ključ ni v `.env.<okolje>` ali dev ni bil redeployan |
| `Daily budget reached` | dnevna omejitev; poženi isti ukaz jutri ali zvišaj `WINDY_DAILY_REQUEST_BUDGET` |
| Pri Approve: »already shown on place …« | kamera je že prikazana pri drugi plaži; tam jo najprej zavrni |
| Pri ročnem dodajanju: »cannot be shown (… player not_public)« | lastnik kamere na Windy ne dovoli vgradnje; te kamere ne moremo prikazati |
| Plaža nima kamere, čeprav je na Windy | kamera je dlje od 3 km, ima prenizko oceno ali je zavrnjena; dodaj jo ročno |
| Na strani ni kamere takoj po uvozu | uvozu morata slediti `embed-check` in `match` (koraki 2–4) |

## Za razvijalce

Tehnično ozadje: `docs/tasks/TASK-006-windy-webcams.md`, `docs/technical/windy-webcams-api.md`, ADR 2026-10-14
»Windy webcams: local copy, local matching, direct iframe«. Nastavitve točkovanja: `config/webcams.php`.
