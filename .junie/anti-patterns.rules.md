# Anti-Patterns

❌ Жёстко запрещено:

1. Толстые контроллеры
2. Логика в моделях (Fat Model)
3. Прямой вызов DB в UseCase
4. Использование Facades в Domain
5. Нарушение dependency rule

---

❌ Плохой пример:

class OrderController {
public function store() {
Order::create([...]); // ❌
}
}

---

✔ Хороший пример:

class OrderController {
public function store(CreateOrderRequest $request, CreateOrderUseCase $useCase) {
return $useCase->execute(...);
}