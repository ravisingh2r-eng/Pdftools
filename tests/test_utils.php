<?php
/**
 * Test Utilities
 *
 * Simple assertion functions for the minimal test harness.
 */

class TestResult
{
    public string $name;
    public bool $passed;
    public string $message;
    public float $duration;

    public function __construct(string $name, bool $passed, string $message = '', float $duration = 0)
    {
        $this->name = $name;
        $this->passed = $passed;
        $this->message = $message;
        $this->duration = $duration;
    }
}

class TestRunner
{
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    public function addResult(TestResult $result): void
    {
        $this->results[] = $result;
        if ($result->passed) {
            $this->passed++;
        } else {
            $this->failed++;
        }
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getSummary(): array
    {
        return [
            'total' => count($this->results),
            'passed' => $this->passed,
            'failed' => $this->failed
        ];
    }

    public function printResults(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "TEST RESULTS\n";
        echo str_repeat('=', 60) . "\n\n";

        foreach ($this->results as $result) {
            $status = $result->passed ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
            echo "[{$status}] {$result->name}";

            if (!empty($result->message)) {
                echo " - {$result->message}";
            }

            echo sprintf(" (%.2fms)\n", $result->duration);
        }

        echo "\n" . str_repeat('-', 60) . "\n";
        echo "Total: {$this->passed} passed, {$this->failed} failed\n";
        echo str_repeat('=', 60) . "\n";
    }
}

// Global test runner instance
$GLOBALS['test_runner'] = new TestRunner();

/**
 * Assert that a condition is true
 */
function assert_true($condition, string $message = ''): bool
{
    if ($condition === true) {
        return true;
    }

    throw new AssertionError($message ?: 'Expected true, got false');
}

/**
 * Assert that a condition is false
 */
function assert_false($condition, string $message = ''): bool
{
    if ($condition === false) {
        return true;
    }

    throw new AssertionError($message ?: 'Expected false, got true');
}

/**
 * Assert that two values are equal
 */
function assert_equals($expected, $actual, string $message = ''): bool
{
    if ($expected === $actual) {
        return true;
    }

    $msg = $message ?: sprintf(
        'Expected %s, got %s',
        var_export($expected, true),
        var_export($actual, true)
    );

    throw new AssertionError($msg);
}

/**
 * Assert that two values are not equal
 */
function assert_not_equals($expected, $actual, string $message = ''): bool
{
    if ($expected !== $actual) {
        return true;
    }

    throw new AssertionError($message ?: 'Values should not be equal');
}

/**
 * Assert that a value is not null
 */
function assert_not_null($value, string $message = ''): bool
{
    if ($value !== null) {
        return true;
    }

    throw new AssertionError($message ?: 'Expected non-null value');
}

/**
 * Assert that a value is null
 */
function assert_null($value, string $message = ''): bool
{
    if ($value === null) {
        return true;
    }

    throw new AssertionError($message ?: 'Expected null value');
}

/**
 * Assert that a file exists
 */
function assert_file_exists(string $path, string $message = ''): bool
{
    if (file_exists($path)) {
        return true;
    }

    throw new AssertionError($message ?: "File does not exist: {$path}");
}

/**
 * Assert that an array contains a value
 */
function assert_contains($needle, array $haystack, string $message = ''): bool
{
    if (in_array($needle, $haystack)) {
        return true;
    }

    throw new AssertionError($message ?: 'Array does not contain expected value');
}

/**
 * Assert that a string contains a substring
 */
function assert_string_contains(string $needle, string $haystack, string $message = ''): bool
{
    if (strpos($haystack, $needle) !== false) {
        return true;
    }

    throw new AssertionError($message ?: "String does not contain: {$needle}");
}

/**
 * Assert that an exception is thrown
 */
function assert_throws(callable $callback, string $exceptionClass = 'Exception', string $message = ''): bool
{
    try {
        $callback();
    } catch (\Throwable $e) {
        if ($e instanceof $exceptionClass) {
            return true;
        }
        throw new AssertionError(
            $message ?: "Expected {$exceptionClass}, got " . get_class($e)
        );
    }

    throw new AssertionError($message ?: "Expected {$exceptionClass} to be thrown");
}

/**
 * Assert value is greater than
 */
function assert_greater_than($expected, $actual, string $message = ''): bool
{
    if ($actual > $expected) {
        return true;
    }

    throw new AssertionError($message ?: "Expected {$actual} to be greater than {$expected}");
}

/**
 * Create a simple test PDF for testing purposes
 */
function create_test_pdf(string $outputPath, string $content = 'Test PDF Content', int $pages = 1): bool
{
    // Load FPDF
    $pdf = new \FPDF();

    for ($i = 0; $i < $pages; $i++) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $content . ' - Page ' . ($i + 1), 0, 1, 'C');
    }

    $pdf->Output('F', $outputPath);

    return file_exists($outputPath);
}

/**
 * Clean up test temp directory
 */
function cleanup_test_temp(): void
{
    if (!defined('TEST_TEMP_DIR')) {
        return;
    }

    $files = glob(TEST_TEMP_DIR . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
