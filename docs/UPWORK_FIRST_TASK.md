# Upwork Candidate First-Task Guide

Welcome! This is your paid trial to prove you can deliver on Rocket Coding. Read all of this before starting.

---

## Ground Rules

1. **ROCKET keyword** — you only got this far because you passed the screening. Don't lose it now.
2. **Read `CONTRIBUTING.md` and `docs/SETUP.md` first.** If you don't, it will show in the PR.
3. **Open a draft PR early.** Don't disappear for three days and come back with a monster PR.
4. **Communicate daily.** Even a one-line "still working on X, hit a snag with Y" is fine.
5. **No PHI in commits.** Ever. Seed data only.

---

## Your First Task

You'll get a GitHub issue labeled `good-first-task` assigned to you. Typical first tasks:

- Add a `/api/v1/health/deep` endpoint that checks DB, Redis, and Anthropic API connectivity
- Wire up the `PHIRedactor` service with a test suite
- Add Laravel Sanctum auth to three existing endpoints
- Build the `AnthropicCodingService` stub with retry + fallback logic

You won't get the most complex task first. We're evaluating how you work, not how much you can do.

---

## What We're Evaluating

| Area | What We Look For |
|------|------------------|
| **Code quality** | Clear naming, typed signatures, no dead code, SOLID where it matters |
| **Tests** | Did you write them? Do they actually cover the risk? |
| **Git hygiene** | Small commits, clear messages, clean history |
| **PR description** | Did you fill out the template? Did you explain your choices? |
| **Communication** | Do you ask questions? Do you update when blocked? |
| **Laravel fluency** | Do you use the framework's idioms or fight them? |
| **AI integration** | Do you treat LLMs as a service boundary, with logging and fallbacks? |

---

## Timeline

- **Day 1–2:** Environment setup, read docs, ask questions, scope your task
- **Day 3–5:** Build, push draft PR, iterate
- **Day 6–7:** Address review feedback, merge or revise

If you hit day 4 without a draft PR, that's a yellow flag. Two weeks with no PR is a red flag.

---

## How You Get Paid

Upwork hourly, weekly billing. Track your time honestly — we'll review the work diary against the output. Padded hours are grounds for termination.

---

## After the First Task

If the first task goes well, you move to regular issue assignments. Rate negotiation happens after 2–3 successful tasks.

If it goes poorly, we'll give one specific piece of feedback and either offer a second chance or end the engagement. We're direct about this.

---

## Questions?

Open a GitHub Discussion or message the product owner directly via Upwork.
