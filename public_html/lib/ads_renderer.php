<?php
/**
 * Ads Renderer
 *
 * Retrieves and renders ad codes from the database.
 * Supports global ad slots and per-tool overrides.
 */

/**
 * Get ad code for a specific slot
 *
 * Checks for tool-specific override first, then falls back to default slot code.
 *
 * @param string $toolSlug  The tool identifier (e.g., 'merge-pdf')
 * @param string $slotKey   The ad slot key (e.g., 'header_banner')
 * @return string           The ad code HTML, or empty string if not found
 */
function get_ad_code(string $toolSlug, string $slotKey): string {
    try {
        // Get database connection
        $pdo = get_ads_db_connection();
        if (!$pdo) {
            return '';
        }

        // First, try to get tool-specific override
        if ($toolSlug) {
            $stmt = $pdo->prepare('
                SELECT tao.custom_code
                FROM tool_ad_overrides tao
                JOIN tools t ON tao.tool_id = t.id
                JOIN ad_slots ads ON tao.ad_slot_id = ads.id
                WHERE t.slug = ?
                  AND ads.slot_key = ?
                  AND tao.is_active = 1
                  AND ads.is_active = 1
                LIMIT 1
            ');
            $stmt->execute([$toolSlug, $slotKey]);
            $override = $stmt->fetchColumn();

            if ($override !== false && $override !== null) {
                return $override;
            }
        }

        // Fall back to default slot code
        $stmt = $pdo->prepare('
            SELECT default_code
            FROM ad_slots
            WHERE slot_key = ?
              AND is_active = 1
            LIMIT 1
        ');
        $stmt->execute([$slotKey]);
        $defaultCode = $stmt->fetchColumn();

        if ($defaultCode !== false && $defaultCode !== null) {
            return $defaultCode;
        }

        return '';

    } catch (PDOException $e) {
        // Log error but don't break the page
        error_log('Ads renderer error: ' . $e->getMessage());
        return '';
    } catch (Exception $e) {
        error_log('Ads renderer error: ' . $e->getMessage());
        return '';
    }
}

/**
 * Render an ad slot with wrapper div
 *
 * @param string $toolSlug  The tool identifier
 * @param string $slotKey   The ad slot key
 * @param string $cssClass  Additional CSS class for the wrapper
 * @return string           Complete HTML with wrapper div
 */
function render_ad_slot(string $toolSlug, string $slotKey, string $cssClass = ''): string {
    $code = get_ad_code($toolSlug, $slotKey);

    if (empty($code)) {
        return '';
    }

    $class = 'ad-slot ad-' . htmlspecialchars($slotKey, ENT_QUOTES, 'UTF-8');
    if ($cssClass) {
        $class .= ' ' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8');
    }

    return '<div class="' . $class . '">' . $code . '</div>';
}

/**
 * Get all ad codes for a tool at once (more efficient)
 *
 * @param string $toolSlug  The tool identifier
 * @param array  $slotKeys  Array of slot keys to fetch
 * @return array            Associative array of slot_key => code
 */
function get_all_ad_codes(string $toolSlug, array $slotKeys): array {
    $codes = array_fill_keys($slotKeys, '');

    try {
        $pdo = get_ads_db_connection();
        if (!$pdo) {
            return $codes;
        }

        // Get all default codes first
        $placeholders = implode(',', array_fill(0, count($slotKeys), '?'));
        $stmt = $pdo->prepare("
            SELECT slot_key, default_code
            FROM ad_slots
            WHERE slot_key IN ($placeholders)
              AND is_active = 1
        ");
        $stmt->execute($slotKeys);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $codes[$row['slot_key']] = $row['default_code'];
        }

        // Override with tool-specific codes if available
        if ($toolSlug) {
            $stmt = $pdo->prepare("
                SELECT ads.slot_key, tao.custom_code
                FROM tool_ad_overrides tao
                JOIN tools t ON tao.tool_id = t.id
                JOIN ad_slots ads ON tao.ad_slot_id = ads.id
                WHERE t.slug = ?
                  AND ads.slot_key IN ($placeholders)
                  AND tao.is_active = 1
                  AND ads.is_active = 1
            ");
            $stmt->execute(array_merge([$toolSlug], $slotKeys));

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $codes[$row['slot_key']] = $row['custom_code'];
            }
        }

        return $codes;

    } catch (PDOException $e) {
        error_log('Ads renderer error: ' . $e->getMessage());
        return $codes;
    } catch (Exception $e) {
        error_log('Ads renderer error: ' . $e->getMessage());
        return $codes;
    }
}

/**
 * Get database connection for ads
 *
 * Creates or returns existing PDO connection.
 * Fails silently if database is not configured.
 *
 * @return PDO|null
 */
function get_ads_db_connection(): ?PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // Check if we already have a connection function from config
        if (function_exists('get_db_connection')) {
            $pdo = get_db_connection();
            return $pdo;
        }

        // Try to create connection using constants
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
        return $pdo;

    } catch (PDOException $e) {
        // Database not configured yet - fail silently
        error_log('Ads DB connection error: ' . $e->getMessage());
        return null;
    }
}
