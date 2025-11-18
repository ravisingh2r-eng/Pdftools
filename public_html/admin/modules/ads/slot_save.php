<?php
/**
 * Ad Slot Save Handler
 */

require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'ad_manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/slots_list.php');
    exit;
}

// Verify CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', 'Invalid security token.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/slots_list.php');
    exit;
}

$pdo = get_db_connection();

$id = (int)($_POST['id'] ?? 0);
$is_edit = $id > 0;

// Collect data
$data = [
    'slot_key' => trim($_POST['slot_key'] ?? ''),
    'slot_name' => trim($_POST['slot_name'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'default_code' => trim($_POST['default_code'] ?? ''),
    'is_active' => isset($_POST['is_active']) ? 1 : 0
];

// Validation
if (empty($data['slot_key']) || empty($data['slot_name'])) {
    set_flash('danger', 'Slot key and name are required.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/slot_edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}

try {
    if ($is_edit) {
        // Update
        $stmt = $pdo->prepare('
            UPDATE ad_slots SET
                slot_name = ?, description = ?, default_code = ?, is_active = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['slot_name'], $data['description'], $data['default_code'], $data['is_active'],
            $id
        ]);

        set_flash('success', 'Ad slot updated successfully.');
    } else {
        // Check if key exists
        $stmt = $pdo->prepare('SELECT id FROM ad_slots WHERE slot_key = ?');
        $stmt->execute([$data['slot_key']]);
        if ($stmt->fetch()) {
            set_flash('danger', 'A slot with this key already exists.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/ads/slot_edit.php');
            exit;
        }

        // Insert
        $stmt = $pdo->prepare('
            INSERT INTO ad_slots (slot_key, slot_name, description, default_code, is_active)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['slot_key'], $data['slot_name'], $data['description'], $data['default_code'], $data['is_active']
        ]);

        set_flash('success', 'Ad slot created successfully.');
    }

    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/slots_list.php');
    exit;

} catch (PDOException $e) {
    error_log('Ad slot save error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred while saving the ad slot.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/ads/slot_edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}
