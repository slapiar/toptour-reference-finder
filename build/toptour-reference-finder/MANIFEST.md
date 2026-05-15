# MANIFEST: TOPTOUR Reference Finder

**Plugin Name:** TOPTOUR Reference Finder  
**Slug:** toptour-reference-finder  
**Version:** 0.1.0  
**Status:** MVP Skeleton  
**Author:** TOPTOUR

## Architektonický popis

TOPTOUR Reference Finder je interný WordPress plugin navrhnutý na **zber referencií, dôkazov a zistení z reálnych pobytov hostí**.

Plugin **NETVRDÍ kvalitu**, **NEHODNOTÍ zariadenia** a **NEMÁ verejný frontend** v tejto verzii.

## Primárny princíp

> Najprv zbieramu dôkazy z reality.  
> Až potom definujeme kritériá hodnotenia.

Plugin zbiera:
- Referenčné zdroje (platformy, články, odkazy)
- Zistenia z recenzií a fotiek
- Vizuálne dôkazy a pozorovania
- Úlohy a prioritizáciu zberu

Plugin **NEPRIDÁVA**:
- Automatické hodnotenie
- Verejné skóre
- Marketing jazykové "vylepšovanie"
- Lož o kvalite bez dôkazov

## Dátový model (Budúcnos)

Budúce tabuľky (zatiaľ neimp implementované):

### toptour_ref_facilities
Ubytovacia zariadení a ostatné zariadenia.
```sql
- id
- name
- slug
- destination_id
- facility_type (hotel, apartment, guesthouse, etc.)
- description
- added_date
- last_updated
```

### toptour_ref_destinations
Mestá, regióny, krajiny.
```sql
- id
- name
- slug
- country
- region
- description
- added_date
```

### toptour_ref_offers
Ponuky, dealy, balíčky.
```sql
- id
- name
- slug
- facility_id
- destination_id
- description
- claim (čo ponuka tvrdi)
- added_date
```

### toptour_ref_sources
Referenčné zdroje - kde sa berú dáta.
```sql
- id
- offer_id / facility_id / destination_id
- platform (Booking, TripAdvisor, Google, article_url, etc.)
- source_type (review, photo, article, social_media, etc.)
- url
- source_date
- credibility_rating
- added_date
```

### toptour_ref_findings
Extrahované zistenia z recenzií a fotiek.
```sql
- id
- source_id
- facility_id / offer_id / destination_id
- finding_type (positive, risk, contradiction, neutral)
- area (cleanliness, service, comfort, price, location, etc.)
- description
- signal_strength (weak, medium, strong)
- quoted_text (pôvodný text z recenzie)
- added_date
- verified_date (kedy bolo zistenie overené)
```

### toptour_ref_photo_evidence
Fotografie a vizuálne pozorovania.
```sql
- id
- facility_id / destination_id / offer_id
- source_id (z ktorého zdroja)
- photo_url
- photo_type (photo, screenshot, comparison)
- comparison_category (advertised_vs_reality)
- description
- added_date
```

### toptour_ref_collection_tasks
Úlohy zberu referencií.
```sql
- id
- target_type (facility, destination, offer)
- target_id
- task_description
- priority
- status (open, in_progress, completed, archived)
- assigned_to_user_id
- deadline
- created_date
- completed_date
```

### toptour_ref_signal_patterns
Opakujúce sa signály (3+ výskytov).
```sql
- id
- finding_type
- area
- facility_id / destination_id
- occurrence_count
- pattern_strength
- first_detected_date
- last_detected_date
```

## Admin rozhranie

Admin menu je po aktivácii dostupný na:
- `wp-admin/admin.php?page=toptour-references`

Submenu:
- Dashboard
- Zariadenia
- Destinácie
- Ponuky
- Referenčné zdroje
- Zistenia
- Fotodôkazy
- Zber referencií
- Nastavenia

Všetky sekcie sú zatiaľ placeholdery s MVP notifikáciou.

## Bezpečnosť a oprávnenia

Capability: `manage_toptour_references`
- Automaticky priradená administrátorom pri aktivácii
- Budúce granulárne oprávnenia pre ďalšie role

Všetky výstupy sú escapnuté:
- HTML: `esc_html()`
- URL: `esc_url()`
- Atribúty: `esc_attr()`

Budúce endpointy:
- Nonce validácia
- Capability check
- Rate limiting

## REST API politika

TOPTOUR Reference Finder bude v budúcnosti poskytovať dáta pre internú PWA cez WordPress REST API namespace `toptour-ref/v1`.

### Plánované endpointy

1. **GET /toptour-ref/v1/status** - Health check
2. **GET /toptour-ref/v1/facilities** - Zoznam zariadení
3. **GET /toptour-ref/v1/facilities/{id}** - Detail zariadenia
4. **GET /toptour-ref/v1/destinations** - Zoznam destinácií
5. **GET /toptour-ref/v1/destinations/{id}** - Detail destinácie
6. **GET /toptour-ref/v1/offers** - Zoznam ponúk
7. **GET /toptour-ref/v1/offers/{id}** - Detail ponuky
8. **GET /toptour-ref/v1/sources** - Zoznam zdrojov
9. **GET /toptour-ref/v1/findings** - Zoznam zistení
10. **GET /toptour-ref/v1/findings/{id}** - Detail zistenia
11. **GET /toptour-ref/v1/photo-evidence** - Zoznam fotodôkazov
12. **GET /toptour-ref/v1/collection-tasks** - Pracovný dashboard
13. **POST /toptour-ref/v1/collection-tasks** - Vytvorenie úlohy
14. **POST /toptour-ref/v1/findings** - Manuálne zistenie
15. **POST /toptour-ref/v1/photo-evidence** - Upload fotodôkazu

Všetky endpointy budú:
- Chránené capability check
- Validované nonce
- Rate limited
- Bez verejného prístupu

### PWA integrácia

Budúca interná PWA aplikácia bude komunikovať s REST API:
- Bez Service Worker (cache layer je zakázaný!)
- Explicitný fetch z frontend aplikácie
- Jasné error handling stavy
- Fallback stany pre API nedostupnosť

## Integrácje (Budúcnos)

### TOPTOUR Core
Reference Finder bude poskytovať dáta pre:
- Detaily ponúk
- Profily zariadení
- Filtrovanie ponúk podľa kvality zdrojov

### Fireflies / Swiss Halley
Import ponúk z partnerov a ich automatické zbieranie referencií v budúcnosti.

### Booking / TripAdvisor
Šcraping recenzií a fotiek (budúcnos, bez API kľúčov).

## Kódovací štandard

- **Prefixed class names:** `Toptour_Ref_*` (nie PHP namespaces)
- **Database prefix:** `toptour_ref_`
- **Translation domain:** `toptour-reference-finder`
- **PHP minimum:** 7.4
- **WordPress minimum:** 5.0

## Licencia

GPL v2 alebo novšia

## Poznámky

1. **Bez Service Worker:** Plugin sa výslovne nepoužíva service worker ako bezpečnostnú či cacheovaciu vrstvu (zlé skúsenosti z minulosti).

2. **Bez vylepšovania:** Plugin nepridáva "pekný marketing jazyk" - len zbiera raw dáta z reality.

3. **Nezávislý:** Plugin je úplne nezávislý na TOPTOUR Core, má vlastný admin, dáta a vývoj.

4. **Audit trail:** Všetky zistenia budú viazané na zdroj a dôkaz - kto to zistil, odkiaľ a kedy.

5. **Iteratívny vývoj:** Budúce fázy budú rozširovať schopnosti bez zmeny základného princípu - **najprv reality, potom kritériá**.
