<?php
/**
 * Error Log Detail View
 *
 * Shows full details of a single error log entry.
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

$errorId = (int)($_GET['id'] ?? 0);

if (!$errorId) {
    header('Location: list.php');
    exit;
}

// Fetch error details
$error = null;

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM error_logs WHERE id = ?');
    $stmt->execute([$errorId]);
    $error = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$error) {
        $errorMessage = 'Error log not found';
    }

} catch (PDOException $e) {
    $errorMessage = 'Failed to load error: ' . $e->getMessage();
}

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
    <title>Error Details - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre { background: #f8f9fa; padding: 1rem; border-radius: 0.25rem; overflow-x: auto; }
        .label { font-weight: 600; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Error Details</h1>
            </div>
            <div class="col-auto">
                <a href="list.php" class="btn btn-outline-secondary">
                    &larr; Back to Error Logs
                </a>
            </div>
        </div>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= h($errorMessage) ?></div>
        <?php elseif ($error): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <span class="badge bg-<?= $levelColors[$error['level']] ?? 'secondary' ?>">
                            <?= strtoupper($error['level']) ?>
                        </span>
                        Error #<?= $error['id'] ?>
                    </span>
                    <small class="text-muted">
                        <?= date('F j, Y g:i:s A', strtotime($error['created_at'])) ?>
                    </small>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="label mb-1">Message</p>
                            <p class="mb-3"><?= nl2br(h($error['message'])) ?></p>

                            <?php if ($error['tool_slug']): ?>
                                <p class="label mb-1">Tool</p>
                                <p class="mb-3">
                                    <code><?= h($error['tool_slug']) ?></code>
                                </p>
                            <?php endif; ?>

                            <?php if ($error['file']): ?>
                                <p class="label mb-1">Location</p>
                                <p class="mb-3">
                                    <code><?= h($error['file']) ?>:<?= $error['line'] ?></code>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <p class="label mb-1">IP Address</p>
                            <p class="mb-3"><?= h($error['ip_address']) ?></p>

                            <?php if ($error['url']): ?>
                                <p class="label mb-1">URL</p>
                                <p class="mb-3 small text-break"><?= h($error['url']) ?></p>
                            <?php endif; ?>

                            <?php if ($error['user_agent']): ?>
                                <p class="label mb-1">User Agent</p>
                                <p class="mb-3 small text-muted"><?= h($error['user_agent']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($error['context']): ?>
                        <div class="mb-4">
                            <p class="label mb-2">Context Data</p>
                            <pre><code><?= h(json_encode(json_decode($error['context']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                        </div>
                    <?php endif; ?>

                    <?php if ($error['stack_trace']): ?>
                        <div class="mb-3">
                            <p class="label mb-2">Stack Trace</p>
                            <pre><code><?= h($error['stack_trace']) ?></code></pre>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted">
                    <small>Logged at <?= $error['created_at'] ?></small>
                </div>
            </div>

            <!-- Navigation between errors -->
            <div class="d-flex justify-content-between mt-3">
                <?php
                // Get previous and next error IDs
                $prevStmt = $pdo->prepare('SELECT id FROM error_logs WHERE id < ? ORDER BY id DESC LIMIT 1');
                $prevStmt->execute([$errorId]);
                $prevId = $prevStmt->fetchColumn();

                $nextStmt = $pdo->prepare('SELECT id FROM error_logs WHERE id > ? ORDER BY id ASC LIMIT 1');
                $nextStmt->execute([$errorId]);
                $nextId = $nextStmt->fetchColumn();
                ?>

                <?php if ($prevId): ?>
                    <a href="view.php?id=<?= $prevId ?>" class="btn btn-outline-secondary">
                        &larr; Previous Error
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>

                <?php if ($nextId): ?>
                    <a href="view.php?id=<?= $nextId ?>" class="btn btn-outline-secondary">
                        Next Error &rarr;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
