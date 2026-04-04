<?php
require __DIR__.'/vendor/autoload.php';
use Laravel\SerializableClosure\SerializableClosure;

$pass = 0;
$fail = 0;
$errors = [];

function test($name, $closures, $expected) {
    global $pass, $fail, $errors;
    try {
        $serialized = serialize(array_map(fn($c) => new SerializableClosure($c), $closures));
        $unserialized = array_map(fn($sc) => $sc->getClosure(), unserialize($serialized));

        $allMatch = true;
        $got = [];
        for ($i = 0; $i < count($expected); $i++) {
            $result = is_callable($expected[$i]) ? $expected[$i]($unserialized[$i]) : ($unserialized[$i])() === $expected[$i];
            $got[] = is_callable($expected[$i]) ? 'custom' : var_export(($unserialized[$i])(), true);
            if (!$result) $allMatch = false;
        }

        if ($allMatch) {
            echo "PASS: $name\n";
            $pass++;
        } else {
            $expectedStr = implode(', ', array_map(fn($e) => is_callable($e) ? 'custom' : var_export($e, true), $expected));
            $gotStr = implode(', ', $got);
            echo "FAIL: $name (expected: $expectedStr, got: $gotStr)\n";
            $fail++;
            $errors[] = $name;
        }
    } catch (\Throwable $e) {
        echo "ERROR: $name - " . get_class($e) . ": " . $e->getMessage() . "\n";
        $fail++;
        $errors[] = "$name (ERROR)";
    }
}

// === CLOSURE FORMS ===

// 1. Arrow functions (the basic case from the PR)
$c = [fn() => 'a', fn() => 'b', fn() => 'c'];
test('Arrow functions - same line', $c, ['a', 'b', 'c']);

// 2. Arrow functions with params (identical signatures)
$c = [fn($x) => $x * 2, fn($x) => $x * 3, fn($x) => $x + 1];
test('Arrow functions with params', $c, [fn($u) => $u(5) === 10, fn($u) => $u(5) === 15, fn($u) => $u(5) === 6]);

// 3. Static arrow functions
$c = [static fn() => 'first', static fn() => 'second', static fn() => 'third'];
test('Static arrow functions', $c, ['first', 'second', 'third']);

// 4. Traditional closures - same line
$c = [function() { return 'x'; }, function() { return 'y'; }, function() { return 'z'; }];
test('Traditional closures', $c, ['x', 'y', 'z']);

// 5. Static traditional closures
$c = [static function() { return 1; }, static function() { return 2; }, static function() { return 3; }];
test('Static traditional closures', $c, [1, 2, 3]);

// 6. Arrow functions returning different types
$c = [fn() => 1, fn() => 'two', fn() => 3.0];
test('Arrow functions mixed return types', $c, [1, 'two', 3.0]);

// 7. Arrow functions returning arrays
$c = [fn() => [1, 2], fn() => [3, 4], fn() => [5, 6]];
test('Arrow functions returning arrays', $c, [[1, 2], [3, 4], [5, 6]]);

// 8. Arrow functions returning null/bool
$c = [fn() => null, fn() => true, fn() => false];
test('Arrow functions null/bool', $c, [null, true, false]);

// 9. Two closures (not three)
$c = [fn() => 'only-two-a', fn() => 'only-two-b'];
test('Two arrow functions', $c, ['only-two-a', 'only-two-b']);

// 10. Four closures
$c = [fn() => 'w', fn() => 'x', fn() => 'y', fn() => 'z'];
test('Four arrow functions', $c, ['w', 'x', 'y', 'z']);

// 11. Five closures
$c = [fn() => 1, fn() => 2, fn() => 3, fn() => 4, fn() => 5];
test('Five arrow functions', $c, [1, 2, 3, 4, 5]);

// 12. Arrow functions with typed params
$c = [fn(int $x) => $x * 2, fn(int $x) => $x * 3];
test('Arrow functions typed params', $c, [fn($u) => $u(5) === 10, fn($u) => $u(5) === 15]);

// 13. Arrow functions with nullable typed params
$c = [fn(?string $s) => $s ?? 'null-a', fn(?string $s) => $s ?? 'null-b'];
test('Arrow functions nullable params', $c, [fn($u) => $u(null) === 'null-a', fn($u) => $u(null) === 'null-b']);

// 14. Arrow functions with return types
$c = [fn(): string => 'typed-a', fn(): string => 'typed-b'];
test('Arrow functions with return types', $c, ['typed-a', 'typed-b']);

// 15. Arrow functions with nullable return types
$c = [fn(): ?string => 'nullable-a', fn(): ?string => 'nullable-b'];
test('Arrow functions nullable return types', $c, ['nullable-a', 'nullable-b']);

// 16. Traditional closures with use
$va = 'alpha'; $vb = 'beta';
$c = [function() use ($va) { return $va; }, function() use ($vb) { return $vb; }];
test('Traditional closures with use (different vars)', $c, ['alpha', 'beta']);

