# AI modeli (izbira modela za klepet in načrtovalec)

> Velja od: 2026-10-30 · Kje: Admin → **AI → AI models** · Okolje: dev, uat, prod (vsako ima svojo izbiro)

## Čemu služi

Tu izbereš, kateri AI model odgovarja v **AI klepetu** in kateri piše besedilo **AI načrtovalca potovanj** (plačljivi
načrt po dnevih). Izbira velja takoj za naslednje vprašanje, **brez deploya**, in samo na okolju, kjer jo nastaviš
(izbira na dev ne spremeni prod).

Če ne izbereš ničesar (prazno polje), se uporablja **privzeti model strežnika** (nastavitev v `.env`). Na produkciji je
to zdaj:

| Funkcija | Privzeto (prazno polje) |
|---|---|
| AI klepet | **Claude Opus 5.5** (`AI_CHAT_ENGINE=agent`, `AI_CHAT_PROVIDER=anthropic`) |
| AI načrtovalec potovanj | **Claude Opus 5.5** od 2026-10-30 (lastnik: »AI trip planner mora biti najdražji/najboljši model«; prej GPT-4o mini). Če okolje nima ključa `ANTHROPIC_API_KEY`, ostane na `OPENAI_MODEL`. |

## Koraki

1. Odpri **AI → AI models**.
2. V razdelku **AI chat** izberi model v polju »AI chat model«; v razdelku **AI trip planner (paid)** model v polju
   »AI trip planner model«. Ob vsakem modelu piše cena na 1 milijon žetonov (vhod / izhod) in ocena cene enega odgovora.
3. (Neobvezno) Klikni **Test the chat model** ali **Test the trip planner model**: izbrani model (tudi še neshranjen)
   dobi eno fiksno vprašanje, prikaže se odgovor, čas in cena. Test stane približno toliko kot en odgovor in se šteje v
   dnevno omejitev porabe.
4. Klikni **Save**. Obvestilo pove, kateri model zdaj uporablja klepet in kateri načrtovalec.
5. Za vrnitev na privzeto izprazni polje (križec) in shrani.

## Tabela modelov

Cene so iz cenika ponudnikov (Anthropic in OpenAI, preverjeno 2026-10-30), v USD. Ocena »na odgovor« je približna:
dokler okolje nima vsaj 20 odgovorov v zadnjih 30 dneh, velja tipičen odgovor klepeta (2–3 iskanja plaž: ~6.000
vhodnih žetonov + ~4.000 iz predpomnilnika + ~600 izhodnih; s to predpostavko Opus 5.5 stane ~0,036 $, kar je izmerjeno
povprečje testa 40 vprašanj 2026-10-09) oziroma 7-dnevni načrt (~4.000 vhodnih + ~3.500 izhodnih). Ko je odgovorov
dovolj, stran sama računa iz povprečja na tem okolju. Stolpec »Measured (30 days)« pokaže dejansko povprečno ceno
odgovora za modele, ki so bili v zadnjih 30 dneh uporabljeni.

### AI klepet

| Model | Cena na 1M žetonov (vhod / izhod) | ~na odgovor | Odgovorov na dan pri 5 $ | Kdaj ga uporabiti |
|---|---|---|---|---|
| Claude Opus 5.5 | 4 $ / 20 $ | 0,037 $ | ~135 | najboljša kakovost; najdražji Claude |
| Claude Sonnet 5.5 | 2 $ / 10 $ | 0,018 $ | ~270 | dobro razmerje: blizu Opusa, pol cene, hitrejši |
| Claude Haiku 5.5 | 0,10 $ / 0,50 $ | 0,001 $ | ~5.300 | najcenejši Claude, najhitrejši; šibkejši pri večkoračnem iskanju |
| OpenAI GPT-6 Astra | 10 $ / 50 $ | — | — | **samo za načrtovalec**: OpenAI v vmesniku, ki ga uporablja klepet, za ta model ne podpira orodij |
| OpenAI GPT-4o | 2,50 $ / 10 $ | 0,031 $ | ~160 | starejši model; tu nima prednosti pred Sonnetom |
| OpenAI GPT-4o mini | 0,15 $ / 0,60 $ | 0,002 $ | ~2.700 | najcenejši; najslabše izbira orodja in sledenje pravilom odgovora |

