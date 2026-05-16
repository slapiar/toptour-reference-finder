## Decision: Reference analysis stores internal structured insight, not copied citations

TOPTOUR References Finder neukladá cudzie referencie ako citácie.

Plugin ukladá vlastné analytické zistenia, verejné metadáta zdroja, časové údaje referencie a časové snapshoty verejne prezentovaných parametrov ponúk.

Referencia sa analyzuje vo vzťahu k ponuke, dodávateľovi, destinácii a bodom záujmu.

Offer Snapshot zachytáva verejný stav ponuky v čase analýzy, pretože ceny, dostupnosť a podmienky sa môžu meniť.

## Decision: Finder uses manual and automatic mode with safe-default manual

References Finder používa režim `manual` / `automatic`.

Predvolený režim je `manual`.

Cron scheduler je napojený na Collection Tasks, Task Runs, Findings, Reference Analysis, Offer Snapshots a Task Events.

Automatický režim zatiaľ nespúšťa neobmedzený externý scraping; spracovanie prebieha iba podľa bezpečne implementovaného interného mechanizmu bez externých HTTP volaní.

## Decision: References Finder separates Task File, Runs, Findings and Events

References Finder distinguishes four core entities for long-term collection workflows:

- Collection Tasks are the primary working file for each assignment.
- Task Runs store each individual execution of the task.
- Findings store concrete discovered signals and references.
- Task Events store the audit timeline of changes and manager decisions.

This separation preserves history, enables repeatable execution and prevents silent overwrites of collection outcomes.

## Decision: Internal enum values remain stable English keys

Internal enum values stored in plugin tables remain stable English keys.

Admin UI may show localized labels (for example Slovak), but this is only a presentation layer.

Forms must keep original English enum values in option value attributes.

Localization must never rewrite persisted enum values in existing records.

This keeps data stable for migrations, filtering, integrations and future automation.

## Decision: Collection tasks are the workflow entry point

Collection tasks are the primary entry point for reference discovery.

Users describe what they want to find in natural working language.

The system analyzes the task, checks internal tables, detects missing data and prepares discovery queries.

In MVP, discovery is rule-based and controlled from admin.

External search providers are prepared as an integration point but are not called unless explicitly configured.

The system must ask for missing required data before attempting more precise discovery.

## Decision: Discovery candidates are reviewed before becoming sources

Discovery candidates are potential sources found or entered during the discovery workflow.

A candidate does not automatically become a reference source.

It must be accepted manually before it is inserted into Reference Sources.

This prevents noisy or unreliable web results from polluting the evidence base.

## Decision: Photo evidence stores visual observations, not media files

Photo evidence records store internal visual observations and URLs to visual material.

The MVP does not upload, store, copy, scrape or analyze image files.

Photo evidence may be linked to sources, findings, targets and collection tasks.

Its purpose is to support manual comparison between official presentation and guest-visible reality.

It is not public scoring, automated image analysis or a media gallery.

## Decision: Findings are evidence records, not evaluations

Findings are internal evidence records extracted manually from reference sources, guest feedback, visual evidence or local verification.

A finding may describe a positive signal, risk, contradiction, repeated signal, uncertainty, source quality issue or neutral observation.

Findings are connected to sources, targets and optional signal patterns.

They do not represent final TOPTOUR rating, scoring or public evaluation.

Evaluation criteria may be built later from repeated and verified findings.

## Decision: Reference sources are manually captured before extraction

Reference sources represent places where real guest experience, visual evidence or contextual information may be found.

A source can be connected to a facility, destination, point of interest, contact, interest, offer or collection task.

In MVP, sources are captured manually.

The plugin does not scrape, fetch, summarize or evaluate external sources automatically.

Source credibility, origin, verification state, suggested credibility changes and search priority are internal working states, not public scoring.

## Decision: Mail workflow is prepared but manually triggered

Reference Finder prepares database structures for mail templates and mail queue records.

Mail notifications are not sent automatically in MVP.

A manager email can only be created or sent through explicit manual admin action.

The system does not use cron, bulk mailing or background sending for source credibility workflow in MVP.

This keeps mail behavior testable, predictable and safe.

## Decision: Points of Interest are independent field objects

Points of Interest are independent internal field objects.

A point of interest may be connected to a destination, a facility, a contact influence record, future findings, future photo evidence or future collection tasks.

POI records represent concrete places in the field, not public map entries or scoring units.

The MVP admin manages POI manually without map APIs, geocoding, automation or public frontend.

# DECISIONS: TOPTOUR Reference Finder

## Decision: Contact relationships are contact-centered in MVP

Contact relationships are managed from the contact form in MVP.

A relationship without a contact is too abstract for the early workflow.

A contact may have relationships to multiple other contacts:
- local partners,
- suppliers,
- clients,
- family,
- friends,
- community members,
- recommender contacts,
- or conflict contacts.

Contact relationships describe internal working context, trust bridges, cooperation paths and possible risks.

They are directional in MVP.

The plugin does not automatically create reciprocal relationships.

