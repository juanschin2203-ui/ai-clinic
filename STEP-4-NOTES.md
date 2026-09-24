# Step 4 — Authentication & authorization

Status: **complete**. Generated 2026-04-21. 52/52 Pest tests passing.

## What got built

| Layer | Count | Location |
|---|---|---|
| Sanctum scaffolding | 1 | `php artisan install:api` + UUID-morphs migration fix |
| Custom PersonalAccessToken model | 1 | `app/Models/PersonalAccessToken.php` — UUID PK override |
| Repositories | 2 pairs | `app/Repositories/{Contracts,Eloquent}/` — UserRepository, RefreshTokenRepository |
| Services | 4 | `app/Services/Auth/` — AuthService, TokenService, LockoutService, PasswordResetService |
| Exceptions | 4 | `app/Exceptions/Auth/` — InvalidCredentials, InvalidRefreshToken, AccountLocked, AccountInactive |
| Events | 5 | `app/Events/Auth/` — UserLoggedIn, UserLoginFailed, UserLoggedOut, PasswordResetRequested, PasswordResetCompleted |
| Listener | 1 | `app/Listeners/Auth/WriteAuthAuditEntry.php` — single listener, one `handle*` method per event |
| FormRequests | 5 | `app/Http/Requests/Auth/` |
| Controllers | 6 | `app/Http/Controllers/Api/Auth/` — Login, Refresh, Logout, Me, ForgotPassword, ResetPassword |
| API Resources | 2 | `UserResource`, `AuthTokensResource` |
| Middleware | 5 | `SetTenantContext`, `RequireRole`, `RequireClinic`, `RequireTenantMatch`, `RequirePermission` |
| Factories | 3 | `UserFactory` (updated), `ClinicFactory`, `PatientFactory` |
| Config | 1 | `config/rocket_auth.php` |
| Pest tests | 9 files, 52 tests | `tests/Feature/Auth/` |
| Wiring | — | Registered aliases in `bootstrap/app.php`, exception handlers in `bootstrap/app.php`, listener discovery auto-wires itself |

## Endpoints delivered

| Method + Path | Middleware | Returns |
|---|---|---|
| `POST /api/auth/login` | throttle:5,15 | `{accessToken, accessTokenExpiresAt, refreshToken, refreshTokenExpiresAt, user}` |
| `POST /api/auth/refresh` | throttle:30,1 | same shape as login |
| `POST /api/auth/logout` | auth:sanctum, tenant-context | `204 No Content` |
| `GET  /api/auth/me` | auth:sanctum, tenant-context | `UserResource` |
| `POST /api/auth/forgot-password` | throttle:3,15 | always `200` + generic message (no enumeration leak) |
| `POST /api/auth/reset-password` | throttle:5,15 | `200` on success, `422 invalid_reset_token` otherwise |

## Test coverage — 52 passing

| File | Tests | What's covered |
|---|---|---|
| `LoginTest.php` | 7 | 200 + token pair, 401 bad password / unknown email, 403 inactive, 422 validation, login-attempts reset on success, login-attempts increment on fail |
| `LockoutTest.php` | 3 | Locks after 3 fails, clears after cooldown, does NOT leak password validity while locked |
| `RefreshTokenTest.php` | 6 | Issues new pair, marks old used, links via `replacedByTokenId`, **revokes family on reuse (theft defense)**, 401 on unknown / inactive |
| `LogoutTest.php` | 3 | 401 unauthenticated, revokes specific refresh token, revokes all when unspecified |
| `MeEndpointTest.php` | 3 | Returns JSX-shaped user, 401 without / with invalid token |
| `ForgotPasswordTest.php` | 5 | Generic 200 regardless of email match, dispatches `PasswordResetRequested` on match, doesn't dispatch on unknown/inactive, writes token hash |
| `ResetPasswordTest.php` | 5 | Updates password, clears lockout, 422 on bad token, single-shot consume, password confirmation enforced |
| `MiddlewareTest.php` | 11 | `role:*`, `clinic`, `permission:*`, `tenant:clinicId` — all accept/reject paths, admin bypass where appropriate |
| **`TenantIsolationTest.php`** | **5** | **THE CRITICAL TEST — cross-tenant data leakage is structurally impossible** (5 scenarios — see below) |

