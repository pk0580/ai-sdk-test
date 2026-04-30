# Idempotency

Operations that can be retried — webhooks, payment, order creation, message handlers — must produce the same result whether they run once or ten times.

---

## Why

- Network retries duplicate requests.
- Queue redelivery duplicates handlers.
- User clicks "Submit" twice.

A non-idempotent endpoint that creates two orders for one click is a bug.

---

## Strategies

### 1. Idempotency Key (HTTP)

Client sends `Idempotency-Key: <uuid>` on the request. Server stores key + response for 24 hours; replays return the original response without re-running the work.

Schema:

```sql
CREATE TABLE idempotency_keys (
    key          VARCHAR(64)  PRIMARY KEY,
    user_id      UUID         NOT NULL,
    request_hash CHAR(64)     NOT NULL,
    response     JSONB        NOT NULL,
    status_code  INTEGER      NOT NULL,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    expires_at   TIMESTAMPTZ  NOT NULL
);
CREATE INDEX ON idempotency_keys (expires_at);
```

Middleware:

```php
final class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (!$key) return $next($request);

        $hash = hash('sha256', $request->getContent());

        if ($cached = IdempotencyKey::find($key)) {
            if ($cached->request_hash !== $hash) {
                abort(409, 'Idempotency-Key reused with different payload');
            }
            return response($cached->response, $cached->status_code);
        }

        $response = $next($request);
        IdempotencyKey::create([
            'key' => $key,
            'user_id' => $request->user()?->id,
            'request_hash' => $hash,
            'response' => $response->getContent(),
            'status_code' => $response->getStatusCode(),
            'expires_at' => now()->addDay(),
        ]);

        return $response;
    }
}
```

### 2. Unique Constraint at the Database

The cleanest. Use a natural unique key (`reference`, `external_id`, `(user_id, day, type)`).

```php
try {
    OrderModel::create(['reference' => $data->reference, ...]);
} catch (UniqueConstraintViolationException $e) {
    return OrderModel::where('reference', $data->reference)->first();
}
```

### 3. State Check

Before acting, check the aggregate's state.

```php
if ($order->status === OrderStatus::Paid) {
    return;     // already done; no-op
}
$order->markAsPaid();
```

Combine with optimistic locking (version column) to prevent races.

### 4. Job Deduplication

`ShouldBeUnique` keeps duplicate dispatches off the queue.

```php
final class SendReceiptJob implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 3600;
    public function uniqueId(): string { return $this->orderId; }
}
```

For at-least-once delivery, also add a state check inside `handle()`.

---

## Anti-Patterns

- "We will not retry, so idempotency does not matter." Networks retry. Queues retry.
- Idempotency key stored in memory (lost on deploy / restart).
- Long retention with no expiry policy (table grows forever).
- Storing only the key without the request hash — replays with different payloads silently succeed.
