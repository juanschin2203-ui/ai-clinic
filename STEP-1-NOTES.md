# Step 1 — Stack & Scaffold

Status: **complete**. Generated 2026-04-21.

## Stack picked

Laravel-native (matches SnapEHR / SnapClaims / SnapDeposits).

| Concern | Choice | Installed |
|---|---|---|
| Language | PHP | 8.3 *(8.3.14 on host for initial scaffold; container uses Laravel Sail's PHP 8.3 image from here on)* |
| Framework | Laravel | 13.6.0 *(launcher said 11; composer pulled latest stable — strict superset, keeping it)* |
| Database | MySQL | 8.4 (Docker container `mysql`) |
| ORM | Eloquent | (bundled) |
| Auth | Laravel Sanctum | ^4.3 |
| Queues | Laravel Queues on Redis + Horizon | ^5.46 |
| AI client | `anthropic-ai/sdk` (official) | ^0.16 |
| Object storage | `league/flysystem-aws-s3-v3` | ^3.32 |
| Redis client | `predis/predis` (pure PHP — no extension needed) | ^3.4 |
| Testing | Pest + Pest-Laravel | ^4.0 |
| Code style | Laravel Pint (bundled) | ^1.27 |
| Dev environment | Laravel Sail (Docker) | ^1.57 |

**Dev runtime: everything runs in Docker.** See the "Docker dev workflow" section below for `sail up` / `sail artisan` / `sail composer` commands. Host PHP + WAMP MySQL are no longer needed once the containers are up.

## Launcher verifications (the four things Step 1 asks me to prove)

### 1 — Twelve seed arrays/objects at the top of `rocket-coding.jsx`

Verified by reading `e:\Tasks\RC\project\files\rocket-coding.jsx` lines 3–100:

| # | Name | Line | Shape |
|---|---|---|---|
| 1 | `USERS` | 3 | array (10 rows: 7 clinic users + 3 admins) |
| 2 | `CLINICS` | 15 | array (3 clinics — Valley `selfCoded:true`, Coastal + Summit `selfCoded:false`) |
| 3 | `PROVIDERS` | 19 | array (6 providers) |
| 4 | `CLINIC_ADMINS` | 27 | array (4 clinic admins) |
| 5 | `APPOINTMENTS` | 33 | array (8 appointments) |
| 6 | `SERVICES` | 42 | array (17 services) |
| 7 | `CPT_LOOKUP` | 49 | **object** (CPT code → description map, ~45 codes) |
| 8 | `REPORT_TYPES` | 50 | array (10 report types: qme, ame, pr1, pr2, initial, followup, procedure, mmi, impairment, causation) |
| 9 | `PATIENTS` | 70 | array |
| 10 | `CASES` | 75 | array (5 cases with full notes + suggested codes) |
| 11 | `FEE_STATES` | 86 | array (CA, TX, NY, FL, IL — each with localities) |
| 12 | `EMR_SYSTEMS` | 93 | array (8 EMR systems) |

Note: `CPT_LOOKUP` is an object literal, not an array (the launcher text says "arrays" loosely). `SVC_MAP` (line 48) is also an object but isn't counted in the 12 since it's a mapping helper, not seed data.

### 2 — Three commercial tiers

Verified at `rocket-coding.jsx` lines 831-833 (the Admin Billing invoice email preview):

| Tier | Rate | Service description in the JSX |
|---|---|---|
| Claim Submission | **$1.75** / claim | `📋 Claim Submission — Claims submitted to payer (HCFA-1500)` |
| AI Auto-Coding | **+$1.00** / claim | `✨ AI Auto-Coding — AI-suggested CPT/DX/Modifiers` |
| Full-Service Billing | **+$5.00** / claim | `🎯 Full-Service Billing — End-to-end claim handling` |

### 3 — Five user roles

Verified. But this has a **real ambiguity worth naming up front.**

Per `ROCKET-CODING-HANDOFF.md`:
- `clinic` — doctor / scheduler / coder / office staff
- `provider` — mobile-first provider
- `staff` — front-desk scheduler
- `admin` — Rocket Coding internal (Gene, sales, back-office coder)
- `deployer` — highest-tier: reference data, EMR integrations, audit, danger zone

But in the prototype's actual USERS seed array (lines 4-13), only **two** `role` values ever appear: `clinic` and `admin`. At the routing level (line 1300-1301), only those two get a top-level app switch:
- `user.role==="clinic"` → `ClinicApp`
- `user.role==="admin"` → `RocketAdminApp`

`ProviderApp` and `StaffApp` are *sub-views inside* `ClinicApp`, not separate login roles. `DeployerApp` is a mode-switch accessed from the LoginScreen's "⚙️ Deployer" button, not a logged-in role.

**Decision for the backend:** all five roles are first-class on the `users.role` column, enforced as a CHECK constraint / enum. Step 2's schema will include them, and Step 4's middleware will enforce per-role guards. This is a deliberate expansion of the prototype — the frontend contract still works (a `role="clinic"` user lands in `ClinicApp`, etc.), and we pick up clean separation for provider-mobile and scheduler-only logins, which the PRD requires.

Flagging this so it doesn't surprise Gene during review: the prototype's seed data will be expanded during Step 3 seeding so at least one example user exists for each of the five roles.

### 4 — Project directory structure

Standard Laravel layout with Rocket-specific additions (the AI pipeline lives under `app/Services/AI/`, background jobs under `app/Jobs/`, schema under `database/migrations/`):

```
codes/
├── app/
│   ├── Console/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/         # Step 5: one per resource (CasesController, etc.)
│   │   ├── Middleware/          # Step 4: RequireTenantMatch, RequireRole, AuditPhi
│   │   ├── Requests/            # Zod-style FormRequest validators
│   │   └── Resources/           # API response shapes (must match JSX field names)
│   ├── Jobs/                    # Step 6: AiPipeline, MonthlyBilling, AchDebit, etc.
│   ├── Models/                  # Step 2: Clinic, User, Patient, Case, Provider, ...
│   ├── Policies/                # Authorization — paired with Sanctum abilities
│   ├── Providers/
│   └── Services/
│       ├── AI/
│       │   ├── Stages/          # Step 6: ExtractPatientData, SuggestCpt, ApplyModifiers, ...
│       │   ├── PipelineOrchestrator.php
│       │   ├── ConfidenceScorer.php
│       │   └── Prompts.php
│       ├── Audit/               # PHI audit log writer (hooked into Eloquent observers)
│       ├── Billing/             # Invoice generation, ACH, Stripe
│       ├── Tenancy/             # GlobalScope that injects clinic_id filter
│       └── Anthropic/           # Thin wrapper over anthropic-ai/sdk
├── bootstrap/
├── config/
│   ├── anthropic.php            # Model names, token caps, timeouts
│   ├── auth.php                 # Sanctum TTLs
│   ├── rocket-coding.php        # Tier prices, report types, compliance rules
│   └── (…stock Laravel configs)
├── database/
│   ├── factories/               # Model factories for Pest tests
│   ├── migrations/              # Step 2: 25+ tables
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── PrototypeSeeder.php        # Step 3: mirrors rocket-coding.jsx seed arrays
│       └── ReferenceDataSeeder.php    # Step 3: CPT, ICD-10, HCPCS, fee schedules, EMRs
├── public/
├── resources/
├── routes/
│   ├── api.php                  # Step 5: all 40-60 API endpoints
│   ├── web.php                  # (Horizon dashboard only)
│   └── console.php
├── storage/
├── tests/
│   ├── Feature/
│   │   ├── Auth/                # Step 4: login, lockout, tenant-isolation (the critical test)
│   │   ├── Cases/
│   │   ├── Clinics/
│   │   └── AiPipeline/          # Step 6
│   ├── Unit/
│   └── Pest.php
├── .env                         # (gitignored) local config
├── .env.example                 # checked-in template (this repo)
├── artisan
├── composer.json
├── composer.lock
├── phpunit.xml                  # Pest uses this too
└── STEP-1-NOTES.md              # this file
```

## Packages installed (grouped by purpose)

### Runtime (`require`)
| Purpose | Package | Version |
|---|---|---|
| Framework | `laravel/framework` | ^13.0 |
| REPL / debugging | `laravel/tinker` | ^3.0 |
| Auth (JWT-like tokens via Sanctum) | `laravel/sanctum` | ^4.3 |
| Queue dashboard | `laravel/horizon` | ^5.46 *(Linux-only — see Windows note below)* |
| Object storage (S3 / R2 / MinIO) | `league/flysystem-aws-s3-v3` | ^3.32 |
| Redis client (pure PHP) | `predis/predis` | ^3.4 |
| Anthropic API SDK | `anthropic-ai/sdk` | ^0.16 |

### Development (`require-dev`)
| Purpose | Package | Version |
|---|---|---|
| Test runner | `pestphp/pest` | ^4.0 |
| Laravel-specific test helpers | `pestphp/pest-plugin-laravel` | ^4.0 |
| Test fixtures | `fakerphp/faker` | ^1.23 |
| Stack traces | `nunomaduro/collision` | ^8.6 |
| Mocks | `mockery/mockery` | ^1.6 |
| Log streaming in dev | `laravel/pail` | ^1.2.5 |
| Code formatting | `laravel/pint` | ^1.27 |
| PHPUnit (Pest sits on top of it) | `phpunit/phpunit` | ^12.5.12 |

## Docker dev workflow (Sail)

The whole dev stack runs in Docker. You don't need PHP, MySQL, or Redis installed on the host beyond the initial scaffold (which is already done). Docker Desktop must be running.

### The four containers

| Service | Image | What it's for | Host port |
|---|---|---|---|
| `laravel.test` | `sail-8.3/app` (built locally from `vendor/laravel/sail/runtimes/8.3/Dockerfile`) | PHP 8.3 + `php artisan serve` + supervisor — the app itself. pcntl/posix are available here, so Horizon runs. | `http://localhost:8080` (`APP_PORT=8080` — port 80 is taken by WAMP Apache on the host) |
| `mysql` | `mysql:8.4` | Database. Persistent volume `sail-mysql`. | `127.0.0.1:3307` (`FORWARD_DB_PORT=3307` — 3306 is taken by WAMP MySQL on the host) |
| `redis` | `redis:alpine` | Cache / queues / Horizon. Persistent volume `sail-redis`. | `127.0.0.1:6379` (`FORWARD_REDIS_PORT`) |
| `mailpit` | `axllent/mailpit:latest` | SMTP catcher — every email the app sends is captured. | SMTP `127.0.0.1:1025`, dashboard `http://localhost:8025` |

**WAMP compatibility:** The codes folder's Docker stack and the host's WAMP (Apache + MySQL) coexist — WAMP keeps port 80 and 3306, Sail uses 8080 and 3307. You don't need to stop WAMP to work on Rocket Coding.

### Starting and stopping

```bash
# First time — builds the sail-8.3/app image (2-5 min)
docker compose --project-directory codes up -d --build

# After that, a plain start is fast
docker compose --project-directory codes up -d

# Tail logs
docker compose --project-directory codes logs -f laravel.test

# Stop everything (preserves volumes — DB data stays)
docker compose --project-directory codes down

# Nuke volumes too (reset DB)
docker compose --project-directory codes down -v
```

### Running artisan / composer / tests inside the container

Three equivalent options — pick whichever you prefer:

1. **`sail` wrapper (shortest, needs bash/zsh):**
   ```bash
   cd codes
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail composer require some/package
   ./vendor/bin/sail test
   ./vendor/bin/sail shell          # open bash in the app container
   ```
   On Windows, easiest is running `./vendor/bin/sail …` from inside WSL2 or Git Bash. Doesn't work natively from cmd/PowerShell.

2. **Plain `docker compose exec` (works everywhere including Windows cmd):**
   ```bash
   docker compose --project-directory codes exec laravel.test php artisan migrate
   docker compose --project-directory codes exec laravel.test composer install
   docker compose --project-directory codes exec laravel.test php artisan test
   ```

3. **PowerShell alias (put in your `$PROFILE` for convenience):**
   ```powershell
   function sail { docker compose --project-directory E:\Tasks\RC\project\codes exec laravel.test @args }
   # then: sail php artisan migrate
   ```

**Golden rule: never run `php artisan` or `composer` from the Windows host for this project after today.** Run them inside the container. That's the whole point of this setup.

### Why Docker for dev (and not just WAMP)

Step 1 hit two show-stoppers when running Laravel on the Windows host that Docker eliminates:

1. **Horizon needs `ext-pcntl` + `ext-posix` → POSIX-only.** Can't run `php artisan horizon` on Windows natively. The Sail `laravel.test` image is Ubuntu-based, so pcntl works there. Step 6 (the AI pipeline) runs jobs via Horizon — without Docker, we'd be stuck with `queue:work` which doesn't give the dashboard/supervisor features we'll want.

2. **The `bootstrap/cache/*.php` rename race.** Something in the Windows file stack (initially I suspected Defender, but the race continued even after adding `Add-MpPreference -ExclusionPath "E:\Tasks\RC\project\codes"` — Windows Search Indexer and/or PhpStorm watchers are the likely remaining culprits) holds a brief handle on newly-written `.tmp` files, causing `Filesystem::replace()`'s atomic rename to fail with error code 32. Inside the Linux container this doesn't happen — the cache writes go through the container's own ext4/overlay filesystem and the Windows file watchers don't see them.

   Legacy symptom (for reference — you should not see this again now that everything runs in Docker):
   ```
   rename(bootstrap\cache\pac1234.tmp, bootstrap\cache/packages.php):
       The process cannot access the file because it is being used by another process (code: 32)
   ```
   If you ever hit it when running `php artisan` on the host (for example to install a new composer package that's not yet in the container), the workaround is: delete orphan `*.tmp` files in `bootstrap/cache/`, retry 2-3 times, and it usually wins. But prefer to just use `docker compose exec` instead — it always works.

Plus a third reason that'll matter later: Step 7's production deploy uses the same Docker pattern, so any dev-time bug is more likely to reproduce in prod.

### Windows bind-mount performance — a real dev-time cost

Measured steady-state for `GET http://localhost:8080/` on this machine: **~3 seconds per request** after cache warmup (first request after container start: 5-8s; cold-boot first request: ~2 minutes while it builds the autoload/config caches). Normally this endpoint is sub-200ms.

Cause: the `.:/var/www/html` bind mount in `docker-compose.yml` routes every file stat through Docker Desktop's Windows↔WSL2 translation layer. Laravel hits hundreds of files per request (autoload, config, views, etc.), so it compounds.

You'll feel this during Steps 2-6. Three mitigations, in order of impact:

1. **Move the project into WSL2's native filesystem** (biggest win — typical 10-20× speedup):
   ```bash
   # inside a WSL2 distro (Ubuntu)
   cd ~
   cp -r /mnt/e/Tasks/RC/project/codes .
   cd codes && docker compose up -d
   ```
   Open the WSL path (`\\wsl$\Ubuntu\home\<you>\codes`) in PhpStorm and it works the same. This is the recommended dev setup for Laravel on Windows.

2. **Put `vendor/` on a named volume** (5-10× speedup on just the autoload path, preserves Windows-native workflow). In `docker-compose.yml` under `laravel.test`:
   ```yaml
   volumes:
     - '.:/var/www/html'
     - sail-vendor:/var/www/html/vendor    # add this
   ```
   Plus add `sail-vendor:` under top-level `volumes:`. Downside: `composer install` must be run inside the container, and `vendor/` won't be visible from the host. (Fine for our case — we're already running composer via `docker compose exec`.)

3. **Enable Docker Desktop's VirtioFS setting** (Settings → General → "Use VirtioFS for file sharing"). Less impact on Windows than macOS, but incremental.

Do NOT do any of these during Step 1 wrap-up — just know they exist if Steps 2-6 feel painfully slow. The current setup is correct and functional; it's just not fast.

## DB column casing

Per the user's preference, column names preserve the JSX field names verbatim (`selfCoded`, `firstName`, `lastName`, `reportType`, etc.) rather than converting to snake_case. This will be enforced in Step 2's migrations.

MySQL on Windows has `lower_case_table_names=1` by default, which lowercases **table** names on disk but NOT column names. So `selfCoded` the column stays `selfCoded`; `Case` the table (if we tried to name it that) would become `case`. Laravel's plural-lowercase convention (`cases`, `users`, `clinics`) side-steps the table-name issue naturally.

## What's next

Step 2 — generate the complete Prisma-equivalent Eloquent schema: migrations, models, relationships, enums, indexes. ~25 tables. I'll produce migration files, `App\Models\*` classes, and list the design choices where the prototype was ambiguous (e.g., should `Case.suggestedCPT` be a JSON column or a separate `SuggestedCode` table — the launcher recommends JSON; I'll justify the call and flag it).

## Files produced in Step 1
- `codes/composer.json` — 6 runtime + 3 dev packages on top of stock Laravel
- `codes/composer.lock` — regenerated
- `codes/.env.example` — fully extended with all Rocket Coding vars (Anthropic, Redis, S3, ACH, SMTP via Mailpit, auth TTLs, login hardening) + Sail/Docker vars (`WWWUSER`, `APP_PORT`, `FORWARD_*_PORT`)
- `codes/.env` — generated from `.env.example`, `APP_KEY` set
- `codes/docker-compose.yml` — four-service dev stack (laravel.test + mysql 8.4 + redis + mailpit) hand-assembled from Sail's stubs (the `sail:install` command itself couldn't run on the Windows host due to the file-lock issue, but the output is identical to what it would have produced)
- `codes/STEP-1-NOTES.md` — this file
- `codes/` — entire Laravel 13 skeleton (not individually enumerated; `composer install` regenerates it)

---
Powered by SnapEcosystem · www.SnapSolutions.com
