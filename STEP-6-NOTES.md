# Step 6 — AI pipeline + queue infrastructure

Status: **complete** (core pipeline). Generated 2026-04-21. 103/103 Pest tests passing (6 new in this step).

## Scope realism

The launcher's Step 6 calls for:

1. **The 6-stage AI pipeline** on Redis queues — ✅ delivered
2. **Monthly billing job** (runs 1st of the month) — **deferred** (schema + Invoice model already in place; the job is a single cron-registered class that will land in Step 6b or Step 7 alongside ACH setup)
3. **ACH debit job** (runs 10th) — deferred (needs Stripe/Plaid adapter)
4. **Send invoice email job** — deferred (paired with billing cycle; needs a Mailable)
5. **Cleanup temp files daily** — deferred (one-liner cron, land with other scheduled tasks)
6. **Audit log flush every 5 min** — not needed yet (we write audit rows synchronously; flush becomes relevant only at much higher scale)

Step 6 **this step** = the AI pipeline, which is the hardest and most valuable part. The remaining scheduled jobs are trivial Laravel cron + a handful of service calls — they'll land as a tight batch in Step 6b when we wire the scheduler.

## What shipped — 21 new files

| Layer | Count | Location |
|---|---|---|
| Config | 1 | `config/anthropic.php` — model names per stage, token caps, retries, stage order |
| Anthropic client | 4 | Interface + impl + `ModelResponse` DTO + `StructuredOutputException` |
| Prompts | 1 | `Prompts.php` — 6 system prompts as constants, CPT citation invariant enforced in prompt text |
| Confidence scoring | 1 | `ConfidenceScorer.php` — HARD-FAILS on ungrounded citations (not "penalty") |
| Pipeline | 3 | `AbstractStage`, `StageResult` DTO, `PipelineOrchestrator` |
| Stages | 6 | `ExtractPatientDataStage`, `DraftReportStage`, `SuggestCptStage`, `ApplyModifiersStage`, `MapDiagnosesStage`, `CheckComplianceStage` |
| Job | 1 | `RunAiPipeline` — dispatched on case create / case rerun-ai |
| Events | 3 | `AiPipelineStarted`, `AiPipelineCompleted`, `AiPipelineFailed` |
| Test scaffolding | 1 | `FakeAnthropicClient` (under `tests/Support/`) |

## The pipeline

Case created via `POST /api/cases` → `MedicalCaseService::create` dispatches `RunAiPipeline` with `afterCommit()` → worker pulls from `ai-pipeline` queue → `PipelineOrchestrator` runs each stage in order, strict sequential:

```
1. extractPatientData   (Haiku, cheap)   — extracts name/dob/dos/claim#/gender/employer/provider/doi
2. draftReport          (Sonnet)         — produces report body sections per ReportType
3. suggestCpt           (Sonnet)         — proposes CPT codes WITH citation quotes
4. applyModifiers       (Sonnet)         — per-state modifier recommendations
5. mapDiagnoses         (Sonnet)         — ICD-10 codes WITH citation quotes
6. checkCompliance      (Sonnet)         — state-specific compliance check
```

**Per-stage behavior:**
- Each stage calls Anthropic via the `AnthropicClient` (which handles 3-retry-with-backoff internally)
- Stage output is parsed as strict JSON; on parse failure the client retries; 3 strikeouts raise `StructuredOutputException`
- Stage's `guard()` catches the exception and returns `StageResult::fail`
- Orchestrator sees the failure → marks case `needsReview`, appends audit entry, emits `AiPipelineFailed`, halts remaining stages

**On success of all 6:**
- Case moves to `PendingDiagnosisApproval` (coder review gate)
- `aiCompletedAt` timestamp set
- `AiPipelineCompleted` event dispatched with per-stage token breakdown
- Overall confidence = MIN across all stages (most conservative stage drags the whole case)

## The anti-hallucination defense

Security invariant (from the feedback_security_invariants memory): **CPT codes must cite the source sentence from clinician notes.** The defense has three layers:

1. **Prompt side**: `SUGGEST_CPT` prompt text explicitly requires a `citation` field per code, threatens rejection, instructs "conservative under-coding is ACCEPTABLE; hallucinated codes are NOT."

2. **Scoring side**: `ConfidenceScorer::scoreCodeSuggestion` does NOT deduct-and-multiply for missing / unmatched citations. It **hard-fails to 0.0** if:
   - The `citation` field is missing
   - The citation string is empty
   - The citation does not appear verbatim in the source notes (case + whitespace normalized)

3. **Stage filter side**: `SuggestCptStage` and `MapDiagnosesStage` iterate the model's raw output and **drop any entry whose ConfidenceScorer score is below 0.50**. The hard-fail at scoring time means any ungrounded suggestion scores 0 and gets dropped.

Test `it rejects CPT codes whose citations do not appear in the notes` demonstrates this end-to-end — the model returns 2 codes (one grounded, one hallucinated), and only the grounded one survives to `cases.suggestedCPT`.

## Token budget + escalation

Per-case hard cap: `ANTHROPIC_MAX_TOKENS_PER_CASE` (default 50000). If `aiTokensUsed` hits the cap before a stage runs, the orchestrator aborts with error code `token_budget_exceeded`, moves case to `needsReview`, emits `AiPipelineFailed`. Defense against a single pathological case draining the Anthropic budget.

Per-request cap: `ANTHROPIC_MAX_TOKENS_PER_REQUEST` (default 4096). Passed to Anthropic as `max_tokens`.

## Retries

**Where retries happen:** `AnthropicClient::generateStructured` — 3 attempts with exponential backoff (2s, 4s, 8s) on both parse failures and API errors.

