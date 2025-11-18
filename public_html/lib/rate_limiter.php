<?php
/**
 * Rate Limiter
 *
 * Simple IP + tool based rate limiting using database storage.
 * Prevents abuse by limiting requests per time window.
 *
 * SQL Schema:
 * -----------
 * CREATE TABLE rate_limits (
 *     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     ip_address VARCHAR(45) NOT NULL,
 *     tool_slug VARCHAR(100) NOT NULL,
 *     request_count INT UNSIGNED DEFAULT 1,
 *     window_start DATETIME NOT NULL,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *
 *     UNIQUE KEY unique_ip_tool_window (ip_address, tool_slug, window_start),
 *     INDEX idx_window_start (window_start),
 *     INDEX idx_ip_address (ip_address)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 * -- Cleanup old records (run via cron)
 * -- DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY);
 */

/**
 * Check if request is rate limited
 *
 * @param string $toolSlug    The tool being accessed
 * @param int    $maxRequests Maximum requests allowed in window
 * @param int    $windowSecs  Time window in seconds (default: 3600 = 1 hour)
 * @return array              ['allowed' => bool, 'remaining' => int, 'reset' => timestamp]
 */
function check_rate_limit(string $toolSlug, int $maxRequests = 20, int $windowSecs = 3600): array {
    $ip = get_client_ip();
    $windowStart = get_window_start($windowSecs);

    try {
        $pdo = get_rate_limit_db();
        if (!$pdo) {
            // If DB not available, allow request but log warning
            error_log('Rate limiter: Database not available');
            return ['allowed' => true, 'remaining' => $maxRequests, 'reset' => time() + $windowSecs];
        }

        // Get or create rate limit record
        $stmt = $pdo->prepare('
            INSERT INTO rate_limits (ip_address, tool_slug, request_count, window_start)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE request_count = request_count + 1
        ');
        $stmt->execute([$ip, $toolSlug, $windowStart]);

        // Get current count
        $stmt = $pdo->prepare('
            SELECT request_count
            FROM rate_limits
            WHERE ip_address = ?
              AND tool_slug = ?
              AND window_start = ?
        ');
        $stmt->execute([$ip, $toolSlug, $windowStart]);
        $count = (int)$stmt->fetchColumn();

        $remaining = max(0, $maxRequests - $count);
        $resetTime = strtotime($windowStart) + $windowSecs;

        return [
            'allowed' => $count <= $maxRequests,
            'remaining' => $remaining,
            'reset' => $resetTime,
            'count' => $count,
            'limit' => $maxRequests
        ];

    } catch (PDOException $e) {
        error_log('Rate limiter error: ' . $e->getMessage());
        // On error, allow request to prevent blocking legitimate users
        return ['allowed' => true, 'remaining' => $maxRequests, 'reset' => time() + $windowSecs];
    }
}

/**
 * Enforce rate limit - blocks request if limit exceeded
 *
 * @param string $toolSlug    The tool being accessed
 * @param int    $maxRequests Maximum requests allowed
 * @param int    $windowSecs  Time window in seconds
 * @return void               Exits with 429 if rate limited
 */
function enforce_rate_limit(string $toolSlug, int $maxRequests = 20, int $windowSecs = 3600): void {
    $result = check_rate_limit($toolSlug, $maxRequests, $windowSecs);

    if (!$result['allowed']) {
        // Set rate limit headers
        header('HTTP/1.1 429 Too Many Requests');
        header('Retry-After: ' . ($result['reset'] - time()));
        header('X-RateLimit-Limit: ' . $maxRequests);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . $result['reset']);

        // Return JSON for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $result['reset'] - time()
            ]);
        } else {
            // HTML response for regular requests
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Rate Limit Exceeded</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .container { max-width: 500px; margin: 0 auto; }
        h1 { color: #e74c3c; }
        .retry { color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Too Many Requests</h1>
        <p>You have exceeded the rate limit for this tool.</p>
        <p class="retry">Please try again in ' . ceil(($result['reset'] - time()) / 60) . ' minutes.</p>
        <p><a href="/">Return to homepage</a></p>
    </div>
</body>
</html>';
        }

        exit;
    }

    // Set rate limit headers for successful requests too
    header('X-RateLimit-Limit: ' . $maxRequests);
    header('X-RateLimit-Remaining: ' . $result['remaining']);
    header('X-RateLimit-Reset: ' . $result['reset']);
}

/**
 * Get rate limit status without incrementing counter
 *
 * @param string $toolSlug
 * @param int    $maxRequests
 * @param int    $windowSecs
 * @return array
 */
function get_rate_limit_status(string $toolSlug, int $maxRequests = 20, int $windowSecs = 3600): array {
    $ip = get_client_ip();
    $windowStart = get_window_start($windowSecs);

    try {
        $pdo = get_rate_limit_db();
        if (!$pdo) {
            return ['count' => 0, 'remaining' => $maxRequests, 'reset' => time() + $windowSecs];
        }

        $stmt = $pdo->prepare('
            SELECT request_count
            FROM rate_limits
            WHERE ip_address = ?
              AND tool_slug = ?
              AND window_start = ?
        ');
        $stmt->execute([$ip, $toolSlug, $windowStart]);
        $count = (int)$stmt->fetchColumn();

        return [
            'count' => $count,
            'remaining' => max(0, $maxRequests - $count),
            'reset' => strtotime($windowStart) + $windowSecs
        ];

    } catch (PDOException $e) {
        return ['count' => 0, 'remaining' => $maxRequests, 'reset' => time() + $windowSecs];
    }
}

/**
 * Clean up old rate limit records
 *
 * @param int $olderThanHours Delete records older than this many hours
 * @return int Number of records deleted
 */
function cleanup_rate_limits(int $olderThanHours = 24): int {
    try {
        $pdo = get_rate_limit_db();
        if (!$pdo) {
            return 0;
        }

        $stmt = $pdo->prepare('
            DELETE FROM rate_limits
            WHERE window_start < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ');
        $stmt->execute([$olderThanHours]);

        return $stmt->rowCount();

    } catch (PDOException $e) {
        error_log('Rate limit cleanup error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get client IP address
 *
 * @return string
 */
function get_client_ip(): string {
    // Check for proxy headers (be careful with these in production)
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Standard proxy
        'HTTP_X_REAL_IP',            // Nginx proxy
        'REMOTE_ADDR'                // Direct connection
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            // X-Forwarded-For can contain multiple IPs
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }

            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

/**
 * Get window start time (rounded to window size)
 *
 * @param int $windowSecs
 * @return string
 */
function get_window_start(int $windowSecs): string {
    $timestamp = floor(time() / $windowSecs) * $windowSecs;
    return date('Y-m-d H:i:s', $timestamp);
}

/**
 * Get database connection for rate limiting
 *
 * @return PDO|null
 */
function get_rate_limit_db(): ?PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // Use existing connection function if available
        if (function_exists('get_db_connection')) {
            $pdo = get_db_connection();
            return $pdo;
        }

        // Fall back to constants
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
        error_log('Rate limiter DB connection error: ' . $e->getMessage());
        return null;
    }
}
