
## 0.2.9
- Pridaná automatizácia search-intake pre Collection Task cez akciu „Vyhľadať a zapísať reálne dáta“.
- `Task Processor` už negeneruje placeholder findings/snapshots pre realný intake flow.
- Pridaná vrstva `Search Provider` s režimami `existing_candidates_only` (default), `configured_api`, `disabled`.
- Search-intake používa kandidátov ako prvý zdroj, potom voliteľný API provider.
- Doplnená deduplikácia URL podľa normalizácie a existujúcich source/finding väzieb.
- Doplnené task eventy pre search-intake lifecycle (`search_query_generated`, `search_result_found`, `source_sent_to_intake`, `source_ingested`, `search_intake_finished`, ...).
- Doplnená sekcia „Vyhľadávací zdroj" v Settings (`search_provider_enabled`, `search_provider_type`, `search_provider_endpoint`, `search_provider_api_key`, `max_search_results_per_task`).
- Opravený zápis offer snapshotu v Data Intake Router na existujúci Offer Snapshots modul.

## 0.2.8
- Stabilizačný release po merge vetvy `release/0.2.2` do `main`.
- Zjednotená release história a tagy na hlavnej vetve.
- Verzia pluginu navýšená na `0.2.8`.
- DB schéma rozšírená o tabuľku `toptour_ref_offers` (`TOPTOUR_REF_DB_VERSION` = `0.2.3`).
- Aktualizovaný projektový kontrakt v dokumentácii: plugin je interné manažérske prostredie pre reálny zber a analýzu verejne dostupných referenčných dát.
- Poznámka k histórii: staré MVP/skeleton obmedzenia v starších sekciách changelogu sú historické a prekonané 0.2.x prevádzkovým kontraktom.
- Pridaný prvý funkčný Data Intake Router pre manuálny vstup URL v detaile Collection Task.
- Doplnená sekcia "Reálny vstup dát" s typizáciou vstupu, linkovaním entít a výsledkovým summary boxom.
- Reálny intake vytvára/aktualizuje Reference Source, vytvára Findings a pri vizuálnych signáloch ukladá Photo Evidence odkazy.
- Pri offer-like URL intake vytvára alebo aktualizuje Offer záznam a vytvára Offer Snapshot.
- Doplnená auditná slovná zásoba task eventov pre intake lifecycle (`intake_started`, `intake_finished`, ...).
- Menu "Ponuky" už nie je placeholder, ale samostatný admin modul nad tabuľkou offers.

## 0.2.5
- Opravená frequency logika pre výpočet `next_run_at`: `twice_daily` = +12h, `three_times_daily` = +8h, `six_daily` = +4h.
- Zjednodušený zoznam Findings na manažérske stĺpce a čitateľnejší hlavný stĺpec „Zistenie“ (názov, stručné zhrnutie, meta riadok).
- Zjednodušená tabuľka nálezov v detaile Collection Task na kľúčové informácie + akcia „Upraviť“.
- Spresnené stavové riadky v detaile úlohy: oddelený stav úlohy a stav posledného behu.
- Doplnené jednotné formátovanie dátumov `Y-m-d H:i` v manažérskych tabuľkách a histórii.
- Doplnené manažérske preklady interných poznámok v histórii eventov (bez zmeny uložených dát).
- Nahradené interné placeholder texty slovenským znením pri nových záznamoch a pridané zobrazovacie mapovanie pre historické anglické placeholdery.
- Upravený text prázdneho stavu pre Source Offer Snapshot sekciu.
- Pridané analytické metadáta referencií vo Findings: časové údaje, language/type, analysis summary/status, confidence a mapovacie poznámky.
- Pridaná vrstva Source Offer Snapshot cez tabuľku `toptour_ref_offer_snapshots`.
- Doplnené časové údaje referencie a analýzy pre lifecycle run.
- Doplnené väzby finding -> offer snapshot -> destination/supplier/offer.
- Doplnené eventy pre reference analysis / offer snapshot / POI candidate a run_skipped.
- Doplnené napojenie analytickej vrstvy na task run lifecycle (manual test run + automatický scheduler hook).
- Doplnený WP-Cron hook `toptour_ref_process_collection_tasks` (bez externého scrapingu a HTTP zberu).
- Doplnený manual/automatic režim Finderu s predvoleným režimom `manual`.
- Doplnené ručné testovacie spustenie v detaile Collection Task.

## 0.2.4
- Extended Collection Tasks schema with scheduling and linkage fields (frequency, next_run_at, destination_id, supplier_id, offer_id, created_by).
- Added Task Runs table for storing individual task executions with run counters and summaries.
- Extended Findings schema with task_id/run_id linkage and lifecycle/audit fields for long-term tracking.
- Added Task Events table for task audit timeline and manager decisions history.
- Added Task Runs and Task Events data classes.
- Extended Collection Tasks admin list with destination, frequency, next run and manager metrics (new findings, pending review, errors).
- Added Collection Task detail MVP blocks: overview, recent findings, recent runs and event timeline.
- Added safe event logging hooks in task save/archive and discovery workflow actions.
- Updated DB schema version to 0.2.1.

