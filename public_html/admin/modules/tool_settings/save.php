<?php
/**
 * Tool Settings Save Handler
 */

require_once __DIR__ . '/../../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;
}

// Verify CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Invalid security token.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;
}

$pdo = get_db_connection();

$tool_id = (int)($_POST['tool_id'] ?? 0);

if ($tool_id <= 0) {
    set_flash('danger', 'Invalid tool ID.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;
}

// Collect data
$data = [
    'max_file_size_mb' => max(1, (int)($_POST['max_file_size_mb'] ?? 50)),
    'max_files' => max(1, (int)($_POST['max_files'] ?? 20)),
    'allowed_extensions' => trim($_POST['allowed_extensions'] ?? 'pdf'),
    'enable_logging' => isset($_POST['enable_logging']) ? 1 : 0,
    'rate_limit_per_hour' => max(0, (int)($_POST['rate_limit_per_hour'] ?? 100)),
    'custom_notes' => trim($_POST['custom_notes'] ?? '')
];

try {
    // Check if record exists
    $stmt = $pdo->prepare('SELECT id FROM tool_settings WHERE tool_id = ?');
    $stmt->execute([$tool_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Update
        $stmt = $pdo->prepare('
            UPDATE tool_settings SET
                max_file_size_mb = ?, max_files = ?, allowed_extensions = ?,
                enable_logging = ?, rate_limit_per_hour = ?, custom_notes = ?
            WHERE tool_id = ?
        ');
        $stmt->execute([
            $data['max_file_size_mb'], $data['max_files'], $data['allowed_extensions'],
            $data['enable_logging'], $data['rate_limit_per_hour'], $data['custom_notes'],
            $tool_id
        ]);
    } else {
        // Insert
        $stmt = $pdo->prepare('
            INSERT INTO tool_settings (tool_id, max_file_size_mb, max_files, allowed_extensions,
                                      enable_logging, rate_limit_per_hour, custom_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $tool_id, $data['max_file_size_mb'], $data['max_files'], $data['allowed_extensions'],
            $data['enable_logging'], $data['rate_limit_per_hour'], $data['custom_notes']
        ]);
    }

    set_flash('success', 'Tool settings saved successfully.');

} catch (PDOException $e) {
    error_log('Tool settings save error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred while saving settings.');
}

header('Location: ' . ADMIN_BASE_URL . '/modules/tool_settings/edit.php?tool_id=' . $tool_id);
exit;
