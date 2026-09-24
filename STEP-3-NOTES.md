# Step 3 — Seed data

Status: **complete**. Generated 2026-04-21.

## What got built

Three files:

| File | Purpose | Size |
|---|---|---|
| [ReferenceDataSeeder.php](database/seeders/ReferenceDataSeeder.php) | CPT / ICD-10 / HCPCS codes + fee schedules + localities + EMR catalog | ~260 lines |
| [PrototypeSeeder.php](database/seeders/PrototypeSeeder.php) | JSX seed arrays row-for-row: clinics, users, providers, clinic admins, services, patients, appointments, EMR connections, cases with full notes + AI outputs | ~390 lines |
| [DatabaseSeeder.php](database/seeders/DatabaseSeeder.php) | Calls both in order — ref data first (no FKs), prototype second | ~25 lines |

Run: `docker compose --project-directory codes exec laravel.test php artisan db:seed`

## Seeded counts (verified — identical after re-run, proving idempotency)

### Reference data

| Table | Count | Notes |
|---|---|---|
| `fee_schedules` | 5 | CA (OMFS), TX (TDI), NY (WCB), FL (DWC), IL (WCC) — matches JSX FEE_STATES verbatim |
| `localities` | 26 | All locality adjusters from JSX preserved |
| `emr_systems` | 9 | All from JSX EMR_SYSTEMS (line 93) — Epic, athenahealth, eClinicalWorks, NextGen, DrChrono, AdvancedMD, Kareo, Custom API, SFTP Transfer |
| `cpt_codes` | 105 | All 45 from JSX CPT_LOOKUP + 60 additional launcher-required codes (99202-99215 E/M, 99455/99456 WC, 97110/97140 PT, 20552/20553 injections, 73721-73723 lower-ext MRI, 72141-72148 spine MRI, 20610 joint, 95905-95913 nerve conduction, plus observation/hospital/ED/arthroscopy) |
| `icd10_codes` | 78 | Covers the 6 codes used in JSX CASES (M54.41, M51.16, S83.511D, M17.11, S13.4XXA, M50.120) + broad WC-focused set (M54.xx radiculopathy/sciatica/lumbago, M50-M51 disc disorders, M16/M17 OA, M75 rotator cuff, S13/S33/S83/S93 sprains, fractures, strains, neuropathies, tendonitis, concussion, F43.23 adjustment disorder) |
| `hcpcs_codes` | 11 | Small starter set: crutches, bipap, LSO/KO braces, injectable steroids, hyaluronan |

### Prototype data (matches JSX line-for-line)

