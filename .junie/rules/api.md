# API Design

REST-style HTTP API. Predictable, versioned, paginated, idempotent where it matters.

---

## Versioning

All endpoints under `/api/v1/...`. Never expose `/api/...` without a version. When breaking changes are needed, bump to `/api/v2/...` and run versions in parallel until clients migrate.

## Controller Shape

Thin. One controller per use case at Medium/Complex tier; `Resource\Controller` only at Simple tier.

```php
final class CreateOrderController
{
    public function __invoke(
        CreateOrderRequest $request,
        CreateOrderAction $action,
    ): JsonResponse {
        $orderId = $action->handle(CreateOrderData::fromRequest($request));

        return new JsonResponse(
            data: ['data' => ['id' => $orderId->value]],
            status: Response::HTTP_CREATED,
            headers: ['Location' => route('orders.show', $orderId->value)],
        );
    }
}
```

Controller responsibilities:

1. Validate (delegated to Form Request).
2. Authorize (delegated to Form Request `authorize()`).
3. Build the DTO from the validated request.
4. Invoke the Action.
5. Return a Response or Resource.

## Response Format

Single, consistent envelope.

```json
{
  "data": { "id": "...", "items": [...] },
  "meta": { "page": 1, "per_page": 20, "total": 137 },
  "links": { "next": "/api/v1/orders?page=2" }
}
```

For collection responses, `data` is an array. Use `JsonResource::collection()` and the built-in pagination meta when using API Resources.

## Errors

Stable error code (machine-readable) + human message.

```json
{
  "error": {
    "code": "order_not_found",
    "message": "Order not found",
    "trace_id": "req_01H..."
  }
}
```

Validation (422):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Map exceptions to HTTP statuses globally in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(fn (OrderNotFoundException $e) => response()->json([
        'error' => ['code' => 'order_not_found', 'message' => $e->getMessage()],
    ], 404));
})
```

## Status Codes

- `200` GET / PATCH success with body
- `201` POST that creates a resource (with `Location` header)
- `202` accepted for async processing
- `204` DELETE / PATCH success with no body
- `400` malformed request (rare; usually `422`)
- `401` unauthenticated
- `403` authenticated but not authorized
- `404` resource not found
- `409` conflict (e.g., optimistic lock mismatch)
- `422` validation failure
- `429` rate limited
- `500` unhandled server error

## Pagination

Required for any list endpoint.

```
GET /api/v1/orders?page=1&per_page=20
```

- Default `per_page` if missing (e.g., 20). Cap at 100.
- For very large datasets, prefer cursor pagination: `?cursor=abc123`.

## Filtering and Sorting

Explicit, allow-listed.

```
GET /api/v1/orders?filter[status]=paid&sort=-created_at
```

Use `spatie/laravel-query-builder` to declare allowed filters/sorts safely. Never accept arbitrary column names from the query string.

## Idempotency

Critical write endpoints (`POST /orders`, `POST /payments`) must accept an `Idempotency-Key` header. Server stores the key + response for 24h; replay returns the original response.

See `advanced/idempotency.md`.

## Rate Limiting

Default tier per token + per route in `routes/api.php`:

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () { ... });
```

Stricter limits for write or expensive endpoints.

## Resources / Serialization

Never return Eloquent models directly. Use `JsonResource` or a DTO.

```php
final class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status->value,
            'total'      => ['amount' => $this->total_cents, 'currency' => 'USD'],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

Field names are snake_case in JSON (Laravel default). Be consistent with the rest of the API.

## Documentation

Every endpoint has an OpenAPI entry (Scribe, l5-swagger, or hand-written `openapi.yaml`). Document request, response, errors, and example payloads. PRs touching an endpoint update the spec.
