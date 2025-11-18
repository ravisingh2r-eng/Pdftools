<?php
/**
 * Tool Content Edit
 *
 * Edit SEO, intro, features, FAQ, and schema for a tool.
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pdo = get_db_connection();

$tool_id = (int)($_GET['tool_id'] ?? 0);

if ($tool_id <= 0) {
    set_flash('danger', 'Invalid tool ID.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;
}

// Get tool info
$stmt = $pdo->prepare('SELECT * FROM tools WHERE id = ?');
$stmt->execute([$tool_id]);
$tool = $stmt->fetch();

if (!$tool) {
    set_flash('danger', 'Tool not found.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
    exit;
}

// Get or create tool_content
$stmt = $pdo->prepare('SELECT * FROM tool_content WHERE tool_id = ?');
$stmt->execute([$tool_id]);
$content = $stmt->fetch();

if (!$content) {
    // Create default record
    $stmt = $pdo->prepare('INSERT INTO tool_content (tool_id) VALUES (?)');
    $stmt->execute([$tool_id]);

    $content = [
        'tool_id' => $tool_id,
        'meta_title' => '',
        'meta_description' => '',
        'primary_keyword' => '',
        'intro_html' => '',
        'how_it_works_html' => '',
        'features_html' => '',
        'faq_json' => '[]',
        'schema_json' => '',
        'og_image_url' => ''
    ];
}

// Parse FAQ JSON for display
$faq_items = json_decode($content['faq_json'] ?: '[]', true) ?: [];

$page_title = 'Edit Content: ' . $tool['name'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Tool Content</h1>
        <small class="text-muted"><?php echo e($tool['name']); ?> (<?php echo e($tool['slug']); ?>)</small>
    </div>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Tools
    </a>
</div>

<form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/tool_content/save.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="tool_id" value="<?php echo $tool_id; ?>">

    <div class="row">
        <div class="col-lg-8">
            <!-- SEO Section -->
            <div class="card mb-4">
                <div class="card-header">SEO & Meta</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="meta_title" class="form-label">Meta Title</label>
                        <input type="text" class="form-control" id="meta_title" name="meta_title"
                               value="<?php echo e($content['meta_title']); ?>" maxlength="70">
                        <small class="text-muted">Recommended: 50-60 characters</small>
                    </div>

                    <div class="mb-3">
                        <label for="meta_description" class="form-label">Meta Description</label>
                        <textarea class="form-control" id="meta_description" name="meta_description"
                                  rows="3" maxlength="160"><?php echo e($content['meta_description']); ?></textarea>
                        <small class="text-muted">Recommended: 150-160 characters</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="primary_keyword" class="form-label">Primary Keyword</label>
                            <input type="text" class="form-control" id="primary_keyword" name="primary_keyword"
                                   value="<?php echo e($content['primary_keyword']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="og_image_url" class="form-label">OG Image URL</label>
                            <input type="url" class="form-control" id="og_image_url" name="og_image_url"
                                   value="<?php echo e($content['og_image_url']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Sections -->
            <div class="card mb-4">
                <div class="card-header">Page Content</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="intro_html" class="form-label">Introduction HTML</label>
                        <textarea class="form-control" id="intro_html" name="intro_html"
                                  rows="4"><?php echo e($content['intro_html']); ?></textarea>
                        <small class="text-muted">Short intro paragraph shown below the tool</small>
                    </div>

                    <div class="mb-3">
                        <label for="how_it_works_html" class="form-label">How It Works HTML</label>
                        <textarea class="form-control" id="how_it_works_html" name="how_it_works_html"
                                  rows="6"><?php echo e($content['how_it_works_html']); ?></textarea>
                        <small class="text-muted">Step-by-step instructions</small>
                    </div>

                    <div class="mb-3">
                        <label for="features_html" class="form-label">Features HTML</label>
                        <textarea class="form-control" id="features_html" name="features_html"
                                  rows="6"><?php echo e($content['features_html']); ?></textarea>
                        <small class="text-muted">Feature grid or list</small>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    FAQ Items
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addFaq">
                        <i class="bi bi-plus"></i> Add FAQ
                    </button>
                </div>
                <div class="card-body">
                    <div id="faqContainer">
                        <?php foreach ($faq_items as $index => $faq): ?>
                            <div class="faq-item border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>FAQ #<?php echo $index + 1; ?></strong>
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-faq">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <div class="mb-2">
                                    <input type="text" class="form-control" name="faq_q[]"
                                           placeholder="Question" value="<?php echo e($faq['q'] ?? ''); ?>">
                                </div>
                                <div>
                                    <textarea class="form-control" name="faq_a[]" rows="2"
                                              placeholder="Answer"><?php echo e($faq['a'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted mb-0"><small>FAQ items will be converted to JSON-LD schema automatically.</small></p>
                </div>
            </div>

            <!-- Schema JSON -->
            <div class="card mb-4">
                <div class="card-header">Custom Schema JSON (Optional)</div>
                <div class="card-body">
                    <textarea class="form-control" id="schema_json" name="schema_json"
                              rows="6" style="font-family: monospace;"><?php echo e($content['schema_json']); ?></textarea>
                    <small class="text-muted">Additional JSON-LD schema markup (advanced)</small>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Actions</div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Content
                        </button>
                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/list.php" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Quick Links</div>
                <div class="card-body">
                    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/edit.php?id=<?php echo $tool_id; ?>"
                       class="btn btn-sm btn-outline-primary w-100 mb-2">
                        <i class="bi bi-pencil"></i> Edit Tool
                    </a>
                    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tool_settings/edit.php?tool_id=<?php echo $tool_id; ?>"
                       class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-gear"></i> Tool Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$page_scripts = <<<'JS'
<script>
// FAQ template
const faqTemplate = `
<div class="faq-item border rounded p-3 mb-3">
    <div class="d-flex justify-content-between mb-2">
        <strong>FAQ Item</strong>
        <button type="button" class="btn btn-sm btn-outline-danger remove-faq">
            <i class="bi bi-trash"></i>
        </button>
    </div>
    <div class="mb-2">
        <input type="text" class="form-control" name="faq_q[]" placeholder="Question">
    </div>
    <div>
        <textarea class="form-control" name="faq_a[]" rows="2" placeholder="Answer"></textarea>
    </div>
</div>
`;

// Add FAQ
document.getElementById('addFaq').addEventListener('click', function() {
    document.getElementById('faqContainer').insertAdjacentHTML('beforeend', faqTemplate);
});

// Remove FAQ
document.getElementById('faqContainer').addEventListener('click', function(e) {
    if (e.target.closest('.remove-faq')) {
        e.target.closest('.faq-item').remove();
    }
});
</script>
JS;

include __DIR__ . '/../../includes/footer.php';
?>
