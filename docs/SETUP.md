# Setup Guide

Get Rocket Coding running locally in under 30 minutes.

---

## Prerequisites

- **Docker Desktop** (latest) — https://www.docker.com/products/docker-desktop
- **Git** — https://git-scm.com/downloads
- **Composer** (optional — only if running outside Docker) — https://getcomposer.org
- **Node.js 20+** (optional — only for frontend dev outside Docker)

**Minimum machine specs:** 8 GB RAM, 20 GB free disk.

---

## Step 1: Clone the Repo

```bash
git clone git@github.com:GeneRocketCoding/rocket-coding.git
cd rocket-coding
```

## Step 2: Configure Environment

```bash
cp .env.example .env
```

Open `.env` and fill in at minimum:
- `ANTHROPIC_API_KEY` — get from Gene
- `OPENAI_API_KEY` — get from Gene
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` — get from Gene
- `DB_PASSWORD` — pick anything for local dev

## Step 3: Start the Stack

```bash
docker-compose up -d
```

This starts:
- `app` — Laravel/PHP 8.2 container
- `nginx` — web server on port 8080
- `mysql` — database on port 3306
- `redis` — cache/queue on port 6379
- `queue` — background job worker
- `mailhog` — fake SMTP on port 8025 (UI at http://localhost:8025)

Verify everything is up:

```bash
docker-compose ps
```

All services should show `Up` or `Up (healthy)`.

## Step 4: Install PHP Dependencies

```bash
docker-compose exec app composer install
```

## Step 5: Generate App Key

```bash
docker-compose exec app php artisan key:generate
```

## Step 6: Run Migrations + Seed

```bash
docker-compose exec app php artisan migrate --seed
```

Seeder creates:
- 1 admin user: `admin@rocketcoding.test` / `Rocket@2026!`
- Sample clinics and synthetic coding cases for testing

## Step 7: Verify

```bash
curl http://localhost:8080/api/health
```

Expected response:
```json
{"status": "ok", "version": "0.1.0", "environment": "local"}
```

Open http://localhost:8080 in your browser — you should see the Rocket Coding login page.

---

## Common Commands

```bash
# Tail logs
docker-compose logs -f app

# Run tests
docker-compose exec app php artisan test

# Open tinker (Laravel REPL)
docker-compose exec app php artisan tinker

# Clear caches
docker-compose exec app php artisan optimize:clear

# Run a specific migration
docker-compose exec app php artisan migrate --path=/database/migrations/2026_XX_XX_your_migration.php

# Fresh database (wipes everything)
docker-compose exec app php artisan migrate:fresh --seed

# Stop everything
docker-compose down

# Stop AND wipe volumes (nuclear option)
docker-compose down -v
```

---

## Troubleshooting

### Port already in use
Something else on your machine is using port 8080, 3306, or 6379. Either stop that service or edit `docker-compose.yml` to use different host ports.

### Permission errors on storage/
```bash
docker-compose exec app chmod -R 775 storage bootstrap/cache
docker-compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### MySQL won't start
Usually a volume corruption issue. Nuke and restart:
```bash
docker-compose down -v
docker-compose up -d
```

### "Class not found" errors
```bash
docker-compose exec app composer dump-autoload
```

### LLM calls failing
Check `ANTHROPIC_API_KEY` and `OPENAI_API_KEY` are set in `.env`. Then restart:
```bash
docker-compose restart app queue
```

### Can't pull from GitHub
Make sure your SSH key is added to your GitHub account, or clone via HTTPS instead.

---

## Next Steps

Once you're running locally:

1. Read [`ARCHITECTURE.md`](ARCHITECTURE.md) to understand the system.
2. Read [`../CONTRIBUTING.md`](../CONTRIBUTING.md) for coding standards.
3. Pick up a `good-first-issue` from [GitHub Issues](https://github.com/GeneRocketCoding/rocket-coding/issues?q=label%3A%22good+first+issue%22).
4. Open a draft PR early — don't wait until it's "done."

Questions? Contact Gene via the channel you were onboarded through.
