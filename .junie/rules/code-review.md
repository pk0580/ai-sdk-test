# Self-Review Checklist

Before returning code to the user, run this checklist. Report at most 5 issues, or `OK`. Loop fix → review at most 3 times.

---

## Architecture

- [ ] Dependencies flow UI → Application → Domain. Infrastructure implements interfaces.
- [ ] Domain code does not import `Illuminate`, `Eloquent`, `Http`, `DB`, facades.
- [ ] Application code does not import `Request`, Eloquent models, or Blade.
- [ ] Controllers are thin: validate → DTO → Action → response.
- [ ] No God service. Each Action has one responsibility.

## DDD (Complex tier only)

- [ ] Entities enforce invariants in their constructor and methods.
- [ ] No public mutable state on entities.
- [ ] Value objects are immutable; equality by value.
- [ ] Domain events are past tense, carry ids, are dispatched after commit.
- [ ] Repository interface is narrow and aggregate-scoped.

## Repositories

- [ ] No business logic.
- [ ] Returns Domain objects or DTOs, not Eloquent models.
- [ ] Read repositories used for complex queries; write repository stays narrow.

## Eloquent

- [ ] No N+1 (eager-loaded relations).
- [ ] No lazy loading inside loops.
- [ ] Pagination on collection queries.
- [ ] Explicit column lists on hot paths.
- [ ] No `findAll()` on large tables.

## Performance

- [ ] Heavy work pushed to queues.
- [ ] External calls have timeout, retries, backoff.
- [ ] Bulk operations use `chunkById` or `lazy`.
- [ ] No unbounded collections returned to the caller.

## API

- [ ] Versioned endpoint (`/api/v1/...`).
- [ ] Consistent response envelope.
- [ ] Proper HTTP status code (201 for create, 422 for validation, 409 for conflict).
- [ ] No Eloquent model returned directly; wrapped in Resource or DTO.
- [ ] Idempotency-Key supported on critical writes.

## Validation and Auth

- [ ] Form Request handles validation **and** authorization.
- [ ] Policies cover per-instance authorization.
- [ ] No mass assignment from `$request->all()`.

## Concurrency

- [ ] Writes are safe under concurrent requests (optimistic lock, unique constraint, advisory lock).
- [ ] Idempotent commands where the operation could be retried.
- [ ] Domain events dispatched with `afterCommit`.

## Security

- [ ] Input validated.
- [ ] SQL bound, never concatenated.
- [ ] Secrets via `config()`, not hardcoded.
- [ ] No PII / passwords / tokens in logs.

## Tests

- [ ] Happy path covered.
- [ ] At least one failure path covered.
- [ ] No `sleep()` in tests; use fakes.
- [ ] No mocks of Domain or DB.
- [ ] Architecture tests still pass.

## Code Quality

- [ ] No dead code, no commented-out blocks.
- [ ] No `TODO` left for the reviewer.
- [ ] Names express intent (no `Manager`, `Helper`, `Util`).
- [ ] Methods read top-down; small (< 30 lines is a good default).
- [ ] PHPStan-clean at the project's level.

## Diff Discipline

- [ ] No drive-by formatting changes.
- [ ] No unrelated refactors mixed with a fix.
- [ ] Tests updated alongside the behavior change.

## Final

If a better architectural approach exists and the cost of switching is small, prefer it. If switching is large, document the trade-off in the response and proceed.

Always prioritize: **maintainability → testability → scalability → ergonomic shortcuts**.
