# Claude Configuration — Laravel DDD

Самодостаточная конфигурация Claude Code для проектов
Laravel 12 / PHP 8.4 с Clean Architecture, DDD, CQRS-lite и
high-load паттернами.

```
claude/
├── CLAUDE.md                      Корневые инструкции (entry point)
├── README.md                      Этот файл
├── settings.json                  Хуки, модель, права, env
├── settings.local.json            Локальные переопределения для машины
│
├── rules/                         27 тематических файлов (architecture, eloquent, …)
├── context/                       Cheat sheets по слоям (4 файла)
├── advanced/                      idempotency, outbox, concurrency, cqrs, resilience
│
├── skills/
│   └── laravel-ddd-architect/     Skill + DSL + генератор + 17 stub-ов
│
├── agents/
│   ├── ddd-reviewer.md
│   ├── code-reviewer.md
│   ├── perf-auditor.md
│   ├── security-auditor.md
│   ├── module-scaffolder.md
│   ├── pest-writer.md
│   └── phpunit-writer.md
│
└── hooks/
    ├── php-postwrite.sh           Pint + php -l после каждого Write/Edit
    ├── phpstan-stop.sh            PHPStan analyse по событию Stop
    ├── test-stop.sh               Прогон Pest/PHPUnit по событию Stop
    └── secret-guard.sh            Блокирует запись секретов и .env-файлов
```

---

## Быстрый старт

1. **Подстроить `settings.json` под окружение**:

   - `CLAUDE_PHP_CONTAINER` — имя PHP-контейнера (например, `shop_php`).
     Оставить пустым, чтобы запускать на хосте.
   - `CLAUDE_SRC_PREFIX` — префикс пути на хосте, маппящийся
     в `CLAUDE_CONTAINER_ROOT` внутри контейнера.
   - `CLAUDE_PHPSTAN_LEVEL`, `CLAUDE_PHPSTAN_MEMORY` — настройка PHPStan.
   - `CLAUDE_SKIP_TESTS=1` в `settings.local.json`, чтобы пропускать
     прогон тестов на Stop во время черновой работы.

2. **Запускать skill** упоминанием в диалоге, например:
   «используй skill laravel-ddd-architect, чтобы заскаффолдить модуль
   Order».

3. **Использовать агентов проактивно** по их описанию:

   - «прогони diff через `code-reviewer`»
   - «заскаффолди модуль Billing через `module-scaffolder`»
   - «проверь новые Eloquent-запросы через `perf-auditor`»

---

## Слои правил

Минимальный набор, который Claude подтягивает на любую задачу:
`workflow`, `decision`, `architecture`, `naming`, `anti-patterns`,
плюс файл слоя, к которому относится изменение
(`domain` / `application` / `infrastructure` / `ui`).

Тематические правила в `rules/` и cheat sheets в `context/`
ссылаются из `CLAUDE.md`. Файлы `advanced/` подгружаются только
когда задача затрагивает concurrency, idempotency, outbox, CQRS
или resilience.

---

## Режимы и формат ответа

Каждый ответ с кодом начинается со строки-заголовка:

```
[FEATURE | FIX | REFACTOR | TEST] [SIMPLE | MEDIUM | COMPLEX] [CRUD | Action+DTO | DDD]
```

Секции (опускаются, если неприменимы):

```
--- IMPLEMENTATION ---
--- SELF-REVIEW ---     (≤5 пунктов или OK)
--- FIX ---             (только если SELF-REVIEW нашёл проблемы)
--- QA ---              (добавленные/изменённые тесты)
```

См. `rules/output.md` и `rules/workflow.md`.

---

## Хуки

| Событие | Хук | Что делает |
|---|---|---|
| `PreToolUse` (`Write|Edit`) | `secret-guard.sh` | Блокирует файлы и содержимое, попадающие под известные паттерны секретов. |
| `PostToolUse` (`Write|Edit`) | `php-postwrite.sh` | Прогоняет Pint + `php -l` (внутри настроенного контейнера, иначе на хосте). |
| `Stop` | `phpstan-stop.sh` | Запускает PHPStan / Larastan, если в проекте есть `phpstan.neon*`. |
| `Stop` | `test-stop.sh` | Запускает Pest, иначе PHPUnit, иначе `php artisan test` — что найдёт. |

Хуки на Stop блокируют завершение хода при ошибке, чтобы Claude
сначала починил проблемы и только потом отчитался о готовности.

---

## Источники

Конфиг консолидирует и обновляет:

- `.claude/CLAUDE.md`, `.claude/skills/laravel-ddd-architect/`,
  `.claude/agents/`, `.claude/hooks/` — исходный конфиг проекта
  (Laravel 13 / PHP 8.3, технический layout DDD).
- `.junie/guidelines.md`, `.junie/index.md`, `.junie/rules/`,
  `.junie/context/`, `.junie/advanced/` — гайдлайны JetBrains Junie
  (Laravel 12 / PHP 8.4, complexity-based decisions, полный каталог
  правил).

Объединённый результат целится в Laravel 12 / PHP 8.4 / Pest 4 /
PHPUnit 12, применяет complexity-based-решения и поддерживает оба
layout-а — layer-first и module-first.
