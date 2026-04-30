# Eloquent

Eloquent is an Infrastructure concern. It is excellent at mapping rows to objects and expressing relations. It is a poor place for business rules.

---

## Placement

- All Eloquent models live in `Infrastructure/Persistence/Eloquent/Models/` (or `app/Models/` for Simple tier).
- Models must not be imported into Domain or Application code in Medium or Complex tiers. Application receives Domain objects or DTOs.

## Model Responsibilities

Allowed:

- `$fillable` / `$guarded`, `$casts`, `$hidden`
- Relations (`hasMany`, `belongsTo`, ...)
- Query scopes (`scopeActive`, `scopePaid`) when they express reusable filters
- Accessors and mutators for trivial presentation (formatting, not validation)
- Factory state methods used in tests

Not allowed:

- Methods that orchestrate use cases (`pay()`, `refund()`) — those belong in the Domain entity or Action
- Direct dispatching of emails, jobs, or external calls
- Validation rules (lives in Form Request or DTO)

## N+1 Prevention

- Use `with()` on index queries. Use `load()` when needed after fetch.
- Enable `Model::preventLazyLoading()` in non-production environments (via `AppServiceProvider::boot()`).
- For lists, prefer `select()` with only the columns you need, or a DTO projection.

```php
// Good
$orders = OrderModel::with(['items:id,order_id,sku,qty'])
    ->where('status', OrderStatus::Paid)
    ->orderByDesc('created_at')
    ->paginate(50);
```

## Large Datasets

- `paginate()` for user-facing lists (max 100 per page).
- `chunkById()` for bulk processing (not `chunk()` when the underlying query may change).
- `lazy()` / `cursor()` for exports and reports.
- Never `get()` without a `limit()` on tables that may exceed a few hundred rows.

## Updates

- Use explicit updates: `$model->fill([...])->save()` or `OrderModel::where(...)->update([...])`.
- `save()` vs `update()` — prefer `save()` after `fill()` so model events fire consistently.
- Mass update without events: `->update([...])` — document why events are being skipped if you choose this path.

## Transactions

```php
DB::transaction(function () use ($data) {
    $order = OrderModel::create([...]);
    $order->items()->createMany([...]);
});
```

- Nested transactions use savepoints; still avoid unless deliberately composing.
- Do not wrap queue dispatch in a transaction. Use `DB::afterCommit(fn () => Bus::dispatch(...))` for write-triggered side effects.

## Relations

- Always type the return: `public function items(): HasMany`.
- Avoid `hasManyThrough` chains that bypass aggregate boundaries — fetch and map in the repository instead.
- Polymorphic relations only when the cost of a flat schema exceeds the cost of the type column + uuid indirection.

## Enums and Casts

- Use native PHP enums with `use Illuminate\Database\Eloquent\Casts\AsStringable`, `AsCollection`, `AsArrayObject`.
- `$casts = ['status' => OrderStatus::class, 'created_at' => 'immutable_datetime']`.

## Querying

- Prefer query builder chain over `DB::raw()`. Use raw only for database-specific functions with a comment explaining the escape hatch.
- Named scopes for reuse; one-off queries inline.
- Repository methods encapsulate cross-table queries; controllers and Actions never write `OrderModel::where(...)->get()` directly in Complex tier.

## Serialization Boundary

Never return an Eloquent model directly to the HTTP response at Medium or Complex tier. Wrap in an `API Resource` or a DTO.
