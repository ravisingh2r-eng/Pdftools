#!/usr/bin/env php
<?php
/**
 * Temporary Files Cleanup Script
 *
 * Removes old temporary files from the upload/temp directory.
 * Should be run via cron every 15-30 minutes.
 *
 * Cron example (every 15 minutes):
 * * /15 * * * * /usr/bin/php /path/to/public_html/cron/cleanup_temp.php >> /path/to/logs/cleanup.log 2>&1
 *
 * Usage:
 *   php cleanup_temp.php              # Default: delete files older than 1 hour
 *   php cleanup_temp.php --age=30     # Delete files older than 30 minutes
 *   php cleanup_temp.php --dry-run    # Show what would be deleted without deleting
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script must be run from command line.');
}

// Parse command line arguments
$options = getopt('', ['age::', 'dry-run']);
$maxAgeMinutes = isset($options['age']) ? (int)$options['age'] : 60;
$dryRun = isset($options['dry-run']);

// Validate age
if ($maxAgeMinutes < 5) {
    $maxAgeMinutes = 5; // Minimum 5 minutes to prevent accidents
}

// Configuration
$tempDirs = [
    __DIR__ . '/../uploads/temp',
    __DIR__ . '/../temp',
    '/tmp/pdf_tools'  // Alternative temp location
];

// Log function
function log_message(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}" . PHP_EOL;
}

// Start cleanup
log_message("Starting temp file cleanup (max age: {$maxAgeMinutes} minutes)");
if ($dryRun) {
    log_message("DRY RUN MODE - No files will be deleted");
}

$totalDeleted = 0;
$totalSize = 0;
$errors = 0;

foreach ($tempDirs as $tempDir) {
    // Skip if directory doesn't exist
    if (!is_dir($tempDir)) {
        continue;
    }

    log_message("Scanning: {$tempDir}");

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $cutoffTime = time() - ($maxAgeMinutes * 60);
        $filesToDelete = [];
        $dirsToDelete = [];

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $mtime = $file->getMTime();

            // Skip if file is newer than cutoff
            if ($mtime > $cutoffTime) {
                continue;
            }

            if ($file->isFile()) {
                $filesToDelete[] = [
                    'path' => $path,
                    'size' => $file->getSize(),
                    'age' => round((time() - $mtime) / 60)
                ];
            } elseif ($file->isDir()) {
                $dirsToDelete[] = $path;
            }
        }

        // Delete files
        foreach ($filesToDelete as $fileInfo) {
            $path = $fileInfo['path'];
            $size = $fileInfo['size'];
            $age = $fileInfo['age'];

            if ($dryRun) {
                log_message("  [DRY] Would delete: {$path} ({$age} min old, " . format_size($size) . ")");
                $totalDeleted++;
                $totalSize += $size;
            } else {
                if (@unlink($path)) {
                    log_message("  Deleted: {$path} ({$age} min old, " . format_size($size) . ")");
                    $totalDeleted++;
                    $totalSize += $size;
                } else {
                    log_message("  ERROR: Failed to delete: {$path}");
                    $errors++;
                }
            }
        }

        // Delete empty directories (in reverse order - deepest first)
        $dirsToDelete = array_reverse($dirsToDelete);
        foreach ($dirsToDelete as $dir) {
            // Only delete if empty
            if (is_dir($dir) && count(scandir($dir)) === 2) {
                if ($dryRun) {
                    log_message("  [DRY] Would remove empty dir: {$dir}");
                } else {
                    if (@rmdir($dir)) {
                        log_message("  Removed empty dir: {$dir}");
                    }
                }
            }
        }

    } catch (Exception $e) {
        log_message("ERROR scanning {$tempDir}: " . $e->getMessage());
        $errors++;
    }
}

// Also clean up PHP session files older than 24 hours (if accessible)
$sessionPath = session_save_path();
if ($sessionPath && is_dir($sessionPath) && is_writable($sessionPath)) {
    log_message("Scanning session directory: {$sessionPath}");

    $sessionCutoff = time() - (24 * 60 * 60); // 24 hours

    foreach (glob($sessionPath . '/sess_*') as $sessionFile) {
        if (filemtime($sessionFile) < $sessionCutoff) {
            if ($dryRun) {
                log_message("  [DRY] Would delete old session: " . basename($sessionFile));
            } else {
                @unlink($sessionFile);
            }
        }
    }
}

// Summary
log_message("Cleanup complete:");
log_message("  Files deleted: {$totalDeleted}");
log_message("  Space freed: " . format_size($totalSize));
if ($errors > 0) {
    log_message("  Errors: {$errors}");
}

// Helper function
function format_size(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Exit with error code if there were errors
exit($errors > 0 ? 1 : 0);