## 0.2.2
- Fixed release packaging to use a temporary staging directory.
- Prevented build/dist artifacts from being tracked in Git.
- Added/verified source recovery archive workflow.
- Kept database schema version unchanged.

## 0.2.1
- Added centralized admin label localization layer for internal enum values.
- Added `Toptour_Ref_Labels` helper with grouped Slovak labels and safe fallback humanization.
- Localized enum labels across Collection Tasks, Reference Sources, Findings, Photo Evidence, Contacts, Points of Interest, Interests, Facilities and Destinations admin screens.
- Kept database enum keys and stored values unchanged (English internal values remain canonical).
- Kept DB schema version unchanged.

## 0.2.0
- Added Collection Task Discovery Engine foundation.
- Added discovery runs table.
- Added discovery candidates table.
- Added discovery missing fields table.
- Added rule-based task resolver for destination, interests, finding areas and expected source type.
- Added missing data prompt/panel in Collection Tasks workflow.
- Added search query preparation without automatic external search.
- Added manual discovery candidate entry and accept-as-source flow.
- Prepared discovery provider abstraction without active external HTTP calls.
- Kept discovery controlled, manual and admin-only with no scraping, AI, REST, cron or public output.

## 0.1.15
- Added Photo Evidence admin screen.
- Added manual create and edit support for photo evidence records.
- Added archive action for photo evidence through verification status.
- Added filters, search and pagination for photo evidence.
- Added source, finding, target and collection task labels.
- Added visual classification fields for photo type, comparison category, visual area, signal strength and verification status.
- Kept photo evidence as internal visual observation records with no uploads, scraping, AI image analysis, REST or public output.

## 0.1.14
- Added Findings admin screen.
- Added manual create and edit support for findings.
- Added archive action for findings through verification status.
- Added filters, search and pagination for findings.
- Added source, target, signal pattern and collection task labels.
- Added finding classification fields for type, area, signal strength, repetition, verification and evidence type.
- Kept findings as internal evidence records with no scoring, scraping, AI extraction, REST or public output.

## 0.1.13
- Added Reference Sources admin screen.
- Added manual create and edit support for reference sources.
- Added expanded credibility, origin, verification, suggestion, priority and next-action fields for sources.
- Added credibility and suggestion date tracking fields.
- Added mail templates table and default source workflow mail templates.
- Added mail queue table for manual manager email drafts and test sends.
- Added manual mail draft and test send actions from source edit screen.
- Added target label helper for facilities, destinations, POI, contacts, interests and collection tasks.
- Added optional link between sources and collection tasks.
- Kept sources and mail workflow manual with no scraping, fetching, AI summarization, cron, REST or public output.

## 0.1.12
- Added Points of Interest admin screen.
- Added manual create and edit support for POI records.
- Added archive action for POI records.
- Added filters, search and pagination for POI records.
- Added destination and facility labels in POI list.
- Kept POI records as internal field objects without public map, scoring, geocoding or frontend output.

## 0.1.11
- Added contact relationships management section inside Contacts admin.
- Added manual create/update/replace support for contact relationship records from the contact form.
- Added contact relationships summary column in Contacts list.
- Added contact label helper for related contacts.
- Kept contact relationships contact-centered, directional and internal with no standalone Relationships admin, no scoring and no public output.

## 0.1.10
- Added contact influence management section inside Contacts admin.
- Added manual create/update/replace support for contact influence records from the contact form.
- Added contact influence summary column in Contacts list.
- Added target label helper for destinations, facilities, interests, contacts and points of interest.
- Kept contact influence contact-centered in MVP with no standalone Influence admin, no scoring and no public output.

## 0.1.9
- Added Interests admin screen.
- Added manual create and edit support for interests.
- Added deactivate action for interests.
- Added filters, search and pagination for interests.
- Added contact-interest assignment section in contact form.
- Added replacement save flow for contact interests with internal defaults.
- Added interests column to Contacts list.
- Added contact counts per interest in Interests list.

## 0.1.8
- Added Contacts admin screen.
- Added manual create and edit support for contacts.
- Added archive action for contacts.
- Added filters, search and pagination for contacts.
- Added resident profile section inside contact form.
- Added create, update and remove support for resident profiles linked to contacts.
- Kept residents independent from a single destination; concrete influence remains planned for contact influence module.

## 0.1.7
- Added Destinations admin screen.
- Added manual create and edit support for destinations.
- Added archive action for destinations.
- Added destination filters, search and pagination.
- Added facility-destination assignment UI in Facilities admin.
- Added assigned destinations display in Facilities list.
- Added facility count display in Destinations list.
- Kept facility-destination relations internal with no scoring or public output.

