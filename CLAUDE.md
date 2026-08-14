# Diplomski rad — pravila pisanja

Rad je na **hrvatskom**. Plan, odluke i otvorena pitanja drže se izvan repozitorija.

## Stil teksta

Ova pravila su obvezujuća. Prekršena, tekst zvuči kao da ga je pisao model.

### Ne koristiti

- **Nikakve crtice u prozi.** Ni `---` (em-dash), ni `--` (en-dash), ni `—` unicode.
  Umjesto njih: zarez, dvotočka, točka ili zagrade.
  `--` je dopušten **samo** u brojčanim rasponima (`str.~144--154`), gdje je tipografski ispravan.
- Rjeđe ili ukrasne znakove koji odaju strojno pisanje: `–`, `•`, `→` u prozi,
  zagrade u zagradama, tri točke kao stilski efekt.
- Podebljavanje kao naglašavanje u tekstu. Vidi niže.

### Rečenice

**Više kratkih rečenica, ne manje složenih.** Ako rečenica ima dva veznika, gotovo je uvijek
bolja kao dvije rečenice.

Loše:

> Zakon predviđa i što se događa kada veza ne radi, pa obveznik tada izdaje račune bez JIR-a,
> a dužan je u roku od dva radna dana uspostaviti vezu i dostaviti elemente svih tako izdanih
> računa, nakon čega slijedi odredba koja je za ovaj rad ključna.

Dobro:

> Zakon uređuje i slučaj kada veza ne radi. Obveznik tada izdaje račune bez JIR-a. U roku od
> dva radna dana dužan je uspostaviti vezu i dostaviti elemente svih tako izdanih računa.
> Slijedi odredba koja je za ovaj rad ključna.

Izbjegavati i trojke kao retoričku naviku (`A, B i C`). Ako se tri stvari nabrajaju, bolje su
tri rečenice ili nabrojana lista.

Kratke rečenice ne znače jednolične. Ako su sve rečenice iste duljine, ritam je strojni.
Povremena dulja rečenica koja nosi jednu misao je u redu. Mijenjati ritam.

### Riječi i fraze

- **Prazne fraze se brišu:** „važno je napomenuti", „treba istaknuti", „u današnje vrijeme",
  „sve više", „igra ključnu ulogu", „od velike važnosti", „u konačnici", „zaključno se može
  reći", „kao što je već spomenuto", „u kontekstu". Test: ako rečenica bez fraze znači isto,
  fraza ide van. Pojačivači („vrlo", „iznimno", „izuzetno") samo ako se tvrdnja može obraniti.
- **Glagol, ne glagolska imenica.** „Vrši se provjera" → „provjerava se".
  „Dolazi do prekida veze" → „veza se prekida".
- **Bez „od strane".** „Račun je potpisan od strane obveznika" → „obveznik potpisuje račun".
- **Izrična rečenica ide s „da", ne s „kako".** „Zakon navodi da…", ne „Zakon navodi kako…".
- **Neodređeni kvantifikatori** („brojni", „razni", „mnogi", „određeni") ne prolaze bez broja,
  primjera ili citata iza sebe. „U roku od dva radna dana", ne „u propisanom roku".
- **Hrvatska riječ prije internacionalizma.** Gdje postoji uvriježena hrvatska riječ,
  ona ima prednost: „distribuirati" → „raspodijeliti", „verificirati" → „provjeriti",
  „kompleksan" → „složen". Iznimka su ustaljeni stručni termini bez dobre zamjene;
  oni se uvode kroz `\strani{}` (vidi *Termini*) i dosljedno ponavljaju.
- **Isti pojam, ista riječ.** Tehnički termin se ponavlja, ne rotira kroz sinonime.
  Rotacija („obveznik" pa „porezni subjekt" pa „izdavatelj") unosi dvosmislenost i odaje model.
  Ponavljanje termina u stručnom tekstu nije greška.

### Odlomci i odjeljci

- **Bez najava.** Odjeljak ne počinje s „U ovom odjeljku opisat će se…" nego prvom tvrdnjom.
  Iznimka: uvodni odlomak poglavlja smije reći čemu poglavlje služi, ali kao tvrdnju
  („Poglavlje uspostavlja propis iz kojeg poglavlje 6 izvodi"), ne kao popis sadržaja.
- **Bez jeke na kraju.** Odjeljak ne završava sažetkom onoga što je upravo rečeno.
  Smije završiti mostom prema sljedećem koraku argumenta
  (kao 2.1: „To pokazuje odjeljak~2.2.").
- **Veznici nisu ljepilo.** Uzastopne rečenice ili odlomci ne počinju s „Također", „Nadalje",
  „Osim toga", „Međutim". Ako logika drži, veznik nije potreban. Ako ne drži, veznik je ne
  spašava.
- **Odlomci različitih oblika.** Ne svaki odlomak po shemi uvodna rečenica, potpore, mini
  zaključak, i ne svi iste duljine. Odlomak od jedne rečenice dopušten je kad nosi argument.

### Prolaz prije commita

Pisati slobodno, stil se ne provjerava u prvom nacrtu. Prije commita jedan prolaz samo za stil:

1. Obrisati prazne fraze, najave i jeke.
2. Glagolske imenice zamijeniti glagolima; izbaciti „od strane".
3. Odlomak koji škripi pročitati naglas. Gdje ponestane daha, rečenica se dijeli.

### Podebljavanje

Štedljivo. **Najviše jedna podebljana rečenica po odjeljku**, i samo ako nosi argument.
Ako se dvoji, ne podebljavati. Kurziv za termine i za blago naglašavanje.

### Registar

Otvoren i tvrdnji svjestan, ali bez samohvale. Rad ima tezu i ona se smije vidjeti.

> ⚠️ **Otvoreno:** koliko suzdržan registar mentor očekuje nije potvrđeno (2026-08-13).
> Do potvrde: pisati otvoreno, ali bez podebljavanja i bez retoričkih figura.
> Ako mentor zatraži bezličnije, prelazi se na „može se uočiti da…" i slično.

## Citiranje

- **Članci propisa idu inline u tekstu**, ne u podrubnim bilješkama: `(čl.~15.~st.~1.)`.
  Razlog: pogl. 2 i 6 citiraju propis na gotovo svakoj rečenici; u bilješkama bi ih bilo
  stotinjak i stranica bi bila neupotrebljiva.
- Prvi put se navodi puni izvor uz `\cite{}`, dalje samo članak.
- Nijedna citacija ne ulazi u rad bez provjere u primarnom izvoru. Arhivirane inačice
  specifikacija su u `rad/izvori/`.

## Termini

Strani termin pri prvom spominjanju: `\strani{prekidač}{circuit breaker}`.
Dalje samo hrvatski oblik.

## Tvrda ograničenja

- Ni jedan stvarni sustav, poslodavac ni klijent ne spominje se nigdje. Ni raniji studentski
  projekt, koji se **ne citira** i iz kojeg se ne preuzima
  ni kod ni tekst.
- Studija slučaja je referentna arhitektura izvedena iz javne specifikacije. Svaka tvrdnja
  ima citat propisa iza sebe.

## Nedovršeno

`\todo{...}` se ispisuje crveno i skuplja u *Popis nedovršenog* na kraju PDF-a.
`\popistodo` u `main.tex` i sve `\todo` uklanjaju se **prije predaje**.
