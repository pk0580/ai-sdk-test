# Context: Domain Layer

Applies to files inside `app/Domain/` (or `app/Modules/*/Domain/`).

---

## Hard Rules

- Pure PHP. No `Illuminate`, no `Eloquent`, no `Symfony`, no HTTP, no serialization.
- No facades. No `app()`, `resolve()`, `config()`, `request()`, `auth()`.
- No `DateTime` mutations. Use `DateTimeImmutable` or `Carbon\CarbonImmutable`.
- No `Carbon` (mutable). Prefer `CarbonImmutable` if you must use Carbon at all; ideally vanilla `DateTimeImmutable`.

## Allowed Concepts

- Entities (with identity)
- Value objects (immutable, equality by value)
- Aggregate roots and aggregates
- Domain services (stateless, span entities)
- Domain events (past tense, ids only)
- Repository **interfaces**
- Domain exceptions

## Required Patterns

- Private constructor + named constructor (`::create`, `::reconstitute`).
- Mutating methods read as business intent: `$order->markAsPaid()`, not setters.
- Invariants enforced at construction and on every mutation.
- `final` classes by default.
- `readonly` for value objects.

## Forbidden

- Public mutable properties (except `readonly`).
- Setters for state transitions.
- Returning Eloquent / framework objects.
- `toArray()` for persistence shape (mappers do that in Infrastructure).

## Quick Test

If you can run `phpunit --filter Domain` without booting Laravel, the layer is clean.

See `rules/domain.md` for full guidance.
