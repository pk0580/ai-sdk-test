# Transactional Outbox

When you need integration events to reliably reach another system after a database write — without dual-write inconsistency.

---

## The Problem

```php
DB::transaction(fn () => $repo->save($order));
$messageBus->publish(new OrderCreated($order->id));   // process crashes here
```

The DB has the order; the downstream never hears about it. Conversely:

```php
$messageBus->publish(...);
DB::transaction(fn () => $repo->save($order));        // transaction fails
```

The downstream got an event for an order that does not exist.

This is the **dual-write problem**.

---

## The Pattern

Write the event to an outbox table **inside the same transaction** as the aggregate. A worker reads the outbox and publishes externally.

```sql
CREATE TABLE outbox_messages (
    id            UUID         PRIMARY KEY,
    aggregate_id  VARCHAR(64)  NOT NULL,
    type          VARCHAR(128) NOT NULL,
    payload       JSONB        NOT NULL,
    created_at    TIMESTAMPTZ  NOT NULL DEFAULT now(),
    published_at  TIMESTAMPTZ  NULL,
    attempts      INT          NOT NULL DEFAULT 0,
    last_error    TEXT         NULL
);
CREATE INDEX ON outbox_messages (published_at) WHERE published_at IS NULL;
```

Action:

```php
DB::transaction(function () use ($order) {
    $this->orders->save($order);
    OutboxMessage::create([
        'id' => Str::uuid(),
        'aggregate_id' => $order->id->value,
        'type' => OrderCreated::class,
        'payload' => ['order_id' => $order->id->value, 'occurred_at' => now()->toIso8601String()],
    ]);
});
```

Worker (scheduled command or queue job):

```php
final class PublishOutboxJob implements ShouldQueue
{
    public function handle(MessagePublisher $publisher): void
    {
        OutboxMessage::query()
            ->whereNull('published_at')
            ->orderBy('created_at')
            ->limit(100)
            ->lockForUpdate()
            ->get()
            ->each(function (OutboxMessage $msg) use ($publisher) {
                try {
                    $publisher->publish($msg->type, $msg->payload);
                    $msg->update(['published_at' => now()]);
                } catch (Throwable $e) {
                    $msg->increment('attempts');
                    $msg->update(['last_error' => $e->getMessage()]);
                }
            });
    }
}
```

Schedule:

```php
Schedule::job(PublishOutboxJob::class)->everyMinute();
```

---

## Guarantees

- **At-least-once** delivery. Consumers must be idempotent (see `advanced/idempotency.md`).
- Ordering per aggregate if you publish in `created_at` order and use a single worker per partition.
- No dual-write inconsistency.

## Variations

- **CDC (Change Data Capture)** — Debezium or similar reads the WAL/binlog and publishes. The outbox table becomes a CDC source. Often preferred at scale.
- **In-process** — small systems can publish directly with `DB::afterCommit()`. The outbox is overkill until you need cross-process or cross-region durability.

## Pitfalls

- Forgetting to set `published_at` on success.
- Worker that does not back off on persistent failures (poison message storms).
- Outbox table without retention — clean up published rows after a grace period.
- Not handling out-of-order delivery on the consumer side.
