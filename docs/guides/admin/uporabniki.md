# Uporabniki in administratorji

> Velja od: 2026-10-14 · Kje: Admin → **User Management → Users**, **Genders** · Okolje: dev, uat, prod (uvoz starih uporabnikov samo prod)

## Čemu služi

**Users** je seznam vseh računov: obiskovalci, ki so se registrirali na spletni strani ali v mobilni aplikaciji
(z e-pošto ali Googlom), in administratorji. Tu lahko poiščeš uporabnika, popraviš podatke, nekomu dodeliš dostop
do admina in pregledaš uporabnike, uvožene s stare strani.

**Genders** je kratek šifrant spolov (s prevodi), ki ga uporablja polje **Gender** pri uporabniku.

Uporabniki so osebni podatki (GDPR): odpiraj, spreminjaj in izvažaj jih samo, ko je to potrebno.

## Seznam »Users«

Privzeto so najnovejši na vrhu. Iščeš lahko po imenu, uporabniškem imenu, e-pošti, imenu in priimku ter telefonu.

### Stolpci

Nekateri so privzeto skriti; prikažeš jih z ikono stolpcev nad tabelo.

| Stolpec | Pomen | Privzeto viden |
|---|---|---|
| **ID** | številka računa | ne |
| **Name** | prikazno ime | da |
| **Username** | uporabniško ime | da |
| **Email** | e-pošta (z njo se prijavi) | da |
| **First Name**, **Last Name** | ime, priimek | da |
| **Mobile** | telefon | ne |
| **Gender** | spol | ne |
| **Country** | država | da |
| **Birth Date** | datum rojstva | ne |
| **Status** | status računa (zeleno Active, rdeče Inactive) | da |
| **Admin** | kljukica = ima dostop do admina | da |
| **Registration** | od kod se je registriral (npr. mobile, web) | ne |
| **Beaches** | koliko plaž je dodal | da |
| **Email verified at**, **Created at**, **Updated at** | datumi | ne |

### Filtri

| Filter | Možnosti |
|---|---|
| **Admin Users** | **All users** / **Only admins** / **Only non-admins** |
| **Status** | status računa |
| **Gender** | spol |
| **Country** | država |
| **Imported from old site** | **All users** / **Only imported** / **Only new** — glej »Uvoz s stare strani« |
| **Register type** | **Mobile** / **Web** |

Akcije: **Edit** v vrstici, **New user** zgoraj desno, izbira več vrstic → **Delete selected**.

## Obrazec uporabnika

| Razdelek | Polje | Pomen |
|---|---|---|
| **Account Information** | **Name** (obvezno) | prikazno ime |
| | **Username** | uporabniško ime; ne sme biti enako drugemu |
| | **Email** (obvezno) | e-pošta; ne sme biti enaka drugemu računu |
| | **Password** | pri novem uporabniku obvezno; pri urejanju **pusti prazno**, če gesla ne menjaš |
| | **Email verified at** | kdaj je potrdil e-pošto |
| | **Admin Access** | dostop do admina (»Allow this user to access the admin panel«) |
| **Personal Information** | **First Name**, **Last Name**, **Date of Birth**, **Gender** | osebni podatki |
| **Contact & Location** | **Mobile Phone**, **Country** | kontakt |
| **Status & Metadata** | **Status** (obvezno) | npr. Active |
| | **Registration Type** | od kod je prišel (npr. mobile, web) |

Na koncu **Save changes** (pri novem **Create**).

## Kako nekoga narediš za administratorja

1. **Users** → poišči osebo (npr. po e-pošti) → **Edit**.
2. Vklopi **Admin Access**.
3. Če se je ta oseba doslej prijavljala samo z Googlom, ji v **Password** vpiši geslo (admin pozna samo prijavo z
   e-pošto in geslom). Geslo ji sporoči po varni poti.
4. **Save changes**.
5. Oseba se prijavi na naslovu admina za to okolje ([splosno.md](splosno.md)). Dostop velja **samo v okolju**, kjer
   si ga nastavil — dev, uat in prod imajo vsak svojo bazo.

Odvzem dostopa: izklopi **Admin Access** → **Save changes**.

Pozor:

- administratorja ne moreš izbrisati prek spletne strani ali aplikacije (API to zavrne), v adminu pa ga lahko
  izbrišeš (**Delete**). Pazi, da ne odvzameš dostopa ali izbrišeš zadnjega administratorja;
- ali že prijavljen uporabnik po odvzemu dostopa takoj izgubi dostop, ni jasno iz kode — preveri.

## Nov uporabnik

**New user** → izpolni vsaj **Name**, **Email**, **Password** in **Status** → **Create**.
Za novega administratorja vklopi še **Admin Access**.

## Brisanje uporabnika

**Edit** → **Delete** (ali več naenkrat: izbira → **Delete selected**). Račun se izbriše takoj. Kaj se zgodi s
plažami, ki jih je dodal, ni jasno iz kode — preveri. Za prošnje »izbrišite moj račun« raje naj uporabnik izbriše
račun sam v aplikaciji ali na strani (tam je za to pripravljen postopek).

## Šifrant »Genders«

| Stolpec | Pomen |
|---|---|
| **ID** | številka |
| **Code** | interna koda (npr. male, female, other) |
| **English Name** | angleško ime |
| **Translations** | število prevodov / 14 (zeleno ≥ 14) |

