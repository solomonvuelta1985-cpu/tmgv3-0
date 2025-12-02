-- ============================================================================
-- Add 'pending_print' Status to Payments Table
-- ============================================================================
-- Created: 2025-11-26
-- Description: Adds 'pending_print' status to handle print confirmation workflow
-- ============================================================================

USE traffic_system;

-- Add 'pending_print' to the status ENUM
ALTER TABLE payments
MODIFY COLUMN status ENUM('completed', 'pending', 'pending_print', 'failed', 'refunded', 'cancelled', 'voided') DEFAULT 'completed';

-- Verify the change
SHOW COLUMNS FROM payments LIKE 'status';

-- ============================================================================
-- Status Explanation:
-- ============================================================================
-- completed     - Payment finalized, receipt printed successfully
-- pending       - Payment not yet processed
-- pending_print - Payment recorded, waiting for print confirmation
-- failed        - Payment processing failed
-- refunded      - Payment was refunded
-- cancelled     - Payment was cancelled
-- voided        - Payment voided due to printer issues or errors
-- ============================================================================