// 17. Traditional closures with SAME use variable
$shared = 'shared';
$c = [function() use ($shared) { return $shared . '-a'; }, function() use ($shared) { return $shared . '-b'; }];
test('Traditional closures same use var', $c, ['shared-a', 'shared-b']);

// 18. Closures with multiple use vars
$x = 1; $y = 2;
$c = [function() use ($x, $y) { return $x + $y; }, function() use ($x, $y) { return $x * $y; }];
test('Traditional closures multiple use vars', $c, [3, 2]);

// 19. Mixed: arrow + traditional on same line
$c = [fn() => 'arrow', function() { return 'traditional'; }];
test('Mixed arrow and traditional', $c, ['arrow', 'traditional']);

// 20. Closures with string operations
$c = [fn() => strtoupper('hello'), fn() => strtolower('WORLD'), fn() => ucfirst('foo')];
test('Arrow functions with string ops', $c, ['HELLO', 'world', 'Foo']);

// 21. Closures with math operations
$c = [fn() => 2 + 3, fn() => 2 * 3, fn() => 2 ** 3];
test('Arrow functions with math', $c, [5, 6, 8]);

// 22. Closures with array operations
$c = [fn() => count([1,2,3]), fn() => array_sum([1,2,3]), fn() => max([1,2,3])];
test('Arrow functions with array ops', $c, [3, 6, 3]);

// 23. Closures returning closures
$c = [fn() => fn() => 'inner-a', fn() => fn() => 'inner-b'];
test('Closures returning closures', $c, [fn($u) => ($u())() === 'inner-a', fn($u) => ($u())() === 'inner-b']);

// 24. Closures with ternary
$c = [fn() => true ? 'yes-a' : 'no-a', fn() => true ? 'yes-b' : 'no-b'];
test('Arrow functions with ternary', $c, ['yes-a', 'yes-b']);

// 25. Closures with null coalescing
$c = [fn() => null ?? 'fallback-a', fn() => null ?? 'fallback-b'];
test('Arrow functions with null coalescing', $c, ['fallback-a', 'fallback-b']);

// 26. Closures with match expression
$c = [fn() => match(1) { 1 => 'one', default => 'other' }, fn() => match(2) { 1 => 'one', default => 'other' }];
test('Arrow functions with match', $c, ['one', 'other']);

// 27. Closures with spread operator
$c = [fn(int ...$nums) => array_sum($nums), fn(int ...$nums) => count($nums)];
test('Arrow functions variadic params', $c, [fn($u) => $u(1, 2, 3) === 6, fn($u) => $u(1, 2, 3) === 3]);

// 28. Closures with union type params (PHP 8.0+)
$c = [fn(int|string $v) => "a:$v", fn(int|string $v) => "b:$v"];
test('Arrow functions union type params', $c, [fn($u) => $u('test') === 'a:test', fn($u) => $u('test') === 'b:test']);

// 29. Closures on different lines (control - should always work)
$ca = fn() => 'line-a';
$cb = fn() => 'line-b';
$cc = fn() => 'line-c';
test('Different lines (control)', [$ca, $cb, $cc], ['line-a', 'line-b', 'line-c']);

// 30. Single closure (control - should always work)
test('Single closure (control)', [fn() => 'solo'], ['solo']);

// 31. Closures with complex bodies
$c = [fn() => implode('-', ['a', 'b']), fn() => implode('-', ['c', 'd']), fn() => implode('-', ['e', 'f'])];
test('Arrow functions complex bodies', $c, ['a-b', 'c-d', 'e-f']);

// 32. Closures referencing constants
define('TEST_CONST_A', 'const-a');
define('TEST_CONST_B', 'const-b');
$c = [fn() => TEST_CONST_A, fn() => TEST_CONST_B];
test('Arrow functions with constants', $c, ['const-a', 'const-b']);

// 33. Closures with type casting
$c = [fn() => (int) '42', fn() => (string) 42, fn() => (float) '3.14'];
test('Arrow functions with casting', $c, [42, '42', 3.14]);

// 34. Closures with concatenation
$c = [fn() => 'hello' . ' ' . 'world', fn() => 'foo' . ' ' . 'bar'];
test('Arrow functions with concatenation', $c, ['hello world', 'foo bar']);

// 35. Multiple params, same signature
$c = [fn($a, $b) => $a + $b, fn($a, $b) => $a - $b, fn($a, $b) => $a * $b];
test('Arrow functions two params', $c, [fn($u) => $u(10, 3) === 13, fn($u) => $u(10, 3) === 7, fn($u) => $u(10, 3) === 30]);

// 36. Closures with default values
$c = [fn($x = 10) => $x * 2, fn($x = 10) => $x * 3];
test('Arrow functions default values', $c, [fn($u) => $u() === 20, fn($u) => $u() === 30]);

// 37. Mixed: some closures different sigs, some same
$c = [fn() => 'no-args-a', fn($x) => $x, fn() => 'no-args-b'];
test('Mixed signatures on same line', $c, ['no-args-a', fn($u) => $u('param') === 'param', 'no-args-b']);

