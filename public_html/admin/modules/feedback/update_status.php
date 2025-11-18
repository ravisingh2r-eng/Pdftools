<?php
/**
 * Feedback Update Handler
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/list.php');
    exit;
}

// Verify CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Invalid security token.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/list.php');
    exit;
}

$pdo = get_db_connection();

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id <= 0) {
    set_flash('danger', 'Invalid feedback ID.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/list.php');
    exit;
}

try {
    switch ($action) {
        case 'update_status':
            $status = $_POST['status'] ?? 'new';
            if (!in_array($status, ['new', 'in_review', 'done'])) {
                $status = 'new';
            }

            $stmt = $pdo->prepare('UPDATE feedback SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);

            set_flash('success', 'Status updated successfully.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/view.php?id=' . $id);
            exit;

        case 'save_notes':
            $notes = trim($_POST['admin_notes'] ?? '');

            $stmt = $pdo->prepare('UPDATE feedback SET admin_notes = ? WHERE id = ?');
            $stmt->execute([$notes, $id]);

            set_flash('success', 'Notes saved successfully.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/view.php?id=' . $id);
            exit;

        case 'delete':
            $stmt = $pdo->prepare('DELETE FROM feedback WHERE id = ?');
            $stmt->execute([$id]);

            set_flash('success', 'Feedback deleted successfully.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/list.php');
            exit;

        default:
            set_flash('danger', 'Invalid action.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/list.php');
            exit;
    }

} catch (PDOException $e) {
    error_log('Feedback update error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/view.php?id=' . $id);
    exit;
}
