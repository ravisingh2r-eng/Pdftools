<?php
/**
 * Usage Logger
 *
 * Logs tool usage to the database for analytics.
 * Works with the existing usage_logs table schema.
 */

/**
 * Log a tool usage event
 *
 * @param string $toolSlug      The tool identifier (e.g., 'merge-pdf')
 * @param int    $fileCount     Number of files processed
 * @param int    $totalSizeIn   Total input size in bytes
 * @param int    $durationMs    Processing duration in milliseconds
 * @param string $status        'success' or 'error'
 * @param string $errorMessage  Error message if status is 'error'
 * @return bool                 True on success, false on failure
 */
function log_usage(
    string $toolSlug,
    int $fileCount = 1,
    int $totalSizeIn = 0,
    int $durationMs = 0,
    string $status = 'success',
    string $errorMessage = ''
): bool {
    try {
        // Include config for database connection
        // Use the main public config, not admin config
        $configPath = __DIR__ . '/../config/config.php';
        if (!file_exists($configPath)) {
            error_log('Usage logger: config.php not found');
            return false;
        }

        require_once $configPath;

        // Get PDO connection
        // Check if we have the admin DB connection function or need to create our own
        if (function_exists('get_db_connection')) {
            $pdo = get_db_connection();
        } else {
            // Fallback: Create connection using constants from config
            // These should be defined in the main config or we use defaults
            $host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $name = defined('DB_NAME') ? DB_NAME : 'pdf_tools';
            $user = defined('DB_USER') ? DB_USER : 'root';
            $pass = defined('DB_PASS') ? DB_PASS : '';
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $user, $pass, $options);
        }

        // Get client information
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        // Handle proxied requests
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ipAddress = trim($ips[0]);
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Truncate user agent if too long
        if ($userAgent && strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }

        // Insert usage log
        $stmt = $pdo->prepare('
            INSERT INTO usage_logs
            (tool_slug, ip_address, user_agent, file_count, total_size_bytes,
             processing_time_ms, status, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');

        $stmt->execute([
            $toolSlug,
            $ipAddress,
            $userAgent,
            $fileCount,
            $totalSizeIn,
            $durationMs,
            $status,
            $errorMessage ?: null
        ]);

        // Also update total_uses counter in tools table if it exists
        try {
            $stmt = $pdo->prepare('
                UPDATE tools
                SET total_uses = total_uses + 1
                WHERE slug = ?
            ');
            $stmt->execute([$toolSlug]);
        } catch (PDOException $e) {
            // Silently ignore if tools table doesn't exist or slug not found
        }

        return true;

    } catch (PDOException $e) {
        error_log('Usage logger error: ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('Usage logger error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Helper function to calculate processing time
 *
 * @param float $startTime  microtime(true) at start
 * @return int              Duration in milliseconds
 */
function calculate_duration_ms(float $startTime): int {
    return (int)((microtime(true) - $startTime) * 1000);
}

/**
 * Helper function to get total upload size
 *
 * @param array $files  $_FILES array (can be single or multiple)
 * @param string $key   The form field name
 * @return int          Total size in bytes
 */
function get_upload_size(array $files, string $key = 'pdf_files'): int {
    if (!isset($files[$key])) {
        return 0;
    }

    $totalSize = 0;

    // Handle multiple files
    if (is_array($files[$key]['size'])) {
        foreach ($files[$key]['size'] as $size) {
            if (is_numeric($size)) {
                $totalSize += $size;
            }
        }
    } else {
        // Single file
        $totalSize = (int)$files[$key]['size'];
    }

    return $totalSize;
}

/**
 * Helper function to get file count
 *
 * @param array $files  $_FILES array
 * @param string $key   The form field name
 * @return int          Number of files
 */
function get_file_count(array $files, string $key = 'pdf_files'): int {
    if (!isset($files[$key])) {
        return 0;
    }

    // Handle multiple files
    if (is_array($files[$key]['name'])) {
        $count = 0;
        foreach ($files[$key]['name'] as $name) {
            if (!empty($name)) {
                $count++;
            }
        }
        return $count;
    }

    // Single file
    return !empty($files[$key]['name']) ? 1 : 0;
}
