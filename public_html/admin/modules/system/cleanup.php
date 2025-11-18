<?php
/**
 * System Cleanup
 *
 * Clean up old temporary files and logs.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_BASE_URL . '/modules/system/health.php');
    exit;
}

// Verify CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Invalid security token.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/system/health.php');
    exit;
}

$pdo = get_db_connection();

$cleanup_results = [];

try {
    // Get retention period from settings
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'temp_file_retention_hours'");
    $stmt->execute();
    $retention_hours = (int)($stmt->fetchColumn() ?: 24);

    // 1. Clean up old usage logs (keep 90 days)
    $stmt = $pdo->prepare('DELETE FROM usage_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
    $stmt->execute();
    $cleanup_results['old_logs'] = $stmt->rowCount();

    // 2. Clean up temporary files
    // In a real implementation, you would scan your uploads/temp directory
    // This is a placeholder that simulates the cleanup
    $temp_dir = PUBLIC_ROOT . '/uploads/temp';

    $files_deleted = 0;
    $bytes_freed = 0;

    if (is_dir($temp_dir)) {
        $cutoff_time = time() - ($retention_hours * 3600);

        $files = new DirectoryIterator($temp_dir);
        foreach ($files as $file) {
            if ($file->isDot() || $file->isDir()) continue;

            if ($file->getMTime() < $cutoff_time) {
                $size = $file->getSize();
                if (@unlink($file->getPathname())) {
                    $files_deleted++;
                    $bytes_freed += $size;
                }
            }
        }
    }

    $cleanup_results['temp_files'] = $files_deleted;
    $cleanup_results['bytes_freed'] = $bytes_freed;

    // 3. Optimize tables (optional - can be slow on large tables)
    // Uncomment if needed:
    // $pdo->query('OPTIMIZE TABLE usage_logs, feedback');

    // Build success message
    $messages = [];

    if ($cleanup_results['old_logs'] > 0) {
        $messages[] = "Deleted {$cleanup_results['old_logs']} old log entries";
    }

    if ($cleanup_results['temp_files'] > 0) {
        $freed = format_bytes($cleanup_results['bytes_freed']);
        $messages[] = "Deleted {$cleanup_results['temp_files']} temp files ({$freed})";
    }

    if (empty($messages)) {
        set_flash('info', 'Cleanup completed. No items needed to be cleaned up.');
    } else {
        set_flash('success', 'Cleanup completed: ' . implode(', ', $messages) . '.');
    }

} catch (Exception $e) {
    error_log('Cleanup error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred during cleanup: ' . $e->getMessage());
}

header('Location: ' . ADMIN_BASE_URL . '/modules/system/health.php');
exit;
