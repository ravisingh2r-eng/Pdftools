<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = '';

// Determine current module from URL
if (strpos($_SERVER['REQUEST_URI'], '/modules/') !== false) {
    preg_match('/\/modules\/([^\/]+)/', $_SERVER['REQUEST_URI'], $matches);
    $current_module = $matches[1] ?? '';
}
?>

<!-- Sidebar -->
<nav class="sidebar">
    <div class="sidebar-brand">
        <a href="<?php echo ADMIN_BASE_URL; ?>/dashboard.php">
            <i class="bi bi-file-earmark-pdf"></i> <?php echo ADMIN_SITE_NAME; ?>
        </a>
    </div>

    <div class="sidebar-nav">
        <a href="<?php echo ADMIN_BASE_URL; ?>/dashboard.php"
           class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section">Content</div>

        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/tools/list.php"
           class="nav-link <?php echo $current_module === 'tools' ? 'active' : ''; ?>">
            <i class="bi bi-tools"></i> Tools
        </a>

        <?php if (has_role(['super_admin', 'ad_manager'])): ?>
        <div class="nav-section">Monetization</div>

        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/slots_list.php"
           class="nav-link <?php echo $current_module === 'ads' && strpos($current_page, 'slot') !== false ? 'active' : ''; ?>">
            <i class="bi bi-megaphone"></i> Ad Slots
        </a>

        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/ads/overrides_list.php"
           class="nav-link <?php echo $current_module === 'ads' && strpos($current_page, 'override') !== false ? 'active' : ''; ?>">
            <i class="bi bi-sliders"></i> Ad Overrides
        </a>
        <?php endif; ?>

        <div class="nav-section">Analytics</div>

        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/analytics/overview.php"
           class="nav-link <?php echo $current_module === 'analytics' && $current_page === 'overview.php' ? 'active' : ''; ?>">
            <i class="bi bi-graph-up"></i> Overview
        </a>

        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/analytics/logs.php"
           class="nav-link <?php echo $current_module === 'analytics' && $current_page === 'logs.php' ? 'active' : ''; ?>">
            <i class="bi bi-list-ul"></i> Usage Logs
        </a>

        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/feedback/list.php"
           class="nav-link <?php echo $current_module === 'feedback' ? 'active' : ''; ?>">
            <i class="bi bi-chat-dots"></i> Feedback
        </a>

        <?php if (has_role('super_admin')): ?>
        <div class="nav-section">System</div>

        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/system/health.php"
           class="nav-link <?php echo $current_module === 'system' ? 'active' : ''; ?>">
            <i class="bi bi-heart-pulse"></i> System Health
        </a>

        <a href="<?php echo ADMIN_BASE_URL; ?>/modules/admins/list.php"
           class="nav-link <?php echo $current_module === 'admins' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i> Administrators
        </a>
        <?php endif; ?>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar">
        <div>
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a href="<?php echo PUBLIC_BASE_URL; ?>/" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-box-arrow-up-right"></i> View Site
            </a>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?php echo e($current_admin['name']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted small"><?php echo e($current_admin['email']); ?></span></li>
                    <li><span class="dropdown-item-text"><span class="badge bg-secondary"><?php echo e(ucwords(str_replace('_', ' ', $current_admin['role']))); ?></span></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo ADMIN_BASE_URL; ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <?php include __DIR__ . '/flash.php'; ?>
