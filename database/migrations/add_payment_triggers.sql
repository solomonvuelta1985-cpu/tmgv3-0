-- ============================================================================
-- Payment Management System - Database Triggers
-- ============================================================================
-- Created: 2025-11-25
-- Description: Creates triggers for automatic payment processing
-- ============================================================================

USE traffic_system;

-- ============================================================================
-- Drop existing triggers if they exist (for re-running this script)
-- ============================================================================

DROP TRIGGER IF EXISTS after_payment_insert;
DROP TRIGGER IF EXISTS after_payment_update;
DROP TRIGGER IF EXISTS before_receipt_print;

-- ============================================================================
-- TRIGGER 1: after_payment_insert
-- ============================================================================
-- Automatically updates citation status to 'paid' when payment is recorded
-- Also sets the payment_date in the citations table
-- ============================================================================

DELIMITER //

CREATE TRIGGER after_payment_insert
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
    -- Only update if payment status is 'completed'
    IF NEW.status = 'completed' THEN
        -- Update citation status to 'paid' and set payment_date
        UPDATE citations
        SET
            status = 'paid',
            payment_date = NEW.payment_date,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;
    END IF;

    -- Insert audit record
    INSERT INTO payment_audit (
        payment_id,
        action,
        new_values,
        performed_by,
        ip_address,
        notes
    )
    VALUES (
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
-- TRIGGER 2: after_payment_update
-- ============================================================================
-- Logs all payment updates to audit trail
-- Updates citation status if payment status changes
-- ============================================================================

DELIMITER //

CREATE TRIGGER after_payment_update
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    -- Insert audit record for the update
    INSERT INTO payment_audit (
        payment_id,
        action,
        old_values,
        new_values,
        performed_by,
        ip_address,
        notes
    )
    VALUES (
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

    -- If payment was refunded or cancelled, revert citation status
    IF NEW.status IN ('refunded', 'cancelled') AND OLD.status = 'completed' THEN
        UPDATE citations
        SET
            status = 'pending',
            payment_date = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;
    END IF;

    -- If payment was completed (from pending/failed), update citation
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE citations
        SET
            status = 'paid',
            payment_date = NEW.payment_date,
            updated_at = CURRENT_TIMESTAMP
        WHERE citation_id = NEW.citation_id;
    END IF;
END//

DELIMITER ;

-- ============================================================================
-- TRIGGER 3: before_receipt_print
-- ============================================================================
-- Updates print tracking when receipt is printed
-- ============================================================================

DELIMITER //

CREATE TRIGGER before_receipt_print
BEFORE UPDATE ON receipts
FOR EACH ROW
BEGIN
    -- If print_count is incremented, update print tracking
    IF NEW.print_count > OLD.print_count THEN
        SET NEW.last_printed_at = CURRENT_TIMESTAMP;

        -- If this is the first print, set printed_at
        IF OLD.print_count = 0 THEN
            SET NEW.printed_at = CURRENT_TIMESTAMP;
        END IF;
    END IF;
END//

DELIMITER ;

-- ============================================================================
-- VERIFICATION
-- ============================================================================
-- Check if triggers were created successfully
-- ============================================================================

SELECT
    TRIGGER_NAME,
    EVENT_MANIPULATION,
    EVENT_OBJECT_TABLE,
    ACTION_TIMING,
    CREATED
FROM
    INFORMATION_SCHEMA.TRIGGERS
WHERE
    TRIGGER_SCHEMA = 'traffic_system'
    AND TRIGGER_NAME IN ('after_payment_insert', 'after_payment_update', 'before_receipt_print')
ORDER BY
    TRIGGER_NAME;

-- ============================================================================
-- END OF TRIGGERS
-- ============================================================================
