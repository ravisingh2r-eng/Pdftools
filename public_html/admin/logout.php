<?php
/**
 * Admin Logout
 */

require_once __DIR__ . '/config/config.php';

// Destroy session
session_unset();
session_destroy();

// Start new session for flash message
session_start();
set_flash('success', 'You have been logged out successfully.');

header('Location: ' . ADMIN_BASE_URL . '/index.php');
exit;
