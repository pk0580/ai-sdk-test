# Domain Layer

Pure PHP. No framework. No I/O.

---

## What Goes Here

- **Entities** — objects with identity (`Order`, `Customer`). Behavior-rich, invariants enforced in constructor and methods.
- **Value Objects** — immutable, equality by value (`Money`, `Email`, `OrderId`, `OrderStatus`).
- **Aggregate roots** — the entity a repository loads and saves. Children are reached through it.
- **Domain services** — stateless operations that span entities and do not naturally belong to one (`PriceCalculator`).
- **Domain events** — past-tense records of things that happened (`OrderPaid`).
- **Repository interfaces** — contracts for persistence, implemented by Infrastructure.
- **Domain exceptions** — business-meaningful failures (`InvalidOrderStatusException`).

## What Does Not Go Here

- `Illuminate\*`, `Eloquent`, `Request`, `DB`, `Cache`, `Queue`, `Event`, `Log`, `Str`, `Arr` (unless the project ships a framework-free fork), `carbon` (use `DateTimeImmutable`)
- HTTP, JSON, serialization annotations
- Database-specific types
- `ShouldQueue` and other Laravel contracts

---

## Invariants

Enforce invariants in the constructor and in every mutating method. Never trust callers.

```php
final class Email
{
    public function __construct(public readonly string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: $value");
        }
    }
}

final class Money
{
    public function __construct(
        public readonly int $amountCents,
        public readonly string $currency,
    ) {
        if ($amountCents < 0) {
            throw new InvalidArgumentException('Money cannot be negative');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency: $currency");
        }
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new DomainException('Currency mismatch');
        }
        return new self($this->amountCents + $other->amountCents, $this->currency);
    }
}
```

## Entities

- Private constructor + named constructor (`Order::create(...)`, `Order::reconstitute(...)`).
- No public mutable state. Mutations go through methods that express business intent.
- Expose read-only properties or explicit accessors.
- Do not implement `toArray()`; mapping to persistence or JSON is done outside.

```php
final class Order
{
    private function __construct(
        public readonly OrderId $id,
        public readonly CustomerId $customerId,
        private OrderStatus $status,
        private array $items,     // list<OrderItem>
    ) {}

    public static function create(CustomerId $customerId): self
    {
        return new self(
            OrderId::generate(),
            $customerId,
            OrderStatus::Draft,
            [],
        );
    }

    public static function reconstitute(
        OrderId $id,
        CustomerId $customerId,
        OrderStatus $status,
        array $items,
    ): self {
        return new self($id, $customerId, $status, $items);
    }

    public function markAsPaid(): void
    {
        if ($this->status !== OrderStatus::Confirmed) {
            throw new InvalidOrderStatusException("Cannot pay order in {$this->status->value}");
        }
        $this->status = OrderStatus::Paid;
    }
}
```

## Value Objects

- `readonly` classes (PHP 8.2+) or individually-readonly properties.
- Equality by value: implement `equals(self $other): bool`.
- No setters. "Mutation" returns a new instance (`Money::add()`).

## Domain Events

- Past tense, immutable, carry ids and minimal data — not full entities.
- Raised by entities or Actions, dispatched by Application layer after successful write.

```php
final readonly class OrderPaid
{
    public function __construct(
        public OrderId $orderId,
        public DateTimeImmutable $paidAt,
    ) {}
}
```

## Repository Interfaces

Defined in Domain, implemented in Infrastructure.

```php
interface OrderRepository
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function nextId(): OrderId;
}
```

Keep the interface narrow. Read-heavy queries belong in a separate read-side repository or query object.

## Testing

Domain is the easiest thing to test: fast, no framework, deterministic.

```php
it('cannot pay a draft order', function () {
    $order = Order::create(new CustomerId('c-1'));
    expect(fn () => $order->markAsPaid())
        ->toThrow(InvalidOrderStatusException::class);
});
```
