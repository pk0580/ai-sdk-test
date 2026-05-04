# Context: Infrastructure Layer

Applies to files inside `app/Infrastructure/` (or `app/Modules/*/Infrastructure/`).

---

## Hard Rules

- Implements interfaces declared in Domain or Application.
- May depend on Application and Domain. Must not depend on UI.
- All framework / external integration lives here: Eloquent, Http, Cache, Queue, Mail, third-party SDKs.

## Typical Contents

- Eloquent models (`Persistence/Eloquent/Models/`)
- Repository implementations (`Persistence/Eloquent/Repositories/`)
- Mappers between Eloquent and Domain (`Persistence/Eloquent/Mappers/`)
- HTTP clients (`Http/Clients/`)
- External SDK adapters (`Stripe/`, `S3/`, ...)
- Mail sender adapters
- Queue handlers (or in `Application` if they are the use case itself)

## Required Patterns

- Repository implementations transform Eloquent models into Domain objects via mappers.
- HTTP clients have timeout, retry, circuit breaker (see `advanced/resilience.md`).
- External adapters wrap third-party exceptions in app-level exceptions.

## Forbidden

- Returning Eloquent models out of repository methods (unless interface explicitly types `Model`).
- Business logic in mappers.
- Side effects in constructors (no DB calls in `__construct`).

See `rules/eloquent.md`, `rules/repositories.md`.
