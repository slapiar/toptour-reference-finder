# TOPTOUR Reference Finder

Internal TOPTOUR plugin for collecting real guest references, review findings, source links and photo evidence before any later evaluation logic.

**Version:** 0.2.2
**Status:** Stabilizovaný interný plugin (MVP foundation)
**Text Domain:** toptour-reference-finder  
**Prefix:** Toptour_Ref_  
**DB Prefix:** toptour_ref_

## Účel

Plugin slúži na interný zber referencií, zdrojov, zistení a fotodôkazov z reálnych pobytov hostí a recenzií. Nesmie slúžiť na verejné hodnotenie, marketingové vylepšovanie ani automatickú klasifikáciu zariadení.

## Základná veta

**TOPTOUR Reference Finder najprv zbiera dôkazy z reálnych skúseností hostí. Hodnotenie vzniká až neskôr, z opakujúcich sa signálov reality.**

Zásada: Najprv zber referencií. Až potom kritériá hodnotenia.

## Účely pluginu

Plugin umožňuje:

1. **Evidovať zariadenia** - ubytovanie, reštaurácie, cestovné zariadenia
2. **Evidovať destinácie** - mestá, regióny, krajiny
3. **Evidovať ponuky** - dealy, balíčky, cestovné ponuky
4. **Ukladať referenčné zdroje** - Booking, TripAdvisor, Google, vlastné články, sociálne siete
5. **Zapisovať zistenia z recenzií a fotiek** - extrahované poznatky z hostiteľských správ
6. **Rozlišovať pozitíva, riziká, rozpory** - kategorizácia signálov reality
7. **Hľadať opakujúce sa signály** - čo sa objavuje v 3+ recenziách
8. **Cieliť zber na konkrétne zariadenie alebo destináciu** - pracovné úlohy zberu
9. **Pripraviť základ pre neskoršie TOPTOUR hodnotenie** - všetky dáta sú viazané na zdroj a dôkaz

## MVP rozsah

Aktuálna verzia (0.2.2) obsahuje:

- Admin moduly pre interný zber referencií (zariadenia, destinácie, kontakty, záujmy, POI, zdroje, zistenia, fotodôkazy, zberové úlohy)
- Databázový installer a aktuálny základ schém pre interné entity
- Discovery a collection workflow foundations (manual/admin-only)
- Loader, capabilities framework a interné admin rozhranie
- REST API kostru s dokumentáciou plánovaných endpointov

## Mimo rozsahu MVP

Aktuálna verzia **NEOBSAHUJE**:

- ❌ Databázové schémy
- ❌ CRUD operácie
- ❌ Webový scraping
- ❌ AI sumarizáciu recenzií
- ❌ Automatické hodnotenie
- ❌ Verejné skóre alebo certifikáty
- ❌ Verejný frontend
- ❌ Integráciu s Booking, TripAdvisor, Google Reviews
- ❌ Porovnávanie cien
- ❌ REST API implementáciu (iba dokumentácia)
- ❌ PWA aplikáciu
- ❌ Service Worker
- ❌ Offline synchronizáciu

## Vzťah k TOPTOUR Core

Plugin je **samostatný** a **nezávislý** na TOPTOUR Core.

**TOPTOUR Core** zostáva jadrom pre:
- Ponuky a dealy
- Zákazníkov
- Požiadavky
- Manažérov

**TOPTOUR Reference Finder** bude v budúcnosti:
- Poskytovať referenčné dáta pre ponuky a destinácie v TOPTOUR Core
- Možno sa napojiť cez REST API alebo custom hooks
- Má vlastný dátový model a admin rozhranie

## Projektová štruktúra

```
toptour-reference-finder/
├── toptour-reference-finder.php      # Hlavný plugin file
├── uninstall.php                     # Uninstall hook
├── README.md                         # Táto dokumentácia
├── MANIFEST.md                       # Architektonický opis
├── DECISIONS.md                      # Architektonické rozhodnutia
├── CHANGELOG.md                      # História verzií
├── includes/
│   ├── class-loader.php              # Loader a inicializácia
│   ├── class-installer.php           # Activation a database setup
│   ├── class-admin.php               # Admin menu a stránky
│   ├── class-capabilities.php        # Definícia oprávnení
│   └── class-rest-api.php            # REST API kostra (plán endpointov)
├── admin/
│   ├── views/
│   │   └── dashboard.php             # Dashboard stránka
│   └── assets/
│       ├── admin.css                 # Admin štýly
│       └── admin.js                  # Admin skripty (zatiaľ prázdny)
└── languages/
    └── (Translations coming in future versions)
```

