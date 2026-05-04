# Laravel Conventions

Use Laravel's built-in features before reaching for custom abstractions. Laravel is the Infrastructure; it does not belong in Domain or Application.

---

## Container and Dependency Injection

- Use constructor injection for every dependency. Autowiring resolves concrete classes; use `bind()` / `singleton()` in a service provider for interfaces.
- Do not use `app()`, `resolve()`, or facades for service location inside classes (only inside closures that the container evaluates, and only when constructor DI is impossible).
- Bind interfaces in a module-scoped service provider:

```php
final class OrderServiceProvider extends ServiceProvider
{
    public array $bindings = [
        OrderRepository::class => EloquentOrderRepository::class,
    ];
}
```

- Singletons only for stateless services with expensive construction (HTTP clients, SDK wrappers). Never singleton a stateful object.

## Service Providers

- Providers only wire things up. No business logic in `boot()` or `register()`.
- Keep them small and feature-scoped. Prefer `app/Modules/<Mod>/Providers/<Mod>ServiceProvider.php` over a single `AppServiceProvider`.

## Routing

- Use `Route::get()` with invokable controllers where possible: `Route::post('/orders', CreateOrderController::class)`.
- Group by module: `Route::prefix('api/v1')->group(base_path('app/Modules/Order/routes.php'))`.
- Always version APIs: `/api/v1/...`. Never expose unversioned endpoints.

## Facades

- Acceptable in UI (controllers, console commands) and Infrastructure adapters.
- Forbidden in Domain. Avoid in Application — inject `Dispatcher`, `DatabaseManager`, `Repository` (cache) instead.

## Eloquent Models

See `rules/eloquent.md`. Keep models in `Infrastructure`. Models are a persistence shape, not the domain.

## Configuration

- All tunables in `config/*.php`; never hardcode secrets or toggles.
- Access via `config('orders.timeout_seconds')`, not `env()` outside config files.
- `env()` is only read inside `config/*.php` so the config can be cached.

## Queues

- Use `dispatch()` or `Bus::dispatch()` from Application code only when it is the use case's primary effect (e.g., `SendNewsletter` is dispatched from a controller).
- For side effects triggered by a state change, raise a Domain event and listen to it with a Listener that dispatches the Job (`ShouldQueue`).

## Cache

- Cache reads at Infrastructure boundary. Domain must not know about cache.
- Use tagged caches or explicit invalidation on write, never rely on TTL alone for correctness.
- `Cache::flexible(...)` (Laravel 11+) for stale-while-revalidate reads.

## Middleware

- Cross-cutting concerns only: auth, throttling, correlation ID, content negotiation, CSRF.
- Do not put business logic in middleware.

## Console

- Each command is one file, one responsibility. Delegate the work to an Action — the command is a UI adapter over it.
- Schedule in `routes/console.php` or module-scoped `bootstrap/console.php`.

## Localization

- All user-facing strings through `__()` or `trans()`; never hardcoded English text in Blade or API responses.
- Error codes are machine-readable (`order_not_found`), separate from human messages.

## Artisan Generators

Prefer custom generators or the `lunarstorm/laravel-ddd` toolkit for DDD modules when the project adopts it. Otherwise, Laravel's built-in `make:*` commands are fine for Simple and Medium tiers.
