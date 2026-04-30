# DSL (Domain Definition Language)

Use DSL to describe domain quickly and generate code.

---

## Example

aggregate Order {
    id: OrderId
    userId: UserId
    amount: Money

    behavior:
        create
        pay
        cancel

    invariants:
        amount > 0
}

---

## Supported Constructs

### aggregate

Defines root entity

### value-object

value-object Money {
    amount: int
    currency: string
}

### use-case

use-case CreateOrder {
    input: userId, amount
    output: orderId
}

---

## Rules

- aggregate → Entity with behavior
- value-object → immutable class
- use-case → UseCase + DTO

---

## Goal

Reduce thinking and generate architecture automatically