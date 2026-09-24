# Rocket Coding — production deploy checklist

Work through this before going live. Every item either blocks a HIPAA
requirement, a SOC 2 control, or a realistic prod incident. Nothing in
this list is "nice to have."

Sign-off this checklist in the project tracker before flipping DNS.

---

## 1. Legal + contracts (do these first; they're long-lead items)

- [ ] **HIPAA Business Associate Agreement signed with hosting provider**
      (AWS, GCP, or equivalent). Without the BAA, ANY PHI in the account
      is a violation on day one.
- [ ] **BAA signed with Anthropic** covering use of their API for PHI.
      (Anthropic has a standard BAA — request via enterprise support.)
      Until this is in place, the AI pipeline cannot run against live
      patient data — dev/staging runs against synthetic data only.
- [ ] **BAA signed with the email provider** (Postmark / SendGrid / SES).
      Invoice emails are PHI-adjacent (they contain patient counts, not
      patient data, but the provider still needs the BAA).
- [ ] **BAA signed with the monitoring/logging provider** (Datadog,
      CloudWatch, Sentry). Log lines contain request IDs + user IDs that
      cross-reference PHI.
- [ ] **SOC 2 Type II audit engagement with an auditor.** Usually a
      3-6 month process — start before the first customer signs.
- [ ] **Pen-test engagement scheduled** for ~2 weeks before go-live.
      Results must be triaged before prod DNS flip.

## 2. Infrastructure

- [ ] Managed MySQL (RDS Aurora recommended) with:
    - [ ] Encryption at rest (AWS-managed KMS key, rotate annually)
    - [ ] Automated daily snapshots, 35-day retention
    - [ ] Multi-AZ failover enabled
    - [ ] Parameter group: `sql_require_primary_key=1`, `local_infile=0`
    - [ ] DB credentials rotated via AWS Secrets Manager + `SecretsManagerRotationEnabled`
- [ ] Managed Redis (ElastiCache, cluster mode disabled OK at current scale)
    - [ ] Encryption at rest + in-transit
    - [ ] Auth token set (not default — never run open Redis)
- [ ] S3 bucket for file uploads
    - [ ] Default encryption: AES-256 (or KMS)
    - [ ] Versioning ON (accidental PHI deletion recovery)
    - [ ] Public access: blocked at bucket AND account level
    - [ ] Lifecycle policy: move to Glacier after 1 year, delete after 7 (HIPAA retention)
    - [ ] Bucket policy restricts access to the app's IAM role only
- [ ] S3 bucket for DB backups (`scripts/migrate-prod.sh` writes here)
    - [ ] Encryption AES-256
    - [ ] Versioning ON
    - [ ] Cross-region replication ON (disaster recovery)
- [ ] WAF in front of the app
    - [ ] AWS Managed Rules "Common Rule Set" + "SQL Injection" + "Bad Inputs"
    - [ ] Rate limit 2000 req/min per IP baseline (tune after first week)
    - [ ] IP reputation list (AWS managed)
- [ ] TLS termination at ALB/CloudFront
    - [ ] TLS 1.2+ only (disable 1.0 / 1.1)
    - [ ] HSTS header with 1-year max-age, includeSubDomains, preload
    - [ ] ACM certificate auto-renewal

## 3. Secrets

- [ ] **NO `.env` file on the production host.** Secrets loaded from
      AWS Secrets Manager via task-definition environment variables.
- [ ] `APP_KEY` generated fresh for production. NEVER reused from dev.
- [ ] `ANTHROPIC_API_KEY` — production key, NOT shared with dev.
      Rate limits separated.
- [ ] MySQL password — rotated before first customer, rotated quarterly.
- [ ] Sanctum access + refresh token TTLs sanity-checked
      (`SANCTUM_ACCESS_TOKEN_TTL=15`, `SANCTUM_REFRESH_TOKEN_TTL=10080`).
- [ ] `APP_DEBUG=false` — verified in the deployed env, not just the image.

## 4. Application config

- [ ] `APP_ENV=production`
- [ ] `APP_URL=https://api.rocketcoding.com` (or whatever the real domain is)
- [ ] `LOG_CHANNEL=stderr`, `LOG_LEVEL=warning`
- [ ] `FILESYSTEM_DISK=s3` — NOT `local`. Verify the app actually writes
      new file uploads to S3 after deploy (upload a dummy, inspect bucket).
- [ ] `QUEUE_CONNECTION=redis`
- [ ] `CACHE_STORE=redis`, `SESSION_DRIVER=redis`
- [ ] `MAIL_MAILER=smtp` pointing at the real email provider
- [ ] Sanctum `SESSION_DOMAIN` + CORS `supports_credentials` aligned
      with the frontend host
- [ ] `BCRYPT_ROUNDS=12` (or higher — confirm `config('hashing.bcrypt.rounds')`)

