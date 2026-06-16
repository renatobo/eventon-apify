<?php
/**
 * Entry point for the dependency-free PHP unit suite.
 *
 * Usage: php tests/php/run.php
 */

require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/cases/*.php') as $case) {
    require $case;
}

eventon_run_tests();
