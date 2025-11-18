<?php
/**
 * PDF Tools - Configuration File
 *
 * Contains database settings, site constants, and helper functions.
 */

// Prevent direct access
if (!defined('APP_RUNNING')) {
    define('APP_RUNNING', true);
}

// =============================================================================
// SITE CONFIGURATION
// =============================================================================

define('SITE_NAME', 'PDF Tools');
define('SITE_DESCRIPTION', 'Free online PDF tools to merge, split, compress, rotate, and protect your PDF files');
define('BASE_URL', 'https://yourdomain.com'); // Change this to your domain

// =============================================================================
// DATABASE CONFIGURATION
// =============================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// =============================================================================
// FILE UPLOAD CONFIGURATION
// =============================================================================

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('TEMP_DIR', __DIR__ . '/../uploads/temp/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['pdf']);

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Get PDO database connection
 *
 * @return PDO
 * @throws PDOException
 */
function get_pdo(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            // Log error in production, show generic message
            error_log('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Database connection failed. Please try again later.');
        }
    }

    return $pdo;
}

/**
 * Sanitize output for HTML
 *
 * @param string $string
 * @return string
 */
function h(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get the current page URL
 *
 * @return string
 */
function current_url(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Redirect to a URL
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Generate CSRF token
 *
 * @return string
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 *
 * @param string $token
 * @return bool
 */
function verify_csrf(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// =============================================================================
// FUTURE SETTINGS PLACEHOLDER
// =============================================================================

// Ad slots configuration
$ad_slots = [
    'header_top' => '<!-- Header Ad Slot -->',
    'footer' => '<!-- Footer Ad Slot -->',
    'sidebar' => '<!-- Sidebar Ad Slot -->',
    'tool_top' => '<!-- Tool Page Top Ad -->',
    'tool_bottom' => '<!-- Tool Page Bottom Ad -->',
];

// Tool categories (for future expansion)
$tool_categories = [
    'organize' => 'Organize PDF',
    'optimize' => 'Optimize PDF',
    'convert' => 'Convert PDF',
    'security' => 'PDF Security',
    'edit' => 'Edit PDF',
];
