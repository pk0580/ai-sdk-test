# Laravel Rules

## Controllers

Контроллер должен:
- принимать Request
- вызывать UseCase
- возвращать Response

❌ Нельзя:
- писать бизнес-логику
- работать с моделями напрямую

---

## Requests

Использовать FormRequest для валидации.

---

## Models (Eloquent)

- Только в Infrastructure
- Не использовать в Domain

---

## Service Container

Использовать bind:

AppServiceProvider:

$this->app->bind(
UserRepositoryInterface::class,
EloquentUserRepository::class
);

---

## Facades

❌ Не использовать в Domain
✔ Можно в Infrastructure

---

## Events / Jobs

- UseCase может диспатчить события
- Тяжёлые операции → Jobs