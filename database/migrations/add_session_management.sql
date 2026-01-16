-- =====================================================
-- SESSION MANAGEMENT: Multi-Device Login Control
-- Tracks active user sessions and limits concurrent logins
-- =====================================================

-- Create active_sessions table
CREATE TABLE IF NOT EXISTS active_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    device_info VARCHAR(255) NULL COMMENT 'Browser/Device information',
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,

    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Instructions:
-- Run this SQL script in your MySQL database to add
-- session management and device limit features
-- =====================================================
