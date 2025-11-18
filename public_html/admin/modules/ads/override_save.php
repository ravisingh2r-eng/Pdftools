<?php
/**
 * Ad Override Save/Delete Handler
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'ad_manager']);

$pdo = get_db_connection();

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM tool_ad_overrides WHERE id = ?');
            $stmt->execute([$delete_id]);
            set_flash('success', 'Override deleted successfully.');
        } catch (PDOException $e) {
            error_log('Override delete error: ' . $e->getMessage());
            set_flash('danger', 'An error occurred while deleting.');
        }
    }
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/overrides_list.php');
    exit;
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/overrides_list.php');
    exit;
}

// Verify CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Invalid security token.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/overrides_list.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$is_edit = $id > 0;

// Collect data
$data = [
    'tool_id' => (int)($_POST['tool_id'] ?? 0),
    'ad_slot_id' => (int)($_POST['ad_slot_id'] ?? 0),
    'custom_code' => trim($_POST['custom_code'] ?? ''),
    'is_active' => isset($_POST['is_active']) ? 1 : 0
];

// Validation
if ($data['tool_id'] <= 0 || $data['ad_slot_id'] <= 0 || empty($data['custom_code'])) {
    set_flash('danger', 'Tool, slot, and custom code are required.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/override_edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}

try {
    if ($is_edit) {
        // Update
        $stmt = $pdo->prepare('
            UPDATE tool_ad_overrides SET
                custom_code = ?, is_active = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['custom_code'], $data['is_active'],
            $id
        ]);

        set_flash('success', 'Override updated successfully.');
    } else {
        // Check if combination exists
        $stmt = $pdo->prepare('SELECT id FROM tool_ad_overrides WHERE tool_id = ? AND ad_slot_id = ?');
        $stmt->execute([$data['tool_id'], $data['ad_slot_id']]);
        if ($stmt->fetch()) {
            set_flash('danger', 'An override for this tool and slot already exists.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/ads/override_edit.php');
            exit;
        }

        // Insert
        $stmt = $pdo->prepare('
            INSERT INTO tool_ad_overrides (tool_id, ad_slot_id, custom_code, is_active)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['tool_id'], $data['ad_slot_id'], $data['custom_code'], $data['is_active']
        ]);

        set_flash('success', 'Override created successfully.');
    }

    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/overrides_list.php');
    exit;

} catch (PDOException $e) {
    error_log('Override save error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred while saving.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/override_edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}
