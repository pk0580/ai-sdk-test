# 🧠 AI Agent System (Laravel) — пошаговый план реализации

## 🎯 Цель проекта

Построить систему, которая:

* имитирует "мышление" AI-агента (PLAN → ACT → OBSERVE → REFLECT)
* работает через event-driven цикл
* поддерживает multi-agent архитектуру
* демонстрирует RAG (поиск по базе знаний)
* показывает, как оптимизировать latency и дебажить reasoning

---

# 🧩 1. Use cases (что реализуем)

## 1️⃣ RAG: Ответ на вопрос по базе знаний

Пользователь: "Как масштабировать Laravel?"

Агент:

1. Планирует: "нужно найти релевантные документы"
2. Делает: vector search
3. Анализирует результат
4. Генерирует ответ

---

## 2️⃣ Tool Agent: Простой калькулятор

Пользователь: "Сколько будет 25 * 17?"

Агент:

* определяет, что нужен tool
* вызывает calculator

---

## 3️⃣ Multi-agent: Research + Summary

Пользователь: "Собери информацию про scaling Laravel и дай краткие рекомендации"

Агенты:

* ResearchAgent → ищет данные
* SummaryAgent → делает вывод

---

# 🏗️ 2. Общая архитектура

## Компоненты

* AgentKernel (entry point)
* Event Bus (Laravel Events)
* Planner (LLM)
* ToolRegistry
* Memory (Vector DB + context)
* Reflector
* LoopController
* Agents (Research, Summary)

---

# 📁 3. Структура проекта

```
app/
 ├── AI/
 │   ├── Agents/
 │   │   ├── BaseAgent.php
 │   │   ├── ResearchAgent.php
 │   │   ├── SummaryAgent.php
 │   │
 │   ├── Core/
 │   │   ├── AgentKernel.php
 │   │   ├── LoopController.php
 │   │   ├── Planner.php
 │   │   ├── Reflector.php
 │   │
 │   ├── Events/
 │   │   ├── UserMessageReceived.php
 │   │   ├── PlanCreated.php
 │   │   ├── ToolCalled.php
 │   │   ├── ToolResultReceived.php
 │   │   ├── ReflectionGenerated.php
 │   │   ├── StepCompleted.php
 │   │
 │   ├── Listeners/
 │   │   ├── PlanListener.php
 │   │   ├── ExecuteToolListener.php
 │   │   ├── ReflectListener.php
 │   │   ├── LoopListener.php
 │   │
 │   ├── Memory/
 │   │   ├── VectorStore.php
 │   │   ├── ContextMemory.php
 │   │
 │   ├── Tools/
 │   │   ├── ToolInterface.php
 │   │   ├── CalculatorTool.php
 │   │   ├── VectorSearchTool.php
 │   │   ├── ToolRegistry.php
 │   │
 │   ├── DTO/
 │   │   ├── Plan.php
 │   │   ├── Step.php
 │   │   ├── AgentStep.php
 │
 ├── Models/
 │   ├── Document.php
 │
 ├── Jobs/
 │   ├── ExecuteToolJob.php
 │
 ├── Console/
 │   ├── Commands/
 │   │   ├── RunAgentCommand.php
```

---

# 🔁 4. Шаг 1: Event-driven ядро

## Что делаем

Создаем события и listener-цепочку

## Почему

👉 это заменяет while loop и делает систему расширяемой

---

### Flow:

1. UserMessageReceived
2. PlanCreated
3. ToolCalled
4. ToolResultReceived
5. ReflectionGenerated
6. StepCompleted

---

# 🧠 5. Шаг 2: Planner (LLM)

## Задача

Разбить задачу на шаги

## Пример плана

```
{
  "steps": [
    {"tool": "vector_search", "query": "laravel scaling"},
    {"tool": "summarize"}
  ]
}
```

## Почему важно

* уменьшает количество вызовов LLM
* ускоряет выполнение

---

# 🔧 6. Шаг 3: Tool Registry ✓

## Что сделаем

Регистрируем инструменты через Laravel AI SDK (`Laravel\Ai\Contracts\Tool`).

## Особенности
* Инструменты создаются командой `php artisan make:tool`.
* Реализуют контракт `Laravel\Ai\Contracts\Tool`.
* Реестр `ToolRegistry` динамически извлекает метаданные инструментов для планировщика.

## Пример
* `CalculatorTool` (инструмент на базе AI SDK)
* `vector_search` (заготовка для Шага 4)

---

# 🔍 7. Шаг 4: RAG (Vector Search)

## Компоненты

* embeddings
* vector DB
* поиск похожих документов

## Flow

1. query → embedding
2. nearest neighbors
3. возвращаем документы

---

# 🔁 8. Шаг 5: Reflection ✓

## Что делает

* анализирует результат tool через LLM (`App\Ai\Core\Reflector`)
* решает: продолжать (`continue`) или завершить (`finish`)

## Почему важно
* позволяет агенту исправлять ошибки (например, если поиск не дал результатов)
* дает возможность дозапросить данные

## Почему

👉 это и есть "мышление"

---

# 🔄 9. Шаг 6: Loop Controller

## Что делает

* управляет циклом через события
* решает, когда закончить

---

# 🤖 10. Шаг 7: Multi-agent

## Реализация

### Supervisor

* принимает задачу
* выбирает агента

### Agents

* ResearchAgent
* SummaryAgent

---

# ⚡ 11. Шаг 8: Оптимизация

## Latency

* batch planning
* кеширование
* очереди

## Стоимость

* дешевые модели для планирования
* дорогие для финального ответа

---

# 🐞 12. Шаг 9: Debugging

## Логируем каждый шаг

```
Thought
Action
Input
Output
```

## Храним в БД ✓

---

# 🚀 13. MVP план внедрения

1. Event system
2. Planner
3. Calculator tool
4. Vector search tool
5. Reflection ✓
6. Loop ✓
7. Responder Agent ✓
8. Multi-agent ✓
9. Optimization ✓
10. Agent Limits (MaxSteps, MaxTokens, Timeout) ✓
11. Debugging (DB Logs, Latency) ✓

---

# 💡 14. Результат

Ты получаешь:

* систему уровня AutoGPT
* управляемый reasoning
* масштабируемую архитектуру
* понятный debugging

---

# 👉 Дальше можно развить

* streaming reasoning
* UI (timeline шагов)
* self-improving agents
* tool selection через embeddings

---

# 📂 15. Рефакторинг (Именование)

* Весь функционал AI перенесен в пространство имен `App\Ai` (с маленькой 'i') для соответствия стандартам Laravel AI SDK.
* Все вызовы `App\AI\*` заменены на `App\Ai\*`.
* Тесты и сервис-провайдеры обновлены.
