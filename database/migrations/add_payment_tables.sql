-- ============================================================================
-- Payment Management System - Database Tables Migration
-- ============================================================================
-- Created: 2025-11-25
-- Description: Creates tables for payment management and receipt generation
-- ============================================================================

-- Use the traffic_system database
USE traffic_system;

-- ============================================================================
-- 1. PAYMENTS TABLE
-- ============================================================================
-- Stores all payment transactions for citations
-- ============================================================================

CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    citation_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'online', 'gcash', 'paymaya', 'bank_transfer', 'money_order') NOT NULL DEFAULT 'cash',
    payment_date DATETIME NOT NULL,
    reference_number VARCHAR(100) NULL COMMENT 'Check number, transaction ID, etc.',
    receipt_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Official Receipt number',
    collected_by INT NOT NULL COMMENT 'User ID of cashier/collector',

    -- Check-specific fields
    check_number VARCHAR(50) NULL,
    check_bank VARCHAR(100) NULL,
    check_date DATE NULL,

    -- Additional information
    notes TEXT NULL,
    status ENUM('completed', 'pending', 'failed', 'refunded', 'cancelled') DEFAULT 'completed',

    -- Audit fields
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (citation_id) REFERENCES citations(citation_id) ON DELETE RESTRICT,
    FOREIGN KEY (collected_by) REFERENCES users(user_id) ON DELETE RESTRICT,

    -- Indexes for performance
    INDEX idx_citation (citation_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_collected_by (collected_by),
    INDEX idx_status (status),
    INDEX idx_payment_method (payment_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores payment transactions for traffic citations';

-- ============================================================================
-- 2. RECEIPTS TABLE
-- ============================================================================
-- Tracks receipt generation, printing, and cancellation
-- ============================================================================

CREATE TABLE IF NOT EXISTS receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,

    -- Generation tracking
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    generated_by INT NOT NULL COMMENT 'User ID who generated the receipt',

    -- Print tracking
    printed_at DATETIME NULL,
    print_count INT DEFAULT 0,
    last_printed_by INT NULL,
    last_printed_at DATETIME NULL,

    -- Status management
    status ENUM('active', 'cancelled', 'void') DEFAULT 'active',
    cancellation_reason TEXT NULL,
    cancelled_by INT NULL,
    cancelled_at DATETIME NULL,

    -- Foreign keys
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE RESTRICT,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (last_printed_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (cancelled_by) REFERENCES users(user_id) ON DELETE RESTRICT,

    -- Indexes
    INDEX idx_payment (payment_id),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_status (status),
    INDEX idx_generated_at (generated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks receipt generation, printing, and cancellation';

-- ============================================================================
-- 3. RECEIPT_SEQUENCE TABLE
-- ============================================================================
-- Manages OR number generation (auto-increment per year)
-- ============================================================================

CREATE TABLE IF NOT EXISTS receipt_sequence (
    id INT PRIMARY KEY DEFAULT 1,
    current_year INT NOT NULL,
    current_number INT NOT NULL DEFAULT 0,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (id = 1) -- Only one row allowed
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Manages OR number sequence generation';

-- Initialize with current year
INSERT INTO receipt_sequence (id, current_year, current_number)
VALUES (1, YEAR(CURDATE()), 0)
ON DUPLICATE KEY UPDATE id = id; -- Don't reset if already exists

-- ============================================================================
-- 4. PAYMENT_AUDIT TABLE
-- ============================================================================
-- Comprehensive audit trail for all payment-related actions
-- ============================================================================

CREATE TABLE IF NOT EXISTS payment_audit (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    action ENUM('created', 'updated', 'refunded', 'cancelled', 'voided') NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    performed_by INT NOT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    notes TEXT NULL,

    -- Foreign keys
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE RESTRICT,
    FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE RESTRICT,

    -- Indexes
    INDEX idx_payment (payment_id),
    INDEX idx_action (action),
    INDEX idx_performed_at (performed_at),
    INDEX idx_performed_by (performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for payment transactions';

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these to verify tables were created successfully
-- ============================================================================

-- Check if all tables exist
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME,
    TABLE_COMMENT
FROM
    INFORMATION_SCHEMA.TABLES
WHERE
    TABLE_SCHEMA = 'traffic_system'
    AND TABLE_NAME IN ('payments', 'receipts', 'receipt_sequence', 'payment_audit')
ORDER BY
    TABLE_NAME;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
