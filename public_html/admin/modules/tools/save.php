<?php
/**
 * Tool Save Handler
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

$id = (int)($_POST['id'] ?? 0);
$is_edit = $id > 0;

// Collect form data
$data = [
    'slug' => trim($_POST['slug'] ?? ''),
    'name' => trim($_POST['name'] ?? ''),
    'category' => trim($_POST['category'] ?? 'basic'),
    'icon' => trim($_POST['icon'] ?? ''),
    'color' => trim($_POST['color'] ?? '#4CAF50'),
    'short_description' => trim($_POST['short_description'] ?? ''),
    'long_description' => trim($_POST['long_description'] ?? ''),
    'is_active' => isset($_POST['is_active']) ? 1 : 0,
    'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    'is_premium' => isset($_POST['is_premium']) ? 1 : 0,
    'sort_order' => (int)($_POST['sort_order'] ?? 100)
];

// Validation
if (empty($data['slug']) || empty($data['name'])) {
    set_flash('danger', 'Slug and name are required.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}

// Validate slug format
if (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
    set_flash('danger', 'Slug must contain only lowercase letters, numbers, and hyphens.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}

try {
    if ($is_edit) {
        // Update existing tool
        $stmt = $pdo->prepare('
            UPDATE tools SET
                name = ?, category = ?, icon = ?, color = ?,
                short_description = ?, long_description = ?,
                is_active = ?, is_featured = ?, is_premium = ?, sort_order = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $data['name'], $data['category'], $data['icon'], $data['color'],
            $data['short_description'], $data['long_description'],
            $data['is_active'], $data['is_featured'], $data['is_premium'], $data['sort_order'],
            $id
        ]);

        set_flash('success', 'Tool updated successfully.');
    } else {
        // Check if slug already exists
        $stmt = $pdo->prepare('SELECT id FROM tools WHERE slug = ?');
        $stmt->execute([$data['slug']]);
        if ($stmt->fetch()) {
            set_flash('danger', 'A tool with this slug already exists.');
            header('Location: ' . ADMIN_BASE_URL . '/modules/tools/edit.php');
            exit;
        }

        // Insert new tool
        $stmt = $pdo->prepare('
            INSERT INTO tools (slug, name, category, icon, color, short_description, long_description,
                              is_active, is_featured, is_premium, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['slug'], $data['name'], $data['category'], $data['icon'], $data['color'],
            $data['short_description'], $data['long_description'],
            $data['is_active'], $data['is_featured'], $data['is_premium'], $data['sort_order']
        ]);

        $id = $pdo->lastInsertId();

        // Create default tool_content and tool_settings records
        $stmt = $pdo->prepare('INSERT INTO tool_content (tool_id) VALUES (?)');
        $stmt->execute([$id]);

        $stmt = $pdo->prepare('INSERT INTO tool_settings (tool_id) VALUES (?)');
        $stmt->execute([$id]);

        set_flash('success', 'Tool created successfully.');
    }

    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;

} catch (PDOException $e) {
    error_log('Tool save error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred while saving the tool.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/edit.php' . ($is_edit ? "?id=$id" : ''));
    exit;
}
