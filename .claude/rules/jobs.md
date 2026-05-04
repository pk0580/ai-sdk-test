# Jobs and Queues

Push heavy work out of the request cycle. Keep jobs idempotent.

---

## When to Queue

Queue it when:

- External API call that may be slow, fail, or retry.
- Email, SMS, push notification.
- Report generation, large exports.
- Bulk operations that touch many rows.
- Anything that should not delay the HTTP response.

Do not queue it when:

- The user must see the result before the response (keep it sync).
- It is a pure in-memory calculation.
- It is a transactional write the user expects to be durable before the 201 returns.

## Job Structure

```php
final class SendOrderReceiptJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;       // seconds; use array for escalating: [10, 30, 60]
    public int $timeout = 60;
    public int $maxExceptions = 3;

    public function __construct(public readonly string $orderId) {}

    public function handle(OrderReceiptSender $sender): void
    {
        $sender->send(new OrderId($this->orderId));
    }

    public function uniqueId(): string
    {
        return $this->orderId;     // implement ShouldBeUnique to prevent duplicates
    }
}
```

Rules:

- Carry **ids**, not full models. Re-fetch inside `handle()`. Models serialize via `SerializesModels` with id references, but explicit ids are clearer and safer.
- Constructor properties `readonly`.
- `handle()` gets its dependencies via method injection.
- Set `$tries`, `$timeout`, `$backoff` explicitly. Do not rely on defaults.

## Idempotency

Jobs may run more than once. `SendOrderReceiptJob` must not send two emails. Strategies:

- **Deduplication by id** — record `order_id` in a `processed_jobs` table; skip if present.
- **State-based** — check the aggregate before acting (`if ($order->receiptSent) return;`).
- **Unique queue** — `ShouldBeUnique` with `uniqueFor` to prevent parallel duplicates.

See `advanced/idempotency.md`.

## Dispatching

Prefer dispatching from an Event Listener rather than directly from an Action, so the Domain event is the source of truth.

```php
final class SendReceiptOnOrderPaid
{
    public function handle(OrderPaid $event): void
    {
        SendOrderReceiptJob::dispatch($event->orderId->value);
    }
}
```

Dispatch from Action directly only when the job is the primary effect of the use case (e.g., `SendNewsletterAction` dispatches `SendNewsletterJob` to each subscriber).

## Transactions

Use `DB::afterCommit(fn () => Job::dispatch(...))` when a job must only run after the enclosing transaction commits. Laravel can also be configured with `after_commit => true` on the connection.

## Retries and Failures

- `failed(Throwable $e)` on the job to record the failure and notify operators.
- `$backoff` must grow (do not hammer a failing downstream every 5 seconds for an hour).
- Dead-letter queue (`failed_jobs` table) monitored in Horizon.

## Horizon

- Configure queues by business priority: `high`, `default`, `low`, `reports`.
- Assign jobs to queues deliberately: transactional > marketing.
- Metrics and alerts via Horizon + Pulse.

## Testing

```php
Queue::fake();
app(PayOrderAction::class)->handle($data);
Queue::assertPushed(SendOrderReceiptJob::class, fn ($j) => $j->orderId === $orderId);
```

For end-to-end: `Queue::fake()->except(SomeJob::class)` when you need one job to run synchronously.
