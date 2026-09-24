# Architecture

How Rocket Coding is put together.

---

## System Overview

Rocket Coding is a Laravel monolith with a React prototype frontend, and an async job system for slow operations (OCR, LLM calls).

```
┌─────────────────────────────────────────────────────────────┐
│                         Browser                              │
│  React (createElement pattern) → public/app/*.html          │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTPS
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                        Nginx                                 │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                   Laravel (PHP 8.2)                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────────┐  │
│  │ Routes   │→ │ Requests │→ │Controllers│→ │ Services  │  │
│  └──────────┘  └──────────┘  └──────────┘  └─────┬──────┘  │
│                                                   │         │
│                                    ┌──────────────┴───────┐ │
│                                    ▼                      ▼ │
│                              ┌──────────┐         ┌─────────┐│
│                              │ Eloquent │         │  Jobs   ││
│                              └────┬─────┘         └────┬────┘│
└───────────────────────────────────┼─────────────────────┼────┘
                                    │                     │
                                    ▼                     ▼
                             ┌────────────┐       ┌──────────────┐
                             │  MySQL 8   │       │ Redis Queue  │
                             └────────────┘       └──────┬───────┘
                                                         │
                                                         ▼
                                                  ┌──────────────┐
                                                  │ Queue Worker │
                                                  │ (Supervisor) │
                                                  └──────┬───────┘
                                                         │
                       ┌─────────────────────────────────┼──────────────────┐
                       ▼                                 ▼                  ▼
                ┌─────────────┐                  ┌──────────────┐    ┌─────────────┐
                │ Anthropic   │                  │   OpenAI     │    │AWS Textract │
                │ Claude API  │                  │  (fallback)  │    │    (OCR)    │
                └─────────────┘                  └──────────────┘    └─────────────┘
```

---

## Core Domains

### 1. Documents
Scanned PDFs, chart notes, dictations. Enters system via upload. OCR'd via AWS Textract if scanned. Text-extracted and normalized before coding.

### 2. Encounters
A clinical visit. Has patient info, provider, chief complaint, assessment, plan, procedures. Source of truth for what happened.

### 3. Codes
CPT, ICD-10, HCPCS, modifiers. Produced by the AI coding pipeline and/or human coders. Every code has a `confidence`, `source` (ai/human/edited), and `rationale`.

### 4. Audits
Case-level coding reviews. Track findings (upcoding, undercoding, modifier errors), financial impact, and recommended actions.

### 5. Appeals
Generated from audit findings or carrier denials. Letter library templates + AI-populated specifics. CA claims use Labor Code §4603.2 references.

---

## The AI Coding Pipeline

This is the core of Rocket Coding. It runs as a chain of queued jobs:

```
Document Upload
     │
     ▼
┌──────────────┐
│ OCR Job      │ ──> AWS Textract (if scanned)
└──────┬───────┘
       ▼
┌──────────────┐
│ Extract Job  │ ──> Normalize text, parse structure
└──────┬───────┘
       ▼
┌──────────────┐
│ Redact Job   │ ──> PHI redaction before LLM call
└──────┬───────┘
       ▼
┌──────────────┐
│ Code Job     │ ──> Anthropic Claude (primary)
│              │ ──> OpenAI GPT-4 (fallback)
└──────┬───────┘
       ▼
┌──────────────┐
│ Validate Job │ ──> Rule engine: bilateral mod check,
│              │     E/M level support, NCCI edits
└──────┬───────┘
       ▼
┌──────────────┐
│ Review Queue │ ──> CPC human review (optional per tier)
└──────────────┘
```

**Service boundaries:**
- `App\Services\AI\AnthropicCodingService` — primary LLM calls
- `App\Services\AI\OpenAICodingService` — fallback
- `App\Services\AI\CodingOrchestrator` — which service to use, retry logic
- `App\Services\PHI\PHIRedactor` — strips identifiable info before LLM
- `App\Services\OCR\TextractService` — AWS Textract wrapper
- `App\Services\Coding\NCCIValidator` — bundling/unbundling rule checks
- `App\Services\Coding\ModifierValidator` — modifier appropriateness

---

## Pricing Tiers (affects features enabled)

| Tier | Monthly Platform Fee | Auto-Code | Human Review | Ortho+ |
|------|----------------------|-----------|--------------|--------|
| Bronze | $299 | ✅ | ❌ | ❌ |
| Silver | $599 | ✅ | ✅ (sample) | ❌ |
| Gold | $999 | ✅ | ✅ (100%) | ❌ |
| Ortho+ | $1,499 | ✅ | ✅ (100%) | ✅ |

Per-claim fees on top:
- Auto-coding: $1.00/claim
- Full-service billing: $5.00/claim

Tier flags live on the `Clinic` model. Feature gating via Laravel Gates.

---

## External Integrations

Rocket Coding integrates with clinic-side EHR and billing systems via REST webhooks. Integration specs are provided to each clinic on onboarding. All integrations use signed HMAC webhooks + JSON REST.

See [`API.md`](API.md) for webhook specs.

---

## Data Flow: A Complete Charge

1. Clinic uploads dictation or chart note (via UI or integration webhook)
2. Rocket Coding queues the coding pipeline
3. Pipeline completes → codes returned via API / webhook
4. Clinic's billing system submits the claim
5. Payer responds with ERA 835
6. If denied/underpaid, clinic webhooks Rocket Coding: "consider appeal"
7. Audit engine checks coding vs payment vs fee schedule
8. If appeal warranted, Rocket Coding drafts appeal letter (AI-populated)
9. Letter goes to CPC review queue → filed with carrier

---

## Deployment

- **Staging:** auto-deploy from `develop` branch
- **Production:** manual deploy from `main` branch, tagged releases only
- Zero-downtime deploys via Laravel Envoyer or similar
- Run `php artisan down` → deploy → `php artisan up` for breaking migrations

---

## Observability

- **Logs:** Laravel logs → stdout in Docker → aggregated in prod (Datadog/CloudWatch)
- **Errors:** Sentry or Bugsnag (TBD)
- **Metrics:** Every LLM call logs: model, tokens, cost, latency, success/failure
- **Audit log:** Every PHI access logged with user, timestamp, IP, resource

---

## Open Architectural Questions

Things we haven't decided yet — contributions welcome:

1. Move coding pipeline to a separate service (Python/FastAPI) for ML tooling? Or keep in Laravel?
2. GraphQL or REST for external integration?
3. Event sourcing for audit trail?
4. Multi-tenant DB strategy: single DB + tenant_id, or DB-per-clinic?

Discuss in GitHub Discussions.
