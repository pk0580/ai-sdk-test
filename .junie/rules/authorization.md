# Authorization

Authorize before validating. Check at the HTTP boundary, and again at the Domain boundary if the rule is a business invariant.

---

## Policies

One Policy per aggregate root. Register automatically or in `AuthServiceProvider::$policies`.

```php
final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->customerId->value
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->isVerified();
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order) && $order->status()->canCancel();
    }
}
```

- Return `bool` for simple allow/deny; return `Response::deny('reason')` when the caller benefits from a message.
- Keep policies free of database queries — the Domain entity is passed in with everything needed.

## Gates

Use Gates for cross-aggregate or global checks (`admin-panel-access`). Define in a service provider.

## Form Request `authorize()`

Call the policy early:

```php
public function authorize(): bool
{
    return $this->user()?->can('create', Order::class) ?? false;
}
```

- `authorize()` returning `false` yields a 403 before rules run.
- For per-instance checks: `$this->user()->can('cancel', $order)` — resolve the model first, then check.

## Authorization in Controllers

Only if you skipped Form Request authorize (e.g., no request body). Use `$this->authorize('view', $order)` inside the controller method.

## Authorization in Actions

Avoid reading `Auth::user()` inside an Application Action. Pass the acting user as part of the DTO or command:

```php
final readonly class CancelOrderData
{
    public function __construct(
        public string $orderId,
        public string $actorId,
    ) {}
}
```

The Action can verify that the actor is permitted, but the **decision** still belongs to the Domain (`Order::cancelBy(UserId $actor)`) when the rule is a business rule.

## Roles and Permissions

- `spatie/laravel-permission` for role/permission storage.
- Roles → Permissions → Policies. Policies are where the `if` lives; roles just group permissions.
- Do not check role names in business logic (`if ($user->hasRole('admin'))`). Check permissions (`$user->can('orders.refund')`).

## API Scopes

- Sanctum tokens carry ability scopes: `['orders:read', 'orders:write']`.
- Check with `$user->tokenCan('orders:write')` in middleware or Form Request.

## Layered Defense

1. Route middleware (`auth:sanctum`, `verified`, `throttle`).
2. Form Request `authorize()` (who can invoke this endpoint).
3. Policy (who can act on this resource).
4. Domain invariant (can this action happen at all given current state).

Each layer catches a different failure mode. Skipping any of them usually means the rule is expressed in the wrong place.
