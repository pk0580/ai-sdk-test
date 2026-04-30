# CQRS

Command Query Responsibility Segregation: separate the model that writes from the model that reads.

---

## When to Adopt

CQRS is overkill for most CRUD apps. Adopt it when:

- Read and write shapes diverge enough that one model serves neither well (e.g., dashboard projections aggregating across aggregates).
- Read traffic dominates and benefits from materialized views, denormalized tables, or a separate datastore (Elastic, Redis).
- Audit / event sourcing requirements drive a write side that is fundamentally different from the read side.

Skip it when:

- The read shape is the same as the aggregate.
- The team is small and the read load is modest.
- You just want "Actions". You can have Actions without CQRS.

---

## Two-Sided Model

### Write Side

- **Commands** (`CreateOrderCommand`) and **Command Handlers** (`CreateOrderHandler`).
- Commands are intent objects; handlers orchestrate domain logic and persist via repositories.
- Always validated (Form Request → DTO → Command).
- Returns `void` or an id, never a read shape.

```php
final readonly class PayOrderCommand
{
    public function __construct(public string $orderId, public string $paymentMethod) {}
}

final readonly class PayOrderHandler
{
    public function __construct(
        private OrderRepository $orders,
        private Dispatcher $events,
        private DatabaseManager $db,
    ) {}

    public function handle(PayOrderCommand $cmd): void
    {
        $this->db->transaction(function () use ($cmd) {
            $order = $this->orders->findById(new OrderId($cmd->orderId)) ?? throw new OrderNotFoundException();
            $order->markAsPaid();
            $this->orders->save($order);
        });
        DB::afterCommit(fn () => $this->events->dispatch(new OrderPaid(new OrderId($cmd->orderId))));
    }
}
```

### Read Side

- **Queries** (`GetOrderQuery`) and **Query Handlers**.
- Returns DTOs (`OrderView`), never aggregates or Eloquent models.
- Reads from optimized projections — read repositories, materialized views, search indexes.
- May bypass the write-side aggregate entirely.

```php
final readonly class GetCustomerDashboardQuery
{
    public function __construct(public string $customerId) {}
}

final readonly class GetCustomerDashboardHandler
{
    public function __construct(private DashboardReadRepository $reads) {}

    public function handle(GetCustomerDashboardQuery $q): DashboardView
    {
        return $this->reads->loadFor(new CustomerId($q->customerId));
    }
}
```

---

## Bus or Direct?

- **Direct** — controllers inject the handler and call it. Simple. Recommended unless a bus brings concrete value.
- **Bus** — `CommandBus` / `QueryBus` with middleware (logging, retries, transactions, audit). Worth it when you have many handlers and consistent middleware, or when you want to ship handlers to a queue (async commands).

Laravel's built-in `Bus::dispatch()` works for both sync handlers and queued jobs. Keep the seam consistent.

---

## Projections

Write side raises events. Projections (read-side processors) update read tables.

```
Command → Aggregate → Event → Projection updates read table → Query reads from read table
```

Projections are **eventually consistent**. The UI must handle reads that lag the write by a few hundred milliseconds — usually fine for dashboards, not fine for "Buy Now → list of my orders".

For after-write reads in the same request, **read your own writes** by querying the write side or holding a short read-side cache invalidation.

---

## Eventual Consistency Pitfalls

- Projection lag confuses users who expect immediate visibility. Communicate it (toast: "Saved. May take a moment to appear.").
- Out-of-order events break projections — consume in deterministic order or design idempotent updates.
- Replayability — keep events long enough to rebuild projections from scratch.

---

## Lite Variant

You can adopt much of the value with less ceremony:

- Use **Actions** for writes (one method per use case).
- Use **read repositories** with DTO projections for reads.
- Skip the bus entirely.

This is "CQRS lite" and fits most Laravel applications.
