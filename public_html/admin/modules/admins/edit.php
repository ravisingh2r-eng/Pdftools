<?php
/**
 * Admin Edit/Create
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role('super_admin');

$pdo = get_db_connection();

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$page_title = $is_edit ? 'Edit Administrator' : 'Add Administrator';

// Default values
$admin = [
    'name' => '',
    'email' => '',
    'role' => 'editor',
    'is_active' => 1
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        set_flash('danger', 'Administrator not found.');
        header('Location: ' . ADMIN_BASE_URL . '/modules/admins/list.php');
        exit;
    }

    $admin = array_merge($admin, $existing);
}

// Roles
$roles = [
    'super_admin' => 'Super Admin',
    'editor' => 'Editor',
    'ad_manager' => 'Ad Manager'
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/admins/list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/admins/save.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $id; ?>">

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Account Information</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?php echo e($admin['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo e($admin['email']); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">
                                Password
                                <?php if (!$is_edit): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                   <?php echo !$is_edit ? 'required' : ''; ?> minlength="6">
                            <?php if ($is_edit): ?>
                                <small class="text-muted">Leave blank to keep current password</small>
                            <?php else: ?>
                                <small class="text-muted">Minimum 6 characters</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="password_confirm" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Role & Status</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role"
                                <?php echo $id == $_SESSION['admin_id'] ? 'disabled' : ''; ?>>
                            <?php foreach ($roles as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $admin['role'] === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($id == $_SESSION['admin_id']): ?>
                            <input type="hidden" name="role" value="<?php echo $admin['role']; ?>">
                            <small class="text-muted">Cannot change your own role</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   <?php echo $admin['is_active'] ? 'checked' : ''; ?>
                                   <?php echo $id == $_SESSION['admin_id'] ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        <?php if ($id == $_SESSION['admin_id']): ?>
                            <input type="hidden" name="is_active" value="1">
                            <small class="text-muted">Cannot deactivate your own account</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $is_edit ? 'Update Admin' : 'Create Admin'; ?>
                </button>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/admins/list.php" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