## 0.1.6
- Added relationship schema foundation.
- Added contacts base table.
- Added resident profiles table without single-destination lock.
- Added interests table with default seed interests.
- Added contact-interest relation table.
- Added contact relationship table.
- Added contact influence table for multi-target and multi-destination influence.
- Added points of interest table.
- Updated database diagnostics table list.

## 0.1.5
- Added Destinations admin screen.
- Added manual create and edit support for destinations.
- Added archive action for destinations.
- Added facility-destination relation table.
- Added destination assignment support in Facilities admin.
- Added destination counts in Destinations admin.
- Kept destinations and facility relations as internal reference collection structures without scoring or public output.

## 0.1.4
- Added Facilities admin screen.
- Added manual create and edit support for facilities.
- Added archive action for facilities.
- Added filters, search and pagination for facilities.
- Kept facilities as internal reference collection targets without scoring or public output.

## Historicky archiv (0.1.x)
Poznamka: nasledujuce zaznamy 0.1.x su historicke a nepredstavuju aktualny prevadzkovy kontrakt 0.2.x.

## 0.1.3
- Added Collection Tasks admin screen.
- Added manual create and edit support for reference collection tasks.
- Added archive action for collection tasks.
- Added filters, search and pagination for collection tasks.
- Kept collection tasks strictly manual with no scraping or automation.

## 0.1.2
- Added admin database diagnostics panel.
- Added table existence and row count checks for initial Reference Finder tables.
- Added signal pattern seed status display.
# Changelog

Všetky zmeny v TOPTOUR Reference Finder.


## 0.2.3
- Added release package for post-recovery stabilization state.
- Confirmed plugin version bump to 0.2.3.
- Kept database schema version unchanged.
- No functional schema changes.

## 0.1.1
- Added database migration framework.
- Added initial tables for facilities, destinations, signal patterns and collection tasks.
- Added default signal pattern seeds.
- Added collection tasks table as an internal work queue for reference collection planning.

## 0.1.0 (2026-05-15) [historicky zaznam]

### Pridané

- ✨ Projektový skeleton pre TOPTOUR Reference Finder
- ✨ Admin menu s 9 placeholderom sekciami
  - Dashboard
  - Zariadenia
  - Destinácie
  - Ponuky
  - Referenčné zdroje
  - Zistenia
  - Fotodôkazy
  - Zber referencií
  - Nastavenia
- ✨ Loader trieda (`class-loader.php`) pre inicializáciu pluginu
- ✨ Installer trieda (`class-installer.php`) s activation hookom
- ✨ Capabilities trieda (`class-capabilities.php`) s `manage_toptour_references` capability
- ✨ Admin trieda (`class-admin.php`) pre menu a page rendering
- ✨ REST API kostra (`class-rest-api.php`) s dokumentáciou 15 plánovaných endpointov
- ✨ Uninstall file (`uninstall.php`) s cleanup logikov
- ✨ Dashboard view (`admin/views/dashboard.php`) s projektovými informáciami
- ✨ Admin CSS (`admin/assets/admin.css`) s placeholderom štýlmi
- ✨ Admin JavaScript (`admin/assets/admin.js`) s prakzou aplikáciou
- 📖 Komplexná dokumentácia:
  - `README.md` - Prehľad a príručka
  - `MANIFEST.md` - Architektonický opis a plánovaný dátový model
  - `DECISIONS.md` - Architektonické rozhodnutia
  - `CHANGELOG.md` - História verzií
- 🔒 Bezpečnostný framework:
  - Escaping všetkých výstupov
  - Capability checks na admin stránkach
  - Nonce placements pre budúce API
- 📋 REST API dokumentácia:
  - 12 GET endpointov pre dátové prístupy
  - 3 POST endpointy pre budúcu PWA integráciu
  - Fallback princípy pre PWA
  - Service Worker exclusion policy

### Poznámky

- **Bez databázových tabuliek** - budú v MVP+1
- **Bez biznis logiky** - iba skeleton
- **Bez REST API implementácie** - iba dokumentácia
- **Bez PWA aplikácie** - iba príprava
- **Bez scrapingu** - budúcna funkcia
- **Bez AI** - budúcna funkcia

### Architektonické rozhodnutia

- ✅ Reference Finder je **nezávislý plugin**, nie modul v TOPTOUR Core
- ✅ **Žiadny Service Worker** - explicitný online-first model
- ✅ **Prefixed class names** - nie PHP namespaces
- ✅ **Bez externých knižníc** - iba WordPress core
- ✅ **Text domain** pre preklady a i18n

### Budúce fázy

- MVP+1: Databázové schémy a migrations
- MVP+2: CRUD operácie a admin formuláre
- MVP+3: REST API implementácia
- MVP+4: Interná PWA aplikácia
- MVP+5: Import z Fireflies/Swiss Halley
- MVP+6: Web scraping integracia
- MVP+7: AI pattern matching
- MVP+8: TOPTOUR Core integrácia

---

**Status:** 🚀 MVP Skeleton Complete  
**Next Step:** Database schema planning (MVP+1)
