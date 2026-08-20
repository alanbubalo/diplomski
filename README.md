# Diplomski rad, Alan Bubalo

Obrasci tolerancije na greške u raspodijeljenim sustavima: studija slučaja Fiskalizacije 2.0

Sveučilište Jurja Dobrile u Puli, Fakultet informatike u Puli

Konačna predaja je prvi tjedan rujna 2026. Ciljni datum pisanja je 1.9., rezerva 2.-7.9.

## Struktura repoa

```
rad/            LaTeX dokument
  main.tex        glavni dokument, metapodaci na vrhu
  tex/            preambula, naslovnica, sažetak
  poglavlja/      01-uvod.tex ... 10-zakljucak.tex
  slike/          dijagrami i grafovi (generirani grafovi idu ovamo)
  references.bib  literatura (biblatex/biber, stil IEEE)

  izvori/         arhivirane inačice primarnih izvora + SHA256SUMS
    teorija/        arhivirani znanstveni radovi + kako je svaki čitan

prototip/       ilustrativni prototip (Laravel, konzolno)
```

> `svc/`, `posrednik/` i `harness/` su prazni i takvi ostaju. Bili su predviđeni za empirijsku
> evaluaciju: SUT, chaos engine, harness. Ta evaluacija je izvan opsega otkad je rok skraćen na
> tri tjedna, a ravnoteža prebačena na (D) analitičku evaluaciju. Ne brišu se, da
> povijest odluka ostane čitljiva.
>
> Zamjenjuje ih `prototip/`: konzolna aplikacija bez HTTP-a. Pokriva granicu C1↔C2 i sinkroni
> kanal prema Sustavu za fiskalizaciju. Bez UBL-a, Schematrona, AS4, XAdES-a i otkrivanja
> adrese. Vanjska odredišta zamijenjena su kontroliranim dvojnicima koji vraćaju uspjeh,
> timeout ili S008. Puni opseg i granice stoje u
> `prototip/README.md`.

## Kompajliranje rada

```sh
cd rad
latexmk -pdf main.tex     # build, uključujući bibliografiju
latexmk -c                # počisti pomoćne datoteke
```

Ručno: `pdflatex main && biber main && pdflatex main && pdflatex main`

## Konvencije

- Rad je na hrvatskom. Sažetak ide i na hrvatskom i na engleskom.
  Strani termin pri prvom spominjanju: `\strani{prekidač}{circuit breaker}`.
- Nedovršeni dijelovi označavaju se `\todo{...}`. Ispisuju se crveno i skupljaju u Popis
  nedovršenog na kraju PDF-a. Taj popis i `\popistodo` u `main.tex` uklanjaju se prije predaje.
- Evaluacija spaja analitičku matricu *način otkazivanja × obrazac* s izvršnim scenarijima.
  Ne mjeri performanse. Svaka ćelija nosi razinu tvrdnje: (a) citat, (b) prototip ili
  (c) prosudba autora. Prešućena prosudba je ono što pada na obrani.
- Nijedna citacija ne ulazi u rad bez provjere u primarnom izvoru. Arhivirane inačice su u
  `rad/izvori/`. `rad/izvori/teorija/README.md` bilježi kako je svaki rad čitan i što nije.
- Bez crtica u prozi, kratke rečenice, štedljivo podebljavanje. Puna pravila stila su u
  `CLAUDE.md` i obvezujuća su.
- Naslovnica slijedi službeni UNIPU obrazac, arhiviran u `rad/izvori/obrasci/`. Mentor i naziv
  studija ostaju placeholderi do predaje.

## Studija slučaja

Studija slučaja je referentna arhitektura izvedena
iz javne specifikacije. Ni jedan stvarni sustav, poslodavac ni klijent ne spominje se nigdje,
ni imenom, ni anonimizirano, ni u zahvalama, ni u metapodacima PDF-a.

