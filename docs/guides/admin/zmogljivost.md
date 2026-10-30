# Zmogljivost API in boti (`ops:perf-report`)

Po izpadu 2026-10-30 (bot je strani nalagal s ~130 naslovov ene mreže, API je odgovarjal 16 s) ima API več plasti
predpomnilnika in ukaz, ki na enem mestu pokaže, kako dobro delujejo. Tehnično ozadje: runbook
`docs/runbooks/performance-and-bots.md` (angleško), odločitev: ADR 2026-10-30 »API performance«.

## Kaj se zgodi, ko v adminu nekaj shraniš

- Seznami v API (države, regije, otoki, mesta, filtri, statične strani, prva stran, priljubljene plaže …) se ob
  vsakem shranjevanju ali brisanju vsebine počistijo takoj.
- Na spletni strani in v aplikaciji je sprememba vidna **v približno 1 minuti** (toliko lahko odgovor hrani nginx).
- Če si podatke spreminjal z ukazom, ki piše neposredno v bazo (npr. `translations:ai`, `content:ai`), na koncu
  poženi `php artisan cache:clear-api --type=all`.

## Ukaz `ops:perf-report`

Kje: na strežniku, v API zabojniku okolja (ukaz samo bere, ničesar ne spreminja):

```bash
docker exec $(docker ps -qf name=api_prod | head -1) php artisan ops:perf-report
```

Kaj pokaže:

| Vrstica | Pomen | Kdaj je slabo |
|---|---|---|
| API response cache | zadetki/zgrešeni odgovori predpomnilnika danes (UTC) | delež zadetkov stalno pod ~80 % |
| Redis | poraba pomnilnika, izrinjeni ključi, največje skupine ključev | »evicted« hitro raste in Redis je poln |
| PHP-FPM www | delavci v uporabi, čakalna vrsta, kolikokrat je zmanjkalo delavcev | čakalna vrsta > 0 dlje časa |
| PHP-FPM health | ločen bazen samo za `/api/v1/health` | – |
| Database | aktivne poizvedbe, najdaljša | najdaljša > nekaj sekund |

Isto poročilo se izpiše tudi v obvestilu vsakega deploya API (»API rollout … Performance report«).

## Opozorilo po e-pošti

Če v okoljsko datoteko (`/opt/beachrex/envs/.env.prod`) dodaš `OPS_ALERT_EMAIL=tvoj@naslov`, ukaz vsakih 10 minut
preveri meje (čakalna vrsta PHP ≥ 5, zmanjkalo delavcev, Redis poln in izriva ključe) in pošlje največ eno sporočilo
na uro. Brez te nastavitve opozorila samo zapiše v dnevnik.

## Pogoste napake

- Ukaz pognan v napačnem zabojniku (`front_prod` nima PHP) → uporabi `api_<okolje>`.
- »no status URL« pri PHP-FPM: zabojnik je star (pred tem deployem); naslednji deploy to uredi.
