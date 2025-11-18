<?php
/**
 * Admin Save/Delete Handler
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role('super_admin');

$pdo = get_db_connection();

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];

    // Cannot delete yourself
    if ($delete_id == $_SESSION['admin_id']) {
        set_flash('danger', 'You cannot delete your own account.');
        header('Location: ' . ADMIN_BASE_URL . '/modules/admins/list.php');
        exit;
    }

    if ($delete_id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM admins WHERE id = ?');
            $stmt->execute([$delete_id]);
            set_flash('success', 'Administrator deleted successfully.');
        } catch (PDOException $e) {
            error_log('Admin delete error: ' . $e->getMessage());
            set_flash('danger', 'An error occurred while deleting.');
        }
    }
    header('Location: ' . ADMIN_BASE_URL . '/modules/admins/list.php');
    exit;
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_BASE_URL . '/modules/admins/list.php');
    exit;
}

// Verify CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Invalid security token.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/admins/list.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$is_edit = $id > 0;

// Collect data
$data = [
    'name' => trim($_POST['name'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    'role' => $_POST['role'] ?? 'editor',
    'is_active' => isset($_POST['is_active']) ? 1 : 0
];

$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Validation
if (empty($data['name']) || empty($data['email'])) {
    set_flash('danger', 'Name and email are required.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/admins/edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    set_flash('danger', 'Invalid email address.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/admins/edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}

// Validate role
$valid_roles = ['super_admin', 'editor', 'ad_manager'];
if (!in_array($data['role'], $valid_roles)) {
    $data['role'] = 'editor';
}

// Password validation
if (!$is_edit && empty($password)) {
    set_flash('danger', 'Password is required for new administrators.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/admins/edit.php');
    exit;
}

if ($password) {
    if (strlen($password) < 6) {
        set_flash('danger', 'Password must be at least 6 characters.');
        header('Location: ' . ADMIN_BASE_URL . '/modules/admins/edit.php' . ($is_edit ? "?id=$id" : ''));
        exit;
    }

    if ($password !== $password_confirm) {
        set_flash('danger', 'Passwords do not match.');
        header('Location: ' . ADMIN_BASE_URL . '/modules/admins/edit.php' . ($is_edit ? "?id=$id" : ''));
        exit;
    }
}

try {
    if ($is_edit) {
        // Check email uniqueness
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ? AND id != ?');
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            set_flash('danger', 'An administrator with this email already exists.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/admins/edit.php?id=' . $id);
            exit;
        }

        // Update
        if ($password) {
            $stmt = $pdo->prepare('
                UPDATE admins SET
                    name = ?, email = ?, password = ?, role = ?, is_active = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $data['name'], $data['email'], password_hash($password, PASSWORD_DEFAULT),
                $data['role'], $data['is_active'], $id
            ]);
        } else {
            $stmt = $pdo->prepare('
                UPDATE admins SET
                    name = ?, email = ?, role = ?, is_active = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $data['name'], $data['email'], $data['role'], $data['is_active'], $id
            ]);
        }

        set_flash('success', 'Administrator updated successfully.');
    } else {
        // Check email uniqueness
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            set_flash('danger', 'An administrator with this email already exists.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/admins/edit.php');
            exit;
        }

        // Insert
        $stmt = $pdo->prepare('
            INSERT INTO admins (name, email, password, role, is_active)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['name'], $data['email'], password_hash($password, PASSWORD_DEFAULT),
            $data['role'], $data['is_active']
        ]);

        set_flash('success', 'Administrator created successfully.');
    }

    header('Location: ' . ADMIN_BASE_URL . '/modules/admins/list.php');
    exit;

} catch (PDOException $e) {
    error_log('Admin save error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred while saving.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/admins/edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}
