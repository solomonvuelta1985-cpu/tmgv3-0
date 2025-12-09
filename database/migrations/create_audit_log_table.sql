-- =============================================
-- Create General Audit Log Table
-- =============================================
-- Purpose: General purpose audit logging for all system actions
-- Date: 2025-12-09
-- =============================================

CREATE TABLE IF NOT EXISTS `audit_log` (
    `audit_id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NULL,
    `action` VARCHAR(50) NOT NULL,
    `table_name` VARCHAR(50) NOT NULL,
    `record_id` INT(11) NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`audit_id`),

    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_table` (`table_name`),
    INDEX `idx_created` (`created_at`),
    INDEX `idx_record` (`table_name`, `record_id`),

    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='General audit trail for all system actions';
