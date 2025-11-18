<?php
/**
 * Tools List
 *
 * Display all tools with filtering and sorting.
 * NOTE: Tools can be synced from /config/tools_registry.php
 * The sync process imports tools from the PHP array into the database.
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$page_title = 'Tools';
$pdo = get_db_connection();

// Filters
$filter_category = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($filter_category) {
    $where[] = 'category = ?';
    $params[] = $filter_category;
}

if ($filter_status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($filter_status === 'inactive') {
    $where[] = 'is_active = 0';
}

if ($search) {
    $where[] = '(name LIKE ? OR slug LIKE ? OR short_description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tools $whereClause");
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Pagination
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$pagination = get_pagination($total, $per_page, $page);

// Get tools
$stmt = $pdo->prepare("
    SELECT * FROM tools
    $whereClause
    ORDER BY sort_order ASC, name ASC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$tools = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query('SELECT DISTINCT category FROM tools ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Tools</h1>
    <div>
        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/edit.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add Tool
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search tools..."
                       value="<?php echo e($search); ?>">
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo e($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                            <?php echo e(ucfirst($cat)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/list.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tools Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($tools)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2 mb-0">No tools found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Uses</th>
                            <th>Order</th>
                            <th width="180">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tools as $tool): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span style="font-size: 1.5rem;"><?php echo $tool['icon'] ?: '📄'; ?></span>
                                        <div>
                                            <strong><?php echo e($tool['name']); ?></strong>
                                            <br><small class="text-muted"><?php echo e($tool['slug']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo e(ucfirst($tool['category'])); ?></span>
                                </td>
                                <td>
                                    <?php if ($tool['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tool['is_featured']): ?>
                                        <i class="bi bi-star-fill text-warning"></i>
                                    <?php else: ?>
                                        <i class="bi bi-star text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($tool['total_uses']); ?></td>
                                <td><?php echo $tool['sort_order']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/edit.php?id=<?php echo $tool['id']; ?>"
                                           class="btn btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tool_content/edit.php?tool_id=<?php echo $tool['id']; ?>"
                                           class="btn btn-outline-info" title="Content">
                                            <i class="bi bi-file-text"></i>
                                        </a>
                                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tool_settings/edit.php?tool_id=<?php echo $tool['id']; ?>"
                                           class="btn btn-outline-secondary" title="Settings">
                                            <i class="bi bi-gear"></i>
                                        </a>
                                        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/delete.php?id=<?php echo $tool['id']; ?>"
                                           class="btn btn-outline-danger" title="Delete"
                                           data-confirm="Are you sure you want to delete this tool?">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <li class="page-item <?php echo !$pagination['has_prev'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&category=<?php echo e($filter_category); ?>&status=<?php echo e($filter_status); ?>&search=<?php echo e($search); ?>">
                            Previous
                        </a>
                    </li>

                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo e($filter_category); ?>&status=<?php echo e($filter_status); ?>&search=<?php echo e($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&category=<?php echo e($filter_category); ?>&status=<?php echo e($filter_status); ?>&search=<?php echo e($search); ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<div class="mt-4">
    <div class="alert alert-info">
        <strong><i class="bi bi-info-circle"></i> Sync with tools_registry.php</strong><br>
        <small>
            To import tools from <code>/config/tools_registry.php</code>, run the sync script via CLI or create a sync button.
            The registry serves as the source of truth for tool definitions that can be imported into the database for extended management.
        </small>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
