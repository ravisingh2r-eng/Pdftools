<?php
/**
 * Ad Slots List
 *
 * Manage global ad slot definitions.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'ad_manager']);

$page_title = 'Ad Slots';
$pdo = get_db_connection();

// Get all ad slots
$stmt = $pdo->query('SELECT * FROM ad_slots ORDER BY slot_key ASC');
$slots = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Ad Slots</h1>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/slot_edit.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Slot
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($slots)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-megaphone fs-1"></i>
                <p class="mt-2 mb-0">No ad slots defined.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Slot Key</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slots as $slot): ?>
                            <tr>
                                <td><code><?php echo e($slot['slot_key']); ?></code></td>
                                <td><?php echo e($slot['slot_name']); ?></td>
                                <td><small class="text-muted"><?php echo e(substr($slot['description'], 0, 50)); ?></small></td>
                                <td>
                                    <?php if ($slot['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/slot_edit.php?id=<?php echo $slot['id']; ?>"
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
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
        <strong><i class="bi bi-info-circle"></i> Usage</strong><br>
        <small>
            Use the <code>slot_key</code> in your tool templates to display ads.
            Per-tool overrides can be set in the <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/overrides_list.php">Ad Overrides</a> section.
        </small>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
