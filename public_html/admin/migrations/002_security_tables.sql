-- Security Tables Migration
-- Run this after the main schema.sql

-- Rate Limits Table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    tool_slug VARCHAR(100) NOT NULL,
    request_count INT UNSIGNED DEFAULT 1,
    window_start DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_ip_tool_window (ip_address, tool_slug, window_start),
    INDEX idx_window_start (window_start),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Error Logs Table
CREATE TABLE IF NOT EXISTS error_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'error',
    message TEXT NOT NULL,
    context JSON,
    tool_slug VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    url VARCHAR(2000),
    file VARCHAR(500),
    line INT UNSIGNED,
    stack_trace TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_level (level),
    INDEX idx_tool_slug (tool_slug),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cleanup Events (optional - for automatic maintenance)
-- Note: Requires EVENT scheduler to be enabled (SET GLOBAL event_scheduler = ON;)

-- Clean up old rate limit records daily
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_rate_limits
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY);
END//
DELIMITER ;

-- Clean up old error logs monthly
DELIMITER //
CREATE EVENT IF NOT EXISTS cleanup_error_logs
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
END//
DELIMITER ;

-- Alternative: Manual cleanup queries (run via cron)
-- DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 DAY);
-- DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
