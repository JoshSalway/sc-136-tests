# SC #136 Independent Test Suite

Independent test evidence for [laravel/serializable-closure#136](https://github.com/laravel/serializable-closure/pull/136), which fixes multiple closures on the same source line with identical signatures all resolving to the first closure after serialization.

## Tests

### `tests/comprehensive.php` (61 tests)

Covers the core bug and validates the fix across multiple scenarios:

- **Single-line closure identity** - the bug: arrays of closures on one line lose identity
- **Multi-line parity** - proves the same closures work correctly when on separate lines
- **Laravel real-world patterns** - Bus::chain, Collection::map, Queue dispatch, event listeners, middleware pipeline, validation rules, route handlers, scheduler tasks
- **Parameter variations** - typed, nullable, union, variadic, default values
- **Static closures, mixed signatures, edge cases**

### `tests/sanity-suite.php` (32 tests)

Full regression and side-effect testing:

- **Basic serialization** - roundtrip, use() variables, object capture, static, arrow, nested, recursive
- **Edge cases** - empty closures, type hints, return types, named arguments, match expressions
- **Memory/performance** - 1000-closure bulk test, idempotency, WeakMap cleanup validation

## Running

```bash
composer install
php tests/comprehensive.php
php tests/sanity-suite.php
```

## Results

- Before fix (2.x base): 4 passed, 57 failed (comprehensive)
- After fix (PR branch): 93 passed, 0 failed (all tests across both files)
