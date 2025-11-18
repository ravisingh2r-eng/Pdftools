<?php
/**
 * Environment Configuration
 *
 * Contains environment-specific settings.
 * This file should be in .gitignore and copied manually to each environment.
 *
 * Copy this file as env.php and update values for your environment.
 */

// =============================================================================
// ENVIRONMENT
// =============================================================================

/**
 * Environment type: 'local', 'staging', 'production'
 */
define('ENVIRONMENT', 'local');

/**
 * Debug mode - enables detailed error messages
 * Set to false in production!
 */
define('DEBUG_MODE', true);

/**
 * Log level: 'debug', 'info', 'warning', 'error'
 * Lower levels include all higher levels
 */
define('LOG_LEVEL', 'debug');

// =============================================================================
// DATABASE
// =============================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// =============================================================================
// SITE SETTINGS
// =============================================================================

/**
 * Base URL without trailing slash
 */
define('BASE_URL', 'https://yourdomain.com');

/**
 * Site name
 */
define('SITE_NAME', 'PDF Tools');

/**
 * Site description for SEO
 */
define('SITE_DESCRIPTION', 'Free online PDF tools to merge, split, compress, rotate, and protect your PDF files');

// =============================================================================
// FILE HANDLING
// =============================================================================

/**
 * Upload directory (absolute path)
 */
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

/**
 * Temporary files directory
 */
define('TEMP_DIR', __DIR__ . '/../uploads/temp/');

/**
 * Logs directory
 */
define('LOG_DIR', __DIR__ . '/../logs/');

/**
 * Maximum file upload size in bytes (default: 50MB)
 */
define('MAX_FILE_SIZE', 50 * 1024 * 1024);

/**
 * Allowed file extensions for upload
 */
define('ALLOWED_EXTENSIONS', ['pdf']);

// =============================================================================
// SECURITY
// =============================================================================

/**
 * Session timeout in seconds (default: 1 hour)
 */
define('SESSION_TIMEOUT', 3600);

/**
 * Rate limit: requests per window
 */
define('RATE_LIMIT_REQUESTS', 20);

/**
 * Rate limit: window in seconds
 */
define('RATE_LIMIT_WINDOW', 3600);

// =============================================================================
// ADMIN
// =============================================================================

/**
 * Admin email for notifications
 */
define('ADMIN_EMAIL', 'admin@yourdomain.com');

// =============================================================================
// EXTERNAL SERVICES (optional)
// =============================================================================

/**
 * Google Analytics ID (leave empty to disable)
 */
define('GA_TRACKING_ID', '');

/**
 * Cloudflare settings
 */
define('CLOUDFLARE_ENABLED', false);
