# Pogovori z AI klepetom (zgodovina)

> Velja od: 2026-10-29 · Kje: Admin → **AI → Chat conversations** (`/ai-conversations`, npr.
> https://dock-dev.beachrex.com/ai-conversations) · Okolje: dev, uat, prod (vsako ima svoje pogovore)

## Čemu služi

Pregled, **kaj ljudje sprašujejo AI vodiča po plažah in kaj jim odgovori** — da vidimo, kje odgovori niso dobri, česa
ljudje iščejo in česa nimamo, ter se na tej podlagi izboljšujemo. Stran je **samo za branje**: pogovorov ne moreš
urejati, dodajati ali brisati.

Prikazani so vsi pogovori s spletne strani in mobilne aplikacije (oba gresta prek istega API), **anonimni** in
**prijavljeni** uporabniki, z obema načinoma delovanja klepeta (`agent` in `pipeline`).

## Seznam pogovorov

Ena vrstica = en pogovor (ena seja klepeta). Najnovejši so na vrhu. Nad seznamom so tri številke za zadnjih 14 dni:
pogovori danes (z grafom po dnevih), delež prijavljenih, vprašanja brez odgovora.

| Stolpec | Pomen |
|---|---|
| Started | kdaj se je pogovor začel |
| Last message | čas zadnjega sporočila |
| User | e-pošta prijavljenega uporabnika ali »anonymous« |
| Lang | jezik strani, s katere je pisal (sl, en, de …) |
| Engine | način klepeta: `agent` (AI sam išče po plažah z orodji) ali `pipeline` (starejši način) |
| Messages | število sporočil (vprašanja + odgovori) |
| First question | prvo vprašanje (skrajšano; cela vsebina ob premiku miške) |
| Opened on | stran, na kateri je odprl klepet (npr. `beach #42 · /sl/plaze/...`, `city · /sl/kraji/split`) |
| Tokens, Cost | porabljeni žetoni in strošek AI za ves pogovor v USD |
| Errors | število vprašanj, na katera ni dobil odgovora (rdeče, če > 0) |

Stolpce Engine, Tokens in Cost lahko skriješ (ikona stolpcev desno nad tabelo).

### Iskanje

Polje **Search** išče po **besedilu vseh sporočil** (vprašanja in odgovori AI) in po e-pošti uporabnika. Velike in
male črke niso pomembne. Primer: »otroci« najde pogovore, kjer je to besedo napisal uporabnik ali AI.

### Filtri

| Filter | Kaj naredi |
|---|---|
| Started from / until | pogovori, začeti v tem obdobju |
| User | vsi / samo prijavljeni / samo anonimni |
| Language | jezik |
| Engine | `agent` ali `pipeline` |
| Has an error | samo pogovori z vsaj enim vprašanjem brez odgovora |

## Podrobnosti pogovora

Klikni **View** v vrstici. Zgoraj so podatki pogovora (začetek, zadnje sporočilo, uporabnik, jezik, način, paket,
število sporočil, vprašanja brez odgovora, žetoni, strošek, uporabljeni AI model, stran, ID seje), spodaj **prepis**:

- **User** (sivo) — vprašanje s časom in stranjo, s katere je bilo poslano; pri načinu `agent` tudi orodja, ki jih
  je AI uporabil za odgovor (npr. `search_beaches({"city":"Split"})`).
- **Assistant** (modro, zamaknjeno) — odgovor AI s časom, modelom in stroškom tega odgovora ter plaže (kartice), ki jih
  je uporabnik videl pod odgovorom. »refused (off-topic)« pomeni, da je AI zavrnil vprašanje, ki ni o plažah.
- Vprašanje **brez odgovora** ima rdeč okvir in razlog:

| Razlog | Pomen |
|---|---|
| the AI provider was busy or timed out | ponudnik AI (Anthropic/OpenAI) je bil preobremenjen ali prepočasen; uporabnik je dobil »poskusi znova čez minuto« |
| server error | napaka na našem strežniku; razvijalec jo najde v dnevniku po ID-ju zahteve |
| paid feature (multi-day trip plan) | vprašanje za večdnevni načrt potovanja, ki je plačljiv |

Ni zabeleženo: vprašanja, ki jih je klepet zavrnil, preden je začel delati (izklopljen klepet, dosežena dnevna
omejitev stroškov, preveč vprašanj v kratkem času, neuspešno preverjanje Turnstile).

## Kako to uporabiš za izboljšave

1. Filtriraj **Has an error** — katera vprašanja ostanejo brez odgovora in zakaj.
2. Išči po besedah iz odgovorov, ki kažejo na slab odgovor (npr. »ne najdem«, »I couldn't find«, »Sorry«).
3. Poglej **Opened on**: na katerih straneh ljudje odpirajo klepet in kaj tam sprašujejo.
4. Ugotovitve (npr. »manjka podatek o parkiriščih«, »AI predlaga plaže iz napačne države«) zapiši in jih predaj
   razvijalcu; besedilo vprašanja lahko kopiraš kot primer.

## Zasebnost

- Pogovori lahko vsebujejo osebne podatke, ki jih je uporabnik napisal sam. Stran vidijo **samo skrbniki**; vsebine ne
  kopiraj drugam, kot je potrebno za izboljšavo.
- Stran **ne prikaže** naslova IP (shranjen je samo kot zgoščena vrednost za omejevanje števila vprašanj) in ne
  podatkov o brskalniku. Besedilo pogovorov ni v globalnem iskanju admina.
- **Koliko časa hranimo pogovore, še ni določeno** (odločitev lastnika, glej spodaj). Do takrat se ne briše nič.
  Če uporabnik prosi za izbris svojega pogovora, to zaenkrat naredi razvijalec (izbris seje izbriše tudi njena sporočila).

## Odprte odločitve lastnika

- **Rok hrambe:** predlog 180 dni (ali 90), nato samodejni izbris pogovorov; strošek (`ai_usage`) ostane brez povezave
  na pogovor.
- **Politika zasebnosti** (razdelek »AI beach assistant«) zdaj pravi le, da se vprašanja in odgovori »obdelajo, da se
  pripravi odgovor«, in kot ponudnika navaja samo OpenAI. Dopolniti jo je treba: da pogovore hranimo in jih beremo za
  izboljšanje storitve, rok hrambe, ponudnika Anthropic.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Menija »AI« ne vidim | prijavi se z računom, ki ima pravice skrbnika |
| Pogovor, ki sem ga pravkar imel, ni na seznamu | osveži stran; pogovor brez sporočil (samo odprt klepet) se ne prikaže |
| Stari pogovori nimajo strani »Opened on« ali stroška po odgovoru | ti podatki se shranjujejo od 2026-10-29 naprej |
| Strošek je »—« | za ta pogovor ni zapisa o porabi (npr. vsa vprašanja so ostala brez odgovora) |

## Za razvijalce

- Stran: `app/Filament/BrAdmin/Resources/AiConversationResource.php` (+ `Pages/`, `Widgets/AiConversationStats.php`),
  prepis `resources/views/filament/ai-conversations/transcript.blade.php`.
- Podatki: `ai_sessions` (seja: `engine`, `page_context` prve strani), `ai_messages` (`content`, `context` z orodji
  oziroma plažami, `page_context`, `error`, `request_id`), `ai_usage` (model, strošek; povezava prek `request_id`).
  Migracija `2026_10_29_180000_add_chat_history_fields_to_ai_tables`.
- Shranjevanje: `App\Http\Controllers\AI\ChatController` (`saveMessage`, `recordFailedTurn`). Vprašanja brez odgovora
  se ne pošiljajo modelu kot zgodovina.
- ADR: `docs/ai/implementation/DECISIONS.md` »AI chat history in the admin« (2026-10-29).
