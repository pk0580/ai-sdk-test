# Clean Architecture Rules

## Use Case

UseCase = один сценарий

Пример:
class CreateOrderUseCase {
public function execute(CreateOrderDTO $dto): Order
}

---

## DTO

DTO:
- Простой объект
- Без логики

---

## Dependency Inversion

❗ Использовать интерфейсы

Domain → interface
Infrastructure → implementation

---

## Testability

- UseCase должен тестироваться без Laravel
- Не использовать Facades