**Where they DON'T happen:** the job level. `RunAiPipeline::$tries = 1`. Retrying a failed pipeline job would re-run successful early stages, wasting tokens. Instead, when the orchestrator hits a stage failure it moves the case to `needsReview` — a coder picks it up manually, potentially using the `POST /api/cases/{id}/rerun-ai` endpoint to dispatch a fresh pipeline run after fixing input.

## Tests (6 new)

| Test | Covers |
|---|---|
| `it runs the pipeline end-to-end` | All 6 stages run, case transitions Processing → PendingDiagnosisApproval, tokens tracked, events fired, fields populated |
| `it rejects CPT codes whose citations do not appear in the notes` | The critical anti-hallucination test — grounded code survives, fabricated code drops |
| `it moves a case to needsReview when a stage fails 3 times` | Failure escalation — case status, audit entry, `AiPipelineFailed` event, remaining stages don't run |
| `it halts the pipeline when the token budget is exceeded` | Budget gate fires before first stage |
| `it dispatches RunAiPipeline job when a case is created via the API` | Case create → Bus dispatch verified with `Bus::fake` |
| `it writes activity_logs entries per stage completion` | Stage transitions emit the activity events the Deployer audit dashboard consumes |

## Architecture decisions

### Decision 1: Anthropic client is an interface, injected everywhere

Tests bind `FakeAnthropicClient` over the `AnthropicClientInterface` binding — **no real Anthropic API calls happen during CI, ever**. Production uses `AnthropicClient` via `AppServiceProvider::register`. Swapping models, providers, or even upgrading the SDK is a one-place change.

### Decision 2: PipelineOrchestrator is synchronous; the Job is the async boundary

The orchestrator itself is a plain service that walks stages in order. `RunAiPipeline` is the only async wrapper — it dispatches to Redis, the worker resolves the orchestrator, runs the pipeline, done. This keeps the orchestrator unit-testable without queue machinery.

### Decision 3: No mid-pipeline rollback on failure

When stage 3 (SuggestCpt) fails after stage 2 (DraftReport) succeeded, the draft stays. That's intentional — the coder picking up a `needsReview` case may want to see what the model produced before the failure, to adjudicate. Database writes per stage are small and idempotent on re-run (the case's `suggestedCPT`/`suggestedDX`/`modifiers` are overwritten, not appended).

### Decision 4: Confidence = MIN across stages, not average

One poor stage drags the whole case's overall confidence down. This surfaces problems in the Deployer audit dashboard instead of averaging them out. A case with compliance confidence 0.3 and CPT confidence 0.95 shouldn't read as 0.625 overall — the 0.3 is a signal the coder needs to see.

### Decision 5: Stage output mutates the MedicalCase directly

Each stage writes to case columns (`suggestedCPT`, `suggestedDX`, `modifiers`, `stateCompliance`, etc.) via `forceFill`. No intermediate "stage result" table. Rationale: the JSON columns ARE the canonical output; storing them on the case row means the frontend's case-detail endpoint gets them for free.

Side effect: the `PhiObserver` fires `PhiUpdated` events on every stage's save — the AI pipeline's PHI trail lands in `audit_logs` automatically. No extra wiring.

### Decision 6: Retry at the API call, not at the job

- **At the API call:** 3 retries with exponential backoff handle transient errors (rate limits, 503s, malformed output).
- **At the job:** `$tries = 1`. We DON'T retry the whole pipeline. Retrying would re-run successful early stages and waste tokens. Human intervention via `rerun-ai` after fixing input is the right response to a pipeline failure.

### Decision 7: Queue uses Redis, connection `ai-pipeline` (logical queue)

`QUEUE_CONNECTION=redis` in `.env`. The `RunAiPipeline` job sets `->onQueue('ai-pipeline')`. Run a dedicated worker for it:

```bash
docker compose exec laravel.test php artisan queue:work redis --queue=ai-pipeline --tries=1 --timeout=900
```

Horizon supervision will land in Step 6b with the billing cron. For now a plain queue worker is fine.

## How to run

```bash
# Full suite (103 tests, ~93s)
docker compose --project-directory codes exec laravel.test php artisan test --testsuite=Feature --compact

# Just the AI pipeline tests
docker compose --project-directory codes exec laravel.test php artisan test --filter "PipelineTest"

# Live smoke — creates a case, watches the queue process it.
# (Requires ANTHROPIC_API_KEY set in .env.)
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"clinic@valleymed.com","password":"demo123"}' | jq -r .data.accessToken)

curl -s -X POST http://localhost:8080/api/cases \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"clinic":"<clinicUuid>","patient":"Test","state":"CA","reportType":"initial","inputSource":"upload","notes":"..."}' | jq

# In another terminal, start the worker:
docker compose exec laravel.test php artisan queue:work redis --queue=ai-pipeline --tries=1

# Watch it process. When the stage loop finishes, GET the case:
curl -s http://localhost:8080/api/cases/{id} \
  -H "Authorization: Bearer $TOKEN" | jq '.data | {status, suggestedCPT, suggestedDX, stateCompliance}'
```

## Deferred to Step 6b

- `MonthlyBillingJob` — runs on the 1st, reads all clinics' cases for the prior month, creates `Invoice` rows via `InvoiceGenerationService`
- `InitiateAchDebitJob` — runs on the 10th, batches unpaid invoices into ACH via Stripe/Plaid
- `SendInvoiceEmailJob` + `InvoiceMail` mailable — per-clinic email with the invoice PDF attached
- `CleanupOrphanedFilesJob` — daily, removes files where the owner row was hard-deleted
- Horizon config + supervisor (the dashboard is already installed; needs `config/horizon.php` tuned for our queue names)
- Password-reset email (stubbed from Step 4) — lands here as a queued listener on `PasswordResetRequested`

---
Powered by SnapEcosystem · www.SnapSolutions.com
