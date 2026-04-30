# Services, Actions, Use Cases

Avoid generic services. Actions and use cases scale better.

---

## Why Not `OrderService`?

A `UserService` grows into `register`, `update`, `suspend`, `changePassword`, `verifyEmail`, `resendWelcome`, `archive`, `restore`, `exportCsv`. After a year it has 40 methods and five reasons to change.

The industry has moved toward **single-operation classes**. Laravel calls them Actions. DDD calls them Use Cases or Command Handlers. Same shape:

- One class, one operation.
- One public method.
- Intent in the class name: `RegisterUserAction`, `SuspendAccountAction`.
- Composable: one Action can call another.

---

## Action vs Service — When Each Is Acceptable

| Prefer Action when | Prefer Service when |
|---|---|
| Use case is a single transaction (`CancelOrder`) | Library-style utility with multiple pure helpers (`Currency::format`) |
| Must be testable in isolation | Stateless formatter or mapper |
| Triggers side effects (events, jobs, mail) | Pure calculation with no I/O |

"Services" that are really stateless libraries (`MoneyFormatter`, `SlugGenerator`) are fine — they just should not contain use cases.

## Command / Handler

Larger systems formalize the pattern:

```php
final readonly class CreateOrderCommand
{
    public function __construct(
        public string $customerId,
        public array $items,   // list<CreateOrderItemCommand>
    ) {}
}

final readonly class CreateOrderHandler
{
    public function __construct(private OrderRepository $orders) {}

    public function handle(CreateOrderCommand $command): OrderId
    {
        // ...
    }
}
```

Adopt Command/Handler when you want:

- A bus that dispatches through middleware (logging, auth, retries).
- Serializable commands (async, outbox pattern).
- A strict distinction between reads (Query/Handler) and writes.

Otherwise, Action + DTO is lighter and equally clear.

---

## Composition

```php
final readonly class RegisterUserAction
{
    public function __construct(
        private CreateUserAction $createUser,
        private SendWelcomeEmailAction $sendWelcome,
    ) {}

    public function handle(RegisterUserData $data): User
    {
        $user = $this->createUser->handle($data);
        $this->sendWelcome->handle(new SendWelcomeEmailData($user->id));
        return $user;
    }
}
```

Actions composing Actions is fine. Avoid a root Action that orchestrates ten sub-Actions — that is a workflow, consider Saga or a dedicated Application service (still named with intent: `UserOnboardingWorkflow`).

## Where Actions Live

- Simple tier: `app/Actions/<Noun>/<Verb><Noun>Action.php`
- Medium / Complex tier: `app/Application/<Module>/<Verb><Noun>/<Verb><Noun>Action.php`

## Banned

- `UserService`, `OrderService`, `PaymentService`, `Manager`, `Helper`, `Util`.
- Grouping operations by entity instead of by intent.
- Static methods holding state.
