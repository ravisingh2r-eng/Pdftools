<?php
/**
 * Tool Delete Handler
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid tool ID.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;
}

$pdo = get_db_connection();

try {
    // Check if tool exists
    $stmt = $pdo->prepare('SELECT name FROM tools WHERE id = ?');
    $stmt->execute([$id]);
    $tool = $stmt->fetch();

    if (!$tool) {
        set_flash('danger', 'Tool not found.');
        header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
        exit;
    }

    // Delete tool (cascades to tool_content, tool_settings, tool_ad_overrides)
    $stmt = $pdo->prepare('DELETE FROM tools WHERE id = ?');
    $stmt->execute([$id]);

    set_flash('success', 'Tool "' . $tool['name'] . '" deleted successfully.');

} catch (PDOException $e) {
    error_log('Tool delete error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred while deleting the tool.');
}

header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
exit;
