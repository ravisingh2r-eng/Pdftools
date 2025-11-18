<?php
/**
 * Usage Logs
 *
 * Display detailed usage logs with filters and pagination.
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$page_title = 'Usage Logs';
$pdo = get_db_connection();

// Filters
$filter_tool = $_GET['tool'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Build query
$where = [];
$params = [];

if ($filter_tool) {
    $where[] = 'tool_slug = ?';
    $params[] = $filter_tool;
}

if ($filter_status) {
    $where[] = 'status = ?';
    $params[] = $filter_status;
}

if ($filter_date) {
    $where[] = 'DATE(created_at) = ?';
    $params[] = $filter_date;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usage_logs $whereClause");
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Pagination
$per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$pagination = get_pagination($total, $per_page, $page);

// Get logs
$stmt = $pdo->prepare("
    SELECT * FROM usage_logs
    $whereClause
    ORDER BY created_at DESC
    LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get distinct tools for filter
$tools = $pdo->query('SELECT DISTINCT tool_slug FROM usage_logs ORDER BY tool_slug')->fetchAll(PDO::FETCH_COLUMN);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Usage Logs</h1>
    <span class="badge bg-secondary"><?php echo number_format($total); ?> records</span>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
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
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="success" <?php echo $filter_status === 'success' ? 'selected' : ''; ?>>Success</option>
                    <option value="error" <?php echo $filter_status === 'error' ? 'selected' : ''; ?>>Error</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" name="date" class="form-control" value="<?php echo e($filter_date); ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/analytics/logs.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2 mb-0">No logs found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Tool</th>
                            <th>Status</th>
                            <th>Files</th>
                            <th>Size</th>
                            <th>Time</th>
                            <th>IP</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('M j, H:i', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td><?php echo e($log['tool_slug']); ?></td>
                                <td>
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="badge bg-success">Success</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Error</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $log['file_count']; ?></td>
                                <td><?php echo format_bytes($log['total_size_bytes']); ?></td>
                                <td><?php echo $log['processing_time_ms']; ?>ms</td>
                                <td><small class="text-muted"><?php echo e($log['ip_address']); ?></small></td>
                                <td>
                                    <?php if ($log['error_message']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="popover" data-bs-trigger="focus"
                                                title="Error" data-bs-content="<?php echo e($log['error_message']); ?>">
                                            <i class="bi bi-info-circle"></i>
                                        </button>
                                    <?php endif; ?>
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
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&tool=<?php echo e($filter_tool); ?>&status=<?php echo e($filter_status); ?>&date=<?php echo e($filter_date); ?>">
                            Previous
                        </a>
                    </li>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($pagination['total_pages'], $page + 2);
                    ?>

                    <?php if ($start > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&tool=<?php echo e($filter_tool); ?>&status=<?php echo e($filter_status); ?>&date=<?php echo e($filter_date); ?>">1</a>
                        </li>
                        <?php if ($start > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&tool=<?php echo e($filter_tool); ?>&status=<?php echo e($filter_status); ?>&date=<?php echo e($filter_date); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end < $pagination['total_pages']): ?>
                        <?php if ($end < $pagination['total_pages'] - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $pagination['total_pages']; ?>&tool=<?php echo e($filter_tool); ?>&status=<?php echo e($filter_status); ?>&date=<?php echo e($filter_date); ?>">
                                <?php echo $pagination['total_pages']; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&tool=<?php echo e($filter_tool); ?>&status=<?php echo e($filter_status); ?>&date=<?php echo e($filter_date); ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php
$page_scripts = '
<script>
// Initialize popovers for error details
var popoverTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="popover"]\'));
var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl);
});
</script>';

include __DIR__ . '/../../includes/footer.php';
?>