| Table | Count | Notes |
|---|---|---|
| `clinics` | 3 | Valley Medical Group (selfCoded=true), Coastal Orthopedics, Summit Pain Mgmt — all fields preserved incl. `taxId`, `npi`, `timezone`, `reportFavs`, `notif` |
| `users` | 15 | 10 from JSX USERS + **5 added** to cover the provider/staff/deployer roles (STEP-2 ambiguity #3). All distinct roles in DB: admin, clinic, deployer, provider, staff — verified |
| `providers` | 6 | All from JSX PROVIDERS; each linked to its matching User via `userId` where one exists |
| `clinic_admins` | 4 | All from JSX CLINIC_ADMINS |
| `services` | 13 | All from JSX SERVICES, PKs preserved verbatim (`s1`, `s5`, `sT`, etc.) so the `cases.svcs` JSON array references them without remapping |
| `patients` | 3 | All from JSX PATIENTS, plus inferred `clinicId` + `providerId` from provider name match (resolves STEP-2 ambiguity #1) |
| `appointments` | 8 | All from JSX APPOINTMENTS with FK resolution to providers/patients where the JSX had display strings |
| `emr_connections` | 3 | Example integrations: Valley → Epic (connected), Valley → athenahealth (connected), Coastal → eClinicalWorks (pending) |
| `cases` | 3 | **Full fidelity**: notes (400+ chars each), `suggestedCPT`, `suggestedDX`, `modifiers`, `stateCompliance`, `emails`, `audit`, `timeline` — every JSX field preserved verbatim as JSON |

## Case-by-case fidelity check (verified in-container)

| JSX field | Case SNP-0001 | Case SNP-0002 | Case SNP-0003 |
|---|---|---|---|
| Status (JSX display → enum value) | Delivered → `delivered` ✓ | Completed → `completed` ✓ | Pending Diagnosis Approval → `pendingDiagnosisApproval` ✓ |
| `suggestedCPT` | 1 entry: 99215 ✓ | 2 entries: 99214, 99455 ✓ | 1 entry: 99456 ✓ |
| `suggestedDX` | 2: M54.41, M51.16 ✓ | 2: S83.511D, M17.11 ✓ | 2: S13.4XXA, M50.120 ✓ |
| `modifiers` | 1 (`-25` on 99214) ✓ | 0 ✓ | 0 ✓ |
| `stateCompliance.system` | CA OMFS ✓ | CA OMFS ✓ | TX TDI ✓ |
| `notes` (byte length) | 449 ✓ | matches JSX | matches JSX |
| `audit` entries | 3 ✓ | 2 ✓ | 2 ✓ |
| `timeline` entries | 2 ✓ | 2 ✓ | 1 ✓ |

## Password hash check

Every seeded user's password is `demo123`, bcrypt-hashed with `BCRYPT_ROUNDS=12`. Verified:

```
Hash::check("demo123", User::where("email", "clinic@valleymed.com")->value("password"))
  → true
```

You can log in as any of the 15 seeded users with password `demo123`:

| Role | Example email |
|---|---|
| clinic | `clinic@valleymed.com` (Dr. Sarah Chen) |
| admin | `admin@rocketcoding.com`, `gene@snapsolutions.com` |
| provider | `pnair@valleymed.com` (Dr. Priya Nair), `evasquez@coastalortho.com` |
| staff | `mgonzalez@valleymed.com`, `dkowalski@coastalortho.com` |
| deployer | `deployer@rocketcoding.com` |

## Tenant scope verified

With `TenantContext::setClinicId($valleyClinic)`:
- `Patient::count()` → **2** (Robert Martinez, Angela Thompson)

With `TenantContext::setClinicId($coastalClinic)`:
- `Patient::count()` → **1** (David Kim)

With no tenant set (seeder / tinker / admin context):
- `Patient::count()` → **3** (all)

The global scope fires on every query; no controller or service has to remember to filter. This is the architectural enforcement of the `tenant isolation` security invariant — confirmed working end-to-end.

## Idempotency — ran seed twice, counts identical

Every `updateOrCreate` uses a natural unique key:

| Table | Unique key for upsert |
|---|---|
| clinics | `email` |
| users | `email` |
| providers | `npi` |
| clinic_admins | `(clinic, email)` |
| patients | `(clinicId, emrId)` |
| appointments | `(clinicId, date, time, patient)` |
| cases | `num` |
| services | `id` (natural PK: `s1`, `s5`, etc.) |
| fee_schedules | `code` |
| localities | `(feeScheduleCode, code)` |
| emr_systems | `name` |
| emr_connections | `(clinicId, emrSystemId)` |
| cpt_codes / icd10_codes / hcpcs_codes | `(code, effectiveFrom)` |

A second `artisan db:seed` run refreshes attributes but does not insert new rows. Verified.

## Design choices logged

### Decision 1: CPT/ICD-10 effectiveFrom = 2024-01-01 for all seed rows

Stable, recent, single value for the whole initial release. Step 5's Deployer upload pipeline will add new releases as fresh rows with later `effectiveFrom` dates — never destructive updates. This lets historical claim coding remain reproducible even after code sets change.

### Decision 2: Patient clinic FK inferred from JSX provider string

JSX PATIENTS has `provider: "Dr. Sarah Chen"` as a display string and no `clinic` field. In the seeder I look up the Provider row by NPI (since that was already linked to a clinic) and derive `clinicId` from there. This mirrors what a real backend would do — patients belong to clinics, and attribution flows through the treating provider.

### Decision 3: 5 extra users added to cover all 5 roles

JSX USERS has only `role: "clinic"` and `role: "admin"` — no `provider` / `staff` / `deployer` examples. I added:
- `pnair@valleymed.com` (provider — Dr. Priya Nair, matches PROVIDERS[2])
- `evasquez@coastalortho.com` (provider — Dr. Elena Vasquez, matches PROVIDERS[4])
- `mgonzalez@valleymed.com`, `dkowalski@coastalortho.com` (staff/scheduler)
- `deployer@rocketcoding.com` (deployer)

Each is linkable from the login screen so Step 4's auth tests have real fixtures for every role.

### Decision 4: Case status stored as enum backend value, not JSX display string

JSX uses display strings like `"Pending Diagnosis Approval"` on cases. DB stores the enum backend value `"pendingDiagnosisApproval"`. The API Resource in Step 5 can surface either the display label (via `CaseStatus::from($value)->label()`) or the raw backend key, depending on what the frontend expects per endpoint.

### Decision 5: EMR connections are clinic-specific, not system-global

JSX EMR_SYSTEMS has a system-wide `status` field (e.g., Epic: "Connected"). In reality each clinic has its own connection state. The seeder stages 3 example connections (Valley→Epic, Valley→athenahealth, Coastal→eClinicalWorks) matching the JSX statuses. The `emr_systems` table remains the global catalog; `emr_connections` is the per-tenant integration record.

### Decision 6: `Patient::withoutGlobalScopes()` in the seeder

Seeders bypass the tenant scope because TenantContext has no tenant set during CLI seeding. When we insert across multiple clinics, the `BelongsToTenant::creating` hook still populates `clinicId` from explicit seeder attributes (since TenantContext is empty, the auto-populate no-ops) — so we pass `clinicId` explicitly on every insert. Used `withoutGlobalScopes()` on `updateOrCreate` lookups too, to ensure "find existing row" across all tenants during seed.

## What's next — Step 4

Authentication and authorization:

- JWT-style access token (15 min) + refresh token (7 days, rotated on use)
- Login / refresh / logout / forgot-password / reset-password endpoints
- Per-role middleware: `requireAuth`, `requireRole`, `requireClinic`, `requireTenantMatch`, `requirePermission`
- Login hardening: per-IP AND per-email rate limit, 3-fail lockout for 15 min
- Audit logging every login success, login failure, password change, logout
- Pest tests incl. **the most important test in the system**: a clinic user cannot access another clinic's data (→ 403)

The schema (Step 2) already has the right columns (`users.loginAttempts`, `lockedUntil`, the `refresh_tokens` table). Step 4 fills in the service/controller/middleware code and wires everything through the repository pattern — no ad-hoc Model calls.

---
Powered by SnapEcosystem · www.SnapSolutions.com
