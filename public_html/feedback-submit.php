<?php
/**
 * Feedback Submission Handler
 *
 * Processes feedback form submissions from tool pages.
 */

require_once __DIR__ . '/config/config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// Get form data
$toolSlug = trim($_POST['tool_slug'] ?? '');
$feedbackType = trim($_POST['feedback_type'] ?? 'other');
$message = trim($_POST['message'] ?? '');
$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');

// Determine redirect URL
$redirectUrl = '/';
if ($toolSlug) {
    $redirectUrl = "/tools/{$toolSlug}/";
}

// Validate required fields
if (empty($message)) {
    header('Location: ' . $redirectUrl . '?feedback=error&reason=empty');
    exit;
}

// Validate message length
if (strlen($message) < 10) {
    header('Location: ' . $redirectUrl . '?feedback=error&reason=short');
    exit;
}

if (strlen($message) > 5000) {
    $message = substr($message, 0, 5000);
}

// Validate email if provided
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . $redirectUrl . '?feedback=error&reason=email');
    exit;
}

// Sanitize feedback type
$validTypes = ['bug', 'idea', 'question', 'other'];
if (!in_array($feedbackType, $validTypes)) {
    $feedbackType = 'other';
}

// Build subject from type
$subjects = [
    'bug' => 'Bug Report',
    'idea' => 'Feature Suggestion',
    'question' => 'Question',
    'other' => 'General Feedback'
];
$subject = $subjects[$feedbackType];

// Add tool context to subject if available
if ($toolSlug) {
    $subject .= ' - ' . $toolSlug;
}

// Get client IP
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ipAddress = trim($ips[0]);
}

try {
    // Database connection
    // Check for existing connection function or create new
    if (function_exists('get_db_connection')) {
        $pdo = get_db_connection();
    } else {
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname = defined('DB_NAME') ? DB_NAME : 'pdf_tools';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
    }

    // Insert feedback
    $stmt = $pdo->prepare('
        INSERT INTO feedback
        (tool_slug, name, email, subject, message, status, ip_address, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ');

    $stmt->execute([
        $toolSlug ?: null,
        $name ?: null,
        $email ?: null,
        $subject,
        $message,
        'new',
        $ipAddress
    ]);

    // Success - redirect back
    header('Location: ' . $redirectUrl . '?feedback=success');
    exit;

} catch (PDOException $e) {
    error_log('Feedback submission error: ' . $e->getMessage());
    header('Location: ' . $redirectUrl . '?feedback=error&reason=db');
    exit;
} catch (Exception $e) {
    error_log('Feedback submission error: ' . $e->getMessage());
    header('Location: ' . $redirectUrl . '?feedback=error&reason=unknown');
    exit;
}
