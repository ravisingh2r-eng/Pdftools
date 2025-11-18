<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/includes/auth_check.php';

$page_title = 'Dashboard';

$pdo = get_db_connection();

// Get statistics
$stats = [];

// Total tools
$stmt = $pdo->query('SELECT COUNT(*) FROM tools');
$stats['total_tools'] = $stmt->fetchColumn();

// Active tools
$stmt = $pdo->query('SELECT COUNT(*) FROM tools WHERE is_active = 1');
$stats['active_tools'] = $stmt->fetchColumn();

// Total usage today
$stmt = $pdo->query('SELECT COUNT(*) FROM usage_logs WHERE DATE(created_at) = CURDATE()');
$stats['usage_today'] = $stmt->fetchColumn();

// Total usage this month
$stmt = $pdo->query('SELECT COUNT(*) FROM usage_logs WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())');
$stats['usage_month'] = $stmt->fetchColumn();

// New feedback count
$stmt = $pdo->query('SELECT COUNT(*) FROM feedback WHERE status = "new"');
$stats['new_feedback'] = $stmt->fetchColumn();

// Error count today
$stmt = $pdo->query('SELECT COUNT(*) FROM usage_logs WHERE DATE(created_at) = CURDATE() AND status = "error"');
$stats['errors_today'] = $stmt->fetchColumn();

// Top 5 tools by usage this month
$stmt = $pdo->query('
    SELECT tool_slug, COUNT(*) as uses
    FROM usage_logs
    WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
    GROUP BY tool_slug
    ORDER BY uses DESC
    LIMIT 5
');
$top_tools = $stmt->fetchAll();

// Recent usage logs
$stmt = $pdo->query('
    SELECT * FROM usage_logs
    ORDER BY created_at DESC
    LIMIT 10
');
$recent_logs = $stmt->fetchAll();

// Recent feedback
$stmt = $pdo->query('
    SELECT * FROM feedback
    WHERE status = "new"
    ORDER BY created_at DESC
    LIMIT 5
');
$recent_feedback = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<h1 class="h3 mb-4">Dashboard</h1>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Tools</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['total_tools']); ?></h3>
                    </div>
                    <i class="bi bi-tools fs-1 text-primary opacity-25"></i>
                </div>
                <small class="text-muted"><?php echo $stats['active_tools']; ?> active</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card stat-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Usage Today</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['usage_today']); ?></h3>
                    </div>
                    <i class="bi bi-graph-up fs-1 text-success opacity-25"></i>
                </div>
                <small class="text-muted"><?php echo number_format($stats['usage_month']); ?> this month</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">New Feedback</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['new_feedback']); ?></h3>
                    </div>
                    <i class="bi bi-chat-dots fs-1 text-warning opacity-25"></i>
                </div>
                <small class="text-muted">Awaiting review</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card stat-card danger">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Errors Today</h6>
                        <h3 class="mb-0"><?php echo number_format($stats['errors_today']); ?></h3>
                    </div>
                    <i class="bi bi-exclamation-triangle fs-1 text-danger opacity-25"></i>
                </div>
                <small class="text-muted">Check logs for details</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Top Tools -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-trophy"></i> Top Tools This Month</span>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/analytics/overview.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($top_tools)): ?>
                    <p class="text-muted mb-0">No usage data available yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Tool</th>
                                    <th class="text-end">Uses</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_tools as $index => $tool): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary me-2"><?php echo $index + 1; ?></span>
                                            <?php echo e($tool['tool_slug']); ?>
                                        </td>
                                        <td class="text-end"><?php echo number_format($tool['uses']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Feedback -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-chat-dots"></i> Recent Feedback</span>
                <a href="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/list.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_feedback)): ?>
                    <p class="text-muted mb-0">No new feedback.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_feedback as $fb): ?>
                            <a href="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/view.php?id=<?php echo $fb['id']; ?>"
                               class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo e($fb['subject'] ?: 'No Subject'); ?></strong>
                                    <small class="text-muted"><?php echo time_ago($fb['created_at']); ?></small>
                                </div>
                                <small class="text-muted">
                                    <?php echo e(substr($fb['message'], 0, 80)); ?><?php echo strlen($fb['message']) > 80 ? '...' : ''; ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> Recent Activity</span>
        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/analytics/logs.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recent_logs)): ?>
            <p class="text-muted mb-0">No recent activity.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Status</th>
                            <th>Files</th>
                            <th>Size</th>
                            <th>Time</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
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
                                <td><?php echo time_ago($log['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
