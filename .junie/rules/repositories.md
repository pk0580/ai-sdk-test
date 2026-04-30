# Repositories

Repositories encapsulate persistence. They do not contain business logic.

---

## When to Introduce a Repository

- **Simple tier** — skip. Use Eloquent directly in Action or Controller.
- **Medium tier** — usually skip. Inject the Eloquent model class into the Action if a single shared query helper makes sense.
- **Complex tier** — interface in Domain, implementation in Infrastructure. Always.

---

## Interface (Domain)

Narrow. Aggregate-scoped. Returns Domain objects.

```php
interface OrderRepository
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function nextId(): OrderId;
}
```

Avoid:

- `findAll()` — no such thing in a system with real data.
- `findByXAndYAndZ()` — fat interface. Extract a query object or a read repository.
- Methods that return `Model` or `Collection` of models.

## Implementation (Infrastructure)

```php
final class EloquentOrderRepository implements OrderRepository
{
    public function __construct(private OrderMapper $mapper) {}

    public function save(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $row = $this->mapper->toRow($order);
            $model = OrderModel::query()->updateOrCreate(['id' => $order->id->value], $row);
            $this->mapper->syncChildren($model, $order);
        });
    }

    public function findById(OrderId $id): ?Order
    {
        $model = OrderModel::with('items')->find($id->value);
        return $model ? $this->mapper->toDomain($model) : null;
    }

    public function nextId(): OrderId
    {
        return OrderId::generate();
    }
}
```

## Mappers

A dedicated `OrderMapper` translates between the Eloquent model and the Domain entity. Keep mapping explicit — do not let entities know how they are persisted.

## Read Repositories (CQRS Flavor)

Split read and write when the read shapes diverge from the write model.

```php
interface OrderReadRepository
{
    /** @return Paginator<OrderListView> */
    public function recentForCustomer(CustomerId $id, int $perPage): Paginator;
    public function dashboardSummary(DateRange $range): DashboardView;
}
```

Reads return DTOs (`OrderListView`), not aggregates. They are optimized for the UI shape.

## Projections

Prefer SQL projections to loading full aggregates for list screens.

```php
OrderModel::query()
    ->select(['id', 'total_cents', 'status', 'created_at'])
    ->where('customer_id', $customerId->value)
    ->latest()
    ->paginate(20)
    ->through(fn ($row) => new OrderListView($row->id, $row->total_cents, $row->status));
```

## Anti-Patterns

- **Fat repository** — 30 `findBy*` methods. Extract read repositories or query objects.
- **Generic `Repository` base class with magic `findBy*`** — hides intent and creates N+1 traps.
- **Business rules inside a repository** — "if customer is VIP, use this query". Decide in Domain or Application; pass parameters.
- **Repository returning Eloquent models across the boundary** — breaks the abstraction; inject the model class directly if that is what you want.
