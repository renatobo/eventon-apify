<?php
/**
 * Generate a dependency-free JSON coverage report and enforce a line floor.
 *
 * Requires Xdebug with coverage mode enabled. The CI job installs and enables
 * it explicitly; local runs fail with a clear setup message.
 */

if (!function_exists('xdebug_start_code_coverage')) {
    fwrite(STDERR, "Coverage requires Xdebug with XDEBUG_MODE=coverage.\n");
    exit(2);
}

xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

define('EVENTON_TEST_NO_AUTO_EXIT', true);
require dirname(__DIR__) . '/tests/php/bootstrap.php';

foreach (glob(dirname(__DIR__) . '/tests/php/cases/*.php') as $case) {
    require $case;
}

$test_exit_code = eventon_run_tests(false);
$coverage = xdebug_get_code_coverage();
xdebug_stop_code_coverage();

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$covered = 0;
$executable = 0;
$files = array();

foreach ($coverage as $path => $lines) {
    if (strpos($path, $root . 'includes' . DIRECTORY_SEPARATOR) !== 0 && $path !== $root . 'eventon-apify.php' && $path !== $root . 'uninstall.php') {
        continue;
    }

    $file_covered = count(array_filter($lines, static fn($status) => $status === 1));
    $file_executable = count(array_filter($lines, static fn($status) => $status === 1 || $status === -1));
    $covered += $file_covered;
    $executable += $file_executable;
    $files[str_replace($root, '', $path)] = array(
        'covered' => $file_covered,
        'executable' => $file_executable,
    );
}

$percentage = $executable > 0 ? ($covered / $executable) * 100 : 0.0;
$minimum = (float) (getenv('EVENTON_APIFY_MIN_COVERAGE') ?: 10);
$report = array(
    'covered_lines' => $covered,
    'executable_lines' => $executable,
    'line_coverage_percent' => round($percentage, 2),
    'minimum_percent' => $minimum,
    'files' => $files,
);
$report_path = dirname(__DIR__) . '/coverage.json';
file_put_contents($report_path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

printf("Coverage: %.2f%% (%d/%d executable lines); minimum %.2f%%\n", $percentage, $covered, $executable, $minimum);

if ($test_exit_code !== 0) {
    exit($test_exit_code);
}

exit($percentage + 0.00001 >= $minimum ? 0 : 1);
