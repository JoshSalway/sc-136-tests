# SC-136 Tests

Independent before/after testing for [laravel/serializable-closure PR #136](https://github.com/laravel/serializable-closure/pull/136).

## PR Summary

**Title:** Fix same-line closure disambiguation via column-aware hashing

PR #136 fixes an issue where multiple closures defined on the same line would all resolve to the first closure when serialized and unserialized. The fix uses token column positions to disambiguate closures that share the same file and line.

## Test Suite

`tests/comprehensive.php` contains 44 test cases covering:

- Arrow functions (same-line, typed, nullable, variadic, union types)
- Traditional closures (with use, static, mixed)
- Return type variations (arrays, null/bool, objects, closures)
- Expression forms (ternary, null coalescing, match, instanceof)
- Scaling (2, 3, 4, 5, and 10 same-line closures)
- Serialization patterns (array, individual, WeakMap caching)
- Known limitation: out-of-order serialization
- Control cases (different lines, single closure)

## Results

### Before (2.x branch) - 4 pass, 39 fail

Most same-line closure tests fail because all closures resolve to the first one on the line.

### After (PR #136 branch) - 43 pass, 0 fail

All 43 numbered tests pass. The out-of-order serialization case (test 43) reports as XFAIL (expected failure / known limitation) and is not counted in the pass/fail totals.

### Package test suite

377 Pest tests pass on the PR branch with no regressions.

## Known Limitation

**Out-of-order serialization** (test 43): If closures on the same line are serialized in a different order than they appear in source, disambiguation can assign incorrect column offsets. This is documented in the PR and is an inherent trade-off of the counter-based approach.
