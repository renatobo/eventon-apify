<?php
/**
 * Tiny dependency-free test harness. Register cases with test(), assert with
 * eq()/ok()/throws(), then call eventon_run_tests().
 *
 * Mirrors the xUnit shape closely enough that cases can be ported to PHPUnit
 * later with little change (test name -> method, eq() -> assertSame, etc.).
 */

$GLOBALS['__eventon_tests'] = array();

/**
 * Register a test case.
 *
 * @param string   $name Human-readable case name.
 * @param callable $fn   Body; throws on failure.
 */
function test($name, callable $fn) {
    $GLOBALS['__eventon_tests'][] = array($name, $fn);
}

final class EventonAssertionError extends Exception {}

/**
 * Assert strict equality.
 */
function eq($actual, $expected, $message = '') {
    if ($actual !== $expected) {
        throw new EventonAssertionError(
            ($message !== '' ? $message . ' — ' : '')
            . 'expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

/**
 * Assert truthiness.
 */
function ok($condition, $message = '') {
    if (!$condition) {
        throw new EventonAssertionError($message !== '' ? $message : 'expected truthy value');
    }
}

/**
 * Assert that $fn throws (optionally of a given class).
 */
function throws(callable $fn, $expected_class = 'Throwable', $message = '') {
    try {
        $fn();
    } catch (Throwable $e) {
        if (!($e instanceof $expected_class)) {
            throw new EventonAssertionError(
                ($message !== '' ? $message . ' — ' : '')
                . 'expected ' . $expected_class . ', got ' . get_class($e)
            );
        }
        return;
    }
    throw new EventonAssertionError($message !== '' ? $message : 'expected an exception, none thrown');
}

/**
 * Run all registered tests, print results, and exit with a status code.
 */
function eventon_run_tests($exit = true) {
    $passed = 0;
    $failed = 0;

    foreach ($GLOBALS['__eventon_tests'] as $case) {
        list($name, $fn) = $case;

        if (function_exists('eventon_test_reset_wp_state')) {
            eventon_test_reset_wp_state();
        }

        try {
            $fn();
            $passed++;
            echo "  ok   - {$name}\n";
        } catch (Throwable $e) {
            $failed++;
            echo "  FAIL - {$name}\n";
            echo '         ' . $e->getMessage() . "\n";
        }
    }

    echo "\n" . ($failed === 0 ? 'PASS' : 'FAIL')
        . ": {$passed} passed, {$failed} failed\n";

    $exit_code = $failed === 0 ? 0 : 1;
    if ($exit) {
        exit($exit_code);
    }

    return $exit_code;
}
