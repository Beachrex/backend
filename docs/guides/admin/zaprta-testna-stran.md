# Zaprta testna stran (dev / uat) z e-poštno povezavo

**Čemu služi:** dev.beachrex.com in uat.beachrex.com vidi samo, kdor dobi enkratno povezavo po e-pošti. Produkcija
(beachrex.com) je vedno odprta, tudi če je stikalo vklopljeno. Admin (dock-dev, dock-uat) in API se ne zapreta.

**Kdo lahko vstopi:** administratorji tega okolja (uporabnik z oznako *Admin* v adminu, Users) in naslovi v
`PREVIEW_ALLOWED_EMAILS`. Vsak drug naslov dobi enako sporočilo, a e-pošte ne prejme.

## Kako vstopiš

1. Odpri https://dev.beachrex.com (ali uat). Stran te preusmeri na obrazec »To je testna stran Beachrex«.
2. Vpiši svoj e-naslov in klikni **Pošlji mi povezavo**.
3. V e-pošti klikni **Odpri testno stran**, nato na strani še gumb **Odpri testno stran**.
4. Brskalnik si dostop zapomni 30 dni (na tej napravi in v tem brskalniku).

Povezava velja 15 minut in deluje enkrat. Na en naslov pride največ 1 e-pošta na minuto in 5 na uro.

## Vklop (lastnik, na strežniku)

1. Ustvari skrivnost: `openssl rand -hex 32` (vsako okolje svojo; nikoli je ne pošiljaj v klepet).
2. V `/opt/beachrex/envs/.env.front.dev` dodaj:
   `PREVIEW_GATE_ENABLED=true` in `PREVIEW_GATE_SECRET=<skrivnost>`.
3. V `/opt/beachrex/envs/.env.dev` (API) dodaj `PREVIEW_GATE_ENABLED=true`, po želji
   `PREVIEW_ALLOWED_EMAILS=tester1@example.com,tester2@example.com`. `MAIL_*` in `FRONTEND_URL` morata biti nastavljena.
4. Najprej znova namesti **API** (GitHub → Beachrex/backend → Actions → Deploy to VPS → Run workflow → dev), nato
   **spletno stran** (Beachrex/web-client → Actions → Deploy to VPS → Run workflow → dev).
5. Enako za uat (`.env.front.uat`, `.env.uat`, okolje uat).

## Izklop in odvzem dostopa

- Izklop: `PREVIEW_GATE_ENABLED=false` (ali vrstico izbriši) v obeh datotekah, nato ponovna namestitev obeh.
- Vsem odvzameš dostop (vsi morajo znova po povezavo): nova `PREVIEW_GATE_SECRET`, ponovna namestitev spletne strani.
- Posameznemu testerju: odstrani naslov iz `PREVIEW_ALLOWED_EMAILS` in zamenjaj skrivnost.

## Pogoste napake

- Stran kaže »This test website is closed«: `PREVIEW_GATE_ENABLED=true`, skrivnost pa manjka ali je krajša od 32
  znakov. Dodaj jo in znova namesti spletno stran.
- E-pošte ni: naslov ni admin in ni v `PREVIEW_ALLOWED_EMAILS`, API nima `PREVIEW_GATE_ENABLED=true` ali `MAIL_*`,
  ali si prosil večkrat v eni minuti. Preveri tudi vsiljeno pošto.
- »Povezava ni veljavna …«: povezava je starejša od 15 minut ali je že bila uporabljena — zahtevaj novo.
