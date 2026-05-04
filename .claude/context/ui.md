# Context: UI Layer

Applies to files inside `app/UI/` (or `app/Http/`, `app/Console/`).

---

## Hard Rules

- Controllers, console commands, Form Requests, API Resources, Blade, Livewire components.
- Depends on Application. May reference Domain only for type hints in DTOs or response mapping.
- Does not contain business logic.

## Controller Responsibilities

1. Validate (delegated to Form Request `rules()`).
2. Authorize (delegated to Form Request `authorize()`).
3. Translate validated input into a DTO.
4. Invoke an Action.
5. Return a Resource, JSON, or view.

## Required Patterns

- Invokable controllers for single-use-case endpoints (`__invoke`).
- Form Request per write endpoint. Never inline `Validator::make()` in controllers.
- API Resources or DTOs for response shape. No raw Eloquent leakage.
- Blade templates do not query the DB.

## Forbidden

- Business rules in controllers.
- `DB::`, `Cache::`, `Mail::` calls inside controllers (that is Infrastructure work, invoked through an Action).
- Returning `Model::find($id)` directly to JSON without a Resource.
- Controllers larger than ~50 lines (split into multiple invokable controllers).

## Console Commands

- Same shape as controllers: parse args, build DTO, call Action.
- Schedule in `routes/console.php`.

## Livewire / Inertia

- Same boundary: components dispatch to Actions; rendering layer has no business rules.
- For Livewire, properties are the DTO; lifecycle methods are thin.

See `rules/api.md`, `rules/validation.md`, `rules/authorization.md`.
