# Architecture

Clean Architecture, four layers, strict dependency direction.

```
UI  →  Application  →  Domain
          ↑
  Infrastructure (implements interfaces from Domain / Application)
```

- **Domain** does not know about any other layer.
- **Application** depends only on Domain.
- **Infrastructure** depends on Application and Domain to implement their interfaces.
- **UI** depends on Application. UI must not import from Domain except when presenting a domain value (e.g., rendering a `Money` VO).

A dependency violation is a bug. Architecture tests enforce this.

---

## Layer Responsibilities

### Domain

- Entities with behavior and invariants.
- Value objects (immutable, equality by value).
- Domain services for logic that does not naturally belong to an entity.
- Domain events.
- Repository **interfaces** only.

Must not import:

- `Illuminate\*`, `Eloquent`, `Http`, `Cache`, `Queue`, `Event`, `Log`, `DB`
- Symfony Messenger, Doctrine
- Any transport, serialization, or framework concern

### Application

- Use cases, implemented as Actions or Command/Query handlers.
- DTOs for input and output.
- Orchestration and transactions (wrap writes in `DB::transaction()` *inside* Infrastructure adapters, not in Domain; the Action calls the adapter).
- Interfaces for infrastructure ports (e.g., `PaymentGateway`, `MailSender`) when the project is large enough to justify them; otherwise inject concrete Infrastructure classes.

Must not:

- Hold business rules that belong in Domain
- Contain persistence code
- Return Eloquent models

### Infrastructure

- Eloquent models, Eloquent-backed repositories, query builders.
- HTTP clients, queue drivers, cache, mail, storage.
- Third-party SDK adapters.
- Implements interfaces declared in Domain or Application.

Infrastructure classes map between framework objects (e.g., `User` Eloquent model) and Domain objects (`User` entity). Never leak Eloquent outside Infrastructure.

### UI

- HTTP controllers (invokable, one use case per controller for non-trivial features).
- Console commands.
- Form Requests (validation + `authorize()`).
- API Resources or Response DTOs.
- Blade templates, Livewire components.

Controllers must be thin: validate → build DTO → invoke Action → return response.

---

## When to Skip a Layer

For **Simple** complexity (see `rules/decision.md`):

- Skip Domain entities; use Eloquent directly in Infrastructure with a thin Form Request + Controller + API Resource.
- Skip the repository interface; inject the Eloquent model or a query class directly.

For **Medium**:

- Keep Domain minimal or absent; put rules in the Action.
- Use Eloquent models as the persistence shape.
- DTO at the Application boundary.

Do not force four layers onto a 20-line feature.
