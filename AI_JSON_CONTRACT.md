# TOPTOUR AI Bridge JSON Contract

Version: 1.0
Scope: File-based AI bridge only (`inbox/*.json` -> OpenAI -> `outbox/*.out.json`)

## Purpose

This contract defines the exact JSON shape used by the AI bridge module.

- AI does not access plugin database tables directly.
- AI reads only the inbox JSON payload.
- AI writes a structured outbox JSON payload for a separate importer/reviewer workflow.
- No definitive verdicts. Outputs are candidate-level only (`pending_review`, `candidate`, `needs_verification`).

## File Locations

Base directory is resolved by WordPress uploads path:

- `base_dir`: `<wp_upload_dir>/toptour-ref-ai`
- `inbox_dir`: `<base_dir>/inbox`
- `outbox_dir`: `<base_dir>/outbox`
- `archive_dir`: `<base_dir>/archive`
- `error_dir`: `<base_dir>/error`

## Input Contract (Inbox)

Filename pattern:
- `<any-name>.json`

Root type:
- JSON object

Required fields:
- `question` (string)

Optional fields:
- `batch_id` (string)
- `task_id` (integer, >= 0)
- `context` (object | array)
- `constraints` (string)
- `meta` (object)

Idempotency rules:
- `batch_id` SHOULD be unique per logical batch.
- Outbox importer enforces one-time processing by `batch_id` via internal registry.
- If `batch_id` is missing, importer derives a synthetic idempotency key from payload metadata.

### Input Schema (JSON Schema Draft-07)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://toptour.sk/schemas/ai-bridge-input-1.0.json",
  "title": "TOPTOUR AI Bridge Input",
  "type": "object",
  "additionalProperties": true,
  "required": ["question"],
  "properties": {
    "batch_id": {
      "type": "string",
      "minLength": 1,
      "maxLength": 200
    },
    "task_id": {
      "type": "integer",
      "minimum": 0
    },
    "question": {
      "type": "string",
      "minLength": 1,
      "maxLength": 50000
    },
    "constraints": {
      "type": "string",
      "maxLength": 20000
    },
    "context": {
      "oneOf": [
        { "type": "object" },
        { "type": "array" }
      ]
    },
    "meta": {
      "type": "object"
    }
  }
}
```

### Minimal Input Example

```json
{
  "question": "Analyze negative guest signals for Sardinia resorts and propose candidate findings."
}
```

### Full Input Example

```json
{
  "batch_id": "negativa-sardinia-2026-05-16-01",
  "task_id": 123,
  "question": "Find candidate negative signals for Sardinia resorts based on provided context.",
  "constraints": "Use only provided context. Do not make definitive claims. Return pending review candidates.",
  "context": {
    "destination": "Sardinia",
    "source_hints": ["TripAdvisor", "Booking.com", "Ostrovok"],
    "signal_categories": [
      "star_rating_mismatch",
      "weak_all_inclusive",
      "hidden_beach_fees"
    ]
  },
  "meta": {
    "requested_by": "manager",
    "locale": "sk-SK"
  }
}
```

## Output Contract (Outbox)

Filename pattern:
- Input `name.json` -> Output `name.out.json`

Root type:
- JSON object

Top-level required fields:
- `version` (string)
- `status` (string: `ok` | `error`)
- `generated_at` (ISO-8601 datetime string)
- `input` (object)
- `structured_output` (object)

Optional top-level fields:
- `ai` (object)
- `error` (string)

### Output Schema (JSON Schema Draft-07)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "$id": "https://toptour.sk/schemas/ai-bridge-output-1.0.json",
  "title": "TOPTOUR AI Bridge Output",
  "type": "object",
  "additionalProperties": true,
  "required": ["version", "status", "generated_at", "input", "structured_output"],
  "properties": {
    "version": {
      "type": "string",
      "const": "1.0"
    },
    "status": {
      "type": "string",
      "enum": ["ok", "error"]
    },
    "generated_at": {
      "type": "string",
      "format": "date-time"
    },
    "input": {
      "type": "object",
      "additionalProperties": true,
      "properties": {
        "batch_id": { "type": "string" },
        "task_id": { "type": "integer", "minimum": 0 },
        "question": { "type": "string" }
      }
    },
    "ai": {
      "type": "object",
      "additionalProperties": true,
      "properties": {
        "model": { "type": "string" },
        "raw_response": { "type": "string" }
      }
    },
    "error": {
      "type": "string"
    },
    "structured_output": {
      "type": "object",
      "additionalProperties": true,
      "required": [
        "status",
        "answer_summary",
        "needs_follow_up",
        "follow_up_question",
        "candidate_sources",
        "candidate_facilities",
        "pending_findings",
        "photo_evidence_candidates",
        "import_notes"
      ],
      "properties": {
        "status": {
          "type": "string",
          "enum": ["ok", "needs_follow_up", "error"]
        },
        "answer_summary": {
          "type": "string"
        },
        "needs_follow_up": {
          "type": "boolean"
        },
        "follow_up_question": {
          "type": "string"
        },
        "candidate_sources": {
          "type": "array",
          "items": {
            "type": "object",
            "additionalProperties": true,
            "properties": {
              "title": { "type": "string" },
              "url": { "type": "string" },
              "platform": { "type": "string" },
              "status": {
                "type": "string",
                "enum": ["candidate", "pending_review", "needs_verification"]
              },
              "task_id": { "type": "integer", "minimum": 0 },
              "source_id": { "type": "integer", "minimum": 0 },
              "facility_id": { "type": "integer", "minimum": 0 },
              "destination_id": { "type": "integer", "minimum": 0 },
              "notes": { "type": "string" }
            }
          }
        },
        "candidate_facilities": {
          "type": "array",
          "items": {
            "type": "object",
            "additionalProperties": true,
            "properties": {
              "name": { "type": "string" },
              "status": {
                "type": "string",
                "enum": ["possible_match", "possible_duplicate", "requires_review", "pending_review"]
              },
              "task_id": { "type": "integer", "minimum": 0 },
              "facility_id": { "type": "integer", "minimum": 0 },
              "destination_id": { "type": "integer", "minimum": 0 },
              "notes": { "type": "string" }
            }
          }
        },
        "pending_findings": {
          "type": "array",
          "items": {
            "type": "object",
            "additionalProperties": true,
            "properties": {
              "category": { "type": "string" },
              "summary": { "type": "string" },
              "status": {
                "type": "string",
                "enum": ["pending_review", "candidate", "needs_verification"]
              },
              "task_id": { "type": "integer", "minimum": 0 },
              "source_id": { "type": "integer", "minimum": 0 },
              "facility_id": { "type": "integer", "minimum": 0 },
              "destination_id": { "type": "integer", "minimum": 0 },
              "notes": { "type": "string" }
            }
          }
        },
        "photo_evidence_candidates": {
          "type": "array",
          "items": {
            "type": "object",
            "additionalProperties": true,
            "properties": {
              "source_url": { "type": "string" },
              "status": {
                "type": "string",
                "enum": ["pending_visual_review", "candidate", "needs_verification"]
              },
              "task_id": { "type": "integer", "minimum": 0 },
              "source_id": { "type": "integer", "minimum": 0 },
              "facility_id": { "type": "integer", "minimum": 0 },
              "destination_id": { "type": "integer", "minimum": 0 },
              "notes": { "type": "string" }
            }
          }
        },
        "import_notes": {
          "type": "array",
          "items": { "type": "string" }
        }
      }
    }
  }
}
```

