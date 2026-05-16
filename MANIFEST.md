# MANIFEST: TOPTOUR Reference Finder

**Plugin Name:** TOPTOUR Reference Finder
**Slug:** toptour-reference-finder
**Version:** 0.2.9
**Status:** Aktivny interny manazersky system
**Author:** TOPTOUR

## Prevadzkovy kontrakt

TOPTOUR Reference Finder je interny pracovny system pre manazera cestovneho ruchu.

System sluzi na:

- evidenciu zariadeni a ubytovania,
- evidenciu destinacii a lokalit,
- evidenciu ponuk a dealov,
- evidenciu referencnych zdrojov,
- zber a analyzu verejne dostupnych referencnych dat,
- zapis zisteni z recenzii, clankov, platforiem a fotiek hosti,
- rozlisovanie pozitiv, rizik a rozporov,
- vyhladavanie opakujucich sa signalov reality,
- vytvaranie casovych zaznamov ponuk a zisteni,
- pripravu podkladov pre neskorsie hodnotenie.

Plugin nesluzi na rabovanie citlivych udajov, kontaktov hosti ani sukromnych dat.
Plugin pracuje s verejne dostupnymi informaciami, verejnymi ponukami, verejnymi referenciami a vlastnymi analytickymi zisteniami TOPTOUR.

## Prevadzkove pravidla

- Realny zber dat je povoleny ako interny manazersky proces.
- Externe HTTP volania su povolene, ak su sucastou schvaleneho zberoveho mechanizmu.
- Automaticke planovane ulohy su povolene cez task lifecycle a cron.
- CRUD moduly su sucastou systemu.
- Databazove schemy su sucastou systemu.
- Placeholdery nesmu byt vysledkom realneho zberu.
- Testovacie zaznamy smu existovat iba ako diagnostika.

## Funkcna architektura

System je postaveny ako interny WordPress plugin s admin workflow.

Hlavne domene a workflow vrstvy:

- Collection Tasks: zadanie zberu a planovanie cyklu.
- Task Runs: jednotlive behy zberu a analyzy.
- Task Events: auditna historia operacii a rozhodnuti.
- Findings: konkretne signaly a zistenia naviazane na zdroje a ciele.
- Offer Snapshots: casove zaznamy verejne prezentovanych parametrov ponuk.
- Reference Sources: evidencia zdrojov a ich kontextu.
- Facilities, Destinations, Offers: pracovne entity manazerskeho prostredia.

## Data governance

- Persistovane hodnoty enum ostavaju stabilne interne kluce.
- Lokalizacia je prezenta vrstva, nie prepis databazovych hodnot.
- Auditna stopa je povinna pre klucove zmeny vo workflow.

## Bezpecnost

- Pristup je riadeny capability modelom WordPress.
- Admin rozhranie je interne, bez verejneho publikovania internych pracovnych dat.
- Integracne volania a automatizacie podliehaju internemu schvaleniu.

## Integracny ramec

Reference Finder moze byt integrovany s dalsimi internymi systemami cez schvalene API alebo workflow mosty.
Klucove pravidlo je zachovanie auditovatelnosti, datovej integrity a legalneho rezimu prace s verejne dostupnymi informaciami.
