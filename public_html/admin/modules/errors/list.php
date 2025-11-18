<?php
/**
 * Error Logs List
 *
 * Admin page to view and filter recent error logs.
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../lib/error_logger.php';

// Check admin authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

// Pagination and filtering
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$levelFilter = $_GET['level'] ?? '';
$toolFilter = $_GET['tool'] ?? '';
$search = $_GET['search'] ?? '';

// Fetch errors
$errors = [];
$total = 0;

try {
    $pdo = get_pdo();

    // Build query
    $where = [];
    $params = [];

    if ($levelFilter) {
        $where[] = 'level = ?';
        $params[] = $levelFilter;
    }

    if ($toolFilter) {
        $where[] = 'tool_slug = ?';
        $params[] = $toolFilter;
    }

    if ($search) {
        $where[] = '(message LIKE ? OR context LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countSql = "SELECT COUNT(*) FROM error_logs {$whereClause}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // Get errors
    $sql = "SELECT * FROM error_logs {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available tools for filter
    $toolsStmt = $pdo->query('SELECT DISTINCT tool_slug FROM error_logs WHERE tool_slug IS NOT NULL ORDER BY tool_slug');
    $tools = $toolsStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $errorMessage = 'Failed to load error logs: ' . $e->getMessage();
}

$totalPages = ceil($total / $perPage);

// Level badge colors
$levelColors = [
    'debug' => 'secondary',
    'info' => 'info',
    'warning' => 'warning',
    'error' => 'danger',
    'critical' => 'dark'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Logs - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .error-message { max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .badge-debug { background-color: #6c757d; }
        .badge-info { background-color: #17a2b8; }
        .badge-warning { background-color: #ffc107; color: #000; }
        .badge-error { background-color: #dc3545; }
        .badge-critical { background-color: #343a40; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Error Logs</h1>
                <p class="text-muted">Monitor application errors and exceptions</p>
            </div>
            <div class="col-auto">
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-outline-secondary">
                    &larr; Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= h($errorMessage) ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-select">
                            <option value="">All Levels</option>
                            <option value="debug" <?= $levelFilter === 'debug' ? 'selected' : '' ?>>Debug</option>
                            <option value="info" <?= $levelFilter === 'info' ? 'selected' : '' ?>>Info</option>
                            <option value="warning" <?= $levelFilter === 'warning' ? 'selected' : '' ?>>Warning</option>
                            <option value="error" <?= $levelFilter === 'error' ? 'selected' : '' ?>>Error</option>
                            <option value="critical" <?= $levelFilter === 'critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tool</label>
                        <select name="tool" class="form-select">
                            <option value="">All Tools</option>
                            <?php foreach ($tools ?? [] as $tool): ?>
                                <option value="<?= h($tool) ?>" <?= $toolFilter === $tool ? 'selected' : '' ?>>
                                    <?= h($tool) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" value="<?= h($search) ?>" placeholder="Search in messages...">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Showing <?= count($errors) ?> of <?= $total ?> errors</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Level</th>
                            <th>Tool</th>
                            <th>Message</th>
                            <th>Location</th>
                            <th>IP</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($errors)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No errors found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($errors as $error): ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <?= date('M j, H:i', strtotime($error['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $error['level'] ?>">
                                            <?= strtoupper($error['level']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $error['tool_slug'] ? h($error['tool_slug']) : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td class="error-message" title="<?= h($error['message']) ?>">
                                        <?= h($error['message']) ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?php if ($error['file']): ?>
                                            <?= basename($error['file']) ?>:<?= $error['line'] ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= h($error['ip_address']) ?></td>
                                    <td>
                                        <a href="view.php?id=<?= $error['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination mb-0 justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&level=<?= urlencode($levelFilter) ?>&tool=<?= urlencode($toolFilter) ?>&search=<?= urlencode($search) ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&level=<?= urlencode($levelFilter) ?>&tool=<?= urlencode($toolFilter) ?>&search=<?= urlencode($search) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&level=<?= urlencode($levelFilter) ?>&tool=<?= urlencode($toolFilter) ?>&search=<?= urlencode($search) ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
