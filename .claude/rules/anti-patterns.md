# Anti-Patterns

If you catch yourself writing any of these, stop and refactor.

---

## Structural

- **Fat controller** — business logic inside controller methods. Move to an Action.
- **Fat model** — Eloquent model with 20+ methods and imports from half the codebase. Push behavior into Domain entities; keep the model as a mapping shape.
- **God service** — `OrderService::class` with `createOrder`, `cancelOrder`, `refundOrder`, `exportOrders`, `archiveOrder`. Split into Actions.
- **Fat repository** — `findByX`, `findByY`, `findByXAndY`, `findByEverything`. Extract a read repository with specific-purpose queries or use query objects.
- **Helper static classes** — `OrderHelper::calculate(...)`. If it has logic, it is a service or a domain method. If it has no logic, delete it.
- **Mixed layers** — Domain importing `Illuminate`, Infrastructure leaking Eloquent models to Controllers, Application reaching into Blade.

## Behavioral

- **Hidden side effects** — a getter that mutates, a query that dispatches an event, a `toArray()` that hits the database.
- **Anemic domain model** — entity with public setters and no behavior. Either add behavior or demote it to a DTO.
- **Setter-based state transitions** — `$order->status = 'paid'`. Replace with `$order->markAsPaid()` that enforces invariants.
- **Boolean flags as parameters** — `process($order, true, false, true)`. Split into two methods or pass an intent enum.
- **Primitive obsession** — `string $email` everywhere. Wrap in `Email` VO if the string carries rules.

## Framework Misuse

- **Facades inside Domain / Application** — `Cache::get(...)`, `DB::table(...)`, `Auth::user()` must not appear outside UI or Infrastructure.
- **`request()` / `auth()` helpers outside UI** — only controllers and Form Requests may read the request.
- **Business logic in service providers** — providers only bind and configure.
- **Service locator (`app()` / `resolve()`)** — use constructor injection.
- **Eloquent observers for domain events** — observers are fine for framework concerns (logging, cache invalidation). Business rules belong in Domain events raised explicitly from Actions or entities.
- **Mass-assignment without a DTO layer** — never do `Model::create($request->all())`.

## Queries

- **N+1** — accessing a relation in a loop without eager loading. Use `with()`, `load()`, or a `HasMany` constraint.
- **`findAll()` on large tables** — always paginate or chunk.
- **`SELECT *` on wide tables** — list the needed columns; DTO projection for lists.
- **Queries in views** — Blade or Livewire templates must not touch the database.

## Testing

- **Mocking the Domain** — if you are mocking `Order`, the test is asking the wrong question. Instantiate the real entity.
- **Mocking the database** — integration tests hit the real database (SQLite memory or a test PostgreSQL).
- **Shared state between tests** — each test starts with a clean slate (`RefreshDatabase` or transactions).
- **Assertions on side effects via `sleep()`** — use `Bus::fake()`, `Queue::fake()`, `Event::fake()`, `Notification::fake()`.

## Git Hygiene (applies to diffs)

- Unrelated formatting changes mixed with a logic fix.
- "Cleanup" commits that silently change behavior.
- Removing failing tests instead of fixing them.
