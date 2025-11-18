<?php
/**
 * Authentication Check
 *
 * Include this file at the top of every protected admin page.
 * Redirects to login if not authenticated or session expired.
 */

require_once __DIR__ . '/../config/config.php';

// Check if logged in
if (!is_logged_in()) {
    set_flash('warning', 'Please log in to continue.');
    header('Location: ' . ADMIN_BASE_URL . '/index.php');
    exit;
}

// Check session timeout
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        // Session expired
        session_unset();
        session_destroy();
        session_start();
        set_flash('warning', 'Your session has expired. Please log in again.');
        header('Location: ' . ADMIN_BASE_URL . '/index.php');
        exit;
    }
}
$_SESSION['last_activity'] = time();

// Verify admin still exists and is active
$current_admin = get_current_admin();
if (!$current_admin) {
    session_unset();
    session_destroy();
    session_start();
    set_flash('danger', 'Your account is no longer active.');
    header('Location: ' . ADMIN_BASE_URL . '/index.php');
    exit;
}
