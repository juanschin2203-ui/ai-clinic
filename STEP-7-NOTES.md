# Step 7 — Production deployment package

Status: **complete**. Generated 2026-04-21. 131/131 Pest tests passing (10 new in this step).

This is the last step of the launcher. Rocket Coding's backend is now
end-to-end: schema, seeds, auth, REST API, AI pipeline, queue infra, billing
cron, and the production deploy assets. What's left between here and a live
production instance is **operational** — the items in `DEPLOY-CHECKLIST.md` —
not engineering.

## What shipped — 14 new files

| Area | Count | Location |
|---|---|---|
| Health endpoint | 1 | `HealthController` — probes DB / Redis / queue / Anthropic config |
| Ops endpoints | 1 | `OpsController` — queue depth, token usage, errors, force-reprocess |
| Routes | — | `routes/api.php` — `/api/health` public, `/api/ops/*` role-gated |
| Production Dockerfile | 1 | Multi-stage (composer → node → runtime), non-root, single image for all 3 roles |
| Production compose override | 1 | `docker-compose.prod.yml` — adds `worker` + `cron` roles, locks down ports |
| Dockerignore | 1 | `.dockerignore` — keeps `.env`, tests, docs out of the image |
| PHP prod config | 3 | `docker/prod/php.ini`, `nginx.conf`, `supervisord.conf` |
| Container entrypoint | 1 | `docker/prod/entrypoint.sh` — role dispatcher (`web`/`worker`/`cron`/`migrate`) |
| Structured JSON logging | — | `config/logging.php` — Monolog JsonFormatter with WebProcessor / MemoryUsageProcessor |
| CI workflow | 1 | `.github/workflows/ci.yml` — static analysis, tests against MySQL + Redis, Docker build |
| Migration script | 1 | `scripts/migrate-prod.sh` — S3 pre-migration backup + artisan migrate + cache rebuild |
| Docs | 2 | `README.md` (quickstart, run-book, troubleshooting), `DEPLOY-CHECKLIST.md` (legal, infra, security, DR) |
| Tests | 2 files, 10 tests | `HealthTest`, `OpsEndpointsTest` |

## Endpoints

| Method + Path | Auth | Purpose |
|---|---|---|
| `GET /api/health` | public, throttled | LB probe. Returns `{status: "ok"\|"degraded", dependencies: {db, redis, queue, anthropic}}` with 200 or 503 |
| `GET /api/ops/queue/depth` | role admin/deployer | Per-queue `{pending, reserved, delayed, total}` depths |
| `GET /api/ops/ai/token-usage` | role admin/deployer | Anthropic tokens per clinic over a date range, from activity_logs |
| `GET /api/ops/errors` | role admin/deployer | Recent severity=error activity entries for triage |
| `POST /api/ops/cases/{case}/reprocess` | role admin/deployer | Reset AI fields + dispatch fresh RunAiPipeline |

## Container role model — one image, three runtime modes

The production `Dockerfile` produces a single image. At runtime, the
entrypoint dispatches to one of four roles based on the `CMD`:

```
docker run rocket-coding:latest web       # nginx + php-fpm (supervisord)
docker run rocket-coding:latest worker    # horizon (queue workers)
docker run rocket-coding:latest cron      # schedule:work (billing + cleanup)
docker run rocket-coding:latest migrate   # one-shot migration, exits 0/non-zero
```

In ECS / Kubernetes, that's three Deployments (web/worker/cron) sharing the
same image, plus an init-style Job/Task for `migrate` during deploys. Easy
to rotate — single image version, same artifact scanned once for CVEs.

## Architecture decisions

### Decision 1: Single-image, role-dispatched, not multi-image

Alternative would be separate images for the API, worker, and cron.
Declined because:
- One build → one set of CVE scans, one deploy artifact to sign
- Identical PHP extensions + composer packages across roles (Horizon needs
  the same Eloquent setup the API uses)
- Simpler rollback — everything moves forward/back together

Trade-off: the runtime image is slightly larger than a pure-worker image.
Not material at current scale (~350 MB compressed).

### Decision 2: Health probe is 4 checks, 3 real + 1 configured

- **DB:** real query (`SELECT 1`)
- **Redis:** real `PING`
- **Queue:** config presence check (no real probe)
- **Anthropic:** env-var presence check (no real API call)

Rationale: a health endpoint that hits Anthropic would cost tokens per
minute (LBs probe aggressively), and the failure mode we actually care
about — "did someone forget to set the API key?" — is caught by the
config presence check. A deeper live Anthropic probe can live at
`/api/ops/ai/reachability` later if we find we need it.

### Decision 3: Structured JSON logs via Monolog, not a new library

Laravel's Monolog already emits JSON when configured with `JsonFormatter`.
Adding `WebProcessor` / `ProcessIdProcessor` / `MemoryUsageProcessor`
enriches every log line with the fields DataDog / CloudWatch / Fluent Bit
want out of the box. No extra dependency added — "Pino-equivalent" lands
in `config/logging.php` by switching the stderr channel's formatter.

### Decision 4: Production migration script does a per-deploy DB backup

Even though RDS has automated backups, `scripts/migrate-prod.sh` writes a
dedicated `pre-migration/` snapshot to S3 before running `artisan migrate`.
Two reasons:
1. Labeled, per-deploy artifact — instantly identifiable in a post-mortem
2. MySQL does NOT support transactional DDL. A mid-migration failure on a
   multi-step ALTER can leave the schema half-applied. The backup is the
   rollback vehicle.

