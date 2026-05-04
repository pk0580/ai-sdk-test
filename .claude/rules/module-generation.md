# Module Generation

When creating a new feature at **Medium** or **Complex** complexity, generate the full set of files below. Do not collapse everything into one class.

---

## Example: `Order` module at Complex tier

### Domain (`app/Domain/Order/` or `app/Modules/Order/Domain/`)

```
Order.php                  // Aggregate root
OrderId.php                // Value object
OrderStatus.php            // Enum or VO
Money.php                  // VO, shared
OrderRepository.php        // Interface
Events/
  OrderCreated.php
  OrderPaid.php
Exceptions/
  OrderNotFound.php
  InvalidOrderStatus.php
```

### Application

```
Order/
  CreateOrder/
    CreateOrderAction.php
    CreateOrderData.php
  PayOrder/
    PayOrderAction.php
    PayOrderData.php
  CancelOrder/
    CancelOrderAction.php
    CancelOrderData.php
  Queries/
    GetOrderQuery.php
    GetOrderHandler.php
    OrderView.php          // Read-side DTO
```

### Infrastructure

```
Persistence/Eloquent/
  Models/
    OrderModel.php
    OrderItemModel.php
  Repositories/
    EloquentOrderRepository.php
  Mappers/
    OrderMapper.php        // Eloquent ↔ Domain
Events/
  Listeners/
    SendOrderReceipt.php
```

### UI

```
Http/
  Controllers/
    CreateOrderController.php    // invokable
    PayOrderController.php
    GetOrderController.php
  Requests/
    CreateOrderRequest.php       // extends FormRequest, has authorize() + rules()
    PayOrderRequest.php
  Resources/
    OrderResource.php
Console/Commands/
  (optional artisan commands)
```

### Tests

```
Unit/Domain/Order/
  OrderTest.php
  OrderIdTest.php
  MoneyTest.php
Feature/Order/
  CreateOrderTest.php
  PayOrderTest.php
Integration/Infrastructure/Order/
  EloquentOrderRepositoryTest.php
Architecture/
  OrderArchTest.php
```

---

## Example: `Invoice` module at Medium tier

Skip Domain entities and the repository interface. Use Eloquent directly.

```
Application/Invoice/
  CreateInvoice/
    CreateInvoiceAction.php
    CreateInvoiceData.php
Infrastructure/Persistence/Eloquent/Models/InvoiceModel.php
UI/Http/
  Controllers/CreateInvoiceController.php
  Requests/CreateInvoiceRequest.php
  Resources/InvoiceResource.php
tests/Feature/Invoice/CreateInvoiceTest.php
```

---

## Rules

- A feature is never a single class. Even a simple CRUD needs Model + Request + Controller + Resource + test.
- Create the directories even if a file is empty. Placeholders make intent clear.
- Wire service providers and route declarations in the same change, not later.
- If the module introduces a queue handler or event listener, register it in the same change.
