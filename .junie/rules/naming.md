# Naming

Names should read as intent. A reader who has never seen the file should understand what a class does from its name alone.

---

## Classes

| Kind | Pattern | Example |
|---|---|---|
| Controller (invokable, one action) | `VerbNounController` | `CreateOrderController` |
| Controller (resourceful, CRUD) | `NounController` | `OrderController` |
| Action / Use Case | `VerbNounAction` | `CreateOrderAction`, `CancelSubscriptionAction` |
| Command (intent object) | `VerbNounCommand` | `CreateOrderCommand` |
| Query | `GetNounQuery`, `FindNounQuery`, `ListNounQuery` | `GetOrderQuery` |
| Query Handler | `<Query>Handler` | `GetOrderHandler` |
| DTO (generic) | `NounData` | `CreateOrderData` |
| Read-side DTO | `NounView`, `NounDto` | `OrderView` |
| Form Request | `VerbNounRequest` | `CreateOrderRequest` |
| API Resource | `NounResource` | `OrderResource` |
| Entity (Domain) | `Noun` | `Order` |
| Value Object | `Noun` | `Money`, `Email`, `OrderId` |
| Repository interface (Domain) | `NounRepository` | `OrderRepository` |
| Repository impl (Infrastructure) | `Eloquent<Noun>Repository` | `EloquentOrderRepository` |
| Eloquent model | `NounModel` if conflicting with entity; else `Noun` | `OrderModel` |
| Event (Domain or framework) | `NounPastTense` | `OrderPaid`, `SubscriptionCancelled` |
| Listener | `VerbNounOn<Event>` | `SendReceiptOnOrderPaid` |
| Job | `VerbNounJob` | `ProcessOrderPaymentJob` |
| Policy | `NounPolicy` | `OrderPolicy` |
| Exception | `<What>Exception` | `OrderNotFoundException`, `InvalidOrderStatusException` |
| Enum | `Noun` or `NounType` | `OrderStatus`, `PaymentMethod` |

## Methods

- Entity behavior reads as a business verb: `markAsPaid()`, `cancel()`, `addItem(Item $item)`.
- Query methods on repositories: `findById()`, `findByEmail()`, `getForDashboard()`.
- Actions have one public entry point: `handle(Data $data)` or `__invoke(Data $data)` (pick one style per project).
- Boolean methods: `isPaid()`, `canBeCancelled()`, `hasItems()`. Do not prefix with `get`.

## Variables

- Collections named plurally: `$orders`, `$items`.
- Single items named singularly: `$order`, `$item`.
- Counters: `$count`, `$total`, `$index`.
- Ids: `$orderId`, not `$id` unless context is unambiguous.

## Banned Suffixes

- `Service`, `Manager`, `Helper`, `Util`, `Utils`, `Controller` (for non-HTTP classes), `Handler` (except Query/Command handler), `Processor`

If legacy code uses them, continue the convention within that area but do not introduce new classes with these names.

## Files

One class per file. File name matches class name exactly. PSR-4 autoload path matches namespace.
