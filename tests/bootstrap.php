<?php
/**
 * Test Bootstrap
 *
 * Sets up the testing environment for PDF Tools tests.
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test mode
define('TEST_MODE', true);
define('APP_RUNNING', true);

// Project root (one level above tests)
define('PROJECT_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', PROJECT_ROOT . '/public_html');

// Load composer autoloader
$autoloader = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Load configuration
require_once PUBLIC_ROOT . '/config/config.php';

// Load service classes
require_once PUBLIC_ROOT . '/lib/Pdf/PdfMergeService.php';
require_once PUBLIC_ROOT . '/lib/Pdf/PdfSplitService.php';
require_once PUBLIC_ROOT . '/lib/Pdf/PdfSecurityService.php';
require_once PUBLIC_ROOT . '/lib/Pdf/PdfOptimizeService.php';
require_once PUBLIC_ROOT . '/lib/Pdf/PdfConvertService.php';
require_once PUBLIC_ROOT . '/lib/Pdf/PdfServiceLoader.php';

// Load test utilities
require_once __DIR__ . '/test_utils.php';

// Create test temp directory
$testTempDir = PROJECT_ROOT . '/tests/temp';
if (!is_dir($testTempDir)) {
    mkdir($testTempDir, 0755, true);
}

define('TEST_TEMP_DIR', $testTempDir);

// Create test fixtures directory
$fixturesDir = PROJECT_ROOT . '/tests/fixtures';
if (!is_dir($fixturesDir)) {
    mkdir($fixturesDir, 0755, true);
}

define('TEST_FIXTURES_DIR', $fixturesDir);

echo "Test environment initialized.\n";
echo "Project root: " . PROJECT_ROOT . "\n";
echo "Test temp dir: " . TEST_TEMP_DIR . "\n\n";