This is not a public social graph, scoring system or evaluation of people.

## Decision: Contact influence is contact-centered in MVP

Contact influence is managed from the contact form in MVP.

Influence without a contact is too abstract for the early workflow.

A contact may have influence across multiple target types:
- destinations,
- facilities,
- interests,
- other contacts,
- points of interest,
- or general context.

Contact influence describes internal working usefulness, practical influence and mutuality.

It is not public scoring, reputation ranking or evaluation of a person.

## Decision: Interests are shared relationship vocabulary

Interests are maintained as a shared dictionary in `{prefix}toptour_ref_interests`.

Contacts do not store freeform interest text as a primary relationship structure.

Contact-interest links are stored through `{prefix}toptour_ref_contact_interests`, so one interest can be reused across many contacts and one contact can hold many interests.

This keeps relationship vocabulary consistent for future filtering, influence modeling and cross-module analytics.

## Decision: Database schema uses WordPress dbDelta migrations

TOPTOUR Reference Finder creates and updates its internal tables through WordPress `dbDelta()` migrations.

The plugin does not use standalone SQL scripts for schema installation.

All table names must use the active WordPress database prefix through `$wpdb->prefix`.

This keeps the plugin compatible with WordPress installations using custom table prefixes.

## Decision: Facilities and destinations use a relation table

Facilities and destinations are connected through `{prefix}toptour_ref_facility_destination`.

This allows one facility to belong to one or more destinations and one destination to contain many facilities.

The relation is part of reference collection structure, not scoring or evaluation.

The relation may distinguish primary destination and broader service or regional relations.

## Decision: Resident influence is multi-target and multi-destination

A resident or contact is not bound to a single destination.

One contact may have different influence, usefulness and mutuality levels across multiple destinations, facilities, points of interest, interests and other contacts.

Resident profiles describe the general resident role of a contact.

Concrete influence is stored separately through target-based influence records.

This keeps the model flexible for real destination networks, where one person may be useful in multiple places and for different reasons.

## Decision: Contacts are the base layer for residents and relationships

Contacts are the base identity layer for people, organizations and groups.

A contact may later become a resident, facility contact, local helper, supplier, guide, client, partner or knowledge source through related tables.

The base contacts table must not force a single role or a single destination.

Roles, interests, influence and relationships are stored in separate relationship tables.

# DECISIONS: TOPTOUR Reference Finder

Architektonické a projektové rozhodnutia.

## Decision: Reference Finder je samostatný plugin

**Status:** ✅ Schválené  
**Date:** 2026-05-15

TOPTOUR Reference Finder **nie je modul** v TOPTOUR Core.

Je to **úplne nezávislý WordPress plugin** s vlastným:
- Dátovým modelom
- Admin rozhraniom
- Databázovými tabuľkami
- Vývojovým rytmom
- Oprávneniami

### Dôvod

TOPTOUR Core je jadrom pre:
- Ponuky
- Zákazníkov
- Požiadavky
- Manažérov

Reference Finder má vlastný cieľ:
- Zber dôkazov z reality
- Identifikácia signálov
- Audit trail pre budúce hodnotenie

TOPTOUR Core sa môže meniť bez toho, aby sa zmenil Reference Finder, a naopak.

### Budúce napojenie

Keď budú obe systémy stabilné, Reference Finder bude poskytovať dáta pre Core cez:
- REST API endpoints
- Custom hooks
- Database queries (ak je potrebné)

### Výhody

✅ Nezávislosť  
✅ Jasný scope  
✅ Vlastný vývoj  
✅ Jednoduchšia testovateľnosť  
✅ Jednoduchšia údržba  
✅ Jasný audit trail  

## Decision: Žiadny Service Worker

**Status:** ✅ Schválené  
**Date:** 2026-05-15

TOPTOUR Reference Finder a budúca PWA integrácia **NEBUDÚ POUŽÍVAŤ service worker** ako:
- Cacheovaciu vrstvu
- Synchronizačnú vrstvu
- Bezpečnostnú vrstvu
- Offline support
- Push notifikácie

### Dôvod

Predchádzajúce skúsenosti:
- Service worker može zobrazovať zastarané dáta bez upozornenia
- Cache invalidácia je komplikovaná a chybová
- Debugovanie je veľmi ťažké
- Vytvára falošný pocit dostupnosti
- Interný pracovný nástroj potrebuje **čitateľné a predvídateľné online správanie**

### Architektúra

PWA bude:
- Online-first aplikácia
- Štandardný REST API fetch
- Explicitné error handling
- Žiadne skryté cache vrstvy
- Jasný status pre offline

Ak API:
- Nie je dostupné → PWA zobrazí "API nedostupné"
- Vráti prázdny zoznam → PWA zobrazí "Žiadne dáta"
- Vráti 403 → PWA zobrazí "Nemáte oprávnenie"
- Sieť vypadne → PWA zobrazí "Bez spojenia"

Nič sa nevykonáva v pozadí bez explicitného vedenia používateľa.

## Decision: Prefixed class names, nie namespaces

