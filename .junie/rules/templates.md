# Templates

Pick the template set matching the complexity tier (`rules/decision.md`).

---

## Simple — CRUD

Eloquent + Form Request + Controller + API Resource.

```php
// app/Models/Customer.php
final class Customer extends Model
{
    protected $fillable = ['name', 'email'];
}

// app/Http/Requests/CreateCustomerRequest.php
final class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:customers,email'],
        ];
    }
}

// app/Http/Controllers/CustomerController.php
final class CustomerController
{
    public function store(CreateCustomerRequest $request): CustomerResource
    {
        return CustomerResource::make(
            Customer::create($request->validated()),
        );
    }
}

// app/Http/Resources/CustomerResource.php
final class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email];
    }
}
```

---

## Medium — Action + DTO

Action encapsulates the use case. DTO at the Application boundary. No repository interface yet.

```php
// app/Application/Customer/CreateCustomer/CreateCustomerData.php
final readonly class CreateCustomerData
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    public static function fromRequest(CreateCustomerRequest $request): self
    {
        return new self(
            name:  $request->validated('name'),
            email: $request->validated('email'),
        );
    }
}

// app/Application/Customer/CreateCustomer/CreateCustomerAction.php
final readonly class CreateCustomerAction
{
    public function __construct(
        private DB $db,
        private Dispatcher $events,
    ) {}

    public function handle(CreateCustomerData $data): Customer
    {
        return $this->db::transaction(function () use ($data) {
            $customer = Customer::create([
                'name'  => $data->name,
                'email' => $data->email,
            ]);

            $this->events->dispatch(new CustomerRegistered($customer->id));

            return $customer;
        });
    }
}

// app/UI/Http/Controllers/CreateCustomerController.php
final class CreateCustomerController
{
    public function __invoke(
        CreateCustomerRequest $request,
        CreateCustomerAction $action,
    ): CustomerResource {
        return CustomerResource::make(
            $action->handle(CreateCustomerData::fromRequest($request)),
        );
    }
}
```

---

## Complex — DDD (Aggregate + Repository + Action)

```php
// app/Domain/Order/OrderId.php
final readonly class OrderId
{
    public function __construct(public string $value)
    {
        if (!Str::isUuid($value)) {
            throw new InvalidArgumentException('OrderId must be a UUID');
        }
    }

    public static function generate(): self
    {
        return new self((string) Str::uuid());
    }
}

// app/Domain/Order/Order.php
final class Order
{
    /** @var list<OrderItem> */
    private array $items = [];

    private function __construct(
        public readonly OrderId $id,
        public readonly CustomerId $customerId,
        private OrderStatus $status,
    ) {}

    public static function create(CustomerId $customerId): self
    {
        return new self(OrderId::generate(), $customerId, OrderStatus::Draft);
    }

    public function addItem(Sku $sku, int $quantity, Money $price): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new InvalidOrderStatusException('Cannot add items to a non-draft order');
        }
        $this->items[] = new OrderItem($sku, $quantity, $price);
    }

    public function markAsPaid(): void
    {
        if ($this->status !== OrderStatus::Confirmed) {
            throw new InvalidOrderStatusException('Only confirmed orders can be paid');
        }
        $this->status = OrderStatus::Paid;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }
}

// app/Domain/Order/OrderRepository.php
interface OrderRepository
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
}

// app/Application/Order/CreateOrder/CreateOrderAction.php
final readonly class CreateOrderAction
{
    public function __construct(
        private OrderRepository $orders,
        private Dispatcher $events,
    ) {}

    public function handle(CreateOrderData $data): OrderId
    {
        $order = Order::create(new CustomerId($data->customerId));

        foreach ($data->items as $item) {
            $order->addItem(new Sku($item->sku), $item->quantity, new Money($item->priceCents));
        }

        $this->orders->save($order);
        $this->events->dispatch(new OrderCreated($order->id));

        return $order->id;
    }
}

// app/Infrastructure/Persistence/Eloquent/Repositories/EloquentOrderRepository.php
final class EloquentOrderRepository implements OrderRepository
{
    public function __construct(private OrderMapper $mapper) {}

    public function save(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $model = OrderModel::query()->updateOrCreate(
                ['id' => $order->id->value],
                $this->mapper->toRow($order),
            );
            $this->mapper->syncItems($model, $order);
        });
    }

    public function findById(OrderId $id): ?Order
    {
        $model = OrderModel::with('items')->find($id->value);
        return $model ? $this->mapper->toDomain($model) : null;
    }
}

// app/UI/Http/Controllers/CreateOrderController.php
final class CreateOrderController
{
    public function __invoke(
        CreateOrderRequest $request,
        CreateOrderAction $action,
    ): JsonResponse {
        $orderId = $action->handle(CreateOrderData::fromRequest($request));

        return new JsonResponse(
            ['data' => ['id' => $orderId->value]],
            Response::HTTP_CREATED,
        );
    }
}
```

---

## Placeholders

Use these when generating new code. Replace `{Noun}`, `{Verb}`, `{params}` etc. before returning.

- Action: `{Verb}{Noun}Action::handle({Verb}{Noun}Data $data)`
- DTO: `readonly class {Verb}{Noun}Data`
- Repository interface: `interface {Noun}Repository { save(); findById(); }`
- Eloquent repo: `Eloquent{Noun}Repository implements {Noun}Repository`
- Controller: `{Verb}{Noun}Controller::__invoke({Verb}{Noun}Request, {Verb}{Noun}Action)`
