-- ============================================================================
-- Payment Management System - Rollback Script
-- ============================================================================
-- Created: 2025-11-25
-- Description: Rollback script to remove payment system tables and triggers
-- WARNING: This will DELETE all payment data! Use with caution!
-- ============================================================================

USE traffic_system;

-- ============================================================================
-- Step 1: Drop Triggers First
-- ============================================================================

DROP TRIGGER IF EXISTS after_payment_insert;
DROP TRIGGER IF EXISTS after_payment_update;
DROP TRIGGER IF EXISTS before_receipt_print;

-- ============================================================================
-- Step 2: Drop Tables in Reverse Order (child tables first)
-- ============================================================================

-- Drop audit table (no foreign key dependencies)
DROP TABLE IF EXISTS payment_audit;

-- Drop receipts table (depends on payments)
DROP TABLE IF EXISTS receipts;

-- Drop payments table (depends on citations and users)
DROP TABLE IF EXISTS payments;

-- Drop sequence table
DROP TABLE IF EXISTS receipt_sequence;

-- ============================================================================
-- Verification
-- ============================================================================

SELECT 'Rollback completed successfully!' AS Status;

-- Check if tables were removed
SELECT
    TABLE_NAME
FROM
    INFORMATION_SCHEMA.TABLES
WHERE
    TABLE_SCHEMA = 'traffic_system'
    AND TABLE_NAME IN ('payments', 'receipts', 'receipt_sequence', 'payment_audit');

-- If no rows returned, rollback was successful

-- ============================================================================
-- END OF ROLLBACK
-- ============================================================================
