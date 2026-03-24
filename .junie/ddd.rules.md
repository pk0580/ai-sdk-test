# DDD Rules

## Entities

- Имеют identity
- Содержат бизнес-логику

Пример:
class Order {
public function cancel() {
if ($this->isPaid) {
throw new DomainException();
}
}
}

---

## Value Objects

- Immutable
- Нет ID

Пример:
class Email {
public function __construct(private string $value) {
if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
throw new InvalidArgumentException();
}
}
}

---

## Aggregates

- Один root
- Управляет целостностью

Order → OrderItems

---

## Repositories

- Только интерфейсы в Domain
- Без логики

---

## Domain Services

Использовать, если логика:
- не относится к одной сущности