## Admin menu

Po aktivácii pluginu sa v WordPress admin paneli objaví menu **TOPTOUR References** s nasledujúcimi položkami:

- 📊 **Dashboard** - Prehľad a dokumentácia
- 🏢 **Zariadenia** - Ubytovanie a zariadenia
- 🗺️ **Destinácie** - Mestá a regióny
- 💼 **Ponuky** - Dealy a cestovné balíčky
- 🔗 **Referenčné zdroje** - Platformy a články
- 📝 **Zistenia** - Extrahované poznatky
- 📷 **Fotodôkazy** - Vizuálne dôkazy
- 📋 **Zber referencií** - Pracovný dashboard
- ⚙️ **Nastavenia** - Konfigurácia (budúcnos)

Sekcie sú interné a určené na riadený zber referencií bez verejného výstupu.

## Oprávnenia

Plugin definuje capability `manage_toptour_references`, ktorá je automaticky priradená administrátorom pri aktivácii.

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

## REST API (Budúcnos)

Plugin bude v budúcnosti poskytovať REST API na namespace `toptour-ref/v1` pre interné PWA aplikácie.

Plánované endpointy sú dokumentované v `includes/class-rest-api.php`.

Aktuálne sa API neimplementuje.

## Bezpečnosť

- Všetky výstupy sú escapnuté pomocou `esc_html()`, `esc_url()`, `esc_attr()`
- Žiadne externe knižnice
- Žiadne automatické API volania
- Všetky dáta budú viazané na interných používateľov s capability check
- Budúce REST API endpointy budú chránené nonce a capability validáciou

## Instalácia

1. Skopíruj priečinok `toptour-reference-finder/` do `wp-content/plugins/`
2. Aktivuj plugin v WordPress admin paneli
3. Plugin automaticky:
   - Vytvorí potrebné options
   - Zaregistruje capabilities
   - Vytvorí admin menu

## Deaktivácia a odinštalácia

- **Deaktivácia:** Plugin si zachováva všetky dáta a nastavenia
- **Odinštalácia:** Plugin si zachováva databázové tabuľky (ak budú existovať v budúcnosti)
  - Tabuľky možno ručne vymazať cez phpMyAdmin alebo WP-CLI

## Budúci vývoj

Plánované fázy:

1. **MVP+1:** Databázové schémy a základné CRUD
2. **MVP+2:** Admin formuláre a dátový editor
3. **MVP+3:** REST API implementácia
4. **MVP+4:** Interná PWA aplikácia
5. **MVP+5:** Import z Fireflies / Swiss Halley
6. **MVP+6:** Web scraping integracia
7. **MVP+7:** AI sumarizácia a pattern matching
8. **MVP+8:** TOPTOUR Core integrácia

## Bezpečnostné poznámky

- ❌ Plugin nepoužíva Service Worker (zakazané!)
- ❌ Plugin neposlúcha na verejné endpointy bez autentifikácie
- ❌ Plugin neskrapuje weby automaticky (iba s manuálnym importom v budúcnosti)
- ❌ Plugin nefunčuje offline bez webového servera
- ✅ Všetky dáta sú interné a chránené oprávneniami

## Pôvod názvu

**Reference** = dôkazy, linkovia, pramene, podklady  
**Finder** = hľadač, zbierač  

Plugin "hľadá" a zbiera referencie z reálnych skúseností hostí pred tým, než sa oň vytvoria hodnotiace kritériá.

## Licencia

GPL v2 alebo novšia. Pozri `LICENSE` alebo https://www.gnu.org/licenses/gpl-2.0.html

## Autor

TOPTOUR  
https://toptour.sk

## Podpora

Kontakt: development@toptour.sk (budúcnos)

---

**Status:** 🔄 Aktívne interné používanie (stabilizačná fáza)
**Posledná aktualizácia:** 2026-05-15