### Successful Output Example

```json
{
  "version": "1.0",
  "status": "ok",
  "generated_at": "2026-05-16T19:30:00Z",
  "input": {
    "batch_id": "negativa-sardinia-2026-05-16-01",
    "task_id": 123,
    "question": "Find candidate negative signals for Sardinia resorts based on provided context."
  },
  "ai": {
    "model": "gpt-4o-mini",
    "raw_response": "{...json from model...}"
  },
  "structured_output": {
    "status": "needs_follow_up",
    "answer_summary": "Identified candidate negative patterns for Sardinia resort context.",
    "needs_follow_up": true,
    "follow_up_question": "Please provide 3-5 concrete source URLs to increase confidence.",
    "candidate_sources": [
      {
        "title": "TripAdvisor Sardinia resort complaints candidate",
        "url": "https://www.tripadvisor.com/",
        "platform": "TripAdvisor",
        "status": "pending_review",
        "task_id": 123,
        "notes": "Candidate only, not verified."
      }
    ],
    "candidate_facilities": [
      {
        "name": "Mangia's Sardinia Resort",
        "status": "possible_match",
        "task_id": 123,
        "notes": "Requires manual entity matching."
      }
    ],
    "pending_findings": [
      {
        "category": "hidden_beach_fees",
        "summary": "Candidate signal: additional beach service charges reported.",
        "status": "needs_verification",
        "task_id": 123
      }
    ],
    "photo_evidence_candidates": [
      {
        "source_url": "https://www.tripadvisor.com/",
        "status": "pending_visual_review",
        "task_id": 123
      }
    ],
    "import_notes": [
      "no_definitive_claims",
      "manual_review_required"
    ]
  }
}
```

### Error Output Example

```json
{
  "version": "1.0",
  "status": "error",
  "generated_at": "2026-05-16T19:35:00Z",
  "input": {
    "batch_id": "negativa-sardinia-2026-05-16-01",
    "task_id": 123,
    "question": "Find candidate negative signals for Sardinia resorts based on provided context."
  },
  "error": "OpenAI HTTP 401",
  "structured_output": {
    "status": "error",
    "answer_summary": "",
    "needs_follow_up": true,
    "follow_up_question": "Doplň prosím kontext a skús otázku znova.",
    "candidate_sources": [],
    "candidate_facilities": [],
    "pending_findings": [],
    "photo_evidence_candidates": [],
    "import_notes": ["openai_error"]
  }
}
```

## Validation Rules (Operational)

Input:
- Reject inbox files with invalid JSON.
- Reject inbox files with missing/empty `question`.
- Move invalid files to `error/`.

Output:
- Always produce `version`, `status`, `generated_at`, `input`, `structured_output`.
- Keep all generated records in review-safe states only.
- Never mark findings as final verdict.
- Never claim visual proof without manual review.
- Runtime validator in AI bridge checks top-level/output structure before writing to outbox; invalid payload is replaced by safe `status=error` output.

Concurrency rules:
- Inbox and outbox files are claimed using processing rename (`*.processing`) before handling.
- Worker-level lock is used to avoid parallel bridge/import runs within one WP instance.
- Processing claim failure means another worker already claimed the file.
- If outbox write fails after claim, inbox file claim is rolled back to original path.

Dedupe rules in importer:
- Findings use deterministic hash dedupe (task + source URL + summary + target).
- Photo evidence candidates are deduped by `(related_collection_task_id, source_id, evidence_url)`.

## Importer Expectations (Next Layer)

Importer should:
- Treat all arrays as candidate-level records.
- Map only to pending/review states in plugin modules.
- Require manager decision before acceptance/public use.
- Preserve traceability via `batch_id`, `task_id`, and `import_notes`.

## Backward Compatibility

- Contract version is `1.0`.
- Future schema changes should increment contract version and keep parser compatibility where possible.
