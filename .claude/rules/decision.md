# Architecture Decision

Match the approach to the actual complexity. Over-architecting a two-field form wastes time and blurs the codebase.

---

## Signals

Count the signals. Use the highest tier that matches.

**Simple (CRUD):**
- 0–2 business rules
- No state machine
- Entity is effectively a database record with validation
- Feature fits in one controller + one model + one Form Request

**Medium (Actions + DTO):**
- 2–3 business rules that require orchestration
- Some side effects (email, event, external call)
- Transactional write across 1–2 tables
- Would benefit from being unit-testable without HTTP

**Complex (DDD):**
- More than 3 interacting business rules
- State transitions with invariants (order lifecycle, subscription, billing)
- Multiple aggregates coordinated through events or a saga
- A bounded context with its own vocabulary
- High consistency or concurrency requirements

---

## Default to Simpler

When signals straddle two tiers, choose the simpler one. Promotion later is cheap. Demolition of premature DDD is expensive.

Call out in the response header *why* you chose a given tier so the reviewer can challenge it.

Example: `[FEATURE] [MEDIUM] [Action+DTO] // one write, one event, no state machine → Action`

---

## Anti-Signals for DDD

Do not pick DDD when:

- The team has no existing DDD code in the module.
- The feature is a one-off admin screen or report.
- The bounded context is unclear or still emerging.
- The only "rule" is field validation.

---

## When in Doubt

Ask the user: *"This looks like CRUD on the surface but there are N rules (list them). Do you want me to go with Actions + DTO, or is there a state machine here I am missing?"*

Proceed with the simpler tier if no answer arrives.
