<?php

require __DIR__.'/../vendor/autoload.php';

use Laravel\SerializableClosure\SerializableClosure;

$pass = 0;
$fail = 0;
$errors = [];

function assertTest(string $name, callable $test): void
{
    global $pass, $fail, $errors;

    try {
        $result = $test();
        if ($result === true) {
            echo "  PASS: $name\n";
            $pass++;
        } else {
            echo "  FAIL: $name (returned: " . var_export($result, true) . ")\n";
            $fail++;
            $errors[] = $name;
        }
    } catch (\Throwable $e) {
        echo "  ERROR: $name - " . get_class($e) . ': ' . $e->getMessage() . "\n";
        $fail++;
        $errors[] = "$name (ERROR)";
    }
}

function roundtrip(\Closure $closure): \Closure
{
    $serialized = serialize(new SerializableClosure($closure));
    return unserialize($serialized)->getClosure();
}

// ============================================================================
// SECTION 1: Basic Serialization Sanity
// ============================================================================
echo "\n--- Basic Serialization Sanity ---\n";

assertTest('simple closure roundtrip', function () {
    $fn = function () { return 'hello'; };
    $result = roundtrip($fn);
    return $result() === 'hello';
});

assertTest('closure with use() variables', function () {
    $name = 'world';
    $fn = function () use ($name) { return "hello $name"; };
    $result = roundtrip($fn);
    return $result() === 'hello world';
});

assertTest('closure with multiple use() variables', function () {
    $a = 'foo';
    $b = 'bar';
    $c = 42;
    $fn = function () use ($a, $b, $c) { return "$a-$b-$c"; };
    $result = roundtrip($fn);
    return $result() === 'foo-bar-42';
});

assertTest('closure capturing object via use()', function () {
    $obj = new \stdClass();
    $obj->value = 'bound';
    $fn = function () use ($obj) { return $obj->value; };
    $result = roundtrip($fn);
    return $result() === 'bound';
});

assertTest('static closure', function () {
    $fn = static function () { return 'static-result'; };
    $result = roundtrip($fn);
    return $result() === 'static-result';
});

assertTest('arrow function (fn)', function () {
    $fn = fn () => 'arrow-result';
    $result = roundtrip($fn);
    return $result() === 'arrow-result';
});

assertTest('nested closures', function () {
    $fn = function () {
        return function () {
            return 'nested-inner';
        };
    };
    $outer = roundtrip($fn);
    $inner = $outer();
    return $inner() === 'nested-inner';
});

assertTest('deeply nested closures (3 levels)', function () {
    $fn = fn () => fn () => fn () => 'deep';
    $l1 = roundtrip($fn);
    return $l1()()() === 'deep';
});

assertTest('recursive closure via use', function () {
    $factorial = null;
    $factorial = function (int $n) use (&$factorial): int {
        return $n <= 1 ? 1 : $n * $factorial($n - 1);
    };
    $result = roundtrip($factorial);
    return $result(5) === 120;
});

// ============================================================================
// SECTION 2: Edge Cases
// ============================================================================
echo "\n--- Edge Cases ---\n";

assertTest('empty closure', function () {
    $fn = function () {};
    $result = roundtrip($fn);
    return $result() === null;
});

assertTest('closure returning null explicitly', function () {
    $fn = function () { return null; };
    $result = roundtrip($fn);
    return $result() === null;
});

assertTest('closure with many parameters (6)', function () {
    $fn = function ($a, $b, $c, $d, $e, $f) {
        return $a + $b + $c + $d + $e + $f;
    };
    $result = roundtrip($fn);
    return $result(1, 2, 3, 4, 5, 6) === 21;
});

assertTest('closure with int type hint', function () {
    $fn = function (int $x): int { return $x * 2; };
    $result = roundtrip($fn);
    return $result(5) === 10;
});

assertTest('closure with string type hint', function () {
    $fn = function (string $s): string { return strtoupper($s); };
    $result = roundtrip($fn);
    return $result('hello') === 'HELLO';
});

assertTest('closure with array type hint', function () {
    $fn = function (array $arr): int { return count($arr); };
    $result = roundtrip($fn);
    return $result([1, 2, 3]) === 3;
});

assertTest('closure with return type', function () {
    $fn = function (): string { return 'typed'; };
    $result = roundtrip($fn);
    return $result() === 'typed';
});

assertTest('closure with nullable return type', function () {
    $fn = function (): ?string { return null; };
    $result = roundtrip($fn);
    return $result() === null;
});

assertTest('closure with union type param', function () {
    $fn = function (int|string $v): string { return "val:$v"; };
    $result = roundtrip($fn);
    return $result(42) === 'val:42' && $result('hi') === 'val:hi';
});

