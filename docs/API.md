# API Reference

Rocket Coding REST API. All endpoints are prefixed with `/api/v1`.

---

## Authentication

All endpoints (except `/health` and `/auth/login`) require a Bearer token.

```http
Authorization: Bearer <token>
```

Obtain a token via `POST /api/v1/auth/login`.

---

## Health & Status

### `GET /health`
Liveness check. No auth required.

**Response 200:**
```json
{
  "status": "ok",
  "version": "0.1.0",
  "environment": "production",
  "timestamp": "2026-04-22T14:30:00Z"
}
```

---

## Authentication

### `POST /auth/login`
**Body:**
```json
{ "email": "user@example.com", "password": "..." }
```
**Response 200:**
```json
{ "token": "...", "user": { "id": 1, "name": "...", "clinic_id": 42 } }
```

### `POST /auth/logout`
Invalidates the current token.

---

## Encounters

### `GET /encounters`
List encounters for the authenticated clinic.

**Query params:**
- `status` — `pending` | `coded` | `billed` | `paid`
- `from`, `to` — ISO date range
- `provider_id` — filter by provider
- `per_page` — default 25, max 100

### `POST /encounters`
Create a new encounter.
```json
{
  "patient_id": 123,
  "provider_id": 45,
  "date_of_service": "2026-04-22",
  "visit_type": "urgent_care",
  "chief_complaint": "..."
}
```

### `GET /encounters/{id}`
Retrieve a single encounter with codes, documents, and audit trail.

### `POST /encounters/{id}/code`
Trigger the AI coding pipeline.
**Response 202:**
```json
{ "job_id": "...", "status": "queued" }
```

---

## Codes

### `GET /encounters/{id}/codes`
Get all codes for an encounter.

### `POST /encounters/{id}/codes`
Add or override a code (CPC human review).
```json
{
  "type": "cpt",
  "code": "99213",
  "modifiers": ["25"],
  "units": 1,
  "source": "human"
}
```

### `PATCH /codes/{id}`
Edit an existing code.

### `DELETE /codes/{id}`
Remove a code.

---

## Documents

### `POST /documents/upload`
Multipart upload. Supports PDF, PNG, JPG, TIFF.

**Response 201:**
```json
{
  "id": 789,
  "filename": "chart_note.pdf",
  "size_bytes": 234567,
  "ocr_status": "queued"
}
```

### `GET /documents/{id}`
Metadata only (download URL signed, short-lived).

---

## Audits

### `GET /audits`
List audit findings.
**Query params:**
- `finding_type` — `upcoding` | `undercoding` | `modifier` | `denial_pattern`
- `carrier`, `facility`, `date_range`

### `POST /audits`
Create an audit finding.

### `GET /audits/{id}/appeal-draft`
Generate an appeal letter draft for this finding.
**Response 200:**
```json
{ "letter_text": "...", "template_used": "ca_sbr_lc_4603_2" }
```

---

## Appeals

### `POST /appeals`
Create a new appeal.
```json
{
  "claim_id": 456,
  "reason_code": "CARC_50",
  "template": "ca_sbr_lc_4603_2",
  "custom_notes": "..."
}
```

### `GET /appeals/{id}`
Retrieve appeal with letter text and status.

### `POST /appeals/{id}/file`
Mark appeal as filed with the carrier.

---

## Webhooks (Incoming — from Clinic Systems)

### `POST /webhooks/ehr/encounter-ready`
Clinic EHR notifies Rocket Coding that an encounter is ready to code.

**Headers:**
- `X-Rocket-Signature: sha256=<hmac>` (required)

### `POST /webhooks/billing/payment-posted`
Clinic billing system notifies Rocket Coding when an ERA 835 is posted.

---

## Webhooks (Outgoing — to Clinic Systems)

Events emitted:
- `encounter.coded` — coding pipeline completed
- `audit.finding.created` — new audit finding
- `appeal.drafted` — appeal letter ready for review
- `appeal.filed` — appeal submitted to carrier

Delivered to URLs configured per clinic in the admin panel. Signed with HMAC-SHA256.

---

## Rate Limits

- 600 requests / minute per user token
- 60 LLM-triggering requests / minute per clinic
- 10 document uploads / minute per user

429 responses include `Retry-After` header.

---

## Errors

Standard HTTP codes. Error body:
```json
{
  "error": {
    "code": "INVALID_CPT_CODE",
    "message": "CPT code 99999 is not a valid AMA code",
    "details": { "field": "codes[0].code" }
  }
}
```
