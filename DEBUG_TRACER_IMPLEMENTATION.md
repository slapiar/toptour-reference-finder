# AI Debug Tracer - Implementation Summary

## Overview
Implementoval som **Debug Tracer** - modálny dialóg pre step-by-step vizualizáciu AI procesov v plugine TOPTOUR Reference Finder. Trasovač umožňuje manažérom kontrolovať jednotlivé kroky spracovanie, pozrieť si medziľahlé dáta a fotografie.

## Features Implemented

### 1. **Modal Interface** (`admin/views/debug-tracer-modal.php`)
- Modálne okno s prehľadným UI
- 4 hlavné sekcie:
  - **Trasovač procesu**: Stav a popis aktuálneho kroku
  - **Progress bar**: Vizuálne zobrazenie pokroku cez jednotlivé kroky
  - **Data Preview Tabs**: 
    - Vstupné údaje (Input)
    - Výstupné údaje (Output)
    - Fotodôkazy (Photos)
    - Denník (Log)

### 2. **Step-by-Step Execution** (`admin/assets/tracer.js`)
Trasovač vykonáva 4 hlavné kroky:
1. **Inicializácia** - Príprava prostredia a načítanie konfigurácie
2. **Generovanie batchu** - Čítanie dát z úlohy a vytvorenie JSON batchu
3. **Spracovanie AI** - Odoslanie batchu na AI a čakanie na odpoveď
4. **Import výsledkov** - Import AI výsledkov do modulov (Findings, Photo Evidence)

Každý krok sa vykonáva individuálne a užívateľ musí kliknúť na "Pokračovať" aby prešiel na ďalší krok.

### 3. **REST API Endpoints** (`includes/class-debug-tracer-api.php`)
Nové REST endpointy pod `/wp-json/toptour/v1/tracer/`:

| Endpoint | Metóda | Popis |
|----------|--------|-------|
| `/tracer/initialize` | POST | Inicializácia trasovača a načítanie konfigurácie |
| `/tracer/generate-batch` | POST | Generovanie batchu z úlohy |
| `/tracer/process-ai` | POST | Simulácia AI spracovania (mock response) |
| `/tracer/import-results` | POST | Import výsledkov a načítanie fotiek |

Všetky endpointy vyžadujú:
- Prihlásenie užívateľa s právom `manage_toptour_references`
- Valídny WordPress REST API nonce

### 4. **Integration with Collection Tasks** (`admin/views/collection-tasks.php`)
- **"Odoslať do AI" tlačítko** sa zmenilo z presmerovania na otvorenie modálneho okna
- Namiesto GET requestu sa teraz používa JavaScropt na otvorenie tracer interface
- Užívateľ môže kontrolovať jednotlivé kroky bez page reload-u

### 5. **UI/UX Features**
- ✅ Farebne označené log entries (info, success, error, warning)
- ✅ Automatické posunutie logu dole pri nových zprávách
- ✅ Responsívne rozloženie s tab-ami na prepínanie údajov
- ✅ Fotky sa zobrazujú v grid formáte s miniatúrami
- ✅ JSON dáta sa zobrazujú vo formatted a čitateľnej forme

## File Structure

```
admin/
├── assets/
│   └── tracer.js (NEW - 450+ lines)
│   └── admin.css (enqueue tracer CSS inline v HTML)
└── views/
    ├── debug-tracer-modal.php (NEW - 700+ lines)
    └── collection-tasks.php (MODIFIED)

includes/
├── class-debug-tracer-api.php (NEW - 400+ lines)
├── class-loader.php (MODIFIED - add tracer API load)
├── class-admin.php (MODIFIED - enqueue tracer JS)
└── class-photo-evidence.php (used by tracer)
```

## Key Classes & Methods

### `Toptour_Ref_Debug_Tracer_API`
Hlavná trieda pre REST API:
- `register_routes()` - Registrácia všetkých tracer endpointov
- `initialize()` - Inicializácia tracer session
- `generate_batch()` - Generovanie batchu
- `process_ai()` - Spracovanie AI (momentálne mock)
- `import_results()` - Import výsledkov

