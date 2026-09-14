# Diplomski rad, Alan Bubalo

Obrasci tolerancije na greške u raspodijeljenim sustavima: studija slučaja Fiskalizacije 2.0

Sveučilište Jurja Dobrile u Puli, Fakultet informatike u Puli

## Struktura repoa

```
rad/            LaTeX dokument
  main.tex        glavni dokument, metapodaci naslovnice na vrhu
  tex/            naslovnica, sažetak
                  izjave.tex je u repou, ali nije uključen u build
  poglavlja/      01-uvod.tex ... 10-zakljucak.tex
                  prilog-sutnje.tex je u repou, ali nije uključen u build
  references.bib  literatura (biblatex/biber, stil IEEE)

prototip/       ilustrativni prototip (Laravel, konzolno), vlastiti README
```

## Kompajliranje rada

```sh
cd rad
latexmk -pdf main.tex     # build, uključujući bibliografiju
latexmk -c                # počisti pomoćne datoteke
```

## Opseg evaluacije

Evaluacija je analitička. Spaja matricu *način otkazivanja × obrazac* s ilustrativnim
prototipom. Svaka čelija matrice nosi razinu tvrdnje: (a) citat, (b) prototip ili
(c) prosudba autora. Prešućena prosudba je ono što pada na obrani.

Empirijska evaluacija je izvan opsega. Nema sustava pod testom, chaos enginea, harnessa
ni ljestvice brancheva, pa nema ni izmjerenih brojeva. Prototip pokriva granicu C1↔C2 i
sinkroni kanal prema Sustavu za fiskalizaciju, na četiri scenarija. Bez UBL-a, Schematrona,
AS4, XAdES-a i otkrivanja adrese. Vanjska odredišta zamijenjena su kontroliranim dvojnicima
koji vraćaju uspjeh, timeout ili S008. Puni opseg i granice stoje u `prototip/README.md`.

## Studija slučaja

Studija slučaja je referentna arhitektura izvedena iz javne specifikacije. Ni jedan stvarni
sustav, poslodavac ni klijent ne spominje se nigdje, ni imenom, ni anonimizirano, ni u
zahvalama, ni u metapodacima PDF-a.
