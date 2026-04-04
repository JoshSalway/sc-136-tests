# sc-136-tests

Independent before/after testing for [laravel/serializable-closure PR #136](https://github.com/laravel/serializable-closure/pull/136).

## What this tests

PR #136 fixes a bug where multiple closures on the same source line with identical signatures all resolve to the first closure after a serialize/unserialize roundtrip.

## Test coverage

43 test cases across 13 categories:

1. **Arrow functions** - 2, 3, 4, 5, and 10 closures on the same line
2. **Static closures** - static arrow functions and static traditional closures
3. **Traditional closures** - with use(), same use var, multiple use vars
4. **Typed parameters** - int, nullable, union, variadic, multi-param, defaults
5. **Return types** - string, nullable string
6. **Return values** - mixed types, arrays, null/true/false, type casts
7. **Expressions** - string ops, math, array ops, concatenation, ternary, null coalescing, match
8. **Object operations** - instanceof, object creation
9. **Higher-order** - closures returning closures
10. **Mixed signatures** - PR #120 + #136 interplay
11. **Constants** - closures referencing define'd constants
12. **Controls** - different lines and single closure (should always work)
13. **Serialization edge cases** - re-serialization, individual serialization, out-of-order (known limitation)

## Results

### Before fix (2.x base branch)

- **Passed: 4**
- **Failed: 39**
- Every same-line closure test fails. All closures resolve to the first one.

### After fix (PR #136 branch)

- **Passed: 43**
- **Failed: 0**
- All tests pass. The out-of-order serialization test correctly shows the known limitation (XFAIL, not counted as failure).

### Pest test suite

- 377 passed, 1 skipped (unrelated), 0 failed
- 873 assertions
- No regressions

## Running

```bash
git clone https://github.com/JoshSalway/sc-136-tests.git
cd sc-136-tests
composer install
php tests/comprehensive.php
```
