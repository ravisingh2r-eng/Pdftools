<?php
/**
 * Flash Messages Display
 *
 * Include this in the page to display flash messages.
 */

$flash_messages = get_flash_messages();

if (!empty($flash_messages)): ?>
<div class="flash-messages">
    <?php foreach ($flash_messages as $flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo e($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
