<?php
/**
 * Ad Overrides List
 *
 * Manage per-tool ad code overrides.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'ad_manager']);

$page_title = 'Ad Overrides';
$pdo = get_db_connection();

// Get all overrides with tool and slot info
$stmt = $pdo->query('
    SELECT tao.*, t.name as tool_name, t.slug as tool_slug, ads.slot_key, ads.slot_name
    FROM tool_ad_overrides tao
    JOIN tools t ON tao.tool_id = t.id
    JOIN ad_slots ads ON tao.ad_slot_id = ads.id
    ORDER BY t.name ASC, ads.slot_key ASC
');
$overrides = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Ad Overrides</h1>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/override_edit.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Override
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($overrides)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-sliders fs-1"></i>
                <p class="mt-2 mb-0">No ad overrides defined.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Slot</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overrides as $override): ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($override['tool_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo e($override['tool_slug']); ?></small>
                                </td>
                                <td>
                                    <?php echo e($override['slot_name']); ?>
                                    <br><code class="small"><?php echo e($override['slot_key']); ?></code>
                                </td>
                                <td>
                                    <?php if ($override['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo time_ago($override['updated_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/override_edit.php?id=<?php echo $override['id']; ?>"
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/override_save.php?delete=<?php echo $override['id']; ?>"
                                           class="btn btn-outline-danger" title="Delete"
                                           data-confirm="Delete this override?">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-4">
    <div class="alert alert-info">
        <strong><i class="bi bi-info-circle"></i> How Overrides Work</strong><br>
        <small>
            When a tool page loads, it checks for an override for each ad slot.
            If found and active, the custom code is used instead of the slot's default code.
        </small>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
