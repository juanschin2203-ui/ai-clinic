# Step 2 — Database schema

Status: **complete**. Generated 2026-04-21.

## What got built

64 new files plus 2 edits:

| Layer | Count | Location |
|---|---|---|
| PHP enums | 9 | `app/Enums/` |
| Migrations | 23 | `database/migrations/2026_04_21_*.php` |
| Tenancy scaffolding | 3 | `app/Services/Tenancy/`, `app/Models/Scopes/`, `app/Models/Concerns/` |
| Abstract base model | 1 | `app/Models/AbstractRocketModel.php` |
| Eloquent models | 23 | `app/Models/` |
| Repository layer | 4 | `app/Repositories/Contracts/`, `app/Repositories/Eloquent/` |
| Service provider | 1 | `app/Providers/RepositoryServiceProvider.php` |
| Edited | 2 | `app/Providers/AppServiceProvider.php`, `bootstrap/providers.php` |

Also **deleted** the stock Laravel `0001_01_01_000000_create_users_table.php` — we couldn't extend it cleanly to the richer rocket-coding shape (UUID PK, `cid` FK, `loginAttempts` / `lockedUntil`, etc.) without breaking the single-file invariant of stock Laravel. Our `2026_04_21_000002_create_users_and_auth_tables.php` replaces it and also creates `sessions` + `password_reset_tokens` (keeping Laravel's one-migration-for-the-auth-trio convention).

## Launcher verifications

### 1. Every table matches JSX field shapes — VERIFIED

All 23 domain tables exist. Field names preserve the JSX casing verbatim:

- `clinics.selfCoded`, `clinics.reportFavs`, `clinics.notif`, `clinics.taxId`, `clinics.addr`
- `users.cid` (not `clinicId` — matches JSX `USERS[].cid`), `users.loginAttempts`, `users.lockedUntil`, `users.emailVerifiedAt`
- `providers.clinic` (not `clinicId` — matches JSX `PROVIDERS[].clinic`)
- `cases.clinic`, `cases.clinicName`, `cases.suggestedCPT`, `cases.suggestedDX`, `cases.stateCompliance`, `cases.claimNum`, `cases.assignedCoderId`, `cases.aiTokenBudget`
- `invoices.clinicId`, `invoices.period`, `invoices.billingMethod`
- `audit_logs.isPhiAccess`
- `refresh_tokens.tokenHash`, `refresh_tokens.usedAt`, `refresh_tokens.replacedByTokenId`

(Verified via `information_schema.columns` query — see the migration run log above this file in the session.)

### 2. Every tenant-scoped table has a clinic FK — VERIFIED

