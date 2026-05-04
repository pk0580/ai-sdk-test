# Events and Listeners

Events decouple a state change from its side effects. They are not a free-for-all message bus.

---

## Two Kinds of Events

1. **Domain events** — business facts. Past tense. Live in `Domain/<Module>/Events/`.
   - `OrderPaid`, `SubscriptionCancelled`, `InvoiceIssued`.
   - Raised by entities or Actions after a successful write.
2. **Framework events** — Laravel lifecycle (`Authenticated`, `MessageSent`, `MigrationStarted`).
   - Listen to these in Infrastructure for cross-cutting concerns.

Do not conflate the two. A `UserRegisteredEvent` raised by Fortify is a framework-level signal; the Domain may have its own `NewCustomerRegistered` that the Application raises after the use case completes.

---

## Raising Domain Events

Raise from the Application layer, after the transaction commits.

```php
final readonly class PayOrderAction
{
    public function handle(PayOrderData $data): void
    {
        $this->db->transaction(function () use ($data) {
            $order = $this->orders->findById(new OrderId($data->orderId))
                ?? throw new OrderNotFoundException();
            $order->markAsPaid();
            $this->orders->save($order);
        });

        $this->events->dispatch(new OrderPaid(new OrderId($data->orderId), new DateTimeImmutable()));
    }
}
```

Dispatch **after** the transaction so listeners never run on a rolled-back write. Equivalent shortcut:

```php
DB::afterCommit(fn () => $this->events->dispatch(new OrderPaid(...)));
```

## Listener Rules

- One listener per side effect. Name it for the effect: `SendReceiptOnOrderPaid`, `NotifyWarehouseOnOrderPaid`.
- Listeners are registered in `EventServiceProvider` or via `#[\Illuminate\Events\Attributes\AsEventListener]`.
- Listeners that do I/O implement `ShouldQueue` so the event dispatch is fast.
- Do not put business logic in listeners. Call an Action: `SendReceiptAction::handle(...)`.

```php
final class SendReceiptOnOrderPaid implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private SendReceiptAction $sendReceipt) {}

    public function handle(OrderPaid $event): void
    {
        $this->sendReceipt->handle(new SendReceiptData($event->orderId->value));
    }
}
```

## Do Not Abuse Events

- Events are for **side effects**, not workflow control. If step B must run after step A and you need its result, call B from A, do not chain events.
- Events are asynchronous in spirit even when dispatched synchronously — do not rely on ordering of multiple listeners.
- Do not pass full entities in events; pass ids. The listener refetches.

## Transactional Outbox

For events that must reach another system reliably, use the outbox pattern (write event row in the same transaction, worker publishes). See `advanced/outbox.md`.

## Eloquent Observers vs Domain Events

| Use observers for | Use domain events for |
|---|---|
| Cache invalidation | Send receipt |
| Logging / auditing | Notify warehouse |
| Denormalization / counters | Trigger workflow |
| Validation (rarely; prefer Form Request + Domain) | Integration to other bounded contexts |

Observers are fine for framework-level glue. Business events belong in the Domain layer, dispatched by the Application.

## Testing

```php
Event::fake();
app(PayOrderAction::class)->handle($data);
Event::assertDispatched(
    OrderPaid::class,
    fn (OrderPaid $e) => $e->orderId->value === $orderId,
);
```

When asserting the **listener** ran, use `Bus::fake()` / `Queue::fake()` for the queued job instead of letting the listener fire.
