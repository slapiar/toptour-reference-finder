# DECISIONS: TOPTOUR Reference Finder

## Decision: Procesna mapa na stranke Zber referencii

**Status:** Schvalene
**Date:** 2026-05-16

Stranka Zber referencii obsahuje procesnu mapu ako riadiaci prvok admin prostredia.
Mapa vedie manazera postupnostou krokov a zamknute kroky brania preskakovaniu logickych predpokladov.

## Decision: Search Intake automation pre Collection Task

**Status:** Schvalene
**Date:** 2026-05-16

Collection Task detail obsahuje akciu `Vyhľadať a zapísať reálne dáta`, ktorá spúšťa real search-intake flow bez placeholder výstupov.

Flow používa tieto pravidlá:

- najprv sa použijú existujúci discovery kandidáti,
- až potom sa použije konfigurovaný search provider (ak je povolený),
- URL výsledky sa deduplikujú podľa normalizovaného URL a existujúcich source/finding väzieb,
- každé URL sa odovzdá do Data Intake Router,
- zlyhanie jedného URL nepreruší spracovanie ďalších,
- audit sa zapisuje do search-intake eventov (`search_intake_started`, `search_result_found`, `source_ingested`, `search_intake_finished`, ...).

Ak provider nie je dostupný a nie sú kandidáti, run sa ukončí bez fake findingov/snapshotov.

## Decision: Data Intake Router ako minimalny produkcny vstup

**Status:** Schvalene
**Date:** 2026-05-16

Collection Task detail obsahuje manualny "Realny vstup dat" pre verejne URL.

Minimalny produkcny flow intake musi:

- validovat URL (iba public http/https),
- nacitat verejnu stranku cez WP HTTP API,
- vytazit zakladne signaly (title, meta description, kratky text, image linky),
- deduplikovat zdroj podla normalizovaneho URL,
- ulozit alebo aktualizovat Reference Source,
- vytvorit Finding v stave `pending_review` / `needs_review`,
- pre offer-like vstupy vytvorit alebo aktualizovat Offer a ulozit Offer Snapshot,
- pre vizualne signaly ulozit Photo Evidence ako URL-only odkazy,
- zapisat audit eventy intake lifecycle do Task Events.

Do intake flow sa neuklada cely HTML obsah stranky a nestahuju sa obrazky do lokalneho uloziska.
Ukladaju sa iba URL odkazy a textove signaly potrebne pre manazersku kontrolu.

## Decision: Prevadzkovy kontrakt internal manager system

**Status:** Schvalene
**Date:** 2026-05-16

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

## Decision: TOPTOUR References Finder uz nie je skeleton MVP

**Status:** Schvalene
**Date:** 2026-05-16

TOPTOUR References Finder uz nie je skeleton MVP.
Od verzie 0.2.x je smerovany ako interne manazerske prostredie pre realny zber a analyzu verejne dostupnych referencnych dat.

System ma ukladat realne zistenia do prislusnych modulov:

- zariadenia,
- destinacie,
- ponuky,
- referencne zdroje,
- zistenia,
- fotodokazy,
- zberove ulohy.

Testovacie placeholdery nesmu nahradzat vysledky realneho zberu.

## Decision: Prevadzkove pravidla pre zber a automatizaciu

**Status:** Schvalene
**Date:** 2026-05-16

- Realny zber dat je povoleny ako interny manazersky proces.
- Externe HTTP volania su povolene, ak su sucastou schvaleneho zberoveho mechanizmu.
- Automaticke planovane ulohy su povolene cez task lifecycle a cron.
- CRUD moduly su sucastou systemu.
- Databazove schemy su sucastou systemu.
- Placeholdery nesmu byt vysledkom realneho zberu.
- Testovacie zaznamy smu existovat iba ako diagnostika.

## Decision: Workflow separation for auditability

**Status:** Schvalene
**Date:** 2026-05-16

Collection Tasks, Task Runs, Findings a Task Events ostavaju oddelene entity.
Toto oddelenie chrani historiu, auditovatelnost a opakovatelnost procesov.

## Decision: Internal enum values stay stable

**Status:** Schvalene
**Date:** 2026-05-16

Interne enum hodnoty ulozene v databaze ostavaju stabilne anglicke kluce.
Lokalizacia sa riesi iba na prezencnej vrstve admin UI.

## Decision: Data use boundary

**Status:** Schvalene
**Date:** 2026-05-16

System pracuje s verejne dostupnymi informaciami a internymi analytickymi zisteniami.
Nepouziva sa na spracovanie sukromnych hostovych dat mimo schvaleneho pravneho a prevadzkoveho ramca TOPTOUR.