## 5. Database

- [ ] Pre-deploy backup written to S3 (`scripts/migrate-prod.sh` does this)
- [ ] `php artisan migrate --force --pretend` reviewed by a second pair
      of eyes BEFORE running for real
- [ ] Production seeding is `ReferenceDataSeeder` only. NEVER run
      `PrototypeSeeder` in production — it creates demo users with
      password `demo123`.
- [ ] Composite unique constraints verified in the deployed schema:
      (`users.email`), (`patients.clinicId, emrId`),
      (`invoices.clinicId, period`)
- [ ] The single most important check:
      ```bash
      docker compose --project-directory codes exec laravel.test \
        php artisan test --filter TenantIsolationTest
      ```
      If this fails against the production schema, HALT the deploy —
      it is a HIPAA incident waiting to happen.

## 6. Observability + alerting

- [ ] Structured JSON logs (Monolog's JsonFormatter) flowing to
      CloudWatch / Datadog / whatever the aggregator is
- [ ] Metrics exported:
    - [ ] HTTP request rate / latency / error rate (from nginx access log)
    - [ ] Queue depth per supervisor (`/api/ops/queue/depth`)
    - [ ] AI token consumption per clinic (`/api/ops/ai/token-usage`)
    - [ ] DB connection pool usage (RDS Performance Insights)
- [ ] Alerts configured:
    - [ ] 5xx error rate > 1% for 5 minutes → PagerDuty
    - [ ] AI pipeline job failure rate > 10% for 15 minutes → PagerDuty
    - [ ] Queue depth > 1000 on any queue for 10 minutes → PagerDuty
    - [ ] Daily Anthropic spend > $500 → email ops
    - [ ] Horizon supervisor down > 2 minutes → PagerDuty
- [ ] Sentry (or equivalent) for unhandled exceptions
- [ ] Uptime monitor (Pingdom / UptimeRobot / Datadog Synthetics) hitting
      `/api/health` every 1 minute — 2 consecutive failures = page

## 7. Security

- [ ] **Automated vulnerability scanner** (Trivy, Snyk, or Dependabot)
      wired into the CI pipeline — fails the build on critical CVEs
- [ ] Dependabot / Renovate enabled on the repo
- [ ] Pen-test report reviewed; every "high" + "critical" finding fixed
- [ ] Rate limits in production mode — verify by curl:
      6 bad logins in a row returns 429 (per-IP) AND 429 (per-email,
      independently) within 15 minutes
- [ ] The `/horizon` dashboard is gated to admin + deployer roles only.
      Verify by logging in as a clinic user and attempting to load it.
- [ ] Session + cookie config in `php.ini` confirmed:
      `session.cookie_secure=1`, `cookie_httponly=1`, `samesite=Strict`
- [ ] `/health` endpoint returns 200 ONLY when all deps are reachable
- [ ] Any dev fixtures (factories, seeders) excluded from the production
      image — verified by `docker run … ls storage/` in the built image

## 8. Backups + disaster recovery

- [ ] RDS backups verified: restore a fresh RDS instance from the latest
      automatic snapshot. Must happen at least once before go-live.
- [ ] S3 versioning verified: delete a file, confirm it's recoverable
      from a previous version
- [ ] Runbook for full regional outage documented — include DNS
      cut-over timing, data-loss window (RPO/RTO stated)
- [ ] Audit-log retention: HIPAA requires 6 years minimum. Confirm
      `audit_logs` is on a table that's being backed up and the backup
      retention >= 6 years in Glacier

## 9. Smoke tests (run these before DNS flip)

All run against the production deploy, in order:

```bash
# 1. Health check green
curl -s https://api.rocketcoding.com/api/health | jq

# 2. Login as the initial admin user (created via a one-off migration)
TOKEN=$(curl -s -X POST https://api.rocketcoding.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"ADMIN","password":"…"}' | jq -r .data.accessToken)

# 3. Confirm admin can list clinics
curl -s https://api.rocketcoding.com/api/clinics \
  -H "Authorization: Bearer $TOKEN" | jq '.data | length'

# 4. Confirm Horizon dashboard loads (browser) and shows supervisors
open https://api.rocketcoding.com/horizon   # admin session required

# 5. Confirm cross-tenant request gets blocked
#    (repeat step 2 as a clinic user, then try /api/clinics/<other-clinic-id>)
```

## 10. Final go-live

- [ ] Tenant isolation test passes
- [ ] All items above checked
- [ ] Rollback plan documented and rehearsed
- [ ] On-call rotation for week 1 set up
- [ ] Post-mortem template ready (we will need it for the first real incident)
- [ ] Customer communications + status page (statuspage.io) live

**Go-live sign-off:** ____________________________________________________
**Date + UTC time of DNS flip:** __________________________________________

---

Powered by SnapEcosystem · www.SnapSolutions.com
