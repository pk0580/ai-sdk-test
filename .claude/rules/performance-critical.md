# Performance-Critical Code

For endpoints, jobs, and queries that may run thousands of times per minute or process millions of rows.

---

## Queries

- Always set explicit columns. No `SELECT *`.
- Always have a `LIMIT` or pagination.
- Always justify joins on large tables; consider denormalization or read-side projections.
- Use prepared statements (Eloquent does this by default; raw queries must use parameter binding).
- Verify execution plan with `EXPLAIN (ANALYZE, BUFFERS)`.

```sql
SELECT id, status, created_at
FROM orders
WHERE customer_id = $1 AND status = 'paid'
ORDER BY created_at DESC
LIMIT 50;
```

Required index: `(customer_id, status, created_at DESC)`.

## Memory and Streaming

- Stream responses with `StreamedResponse` or `ResponseFactory::streamDownload()` for exports.
- Process imports in chunks; flush and clear after each chunk.

```php
foreach (LazyCollection::make(fn () => readCsv($path))->chunk(1000) as $chunk) {
    DB::transaction(fn () => OrderModel::insert($chunk->all()));
}
```

## Backpressure

- Cap concurrency on queues per business priority (Horizon `processes` per queue).
- Rate-limit downstream calls (`Http::retry(3, 100)`, exponential backoff).
- Drop or shed low-priority work under sustained overload — the system stays up.

## External Calls

Every external call sets:

- **Timeout** — `Http::timeout(5)`.
- **Retries** with backoff — `Http::retry(3, 200)`.
- **Circuit breaker** — block calls when error rate exceeds threshold (use a package or hand-rolled state in Redis).

```php
Http::baseUrl(config('services.shipping.url'))
    ->timeout(5)
    ->connectTimeout(2)
    ->retry(3, 200, fn ($e) => $e instanceof ConnectionException)
    ->withHeaders(['Authorization' => 'Bearer '.config('services.shipping.token')])
    ->post('/labels', $payload);
```

See `advanced/resilience.md`.

## Caching for Hot Reads

- Read-through cache for endpoints with predictable cache keys.
- Per-tenant cache keys to avoid cross-tenant leaks.
- Stampede protection (lock on cache miss, single regenerator).

```php
return Cache::lock("order:$id:rebuild", 10)->block(2, function () use ($id) {
    return Cache::remember("order:$id", 60, fn () => $this->loadOrder($id));
});
```

## Batching

- Batch DB inserts: `Model::insert([...])` not `Model::create()` in a loop.
- Batch jobs via `Bus::batch([...])->dispatch()` for fan-out.
- Aggregate metrics emission to avoid one UDP packet per event.

## Observability

Production-critical code emits:

- Structured logs with `request_id`, `trace_id`, `user_id`, duration.
- Metrics: counter (events), gauge (queue depth), histogram (latency).
- Spans: distributed tracing through OpenTelemetry, or Pulse for in-Laravel tracing.

```php
Log::withContext(['order_id' => $orderId->value, 'trace_id' => request()->header('X-Trace-Id')]);
```

## Octane and Workers

- For very high RPS endpoints, Laravel Octane (Swoole/RoadRunner/FrankenPHP) keeps the framework warm.
- Workers must be stateless. Avoid static state, request-scoped singletons that leak.

## Anti-Patterns

- Generating thousands of jobs in a synchronous request.
- Long-running queries in HTTP context (move to a queue, return job id, poll).
- Queueing a job per row of a million-row table without batching.
- Caching write paths.
