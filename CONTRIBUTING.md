# Contributing to Rocket Coding

Thanks for working on Rocket Coding. This doc covers how we work — branches, commits, PRs, and code standards.

## Ground Rules

1. **Never commit PHI or real patient data.** Use seed data and synthetic records only.
2. **Never commit secrets.** `.env` is gitignored; use `.env.example` for new keys.
3. **Ask before adding a new dependency.** We're intentional about the stack.
4. **No JSX in frontend artifacts.** Use `React.createElement` pattern — large prototypes break otherwise.

---

## Branch Naming

Use prefixes so branches are scannable in the list:

- `feat/` — new feature (e.g., `feat/era-835-parser`)
- `fix/` — bug fix (e.g., `fix/coding-queue-sort`)
- `chore/` — tooling, deps, config (e.g., `chore/upgrade-laravel-11`)
- `docs/` — docs only (e.g., `docs/api-reference`)
- `refactor/` — non-behavioral code change

Keep names short, lowercase, hyphens not underscores.

---

## Commit Messages

Format: `<type>: <short summary>`

```
feat: add AWS Textract service for scanned chart OCR
fix: correct 64484 modifier logic for bilateral lumbar cases
chore: bump anthropic-sdk to latest
docs: document CARC/RARC extraction pipeline
```

Keep the summary under 72 characters. Add a body if the "why" isn't obvious from the summary.

---

## Pull Requests

1. **Open a draft PR early** if you want feedback before you're done.
2. **Fill out the PR template** — it's not optional. Skipping it is the #1 reason PRs get sent back.
3. **Keep PRs small.** Under 400 lines changed is the target. If it's bigger, split it.
4. **Link the issue** — every PR should reference a GitHub issue (e.g., "Closes #42").
5. **Self-review first.** Read your own diff before requesting review.

### Required Checks Before Merge

- [ ] All tests pass (`php artisan test`)
- [ ] No PHPStan errors (`vendor/bin/phpstan analyse`)
- [ ] No PHP CS Fixer violations (`vendor/bin/php-cs-fixer fix --dry-run`)
- [ ] Migrations reviewed (if any)
- [ ] `.env.example` updated (if new env vars added)
- [ ] Docs updated (if public API changed)

---

## Code Standards

### PHP / Laravel

- **PSR-12** formatting, enforced by PHP CS Fixer
- **Typed properties and return types everywhere.** No `mixed` unless genuinely necessary.
- **Single Responsibility.** Controllers stay thin — logic goes in Services or Actions.
- **Form Requests** for validation, not inline `$request->validate()`.
- **Eloquent** for DB access — no raw queries unless there's a clear perf reason.
- **Jobs for anything slow** (API calls, OCR, LLM calls). Never block the HTTP thread.

### Database

- Migrations are **append-only** in production. Never edit a deployed migration — write a new one.
- Use foreign key constraints.
- Soft deletes (`deleted_at`) for anything auditable.
- Seed data lives in `database/seeders/` and must be idempotent.

### Frontend

- `React.createElement` pattern — assign `var ce = React.createElement` at top of file
- Use `var`, not `let`/`const`, in prototype artifacts
- Inline styles — follow design tokens from `docs/DESIGN_TOKENS.md`
- Button standard: `height: 26–28px`, `fontSize: 10`, `padding: "0 12px"`
- Card standard: solid white `#fff`, 3px colored left border, `#E0E7FF` separators

### AI / LLM Integration

- **All LLM calls go through a Service class** — never call Anthropic/OpenAI directly from a controller.
- **Fallback logic required**: Anthropic primary, OpenAI secondary.
- **Log every LLM request/response** for audit (with PHI redacted).
- **Cost tracking**: every LLM call records tokens used + estimated cost.
- **Never send raw PHI to an LLM without a BAA in place.** Use the redaction layer.

---

## Testing

- **Unit tests** for Services and Actions.
- **Feature tests** for API endpoints.
- **Factory everything** — don't hardcode test data.
- Aim for 70%+ coverage on new code. We'll enforce this once the baseline is set.

---

## Questions?

Open a GitHub Discussion or contact Gene via the channel you were onboarded through.
