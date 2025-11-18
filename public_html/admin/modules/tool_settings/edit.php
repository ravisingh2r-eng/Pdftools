<?php
/**
 * Tool Settings Edit
 *
 * Edit processing limits, extensions, and options for a tool.
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pdo = get_db_connection();

$tool_id = (int)($_GET['tool_id'] ?? 0);

if ($tool_id <= 0) {
    set_flash('danger', 'Invalid tool ID.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;
}

// Get tool info
$stmt = $pdo->prepare('SELECT * FROM tools WHERE id = ?');
$stmt->execute([$tool_id]);
$tool = $stmt->fetch();

if (!$tool) {
    set_flash('danger', 'Tool not found.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;
}

// Get or create tool_settings
$stmt = $pdo->prepare('SELECT * FROM tool_settings WHERE tool_id = ?');
$stmt->execute([$tool_id]);
$settings = $stmt->fetch();

if (!$settings) {
    // Create default record
    $stmt = $pdo->prepare('INSERT INTO tool_settings (tool_id) VALUES (?)');
    $stmt->execute([$tool_id]);

    $settings = [
        'tool_id' => $tool_id,
        'max_file_size_mb' => 50,
        'max_files' => 20,
        'allowed_extensions' => 'pdf',
        'enable_logging' => 1,
        'rate_limit_per_hour' => 100,
        'custom_notes' => ''
    ];
}

$page_title = 'Settings: ' . $tool['name'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Tool Settings</h1>
        <small class="text-muted"><?php echo e($tool['name']); ?> (<?php echo e($tool['slug']); ?>)</small>
    </div>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Tools
    </a>
</div>

<form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/tool_settings/save.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="tool_id" value="<?php echo $tool_id; ?>">

    <div class="row">
        <div class="col-lg-8">
            <!-- File Limits -->
            <div class="card mb-4">
                <div class="card-header">File Limits</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="max_file_size_mb" class="form-label">Max File Size (MB)</label>
                            <input type="number" class="form-control" id="max_file_size_mb" name="max_file_size_mb"
                                   value="<?php echo $settings['max_file_size_mb']; ?>" min="1" max="500">
                            <small class="text-muted">Maximum size per uploaded file</small>
                        </div>
                        <div class="col-md-6">
                            <label for="max_files" class="form-label">Max Files</label>
                            <input type="number" class="form-control" id="max_files" name="max_files"
                                   value="<?php echo $settings['max_files']; ?>" min="1" max="100">
                            <small class="text-muted">Maximum number of files per request</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="allowed_extensions" class="form-label">Allowed Extensions</label>
                        <input type="text" class="form-control" id="allowed_extensions" name="allowed_extensions"
                               value="<?php echo e($settings['allowed_extensions']); ?>">
                        <small class="text-muted">Comma-separated list (e.g., pdf,jpg,png)</small>
                    </div>
                </div>
            </div>

            <!-- Processing Options -->
            <div class="card mb-4">
                <div class="card-header">Processing Options</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="rate_limit_per_hour" class="form-label">Rate Limit (per hour per IP)</label>
                            <input type="number" class="form-control" id="rate_limit_per_hour" name="rate_limit_per_hour"
                                   value="<?php echo $settings['rate_limit_per_hour']; ?>" min="0">
                            <small class="text-muted">0 = unlimited</small>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="enable_logging" name="enable_logging" value="1"
                                       <?php echo $settings['enable_logging'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_logging">Enable Usage Logging</label>
                            </div>
                            <small class="text-muted">Track usage for this tool</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="card mb-4">
                <div class="card-header">Internal Notes</div>
                <div class="card-body">
                    <textarea class="form-control" id="custom_notes" name="custom_notes"
                              rows="4"><?php echo e($settings['custom_notes']); ?></textarea>
                    <small class="text-muted">Admin notes (not shown to users)</small>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Actions</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Settings
                        </button>
                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/list.php" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Quick Links</div>
                <div class="card-body">
                    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/edit.php?id=<?php echo $tool_id; ?>"
                       class="btn btn-sm btn-outline-primary w-100 mb-2">
                        <i class="bi bi-pencil"></i> Edit Tool
                    </a>
                    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tool_content/edit.php?tool_id=<?php echo $tool_id; ?>"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-file-text"></i> Tool Content
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