| Table | Tenant column | Matches JSX? |
|---|---|---|
| users | `cid` (nullable — admins have no clinic) | yes |
| providers | `clinic` | yes |
| clinic_admins | `clinic` | yes (from JSX `CLINIC_ADMINS[].clinic`) |
| patients | `clinicId` | added (JSX lacked this — see ambiguity #3 below) |
| appointments | `clinicId` | added |
| cases | `clinic` | yes |
| emr_connections | `clinicId` | added |
| files | `clinicId` | added |
| invoices | `clinicId` | added |
| invoice_lines | (via `invoiceId` → `invoices.clinicId`) | n/a — indirect |
| payment_methods | `clinicId` | added |
| payments | `clinicId` | added |
| audit_logs | `clinicId` (nullable — system events) | n/a — new |
| activity_logs | `clinicId` (nullable) | n/a — new |
| refresh_tokens | (via `userId` → `users.cid`) | n/a — indirect |

Every row with a direct clinic FK uses **ON DELETE CASCADE** (tenant deletion cascades), EXCEPT:
- `invoices.clinicId` → **ON DELETE RESTRICT** (financial record preservation)
- `payments.invoiceId` + `payments.clinicId` → **RESTRICT**
- `invoice_lines.caseId` → **NULLIFY** (keep line for audit if case is deleted)
- `audit_logs.clinicId` → **NULLIFY** (retain audit rows even if clinic deleted)

### 3. Every table has UUID id, camelCase timestamps — VERIFIED

`$table->uuid('id')->primary()` on 21 of 23 tables. Two exceptions (by design):
- `services.id` — preserves JSX keys `"s1"`, `"s16"`, `"sT"` so the `cases.svcs` JSON array references them without remapping
- `fee_schedules.code` — PK is the 2-letter state code for direct JSX matchup with `FEE_STATES[].code`

Timestamps are `createdAt` / `updatedAt` / `deletedAt` (not Laravel's default `created_at`). Enforced at the model level via `AbstractRocketModel`'s `CREATED_AT / UPDATED_AT / DELETED_AT` constants.

UUIDs are **v7 (time-ordered)**, from Laravel's `HasUuids` trait → `Str::orderedUuid()`. v7 gives us lexicographic ordering by creation time for free, which makes range queries and index locality better than v4. Confirmed by the Tinker output: `019db1c6-454b-7236-...` (the `019db1c6` prefix is the millisecond timestamp).

### 4. PHI tables marked — VERIFIED

Every PHI table has a `// PHI` comment in its migration header:
- `patients` (every column except id/FKs/timestamps)
- `appointments` (patient name, claim)
- `cases` (notes, claimNum, emrId, dob, dos, patient name)
- `files` where `containsPhi=true`

Step 4 adds the `PhiAuditObserver` that watches these models and emits an `AuditLog` entry on every read/write. In Step 2 we just staged the models and `audit_logs` table; wiring happens next.

### 5. Enums — VERIFIED

9 backed-string PHP enums under `app/Enums/`:

| Enum | Values |
|---|---|
| UserRole | clinic, provider, staff, admin, deployer |
| Permission | full, coder, scheduler, viewer |
| CaseStatus | processing, needsReview, pendingDiagnosisApproval, completed, delivered, deleted |
| ReportType | qme, ame, pr1, pr2, initial, followup, procedure, mmi, impairment, causation |
| InputSource | upload, dictation, batch |
| BillingMethod | ach, card |
| UsState | all 50 + DC |
| EmrStatus | connected, pending, notConnected |
| EmrType | fhir, rest, sftp, api |

Each has `label()` for display and, where useful, domain-specific methods (`UserRole::requiresClinic()`, `CaseStatus::isOpen()`, `ReportType::requiredSections()`, `Permission::canCode()`, `UsState::hasFeeSchedule()`).

Models cast the string columns to these enum instances via `$casts` (e.g., `'role' => UserRole::class`).

### 6. Indexes on FKs and hot paths — VERIFIED

| Table | Indexes |
|---|---|
| clinics | state, active, selfCoded |
| users | email (unique), role, (cid, role), (cid, active), active |
| providers | clinic, (clinic, active), npi |
| clinic_admins | clinic, (clinic, email) unique |
| patients | clinicId, (clinicId, reportStatus), emrId |
| cpt_codes / icd10_codes / hcpcs_codes | (code, effectiveFrom) unique, code, category/chapter, active |
| appointments | clinicId, (clinicId, date), status |
| **cases** | **clinic**, **status**, **(clinic, status)** (launcher-required), (clinic, reportType), assignedCoderId, state, num |
| emr_connections | (clinicId, emrSystemId) unique, clinicId, status |
| files | (ownerType, ownerId), clinicId, kind, containsPhi |
| **invoices** | **(clinicId, period) unique**, **(clinicId, period)** (launcher-required), clinicId, status, dueDate |
| invoice_lines | invoiceId, caseId, tier |
| payments | invoiceId, clinicId, status, providerRef |
| audit_logs | userId, clinicId, (entityType, entityId), action, isPhiAccess, occurredAt, (clinicId, occurredAt) |
| activity_logs | userId, clinicId, category, (category, event), severity, occurredAt |
| refresh_tokens | tokenHash (unique), userId, expiresAt, (userId, revokedAt) |

### 7. Relations — VERIFIED

The Tinker smoke run (see session transcript) confirmed:
- `Clinic::class` maps to table `clinics`
- `MedicalCase::class` maps to table `cases` (PHP class renamed to avoid the reserved `Case` keyword — URL routes still say `/api/cases`)
- `MedicalCase::getTenantColumn()` returns `'clinic'`
- `Patient::getTenantColumn()` returns `'clinicId'`
- `ClinicRepositoryInterface::class` resolves to `EloquentClinicRepository`
- TenantContext is a singleton
- UUIDs are v7 time-ordered
- Setting a clinic ID on TenantContext filters tenant-scoped queries (patients)

## Architecture decisions logged

### Decision 1: DB column names match JSX seed keys verbatim

`selfCoded`, `reportFavs`, `cid`, `clinic` (yes, as a column name for an FK — in providers, clinic_admins, cases), `sigBlock`, `taxId`, `addr` — stored exactly as the prototype names them. No snake_case conversion, no abbreviation expansion, no disambiguation.

**Why:** The user-flagged security invariant says the API contract must preserve JSX field names or the frontend silently breaks. Having the DB match too (not just the API) removes a translation layer where bugs could hide, at the cost of slightly inconsistent naming (both `cid` and `clinicId` exist because JSX uses both). Worth it.

**Trade-off documented:** The `users.cid` / `providers.clinic` / `patients.clinicId` split is a real inconsistency and will trip up new developers. `STEP-2-NOTES.md` + the migration headers call it out. If a future Gene-approved refactor renames the JSX fields to consistent names, we rename DB columns and API Resources in lockstep.

### Decision 2: UUID v7 primary keys everywhere (with two domain-motivated exceptions)

Every identity/domain table uses `$table->uuid('id')->primary()`. `HasUuids` trait on every model generates v7 (time-ordered) UUIDs via `Str::orderedUuid()`.

**Exceptions:**
- `services.id` — preserves JSX "s1"/"s16"/"sT" keys to match the `cases.svcs` JSON array
- `fee_schedules.code` — PK is the 2-letter state code

Both are reference-data tables with a small, fixed natural key. Using UUIDs there would force a remapping step in every seed.

### Decision 3: JSON columns for AI snapshots, not normalized tables

`cases.suggestedCPT`, `suggestedDX`, `modifiers`, `stateCompliance`, `emails`, `audit`, `timeline` are all JSON. The launcher recommended this; I concur.

**Rationale:** These are AI pipeline output *snapshots* — the model suggested these codes, with these confidences, at this time. They're not normalized operational data. If we needed to query "all cases where CPT 99213 was suggested," a dedicated `case_suggested_codes` table would be better. We don't today. If that changes, adding the normalized table later is a reversible migration.

**Callout for Step 6:** The AI prompt for suggestCPT MUST produce output in the shape `[{code, desc, reasoning, confidence, citation}]` — the `citation` field is where the model quotes the source sentence from the clinician notes supporting the suggested code. That's non-negotiable per the user's security invariants (AI hallucination defense). Confidence derivation also depends on citations being present, not on the model's self-reported number.

### Decision 4: Tenant isolation enforced at the ORM layer, not per-query

`BelongsToTenant` trait + `TenantScope` global scope means every query against a tenant-scoped model auto-filters by the current `TenantContext`'s `clinicId`. Controllers, services, and repositories don't need to remember to filter — it's structurally impossible to leak another tenant's data without explicitly calling `TenantContext::runWithoutTenant()`.

**Why architectural, not test-driven:** The user's security memory flagged this explicitly — "one passing test today doesn't stop someone from adding route #47 next month that forgets the check." Global scope makes forgetting impossible.

**Admin cross-tenant reads are explicit:** Rocket admins need to see across clinics. They use `TenantContext::runWithoutTenant(fn() => ...)` — every such call is easy to grep and audit-log.

**Gotcha:** If a request reaches a tenant-scoped query without TenantContext populated (e.g., forgotten middleware), the scope returns everything — NOT nothing. That's because "no tenant = seed / tinker / background job, don't filter." Step 4's middleware will assert `TenantContext::hasTenant()` for every authenticated non-admin request, closing the gap.

### Decision 5: Repository pattern is the only way models are accessed

`BaseRepository` abstract + per-model interface + per-model Eloquent impl. Services and controllers inject the *interface* — not the model, not the concrete class. `RepositoryServiceProvider` wires up the bindings.

Only `EloquentClinicRepository` exists today as the canonical example. Steps 4, 5, 6 produce the others (User, RefreshToken, MedicalCase, Patient, Invoice, EmrConnection, etc.) as they're needed, each adding its binding to `RepositoryServiceProvider::$bindings`.

**No ad-hoc `Model::query()` calls in controllers or services.** Enforced by convention (no tooling yet; we can add PHPStan rules later).

### Decision 6: Strict mass-assignment (guarded everything by default)

`AbstractRocketModel::$guarded = ['id', 'createdAt', 'updatedAt', 'deletedAt']` and each model has an explicit `$fillable` whitelist. Prevents accidental mass-assignment of sensitive fields (tenant FKs, audit metadata, password hashes, token hashes).

Also enabled `Model::preventLazyLoading()` (throw on N+1 in dev) and `Model::preventSilentlyDiscardingAttributes()` (throw when code sets an attribute the model doesn't know about) in non-production environments. Loud failures in dev = fewer surprises in prod.

### Decision 7: `AuditLog` and `ActivityLog` are append-only (`UPDATED_AT = null`)

They have only `createdAt` and `occurredAt`. There's no such thing as "updating" an audit row — every state change is a new row. Overrode `UPDATED_AT` to `null` on these two models so Eloquent's auto-timestamp behavior skips it.

**Step 7 DB hardening:** production will add MySQL row-level triggers blocking UPDATE/DELETE on these two tables. Model-level is defense-in-depth.

## Ambiguities encountered and how I resolved them

**Ambiguity 1 — JSX PATIENTS has no clinic FK; the `provider` field is just a display string.**
→ Added `patients.clinicId` (FK, cascade on delete) and `patients.providerId` (FK, null on delete). The API Resource in Step 5 will include the provider display name by joining; backend gains real tenancy + attribution.

**Ambiguity 2 — JSX uses inconsistent FK names (users→cid, providers/cases→clinic).**
→ Preserved JSX verbatim. `users.cid`, `providers.clinic`, `cases.clinic`, `clinic_admins.clinic`, `patients.clinicId` (new). The `BelongsToTenant` trait supports per-model `$tenantColumn` override — Provider, ClinicAdmin, MedicalCase set it to `'clinic'`.

**Ambiguity 3 — Five user roles but the JSX USERS seed only uses `clinic` and `admin`.**
→ Schema has room for all five (`role` column is a 20-char varchar, enum-cast via `UserRole`). The `provider`, `staff`, `deployer` roles are backend-first-class; Step 3's seed will add at least one example user per role. The UI's routing (ClinicApp / ProviderApp / etc.) can still key off the role value.

**Ambiguity 4 — JSX has `users` / `providers` / `cases` counts on each clinic row.**
→ Not stored. They're derived via `Clinic::withCount(['users', 'providers', 'cases'])`. The API Resource in Step 5 exposes them with the same keys the JSX expects (`users`, `providers`, `cases`) computed on the fly. Keeps DB denormalization-free without breaking the frontend.

**Ambiguity 5 — JSX has `users[].cn` (clinic name) stored on every user row.**
→ Not stored. Derived from the `cid → clinics.name` join. Same reasoning as #4.

**Ambiguity 6 — `Case` is a PHP reserved word, but the JSX and URL use `cases`.**
→ Table name stays `cases` (matches JSX and URL). Model class is `MedicalCase`. The Eloquent `$table = 'cases'` property pins the mapping. API route path remains `/api/cases` in Step 5.

**Ambiguity 7 — Sanctum doesn't have native refresh tokens but the launcher wants JWT-style rotation.**
→ Added `refresh_tokens` table alongside Sanctum's `personal_access_tokens` (which Step 4 will create via `php artisan install:api`). Our refresh tokens store SHA-256 hashes (never the raw token), with `usedAt` / `revokedAt` / `replacedByTokenId` columns supporting rotation-on-refresh. Step 4 wires the full flow.

**Ambiguity 8 — Reference data (CPT, ICD-10, HCPCS) — versioning strategy?**
→ Per `(code, effectiveFrom)` unique constraint. `effectiveTo` null means "currently in force." Old codes retained for historical claim coding. Step 5's Deployer-side "reference data upload" pipeline commits new releases by appending rows with fresh `effectiveFrom` dates (no destructive updates).

**Ambiguity 9 — Files: clinic-scoped vs polymorphic ownership?**
→ Both. `files.clinicId` is the tenant FK (clinic scope). `files.ownerType` + `ownerId` is the polymorphic logical owner (patient, case, invoice, etc.). A file can be clinic-owned without belonging to a specific case; a chart PDF is case-owned AND clinic-scoped. Both hold simultaneously.

**Ambiguity 10 — `stateCompliance` column shape.**
→ JSON: `{compliant: boolean, system: string, note: string, violations: string[]}`. Matches JSX exactly. If Step 6's checkCompliance AI stage evolves, we extend the JSON shape — no migration needed.

## How to run

From a fresh checkout with Docker running:

```bash
# 1. Bring up the stack
docker compose --project-directory codes up -d

# 2. Run migrations
docker compose --project-directory codes exec laravel.test php artisan migrate:fresh

# 3. Confirm
docker compose --project-directory codes exec laravel.test php artisan tinker --execute='
    echo App\Models\Clinic::count() . " clinics" . PHP_EOL;
'
# → "0 clinics" (empty DB — seed in Step 3)
```

To reset completely (drop and re-migrate):

```bash
docker compose --project-directory codes exec laravel.test php artisan migrate:fresh --force
```

## What's next — Step 3

Seed data that mirrors the prototype. Every USER, CLINIC, PROVIDER, CLINIC_ADMIN, APPOINTMENT, SERVICE, CPT_LOOKUP, REPORT_TYPES, PATIENT, CASE, FEE_STATE, and EMR_SYSTEM from rocket-coding.jsx becomes a row in the DB. I'll also seed:

- One user per role (to resolve ambiguity #3 — provider/staff/deployer need example logins)
- ~100 CPT codes, ~80 ICD-10 codes covering the codes used in the prototype plus common WC diagnoses
- All 5 state fee schedules + localities (already mapped in JSX)
- All 8 EMR systems (already mapped in JSX)

Password for every seeded user: `demo123` (bcrypt-hashed, 12 rounds per `BCRYPT_ROUNDS`).

Seeder will be idempotent — running it twice won't create duplicates. After seeding, the DB will render exactly what the prototype renders from its in-memory arrays.

---
Powered by SnapEcosystem · www.SnapSolutions.com
