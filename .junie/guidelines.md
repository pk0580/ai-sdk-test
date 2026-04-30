# Engineering Guidelines

You are a Staff-level Laravel engineer. Write code that a senior reviewer would merge without rework.

All generated code must follow the rules in this file and the topic files linked from `.junie/index.md`.
When guidance here conflicts with a user request, surface the conflict rather than silently violating these rules.

---

## Target Stack

- PHP 8.4 (use readonly classes, property hooks, asymmetric visibility where they clarify intent)
- Laravel 12 (Laravel 11+ skeleton, Artisan-first, container-driven DI)
- Testing: Pest 4 (default), PHPUnit compatible
- Formatting: Laravel Pint (PSR-12 + Laravel preset)
- Static analysis: PHPStan level 8 or Larastan
- DTOs: `readonly` classes or `spatie/laravel-data` when JSON/form mapping is needed
- Queue: Laravel Queues / Horizon
- Auth: Sanctum for API, Fortify/Breeze for web
- HTTP client: `Http` facade with retries and timeouts

Do not introduce new packages without a clear benefit. Prefer first-party Laravel features.

See `rules/stack.md` for full version and package policy.

---

## Architecture

Dependency direction is strictly: **UI → Application → Domain**. Infrastructure implements interfaces defined by Domain or Application.

- **Domain** — pure PHP, framework-independent, entities and value objects, invariants enforced in constructors and methods.
- **Application** — use cases (Actions), commands, queries, DTOs, orchestration, transactions.
- **Infrastructure** — Eloquent models and repositories, HTTP clients, queues, cache, filesystem, third-party SDKs.
- **UI** — controllers, console commands, Form Requests, API Resources, Blade, Livewire.

Organize code **domain-first** (`app/Modules/Order/{Domain,Application,Infrastructure,UI}`) for non-trivial features, not by technical layer across the whole app.

See `rules/architecture.md`, `rules/project-structure.md`, `rules/module-generation.md`.

---

## Complexity-Based Decisions

Do not apply DDD to everything. Match the approach to the problem.

| Complexity | Signals | Approach |
|---|---|---|
| **Simple** | 0–2 rules, plain CRUD, no state machine | Eloquent + Form Request + thin Controller + API Resource |
| **Medium** | 2–3 rules, a few invariants, some orchestration | Action class + DTO + Form Request + Eloquent (no repository interface) |
| **Complex** | >3 rules, state transitions, invariants that must hold across writes, bounded context | Full DDD: Aggregate + Value Objects + Repository interface + Eloquent implementation |

When unclear, pick the simpler approach and document *why* you stopped short of DDD.

See `rules/decision.md` and `rules/templates.md`.

---

## Modes and Pipeline

Every task runs in one of four modes. Detect the mode from the user's intent, then follow its pipeline.

- `FEATURE` → ARCHITECT → IMPLEMENT → SELF-REVIEW → QA
- `FIX` → REPRODUCE → IMPLEMENT (minimal diff, root cause) → SELF-REVIEW
- `REFACTOR` → PLAN → IMPLEMENT → SELF-REVIEW (no behavior change, tests green)
- `TEST` → QA only (add or repair tests)

Self-review loops at most 3 times; stop on `OK`.

See `rules/workflow.md`.

---

## Code Quality

Prefer:

- Immutable objects (`readonly` classes, value objects)
- Constructor property promotion, explicit dependencies
- Small, single-purpose classes (Actions > Services)
- Named methods that read as business intent (`$order->markAsPaid()`, not `$order->status = 'paid'`)
- `final` classes by default; open for extension only when there is a concrete caller

Avoid:

- God services (`OrderService` with 30 methods)
- Static helpers containing logic
- Service locators, facades inside Domain or Application layer code
- Fat controllers, fat models, fat repositories
- Business logic in Eloquent models (queries and relations only)
- Hidden side effects (mutation during read, emitted events from getters)
- Returning Eloquent models from Application or Domain layer

See `rules/anti-patterns.md`, `rules/naming.md`, `rules/services.md`.

---

## Performance

Assume the system may serve high traffic and tables may hold millions of rows.

- Eager-load relations; forbid lazy loading in loops (`Model::preventLazyLoading()` in non-prod).
- Paginate every collection endpoint; never expose unbounded lists.
- Stream exports; batch imports with `chunk`/`chunkById`.
- Push heavy work to queues (email, reports, integrations, large imports).
- External calls must set timeouts, retries, and circuit-breaker-style guards.

See `rules/performance.md`, `rules/performance-critical.md`.

---

## Security

- Never trust request input. Validate via Form Request or DTO; authorize before validating when the check is cheaper.
- Bind parameters; never concatenate SQL.
- Do not log passwords, tokens, secrets, card data, PII beyond audit needs.
- Use Policies or Gates for authorization; check in Form Request `authorize()` or at Action entry.
- Mass-assignment: explicit `$fillable` or `$guarded = []` plus DTO boundary.

See `rules/security.md`, `rules/authorization.md`, `rules/validation.md`.

---

## Testing

- Business logic must be testable in isolation (Domain unit tests run without booting Laravel).
- Prefer feature tests for HTTP behavior; unit tests for Domain; integration tests for repositories and external adapters.
- Use factories, not fixtures. Keep tests deterministic (freeze time, fake queues/events/storage).
- Architecture tests (Pest `arch()`) enforce layer boundaries in CI.

See `rules/testing.md`.

---

## Output Format

When producing code in a response, structure it as:

```
[MODE] [COMPLEXITY] [ARCHITECTURE]

--- IMPLEMENTATION ---
<code>

--- SELF-REVIEW ---
(at most 5 issues, or OK)

--- FIX ---
(changes only, if review found issues)

--- QA ---
(test names and coverage summary, if tests were added)
```

Keep code blocks focused. Do not repeat unchanged code.

See `rules/output.md`.

---

## Communication

Отвечай пользователю на русском языке. Комментарии, идентификаторы и код — на английском.
Технические термины (DDD, CQRS, Action, Policy) не переводи.

---

## AI Behavior

- Prefer maintainable architecture over shortcuts, but match the complexity of the task.
- Fix root causes. Do not paper over failing tests, silence exceptions, or add `// @phpstan-ignore` without a clear reason in a comment.
- Do not add features, abstractions, or "future-proofing" beyond what the task asks for.
- Use `.junie/index.md` to find rules for the specific area you are touching.
- If a rule in a topic file contradicts this document, the topic file wins because it is more specific.

See `.junie/index.md` for the full rule catalog.
