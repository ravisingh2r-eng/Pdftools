<?php
/**
 * Tool Edit/Create
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pdo = get_db_connection();

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;

$page_title = $is_edit ? 'Edit Tool' : 'Add Tool';

// Get tool data if editing
$tool = [
    'slug' => '',
    'name' => '',
    'category' => 'basic',
    'icon' => '',
    'color' => '#4CAF50',
    'short_description' => '',
    'long_description' => '',
    'is_active' => 1,
    'is_premium' => 0,
    'is_featured' => 0,
    'sort_order' => 100
];

if ($is_edit) {
    $stmt = $pdo->prepare('SELECT * FROM tools WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        set_flash('danger', 'Tool not found.');
        header('Location: ' . ADMIN_BASE_URL . '/modules/tools/list.php');
        exit;
    }

    $tool = array_merge($tool, $existing);
}

// Categories
$categories = ['basic', 'compress', 'convert', 'security', 'edit', 'advanced'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?php echo $page_title; ?></h1>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/tools/save.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $id; ?>">

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Basic Information</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="slug" name="slug"
                                   value="<?php echo e($tool['slug']); ?>" required
                                   pattern="[a-z0-9-]+" title="Lowercase letters, numbers, and hyphens only"
                                   <?php echo $is_edit ? 'readonly' : ''; ?>>
                            <small class="text-muted">URL-friendly identifier (e.g., merge-pdf)</small>
                        </div>
                        <div class="col-md-6">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?php echo e($tool['name']); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $tool['category'] === $cat ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="icon" class="form-label">Icon (Emoji)</label>
                            <input type="text" class="form-control" id="icon" name="icon"
                                   value="<?php echo e($tool['icon']); ?>" maxlength="10">
                        </div>
                        <div class="col-md-3">
                            <label for="color" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color w-100" id="color" name="color"
                                   value="<?php echo e($tool['color']); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="short_description" class="form-label">Short Description</label>
                        <textarea class="form-control" id="short_description" name="short_description"
                                  rows="2"><?php echo e($tool['short_description']); ?></textarea>
                        <small class="text-muted">Brief description for listings (1-2 sentences)</small>
                    </div>

                    <div class="mb-3">
                        <label for="long_description" class="form-label">Long Description</label>
                        <textarea class="form-control" id="long_description" name="long_description"
                                  rows="4"><?php echo e($tool['long_description']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">Status & Options</div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   <?php echo $tool['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        <small class="text-muted">Tool is visible and usable</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1"
                                   <?php echo $tool['is_featured'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_featured">Featured</label>
                        </div>
                        <small class="text-muted">Show on homepage featured section</small>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_premium" name="is_premium" value="1"
                                   <?php echo $tool['is_premium'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_premium">Premium</label>
                        </div>
                        <small class="text-muted">Reserved for future premium features</small>
                    </div>

                    <div class="mb-3">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order"
                               value="<?php echo $tool['sort_order']; ?>" min="1">
                        <small class="text-muted">Lower number appears first</small>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?php echo $is_edit ? 'Update Tool' : 'Create Tool'; ?>
                </button>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/list.php" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
