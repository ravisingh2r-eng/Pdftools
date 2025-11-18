<?php
/**
 * Tool Content Save Handler
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

// Build FAQ JSON
$faq_json = '[]';
if (!empty($_POST['faq_q']) && is_array($_POST['faq_q'])) {
    $faq_items = [];
    foreach ($_POST['faq_q'] as $index => $question) {
        $question = trim($question);
        $answer = trim($_POST['faq_a'][$index] ?? '');
        if ($question && $answer) {
            $faq_items[] = ['q' => $question, 'a' => $answer];
        }
    }
    $faq_json = json_encode($faq_items, JSON_UNESCAPED_UNICODE);
}

// Collect data
$data = [
    'meta_title' => trim($_POST['meta_title'] ?? ''),
    'meta_description' => trim($_POST['meta_description'] ?? ''),
    'primary_keyword' => trim($_POST['primary_keyword'] ?? ''),
    'intro_html' => trim($_POST['intro_html'] ?? ''),
    'how_it_works_html' => trim($_POST['how_it_works_html'] ?? ''),
    'features_html' => trim($_POST['features_html'] ?? ''),
    'faq_json' => $faq_json,
    'schema_json' => trim($_POST['schema_json'] ?? ''),
    'og_image_url' => trim($_POST['og_image_url'] ?? '')
];

try {
    // Check if record exists
    $stmt = $pdo->prepare('SELECT id FROM tool_content WHERE tool_id = ?');
    $stmt->execute([$tool_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Update
        $stmt = $pdo->prepare('
            UPDATE tool_content SET
                meta_title = ?, meta_description = ?, primary_keyword = ?,
                intro_html = ?, how_it_works_html = ?, features_html = ?,
                faq_json = ?, schema_json = ?, og_image_url = ?
            WHERE tool_id = ?
        ');
        $stmt->execute([
            $data['meta_title'], $data['meta_description'], $data['primary_keyword'],
            $data['intro_html'], $data['how_it_works_html'], $data['features_html'],
            $data['faq_json'], $data['schema_json'], $data['og_image_url'],
            $tool_id
        ]);
    } else {
        // Insert
        $stmt = $pdo->prepare('
            INSERT INTO tool_content (tool_id, meta_title, meta_description, primary_keyword,
                                     intro_html, how_it_works_html, features_html,
                                     faq_json, schema_json, og_image_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $tool_id, $data['meta_title'], $data['meta_description'], $data['primary_keyword'],
            $data['intro_html'], $data['how_it_works_html'], $data['features_html'],
            $data['faq_json'], $data['schema_json'], $data['og_image_url']
        ]);
    }

    set_flash('success', 'Tool content saved successfully.');

} catch (PDOException $e) {
    error_log('Tool content save error: ' . $e->getMessage());
    set_flash('danger', 'An error occurred while saving content.');
}

header('Location: ' . ADMIN_BASE_URL . '/modules/tool_content/edit.php?tool_id=' . $tool_id);
exit;
