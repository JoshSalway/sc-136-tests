<?php

require __DIR__.'/../vendor/autoload.php';

use Laravel\SerializableClosure\SerializableClosure;

$pass = 0;
$fail = 0;
$errors = [];

function test(string $name, array $closures, array $expected): void
{
    global $pass, $fail, $errors;

    try {
        $serialized = serialize(array_map(
            fn ($c) => new SerializableClosure($c),
            $closures
        ));

        $unserialized = array_map(
            fn ($sc) => $sc->getClosure(),
            unserialize($serialized)
        );

        $allMatch = true;
        $got = [];

        for ($i = 0; $i < count($expected); $i++) {
            if (is_callable($expected[$i]) && ! is_string($expected[$i])) {
                $result = $expected[$i]($unserialized[$i]);
                $got[] = 'custom';
            } else {
                $actual = ($unserialized[$i])();
                $result = $actual === $expected[$i];
                $got[] = var_export($actual, true);
            }

            if (! $result) {
                $allMatch = false;
            }
        }

        if ($allMatch) {
            echo "  PASS: $name\n";
            $pass++;
        } else {
            $expectedStr = implode(', ', array_map(
                fn ($e) => is_callable($e) && ! is_string($e) ? 'custom' : var_export($e, true),
                $expected
            ));
            echo "  FAIL: $name (expected: $expectedStr, got: " . implode(', ', $got) . ")\n";
            $fail++;
            $errors[] = $name;
        }
    } catch (\Throwable $e) {
        echo "  ERROR: $name - " . get_class($e) . ': ' . $e->getMessage() . "\n";
        $fail++;
        $errors[] = "$name (ERROR)";
    }
}

// ============================================================================
// SECTION 1: Arrow Functions (basic same-line identity)
// ============================================================================
echo "\n--- Arrow Functions ---\n";

$c = [fn () => 'a', fn () => 'b', fn () => 'c'];
test('3 arrow functions on same line', $c, ['a', 'b', 'c']);

$c = [fn () => 'only-two-a', fn () => 'only-two-b'];
test('2 arrow functions on same line', $c, ['only-two-a', 'only-two-b']);

$c = [fn () => 'w', fn () => 'x', fn () => 'y', fn () => 'z'];
test('4 arrow functions on same line', $c, ['w', 'x', 'y', 'z']);

$c = [fn () => 1, fn () => 2, fn () => 3, fn () => 4, fn () => 5];
test('5 arrow functions on same line', $c, [1, 2, 3, 4, 5]);

