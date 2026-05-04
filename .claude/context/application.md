# Context: Application Layer

Applies to files inside `app/Application/` (or `app/Modules/*/Application/`).

---

## Hard Rules

- May import from Domain. May import infrastructure interfaces. May not import HTTP, Eloquent, Blade.
- One Action per use case, one public entry method (`handle()` or `__invoke()`).
- `readonly` class with constructor-injected dependencies.
- Wrap multi-row writes in `DB::transaction()`. Dispatch domain events with `afterCommit`.

## Allowed Concepts

- Actions (use cases)
- Commands and Command Handlers (when CQRS is justified)
- Queries and Query Handlers
- DTOs (input and output)
- Application services (rare, when an Action would be too small)

## Required Patterns

- DTO at the boundary; no `Request` objects past the controller.
- Authorization passed in via DTO (`$data->actorId`), not pulled from `Auth::user()`.
- Returns Domain entities, VOs, or DTOs. Never Eloquent.

## Forbidden

- `Request`, `Response`, `Auth::user()`, `request()`, `auth()`.
- Direct Eloquent calls (use repository or inject the model class).
- Business rules that belong in entities (an Action that says `if ($order->status === 'paid')` should call `$order->markAsPaid()` and let the entity decide).

See `rules/application.md`, `rules/services.md`.
