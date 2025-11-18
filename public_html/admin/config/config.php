<?php
/**
 * Admin Panel Configuration
 *
 * Database connection and global settings for the admin panel.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// =====================================================
// DATABASE CONFIGURATION
// =====================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'pdf_tools');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// APPLICATION SETTINGS
// =====================================================
define('ADMIN_SITE_NAME', 'PDF Tools Admin');
define('ADMIN_BASE_URL', '/admin');
define('PUBLIC_BASE_URL', '');

// Paths
define('ADMIN_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', dirname(ADMIN_ROOT));

// Security
define('CSRF_TOKEN_NAME', 'admin_csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// =====================================================
// DATABASE CONNECTION (PDO)
// =====================================================
function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed. Please check configuration.');
        }
    }

    return $pdo;
}

// =====================================================
// CSRF PROTECTION
// =====================================================
function generate_csrf_token(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf_token(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) &&
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

// =====================================================
// FLASH MESSAGES
// =====================================================
function set_flash(string $type, string $message): void {
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_messages(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

// =====================================================
// AUTHENTICATION HELPERS
// =====================================================
function is_logged_in(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function get_current_admin(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $admin = null;

    if ($admin === null) {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
    }

    return $admin ?: null;
}

function has_role(string|array $roles): bool {
    $admin = get_current_admin();
    if (!$admin) return false;

    if (is_string($roles)) {
        $roles = [$roles];
    }

    return in_array($admin['role'], $roles);
}

function require_role(string|array $roles): void {
    if (!has_role($roles)) {
        set_flash('danger', 'You do not have permission to access this page.');
        header('Location: ' . ADMIN_BASE_URL . '/dashboard.php');
        exit;
    }
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================
function e(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url, string $message = '', string $type = 'success'): void {
    if ($message) {
        set_flash($type, $message);
    }
    header('Location: ' . $url);
    exit;
}

function format_bytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

function time_ago(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';

    return date('M j, Y', $time);
}

function get_pagination(int $total, int $per_page, int $current_page): array {
    $total_pages = ceil($total / $per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;

    return [
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}
