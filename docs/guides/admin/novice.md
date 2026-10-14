# Prijave na novice

> Velja od: 2026-10-14 · Kje: Admin → **Content → Newsletter Subscribers** · Okolje: dev, uat, prod

## Čemu služi

V nogi spletne strani je obrazec za prijavo na e-novice (e-poštni naslov + kljukica za soglasje s politiko
zasebnosti). Vsaka prijava se shrani v našo bazo in jo vidiš tukaj. Ta stran je **seznam prijavljenih**: pregleduješ
ga, označiš, kdo je prenesen v Klaviyo, in odjaviš ali izbrišeš naslove.

Kaj sistem **ne** dela (stanje kode na ta dan):

- **Ne pošilja e-novic** in **ni povezan s Klaviyo**. Polje **Synced to ESP (Klaviyo)** je samo kljukica, ki jo
  nastaviš ti, ko naslove sam preneseš v Klaviyo.
- Nima izvoza (gumba za prenos seznama v Excel/CSV). Kako naslove prenesti v Klaviyo, ni jasno iz kode — preveri
  (npr. z razvijalcem).
- Nima povezave za odjavo. Odjave, ki jih ljudje naredijo v Klaviyo, se v našo bazo ne prenesejo same; odjavo moraš
  vpisati tukaj (glej [Odjava](#odjava)).

## Kako pride prijava v seznam

1. Obiskovalec v nogi vpiše e-pošto, obkljuka soglasje in klikne prijavo.
2. Sistem preveri, da je naslov veljaven (tudi da domena sprejema pošto) in da je soglasje obkljukano.
3. Naslov shrani z malimi črkami. Shrani tudi: čas prijave, vir (`footer`), jezik strani, naslov IP in brskalnik.
4. Če naslov že obstaja:
   - je že prijavljen (**Active**) → nič se ne spremeni, obiskovalec dobi sporočilo »že ste prijavljeni«;
   - je bil odjavljen → se **ponovno prijavi**: status **Active**, kljukica **Synced** se odstrani (treba ga je spet
     prenesti v Klaviyo), čas prijave, IP, jezik in brskalnik se posodobijo.

Ročnega dodajanja v adminu **ni** (ni gumba »New«): naslov se lahko prijavi samo sam, prek obrazca, s soglasjem.

## Seznam

Admin → **Content → Newsletter Subscribers**. Najnovejše prijave na vrhu.

### Stolpci

| Stolpec | Pomen |
|---|---|
| **Email** | e-poštni naslov; klik nanj ga kopira (»Email copied«) |
| **Status** | **Active** (zelena) = prijavljen; **Disabled** (rdeča) = odjavljen |
| **Synced** | zelena kljukica = označen kot prenesen v Klaviyo; rdeč križec = še ne |
| **GDPR** | soglasje s politiko zasebnosti (pri prijavah prek obrazca vedno da) |
| **Source** | od kod je prijava (zdaj vedno `footer`) |
| **Locale** | jezik strani ob prijavi (npr. `sl`, `de`) |
| **Subscribed** | čas prijave |
| **Synced** (skrit), **Created** (skrit) | čas označitve »Synced« in čas nastanka zapisa; vklopiš ju z ikono stolpcev |

Iskanje zgoraj išče po e-pošti.

### Filtri

| Filter | Kaj naredi |
|---|---|
| **Status** | samo **Active** ali samo **Disabled** |
| **Synced to ESP** | **Synced** / **Not synced** |
| **GDPR Consent** | s soglasjem / brez |

Za seznam, ki ga je treba prenesti v Klaviyo: **Status** = Active in **Synced to ESP** = Not synced.

### Akcije

| Gumb | Kaj naredi |
|---|---|
| **Edit** (v vrstici) | odpre zapis |
| **Delete** (v vrstici) | trajno izbriše zapis |
| označi vrstice → **Mark as Synced** | označi izbrane kot prenesene v Klaviyo in zapiše čas (obvestilo »Marked as Synced«) |
| označi vrstice → **Unsubscribe** | nastavi izbrane na **Disabled** (obvestilo »Unsubscribed«) |
| označi vrstice → **Delete selected** | trajno izbriše izbrane |

## Urejanje zapisa

Razdelek **Subscriber Details**:

| Polje | Ali ga lahko spremeniš | Pomen |
|---|---|---|
| **Email** | ne (zaklenjeno) | za drug naslov zapis izbriši in naj se oseba prijavi znova |
| **Status** | da | **Active** = prijavljen, **Disabled** = odjavljen. Na izbiro so vsi statusi sistema; uporabljaj samo ta dva. |
| **Synced to ESP (Klaviyo)** | da | vklopi, ko je naslov prenesen v Klaviyo. Ročni vklop tukaj **ne** zapiše časa **Synced At** (to naredi samo **Mark as Synced**). |
| **GDPR Consent Accepted** | ne | nastavi ga obrazec ob prijavi |
| **Source** | da | vir prijave (največ 50 znakov) |
| **Subscription Locale** | ne | jezik ob prijavi |

Razdelek **Audit Information** (samo za branje): **Subscribed At**, **Synced At**, **IP Address**, **Created At**.

Shrani z **Save changes**. Zgoraj desno je še **Delete**.

## Pogosta opravila

### Prenos novih naslovov v Klaviyo

1. Filtra: **Status** = Active, **Synced to ESP** = Not synced.
2. Naslove prenesi v Klaviyo (način prenosa ni del admina — ni jasno iz kode, preveri).
3. Označi iste vrstice → **Mark as Synced**.

Ponovno prijavljeni naslovi se sami vrnejo med »Not synced«, zato jih ob naslednjem prenosu dobiš znova.

### Odjava

Ko nekdo prosi za odjavo ali se odjavi v Klaviyo:

1. Poišči naslov (iskanje).
2. Označi vrstico → **Unsubscribe** (ali **Edit** → **Status** = Disabled → **Save changes**).
3. Odjavi ga tudi v Klaviyo, če se ni odjavil tam (admin Klaviyo ne obvesti).

### Izbris na zahtevo (GDPR)

Če oseba zahteva izbris podatkov: poišči naslov → **Delete**. Zapis se trajno izbriše iz naše baze (tudi IP in
brskalnik). V Klaviyo ga izbriši posebej.

Odjava (**Unsubscribe**) podatke obdrži in naslov ostane v bazi; izbris (**Delete**) jih odstrani. Če oseba po
izbrisu znova izpolni obrazec, nastane nov zapis.

## GDPR — kaj je dobro vedeti

- Pravna podlaga je **soglasje** (politika zasebnosti, razdelek »E-novice«): naslov uporabljamo samo za novice o
  Beachrexu, odjava je mogoča kadar koli.
- Politika zasebnosti pravi, da e-novice hranimo »v lastni podatkovni zbirki«, in ne omenja Klaviyo. Preden naslove
  preneseš v Klaviyo, je treba posodobiti politiko zasebnosti (Klaviyo kot obdelovalec, prenos v ZDA) — to je
  vsebinska/pravna odločitev, ne funkcija admina.
- Sistem poleg e-pošte shrani še **naslov IP** in **brskalnik** (kot dokaz soglasja). Politika zasebnosti pri
  e-novicah navaja samo e-pošto, čas prijave in privolitev — tudi to je smiselno uskladiti.
- Ne dodajaj naslovov drugih ljudi in ne prenašaj v Klaviyo naslovov s statusom **Disabled**.
- Sistem ne briše starih odjavljenih naslovov sam; koliko časa jih hranimo, ni določeno v kodi.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| Ni gumba za dodajanje naslova | namenoma; prijava je mogoča samo prek obrazca s soglasjem |
| E-pošte ne morem popraviti | namenoma zaklenjena; izbriši zapis, oseba naj se prijavi znova |
| Odjavljen naslov je spet **Active** | oseba je ponovno izpolnila obrazec; to je pravilno |
| Obiskovalec pravi, da prijava ne deluje | naslov ni veljaven ali domena ne sprejema pošte, soglasje ni obkljukano, ali je poskusil več kot 10-krat v minuti |
| **Synced At** je prazen, čeprav je kljukica vklopljena | kljukica je bila vklopljena ročno v obrazcu; uporabi **Mark as Synced** |
| Odjava v Klaviyo se tukaj ne vidi | povezave ni; odjavi ročno z **Unsubscribe** |

## Za razvijalce

- Admin: `app/Filament/BrAdmin/Resources/NewsletterSubscriberResource.php` (seznam brez `CreateAction`).
- API: `POST /api/v1/newsletter/subscribe` (`NewsletterSubscriberController`, `throttle:10,1`, `email:rfc,dns`,
  `consent` mora biti `accepted`). Model `App\Models\NewsletterSubscriber` (e-pošta v malih črkah).
- Klaviyo/ESP sinhronizacija ni implementirana (`DEV_HANDOFF.md`, vnos o Newsletter, »for a later ESP/Klaviyo sync,
  not implemented«). Web: `src/components/Footer/FooterNewsletter.tsx`, `src/lib/newsletter.ts`.
- Politika zasebnosti: `database/content/static-pages/{locale}.json`, `privacy-policy` (glej [staticne-strani.md](staticne-strani.md)).
