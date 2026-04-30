# Output Format

Every response that produces code must start with a header line identifying mode, complexity, and architecture tier.

```
[MODE] [COMPLEXITY] [ARCH]
```

Examples:

- `[FEATURE] [MEDIUM] [Action+DTO]`
- `[FIX] [SIMPLE] [CRUD]`
- `[REFACTOR] [COMPLEX] [DDD]`

---

## Sections

Follow this structure. Omit a section if it is not relevant to the task.

```
[MODE] [COMPLEXITY] [ARCH]

Brief rationale: 1–2 sentences on why this tier was chosen.

--- IMPLEMENTATION ---
<code blocks, grouped by file, with the full file path as the block header>

--- SELF-REVIEW ---
<at most 5 issues, or the single word: OK>

--- FIX ---
<only if SELF-REVIEW found issues; show the changed portions only>

--- QA ---
<tests added or modified, with test names and what they cover>
```

---

## Code Blocks

- One block per file.
- Precede each block with the file path on its own line.
- Omit unchanged portions when fixing; show context of at most 3 lines around the change.
- Do not repeat the same file twice in one response; combine edits.

Example:

```
// app/Application/Order/CreateOrder/CreateOrderAction.php
final readonly class CreateOrderAction { ... }
```

---

## Prose

- Prefer lists over paragraphs.
- No marketing language ("robust", "seamless", "powerful").
- No restating the user's question back to them.
- Mention trade-offs the reviewer should know about (e.g., "chose Medium over Complex because there is no state machine yet; revisit if a `payment_failed` state is added").

---

## When You Cannot Complete the Task

State clearly:

1. What you tried.
2. What blocked you.
3. What you need from the user to continue.

Do not ship partial code with `TODO`s to hide an incomplete implementation.
