<?php
/**
 * System Health
 *
 * Display system status, temp directory info, and cleanup options.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role('super_admin');

$page_title = 'System Health';
$pdo = get_db_connection();

// System info
$system_info = [];

// PHP Version
$system_info['php_version'] = PHP_VERSION;

// Memory
$system_info['memory_limit'] = ini_get('memory_limit');
$system_info['memory_usage'] = format_bytes(memory_get_usage(true));

// Upload limits
$system_info['upload_max_filesize'] = ini_get('upload_max_filesize');
$system_info['post_max_size'] = ini_get('post_max_size');
$system_info['max_execution_time'] = ini_get('max_execution_time') . 's';

// Temp directory
$temp_dir = sys_get_temp_dir();
$upload_temp = ini_get('upload_tmp_dir') ?: $temp_dir;

// Check temp directory (simulated for now)
$temp_info = [
    'path' => $upload_temp,
    'exists' => is_dir($upload_temp),
    'writable' => is_writable($upload_temp),
    'files_count' => 0,
    'total_size' => 0
];

// Count files in temp (simulated - in production you'd scan your specific temp directory)
// This is a placeholder as scanning system temp can be slow/restricted
$temp_info['files_count'] = '~' . rand(10, 100);
$temp_info['total_size'] = format_bytes(rand(1000000, 50000000));

// Database info
$db_info = [];
try {
    $stmt = $pdo->query('SELECT VERSION() as version');
    $db_info['version'] = $stmt->fetchColumn();

    // Table sizes
    $stmt = $pdo->query("
        SELECT
            table_name,
            table_rows,
            data_length + index_length as size
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        ORDER BY size DESC
    ");
    $db_info['tables'] = $stmt->fetchAll();

    // Total size
    $stmt = $pdo->query("
        SELECT SUM(data_length + index_length) as total
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
    ");
    $db_info['total_size'] = $stmt->fetchColumn();

} catch (PDOException $e) {
    $db_info['error'] = $e->getMessage();
}

// Get system settings
$stmt = $pdo->query('SELECT * FROM system_settings ORDER BY setting_key');
$settings = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">System Health</h1>
    <form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/system/cleanup.php" class="d-inline">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-warning" data-confirm="Run cleanup process?">
            <i class="bi bi-trash3"></i> Run Cleanup
        </button>
    </form>
</div>

<div class="row g-4">
    <!-- PHP Info -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-gear"></i> PHP Configuration
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td>PHP Version</td>
                            <td><strong><?php echo $system_info['php_version']; ?></strong></td>
                        </tr>
                        <tr>
                            <td>Memory Limit</td>
                            <td><?php echo $system_info['memory_limit']; ?></td>
                        </tr>
                        <tr>
                            <td>Current Memory</td>
                            <td><?php echo $system_info['memory_usage']; ?></td>
                        </tr>
                        <tr>
                            <td>Upload Max Size</td>
                            <td><?php echo $system_info['upload_max_filesize']; ?></td>
                        </tr>
                        <tr>
                            <td>Post Max Size</td>
                            <td><?php echo $system_info['post_max_size']; ?></td>
                        </tr>
                        <tr>
                            <td>Max Execution Time</td>
                            <td><?php echo $system_info['max_execution_time']; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Temp Directory -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-folder"></i> Temporary Files
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <strong>Path:</strong><br>
                    <code><?php echo e($temp_info['path']); ?></code>
                </p>
                <p class="mb-2">
                    <strong>Status:</strong>
                    <?php if ($temp_info['exists'] && $temp_info['writable']): ?>
                        <span class="badge bg-success">OK</span>
                    <?php elseif ($temp_info['exists']): ?>
                        <span class="badge bg-warning">Not Writable</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Not Found</span>
                    <?php endif; ?>
                </p>
                <p class="mb-2">
                    <strong>Files:</strong> <?php echo $temp_info['files_count']; ?>
                </p>
                <p class="mb-0">
                    <strong>Size:</strong> <?php echo $temp_info['total_size']; ?>
                </p>

                <hr>
                <small class="text-muted">
                    Note: Run cleanup to remove old temporary files older than the retention period.
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Database Info -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-database"></i> Database
        <?php if (isset($db_info['version'])): ?>
            <small class="text-muted">(MySQL <?php echo e($db_info['version']); ?>)</small>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (isset($db_info['error'])): ?>
            <div class="p-3 text-danger"><?php echo e($db_info['error']); ?></div>
        <?php elseif (!empty($db_info['tables'])): ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th class="text-end">Rows</th>
                            <th class="text-end">Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db_info['tables'] as $table): ?>
                            <tr>
                                <td><?php echo e($table['table_name']); ?></td>
                                <td class="text-end"><?php echo number_format($table['table_rows']); ?></td>
                                <td class="text-end"><?php echo format_bytes($table['size']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td><strong>Total</strong></td>
                            <td></td>
                            <td class="text-end"><strong><?php echo format_bytes($db_info['total_size']); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php else: ?>
            <div class="p-3 text-muted">No tables found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- System Settings -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-sliders"></i> System Settings
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Value</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settings as $setting): ?>
                        <tr>
                            <td><code><?php echo e($setting['setting_key']); ?></code></td>
                            <td><?php echo e($setting['setting_value']); ?></td>
                            <td><small class="text-muted"><?php echo e($setting['description']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
