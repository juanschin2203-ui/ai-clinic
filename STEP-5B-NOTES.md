# Step 5b — Files, audit queries, per-email rate limit

Status: **complete**. Generated 2026-04-21. 97/97 Pest tests passing (17 new in this step).

## What shipped

| Area | Count | Location |
|---|---|---|
| File upload/download | 7 files | `FileRepository`, `FileService`, `FilePolicy`, `FileResource`, `StoreFileRequest`, `FilesController`, routes |
| Deployer audit queries | 1 controller | `AuditController` — `/audit/hipaa`, `/audit/soc2`, `/audit/activity` |
| Per-email rate limiter | 1 service provider | `RateLimitServiceProvider` — named limiters: `login`, `refresh`, `forgot-password` |
| Tests | 3 files, 17 tests | `FilesTest`, `AuditQueryTest`, `LoginRateLimiterTest` |

## Endpoints added

| Method | Path | Gate | Notes |
|---|---|---|---|
| GET    | `/api/files` | `viewAny File` | Optional `?ownerType=&ownerId=` for attachment-style lookup |
| GET    | `/api/files/{file}` | `view File` | Metadata only; fires PhiAccessed if `containsPhi=true` |
| POST   | `/api/files` | `create File` | Multipart upload; returns metadata |
| GET    | `/api/files/{file}/download` | `view File` | Streams bytes; fires PhiAccessed on PHI files |
| DELETE | `/api/files/{file}` | `delete File` | Removes storage + metadata |
| GET    | `/api/audit/hipaa` | role admin/deployer | `isPhiAccess=true` audit rows only — the HIPAA §164.312(b) trail |
| GET    | `/api/audit/soc2` | role admin/deployer | All `audit_logs` rows — covers SOC 2 CC-series |
| GET    | `/api/audit/activity` | role admin/deployer | `activity_logs` — ops events (AI pipeline, billing, EMR) |

All audit endpoints support `?from=YYYY-MM-DD&to=YYYY-MM-DD&clinicId=&userId=&action=&entityType=&perPage=`.

## Design decisions

### Decision 1: File payloads never touch the DB

`files` table holds metadata only — `s3Key`, `filename`, `mimeType`, `sizeBytes`, `checksum`, `containsPhi`. The actual bytes live on the configured Laravel filesystem (dev: `local` disk under `storage/app/private/files/...`; prod: S3 with KMS encryption per `.env`).

Storage layout: `files/{clinicId}/{YYYY}/{MM}/{uuid}.{ext}` — tenant-partitioned so clinic bulk deletes are a directory scan, not a per-row query.

### Decision 2: PHI gating is driven by `kind`, not UI

`StoreFileRequest` accepts one of `chart`, `dictation`, `batch`, `invoicePdf`, `emrExport`. The service maps `['chart', 'dictation', 'emrExport']` → `containsPhi = true`. Downloads fire `PhiAccessed` only when `containsPhi` is true — so downloading an invoice PDF doesn't pollute the HIPAA report.

### Decision 3: `s3Bucket` and `s3Key` are NOT in the API response

`FileResource` deliberately omits both. They're internal storage concerns. The frontend calls the `downloadUrl` route (which streams via controller with auth + audit) rather than accessing S3 directly. When we add S3 pre-signed URLs in a future step, the Resource grows a short-lived URL field instead — same interface shape from the frontend's perspective.

### Decision 4: Per-email rate limit stacks with per-IP, doesn't replace it

`RateLimitServiceProvider` defines `login` as a named limiter returning a **list of two Limit objects**: one keyed by `login_ip:{ip}`, one keyed by `login_email:{email}`. Laravel's `throttle:login` middleware enforces ALL limits — either limit tripping returns 429. This defeats the corporate NAT case (many users share one IP, which could exhaust the per-IP budget legitimately) AND the distributed-attacker case (many IPs pounding one account).

Email is lowercased + trimmed before bucketing so `TEST@Example.COM` and `test@example.com` share a bucket. Verified in the tests.

### Decision 5: Audit endpoints use `abort(403)` defensively

The route group already has `role:admin,deployer` middleware. The controller re-asserts via `assertDeployerOrAdmin()`. Redundant by design — if the route middleware ever gets misconfigured, the controller fails closed.

### Decision 6: Rate-limit tests go at the definition level, not HTTP level

Base `TestCase::setUp()` globally `withoutMiddleware(ThrottleRequests::class)` to stop rate-limit state from leaking across tests. That means HTTP-level rate-limit tests wouldn't exercise the limiter. Instead, `LoginRateLimiterTest` reaches into `RateLimiter::limiter('login')`, invokes the closure with a fake Request, and asserts the returned `Limit` objects have the expected keys — testing the DEFINITION, not the enforcement. The enforcement is Laravel's own code, covered by Laravel's test suite.

## What's deferred to a later step

- **Reference-data upload pipeline** (Deployer: POST /reference-data/upload → stage → preview → commit/discard). Multi-stage flow with staging table + CSV parsing. Own focused step.
- **OpenAPI spec generation.** Probably via `dedoc/scramble` auto-scanning FormRequests. Lands when we need the spec (e.g., for a public-facing docs site or SDK generation).
- **S3 pre-signed URLs for direct browser upload.** Current POST /files proxies the bytes through the app server. Fine for the current scale; swap to pre-signed when upload sizes grow.
- **File versioning.** Currently a chart upload replaces the prior one if re-uploaded. If a case's chart history matters for audit, we add a `parentFileId` FK later.

## How to run

```bash
# Full suite (97 tests)
docker compose --project-directory codes exec laravel.test php artisan test --testsuite=Feature --compact

# Just Step 5b
docker compose --project-directory codes exec laravel.test \
  php artisan test --filter "FilesTest|AuditQueryTest|LoginRateLimiterTest"

# Smoke-test file upload end-to-end
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"clinic@valleymed.com","password":"demo123"}' | jq -r .data.accessToken)

curl -X POST http://localhost:8080/api/files \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F file=@/path/to/chart.pdf \
  -F kind=chart \
  -F ownerType=medical_case \
  -F ownerId=<caseUuid>
```

---
Powered by SnapEcosystem · www.SnapSolutions.com
