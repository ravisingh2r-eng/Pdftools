-- PDF Tools Admin Panel Database Schema
-- Run this SQL to set up the database tables

-- =====================================================
-- ADMINS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'editor', 'ad_manager') NOT NULL DEFAULT 'editor',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TOOLS TABLE
-- Stores tool metadata (can sync with tools_registry.php)
-- =====================================================
CREATE TABLE IF NOT EXISTS tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    color VARCHAR(20) DEFAULT NULL,
    short_description TEXT,
    long_description TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_premium TINYINT(1) NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 100,
    total_uses INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active),
    INDEX idx_is_featured (is_featured),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TOOL_CONTENT TABLE
-- SEO and content for each tool page
-- =====================================================
CREATE TABLE IF NOT EXISTS tool_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tool_id INT NOT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description TEXT,
    primary_keyword VARCHAR(100) DEFAULT NULL,
    intro_html TEXT,
    how_it_works_html TEXT,
    features_html TEXT,
    faq_json JSON DEFAULT NULL,
    schema_json JSON DEFAULT NULL,
    og_image_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tool_id (tool_id),
    FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TOOL_SETTINGS TABLE
-- Processing settings and limits for each tool
-- =====================================================
CREATE TABLE IF NOT EXISTS tool_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tool_id INT NOT NULL,
    max_file_size_mb INT DEFAULT 50,
    max_files INT DEFAULT 20,
    allowed_extensions VARCHAR(255) DEFAULT 'pdf',
    enable_logging TINYINT(1) DEFAULT 1,
    rate_limit_per_hour INT DEFAULT 100,
    custom_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tool_id (tool_id),
    FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AD_SLOTS TABLE
-- Global ad slot definitions
-- =====================================================
CREATE TABLE IF NOT EXISTS ad_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_key VARCHAR(50) NOT NULL UNIQUE,
    slot_name VARCHAR(100) NOT NULL,
    description TEXT,
    default_code TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slot_key (slot_key),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TOOL_AD_OVERRIDES TABLE
-- Per-tool ad code overrides
-- =====================================================
CREATE TABLE IF NOT EXISTS tool_ad_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tool_id INT NOT NULL,
    ad_slot_id INT NOT NULL,
    custom_code TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tool_slot (tool_id, ad_slot_id),
    FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
    FOREIGN KEY (ad_slot_id) REFERENCES ad_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- USAGE_LOGS TABLE
-- Tool usage analytics
-- =====================================================
CREATE TABLE IF NOT EXISTS usage_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tool_slug VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT,
    file_count INT DEFAULT 1,
    total_size_bytes BIGINT DEFAULT 0,
    processing_time_ms INT DEFAULT 0,
    status ENUM('success', 'error') DEFAULT 'success',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tool_slug (tool_slug),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FEEDBACK TABLE
-- User feedback and support requests
-- =====================================================
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tool_slug VARCHAR(100) DEFAULT NULL,
    name VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'in_review', 'done') NOT NULL DEFAULT 'new',
    admin_notes TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tool_slug (tool_slug),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SYSTEM_SETTINGS TABLE
-- Global system configuration
-- =====================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'int', 'bool', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DEFAULT DATA
-- =====================================================

-- Default admin user (password: Admin@123)
INSERT INTO admins (email, password, name, role, is_active) VALUES
('admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'super_admin', 1);

-- Default ad slots
INSERT INTO ad_slots (slot_key, slot_name, description, default_code, is_active) VALUES
('header_banner', 'Header Banner', 'Top of page banner ad (728x90 or responsive)', '<!-- Header Ad Placeholder -->', 1),
('sidebar_top', 'Sidebar Top', 'Top of sidebar ad unit', '<!-- Sidebar Top Ad Placeholder -->', 1),
('sidebar_bottom', 'Sidebar Bottom', 'Bottom of sidebar ad unit', '<!-- Sidebar Bottom Ad Placeholder -->', 1),
('before_tool', 'Before Tool', 'Above the tool form', '<!-- Before Tool Ad Placeholder -->', 1),
('after_tool', 'After Tool', 'Below the tool form', '<!-- After Tool Ad Placeholder -->', 1),
('in_content', 'In Content', 'Within content sections', '<!-- In Content Ad Placeholder -->', 1),
('footer_banner', 'Footer Banner', 'Bottom of page banner', '<!-- Footer Ad Placeholder -->', 1);

-- Default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'PDF Tools', 'string', 'Website name'),
('maintenance_mode', '0', 'bool', 'Enable maintenance mode'),
('max_upload_size_mb', '50', 'int', 'Maximum file upload size in MB'),
('temp_file_retention_hours', '24', 'int', 'Hours to keep temp files before cleanup'),
('enable_analytics', '1', 'bool', 'Enable usage logging'),
('contact_email', 'support@example.com', 'string', 'Support contact email');
