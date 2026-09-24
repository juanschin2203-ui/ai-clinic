# 🚀 Rocket Coding

**AI-powered clinical documentation & medical coding platform for Workers' Compensation, OccMed, and Urgent Care clinics.**

---

## What Rocket Coding Does

Rocket Coding ingests clinical documentation (dictations, chart notes, scanned records) and produces audit-ready, payer-accurate medical coding — CPT, ICD-10, modifiers, E/M levels, and procedure coding — with human review workflows for CPCs.

**Target users:** WC clinics, OccMed providers, Urgent Care facilities, and third-party billing companies serving them.

**Core value:** Reduce coding time per chart by 60–80%, catch systemic undercoding, and produce appeal-ready documentation.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel (latest), PHP 8.2+ |
| Database | MySQL 8 |
| Queue/Scheduler | Supervisor + Cronie |
| Web Server | Nginx |
| Containerization | Docker + Docker Compose |
| Primary LLM | Anthropic Claude API |
| Secondary LLM | OpenAI GPT-4 |
| OCR | AWS Textract |
| Frontend | React (via `React.createElement` pattern — **no JSX**) |

---

## Quick Start

```bash
# 1. Clone the repo
git clone git@github.com:GeneRocketCoding/rocket-coding.git
cd rocket-coding

# 2. Copy environment file and fill in credentials
cp .env.example .env

# 3. Start containers
docker-compose up -d

# 4. Install dependencies
docker-compose exec app composer install
docker-compose exec app php artisan key:generate

# 5. Run migrations and seed
docker-compose exec app php artisan migrate --seed

# 6. Verify it's running
curl http://localhost:8080/api/health
```

Full setup instructions → [`docs/SETUP.md`](docs/SETUP.md)

---

## Repo Structure

```
rocket-coding/
├── .github/              # Issue templates, PR template, workflows
├── app/                  # Laravel application code
├── config/               # Laravel config
├── database/             # Migrations, seeders, factories
├── docs/                 # Architecture, API, onboarding docs
├── public/               # Web root (React frontend lives here)
├── resources/            # Blade views, raw assets
├── routes/               # API and web routes
├── storage/              # Logs, uploads (gitignored)
├── tests/                # PHPUnit tests
├── docker-compose.yml    # Local dev stack
├── Dockerfile            # Production image
└── README.md             # You are here
```

---

## Documentation

- **[`docs/SETUP.md`](docs/SETUP.md)** — Local dev setup, troubleshooting
- **[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)** — System design, data flow, service boundaries
- **[`docs/API.md`](docs/API.md)** — REST API reference
- **[`docs/DESIGN_TOKENS.md`](docs/DESIGN_TOKENS.md)** — Frontend design system (colors, typography, components)
- **[`CONTRIBUTING.md`](CONTRIBUTING.md)** — Coding standards, branch naming, PR process
- **[`SECURITY.md`](SECURITY.md)** — Security policy, PHI handling, reporting vulnerabilities

---

## Brand & Design

- **Primary color:** `#1E40AF` (navy)
- **Background:** `#F0EEF6`
- **Cards:** White, 3px colored left border, `#E0E7FF` header separator
- **Typography:** DM Sans (display + body)
- **Logo:** 🚀 navy gradient square
- **Border color:** `#DBE3F4`

Full design tokens in [`docs/DESIGN_TOKENS.md`](docs/DESIGN_TOKENS.md).

---

## Support

- **Product owner:** Gene Howell
- **Issues:** [GitHub Issues](https://github.com/GeneRocketCoding/rocket-coding/issues)
- **Security:** See [`SECURITY.md`](SECURITY.md)

---

## License

Proprietary — © Rocket Coding. All rights reserved.
Unauthorized copying, modification, or distribution is prohibited.