The script also writes a `deploy.migration.completed` row to
`activity_logs` — the Deployer audit dashboard's deploy history.

### Decision 5: `/horizon` is gated to admin + deployer only

`HorizonServiceProvider::gate()` (updated in Step 6b) checks
`$user->role ∈ {Admin, Deployer}` and returns false otherwise. A clinic
user browsing to `/horizon` directly gets 403. Matches the audit endpoints.

### Decision 6: CI runs tests against MySQL + Redis (service containers) too

Most tests run against in-memory SQLite for speed (phpunit.xml). BUT the
CI pipeline also spins up MySQL 8.4 and Redis service containers and
runs `artisan migrate --pretend` against MySQL. This catches migration-
portability bugs (MySQL-only syntax in a migration, missing index name,
etc.) without the full cost of running every Pest test against MySQL.

### Decision 7: `CONTAINER_ROLE` defaults to `web`

If someone runs `docker run rocket-coding:latest` with no CMD, they get
the web container. Matches typical developer intuition and keeps
accidental deploys safe (`web` is the lowest-side-effect role).

## Test coverage — 10 new tests

| Test | What's covered |
|---|---|
| health: 200 + ok when all deps reachable | the happy path |
| health: 503 + degraded when Anthropic not configured | the env-check failure mode |
| health: no auth required | load balancer compatibility |
| ops: queue/depth returns per-queue structure for admin | happy path |
| ops: queue/depth is 403 for clinic users | authorization gate |
| ops: ai/token-usage aggregates per-clinic from activity_logs | the Deployer billing-over-token report |
| ops: cases/reprocess dispatches a fresh RunAiPipeline + resets case | the Deployer's "Force AI re-process" button |
| ops: cases/reprocess returns 404 for unknown case | error shape |
| ops: errors lists severity=error activity entries | triage dashboard |
| ops: all endpoints 401 without token | no anon access |

## Deployment workflow summary

```
1. Merge PR to main
2. CI runs: static-analysis → tests → docker-build
3. On green build, CI publishes rocket-coding:<git-sha> image to ECR
4. Deploy pipeline:
   a. Put app into maintenance mode:   artisan down
   b. Run one-shot migrate task:       image:<sha> CMD=migrate
      (internally runs scripts/migrate-prod.sh — S3 backup first)
   c. Update web Deployment:           image:<sha> CMD=web (rolling)
   d. Update worker Deployment:        image:<sha> CMD=worker (rolling)
   e. Update cron Deployment:          image:<sha> CMD=cron (recreate)
   f. Bring app out of maintenance:    artisan up
5. Health check loop until all pods return 200 on /api/health
6. Announce release in #ops Slack
```

Items #a and #f live in the CD pipeline script, not in code. The
`migrate` role in `entrypoint.sh` handles #b.

## What's NOT in this step (deferred or out of scope)

- **Real Stripe / Plaid ACH integration** (stub in `InitiateAchDebit`) — blocked on provider accounts + BAAs.
- **DomPDF invoice rendering** — invoice email currently renders markdown inline with all the same numbers. Real PDF attachment drops in when requested.
- **OpenAPI spec** — probably `dedoc/scramble` package; haven't wired it because nobody's asked for the SDK yet.
- **Sentry / Datadog SDK wiring** — SDK install + DSN config is a 10-line follow-up when the monitoring vendor is picked.
- **WAF rules** — managed by the infra team at the AWS/CloudFlare layer; see DEPLOY-CHECKLIST §2.
- **Pen-test remediation** — will happen, in sequence, after a contracted pen-tester reports findings.

## How to run

```bash
# Build the production image locally
docker build -t rocket-coding:test .

# Run it in web mode (mimics production)
docker run -p 8080:8080 --env-file .env rocket-coding:test web

# Run the full test suite including Step 7
docker compose exec laravel.test php artisan test --testsuite=Feature --compact

# Just the Step 7 tests
docker compose exec laravel.test php artisan test --filter "HealthTest|OpsEndpointsTest"

# Preview the migration script (dry run — doesn't actually back up or migrate)
APP_ENV=production bash -x ./scripts/migrate-prod.sh --help || true

# Hit the health endpoint
curl -s http://localhost:8080/api/health | jq
```

## Final tally across all seven steps

| Step | What | Tests |
|---|---|---|
| 1 | Stack + Docker scaffold | — |
| 2 | 23-table schema + tenant isolation + PHI markers | — |
| 3 | Prototype + reference data seeding | — |
| 4 | Sanctum auth + refresh rotation + 5 middleware | 52 |
| 5 | 7 core REST resources + PHI audit | 80 (cumulative) |
| 5b | Files + audit queries + per-email rate limit | 97 (cumulative) |
| 6 | AI pipeline (6 stages, citation-grounded, queued) | 103 (cumulative) |
| 6b | Billing cycle + invoice mail + cleanup + Horizon | 121 (cumulative) |
| 7 | Production Docker + CI + health + ops + docs | **131** |

**131 Pest feature tests.** Among them: the tenant-isolation test (5 scenarios, the HIPAA-load-bearing one), the PHI-audit test (5 write types → correct audit_logs rows), and the anti-hallucination test (CPT codes without verifiable citations get dropped before they reach the DB).

The three things flagged up-front — tenant isolation, schema field-name parity, AI citation enforcement — are verified by specific, named tests. If any of those three fails in the future, the test name is the grep string to find the regression.

---
Powered by SnapEcosystem · www.SnapSolutions.com
