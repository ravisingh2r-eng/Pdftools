<?php
/**
 * Centralized Error Logger
 *
 * Provides structured error logging to database and/or files.
 * Tracks errors with context for debugging and monitoring.
 *
 * SQL Schema:
 * -----------
 * CREATE TABLE error_logs (
 *     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'error',
 *     message TEXT NOT NULL,
 *     context JSON,
 *     tool_slug VARCHAR(100),
 *     ip_address VARCHAR(45),
 *     user_agent VARCHAR(500),
 *     url VARCHAR(2000),
 *     file VARCHAR(500),
 *     line INT UNSIGNED,
 *     stack_trace TEXT,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *
 *     INDEX idx_level (level),
 *     INDEX idx_tool_slug (tool_slug),
 *     INDEX idx_created_at (created_at),
 *     INDEX idx_ip_address (ip_address)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * -- Cleanup old logs (run via cron)
 * -- DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
 */

// Error level constants
define('LOG_LEVEL_DEBUG', 'debug');
define('LOG_LEVEL_INFO', 'info');
define('LOG_LEVEL_WARNING', 'warning');
define('LOG_LEVEL_ERROR', 'error');
define('LOG_LEVEL_CRITICAL', 'critical');

/**
 * Log an error message
 *
 * @param string      $message   Error message
 * @param string      $level     Error level (debug, info, warning, error, critical)
 * @param array       $context   Additional context data
 * @param string|null $toolSlug  Tool where error occurred
 * @return bool                  Success status
 */
function log_error(
    string $message,
    string $level = LOG_LEVEL_ERROR,
    array $context = [],
    ?string $toolSlug = null
): bool {
    // Validate level
    $validLevels = ['debug', 'info', 'warning', 'error', 'critical'];
    if (!in_array($level, $validLevels)) {
        $level = LOG_LEVEL_ERROR;
    }

    // Get debug backtrace for file/line info
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = $trace[0] ?? [];

    // Build log entry
    $entry = [
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'tool_slug' => $toolSlug,
        'ip_address' => get_logger_client_ip(),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        'url' => get_current_url(),
        'file' => $caller['file'] ?? null,
        'line' => $caller['line'] ?? null,
        'stack_trace' => ($level === LOG_LEVEL_CRITICAL) ? get_stack_trace() : null
    ];

    // Log to database
    $dbSuccess = log_to_database($entry);

    // Also log to file for critical errors or if DB fails
    if ($level === LOG_LEVEL_CRITICAL || !$dbSuccess) {
        log_to_file($entry);
    }

    return $dbSuccess;
}

/**
 * Log a debug message
 */
function log_debug(string $message, array $context = [], ?string $toolSlug = null): bool {
    return log_error($message, LOG_LEVEL_DEBUG, $context, $toolSlug);
}

/**
 * Log an info message
 */
function log_info(string $message, array $context = [], ?string $toolSlug = null): bool {
    return log_error($message, LOG_LEVEL_INFO, $context, $toolSlug);
}

/**
 * Log a warning message
 */
function log_warning(string $message, array $context = [], ?string $toolSlug = null): bool {
    return log_error($message, LOG_LEVEL_WARNING, $context, $toolSlug);
}

/**
 * Log a critical error
 */
function log_critical(string $message, array $context = [], ?string $toolSlug = null): bool {
    return log_error($message, LOG_LEVEL_CRITICAL, $context, $toolSlug);
}

/**
 * Log an exception
 *
 * @param Throwable   $e
 * @param string|null $toolSlug
 * @param array       $context
 * @return bool
 */
function log_exception(Throwable $e, ?string $toolSlug = null, array $context = []): bool {
    $context['exception_class'] = get_class($e);
    $context['exception_code'] = $e->getCode();

    $entry = [
        'level' => LOG_LEVEL_ERROR,
        'message' => $e->getMessage(),
        'context' => $context,
        'tool_slug' => $toolSlug,
        'ip_address' => get_logger_client_ip(),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        'url' => get_current_url(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'stack_trace' => $e->getTraceAsString()
    ];

    $dbSuccess = log_to_database($entry);

    if (!$dbSuccess) {
        log_to_file($entry);
    }

    return $dbSuccess;
}

/**
 * Set custom error and exception handlers
 *
 * Call this in your bootstrap/config to enable automatic error logging
 */
function register_error_handlers(): void {
    // Custom error handler
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        // Don't log errors that are suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $levelMap = [
            E_ERROR => LOG_LEVEL_ERROR,
            E_WARNING => LOG_LEVEL_WARNING,
            E_PARSE => LOG_LEVEL_CRITICAL,
            E_NOTICE => LOG_LEVEL_INFO,
            E_CORE_ERROR => LOG_LEVEL_CRITICAL,
            E_CORE_WARNING => LOG_LEVEL_WARNING,
            E_COMPILE_ERROR => LOG_LEVEL_CRITICAL,
            E_COMPILE_WARNING => LOG_LEVEL_WARNING,
            E_USER_ERROR => LOG_LEVEL_ERROR,
            E_USER_WARNING => LOG_LEVEL_WARNING,
            E_USER_NOTICE => LOG_LEVEL_INFO,
            E_STRICT => LOG_LEVEL_DEBUG,
            E_RECOVERABLE_ERROR => LOG_LEVEL_ERROR,
            E_DEPRECATED => LOG_LEVEL_DEBUG,
            E_USER_DEPRECATED => LOG_LEVEL_DEBUG
        ];

        $level = $levelMap[$errno] ?? LOG_LEVEL_ERROR;

        $entry = [
            'level' => $level,
            'message' => $errstr,
            'context' => ['error_type' => $errno],
            'tool_slug' => null,
            'ip_address' => get_logger_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'url' => get_current_url(),
            'file' => $errfile,
            'line' => $errline,
            'stack_trace' => null
        ];

        log_to_database($entry);

        // Return false to also execute PHP's internal error handler
        return false;
    });

    // Custom exception handler
    set_exception_handler(function (Throwable $e) {
        log_exception($e);

        // Re-throw for default handling (will show error page)
        throw $e;
    });

    // Shutdown function for fatal errors
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $entry = [
                'level' => LOG_LEVEL_CRITICAL,
                'message' => $error['message'],
                'context' => ['error_type' => $error['type']],
                'tool_slug' => null,
                'ip_address' => get_logger_client_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'url' => get_current_url(),
                'file' => $error['file'],
                'line' => $error['line'],
                'stack_trace' => null
            ];

            log_to_database($entry);
        }
    });
}