**Status:** ✅ Schválené  
**Date:** 2026-05-15

Plugin používa:
- ✅ `Toptour_Ref_*` prefixované triedy
- ❌ Nie PHP `namespace`

### Dôvod

- Kompatibilita s WP standardom
- Jednoduchšie phpstan validovanie
- Menej „modern PHP" šumu v internom plugin
- Jasné pomenovanie bez `use` statements

### Príklady

```php
// ✅ SPRÁVNE
class Toptour_Ref_Admin { ... }
class Toptour_Ref_Loader { ... }
class Toptour_Ref_Installer { ... }

// ❌ NESPRÁVNE
namespace Toptour\Ref;
class Admin { ... }
```

## Decision: Bez biznis logiky v MVP

**Status:** ✅ Schválené  
**Date:** 2026-05-15

Verzia 0.1.0 je **len skeleto**:
- ✅ Admin menu
- ✅ Dokumentácia
- ✅ Loader a structure
- ✅ REST API dokumentácia
- ❌ Databázové tabuľky
- ❌ CRUD operácie
- ❌ REST API implementácia
- ❌ Scraping
- ❌ AI
- ❌ Automatické hodnotenie

### Dôvod

1. Najpre projektová štruktúra a dokumentácia
2. Potom implementácia databáz a CRUD (MVP+1)
3. Potom REST API (MVP+2)
4. Potom PWA (MVP+3)

Každá fáza je séria, nie paralelná.

## Decision: Neimplementuj všetky plánované tabuľky

**Status:** ✅ Schválené  
**Date:** 2026-05-15

Databázové tabuľky sa nebudú vytvárať v MVP (0.1.0).

Plán:
- MVP 0.1.0: Žiadne tabuľky
- MVP+1: Všetky tabuľky + migrations
- MVP+1: CRUD pre tabuľky
- MVP+2: Admin formuláre
- MVP+3: REST API

Kód bude pripravený na migrácie:
- `class-installer.php` má metódu `create_tables()`
- Je tam TODO komentár s plánovanou schémou
- Migrácie sa budú implementovať keď sa budú potrebovať

## Decision: Oprávnenia pre adminy

**Status:** ✅ Schválené  
**Date:** 2026-05-15

MVP používa capability:
- `manage_toptour_references` (mapované na `manage_options` pre admins)

Budúce granulárne oprávnenia:
- `read_toptour_references`
- `edit_toptour_references`
- `delete_toptour_references`
- `manage_toptour_facilities`
- `manage_toptour_destinations`
- `manage_toptour_offers`
- `manage_toptour_sources`
- `manage_toptour_findings`
- `manage_toptour_photo_evidence`
- `manage_toptour_collection_tasks`

V MVP sú všetky oprávnenia mapované na administrátorov.

## Decision: Bezpečnostný fallback pre PWA

**Status:** ✅ Schválené  
**Date:** 2026-05-15

Ak API nie je dostupné, PWA musí zobraziť bezpečný stav:

| Scenár | Odpoveď API | PWA zobrazí |
|--------|-----------|-----------|
| Zdravý API | 200 OK s dátami | Dáta |
| API down | žiadna odpoveď | "API nedostupné" |
| Prázdny list | 200 OK [] | "Zatiaľ bez dát" |
| Bez oprávnenia | 401/403 | "Nemáte prístup" |
| Sieť down | žiadna | "Bez spojenia" |
| Neplatný JSON | garbage | "Chyba servera" |
| Endpoint neexistuje | 404 | "Funkcia nie je dostupná" |

Žiadne nekonečné retry slučky, žiadne skryté backendy, žiadne falošné cache.

## Decision: Žiadne externé knižnice

**Status:** ✅ Schválené  
**Date:** 2026-05-15

Plugin **nepoužíva**:
- ❌ Composer dependencies
- ❌ npm balíčky
- ❌ jQuery pluginy
- ❌ CSS frameworky

Používa:
- ✅ WordPress core API
- ✅ Natívny JavaScript
- ✅ WordPress admin štýly

## Decision: Text Domain a prelokaliz.

**Status:** ✅ Schválené  
**Date:** 2026-05-15

- Text Domain: `toptour-reference-finder`
- Domain Path: `/languages`
- Všetky reťazce: `esc_html__()`, `esc_html_e()`
- Nebude žiadny hardcoded text bez prekladu

Aktuálne je SK a EN, ale skelet je pripravený na ľubovoľný jazyk.

## Decision: Signal patterns are part of reference collection

**Status:** ✅ Schválené  
**Date:** 2026-05-15

`toptour_ref_signal_patterns` is included as a working classification layer for recurring reference signals.

It does not represent final TOPTOUR evaluation or scoring.

Its purpose is to help distinguish:
- types of recurring signals,
- source categories,
- evidence collection methods,
- review/photo contradiction patterns,
- positive patterns,
- risk patterns,
- uncertainty patterns.

Signal patterns support structured evidence collection before any later evaluation criteria are created.

---

Všetky rozhodnutia budú aktualizované v ďalších fázach vývoja.