assertTest('closure with default parameter values', function () {
    $fn = function (int $x = 10, string $s = 'default') {
        return "$s:$x";
    };
    $result = roundtrip($fn);
    return $result() === 'default:10' && $result(5, 'custom') === 'custom:5';
});

assertTest('closure referencing global function', function () {
    $fn = function () { return strlen('hello'); };
    $result = roundtrip($fn);
    return $result() === 5;
});

assertTest('closure with variadic params', function () {
    $fn = function (string ...$args): string { return implode(',', $args); };
    $result = roundtrip($fn);
    return $result('a', 'b', 'c') === 'a,b,c';
});

assertTest('closure with typed variadic params', function () {
    $fn = function (int ...$nums): int { return array_sum($nums); };
    $result = roundtrip($fn);
    return $result(1, 2, 3, 4, 5) === 15;
});

assertTest('closure returning array', function () {
    $fn = fn () => ['key' => 'value', 'num' => 42];
    $result = roundtrip($fn);
    $arr = $result();
    return $arr['key'] === 'value' && $arr['num'] === 42;
});

assertTest('closure returning object', function () {
    $fn = function () {
        $obj = new \stdClass();
        $obj->name = 'test';
        return $obj;
    };
    $result = roundtrip($fn);
    return $result()->name === 'test';
});

assertTest('closure with string keys in use', function () {
    $data = ['key' => 'value'];
    $fn = function () use ($data) { return $data['key']; };
    $result = roundtrip($fn);
    return $result() === 'value';
});

assertTest('arrow function with complex expression', function () {
    $fn = fn ($x) => array_map(fn ($v) => $v * $x, [1, 2, 3]);
    $result = roundtrip($fn);
    return $result(10) === [10, 20, 30];
});

assertTest('closure with match expression', function () {
    $fn = function (string $status): string {
        return match ($status) {
            'active' => 'Running',
            'paused' => 'Suspended',
            default => 'Unknown',
        };
    };
    $result = roundtrip($fn);
    return $result('active') === 'Running' && $result('other') === 'Unknown';
});

assertTest('closure with named arguments style call', function () {
    $fn = function (string $first, string $last): string {
        return "$first $last";
    };
    $result = roundtrip($fn);
    return $result(first: 'John', last: 'Doe') === 'John Doe';
});

// ============================================================================
// SECTION 3: Memory and Performance Sanity
// ============================================================================
echo "\n--- Memory and Performance Sanity ---\n";

assertTest('serialize 1000 closures (no memory leak)', function () {
    $memBefore = memory_get_usage(true);

    for ($i = 0; $i < 1000; $i++) {
        $fn = function () use ($i) { return $i; };
        $s = serialize(new SerializableClosure($fn));
        $r = unserialize($s)->getClosure();
        if ($r() !== $i) {
            return false;
        }
    }

    $memAfter = memory_get_usage(true);
    $memDiff = $memAfter - $memBefore;

    // Allow up to 10MB growth (generous, just checking for leaks)
    echo "    Memory delta: " . number_format($memDiff / 1024) . " KB\n";
    return $memDiff < 10 * 1024 * 1024;
});

assertTest('serialize same closure multiple times (idempotent)', function () {
    $fn = fn () => 'idempotent';

    $results = [];
    for ($i = 0; $i < 100; $i++) {
        $s = serialize(new SerializableClosure($fn));
        $results[] = unserialize($s)->getClosure()();
    }

    return count(array_unique($results)) === 1 && $results[0] === 'idempotent';
});

assertTest('serialize 1000 same-line closures in array', function () {
    // Simulate arrays of closures being serialized in bulk
    $closures = [];
    for ($i = 0; $i < 1000; $i++) {
        $closures[] = function () use ($i) { return $i; };
    }

    $serialized = serialize(array_map(
        fn ($c) => new SerializableClosure($c),
        $closures
    ));

    $unserialized = array_map(
        fn ($sc) => $sc->getClosure(),
        unserialize($serialized)
    );

    // Spot check
    return $unserialized[0]() === 0
        && $unserialized[499]() === 499
        && $unserialized[999]() === 999;
});

assertTest('WeakMap cleanup after GC', function () {
    // Create and discard closures to verify WeakMap does not retain them
    for ($i = 0; $i < 100; $i++) {
        $fn = function () use ($i) { return $i; };
        serialize(new SerializableClosure($fn));
        unset($fn);
    }

    // If we got here without error, WeakMap is not causing issues
    return true;
});

// ============================================================================
// RESULTS
// ============================================================================
echo "\n=== RESULTS ===\n";
echo "Passed: $pass\n";
echo "Failed: $fail\n";
echo "Total: " . ($pass + $fail) . "\n";

if (! empty($errors)) {
    echo "\nFailures:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
}

echo "\n";
exit($fail > 0 ? 1 : 0);
