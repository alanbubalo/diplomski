# Diplomski rad — Alan Bubalo

**Obrasci tolerancije na greške u distribuiranim sustavima: studija slučaja Fiskalizacije 2.0**

Sveučilište Jurja Dobrile u Puli, Fakultet informatike u Puli · rok: **30.09.2026.**

---

## Struktura repoa

```
rad/            LaTeX dokument
  main.tex        glavni dokument, metapodaci na vrhu
  tex/            preambula, naslovnica, sažetak
  poglavlja/      01-uvod.tex … 10-zakljucak.tex
  slike/          dijagrami i grafovi (generirani grafovi idu ovamo)
  references.bib  literatura (biblatex/biber, stil IEEE)

svc/            sustav koji se testira (SUT) — ljestvica brancheva naive → resilient
posrednik/      mock informacijskog posrednika — chaos engine
harness/        driver, oracle, reporteri; generira tablice i grafove u rad/slike/
```

`svc/`, `posrednik/` i `harness/` su za sada prazni — sadržaj dolazi kasnije.

## Kompajliranje rada

```sh
cd rad
latexmk -pdf main.tex     # build, uključujući bibliografiju
latexmk -c                # počisti pomoćne datoteke
```

Ručno: `pdflatex main && biber main && pdflatex main && pdflatex main`

## Konvencije

- **Jezik rada je hrvatski.** Sažetak i abstract oba.
  Strani termin pri prvom spominjanju: `\strani{prekidač}{circuit breaker}`.
- Nedovršeni dijelovi označavaju se `\todo{...}` — ispisuju se crveno i skupljaju
  u **Popis nedovršenog** na kraju PDF-a. Taj popis i `\popistodo` u `main.tex`
  uklanjaju se prije predaje.
- **Brojevi iz evaluacije se ne prepisuju rukom.** Harness generira LaTeX tablice
  i grafove izravno u `rad/`; ponovljeno mjerenje osvježava tekst samo.
- Naslovnica je privremena dok se ne potvrdi službeni UNIPU/FIPU predložak.
  Mentor i naziv studija ostaju placeholderi do tada.

## Studija slučaja

Studija slučaja je referentna arhitektura izvedena iz
javne specifikacije. Ni jedan stvarni sustav, poslodavac ni klijent ne
spominje se nigdje — ni imenom, ni anonimizirano, ni u zahvalama, ni u metapodacima PDF-a.

