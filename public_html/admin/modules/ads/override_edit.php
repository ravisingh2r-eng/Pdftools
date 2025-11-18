<?php
/**
 * Ad Override Edit/Create
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'ad_manager']);

$pdo = get_db_connection();

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$page_title = $is_edit ? 'Edit Ad Override' : 'Add Ad Override';

// Default values
$override = [
    'tool_id' => (int)($_GET['tool_id'] ?? 0),
    'ad_slot_id' => 0,
    'custom_code' => '',
    'is_active' => 1
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM tool_ad_overrides WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        set_flash('danger', 'Override not found.');
        header('Location: ' . ADMIN_BASE_URL . '/modules/ads/overrides_list.php');
        exit;
    }

    $override = array_merge($override, $existing);
}

// Get tools and slots for dropdowns
$tools = $pdo->query('SELECT id, name, slug FROM tools ORDER BY name ASC')->fetchAll();
$slots = $pdo->query('SELECT id, slot_key, slot_name FROM ad_slots ORDER BY slot_key ASC')->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/overrides_list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/ads/override_save.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $id; ?>">

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Override Configuration</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="tool_id" class="form-label">Tool <span class="text-danger">*</span></label>
                            <select class="form-select" id="tool_id" name="tool_id" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                                <option value="">Select Tool</option>
                                <?php foreach ($tools as $tool): ?>
                                    <option value="<?php echo $tool['id']; ?>"
                                            <?php echo $override['tool_id'] == $tool['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($tool['name']); ?> (<?php echo e($tool['slug']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($is_edit): ?>
                                <input type="hidden" name="tool_id" value="<?php echo $override['tool_id']; ?>">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="ad_slot_id" class="form-label">Ad Slot <span class="text-danger">*</span></label>
                            <select class="form-select" id="ad_slot_id" name="ad_slot_id" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                                <option value="">Select Slot</option>
                                <?php foreach ($slots as $slot): ?>
                                    <option value="<?php echo $slot['id']; ?>"
                                            <?php echo $override['ad_slot_id'] == $slot['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($slot['slot_name']); ?> (<?php echo e($slot['slot_key']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($is_edit): ?>
                                <input type="hidden" name="ad_slot_id" value="<?php echo $override['ad_slot_id']; ?>">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="custom_code" class="form-label">Custom Ad Code <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="custom_code" name="custom_code"
                                  rows="8" style="font-family: monospace;" required><?php echo e($override['custom_code']); ?></textarea>
                        <small class="text-muted">HTML/JavaScript code specific to this tool</small>
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
                               <?php echo $override['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                    <small class="text-muted">Inactive overrides won't be applied</small>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $is_edit ? 'Update Override' : 'Create Override'; ?>
                </button>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/overrides_list.php" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