/**
 * Log entry to database
 *
 * @param array $entry
 * @return bool
 */
function log_to_database(array $entry): bool {
    try {
        $pdo = get_error_logger_db();
        if (!$pdo) {
            return false;
        }

        $stmt = $pdo->prepare('
            INSERT INTO error_logs (
                level, message, context, tool_slug, ip_address,
                user_agent, url, file, line, stack_trace
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        return $stmt->execute([
            $entry['level'],
            substr($entry['message'], 0, 65535),
            !empty($entry['context']) ? json_encode($entry['context']) : null,
            $entry['tool_slug'],
            $entry['ip_address'],
            $entry['user_agent'],
            $entry['url'],
            $entry['file'],
            $entry['line'],
            $entry['stack_trace']
        ]);

    } catch (PDOException $e) {
        // Can't log to database, will fall back to file
        return false;
    }
}

/**
 * Log entry to file (fallback)
 *
 * @param array $entry
 * @return bool
 */
function log_to_file(array $entry): bool {
    // Determine log file path
    $logDir = defined('LOG_DIR') ? LOG_DIR : dirname(__DIR__) . '/logs';

    // Create log directory if it doesn't exist
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/errors_' . date('Y-m-d') . '.log';

    // Format log line
    $timestamp = date('Y-m-d H:i:s');
    $level = strtoupper($entry['level']);
    $message = $entry['message'];
    $context = !empty($entry['context']) ? json_encode($entry['context']) : '';
    $location = $entry['file'] ? "{$entry['file']}:{$entry['line']}" : '';

    $logLine = "[{$timestamp}] [{$level}] {$message}";
    if ($location) {
        $logLine .= " at {$location}";
    }
    if ($context) {
        $logLine .= " context: {$context}";
    }
    if ($entry['tool_slug']) {
        $logLine .= " tool: {$entry['tool_slug']}";
    }
    if ($entry['ip_address']) {
        $logLine .= " ip: {$entry['ip_address']}";
    }
    $logLine .= PHP_EOL;

    if ($entry['stack_trace']) {
        $logLine .= "Stack trace:\n{$entry['stack_trace']}\n";
    }

    return (bool)@file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Get recent errors for monitoring
 *
 * @param int    $limit
 * @param string $level
 * @return array
 */
function get_recent_errors(int $limit = 100, string $level = ''): array {
    try {
        $pdo = get_error_logger_db();
        if (!$pdo) {
            return [];
        }

        $sql = 'SELECT * FROM error_logs';
        $params = [];

        if ($level) {
            $sql .= ' WHERE level = ?';
            $params[] = $level;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ?';
        $params[] = $limit;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get client IP address
 */
function get_logger_client_ip(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

/**
 * Get current URL
 */
function get_current_url(): string {
    if (php_sapi_name() === 'cli') {
        return 'cli';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    return substr("{$scheme}://{$host}{$uri}", 0, 2000);
}

/**
 * Get formatted stack trace
 */
function get_stack_trace(): string {
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    // Remove this function and log_error from trace
    array_shift($trace);
    array_shift($trace);

    $output = '';
    foreach ($trace as $i => $frame) {
        $file = $frame['file'] ?? '[internal]';
        $line = $frame['line'] ?? '?';
        $function = $frame['function'] ?? '';
        $class = $frame['class'] ?? '';
        $type = $frame['type'] ?? '';

        $output .= "#{$i} {$file}({$line}): ";
        if ($class) {
            $output .= "{$class}{$type}";
        }
        $output .= "{$function}()\n";
    }

    return $output;
}

/**
 * Get database connection for error logger
 */
function get_error_logger_db(): ?PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (function_exists('get_db_connection')) {
            $pdo = get_db_connection();
            return $pdo;
        }

        if (!defined('DB_HOST') || !defined('DB_NAME')) {
            return null;
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
        );

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;

    } catch (PDOException $e) {
        // Can't log this error to DB - it would be recursive
        error_log('Error logger DB connection failed: ' . $e->getMessage());
        return null;
    }
}
