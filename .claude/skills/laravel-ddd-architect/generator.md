# Laravel Structure Generator

This module generates a real Laravel folder structure based on DDD and Clean Architecture.

---

## Base Structure

app/
├── Domain/
│   └── {{BoundedContext}}/
│       ├── Entity/
│       ├── ValueObject/
│       ├── Repository/
│       ├── Event/
│       └── Exception/
│
├── Application/
│   └── {{BoundedContext}}/
│       ├── UseCase/
│       ├── DTO/
│       ├── Command/
│       └── Query/
│
├── Infrastructure/
│   └── {{BoundedContext}}/
│       ├── Persistence/
│       │   └── Eloquent/
│       ├── Repository/
│       └── Service/
│
├── Interface/
│   └── Http/
│       └── {{BoundedContext}}/
│           ├── Controller/
│           └── Request/

---

## Generation Rules

When generating a feature:

1. ALWAYS create a Bounded Context (if not specified)
2. Place files in correct layer
3. Respect dependency direction
4. Generate namespaces based on folder structure

---

## Example

Feature: Create Order

Generated structure:

app/
├── Domain/Order/
│   ├── Entity/Order.php
│   ├── ValueObject/OrderId.php
│   └── Repository/OrderRepository.php
│
├── Application/Order/
│   ├── DTO/CreateOrderDTO.php
│   ├── Command/CreateOrderCommand.php
│   └── UseCase/CreateOrderUseCase.php
│
├── Infrastructure/Order/
│   └── Repository/EloquentOrderRepository.php
│
└── Interface/Http/Order/
    ├── Controller/CreateOrderController.php
    └── Request/CreateOrderRequest.php

---

## Namespace Rules

Domain:
App\Domain\{{BoundedContext}}\...

Application:
App\Application\{{BoundedContext}}\...

Infrastructure:
App\Infrastructure\{{BoundedContext}}\...

Interface:
App\Interface\Http\{{BoundedContext}}\...

---

## File Mapping Rules

- Entity → Domain/Entity
- ValueObject → Domain/ValueObject
- Repository Interface → Domain/Repository
- UseCase → Application/UseCase
- DTO → Application/DTO
- Command → Application/Command
- Controller → Interface/Http/Controller
- FormRequest → Interface/Http/Request
- Eloquent Repository → Infrastructure/Repository

---

## Advanced Rules

### CQRS

If CQRS is enabled:

Application/
├── Command/
├── Query/

---

### Events

Domain Events:
Domain/Event/

Integration Events:
Infrastructure/Event/

---

### Highload Mode

Add:

Infrastructure/
├── Cache/
├── Queue/
├── ReadModel/

---

## Output Format

When generating code:

1. Show full folder tree
2. Show file paths
3. Generate code per file
4. Ensure namespaces match paths

---

## Example Output

[Folder Tree]

[File: app/Domain/Order/Entity/Order.php]
(code)

[File: app/Application/Order/UseCase/CreateOrderUseCase.php]
(code)

...

---

## Strict Rules

- NEVER mix layers
- NEVER put Eloquent outside Infrastructure
- ALWAYS generate full structure
- ALWAYS follow PSR-4