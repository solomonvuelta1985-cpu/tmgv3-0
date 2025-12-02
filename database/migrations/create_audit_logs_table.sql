-- =============================================
-- Create Audit Logs Table
-- =============================================
-- Purpose: Track all OR number changes and payment actions
--          for government compliance (BIR/COA)
-- Date: 2025-01-29
-- =============================================

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `action_type` VARCHAR(50) NOT NULL COMMENT 'Type of action: payment_created, payment_cancelled, payment_voided, or_number_changed, payment_finalized',
  `entity_type` VARCHAR(50) NOT NULL COMMENT 'Type of entity: payment, receipt, citation',
  `entity_id` INT(11) NOT NULL COMMENT 'ID of the entity (payment_id, receipt_id, citation_id)',

  -- OR Number Tracking
  `or_number_old` VARCHAR(50) DEFAULT NULL COMMENT 'Previous OR number (for changes)',
  `or_number_new` VARCHAR(50) DEFAULT NULL COMMENT 'New OR number',

  -- Payment Details
  `ticket_number` VARCHAR(50) DEFAULT NULL COMMENT 'Citation ticket number',
  `amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Payment amount',
  `payment_status_old` VARCHAR(20) DEFAULT NULL COMMENT 'Previous payment status',
  `payment_status_new` VARCHAR(20) DEFAULT NULL COMMENT 'New payment status',

  -- User & Timestamp
  `user_id` INT(11) NOT NULL COMMENT 'User who performed the action',
  `username` VARCHAR(100) NOT NULL COMMENT 'Username (for quick reference)',
  `action_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the action occurred',

  -- Additional Context
  `reason` TEXT DEFAULT NULL COMMENT 'Reason for the action',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of user',
  `user_agent` TEXT DEFAULT NULL COMMENT 'Browser/device information',

  -- Metadata
  `additional_data` TEXT DEFAULT NULL COMMENT 'JSON formatted additional data',

  PRIMARY KEY (`log_id`),
  INDEX `idx_action_type` (`action_type`),
  INDEX `idx_entity_type_id` (`entity_type`, `entity_id`),
  INDEX `idx_or_numbers` (`or_number_old`, `or_number_new`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action_datetime` (`action_datetime`),
  INDEX `idx_ticket_number` (`ticket_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Audit trail for payments and OR numbers';

-- Add foreign key constraint (optional - if you want referential integrity)
-- ALTER TABLE `audit_logs` ADD CONSTRAINT `fk_audit_user`
--   FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
--   ON DELETE RESTRICT ON UPDATE CASCADE;

-- =============================================
-- Sample Queries for Audit Reporting
-- =============================================

-- 1. View all OR number changes
-- SELECT * FROM audit_logs WHERE action_type = 'or_number_changed' ORDER BY action_datetime DESC;

-- 2. View all cancelled payments
-- SELECT * FROM audit_logs WHERE action_type = 'payment_cancelled' ORDER BY action_datetime DESC;

-- 3. View audit trail for specific OR number
-- SELECT * FROM audit_logs WHERE or_number_old = 'CGVM15320501' OR or_number_new = 'CGVM15320501' ORDER BY action_datetime DESC;

-- 4. View all actions by a specific user
-- SELECT * FROM audit_logs WHERE user_id = 1 ORDER BY action_datetime DESC;

-- 5. View daily OR usage summary (for BIR reporting)
-- SELECT
--     DATE(action_datetime) as date,
--     COUNT(DISTINCT or_number_new) as total_or_used,
--     SUM(amount) as total_amount
-- FROM audit_logs
-- WHERE action_type IN ('payment_created', 'payment_finalized')
-- GROUP BY DATE(action_datetime)
-- ORDER BY date DESC;