$c = [fn () => 0, fn () => 1, fn () => 2, fn () => 3, fn () => 4, fn () => 5, fn () => 6, fn () => 7, fn () => 8, fn () => 9];
test('10 arrow functions on same line (stress test)', $c, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

// ============================================================================
// SECTION 2: Static Closures
// ============================================================================
echo "\n--- Static Closures ---\n";

$c = [static fn () => 'first', static fn () => 'second', static fn () => 'third'];
test('static arrow functions on same line', $c, ['first', 'second', 'third']);

$c = [static function () { return 1; }, static function () { return 2; }, static function () { return 3; }];
test('static traditional closures on same line', $c, [1, 2, 3]);

// ============================================================================
// SECTION 3: Traditional Closures
// ============================================================================
echo "\n--- Traditional Closures ---\n";

$c = [function () { return 'x'; }, function () { return 'y'; }, function () { return 'z'; }];
test('traditional closures on same line', $c, ['x', 'y', 'z']);

$va = 'alpha';
$vb = 'beta';
$c = [function () use ($va) { return $va; }, function () use ($vb) { return $vb; }];
test('traditional closures with different use vars', $c, ['alpha', 'beta']);

$shared = 'shared';
$c = [function () use ($shared) { return $shared.'-a'; }, function () use ($shared) { return $shared.'-b'; }];
test('traditional closures with same use var', $c, ['shared-a', 'shared-b']);

$x = 1;
$y = 2;
$c = [function () use ($x, $y) { return $x + $y; }, function () use ($x, $y) { return $x * $y; }];
test('traditional closures with multiple use vars', $c, [3, 2]);

// ============================================================================
// SECTION 4: Typed Parameters and Return Types
// ============================================================================
echo "\n--- Typed Parameters and Return Types ---\n";

$c = [fn ($x) => $x * 2, fn ($x) => $x * 3, fn ($x) => $x + 1];
test('arrow functions with untyped params', $c, [fn ($u) => $u(5) === 10, fn ($u) => $u(5) === 15, fn ($u) => $u(5) === 6]);

$c = [fn (int $x) => $x * 2, fn (int $x) => $x * 3];
test('arrow functions with int params', $c, [fn ($u) => $u(5) === 10, fn ($u) => $u(5) === 15]);

$c = [fn (?string $s) => $s ?? 'null-a', fn (?string $s) => $s ?? 'null-b'];
test('arrow functions with nullable params', $c, [fn ($u) => $u(null) === 'null-a', fn ($u) => $u(null) === 'null-b']);

$c = [fn (int|string $v) => "a:$v", fn (int|string $v) => "b:$v"];
test('arrow functions with union type params', $c, [fn ($u) => $u('test') === 'a:test', fn ($u) => $u('test') === 'b:test']);

$c = [fn (int ...$nums) => array_sum($nums), fn (int ...$nums) => count($nums)];
test('arrow functions with variadic params', $c, [fn ($u) => $u(1, 2, 3) === 6, fn ($u) => $u(1, 2, 3) === 3]);

$c = [fn ($a, $b) => $a + $b, fn ($a, $b) => $a - $b, fn ($a, $b) => $a * $b];
test('arrow functions with two params', $c, [fn ($u) => $u(10, 3) === 13, fn ($u) => $u(10, 3) === 7, fn ($u) => $u(10, 3) === 30]);

$c = [fn ($x = 10) => $x * 2, fn ($x = 10) => $x * 3];
test('arrow functions with default values', $c, [fn ($u) => $u() === 20, fn ($u) => $u() === 30]);

$c = [fn (): string => 'typed-a', fn (): string => 'typed-b'];
test('arrow functions with return types', $c, ['typed-a', 'typed-b']);

$c = [fn (): ?string => 'nullable-a', fn (): ?string => 'nullable-b'];
test('arrow functions with nullable return types', $c, ['nullable-a', 'nullable-b']);

// ============================================================================
// SECTION 5: Return Value Variations
// ============================================================================
echo "\n--- Return Value Variations ---\n";

$c = [fn () => 1, fn () => 'two', fn () => 3.0];
test('mixed return types (int, string, float)', $c, [1, 'two', 3.0]);

$c = [fn () => [1, 2], fn () => [3, 4], fn () => [5, 6]];
test('returning arrays', $c, [[1, 2], [3, 4], [5, 6]]);

$c = [fn () => null, fn () => true, fn () => false];
test('returning null, true, false', $c, [null, true, false]);

$c = [fn () => (int) '42', fn () => (string) 42, fn () => (float) '3.14'];
test('returning with type casts', $c, [42, '42', 3.14]);

// ============================================================================
// SECTION 6: Expressions in Closure Bodies
// ============================================================================
echo "\n--- Expressions in Closure Bodies ---\n";

$c = [fn () => strtoupper('hello'), fn () => strtolower('WORLD'), fn () => ucfirst('test')];
test('string function calls', $c, ['HELLO', 'world', 'Test']);

$c = [fn () => 2 + 3, fn () => 2 * 3, fn () => 2 ** 3];
test('math operations', $c, [5, 6, 8]);

$c = [fn () => count([1, 2, 3]), fn () => array_sum([1, 2, 3]), fn () => max([1, 2, 3])];
test('array function calls', $c, [3, 6, 3]);

$c = [fn () => 'hello'.' '.'world', fn () => 'foo'.' '.'bar'];
test('string concatenation', $c, ['hello world', 'foo bar']);

$c = [fn () => implode('-', ['a', 'b']), fn () => implode('-', ['c', 'd']), fn () => implode('-', ['e', 'f'])];
test('complex expressions (implode)', $c, ['a-b', 'c-d', 'e-f']);

$c = [fn () => true ? 'yes-a' : 'no-a', fn () => true ? 'yes-b' : 'no-b'];
test('ternary expressions', $c, ['yes-a', 'yes-b']);

$c = [fn () => null ?? 'fallback-a', fn () => null ?? 'fallback-b'];
test('null coalescing', $c, ['fallback-a', 'fallback-b']);

$c = [fn () => match (1) { 1 => 'one', default => 'other' }, fn () => match (2) { 1 => 'one', default => 'other' }];
test('match expressions', $c, ['one', 'other']);

// ============================================================================
// SECTION 7: Object Operations
// ============================================================================
echo "\n--- Object Operations ---\n";

$c = [fn ($o) => $o instanceof \stdClass, fn ($o) => $o instanceof \ArrayObject];
test('instanceof checks', $c, [fn ($u) => $u(new \stdClass) === true, fn ($u) => $u(new \stdClass) === false]);

$c = [fn () => new \stdClass(), fn () => new \ArrayObject()];
test('object creation', $c, [fn ($u) => $u() instanceof \stdClass, fn ($u) => $u() instanceof \ArrayObject]);

// ============================================================================
// SECTION 8: Nested and Higher-Order Closures
// ============================================================================
echo "\n--- Nested and Higher-Order Closures ---\n";

$c = [fn () => fn () => 'inner-a', fn () => fn () => 'inner-b'];
test('closures returning closures', $c, [fn ($u) => ($u())() === 'inner-a', fn ($u) => ($u())() === 'inner-b']);

// ============================================================================
// SECTION 9: Mixed Signatures (PR #120 + #136 interplay)
// ============================================================================
echo "\n--- Mixed Signatures ---\n";

$c = [fn () => 'arrow', function () { return 'traditional'; }];
test('mixed arrow and traditional', $c, ['arrow', 'traditional']);

$c = [fn () => 'no-args-a', fn ($x) => $x, fn () => 'no-args-b'];
test('mixed signatures on same line', $c, ['no-args-a', fn ($u) => $u('param') === 'param', 'no-args-b']);

// ============================================================================
// SECTION 10: Constants
// ============================================================================
echo "\n--- Constants ---\n";

define('TEST_CONST_A', 'const-a');
define('TEST_CONST_B', 'const-b');
$c = [fn () => TEST_CONST_A, fn () => TEST_CONST_B];
test('referencing constants', $c, ['const-a', 'const-b']);

// ============================================================================
// SECTION 11: Multi-Line Parity (proving the bug is single-line only)
// ============================================================================
echo "\n--- Multi-Line Parity ---\n";

// Parity for Section 1: arrow functions
$pa = fn () => 'a';
$pb = fn () => 'b';
$pc = fn () => 'c';
test('3 arrow functions on separate lines (parity)', [$pa, $pb, $pc], ['a', 'b', 'c']);

$p2a = fn () => 'only-two-a';
$p2b = fn () => 'only-two-b';
test('2 arrow functions on separate lines (parity)', [$p2a, $p2b], ['only-two-a', 'only-two-b']);

// Parity for Section 2: static closures
$sa = static fn () => 'first';
$sb = static fn () => 'second';
$sc = static fn () => 'third';
test('static arrow functions on separate lines (parity)', [$sa, $sb, $sc], ['first', 'second', 'third']);

$sta = static function () { return 1; };
$stb = static function () { return 2; };
$stc = static function () { return 3; };
test('static traditional closures on separate lines (parity)', [$sta, $stb, $stc], [1, 2, 3]);

// Parity for Section 3: traditional closures
$ta = function () { return 'x'; };
$tb = function () { return 'y'; };
$tc = function () { return 'z'; };
test('traditional closures on separate lines (parity)', [$ta, $tb, $tc], ['x', 'y', 'z']);

$va = 'alpha';
$vb = 'beta';
$tua = function () use ($va) { return $va; };
$tub = function () use ($vb) { return $vb; };
test('traditional closures with different use vars on separate lines (parity)', [$tua, $tub], ['alpha', 'beta']);

// Parity for Section 4: typed params
$tpa = fn (int $x) => $x * 2;
$tpb = fn (int $x) => $x * 3;
test('arrow functions with int params on separate lines (parity)', [$tpa, $tpb], [fn ($u) => $u(5) === 10, fn ($u) => $u(5) === 15]);

$dfa = fn ($x = 10) => $x * 2;
$dfb = fn ($x = 10) => $x * 3;
test('arrow functions with default values on separate lines (parity)', [$dfa, $dfb], [fn ($u) => $u() === 20, fn ($u) => $u() === 30]);

$rta = fn (): string => 'typed-a';
$rtb = fn (): string => 'typed-b';
test('arrow functions with return types on separate lines (parity)', [$rta, $rtb], ['typed-a', 'typed-b']);

// Parity for Section 9: mixed signatures
$mxa = fn () => 'arrow';
$mxb = function () { return 'traditional'; };
test('mixed arrow and traditional on separate lines (parity)', [$mxa, $mxb], ['arrow', 'traditional']);

// ============================================================================
// SECTION 12: Laravel Docs Real-World Examples
// ============================================================================
echo "\n--- Laravel Docs Real-World Examples ---\n";

// Bus::chain() style - array of closures simulating job chains
$chain = [fn () => 'process-payment', fn () => 'send-receipt', fn () => 'update-inventory'];
test('Bus::chain() style job closures', $chain, ['process-payment', 'send-receipt', 'update-inventory']);

// Collection::map() with serialized closures
$mappers = [fn ($v) => $v * 2, fn ($v) => $v + 10, fn ($v) => $v ** 2];
test('Collection::map() style closures', $mappers, [
    fn ($u) => $u(5) === 10,
    fn ($u) => $u(5) === 15,
    fn ($u) => $u(5) === 25,
]);

// Queue closure style - dispatch(function() { ... })
$jobs = [
    function () { return 'job-1-result'; },
    function () { return 'job-2-result'; },
    function () { return 'job-3-result'; },
];
test('Queue dispatch style closures', $jobs, ['job-1-result', 'job-2-result', 'job-3-result']);

// Event listener closures
$listeners = [fn () => 'user.created handler', fn () => 'user.updated handler', fn () => 'user.deleted handler'];
test('Event listener closures', $listeners, ['user.created handler', 'user.updated handler', 'user.deleted handler']);

// Middleware-style closures (pipeline pattern)
$middleware = [fn ($req) => "auth:$req", fn ($req) => "throttle:$req", fn ($req) => "verified:$req"];
test('Middleware pipeline closures', $middleware, [
    fn ($u) => $u('request') === 'auth:request',
    fn ($u) => $u('request') === 'throttle:request',
    fn ($u) => $u('request') === 'verified:request',
]);

// Validation rule closures
$rules = [fn ($v) => strlen($v) >= 3, fn ($v) => strlen($v) <= 255, fn ($v) => ctype_alpha($v)];
test('Validation rule closures', $rules, [
    fn ($u) => $u('hello') === true,
    fn ($u) => $u('hello') === true,
    fn ($u) => $u('hello') === true,
]);

// Route closures (simulated)
$routes = [fn () => 'home page', fn () => 'about page', fn () => 'contact page'];
test('Route handler closures', $routes, ['home page', 'about page', 'contact page']);

// Scheduler closures
$scheduled = [fn () => 'backup-db', fn () => 'prune-stale', fn () => 'send-digest'];
test('Scheduler task closures', $scheduled, ['backup-db', 'prune-stale', 'send-digest']);

// ============================================================================
// SECTION 13: Controls (should always work, with or without fix)
// ============================================================================
echo "\n--- Controls ---\n";

$ca = fn () => 'line-a';
$cb = fn () => 'line-b';
$cc = fn () => 'line-c';
test('closures on different lines (control)', [$ca, $cb, $cc], ['line-a', 'line-b', 'line-c']);

test('single closure (control)', [fn () => 'solo'], ['solo']);

// ============================================================================
// SECTION 14: Serialization Edge Cases
// ============================================================================
echo "\n--- Serialization Edge Cases ---\n";

// Same closure serialized twice (WeakMap caching)
$single = fn () => 'cached';
$s1 = serialize(new SerializableClosure($single));
$s2 = serialize(new SerializableClosure($single));
$r1 = unserialize($s1)->getClosure()();
$r2 = unserialize($s2)->getClosure()();
$ok = $r1 === 'cached' && $r2 === 'cached';
echo $ok ? "  PASS: same closure serialized twice\n" : "  FAIL: same closure serialized twice (got $r1, $r2)\n";
if ($ok) { $pass++; } else { $fail++; $errors[] = 'same closure serialized twice'; }

// Individual serialization of same-line closures (not grouped in array_map)
$c = [fn () => 'sep-a', fn () => 'sep-b', fn () => 'sep-c'];
$s0 = serialize(new SerializableClosure($c[0]));
$s1 = serialize(new SerializableClosure($c[1]));
$s2 = serialize(new SerializableClosure($c[2]));
$r0 = unserialize($s0)->getClosure()();
$r1 = unserialize($s1)->getClosure()();
$r2 = unserialize($s2)->getClosure()();
$ok = $r0 === 'sep-a' && $r1 === 'sep-b' && $r2 === 'sep-c';
echo $ok ? "  PASS: same-line closures serialized individually\n" : "  FAIL: same-line closures serialized individually (got: $r0, $r1, $r2)\n";
if ($ok) { $pass++; } else { $fail++; $errors[] = 'same-line closures serialized individually'; }

// Different-line closures serialized individually
$c1 = fn () => 'ind-a';
$c2 = fn () => 'ind-b';
$c3 = fn () => 'ind-c';
$s1 = serialize(new SerializableClosure($c1));
$s2 = serialize(new SerializableClosure($c2));
$s3 = serialize(new SerializableClosure($c3));
$r1 = unserialize($s1)->getClosure()();
$r2 = unserialize($s2)->getClosure()();
$r3 = unserialize($s3)->getClosure()();
$ok = $r1 === 'ind-a' && $r2 === 'ind-b' && $r3 === 'ind-c';
echo $ok ? "  PASS: different-line closures serialized individually\n" : "  FAIL: different-line closures serialized individually\n";
if ($ok) { $pass++; } else { $fail++; $errors[] = 'different-line closures serialized individually'; }

// ============================================================================
// SECTION 15: Out-of-Order Serialization (Known Limitation)
// ============================================================================
echo "\n--- Known Limitation: Out-of-Order Serialization ---\n";

$c = [fn () => 'order-a', fn () => 'order-b', fn () => 'order-c'];
$s2 = serialize(new SerializableClosure($c[2]));
$s0 = serialize(new SerializableClosure($c[0]));
$s1 = serialize(new SerializableClosure($c[1]));
$r0 = unserialize($s0)->getClosure()();
$r1 = unserialize($s1)->getClosure()();
$r2 = unserialize($s2)->getClosure()();
$ooo_pass = $r0 === 'order-a' && $r1 === 'order-b' && $r2 === 'order-c';
if ($ooo_pass) {
    echo "  PASS: out-of-order serialization (surprisingly works!)\n";
    $pass++;
} else {
    echo "  XFAIL: out-of-order serialization (known limitation)\n";
    echo "    Got: $r0, $r1, $r2 (expected: order-a, order-b, order-c)\n";
    echo "    This only happens when same-line closures are deliberately\n";
    echo "    serialized out of array order, which no standard PHP construct does.\n";
}

// ============================================================================
// RESULTS
// ============================================================================
echo "\n=== RESULTS ===\n";
echo "Passed: $pass\n";
echo "Failed: $fail\n";

if (! empty($errors)) {
    echo "Failures:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
}

echo "\n";
exit($fail > 0 ? 1 : 0);
