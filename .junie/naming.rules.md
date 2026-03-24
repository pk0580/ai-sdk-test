# Naming Rules

## Общие правила
- Имена должны отражать бизнес-смысл
- Не использовать сокращения

---

## Классы

### Entities
User
Order
Payment

### Value Objects
Email
Money
OrderId

---

### Use Cases
CreateOrderUseCase
CancelOrderUseCase
GetUserProfileUseCase

---

### Repository Interfaces
UserRepositoryInterface
OrderRepositoryInterface

---

### Реализации
EloquentUserRepository

---

### DTO
CreateOrderDTO

---

### Controllers
CreateOrderController

---

## Методы

- create()
- update()
- delete()
- findById()
- save()

UseCase:
- execute()