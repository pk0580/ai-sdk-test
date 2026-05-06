# Claude Code — Инструкция по работе с проектом

Laravel 13 / PHP 8.4, Clean Architecture, DDD, CQRS-lite.
Docker-only workflow в WSL2.

---

## 1. Требования

| Зависимость | Где нужна |
|---|---|
| WSL2 Ubuntu | хост |
| `bash`, `docker` CLI | хост |
| Docker-контейнер с PHP 8.4 + Laravel | запущен при работе с Claude |

---

## 2. Первоначальная настройка

Создай `.claude/settings.local.json` (не коммитится — добавь в `.gitignore`):

```json
{
  "permissions": { "mode": "bypassPermissions" },
  "env": {
    "CLAUDE_PHP_CONTAINER": "имя_контейнера"
  }
}
```

Имя контейнера — вывод `docker ps --format '{{.Names}}'`.

**Опциональные переменные** (можно добавить в тот же файл):

| Переменная | По умолчанию | Описание |
|---|---|---|
| `CLAUDE_SRC_PREFIX` | `<project_root>/src/` | Путь на хосте (в формате Unix/WSL, например `/mnt/c/...`), маппится в контейнер (дефолт вычисляется хуком) |
| `CLAUDE_CONTAINER_ROOT` | `/var/www/html` | Рабочая директория внутри контейнера |
| `CLAUDE_PHPSTAN_LEVEL` | `8` | Уровень PHPStan (применяется только если не задан в `phpstan.neon`) |
| `CLAUDE_PHPSTAN_MEMORY` | `512M` | Лимит памяти PHPStan |

Переменные `CLAUDE_CONTAINER_ROOT`, `CLAUDE_PHPSTAN_LEVEL`, `CLAUDE_PHPSTAN_MEMORY` заданы глобально в `.claude/settings.json`.
`CLAUDE_SRC_PREFIX` — только в `settings.local.json` (или вообще не нужна — хук вычисляет дефолт сам). **Важно:** путь должен быть в формате Unix/WSL (например, `/mnt/c/projects/...`).

---

## 3. Автоматические хуки

Запускаются без участия пользователя при каждом вызове инструментов Write/Edit/Bash:

| Событие | Хук | Когда | Действие |
|---|---|---|---|
| `PreToolUse` (Write / Edit / Bash) | `secret-guard.sh` | Всегда | Блокирует запись `.env*`, ключей API (AKIA…, sk_live_…, ghp_…, RSA-ключей) |
| `PostToolUse` (Write / Edit) | `php-postwrite.sh` | Только для `*.php`-файлов внутри `${CLAUDE_SRC_PREFIX}` | Запускает Pint + `php -l` внутри контейнера |

> Хук `php-postwrite.sh` срабатывает на Write/Edit любого файла, но **сам** фильтрует: пропускает всё кроме `*.php` и файлов вне `${CLAUDE_SRC_PREFIX}`.

Если контейнер не запущен — `php-postwrite.sh` молча завершается (не блокирует).

---

## 4. Слэш-команды (ручной запуск)

```
/phpstan                        # PHPStan по всему проекту
/phpstan --paths=src/app/Domain # PHPStan только для Domain
/test                           # весь тест-сьют
/test --filter=OrderTest        # только тесты с именем OrderTest
/composer-audit                 # проверка CVE в зависимостях composer
```

Все три команды:
- проверяют, что `CLAUDE_PHP_CONTAINER` задан и контейнер запущен;
- выполняют нужный бинарь внутри контейнера через `docker exec`.

`/test` автодетектит runner: Pest → PHPUnit → `php artisan test`.
`/phpstan` пропускает запуск, если нет `phpstan.neon` или `vendor/bin/phpstan`.
`/composer-audit` выводит CVE-список и предлагает план, не апгрейдит автоматически.

---

## 5. Режимы работы Claude

Режим определяется по запросу автоматически:

| Режим | Ключевые слова | Пайплайн |
|---|---|---|
| `FEATURE` | add, implement, build, create | ARCHITECT → IMPLEMENT → SELF-REVIEW → QA |
| `FIX` | bug, broken, fails, wrong, crash | REPRODUCE → IMPLEMENT → SELF-REVIEW |
| `REFACTOR` | refactor, clean up, extract | PLAN → IMPLEMENT → SELF-REVIEW |
| `TEST` | add tests, cover, missing tests | QA only |

Каждый ответ с кодом начинается заголовком `[MODE] [COMPLEXITY] [ARCH]`.
Self-review loop — не более 3 итераций, останавливается на `OK`.

---

## 6. Тиры сложности

| Тир | Когда | Что генерируется |
|---|---|---|
| **Simple** (CRUD) | 0–2 правила бизнес-логики | Model + FormRequest + Controller + Resource + Feature test |
| **Medium** (Action+DTO) | 2–3 правила, side effects, multi-table tx | Action + DTO + FormRequest + Eloquent + Controller + Resource + Feature test |
| **Complex** (DDD) | >3 правил, state machine, инварианты | Aggregate + VOs + Repository interface + Eloquent repo + Mapper + Action + DTO + FormRequest + Controller + Resource + Tests |

---

## 7. Skill: `laravel-ddd-architect`

Упомяни skill в запросе:

