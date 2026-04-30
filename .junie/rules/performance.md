# Performance

Default assumption: tables grow without bound, traffic doubles, and someone is going to call your endpoint in a tight loop.

---

## Queries

- Eager-load relations the response will use.
- `Model::preventLazyLoading()` in non-prod to surface N+1 in development.
- `select()` only the columns you need on hot paths.
- Index every column that appears in `WHERE`, `ORDER BY`, or `JOIN ON` for queries that run often.
- Avoid `count()` on large tables when you only need "is there one?". Use `exists()`.

## Pagination

- Mandatory on every collection endpoint.
- Cursor pagination for very large datasets or infinite scroll.
- Offset pagination is fine up to ~10k rows; beyond that, switch to cursor.

## Memory

- Never load thousands of rows into memory at once.
- `lazy()` / `cursor()` for streaming.
- `chunkById()` (not `chunk()`) for batch processing of mutating queries.

## Caching

- Cache reads at the Infrastructure boundary, never inside Domain.
- Tag caches by aggregate (`cache()->tags(['order:'.$id])`).
- Invalidate explicitly on write; do not rely on TTL for correctness.
- `Cache::flexible()` (Laravel 11+) for stale-while-revalidate.

## Asynchronous Processing

Push to a queue:

- Email, SMS, push notifications
- Reports, exports, large imports
- External API integrations
- Webhook delivery

See `rules/jobs.md`.

## Bulk Writes

- `insert()` for many rows at once (no events, no Eloquent overhead).
- `upsert()` for idempotent batch upserts.
- Disable model events (`->withoutEvents(...)`) if they fire per row in a loop.

## Avoid

- `findAll()` — does not exist for a reason.
- `with('*')` — eager-loads everything.
- `whereIn('id', $thousand_ids)` — chunk it (Laravel rewrites to multiple queries beyond a few thousand).
- `orderBy('RAND()')` — kills the database. Use sample tables or app-level sampling.

## Profile Before Optimizing

- Laravel Telescope (local) and Pulse (prod) for query timing.
- Clockwork or Debugbar for in-browser profiling.
- `DB::listen()` or query log when chasing N+1.
- Always measure on data shaped like production (anonymized snapshots, not 50-row dev DBs).

See `rules/performance-critical.md` for high-traffic specifics.
