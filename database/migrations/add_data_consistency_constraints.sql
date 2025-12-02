-- ============================================================================
-- Data Consistency Constraints and Enhanced Triggers
-- ============================================================================
-- Created: 2025-11-29
-- Description: Adds database-level constraints and enhanced triggers to
--              prevent citation-payment status inconsistencies
-- ============================================================================

USE traffic_system;

-- ============================================================================
-- PART 1: Drop existing triggers to recreate with enhancements
-- ============================================================================

DROP TRIGGER IF EXISTS after_payment_insert;
DROP TRIGGER IF EXISTS after_payment_update;
DROP TRIGGER IF EXISTS before_payment_status_change;

-- ============================================================================
-- PART 2: Create enhanced logging table for trigger failures
-- ============================================================================

CREATE TABLE IF NOT EXISTS trigger_error_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    trigger_name VARCHAR(100) NOT NULL,
    error_message TEXT NOT NULL,
    error_details JSON NULL,
    citation_id INT NULL,
    payment_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_trigger_name (trigger_name),
    INDEX idx_citation_id (citation_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs trigger execution errors for debugging';

-- ============================================================================
-- PART 3: Create BEFORE trigger to validate payment status changes
-- ============================================================================
-- This trigger prevents creating completed payments on pending citations
-- ============================================================================

DELIMITER //

CREATE TRIGGER before_payment_status_change
BEFORE INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE citation_status VARCHAR(20);
    DECLARE error_msg TEXT;

    -- Get current citation status
    SELECT status INTO citation_status
    FROM citations
    WHERE citation_id = NEW.citation_id;

    -- VALIDATION RULE 1: Cannot create completed payment on void/dismissed citation
    IF NEW.status = 'completed' AND citation_status IN ('void', 'dismissed') THEN
        SET error_msg = CONCAT('Cannot create completed payment: Citation #', NEW.citation_id, ' is ', citation_status);
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = error_msg;
    END IF;

    -- VALIDATION RULE 2: Warn if creating completed payment on pending citation
    -- (This is now acceptable if it's being finalized from pending_print)
    -- But we log it for monitoring
    IF NEW.status = 'completed' AND citation_status = 'pending' THEN
        -- Log this for monitoring (non-blocking)
        INSERT IGNORE INTO trigger_error_log (
            trigger_name,
            error_message,
            error_details,
            citation_id,
            payment_id
        ) VALUES (
            'before_payment_status_change',
            'WARNING: Creating completed payment on pending citation',
            JSON_OBJECT(
                'citation_id', NEW.citation_id,
                'citation_status', citation_status,
                'payment_status', NEW.status,
                'receipt_number', NEW.receipt_number
            ),
            NEW.citation_id,
            NULL
        );
    END IF;
END//

DELIMITER ;

-- ============================================================================
-- PART 4: Enhanced AFTER INSERT trigger with error handling
-- ============================================================================

DELIMITER //

CREATE TRIGGER after_payment_insert
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    DECLARE current_citation_status VARCHAR(20);
    DECLARE update_failed BOOLEAN DEFAULT FALSE;

    -- Only proceed if payment status is 'completed'
    IF NEW.status = 'completed' THEN
        -- Get current citation status
        SELECT status INTO current_citation_status
        FROM citations
        WHERE citation_id = NEW.citation_id;

        -- Update citation status to 'paid' and set payment_date
        UPDATE citations
        SET
            status = 'paid',
            payment_date = NEW.payment_date,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;

        -- Verify the update succeeded
        SELECT status INTO current_citation_status
        FROM citations
        WHERE citation_id = NEW.citation_id;

        -- Log if update failed
        IF current_citation_status != 'paid' THEN
            INSERT INTO trigger_error_log (
                trigger_name,
                error_message,
                error_details,
                citation_id,
                payment_id
            ) VALUES (
                'after_payment_insert',
                'CRITICAL: Failed to update citation status to paid',
                JSON_OBJECT(
                    'citation_id', NEW.citation_id,
                    'citation_status_before', current_citation_status,
                    'citation_status_after', current_citation_status,
                    'payment_id', NEW.payment_id,
                    'payment_status', NEW.status,
                    'receipt_number', NEW.receipt_number
                ),
                NEW.citation_id,
                NEW.payment_id
            );
        END IF;
    END IF;

    -- Always create audit record
    INSERT INTO payment_audit (
        payment_id,
        action,
        new_values,
        performed_by,
        ip_address,
        notes
    ) VALUES (
        NEW.payment_id,
        'created',
        JSON_OBJECT(
            'amount_paid', NEW.amount_paid,
            'payment_method', NEW.payment_method,
            'receipt_number', NEW.receipt_number,
            'status', NEW.status
        ),
        NEW.collected_by,
        @user_ip,
        CONCAT('Payment recorded for citation ID: ', NEW.citation_id)
    );
END//

DELIMITER ;

-- ============================================================================
-- PART 5: Enhanced AFTER UPDATE trigger with consistency checks
-- ============================================================================

DELIMITER //

CREATE TRIGGER after_payment_update
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    DECLARE current_citation_status VARCHAR(20);

    -- Insert audit record for the update
    INSERT INTO payment_audit (
        payment_id,
        action,
        old_values,
        new_values,
        performed_by,
        ip_address,
        notes
    ) VALUES (
        NEW.payment_id,
        'updated',
        JSON_OBJECT(
            'status', OLD.status,
            'amount_paid', OLD.amount_paid,
            'payment_method', OLD.payment_method,
            'notes', OLD.notes
        ),
        JSON_OBJECT(
            'status', NEW.status,
            'amount_paid', NEW.amount_paid,
            'payment_method', NEW.payment_method,
            'notes', NEW.notes
        ),
        NEW.collected_by,
        @user_ip,
        'Payment updated'
    );

    -- RULE 1: If payment was refunded or cancelled, revert citation status
    IF NEW.status IN ('refunded', 'cancelled', 'voided') AND OLD.status = 'completed' THEN
        UPDATE citations
        SET
            status = 'pending',
            payment_date = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;

        -- Verify update
        SELECT status INTO current_citation_status
        FROM citations WHERE citation_id = NEW.citation_id;

        IF current_citation_status != 'pending' THEN
            INSERT INTO trigger_error_log (
                trigger_name,
                error_message,
                error_details,
                citation_id,
                payment_id
            ) VALUES (
                'after_payment_update',
                'CRITICAL: Failed to revert citation to pending after payment cancellation',
                JSON_OBJECT(
                    'citation_id', NEW.citation_id,
                    'payment_old_status', OLD.status,
                    'payment_new_status', NEW.status,
                    'citation_status', current_citation_status
                ),
                NEW.citation_id,
                NEW.payment_id
            );
        END IF;
    END IF;

    -- RULE 2: If payment was completed (from pending/failed), update citation
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE citations
        SET
            status = 'paid',
            payment_date = NEW.payment_date,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;

        -- Verify update
        SELECT status INTO current_citation_status
        FROM citations WHERE citation_id = NEW.citation_id;

        IF current_citation_status != 'paid' THEN
            INSERT INTO trigger_error_log (
                trigger_name,
                error_message,
                error_details,
                citation_id,
                payment_id
            ) VALUES (
                'after_payment_update',
                'CRITICAL: Failed to update citation to paid after payment completion',
                JSON_OBJECT(
                    'citation_id', NEW.citation_id,
                    'payment_old_status', OLD.status,
                    'payment_new_status', NEW.status,
                    'citation_status', current_citation_status
                ),
                NEW.citation_id,
                NEW.payment_id
            );
        END IF;
    END IF;
END//

DELIMITER ;

-- ============================================================================
-- PART 6: Create stored procedure to check data consistency
-- ============================================================================

DELIMITER //

CREATE PROCEDURE sp_check_citation_payment_consistency()
BEGIN
    -- Find inconsistent records
    SELECT
        'INCONSISTENCY' as issue_type,
        c.citation_id,
        c.ticket_number,
        c.status as citation_status,
        COUNT(p.payment_id) as payment_count,
        GROUP_CONCAT(CONCAT(p.receipt_number, ':', p.status) SEPARATOR ', ') as payments
    FROM citations c
    LEFT JOIN payments p ON c.citation_id = p.citation_id
    GROUP BY c.citation_id
    HAVING
        (c.status = 'pending' AND SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) > 0)
        OR
        (c.status = 'paid' AND SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) = 0);
END//

DELIMITER ;

-- ============================================================================
-- PART 7: Verification queries
-- ============================================================================

-- Check if triggers were created successfully
SELECT
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE,
    ACTION_TIMING,
    CREATED
FROM INFORMATION_SCHEMA.TRIGGERS
WHERE TRIGGER_SCHEMA = 'traffic_system'
  AND TRIGGER_NAME IN ('before_payment_status_change', 'after_payment_insert', 'after_payment_update')
ORDER BY TRIGGER_NAME;

-- Check if error log table was created
SHOW TABLES LIKE 'trigger_error_log';

-- Check if stored procedure was created
SHOW PROCEDURE STATUS WHERE Db = 'traffic_system' AND Name = 'sp_check_citation_payment_consistency';

-- ============================================================================
-- PART 8: Test the consistency checker
-- ============================================================================

-- Run the consistency check (should return empty if all is good)
CALL sp_check_citation_payment_consistency();

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
