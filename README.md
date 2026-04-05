# SC #136 Independent Test Suite

Independent test evidence for [laravel/serializable-closure#136](https://github.com/laravel/serializable-closure/pull/136).

This repository exists outside the PR itself to provide standalone, reproducible proof that the fix works correctly and introduces no regressions. It runs against the PR branch via Composer VCS, so anyone can clone this repo, install, and verify.

## The Bug

When multiple closures with identical signatures are defined on the **same source line**, serializable-closure resolves them all to the **first** closure on that line after serialization. The second, third, fourth (etc.) closures silently become copies of the first.

This affects real Laravel patterns like `Bus::chain()`, queue job arrays, event listener arrays, validation rule arrays, and any other place where multiple closures are defined inline in an array.

## Setup

Clone and install:

```bash
git clone https://github.com/JoshSalway/sc-136-tests.git
cd sc-136-tests
composer install
```

The `composer.json` pulls `laravel/serializable-closure` from the PR branch (`fix/closure-array-identity`) via a VCS repository pointing at the fork.

## Running the Tests

```bash
php tests/comprehensive.php
php tests/sanity-suite.php
```

Both files are self-contained PHP scripts with their own test harness. They output PASS/FAIL per test and a summary at the end. Exit code is 0 on all-pass, 1 on any failure.

---

## Test File: `tests/comprehensive.php` (61 tests)

This file directly tests the same-line closure identity bug and validates the fix across a wide range of closure forms. It uses a `test()` helper that serializes an array of closures, unserializes them, and asserts each closure returns its expected value.

### Section 1: Arrow Functions (5 tests)

The core bug reproduction. Arrays of arrow functions defined on a single line, varying the count from 2 to 10:

| Test | Description |
|------|-------------|
| 3 arrow functions on same line | The canonical bug case: three `fn()` closures returning 'a', 'b', 'c' |
| 2 arrow functions on same line | Minimum case to trigger the bug |
| 4 arrow functions on same line | Four distinct return values |
| 5 arrow functions on same line | Five closures returning integers 1-5 |
| 10 arrow functions on same line (stress test) | Ten closures returning 0-9, stress-tests the indexing logic |

### Section 2: Static Closures (2 tests)

Verifies the fix works with `static` closures, which have different binding behavior:

| Test | Description |
|------|-------------|
| static arrow functions on same line | `static fn() => 'first'`, `static fn() => 'second'`, `static fn() => 'third'` |
| static traditional closures on same line | `static function() { return 1; }`, `static function() { return 2; }`, etc. |

### Section 3: Traditional Closures (4 tests)

Tests `function () { ... }` syntax (not arrow functions), including `use()` variable capture:

| Test | Description |
|------|-------------|
| traditional closures on same line | Basic `function () { return 'x'; }` style |
| traditional closures with different use vars | Each closure captures a different variable via `use()` |
| traditional closures with same use var | Both closures capture the same variable but produce different output |
| traditional closures with multiple use vars | Closures using `use ($x, $y)` with different operations (`$x + $y` vs `$x * $y`) |

### Section 4: Typed Parameters and Return Types (9 tests)

Exercises every PHP parameter/return type variation to ensure the fix handles type metadata correctly:

| Test | Description |
|------|-------------|
| arrow functions with untyped params | `fn ($x) => $x * 2` vs `fn ($x) => $x * 3` vs `fn ($x) => $x + 1` |
| arrow functions with int params | `fn (int $x) => $x * 2` vs `fn (int $x) => $x * 3` |
| arrow functions with nullable params | `fn (?string $s) => $s ?? 'null-a'` vs `'null-b'` |
| arrow functions with union type params | `fn (int|string $v) => "a:$v"` vs `"b:$v"` |
| arrow functions with variadic params | `fn (int ...$nums) => array_sum($nums)` vs `count($nums)` |
| arrow functions with two params | `fn ($a, $b) => $a + $b` vs `$a - $b` vs `$a * $b` |
| arrow functions with default values | `fn ($x = 10) => $x * 2` vs `$x * 3` |
| arrow functions with return types | `fn (): string => 'typed-a'` vs `'typed-b'` |
| arrow functions with nullable return types | `fn (): ?string => 'nullable-a'` vs `'nullable-b'` |

### Section 5: Return Value Variations (4 tests)

Ensures different return value types do not confuse the serialization:

| Test | Description |
|------|-------------|
| mixed return types (int, string, float) | `fn () => 1`, `fn () => 'two'`, `fn () => 3.0` |
| returning arrays | Each closure returns a different array |
| returning null, true, false | `fn () => null`, `fn () => true`, `fn () => false` |
| returning with type casts | `(int) '42'`, `(string) 42`, `(float) '3.14'` |

### Section 6: Expressions in Closure Bodies (8 tests)

Validates that closures with different function calls and operators in their bodies are correctly distinguished:

| Test | Description |
|------|-------------|
| string function calls | `strtoupper('hello')` vs `strtolower('WORLD')` vs `ucfirst('test')` |
| math operations | `2 + 3` vs `2 * 3` vs `2 ** 3` |
| array function calls | `count()` vs `array_sum()` vs `max()` |
| string concatenation | `'hello world'` vs `'foo bar'` |
| complex expressions (implode) | Three `implode('-', ...)` calls with different arrays |
| ternary expressions | `true ? 'yes-a' : 'no-a'` vs `'yes-b'` |
| null coalescing | `null ?? 'fallback-a'` vs `'fallback-b'` |
| match expressions | `match (1) { ... }` vs `match (2) { ... }` |

### Section 7: Object Operations (2 tests)

| Test | Description |
|------|-------------|
| instanceof checks | `fn ($o) => $o instanceof \stdClass` vs `\ArrayObject` |
| object creation | `fn () => new \stdClass()` vs `new \ArrayObject()` |

### Section 8: Nested and Higher-Order Closures (1 test)

| Test | Description |
|------|-------------|
| closures returning closures | `fn () => fn () => 'inner-a'` vs `'inner-b'` -- tests that nesting does not break identity |

### Section 9: Mixed Signatures (2 tests)

Tests interplay between PR #120 (which added same-line support for different signatures) and #136 (which fixes same-line with identical signatures):

| Test | Description |
|------|-------------|
| mixed arrow and traditional | `fn () => 'arrow'` alongside `function () { return 'traditional'; }` |
| mixed signatures on same line | `fn ()` alongside `fn ($x)` alongside `fn ()` -- different arity on same line |

### Section 10: Constants (1 test)

| Test | Description |
|------|-------------|
| referencing constants | `fn () => TEST_CONST_A`, `fn () => TEST_CONST_B` with `define()`d constants |

### Section 11: Multi-Line Parity (10 tests)

These tests are the **control group**. Each one mirrors a same-line test from earlier sections but defines the closures on **separate lines**. Multi-line closures have always worked because each closure gets a unique line number. By running both variants, we prove:

1. The fix makes same-line closures behave identically to multi-line closures.
2. The fix does not break the existing multi-line behavior.

| Test | Mirrors |
|------|---------|
| 3 arrow functions on separate lines (parity) | Section 1: 3 arrow functions |
| 2 arrow functions on separate lines (parity) | Section 1: 2 arrow functions |
| static arrow functions on separate lines (parity) | Section 2: static arrow functions |
| static traditional closures on separate lines (parity) | Section 2: static traditional closures |
| traditional closures on separate lines (parity) | Section 3: traditional closures |
| traditional closures with different use vars on separate lines (parity) | Section 3: use() closures |
| arrow functions with int params on separate lines (parity) | Section 4: int params |
| arrow functions with default values on separate lines (parity) | Section 4: default values |
| arrow functions with return types on separate lines (parity) | Section 4: return types |
| mixed arrow and traditional on separate lines (parity) | Section 9: mixed signatures |

### Section 12: Laravel Docs Real-World Examples (8 tests)

Closures modeled after actual Laravel patterns from the documentation. These are the patterns most likely to be affected by the bug in production code:

| Test | Laravel Pattern |
|------|-----------------|
| Bus::chain() style job closures | Job chain where each step must be distinct |
| Collection::map() style closures | Array of transform functions: `$v * 2`, `$v + 10`, `$v ** 2` |
| Queue dispatch style closures | Traditional `function ()` closures simulating queued jobs |
| Event listener closures | user.created, user.updated, user.deleted handlers |
| Middleware pipeline closures | auth, throttle, verified middleware as closures |
| Validation rule closures | `strlen >= 3`, `strlen <= 255`, `ctype_alpha` checks |
| Route handler closures | Three route closures returning different page content |
| Scheduler task closures | backup-db, prune-stale, send-digest scheduled tasks |

### Section 13: Controls (2 tests)

Closures that should always work regardless of whether the fix is applied. Baseline sanity:

| Test | Description |
|------|-------------|
| closures on different lines (control) | Three closures on separate lines -- must always pass |
| single closure (control) | Single closure in array -- must always pass |

### Section 14: Serialization Edge Cases (3 tests)

Tests serialization behaviors beyond the standard `serialize(array_map(...))` pattern:

| Test | Description |
|------|-------------|
| same closure serialized twice | A single closure instance serialized independently twice; tests WeakMap caching behavior |
| same-line closures serialized individually | Same-line closures serialized one at a time (not via `array_map`), then each unserialized independently |
| different-line closures serialized individually | Same as above but with closures on separate lines; control case for individual serialization |

### Section 15: Known Limitation -- Out-of-Order Serialization (1 test, XFAIL)

Documents a known edge case where same-line closures are serialized in a **non-sequential order** (e.g., index 2 before index 0). This is marked as an expected failure (XFAIL) because:

- No standard PHP construct serializes closures out of array order.
- The fix uses a positional counter that assumes left-to-right processing.
- Documenting this explicitly prevents future confusion.

---

## Test File: `tests/sanity-suite.php` (32 tests)

This file is a full **regression and side-effect test suite**. It does not test the same-line bug at all. Instead, it verifies that the fix does not break any existing serializable-closure functionality. It uses an `assertTest()` helper that runs each test in isolation with a `roundtrip()` function (serialize then unserialize a single closure).

### Section 1: Basic Serialization Sanity (9 tests)

Core roundtrip tests for every closure form:

| Test | What it verifies |
|------|------------------|
| simple closure roundtrip | `function () { return 'hello'; }` survives serialize/unserialize |
| closure with use() variables | Single `use ($name)` variable is preserved |
| closure with multiple use() variables | Three `use()` variables of different types (string, string, int) |
| closure capturing object via use() | `stdClass` object with property is preserved through serialization |
| static closure | `static function () { ... }` maintains static binding |
| arrow function (fn) | `fn () => 'arrow-result'` roundtrips correctly |
| nested closures | Outer closure returns inner closure; both survive serialization |
| deeply nested closures (3 levels) | `fn () => fn () => fn () => 'deep'` -- three levels of nesting |
| recursive closure via use | Factorial function using `use (&$factorial)` self-reference; verifies `$factorial(5) === 120` |

### Section 2: Edge Cases (19 tests)

Thorough coverage of PHP closure features to catch any regressions:

| Test | What it verifies |
|------|------------------|
| empty closure | `function () {}` returns `null` after roundtrip |
| closure returning null explicitly | `return null` is preserved (distinct from empty closure) |
| closure with many parameters (6) | Six parameters, verifies `1+2+3+4+5+6 === 21` |
| closure with int type hint | `function (int $x): int` preserves type information |
| closure with string type hint | `function (string $s): string` with `strtoupper` |
| closure with array type hint | `function (array $arr): int` with `count()` |
| closure with return type | `: string` return type declaration |
| closure with nullable return type | `: ?string` returning `null` |
| closure with union type param | `int|string` union type parameter |
| closure with default parameter values | `int $x = 10, string $s = 'default'` -- tests both default and explicit calls |
| closure referencing global function | `strlen('hello')` -- global function access from closure |
| closure with variadic params | `string ...$args` with `implode` |
| closure with typed variadic params | `int ...$nums` with `array_sum` |
| closure returning array | Returns associative array, verifies keys and values |
| closure returning object | Creates and returns `stdClass` with property |
| closure with string keys in use | Captures associative array via `use()`, accesses by key |
| arrow function with complex expression | `fn ($x) => array_map(fn ($v) => $v * $x, [1, 2, 3])` -- nested arrow function inside `array_map` |
| closure with match expression | Full `match` expression with named cases and default |
| closure with named arguments style call | `$result(first: 'John', last: 'Doe')` -- PHP 8 named arguments |

### Section 3: Memory and Performance Sanity (4 tests)

Validates that the fix (which adds a WeakMap-based counter) does not introduce memory leaks or performance issues:

| Test | What it verifies |
|------|------------------|
| serialize 1000 closures (no memory leak) | Creates and roundtrips 1,000 closures in a loop, measures memory delta, asserts less than 10MB growth. Reports exact memory delta in KB. |
| serialize same closure multiple times (idempotent) | Serializes the same closure 100 times, verifies all 100 roundtrips produce the same result |
| serialize 1000 same-line closures in array | Creates a 1,000-element closure array (via loop, so each is on the same line), serializes the whole array at once, spot-checks indices 0, 499, and 999 |
| WeakMap cleanup after GC | Creates and discards 100 closures, verifying that WeakMap entries are released and no errors occur from stale references |

---

## Results

### Before Fix (serializable-closure 2.x base)

```
comprehensive.php: 4 passed, 57 failed
sanity-suite.php:  32 passed, 0 failed
```

Only the control tests and multi-line parity tests pass in `comprehensive.php`. Every same-line test fails because all closures resolve to the first one on that line. The sanity suite passes because it does not test same-line closures.

### After Fix (PR #136 branch)

```
comprehensive.php: 61 passed, 0 failed  (+ 1 documented XFAIL for out-of-order)
sanity-suite.php:  32 passed, 0 failed
```

All 93 tests pass. The single XFAIL (out-of-order serialization) is a documented known limitation, not a failure.

## Total Test Coverage

| Category | Count | File |
|----------|-------|------|
| Arrow functions (same-line identity) | 5 | comprehensive.php |
| Static closures | 2 | comprehensive.php |
| Traditional closures | 4 | comprehensive.php |
| Typed parameters and return types | 9 | comprehensive.php |
| Return value variations | 4 | comprehensive.php |
| Expressions in closure bodies | 8 | comprehensive.php |
| Object operations | 2 | comprehensive.php |
| Nested/higher-order closures | 1 | comprehensive.php |
| Mixed signatures (#120 + #136 interplay) | 2 | comprehensive.php |
| Constants | 1 | comprehensive.php |
| Multi-line parity (control group) | 10 | comprehensive.php |
| Laravel docs real-world patterns | 8 | comprehensive.php |
| Controls (always-pass baseline) | 2 | comprehensive.php |
| Serialization edge cases | 3 | comprehensive.php |
| Known limitation (XFAIL) | 1 | comprehensive.php |
| Basic serialization sanity | 9 | sanity-suite.php |
| Edge cases (regression coverage) | 19 | sanity-suite.php |
| Memory and performance sanity | 4 | sanity-suite.php |
| **Total** | **94** | |
