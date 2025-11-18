<?php
/**
 * Analytics Overview
 *
 * Display usage statistics and trends.
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$page_title = 'Analytics Overview';
$pdo = get_db_connection();

// Date range
$range = $_GET['range'] ?? '7';
$range_days = max(1, min(365, (int)$range));

// Get overall stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_uses,
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
        SUM(file_count) as total_files,
        SUM(total_size_bytes) as total_bytes,
        AVG(processing_time_ms) as avg_time
    FROM usage_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
");
$stmt->execute([$range_days]);
$overall = $stmt->fetch();

// Get daily usage for chart
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as uses
    FROM usage_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$range_days]);
$daily_usage = $stmt->fetchAll();

// Get top tools
$stmt = $pdo->prepare("
    SELECT tool_slug, COUNT(*) as uses,
           SUM(file_count) as files,
           SUM(total_size_bytes) as bytes
    FROM usage_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY tool_slug
    ORDER BY uses DESC
    LIMIT 10
");
$stmt->execute([$range_days]);
$top_tools = $stmt->fetchAll();

// Get error rate by tool
$stmt = $pdo->prepare("
    SELECT tool_slug,
           COUNT(*) as total,
           SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
    FROM usage_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY tool_slug
    HAVING errors > 0
    ORDER BY errors DESC
    LIMIT 10
");
$stmt->execute([$range_days]);
$error_tools = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Analytics Overview</h1>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto" onchange="location.href='?range='+this.value">
            <option value="7" <?php echo $range_days == 7 ? 'selected' : ''; ?>>Last 7 days</option>
            <option value="14" <?php echo $range_days == 14 ? 'selected' : ''; ?>>Last 14 days</option>
            <option value="30" <?php echo $range_days == 30 ? 'selected' : ''; ?>>Last 30 days</option>
            <option value="90" <?php echo $range_days == 90 ? 'selected' : ''; ?>>Last 90 days</option>
            <option value="365" <?php echo $range_days == 365 ? 'selected' : ''; ?>>Last year</option>
        </select>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4 col-xl-2">
        <div class="card stat-card primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Total Uses</h6>
                <h3 class="mb-0"><?php echo number_format($overall['total_uses']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card stat-card success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Successful</h6>
                <h3 class="mb-0"><?php echo number_format($overall['successful']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card stat-card danger">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Errors</h6>
                <h3 class="mb-0"><?php echo number_format($overall['errors']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card stat-card info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Files Processed</h6>
                <h3 class="mb-0"><?php echo number_format($overall['total_files']); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card stat-card warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Data Processed</h6>
                <h3 class="mb-0"><?php echo format_bytes($overall['total_bytes'] ?? 0); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-xl-2">
        <div class="card stat-card">
            <div class="card-body text-center">
                <h6 class="text-muted mb-1">Avg Time</h6>
                <h3 class="mb-0"><?php echo round($overall['avg_time'] ?? 0); ?>ms</h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Daily Usage Chart -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Daily Usage</div>
            <div class="card-body">
                <?php if (empty($daily_usage)): ?>
                    <p class="text-muted text-center mb-0">No usage data for this period.</p>
                <?php else: ?>
                    <div style="height: 300px;">
                        <canvas id="usageChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Tools -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Top Tools</div>
            <div class="card-body p-0">
                <?php if (empty($top_tools)): ?>
                    <div class="p-3 text-muted text-center">No data available.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Tool</th>
                                    <th class="text-end">Uses</th>
                                    <th class="text-end">Files</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_tools as $tool): ?>
                                    <tr>
                                        <td><?php echo e($tool['tool_slug']); ?></td>
                                        <td class="text-end"><?php echo number_format($tool['uses']); ?></td>
                                        <td class="text-end"><?php echo number_format($tool['files']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Error Analysis -->
<?php if (!empty($error_tools)): ?>
<div class="card mt-4">
    <div class="card-header text-danger">
        <i class="bi bi-exclamation-triangle"></i> Error Analysis
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Tool</th>
                        <th>Total Uses</th>
                        <th>Errors</th>
                        <th>Error Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($error_tools as $tool): ?>
                        <?php $rate = $tool['total'] > 0 ? ($tool['errors'] / $tool['total']) * 100 : 0; ?>
                        <tr>
                            <td><?php echo e($tool['tool_slug']); ?></td>
                            <td><?php echo number_format($tool['total']); ?></td>
                            <td><?php echo number_format($tool['errors']); ?></td>
                            <td>
                                <span class="badge <?php echo $rate > 10 ? 'bg-danger' : ($rate > 5 ? 'bg-warning' : 'bg-secondary'); ?>">
                                    <?php echo number_format($rate, 1); ?>%
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Chart data
$chart_labels = array_column($daily_usage, 'date');
$chart_data = array_column($daily_usage, 'uses');

$page_scripts = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById("usageChart");
if (ctx) {
    new Chart(ctx, {
        type: "line",
        data: {
            labels: ' . json_encode($chart_labels) . ',
            datasets: [{
                label: "Uses",
                data: ' . json_encode($chart_data) . ',
                borderColor: "#0d6efd",
                backgroundColor: "rgba(13, 110, 253, 0.1)",
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}
</script>';

include __DIR__ . '/../../includes/footer.php';
?>