### AI načrtovalec potovanj (cena AI na en načrt in delež cene, ki jo plača kupec)

Kupec plača 19 $ (3 dni), 39 $ (7 dni) ali 59 $ (14 dni) (Stripe, pred provizijo in DDV).

| Model | 3 dni | 7 dni | 14 dni | Kdaj ga uporabiti |
|---|---|---|---|---|
| Claude Opus 5.5 (privzeto) | 0,044 $ (0,2 %) | 0,086 $ (0,2 %) | 0,16 $ (0,3 %) | najboljše besedilo v vseh 19 jezikih |
| OpenAI GPT-6 Astra | 0,11 $ (0,6 %) | 0,22 $ (0,6 %) | 0,40 $ (0,7 %) | OpenAI-jev najboljši model; 2,5× dražji od Opusa |
| Claude Sonnet 5.5 | 0,022 $ | 0,043 $ | 0,080 $ | skoraj enako dobro, pol cene |
| Claude Haiku 5.5 | 0,001 $ | 0,002 $ | 0,004 $ | najcenejši; krajša, preprostejša besedila |
| OpenAI GPT-4o | 0,023 $ | 0,045 $ | 0,083 $ | starejši |
| OpenAI GPT-4o mini | 0,001 $ | 0,003 $ | 0,005 $ | prejšnji privzeti model |

Pri vseh modelih je strošek AI pod 1 % cene načrta; tudi če model pri prvem poskusu ne uspe in ga ponovi, se strošek
le podvoji.

Pozor: cenejši modeli pogosto naredijo več krogov iskanja ali daljši razmislek, zato je njihova dejanska cena lahko do
2× višja od ocene. Po nekaj dneh poglej stolpec »Measured (30 days)«.

Dnevna omejitev: brezplačni klepet ima **5 $ na dan** (ADR 2026-10-29), vse skupaj (tudi načrtovalec) **20 $**. Ko je
omejitev dosežena, klepet do polnoči počiva. Cenejši model pomeni več odgovorov na dan.

## Kaj pomenijo oznake

| Oznaka | Pomen |
|---|---|
| **in use** | ta model ta funkcija uporablja zdaj |
| **server default** | ta model se uporablja, če je polje prazno |
| **no API key** | okolje nima ključa tega ponudnika (`ANTHROPIC_API_KEY` ali `OPENAI_API_KEY`); modela ni mogoče izbrati |

## Posebnosti

- **Klepet in Claude**: klepet ima dva načina (»engine«). Claude modeli delajo samo v načinu *agent* (model sam išče
  plaže z orodji, kot MCP strežnik). Če okolje teče v preprostem načinu *pipeline* (samo OpenAI) in izbereš Claude
  model, se klepet samodejno preklopi v *agent*. OpenAI modeli delajo v obeh načinih.
- **Načrtovalec, Claude in GPT-6 Astra**: plaže in vrstni red vedno izbere naša koda; model napiše samo besedilo. Ti
  modeli pred odgovorom razmislijo, zato 7- ali 14-dnevni načrt lahko traja 30–90 sekund; načrt se ustvarja v ozadju,
  zato to obiskovalca ne moti. En poskus ima največ 110 sekund; če ne uspe dvakrat, kupec dobi načrt s preprostim
  besedilom in kredit nazaj.
- **Ključ izgine**: če na okolju zmanjka ključa za izbrani model, se funkcija ne ustavi, ampak uporabi privzeti model
  (in zapiše opozorilo v dnevnik).
- Cene in izračun stroškov za dnevno omejitev so na enem mestu v kodi (`ModelPricing`); če ponudnik spremeni cene,
  jih popravi razvijalec.

## Pogoste napake

- Izbereš model in ne klikneš **Save** → test uporabi izbrani model, klepet pa še vedno privzetega.
- Preklop na najcenejši model brez testa → najprej preizkusi nekaj tipičnih vprašanj (gumb Test ali na dev strani).
- Pričakovanje, da izbira na dev velja tudi na prod → vsako okolje ima svojo izbiro.
