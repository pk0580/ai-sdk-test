# Validation

Two layers: HTTP shape (Form Request) and Domain invariants (entity constructors).

---

## Form Request

Validates the HTTP shape. Rejects requests with 422 before any Action runs.

```php
final class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id'        => ['required', 'uuid', 'exists:customers,id'],
            'items'              => ['required', 'array', 'min:1', 'max:100'],
            'items.*.sku'        => ['required', 'string', 'regex:/^[A-Z0-9-]+$/'],
            'items.*.qty'        => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.price_cents'=> ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.min' => 'An order must contain at least one item.',
        ];
    }
}
```

Rules:

- Put authorization in `authorize()`. If the check fails there, Laravel returns 403 before validation runs, so non-authorized users do not get 422.
- Validate every field. Use `prohibited` or `Rule::excludeIf` to block unexpected fields explicitly.
- Use `Rule::unique(...)->ignore(...)` for updates.
- `exists` checks are for referential integrity, not auth — do not use them to hide not-found vs forbidden.

## Domain Validation

The entity constructor and methods enforce invariants that must hold regardless of how the data arrived. See `rules/domain.md`.

```php
new Email($value);              // throws on bad format
$order->markAsPaid();           // throws if status is wrong
```

Domain exceptions map to 4xx/5xx at the HTTP boundary via a global handler (`bootstrap/app.php` exception map).

## DTO Validation

If using `spatie/laravel-data`, attribute-based rules on the DTO merge nicely with Form Request rules:

```php
final readonly class CreateOrderData extends Data
{
    public function __construct(
        #[Uuid, Exists('customers', 'id')]
        public string $customerId,
        /** @var DataCollection<int, CreateOrderItemData> */
        #[DataCollectionOf(CreateOrderItemData::class)]
        public array $items,
    ) {}
}
```

Choose one approach per project. Do not validate the same thing in both places.

## Error Response Shape

422 body:

```json
{
  "message": "The items must contain at least 1 items.",
  "errors": {
    "items": ["The items must contain at least 1 items."]
  }
}
```

Custom exceptions (business failures) map to 4xx with a stable error code:

```json
{
  "error": {
    "code": "invalid_order_status",
    "message": "Only confirmed orders can be paid."
  }
}
```

## What Not to Do

- Inline validation inside controllers (`if (!$request->input('...')) ...`). Use Form Requests.
- `Validator::make(...)` inside an Action. If an Action needs validation, its caller should have run a Form Request or DTO.
- Domain objects silently accepting invalid input. Throw, loudly.
