# TOPTOUR Reference Finder

Interny WordPress plugin pre TOPTOUR, ktory sluzi ako funkcne pracovne prostredie pre manazera cestovneho ruchu.

**Version:** 0.2.8
**Status:** Aktivne interne pouzivanie (prevadzkovy rezim)
**Text Domain:** toptour-reference-finder
**Prefix:** Toptour_Ref_
**DB Prefix:** toptour_ref_

## Aktualny kontrakt

TOPTOUR Reference Finder je interny pracovny system pre manazera cestovneho ruchu.

Jeho ucel je:

- evidovat zariadenia a ubytovanie,
- evidovat destinacie a lokality,
- evidovat ponuky a dealy,
- evidovat referencne zdroje,
- zbierat a analyzovat verejne dostupne referencne data,
- zapisovat zistenia z recenzii, clankov, platforiem a fotiek hosti,
- rozlisovat pozitiva, rizika a rozpory,
- hladat opakujuce sa signaly reality,
- vytvarat casove zaznamy ponuk a zisteni,
- pripravit podklad pre neskorsie hodnotenie.

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

## Moduly

Plugin obsahuje interne moduly pre:

- zariadenia,
- destinacie,
- ponuky,
- referencne zdroje,
- zistenia,
- fotodokazy,
- zberove ulohy,
- task runs,
- task events,
- offer snapshots,
- nastavenia a diagnostiku.

## Bezpecnost a data policy

- Pristup je riadeny cez WordPress capability system.
- Praca prebieha v internom admin prostredi.
- System ma auditnu stopu cez task events, runs a viazanie zisteni na zdroje.
- Zber sa opiera o verejne dostupne informacie a interne analyticke spracovanie.

## Vztah k TOPTOUR Core

Reference Finder je samostatny interny plugin s vlastnym datovym modelom a vlastnym admin workflow.
Napojenie na dalsie interne systemy je mozne cez schvalene integracne rozhrania.

## Instalacia

1. Skopiruj priecinok `toptour-reference-finder/` do `wp-content/plugins/`.
2. Aktivuj plugin vo WordPress admine.
3. Over capability pristup a funkcnost modulov v admin prostredi.

## Deaktivacia a odinstalacia

- Deaktivacia pluginu ponechava data pre internu kontinuitu.
- Pri odinstalacii sa postupuje podla internych prevadzkovych pravidiel TOPTOUR.

## Licencia

GPL v2 alebo novsia.

## Autor

TOPTOUR
https://toptour.sk
