# Step 6b — Billing cycle, mail, cleanup, Horizon

Status: **complete**. Generated 2026-04-21. 121/121 Pest tests passing (18 new in this step).

## What shipped — 15 new files

| Area | Count | Location |
|---|---|---|
| Invoice repository | 2 | `InvoiceRepositoryInterface` + `EloquentInvoiceRepository` |
| Billing core | 2 | `TierPricing` (constants), `InvoiceGenerationService` (per-clinic-per-period invoice math) |
| Billing jobs | 4 | `RunMonthlyBillingCycle`, `GenerateClinicInvoice`, `SendInvoiceEmail`, `InitiateAchDebit` (stubbed) |
| Mailables | 2 | `InvoiceMail`, `PasswordResetMail` (finally wired from Step 4's stub) |
| Mail templates | 2 | `resources/views/mail/invoice.blade.php`, `password-reset.blade.php` |
| Password-reset listener | 1 | `QueuePasswordResetEmail` — handles the Step-4 event |
| Maintenance | 1 | `CleanupOrphanedFiles` daily job |
| Scheduler | 1 | `routes/console.php` — 1st-of-month billing, 10th-of-month ACH, daily cleanup |
| Horizon | 2 | Installed + `config/horizon.php` tuned with 4 named supervisors; `HorizonServiceProvider::gate()` locks dashboard to admin+deployer |
| Tests | 3 files, 18 tests | `InvoiceGenerationTest`, `BillingJobsTest`, `PasswordResetMailTest` |

## The billing math

Per the JSX prototype (rocket-coding.jsx line 819 — Admin Billing invoice preview), pricing is **additive**, not tiered:

| Trigger | Line item | Rate |
|---|---|---|
| Every case completed/delivered in the period | Claim Submission | $1.75 |
| Case has non-empty `suggestedCPT` (AI actually produced output) | AI Auto-Coding | +$1.00 |
| Clinic is NOT selfCoded (Rocket coded end-to-end for them) | Full-Service Billing | +$5.00 |

A case at a non-selfCoded clinic with AI output gets all three lines → $7.75 per case.
A case at a selfCoded clinic with AI output → $2.75.
A case at a selfCoded clinic without AI output → $1.75.

Full tier matrix verified by `InvoiceGenerationTest`, 10 tests covering each permutation + period filtering + status filtering + idempotency + immutability-after-send.

## The billing cycle

```
1st of month @ 03:00 UTC  →  RunMonthlyBillingCycle
    (scheduler fires)         fan-out: one GenerateClinicInvoice per active clinic

GenerateClinicInvoice         →  creates Invoice (status=draft) + InvoiceLine[] via
    (per clinic, async)           InvoiceGenerationService, then dispatches
                                  SendInvoiceEmail

SendInvoiceEmail              →  Mail::to(clinic.email)->send(new InvoiceMail(...))
    (async, queued)               → Invoice.status = 'sent', Invoice.sentAt = now()

10th of month @ 09:00 UTC     →  scheduler reads invoices where status='sent' and
                                  dueDate <= today; fans out InitiateAchDebit per

InitiateAchDebit (STUB)       →  creates Payment row status='initiated' with a fake
    (async, queued)               provider ref; ActivityLog billing.ach.initiated
                                  → Invoice.status = 'paying'

                                  Real Stripe/Plaid integration replaces the body
                                  of InitiateAchDebit::initiateProviderDebit() when
                                  Snap Solutions has provider accounts.
```

All of this runs on the Horizon supervisor stack:

| Supervisor | Queue | Purpose | Nice | Tries |
|---|---|---|---|---|
| `supervisor-default` | `default` | user-facing jobs | 0 | 3 |
| `supervisor-ai` | `ai-pipeline` | the 6-stage pipeline from Step 6 | 0 | 1 |
| `supervisor-billing` | `billing` | everything above | 5 | 3 |
| `supervisor-notifications` | `notifications`, `maintenance` | mailables + cleanup | 5 | 5 |

Per-environment process counts scale from 1 each locally to 10+6+3+3 in production (config/horizon.php).

## Design decisions

### Decision 1: Fan-out via a per-clinic dispatcher, not a single monolithic job

`RunMonthlyBillingCycle` does NOT generate invoices inline. It loops active clinics and dispatches one `GenerateClinicInvoice` per clinic. One failure doesn't block the batch, the queue can parallelize across workers, and a retry of the entry-point job doesn't re-run clinics that already succeeded (each clinic's job is idempotent on its own).

### Decision 2: `InvoiceGenerationService` is idempotent on `(clinicId, period)` — but stops being so once the invoice is sent

First call: creates Invoice with status=draft + lines. Second call while still draft: recomputes lines + total (catches late-arriving cases). Third call after `status='sent'`: returns the existing row unchanged, logs nothing, writes nothing. This matches real-world billing — you can tweak a draft until the 1st, but once the clinic has the email, the invoice is legally frozen. An amendment goes on the next cycle.

### Decision 3: Invoice dates are deterministic — 1st and 10th of the MONTH FOLLOWING the billing period

`generateForClinicAndPeriod($clinic, '2026-03')` → `invoiceDate = 2026-04-01`, `dueDate = 2026-04-10`. Matches the JSX's invoice-preview copy: "Invoice Date: M/1/YYYY · Payment Due: M/10/YYYY · Period: Prior Month Usage". Tests assert this verbatim.

### Decision 4: ACH integration is a stub, not absent

`InitiateAchDebit` job exists, dispatches from the scheduler, and writes a Payment row with `providerRef = 'stub-<uuid>'`. When the real Stripe ACH or Plaid Transfer account lands, only `initiateProviderDebit()` changes — everything upstream (scheduler wiring, queue handling, Payment row shape, ActivityLog emission) is already correct. This matters because the deploy-time code review finds a clearly-marked stub, not silently missing plumbing.

### Decision 5: Password-reset email finally wired end-to-end

Step 4 left `PasswordResetRequested` firing with no listener consuming it. Step 6b fills that in via `QueuePasswordResetEmail` (auto-discovered by Laravel 11+ from the `handle*` naming convention) + `PasswordResetMail` + `password-reset.blade.php`. Three new tests assert the mail is queued for active users and NOT queued for unknown/inactive emails (enumeration defense still intact).

### Decision 6: Horizon dashboard is gated to admin/deployer only

`HorizonServiceProvider::gate()` returns true only for `UserRole::Admin` or `UserRole::Deployer`. Rejects null user (unauthenticated) and every other role. A clinic user logging into the web session wouldn't see the Horizon UI even if they guessed `/horizon`.

### Decision 7: `onOneServer()` on every scheduled task

Billing runs once — even if we scale the app to multiple queue workers in production. The `onOneServer()` modifier uses the cache backend to hold a lock during the scheduled window; exactly one instance executes. Each scheduled task has a unique `name()` (the lock key) — Laravel 11+ requires that.

### Decision 8: `CleanupOrphanedFiles` sweeps files older than 7 days only

Don't delete anything freshly uploaded — the owner row might be briefly inconsistent during a transaction rollback. 7-day cooldown gives normal app workflows plenty of time to settle. Chunks 200 at a time to bound memory, uses `withoutGlobalScopes()` so the sweep sees all tenants (this is infrastructure, not tenant-scoped business logic).

## How to run

```bash
# Full suite
docker compose --project-directory codes exec laravel.test php artisan test --testsuite=Feature --compact

# Just billing
docker compose --project-directory codes exec laravel.test \
  php artisan test --filter "InvoiceGenerationTest|BillingJobsTest"

# Manually trigger a billing cycle for a specific period
docker compose --project-directory codes exec laravel.test \
  php artisan tinker --execute="\\App\\Jobs\\Billing\\RunMonthlyBillingCycle::dispatch('2026-03');"

# Start a worker that drains all four queues
docker compose --project-directory codes exec laravel.test \
  php artisan queue:work redis --queue=default,ai-pipeline,billing,notifications,maintenance

# Or start Horizon (preferred — auto-scales processes per supervisor config)
docker compose --project-directory codes exec laravel.test php artisan horizon

# Dashboard (admin/deployer only): http://localhost:8080/horizon
```

## What's NOT in Step 6b

- Real Stripe/Plaid ACH integration — stub is in place, real integration drops in when Snap Solutions has a provider account
- Real PDF invoice attachment — markdown email renders the invoice inline; DomPDF attachment lands in Step 7 or 7b with prod-image work
- ACH webhook handler for async payment status (succeeded/failed) — follows real ACH integration
- Invoice CRUD endpoints for the Admin Billing screen's read side — the schema + data is populated; adding `InvoicesController` is a one-off when the frontend wires up

## Next: Step 7 — production infrastructure

Dockerfile (multi-stage, non-root, Horizon-enabled), docker-compose.prod.yml, `.github/workflows/ci.yml`, `/health` endpoint with dependency probes, `README.md` + `DEPLOY-CHECKLIST.md`.

---
Powered by SnapEcosystem · www.SnapSolutions.com
