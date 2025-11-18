<?php
/**
 * Administrators List
 *
 * Manage admin users (super_admin only).
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role('super_admin');

$page_title = 'Administrators';
$pdo = get_db_connection();

// Get all admins
$stmt = $pdo->query('SELECT * FROM admins ORDER BY role, name ASC');
$admins = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Administrators</h1>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/admins/edit.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Admin
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($admins)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-people fs-1"></i>
                <p class="mt-2 mb-0">No administrators found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr <?php echo $admin['id'] == $_SESSION['admin_id'] ? 'class="table-light"' : ''; ?>>
                                <td>
                                    <strong><?php echo e($admin['name']); ?></strong>
                                    <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                        <span class="badge bg-info">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($admin['email']); ?></td>
                                <td>
                                    <?php
                                    $role_badges = [
                                        'super_admin' => 'bg-danger',
                                        'editor' => 'bg-primary',
                                        'ad_manager' => 'bg-warning'
                                    ];
                                    $badge_class = $role_badges[$admin['role']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo e(ucwords(str_replace('_', ' ', $admin['role']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($admin['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($admin['last_login']): ?>
                                        <?php echo time_ago($admin['last_login']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/admins/edit.php?id=<?php echo $admin['id']; ?>"
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <a href="<?php echo ADMIN_BASE_URL; ?>/modules/admins/save.php?delete=<?php echo $admin['id']; ?>"
                                               class="btn btn-outline-danger" title="Delete"
                                               data-confirm="Are you sure you want to delete this administrator?">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
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
        <strong><i class="bi bi-info-circle"></i> Roles</strong><br>
        <small>
            <strong>Super Admin:</strong> Full access to all features including system settings and admin management.<br>
            <strong>Editor:</strong> Manage tools, content, and feedback.<br>
            <strong>Ad Manager:</strong> Manage ad slots and overrides, view analytics.
        </small>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
