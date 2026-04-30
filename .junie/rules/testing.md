# Testing

Pest 4 by default. PHPUnit if the project already standardized on it. All business logic is testable; if it is not, refactor.

---

## Test Pyramid

- **Unit** — Domain layer. No framework boot. Fast (sub-millisecond per test).
- **Integration** — Infrastructure adapters (repositories, HTTP clients, queue handlers) against real dependencies (test DB, mock HTTP).
- **Feature** — HTTP endpoints, end-to-end through the container. The bulk of the suite.
- **Architecture** — Pest `arch()` rules to enforce layer boundaries in CI.

Heavier weight at the feature layer is fine for Laravel apps; the framework is itself the integration surface for most code.

---

## Pest Examples

### Unit (Domain)

```php
// tests/Unit/Domain/Order/OrderTest.php
use App\Domain\Order\{Order, OrderStatus, CustomerId};

it('creates an order in draft status', function () {
    $order = Order::create(new CustomerId('c-1'));
    expect($order->status())->toBe(OrderStatus::Draft);
});

it('cannot pay a draft order', function () {
    $order = Order::create(new CustomerId('c-1'));
    expect(fn () => $order->markAsPaid())
        ->toThrow(InvalidOrderStatusException::class);
});
```

No `RefreshDatabase`, no `TestCase` — pure PHPUnit/Pest with the Domain class only.

### Feature (HTTP)

```php
// tests/Feature/Order/CreateOrderTest.php
uses(Tests\TestCase::class, RefreshDatabase::class);

it('creates an order', function () {
    Event::fake();
    $customer = Customer::factory()->create();
    $token = $customer->createToken('test', ['orders:write']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [['sku' => 'SKU-1', 'qty' => 2, 'price_cents' => 999]],
        ]);

    $response->assertCreated()->assertJsonPath('data.id', fn ($v) => is_string($v));
    Event::assertDispatched(OrderCreated::class);
    expect(OrderModel::count())->toBe(1);
});

it('rejects unauthenticated requests', function () {
    $this->postJson('/api/v1/orders', [])->assertUnauthorized();
});

it('returns 422 on missing items', function () {
    $customer = Customer::factory()->create();
    $this->actingAs($customer)
        ->postJson('/api/v1/orders', ['customer_id' => $customer->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});
```

### Integration (Repository)

```php
// tests/Integration/Infrastructure/Order/EloquentOrderRepositoryTest.php
uses(Tests\TestCase::class, RefreshDatabase::class);

it('round-trips an order', function () {
    $repo = app(OrderRepository::class);
    $order = Order::create(new CustomerId('c-1'));
    $order->addItem(new Sku('S-1'), 1, new Money(500, 'USD'));
    $repo->save($order);

    $loaded = $repo->findById($order->id);
    expect($loaded)->not->toBeNull();
    expect($loaded->status())->toBe(OrderStatus::Draft);
});
```

### Architecture

```php
// tests/Architecture/LayersTest.php
arch('domain has no framework imports')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'Symfony', 'Eloquent']);

arch('controllers are invokable or thin')
    ->expect('App\UI\Http\Controllers')
    ->toBeClasses();

arch('actions are readonly')
    ->expect('App\Application')
    ->classes()
    ->toBeReadonly();
```

---

## Factories

Use Laravel factories for any test that needs DB rows. Define `state` methods for variants.

```php
final class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Draft,
            'total_cents' => 0,
        ];
    }

    public function paid(): self
    {
        return $this->state(['status' => OrderStatus::Paid, 'total_cents' => 1999]);
    }
}
```

---

## Determinism

- Freeze time: `Carbon::setTestNow('2026-01-01')` or `Pest\Time::freeze()`.
- Fake side effects: `Queue::fake()`, `Event::fake()`, `Bus::fake()`, `Mail::fake()`, `Notification::fake()`, `Storage::fake()`, `Http::fake()`.
- Seed random sources: `fake()->seed(1234)` if randomness affects assertions.
- One assertion topic per test. Multiple assertions per test are fine; multiple unrelated scenarios are not.

---

## Coverage

- Aim for high coverage on Domain (close to 100%; it is pure code).
- Application: every Action has at least one happy path and one failure path.
- Infrastructure: integration tests for non-trivial mappers and repository methods.
- Do not chase coverage on Eloquent models, controllers (covered by feature tests), or framework glue.

---

## What Not to Mock

- Domain entities — use the real class.
- Eloquent models in feature tests — use the real DB (transactions roll back).
- Time, queues, mail, events, storage — use Laravel's fakes; they are explicit and assertable.

## What to Mock

- Outbound HTTP — `Http::fake([...])`.
- Third-party SDKs — bind a fake implementation in the test container.
- Slow or flaky external services in integration tests; use real services in dedicated end-to-end suites.
