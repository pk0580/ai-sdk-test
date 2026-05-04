# Workflow

Every task runs in one of four modes. Detect the mode from the user's intent before writing code.

---

## Modes

| Mode | Trigger words | Primary goal |
|---|---|---|
| `FEATURE` | add, implement, build, create | Deliver new behavior behind a clean boundary |
| `FIX` | bug, broken, fails, wrong, crash | Restore expected behavior with minimal diff |
| `REFACTOR` | refactor, clean up, rename, extract, simplify | Change shape without changing behavior |
| `TEST` | add tests, cover, missing tests, increase coverage | Raise test confidence |

If intent is ambiguous, ask once; otherwise default to `FEATURE`.

---

## Pipelines

### FEATURE

1. **ARCHITECT** — pick complexity tier, layer boundaries, and the module's directory layout. Announce the decision in the response header.
2. **IMPLEMENT** — write code per the chosen templates. No speculative abstraction.
3. **SELF-REVIEW** — run the checklist in `rules/code-review.md`. List issues or write `OK`.
4. **QA** — add or extend tests; describe what is covered.

### FIX

1. **REPRODUCE** — describe the failing path in one or two sentences. If you cannot reproduce, say so explicitly.
2. **IMPLEMENT** — change the smallest amount of code that addresses the root cause. No drive-by edits. Do not rename, reformat, or restructure unrelated code.
3. **SELF-REVIEW** — verify the fix does not introduce regressions elsewhere. Add a regression test.

### REFACTOR

1. **PLAN** — list the moves. Keep public APIs stable unless the task is the rename.
2. **IMPLEMENT** — mechanical changes; no behavior changes.
3. **SELF-REVIEW** — confirm tests are untouched or only moved. All green.

### TEST

1. **QA** — identify untested paths; add feature tests first, then unit tests.

---

## Self-Review Loop

After IMPLEMENT, run SELF-REVIEW. If it reports issues, apply FIX, then SELF-REVIEW again. Maximum 3 loops; stop on `OK`.

Do not ship code with unresolved SELF-REVIEW issues. If you cannot resolve them in 3 loops, surface the blocker and stop.

---

## Constraints

- Prefer the simplest solution that meets the requirements.
- Fix root causes. Do not mute errors, skip tests, or suppress warnings to make a check pass.
- Do not introduce TODOs for the reviewer. Either do it now or leave it out and explain why.
- Do not bundle unrelated changes into a FIX. They belong in a follow-up REFACTOR.