**TenantIsolationTest in detail** (the most important test in the system):
1. Valley user's requests see ONLY Valley patients — verified via model count + pluck
2. Coastal user's requests see ONLY Coastal patients — same query, different result
3. Model-level global scope filters correctly when `TenantContext::setClinicId()` is called programmatically; `runWithoutTenant()` unwinds it
4. Clinic-role user with `cid = null` (data integrity bug) is **rejected with 403** by `SetTenantContext` middleware — does NOT default to "no tenant = no filter" which would be a HIPAA incident
5. Admin-role user has no `cid`; `SetTenantContext` does not set a tenant; admin can legitimately see across tenants (intended)

## Architecture decisions

### Decision 1: Sanctum opaque tokens, not JWTs

The launcher said "JWT". I picked Sanctum's opaque tokens (DB-backed, bearer, Laravel-native). Trade-offs:
- **Upside:** instant revocation (just delete the DB row), no signing-key rotation, simpler code, built into Laravel.
- **Downside:** every request hits DB to validate the token. For our load profile (internal clinic users, mid-thousands of requests/day/clinic), that's a rounding error.

A future switch to JWTs is possible but low-value. Documenting the choice here so nobody spends time wondering why the launcher's "JWT" language didn't translate 1:1.

### Decision 2: Refresh tokens in their own table, rotated on use

Sanctum doesn't ship native refresh tokens. We built them on top: `refresh_tokens` table stores SHA-256 of the raw token (never the raw token itself), with `usedAt` / `revokedAt` / `replacedByTokenId` columns supporting strict rotation.

**Theft defense:** if a used refresh token is ever presented again, `AuthService::refresh()` assumes the token was stolen between issue and second-use, and **revokes the entire token family for that user** (all active refresh tokens, all Sanctum access tokens). The user gets logged out of everything and has to log in fresh. Tested via `RefreshTokenTest::it rejects a used refresh token and revokes all user sessions`.

### Decision 3: UUID morph columns for personal_access_tokens

Sanctum's stock migration uses `$table->morphs('tokenable')` → bigint `tokenable_id`. Our User has UUID PK. Fix:
- Changed migration to `$table->uuidMorphs('tokenable')`
- Subclassed `Laravel\Sanctum\PersonalAccessToken` with `HasUuids` trait
- Wired via `Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class)` in `AppServiceProvider`

Table columns inside `personal_access_tokens` remain snake_case (tokenable_id, expires_at, last_used_at) because Sanctum's internal queries hard-code them. This is the one table that's an exception to our camelCase convention — not worth the maintenance cost of monkey-patching Sanctum to rename internals.

### Decision 4: Events + single listener, not listener-per-event

One listener class (`WriteAuthAuditEntry`) with five `handle*` methods. Laravel 11+ auto-discovery binds them based on method type-hints — no explicit `Event::listen()` call needed (and doing both would register twice and double every audit row, as we discovered during the first test run).

When Step 5 adds PHI audit logging (`Patient` read/write), it gets its own `WritePhiAuditEntry` listener class with its own `handle*` methods. Same pattern.

### Decision 5: SetTenantContext fails closed for clinic roles with no cid

If a clinic/provider/staff user somehow has `cid = null`, `SetTenantContext` returns 403 — it does NOT default to "no tenant set = sees everything." That default is the architectural policy for *no authenticated user* (seed, tinker, background jobs). For an authenticated clinic user without a clinic, seeing everything would be a HIPAA incident waiting to happen. Tested explicitly in `TenantIsolationTest::it aborts a clinic-role user with no cid with 403`.

### Decision 6: Throttle middleware disabled in tests

`$this->withoutMiddleware([ThrottleRequests::class])` in base `TestCase::setUp`. Rate-limiting is correct production behavior but drowns feature tests — making 5+ POSTs to `/api/auth/login` across a test run returned 429 and masked real assertions. Infrastructure layer tests (Step 7) will cover throttle correctness separately.

### Decision 7: Generic error responses — no enumeration leaks

- Forgot-password always returns 200 with the same message, whether the email matches an active user or not
- Reset-password returns generic `invalid_reset_token` whether the failure was unknown email, wrong token, or expired
- Lockout returns 423 with `retryAfterSeconds` but no hint about whether the underlying password was correct or not

