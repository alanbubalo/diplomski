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
