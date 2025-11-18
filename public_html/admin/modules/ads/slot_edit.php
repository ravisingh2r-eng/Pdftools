<?php
/**
 * Ad Slot Edit/Create
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'ad_manager']);

$pdo = get_db_connection();

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$page_title = $is_edit ? 'Edit Ad Slot' : 'Add Ad Slot';

// Default values
$slot = [
    'slot_key' => '',
    'slot_name' => '',
    'description' => '',
    'default_code' => '',
    'is_active' => 1
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM ad_slots WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        set_flash('danger', 'Ad slot not found.');
        header('Location: ' . ADMIN_BASE_URL . '/modules/ads/slots_list.php');
        exit;
    }

    $slot = array_merge($slot, $existing);
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/slots_list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/ads/slot_save.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $id; ?>">

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Slot Information</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="slot_key" class="form-label">Slot Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="slot_key" name="slot_key"
                                   value="<?php echo e($slot['slot_key']); ?>" required
                                   pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only"
                                   <?php echo $is_edit ? 'readonly' : ''; ?>>
                            <small class="text-muted">Identifier (e.g., header_banner)</small>
                        </div>
                        <div class="col-md-6">
                            <label for="slot_name" class="form-label">Display Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="slot_name" name="slot_name"
                                   value="<?php echo e($slot['slot_name']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="2"><?php echo e($slot['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="default_code" class="form-label">Default Ad Code</label>
                        <textarea class="form-control" id="default_code" name="default_code"
                                  rows="6" style="font-family: monospace;"><?php echo e($slot['default_code']); ?></textarea>
                        <small class="text-muted">HTML/JavaScript code for this ad slot</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Status</div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               <?php echo $slot['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <small class="text-muted">Inactive slots won't display ads</small>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $is_edit ? 'Update Slot' : 'Create Slot'; ?>
                </button>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/slots_list.php" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