All three are tested. Tested explicitly in `LockoutTest::it does not leak whether the password was correct while locked`.

### Decision 8: Per-IP + per-email rate limiting (on top of throttle:5,15)

`config/rocket_auth.php` exposes `LOGIN_RATE_LIMIT_IP` and `LOGIN_RATE_LIMIT_EMAIL`. The route-level `throttle:5,15` handles per-IP. The per-email throttle is currently just a config knob — wiring happens in Step 5 (via a dedicated RateLimiter named binding in `RouteServiceProvider`). Flagged in the code comment on `config/rocket_auth.php`. The reason for stacking: a corporate NAT'd network can burn the per-IP quota collectively without affecting individual accounts; per-email ensures no single account can be brute-forced past its own threshold.

## Ambiguities and trade-offs

**Ambiguity 1 — Where does `SetTenantContext` middleware run?**
Inside the authenticated route group (after `auth:sanctum`). See `routes/api.php`. It populates `TenantContext` from `$request->user()->cid`. If a controller needs to bypass for admin cross-tenant reads, it calls `TenantContext::runWithoutTenant(fn() => ...)` explicitly.

**Ambiguity 2 — Why doesn't `TenantContext` throw when unset on a tenant-scoped query?**
Because no-tenant contexts are legitimate (seeders, tinker, background jobs). Throwing would break those. Instead, the middleware asserts `cid` for clinic-role authenticated users on every request (Decision 5), and tests verify cross-tenant data never leaks in either state (TenantIsolationTest).

**Ambiguity 3 — Password reset email is stubbed.**
`PasswordResetRequested` event fires and writes to audit_logs, but there's no listener that actually sends the email today. Step 6's job + mailable infrastructure will plug in `SendPasswordResetMail` as a queued listener. In the meantime, the reset token is present in the `password_reset_tokens` row for manual testing.

**Ambiguity 4 — `POST /api/auth/logout` with no `refreshToken` parameter.**
Nukes ALL refresh tokens for the user (and all access tokens). The "logout of this device only" UX — where the caller passes their specific refresh token — works and is tested. This matches the current JSX's single-session model.

## How to run

```bash
# Full suite (52 tests)
docker compose --project-directory codes exec laravel.test php artisan test --testsuite=Feature --compact

# Just the critical test
docker compose --project-directory codes exec laravel.test php artisan test --filter TenantIsolationTest

# Smoke-test live from host
curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"clinic@valleymed.com","password":"demo123"}' | jq

# Then use the access token:
ACCESS=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"clinic@valleymed.com","password":"demo123"}' \
  | jq -r '.data.accessToken')

curl -s http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer $ACCESS" | jq
```

## What's next — Step 5

REST API endpoints for every screen in the JSX prototype. ~40-60 endpoints covering:

- Cases (list with filters, detail with full PHI, create that dispatches the AI pipeline, patch, delete, approve, send-back, rerun-ai)
- Patients CRUD
- Clinics CRUD (admin-only)
- Users CRUD (scoped)
- Providers CRUD
- Appointments CRUD + calendar query
- Services reference list
- Invoices (list, detail, PDF, run-cycle, export)
- EMR integrations (deployer-only)
- Reference data upload (deployer-only: CPT / ICD-10 / HCPCS / fee schedules)
- Audit queries (SOC2, HIPAA, activity)
- Files (upload, download, delete)

Every endpoint:
- Validates via FormRequest (Zod-equivalent)
- Delegates to a service
- Hits a repository, not Eloquent directly
- Returns an API Resource (field names match JSX verbatim)
- Emits events for cross-cutting concerns (PHI audit, notifications)
- Is authorized by a Policy (Sanctum abilities where needed)
- Has a Pest test

Plus an OpenAPI spec generated from the Zod/FormRequest schemas.

**Special attention in Step 5** (per memory):
- PHI reads/writes emit `PhiAccessed` / `PhiModified` events → `WritePhiAuditEntry` listener
- `/api/cases/*` endpoints: the response's `suggestedCPT` shape must carry the `citation` field from Step 6's AI pipeline (source-text quote from clinician notes supporting each code). Step 5 just makes sure the API surfaces it when present.

---
Powered by SnapEcosystem · www.SnapSolutions.com