```
Используй skill laravel-ddd-architect, создай модуль Order — Complex тир.
```

Поддерживает DSL:

```
aggregate Order {
    id:     OrderId
    status: OrderStatus

    behavior:
        create(customerId: CustomerId)
        addItem(sku: Sku, qty: int, price: Money)
        markAsPaid()
        cancel(reason: string)

    invariants:
        items.size > 0 when markAsPaid
        status == Draft when addItem

    events:
        OrderCreated(orderId, customerId)
        OrderPaid(orderId, paidAt)
}
```

Skill генерирует полную структуру (Entity, VOs, Repository interface + impl, Mapper, Action, DTO, Controller, FormRequest, Resource, Policy, ServiceProvider, Migration, Tests) и прописывает provider в `bootstrap/providers.php` и маршрут в `routes/api.php`.

---

## 8. Агенты

Агенты из `.claude/agents/` вызываются **проактивно** (Claude запускает сам по контексту задачи) или явно по имени в запросе.

| Агент | Когда вызывается проактивно |
|---|---|
| `ddd-reviewer` | После создания/изменения файлов в `Domain/`, `Application/`, `Infrastructure/`, `Interface/` |
| `code-reviewer` | После multi-file changeset, перед открытием PR |
| `perf-auditor` | После изменений Eloquent-запросов, репозиториев, контроллеров на hot path |
| `security-auditor` | После изменений FormRequest, контроллеров, конфигурации, маршрутов |
| `module-scaffolder` | По запросу «создай новый модуль / bounded context» |
| `test-writer` | После создания новых классов без тестов под `src/app/Domain/`, `src/app/Application/`, `src/app/Infrastructure/` |

Явный вызов в запросе:
```
Прогони diff через code-reviewer.
Проверь новые Eloquent-запросы через perf-auditor.
```

---

## 9. Архитектура проекта

```
src/app/
├── Domain/{Ctx}/
│   ├── Entity/          # Aggregate roots, entities
│   ├── ValueObject/     # Immutable readonly classes
│   ├── Repository/      # Interfaces only
│   ├── Event/           # Past-tense domain events
│   └── Exception/       # Named after violated invariant
│
├── Application/{Ctx}/
│   └── UseCase/{Verb}{Noun}/
│       ├── {Verb}{Noun}Action.php   # readonly class, handle()
│       └── {Verb}{Noun}Data.php     # readonly DTO
│
├── Infrastructure/{Ctx}/
│   ├── Persistence/Eloquent/
│   │   ├── Models/
│   │   ├── Repositories/   # Eloquent{Name}Repository
│   │   └── Mappers/
│   ├── Event/Listener/
│   ├── Job/
│   └── Provider/
│
└── Interface/Http/{Ctx}/
    ├── Controller/      # Invokable, thin
    ├── Request/         # FormRequest с authorize() + rules()
    ├── Resource/        # JsonResource
    └── Policy/

src/tests/
├── Unit/Domain/         # Pure PHP, no framework boot
├── Feature/{Ctx}/       # HTTP endpoints, full container
├── Integration/         # Real DB, repository round-trips
└── Architecture/        # arch() layer boundary rules
```

**Namespaces:** `App\Domain\`, `App\Application\`, `App\Infrastructure\`, `App\Interface\Http\`.

**Dependency rule:** `App\Interface` → `App\Application` → `App\Domain` ← `App\Infrastructure`.

---

## 10. Нейминг

| Артефакт | Пример |
|---|---|
| Entity / Aggregate | `Order` |
| Value Object | `OrderId`, `Money`, `OrderStatus` |
| Repository interface | `OrderRepository` |
| Repository impl | `EloquentOrderRepository` |
| Action (Medium) | `CreateOrderAction` |
| UseCase (Complex) | `CreateOrderAction` |
| DTO | `CreateOrderData` |
| Domain Event | `OrderPaid`, `OrderCancelled` |
| Controller | `CreateOrderController` |
| FormRequest | `CreateOrderRequest` |
| Resource | `OrderResource` |
| Listener | `SendReceiptOnOrderPaid` |
| Job | `ProcessOrderPaymentJob` |

**Запрещённые суффиксы:** `Manager`, `Helper`, `Util`, `Processor`, `Service` (как use case).

---

## 11. Troubleshooting

**Хуки не срабатывают (Pint/php -l не запускается)**
→ Проверь `CLAUDE_PHP_CONTAINER` в `settings.local.json` и что контейнер запущен (`docker ps`).

**`/phpstan` говорит «не настроен»**
→ Нужен файл `src/phpstan.neon` или `src/phpstan.neon.dist` + `vendor/bin/phpstan` (установи `composer require --dev phpstan/phpstan` или `larastan/larastan`).

**`/test` говорит «runner не найден»**
→ Нужен `vendor/bin/pest` (Pest) или `vendor/bin/phpunit` (PHPUnit) в `src/vendor/bin/`.

**`secret-guard.sh` блокирует легитимный файл**
→ Не записывай напрямую в `.env*`. Используй `.env.example` как шаблон.

**`bypassPermissions` нет в settings.json**
→ Это намеренно: `bypassPermissions` лежит в `settings.local.json` (не коммитится). Каждый разработчик добавляет его сам после клонирования.
