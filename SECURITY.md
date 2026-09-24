# Security Policy

Rocket Coding processes Protected Health Information (PHI) under HIPAA. Security is not optional.

## Reporting a Vulnerability

**Do not file a public GitHub issue for security vulnerabilities.**

Email the product owner with the subject line `SECURITY — Rocket Coding`.

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Your suggested fix (if any)

We'll acknowledge within 2 business days and provide a remediation timeline.

---

## Baseline Security Requirements

### Secrets & Credentials
- **Never** commit `.env`, API keys, certificates, or credentials.
- Use `.env.example` with placeholder values.
- Production secrets live in the deployment secret manager, not in the repo.
- Rotate any accidentally committed credential within 24 hours.

### PHI Handling
- **No real PHI in the repo.** Seeds, tests, and fixtures use synthetic data only.
- All PHI at rest must be encrypted (database-level encryption for sensitive columns).
- All PHI in transit must use TLS 1.2+.
- LLM calls must go through the redaction layer defined in `app/Services/PHIRedactor.php`.
- Audit logs for every PHI access, retained per HIPAA requirements.

### Dependencies
- `composer audit` runs in CI. Critical/high vulns block merge.
- `npm audit` runs for frontend deps.
- Dependabot enabled on the repo.

### Authentication
- Laravel Sanctum for API auth.
- Password policy: 12+ chars, complexity enforced.
- MFA required for admin roles.
- Session timeout: 30 minutes inactive for clinical users.

### Access Control
- Role-based access (RBAC) via Laravel policies.
- Least-privilege principle — default deny.
- All admin actions logged with user, timestamp, IP.

---

## HIPAA Compliance

Rocket Coding is a Business Associate under HIPAA. BAAs are required with:
- Hosting provider
- Anthropic (for Claude API)
- OpenAI (if used for PHI)
- AWS (for Textract and storage)

Contact the product owner before integrating any new third-party service that could touch PHI.

---

## Supported Versions

We patch security issues on the latest release only. Keep your deployment current.