// 38. Closures with instanceof
$c = [fn($o) => $o instanceof \stdClass, fn($o) => $o instanceof \ArrayObject];
test('Arrow functions with instanceof', $c, [fn($u) => $u(new \stdClass) === true, fn($u) => $u(new \stdClass) === false]);

// 39. Closures creating objects
$c = [fn() => new \stdClass(), fn() => new \ArrayObject()];
test('Arrow functions creating objects', $c, [fn($u) => $u() instanceof \stdClass, fn($u) => $u() instanceof \ArrayObject]);

// === EDGE CASES FOR THE FIX ===

// 40. Serialize same closure twice (WeakMap caching)
$single = fn() => 'cached';
$s1 = serialize(new SerializableClosure($single));
$s2 = serialize(new SerializableClosure($single));
$r1 = unserialize($s1)->getClosure()();
$r2 = unserialize($s2)->getClosure()();
echo ($r1 === 'cached' && $r2 === 'cached') ? "PASS: Same closure serialized twice\n" : "FAIL: Same closure serialized twice (got $r1, $r2)\n";
if ($r1 === 'cached' && $r2 === 'cached') $pass++; else { $fail++; $errors[] = 'Same closure serialized twice'; }

// 41. Closures serialized individually (not as array)
$c1 = fn() => 'ind-a'; $c2 = fn() => 'ind-b'; $c3 = fn() => 'ind-c';
$s1 = serialize(new SerializableClosure($c1));
$s2 = serialize(new SerializableClosure($c2));
$s3 = serialize(new SerializableClosure($c3));
$r1 = unserialize($s1)->getClosure()();
$r2 = unserialize($s2)->getClosure()();
$r3 = unserialize($s3)->getClosure()();
echo ($r1 === 'ind-a' && $r2 === 'ind-b' && $r3 === 'ind-c') ? "PASS: Individual serialization (diff lines)\n" : "FAIL: Individual serialization\n";
if ($r1 === 'ind-a' && $r2 === 'ind-b' && $r3 === 'ind-c') $pass++; else { $fail++; $errors[] = 'Individual serialization'; }

// 42. Same-line closures serialized individually (not as group)
$c = [fn() => 'sep-a', fn() => 'sep-b', fn() => 'sep-c'];
$s0 = serialize(new SerializableClosure($c[0]));
$s1 = serialize(new SerializableClosure($c[1]));
$s2 = serialize(new SerializableClosure($c[2]));
$r0 = unserialize($s0)->getClosure()();
$r1 = unserialize($s1)->getClosure()();
$r2 = unserialize($s2)->getClosure()();
echo ($r0 === 'sep-a' && $r1 === 'sep-b' && $r2 === 'sep-c') ? "PASS: Same-line individually serialized\n" : "FAIL: Same-line individually serialized (got: $r0, $r1, $r2)\n";
if ($r0 === 'sep-a' && $r1 === 'sep-b' && $r2 === 'sep-c') $pass++; else { $fail++; $errors[] = 'Same-line individually serialized'; }

// 43. Out-of-order serialization (the known limitation)
$c = [fn() => 'order-a', fn() => 'order-b', fn() => 'order-c'];
$s2 = serialize(new SerializableClosure($c[2]));
$s0 = serialize(new SerializableClosure($c[0]));
$s1 = serialize(new SerializableClosure($c[1]));
$r0 = unserialize($s0)->getClosure()();
$r1 = unserialize($s1)->getClosure()();
$r2 = unserialize($s2)->getClosure()();
$ooo_pass = ($r0 === 'order-a' && $r1 === 'order-b' && $r2 === 'order-c');
echo $ooo_pass ? "PASS: Out-of-order serialization (surprisingly works!)\n" : "XFAIL: Out-of-order serialization (known limitation - got: $r0, $r1, $r2)\n";
if ($ooo_pass) $pass++; else echo "  (This is expected to fail per the PR's known limitation)\n";

// 44. Large number of same-line closures (stress test)
$c = [fn() => 0, fn() => 1, fn() => 2, fn() => 3, fn() => 4, fn() => 5, fn() => 6, fn() => 7, fn() => 8, fn() => 9];
$serialized = serialize(array_map(fn($cl) => new SerializableClosure($cl), $c));
$unserialized = array_map(fn($sc) => $sc->getClosure(), unserialize($serialized));
$all_ok = true;
$results = [];
for ($i = 0; $i < 10; $i++) {
    $r = $unserialized[$i]();
    $results[] = $r;
    if ($r !== $i) $all_ok = false;
}
echo $all_ok ? "PASS: 10 same-line closures\n" : "FAIL: 10 same-line closures (got: " . implode(',', $results) . ")\n";
if ($all_ok) $pass++; else { $fail++; $errors[] = '10 same-line closures'; }

echo "\n=== RESULTS ===\n";
echo "Passed: $pass\n";
echo "Failed: $fail\n";
if (!empty($errors)) {
    echo "Failures:\n";
    foreach ($errors as $e) echo "  - $e\n";
}
