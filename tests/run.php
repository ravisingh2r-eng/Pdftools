#!/usr/bin/env php
<?php
/**
 * Test Runner
 *
 * CLI script to run all tests in the cases directory.
 *
 * Usage: php tests/run.php [--filter=pattern]
 */

require_once __DIR__ . '/bootstrap.php';

// Parse command line arguments
$options = getopt('', ['filter::', 'verbose']);
$filter = $options['filter'] ?? null;
$verbose = isset($options['verbose']);

echo "PDF Tools Test Suite\n";
echo str_repeat('=', 60) . "\n\n";

// Find test files
$testDir = __DIR__ . '/cases';
if (!is_dir($testDir)) {
    echo "No test cases directory found.\n";
    exit(1);
}

$testFiles = glob($testDir . '/*Test.php');

if (empty($testFiles)) {
    echo "No test files found in {$testDir}\n";
    exit(1);
}

echo "Found " . count($testFiles) . " test file(s)\n\n";

// Run each test file
foreach ($testFiles as $testFile) {
    $filename = basename($testFile);

    // Apply filter if specified
    if ($filter && strpos($filename, $filter) === false) {
        continue;
    }

    echo "Running: {$filename}\n";
    echo str_repeat('-', 40) . "\n";

    // Include the test file
    require_once $testFile;

    // Get all functions in the file
    $functions = get_defined_functions()['user'];

    // Find and run test_* functions
    foreach ($functions as $function) {
        if (strpos($function, 'test_') !== 0) {
            continue;
        }

        $testName = str_replace('_', ' ', substr($function, 5));
        $testName = ucfirst($testName);

        $start = microtime(true);

        try {
            // Run the test
            call_user_func($function);

            $duration = (microtime(true) - $start) * 1000;
            $result = new TestResult($testName, true, '', $duration);

        } catch (AssertionError $e) {
            $duration = (microtime(true) - $start) * 1000;
            $result = new TestResult($testName, false, $e->getMessage(), $duration);

        } catch (Exception $e) {
            $duration = (microtime(true) - $start) * 1000;
            $result = new TestResult($testName, false, 'Exception: ' . $e->getMessage(), $duration);
        }

        $GLOBALS['test_runner']->addResult($result);

        if ($verbose) {
            $status = $result->passed ? 'PASS' : 'FAIL';
            echo "  [{$status}] {$testName}\n";
        }
    }

    echo "\n";
}

// Print results
$GLOBALS['test_runner']->printResults();

// Clean up
cleanup_test_temp();

// Exit with appropriate code
$summary = $GLOBALS['test_runner']->getSummary();
exit($summary['failed'] > 0 ? 1 : 0);