Filter **Translations**: **All genders** / **Complete (all 14 languages)** / **Missing translations**.
(Podprtih jezikov je dejansko 19; oznaka »14« je zastarela.)

Na obrazcu je samo **Code** (obvezno, do 20 znakov). Pod njim je razdelek **Translations**, kjer z
**New translation** dodaš ime v jeziku: **Language**, **Name** (obvezno), **Slug (URL-friendly)**.
Kode spola ne spreminjaj, če jo uporabniki že imajo.

## Uvoz s stare strani (`users:migrate-from-old-db`)

Ukaz prenese račune s stare strani beachrex.com (iz datoteke `database/sql/user.sql` v projektu) v novo bazo.

**Kdaj:** samo **v produkciji, po zagonu** nove strani (GDPR: osebne podatke prenesemo samo tja, kjer jih res
rabimo; ne na dev ali uat).

Kaj naredi:

- vsakemu staremu uporabniku ustvari račun z imenom, e-pošto, uporabniškim imenom, imenom in priimkom, datumom
  rojstva, spolom, telefonom, državo, statusom in datumom registracije;
- **gesla s stare strani se ne prenesejo** (bila so shranjena nevarno). Vsak uvožen račun dobi naključno geslo,
  ki ga nihče ne pozna;
- uvoženi uporabnik se prijavi **z Googlom** (sistem ga poveže po isti e-pošti) ali si nastavi novo geslo prek
  **pozabljenega gesla** na spletni strani (deluje, ko so v `.env.prod` nastavljene spremenljivke `MAIL_*`);
- nihče od uvoženih ne dobi dostopa do admina;
- če račun z isto e-pošto že obstaja, ga preskoči (»Skipped (already exist)«) in ga ne spremeni;
- zapomni si, koga je že obdelal, zato ga lahko varno ponoviš ali nadaljuješ.

**Datoteke `database/sql/user.sql` ne briši** (odločitev lastnika).

Ukaz poganjaš na strežniku. Pred vsak ukaz dodaj:

```
docker compose -f /opt/beachrex/deploy/docker-compose.vps.yml --env-file /opt/beachrex/envs/.env.prod exec -T api_prod
```

Ukaz **nima** `--dry-run`. Namesto tega najprej poskusi na 10 uporabnikih:

| Korak | Ukaz | Kaj naredi |
|---|---|---|
| 1 | `php artisan users:migrate-from-old-db --test` | uvozi **samo prvih 10** uporabnikov (zares, ne poskusno) |
| 2 | — | Admin → **Users** → filter **Imported from old site** = **Only imported**: preveri 10 računov (imena, šumniki, država, spol) |
| 3 | `php artisan users:migrate-from-old-db --resume` | uvozi vse preostale (že obdelanih ne pošlje znova) |

Druge možnosti:

| Možnost | Kaj naredi |
|---|---|
| `--limit=500` | uvozi največ 500 uporabnikov |
| `--resume` | nadaljuje, kjer je prejšnji zagon ostal |
| `--reset` | pozabi, koga je že obdelal (računov ne izbriše). Ne uporabljaj brez razloga. |
| `--file=…` | druga datoteka namesto `database/sql/user.sql` |

Na koncu izpiše tabelo: **Successfully migrated**, **Failed**, **Skipped (already exist)**, **Total processed**.

### Filter »Imported from old site«

- **Only imported**: računi, ki jih je ustvaril ta ukaz;
- **Only new**: vsi ostali (registrirani na novi strani ali v aplikaciji);
- računi, ki so že obstajali in jih je ukaz preskočil, štejejo kot »new«.

## Pogoste težave

| Težava | Vzrok in rešitev |
|---|---|
| »The email has already been taken.« / »The username …« | drug račun že ima to e-pošto ali uporabniško ime |
| Admin se ne more prijaviti | ni vklopljen **Admin Access**, napačno geslo, ali dostop nastavljen v drugem okolju |
| Uvožen uporabnik se ne more prijaviti s starim geslom | pričakovano; naj uporabi Google ali pozabljeno geslo |
| Pozabljeno geslo ne pošlje e-pošte | v `.env.<okolje>` manjkajo `MAIL_*` ali okolje po spremembi ni bilo ponovno nameščeno |
| Pri uvozu »Failed« | napaka pri posameznem računu (npr. manjkajoča e-pošta); izpiše se razlog. Ponovni zagon z `--resume` jih poskusi znova. |
| »SQL file not found« | datoteke `database/sql/user.sql` ni na strežniku — ni jasno iz kode, ali je v sliki za prod — preveri |

## Za razvijalce

Viri: `app/Filament/BrAdmin/Resources/UserResource.php`, `GenderResource.php` (+ `Pages/`,
`GenderResource/RelationManagers/TranslationsRelationManager.php`). Dostop do panela:
`App\Models\User::canAccessPanel()` (`is_admin === true`). Uvoz: `app/Console/Commands/MigrateUsersFromOldDb.php`,
stanje v `user_migration_status` (filter bere vrstice s `status = success`). Odločitve: `docs/HANDOFF.md` →
»Old user migration« (možnost a, 2026-10-09), pozabljeno geslo `POST /api/v1/forgot-password`.
