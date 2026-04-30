# Rule Catalog

Entry point: `.junie/guidelines.md`.

Load the files below when relevant to the task at hand.
Minimum set for any task: `workflow`, `decision`, `architecture`, `naming`, `anti-patterns`, plus the layer you are touching.

---

## Workflow

- `rules/workflow.md` — modes (FEATURE / FIX / REFACTOR / TEST) and pipelines
- `rules/decision.md` — CRUD vs Actions vs DDD heuristic
- `rules/output.md` — how to structure the response
- `rules/stack.md` — target versions, preferred packages, execution commands

## Architecture

- `rules/architecture.md` — layers, dependency direction
- `rules/project-structure.md` — directory layout, domain-first modules
- `rules/module-generation.md` — full module scaffold
- `rules/naming.md` — naming conventions
- `rules/anti-patterns.md` — what to avoid
- `rules/templates.md` — code templates per complexity

## Domain

- `rules/domain.md` — entities, value objects, invariants

## Application

- `rules/application.md` — use cases, commands, queries, DTOs
- `rules/services.md` — Actions over generic services
- `rules/repositories.md` — read vs write repositories

## Framework

- `rules/laravel.md` — Laravel conventions, container, providers
- `rules/eloquent.md` — ORM usage, relations, scopes
- `rules/validation.md` — Form Requests, rules, DTO validation
- `rules/authorization.md` — Policies, Gates, Form Request auth
- `rules/jobs.md` — queues, async handlers, idempotency
- `rules/events.md` — events, listeners, side effects

## API

- `rules/api.md` — REST, versioning, errors, pagination

## Database

- `rules/database.md` — migrations, indexes, data types

## Performance

- `rules/performance.md` — general guidance
- `rules/performance-critical.md` — high-traffic specifics

## Security

- `rules/security.md` — input, secrets, logging

## Testing

- `rules/testing.md` — Pest, factories, architecture tests

## Review

- `rules/code-review.md` — self-review checklist

## Context (per-layer cheat sheets)

- `context/domain.md`
- `context/application.md`
- `context/infrastructure.md`
- `context/ui.md`

## Advanced

- `advanced/idempotency.md`
- `advanced/outbox.md`
- `advanced/concurrency.md`
- `advanced/cqrs.md`
- `advanced/resilience.md`
