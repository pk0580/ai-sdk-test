# Laravel Structure Generator

Generates a real Laravel folder structure based on Clean Architecture
and DDD, scaled to the chosen complexity tier.

---

## Base Structures

### Layer-First (Small / Medium projects)

```
app/
├── Domain/
│   └── {BoundedContext}/
│       ├── Entity/
│       ├── ValueObject/
│       ├── Repository/         (interfaces)
│       ├── Event/
│       └── Exception/
│
├── Application/
│   └── {BoundedContext}/
│       ├── UseCase/{Verb}{Noun}/
│       │   ├── {Verb}{Noun}.php
│       │   └── {Verb}{Noun}Data.php
│       ├── DTO/
│       ├── Command/
│       └── Query/
│
├── Infrastructure/
│   └── {BoundedContext}/
│       ├── Persistence/Eloquent/
│       │   ├── Models/
│       │   ├── Repositories/
│       │   └── Mappers/
│       ├── Event/Listener/
│       ├── Job/
│       └── Provider/
│
└── Interface/
    └── Http/
        └── {BoundedContext}/
            ├── Controller/
            ├── Request/
            ├── Resource/
            └── Policy/
```

### Domain-First Modules (Large projects)

```
app/
└── Modules/
    └── {BoundedContext}/
        ├── Domain/
        ├── Application/
        ├── Infrastructure/
        └── UI/
```

---

## Complete Example: `Order` Module (Complex tier)

```
app/
├── Domain/Order/
│   ├── Entity/Order.php
│   ├── ValueObject/OrderId.php
│   ├── ValueObject/OrderStatus.php
│   ├── ValueObject/Money.php
│   ├── Repository/OrderRepository.php
│   ├── Event/OrderCreated.php
│   ├── Event/OrderPaid.php
│   └── Exception/InvalidOrderStatusException.php
│
├── Application/Order/
│   ├── UseCase/CreateOrder/
│   │   ├── CreateOrder.php
│   │   └── CreateOrderData.php
│   ├── UseCase/PayOrder/
│   │   ├── PayOrder.php
│   │   └── PayOrderData.php
│   └── Query/GetOrderDashboard/
│       ├── GetOrderDashboardQuery.php
│       ├── GetOrderDashboardHandler.php
│       └── DashboardView.php
│
├── Infrastructure/Order/
│   ├── Persistence/Eloquent/Models/OrderModel.php
│   ├── Persistence/Eloquent/Repositories/EloquentOrderRepository.php
│   ├── Persistence/Eloquent/Mappers/OrderMapper.php
│   ├── Event/Listener/SendReceiptOnOrderPaid.php
│   ├── Job/ProcessOrderPaymentJob.php
│   └── Provider/OrderServiceProvider.php
│
└── Interface/Http/Order/
    ├── Controller/CreateOrderController.php
    ├── Controller/PayOrderController.php
    ├── Request/CreateOrderRequest.php
    ├── Request/PayOrderRequest.php
    ├── Resource/OrderResource.php
    └── Policy/OrderPolicy.php

tests/
├── Unit/Domain/Order/OrderTest.php
├── Unit/Domain/Order/MoneyTest.php
├── Feature/Order/CreateOrderTest.php
├── Integration/Infrastructure/Order/EloquentOrderRepositoryTest.php
└── Architecture/OrderArchTest.php
```

---

## Namespace Rules

| Layer | Namespace |
|---|---|
| Domain | `App\Domain\{BoundedContext}\...` |
| Application | `App\Application\{BoundedContext}\...` |
| Infrastructure | `App\Infrastructure\{BoundedContext}\...` |
| Interface | `App\Interface\Http\{BoundedContext}\...` |
| Module variant | `App\Modules\{BoundedContext}\{Layer}\...` |

PSR-4 autoload path matches namespace exactly.

---

## File Mapping

| Concept | Location |
|---|---|
| Entity / Aggregate | `Domain/{Ctx}/Entity/` |
| Value Object | `Domain/{Ctx}/ValueObject/` |
| Repository interface | `Domain/{Ctx}/Repository/` |
| Domain Event | `Domain/{Ctx}/Event/` |
| Domain Exception | `Domain/{Ctx}/Exception/` |
| UseCase / Action | `Application/{Ctx}/UseCase/{Verb}{Noun}/` |
| DTO | `Application/{Ctx}/DTO/` (or co-located with use case) |
| Command / Query | `Application/{Ctx}/Command|Query/` |
| Eloquent Model | `Infrastructure/{Ctx}/Persistence/Eloquent/Models/` |
| Eloquent Repository | `Infrastructure/{Ctx}/Persistence/Eloquent/Repositories/` |
| Mapper | `Infrastructure/{Ctx}/Persistence/Eloquent/Mappers/` |
| Listener | `Infrastructure/{Ctx}/Event/Listener/` |
| Job | `Infrastructure/{Ctx}/Job/` |
| Service Provider | `Infrastructure/{Ctx}/Provider/` |
| Controller | `Interface/Http/{Ctx}/Controller/` |
| Form Request | `Interface/Http/{Ctx}/Request/` |
| API Resource | `Interface/Http/{Ctx}/Resource/` |
| Policy | `Interface/Http/{Ctx}/Policy/` |

---

## CQRS-Lite Add-Ons

```
Application/{Ctx}/
├── Query/{Noun}/
│   ├── Get{Noun}Query.php
│   ├── Get{Noun}Handler.php
│   └── {Noun}View.php
└── ReadModel/   (optional, if read-side needs separate models)

Infrastructure/{Ctx}/Persistence/Eloquent/Repositories/
└── Eloquent{Noun}ReadRepository.php
```

---

## High-Load Add-Ons

```
Infrastructure/{Ctx}/
├── Cache/
├── Queue/
├── Outbox/
│   ├── OutboxMessage.php           (Eloquent model)
│   └── PublishOutboxJob.php
└── ReadModel/
```

---

## Output Format

For Complex features, the response shows:

1. **Folder tree** (text, scoped to the new files)
2. **File-by-file generation**, each with the full path on its own
   line above the code block
3. **Service provider binding** snippet
4. **Route registration** snippet
5. **Test names** to be written

---

## Strict Rules

- NEVER mix layers
- NEVER put Eloquent outside Infrastructure (Medium / Complex tier)
- NEVER reach into another module's Domain — go through public Actions
  or events
- ALWAYS follow PSR-4
- ALWAYS use `final` and `declare(strict_types=1);`
- ALWAYS provide a Mapper between Eloquent and Domain in Complex tier
