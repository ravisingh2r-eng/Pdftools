<?php
/**
 * View Feedback
 *
 * Display full feedback details and allow status updates.
 */

require_once __DIR__ . '/../../includes/auth_check.php';

$pdo = get_db_connection();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'Invalid feedback ID.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/list.php');
    exit;
}

// Get feedback
$stmt = $pdo->prepare('SELECT * FROM feedback WHERE id = ?');
$stmt->execute([$id]);
$feedback = $stmt->fetch();

if (!$feedback) {
    set_flash('danger', 'Feedback not found.');
    header('Location: ' . ADMIN_BASE_URL . '/modules/feedback/list.php');
    exit;
}

$page_title = 'View Feedback #' . $id;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Feedback #<?php echo $id; ?></h1>
    <a href="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/list.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to List
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Message -->
        <div class="card mb-4">
            <div class="card-header">
                <strong><?php echo e($feedback['subject'] ?: 'No Subject'); ?></strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <?php echo nl2br(e($feedback['message'])); ?>
                </div>
            </div>
            <div class="card-footer text-muted">
                <small>
                    Submitted <?php echo date('M j, Y \a\t g:i A', strtotime($feedback['created_at'])); ?>
                    <?php if ($feedback['ip_address']): ?>
                        from <?php echo e($feedback['ip_address']); ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>

        <!-- Admin Notes -->
        <div class="card">
            <div class="card-header">Admin Notes</div>
            <div class="card-body">
                <form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/update_status.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <div class="mb-3">
                        <textarea class="form-control" name="admin_notes" rows="4"
                                  placeholder="Add internal notes..."><?php echo e($feedback['admin_notes']); ?></textarea>
                    </div>

                    <button type="submit" name="action" value="save_notes" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Save Notes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Contact Info -->
        <div class="card mb-4">
            <div class="card-header">Contact Info</div>
            <div class="card-body">
                <?php if ($feedback['name']): ?>
                    <p class="mb-2">
                        <strong>Name:</strong><br>
                        <?php echo e($feedback['name']); ?>
                    </p>
                <?php endif; ?>

                <?php if ($feedback['email']): ?>
                    <p class="mb-2">
                        <strong>Email:</strong><br>
                        <a href="mailto:<?php echo e($feedback['email']); ?>"><?php echo e($feedback['email']); ?></a>
                    </p>
                <?php endif; ?>

                <?php if ($feedback['tool_slug']): ?>
                    <p class="mb-0">
                        <strong>Tool:</strong><br>
                        <span class="badge bg-secondary"><?php echo e($feedback['tool_slug']); ?></span>
                    </p>
                <?php endif; ?>

                <?php if (!$feedback['name'] && !$feedback['email'] && !$feedback['tool_slug']): ?>
                    <p class="text-muted mb-0">No contact information provided.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status -->
        <div class="card mb-4">
            <div class="card-header">Status</div>
            <div class="card-body">
                <form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/update_status.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <div class="mb-3">
                        <select class="form-select" name="status">
                            <option value="new" <?php echo $feedback['status'] === 'new' ? 'selected' : ''; ?>>
                                New
                            </option>
                            <option value="in_review" <?php echo $feedback['status'] === 'in_review' ? 'selected' : ''; ?>>
                                In Review
                            </option>
                            <option value="done" <?php echo $feedback['status'] === 'done' ? 'selected' : ''; ?>>
                                Done
                            </option>
                        </select>
                    </div>

                    <button type="submit" name="action" value="update_status" class="btn btn-primary w-100">
                        Update Status
                    </button>
                </form>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">Actions</div>
            <div class="card-body d-grid gap-2">
                <?php if ($feedback['email']): ?>
                    <a href="mailto:<?php echo e($feedback['email']); ?>?subject=Re: <?php echo e($feedback['subject']); ?>"
                       class="btn btn-outline-primary">
                        <i class="bi bi-envelope"></i> Reply by Email
                    </a>
                <?php endif; ?>

                <form method="POST" action="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/update_status.php" class="d-inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-outline-danger w-100"
                            data-confirm="Are you sure you want to delete this feedback?">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
