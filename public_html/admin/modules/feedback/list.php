<?php
/**
 * Feedback List
 *
 * Display user feedback and support requests.
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$page_title = 'Feedback';
$pdo = get_db_connection();

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_tool = $_GET['tool'] ?? '';

// Build query
$where = [];
$params = [];

if ($filter_status) {
    $where[] = 'status = ?';
    $params[] = $filter_status;
}

if ($filter_tool) {
    $where[] = 'tool_slug = ?';
    $params[] = $filter_tool;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM feedback $whereClause");
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Pagination
$per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$pagination = get_pagination($total, $per_page, $page);

// Get feedback
$stmt = $pdo->prepare("
    SELECT * FROM feedback
    $whereClause
    ORDER BY
        CASE status WHEN 'new' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END,
        created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// Get counts by status
$status_counts = [];
$stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM feedback GROUP BY status");
while ($row = $stmt->fetch()) {
    $status_counts[$row['status']] = $row['cnt'];
}

// Get distinct tools
$tools = $pdo->query('SELECT DISTINCT tool_slug FROM feedback WHERE tool_slug IS NOT NULL ORDER BY tool_slug')->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Feedback</h1>
    <div>
        <span class="badge bg-danger"><?php echo $status_counts['new'] ?? 0; ?> new</span>
        <span class="badge bg-warning"><?php echo $status_counts['in_review'] ?? 0; ?> in review</span>
        <span class="badge bg-success"><?php echo $status_counts['done'] ?? 0; ?> done</span>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="new" <?php echo $filter_status === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="in_review" <?php echo $filter_status === 'in_review' ? 'selected' : ''; ?>>In Review</option>
                    <option value="done" <?php echo $filter_status === 'done' ? 'selected' : ''; ?>>Done</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="tool" class="form-select">
                    <option value="">All Tools</option>
                    <?php foreach ($tools as $tool): ?>
                        <option value="<?php echo e($tool); ?>" <?php echo $filter_tool === $tool ? 'selected' : ''; ?>>
                            <?php echo e($tool); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/list.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Feedback List -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($feedbacks)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-chat-dots fs-1"></i>
                <p class="mt-2 mb-0">No feedback found.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($feedbacks as $fb): ?>
                    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/view.php?id=<?php echo $fb['id']; ?>"
                       class="list-group-item list-group-item-action <?php echo $fb['status'] === 'new' ? 'bg-light' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <?php if ($fb['status'] === 'new'): ?>
                                        <span class="badge bg-danger">New</span>
                                    <?php elseif ($fb['status'] === 'in_review'): ?>
                                        <span class="badge bg-warning">In Review</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Done</span>
                                    <?php endif; ?>
                                    <strong><?php echo e($fb['subject'] ?: 'No Subject'); ?></strong>
                                    <?php if ($fb['tool_slug']): ?>
                                        <span class="badge bg-secondary"><?php echo e($fb['tool_slug']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-1 text-muted">
                                    <?php echo e(substr($fb['message'], 0, 150)); ?><?php echo strlen($fb['message']) > 150 ? '...' : ''; ?>
                                </p>
                                <small class="text-muted">
                                    <?php if ($fb['name']): ?>
                                        <?php echo e($fb['name']); ?>
                                        <?php if ($fb['email']): ?>
                                            &lt;<?php echo e($fb['email']); ?>&gt;
                                        <?php endif; ?>
                                        •
                                    <?php endif; ?>
                                    <?php echo time_ago($fb['created_at']); ?>
                                </small>
                            </div>
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    <li class="page-item <?php echo !$pagination['has_prev'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo e($filter_status); ?>&tool=<?php echo e($filter_tool); ?>">
                            Previous
                        </a>
                    </li>

                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo e($filter_status); ?>&tool=<?php echo e($filter_tool); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo e($filter_status); ?>&tool=<?php echo e($filter_tool); ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