### `TracerController` (tracer.js)
JavaScript kontrolér:
- `open(taskId)` - Otvorenie tracer modálu
- `processNextStep()` - Spustenie ďalšieho kroku
- `stepInitialize()` - Krok 1
- `stepGenerateBatch()` - Krok 2
- `stepProcessAI()` - Krok 3
- `stepImportResults()` - Krok 4

## Data Flow

```
1. Užívateľ klika na "Odoslať do AI" v Collection Tasks
                    ↓
2. JS otvorí tracer modal a inicializuje TracerController
                    ↓
3. Užívateľ klika "Spracovať AI Inbox teraz"
                    ↓
4. TracerController volá REST API endpoints postupne
                    ↓
5. Každý endpoint:
   - Vykonáva svoju akciu
   - Vráti medziľahlé dáta
   - Tracerň zobrazia dáta v príslušnom tabe
                    ↓
6. Užívateľ môže skúmať dáta a pokračovať na ďalší krok
                    ↓
7. Po všetkých krokoch sa zobrazí zoznam fotiek a výsledkov
```

## Security Considerations

✅ Všetky REST API endpointy vyžadujú `manage_toptour_references` capability
✅ Nonce validácia pre všetky POST requesty
✅ Všetky užívateľské vstupy sú sanitizované
✅ Bezpečné spracovanie JSON dát

## Browser Compatibility

- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Používajú moderné ES6 features (fetch API, async/await)

## Next Steps & Future Enhancements

1. **Mock AI Bridge** → Real AI Integration
   - Momentálne sa `/tracer/process-ai` vracia mock response
   - Integrovať reálne OpenAI API calls

2. **Photo Evidence Enhancement**
   - Zobraziť fotografie v agende Fotodôkazy priamo z tracer modálu
   - Možnosť kliknutia na fotku na zobrazenie v detaile

3. **Real-time Updates**
   - Implementovať WebSockets pre real-time progres AI spracovania
   - Live update log entries ak by AI trvalo dlho

4. **Export & Reports**
   - Možnosť exportovať tracer log ako PDF
   - Uloženie tracer sessionu na budúcu analýzu

5. **Batch Processing**
   - Spracovávať viaceré úlohy v rade
   - Progress bar pre viaceré úlohy

## Testing Checklist

- [ ] Otvorenie tracer modálu z Collection Tasks
- [ ] Spustenie Krok 1 (Inicializácia)
- [ ] Zobrazenie konfigurácie v Input tab
- [ ] Spustenie Krok 2 (Generovanie batchu)
- [ ] Zobrazenie JSON payload v Input tab
- [ ] Spustenie Krok 3 (Spracovanie AI)
- [ ] Zobrazenie AI response v Output tab
- [ ] Spustenie Krok 4 (Import)
- [ ] Zobrazenie fotiek v Photos tab
- [ ] Zobrazenie log entries s správnymi farbami
- [ ] Nonce a bezpečnosť - test bez nonce
- [ ] Test s neexistujúcou úlohou

## API Response Examples

### `/tracer/initialize` Response
```json
{
  "success": true,
  "tracer_run_id": "tracer-123-uuid",
  "task": {
    "id": 123,
    "title": "Moja úloha",
    "destination": "Praha"
  },
  "config": {
    "ai_enabled": 1,
    "ai_model": "gpt-4o-mini",
    "max_tokens": 1800,
    "temperature": 0.2,
    "batch_limit": 5
  }
}
```

### `/tracer/generate-batch` Response
```json
{
  "success": true,
  "batch_id": "batch-123",
  "filename": "batch-123.in.json",
  "record_count": 5,
  "batch_payload": { ... }
}
```

### `/tracer/import-results` Response
```json
{
  "success": true,
  "batch_id": "batch-123",
  "findings_created": 3,
  "photos_created": 5,
  "sources_processed": 1,
  "photos": [
    {
      "id": 1,
      "description": "Foto kúpeľne",
      "photo_url": "https://...",
      "thumbnail_url": "https://..."
    }
  ]
}
```

## License & Attribution
Implementované ako časť pluginu TOPTOUR Reference Finder v0.2.14
