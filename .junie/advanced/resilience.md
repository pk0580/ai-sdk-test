# Resilience

External calls fail. Networks partition. Downstreams degrade. Build for it.

---

## Timeouts

Every external call has a timeout. There is no acceptable default of "wait forever".

```php
Http::timeout(5)              // total time
    ->connectTimeout(2)       // socket connect
    ->get('https://api.example.com/...');
```

- HTTP: 2–10 seconds depending on the operation.
- Database: connection timeout + statement timeout (set on the connection or per-query).
- Cache (Redis): 1 second connect, 500 ms read.
- External SDK: configure or wrap with `pcntl_signal` if the SDK ignores timeouts.

## Retries with Backoff

Retry only on transient errors (`5xx`, connection refused, timeout). Never retry on `4xx` (the request is wrong; retrying will not fix it).

```php
Http::retry(
    times: 3,
    sleepMilliseconds: 200,
    when: fn ($e) => $e instanceof ConnectionException,
)->get(...);
```

Backoff strategies:

- **Linear**: 200 ms, 400 ms, 600 ms.
- **Exponential**: 200 ms, 400 ms, 800 ms, 1600 ms.
- **Exponential with jitter** (preferred): exponential base + random offset to spread load.

## Circuit Breaker

When a downstream is failing repeatedly, stop calling it for a cooldown window. Saves your latency, gives the downstream room to recover.

States:

- **Closed** — calls pass through; failures counted.
- **Open** — calls fail fast for the cooldown window; do not contact the downstream.
- **Half-open** — after cooldown, allow a probe call; success closes, failure reopens.

Implementation: a state machine in Redis with TTL on the open state, or a package (`prewk/laravel-circuit-breaker`, `ackintosh/ganesha-laravel`).

```php
$breaker = app(CircuitBreaker::class)->for('shipping-api');

if ($breaker->isOpen()) {
    throw new DownstreamUnavailableException();
}

try {
    $response = $http->post(...);
    $breaker->recordSuccess();
} catch (Throwable $e) {
    $breaker->recordFailure();
    throw $e;
}
```

## Bulkheads

Isolate failure domains. A failing downstream should not consume all your queue workers.

- Separate queues per integration: `mail`, `shipping`, `analytics`.
- Separate Horizon `processes` per queue.
- Connection pools per integration if using HTTP/2 or persistent connections.

## Rate Limit Yourself (Outbound)

If a downstream allows 100 req/min, throttle to 80 req/min and never get 429'd. Use Redis token bucket or `Cache::lock()` based limiting.

```php
RateLimiter::attempt('shipping-api:'.$tenantId, 80, function () use ($http) {
    return $http->post(...);
}, 60);
```

## Graceful Degradation

When a non-critical downstream is down, degrade rather than fail:

- Cached snapshot ("last known balance: $1,245 (updated 5 minutes ago)").
- Disabled feature with explanation ("Shipping estimates temporarily unavailable").
- Async fallback (queue the work, return 202 with a poll URL).

Critical paths (payment, auth) fail loudly with a clear error code and a human message.

## Idempotent Retries

Retries are safe only when the operation is idempotent. See `advanced/idempotency.md`.

For non-idempotent operations:

- Send an idempotency key.
- Track operation status; check before retrying.

## Observability

Every retry, every breaker state change, every timeout emits a metric and a structured log.

```php
Log::info('shipping.retry', [
    'attempt' => $attempt,
    'reason' => $reason,
    'tenant' => $tenantId,
]);
```

## Anti-Patterns

- Infinite retries (`while (true) { retry(); sleep(1); }`).
- Retrying `4xx` responses.
- Catching `Throwable` and silently continuing — surfaces the problem when it is too late.
- "Just increase the timeout to 60 seconds." Slow is the new down.
- No circuit breaker on a downstream that has ever had an incident.
