# Concurrency

Assume two processes will modify the same data at the same time. Pick the right tool.

---

## Optimistic Locking

Default for most aggregates. Add a `version` column; increment on every write; reject writes whose `version` does not match the loaded value.

```sql
ALTER TABLE orders ADD COLUMN version INT NOT NULL DEFAULT 0;
```

```php
public function save(Order $order): void
{
    $rows = OrderModel::where('id', $order->id->value)
        ->where('version', $order->version)
        ->update([...$this->mapper->toRow($order), 'version' => $order->version + 1]);

    if ($rows === 0) {
        throw new ConcurrencyException('Order was modified by another process');
    }
}
```

Map `ConcurrencyException` to HTTP 409 Conflict so the client can refetch and retry.

## Pessimistic Locking

When the operation is short and contention is high (inventory decrement, balance update).

```php
DB::transaction(function () use ($id, $qty) {
    $row = DB::table('inventory')->where('product_id', $id)->lockForUpdate()->first();
    if ($row->qty < $qty) throw new InsufficientStockException();
    DB::table('inventory')->where('product_id', $id)->update(['qty' => $row->qty - $qty]);
});
```

Always inside a transaction. Always have a timeout.

## Advisory Locks (PostgreSQL)

Coordinate across processes without a row to lock.

```php
DB::statement('SELECT pg_advisory_xact_lock(?)', [crc32('daily-import')]);
```

Released at transaction end. Use for cron jobs that must not overlap.

## Database Constraints

Cheapest concurrency tool. Let the database reject invalid concurrent states:

- Unique indexes for "one of X per Y".
- Check constraints for invariants (`amount >= 0`).
- Foreign keys for referential integrity.
- Exclusion constraints (PostgreSQL) for non-overlapping ranges.

## Idempotency

A retried write must not duplicate. See `advanced/idempotency.md`.

## Distributed Locks (Redis)

For coordinating across application instances. Laravel ships with `Cache::lock()`.

```php
$lock = Cache::lock('rebuild-leaderboard', 60);
if ($lock->get()) {
    try {
        // ...
    } finally {
        $lock->release();
    }
}
```

Set an explicit TTL. Always wrap in `try/finally`. Prefer `block()` with a max wait if waiting is acceptable.

## Choosing

| Situation | Tool |
|---|---|
| User edits a profile that another user might also edit | Optimistic locking + 409 |
| Decrement inventory under load | Pessimistic locking + transaction |
| One cron job at a time across nodes | Advisory lock or distributed lock |
| One unique business reference per row | Unique constraint |
| Webhook may fire twice | Idempotency key or state check |
| Long-running workflow with steps | Saga or state machine, not raw locks |

## Anti-Patterns

- Sharing a Redis lock across application versions without migration thought.
- "Read, sleep, write" loops to dodge locks. Use locks properly or use a unique constraint.
- Holding a database lock while making external HTTP calls inside the transaction.
- Optimistic locking without surfacing the conflict to the client (silent overwrites).
