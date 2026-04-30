# Project Structure

Two valid layouts. Choose based on project size.

---

## Small / Medium Projects — Layer-First

```
app/
├── Domain/
│   ├── Order/
│   │   ├── Order.php
│   │   ├── OrderId.php
│   │   ├── OrderStatus.php
│   │   ├── OrderRepository.php        // interface
│   │   └── Events/OrderPaid.php
│   └── Customer/
├── Application/
│   ├── Order/
│   │   ├── CreateOrder/
│   │   │   ├── CreateOrderAction.php
│   │   │   └── CreateOrderData.php    // DTO
│   │   └── CancelOrder/
│   └── Shared/
├── Infrastructure/
│   ├── Persistence/
│   │   └── Eloquent/
│   │       ├── Models/OrderModel.php
│   │       └── Repositories/EloquentOrderRepository.php
│   ├── Mail/
│   └── Http/Clients/
└── UI/
    ├── Http/
    │   ├── Controllers/OrderController.php
    │   ├── Requests/CreateOrderRequest.php
    │   └── Resources/OrderResource.php
    └── Console/Commands/
```

---

## Large Projects — Domain-First (Modules)

Each module is a bounded context. Cross-module access goes through public Actions or events, not by reaching into another module's Domain.

```
app/
└── Modules/
    ├── Order/
    │   ├── Domain/
    │   ├── Application/
    │   ├── Infrastructure/
    │   └── UI/
    ├── Billing/
    │   ├── Domain/
    │   ├── Application/
    │   ├── Infrastructure/
    │   └── UI/
    └── Catalog/
        ├── Domain/
        ├── Application/
        ├── Infrastructure/
        └── UI/
```

Routing, service providers, migrations, and tests can live inside each module or at the app root depending on team preference; keep it consistent per project.

---

## Dependency Flow

```
HTTP Request
    ↓
UI\Http\Controllers\*Controller
    ↓ (validated DTO)
Application\*\*Action
    ↓ (domain calls)
Domain\*\*Entity / *Repository
    ↑ (implementation)
Infrastructure\Persistence\Eloquent\Repositories\Eloquent*Repository
    ↓
Database
```

---

## Naming Per Layer

| Layer | Suffixes / forms |
|---|---|
| Domain | `Order`, `OrderId`, `Money`, `OrderRepository` (interface) |
| Application | `CreateOrderAction`, `CreateOrderData`, `GetOrderQuery`, `OrderDto` |
| Infrastructure | `OrderModel`, `EloquentOrderRepository`, `StripePaymentGateway` |
| UI | `OrderController`, `CreateOrderRequest`, `OrderResource` |

Do not name anything `OrderManager`, `OrderHelper`, `OrderUtil`, or `OrderService` unless the project's legacy code already uses the suffix and renaming is out of scope.

---

## DTO Placement

DTOs belong in **Application** or **UI**. Never in Domain. A Domain VO is not a DTO; it expresses a concept (`Money`, `Email`), not a transport shape.

---

## Test Layout

Mirror the production layout under `tests/`:

```
tests/
├── Unit/Domain/Order/OrderTest.php
├── Feature/Order/CreateOrderTest.php
└── Integration/Infrastructure/Persistence/EloquentOrderRepositoryTest.php
```
