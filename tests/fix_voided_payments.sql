-- ============================================================================
-- FIX VOIDED PAYMENTS - Enhanced Citation Status Update Script
-- ============================================================================
-- This script fixes citations that have voided payments but still show
-- as 'paid' or other statuses instead of 'pending'.
--
-- Date: 2025-11-29
-- Issue: When payments are voided, the citation status wasn't being
--        reverted back to 'pending'
--
-- ENHANCED FEATURES:
-- - Shows receipt_number (OR number) for official tracking
-- - Shows reference_number (GCash/Bank/Check reference) for reconciliation
-- - Shows payment_method to understand payment type
-- - Shows payment_date for audit trail
-- ============================================================================

-- ============================================================================
-- BEFORE THE FIX: Show affected citations that need fixing
-- ============================================================================
SELECT
    '=== CITATIONS WITH VOIDED PAYMENTS (BEFORE FIX) ===' AS report_section;

SELECT
    c.citation_id,
    c.ticket_number,
    c.status AS citation_status,
    c.payment_date AS citation_payment_date,
    p.payment_id,
    p.receipt_number AS or_number,
    p.reference_number AS transaction_ref,
    p.payment_method,
    p.payment_date AS original_payment_date,
    p.status AS payment_status,
    p.amount_paid,
    SUBSTRING(p.notes, LOCATE('[VOIDED]', p.notes), 100) AS void_reason
FROM citations c
INNER JOIN payments p ON c.citation_id = p.citation_id
WHERE p.status = 'voided'
    AND c.status != 'pending'
ORDER BY c.citation_id;

-- ============================================================================
-- STEP 1: Update citations with voided payments back to 'pending' status
-- ============================================================================
SELECT
    '=== EXECUTING FIX ===' AS report_section;

UPDATE citations c
INNER JOIN payments p ON c.citation_id = p.citation_id
SET
    c.status = 'pending',
    c.payment_date = NULL,
    c.updated_at = NOW()
WHERE p.status = 'voided'
    AND c.status != 'pending';

-- Show number of affected rows
SELECT ROW_COUNT() AS 'Citations Updated (should match count above)';

-- ============================================================================
-- STEP 2: Verify the fix - Should return NO rows (all fixed)
-- ============================================================================
SELECT
    '=== VERIFICATION: Any remaining issues? ===' AS report_section;

SELECT
    c.citation_id,
    c.ticket_number,
    c.status AS citation_status,
    p.payment_id,
    p.receipt_number AS or_number,
    p.reference_number AS transaction_ref,
    p.payment_method,
    p.status AS payment_status
FROM citations c
INNER JOIN payments p ON c.citation_id = p.citation_id
WHERE p.status = 'voided'
    AND c.status != 'pending'
ORDER BY c.citation_id;

SELECT 'If the above query returns NO rows, the fix was successful!' AS verification_message;

-- ============================================================================
-- STEP 3: Show ALL citations with voided payments (should all be 'pending' now)
-- ============================================================================
SELECT
    '=== ALL VOIDED PAYMENTS (AFTER FIX) ===' AS report_section;

SELECT
    c.citation_id,
    c.ticket_number,
    c.status AS citation_status,
    c.payment_date AS citation_payment_date,
    c.total_fine,
    p.payment_id,
    p.receipt_number AS or_number,
    p.reference_number AS transaction_ref,
    p.payment_method,
    p.payment_date AS original_payment_date,
    p.status AS payment_status,
    p.amount_paid,
    CASE
        WHEN p.notes LIKE '%[VOIDED]%'
        THEN SUBSTRING(p.notes, LOCATE('[VOIDED]', p.notes) + 10, 200)
        ELSE p.notes
    END AS void_reason
FROM citations c
INNER JOIN payments p ON c.citation_id = p.citation_id
WHERE p.status = 'voided'
ORDER BY c.citation_id DESC;

-- ============================================================================
-- STEP 4: Specific check for ticket 06122
-- ============================================================================
SELECT
    '=== TICKET 06122 DETAIL ===' AS report_section;

SELECT
    c.citation_id,
    c.ticket_number,
    c.status AS citation_status,
    c.payment_date AS citation_payment_date,
    c.total_fine,
    p.payment_id,
    p.receipt_number AS or_number,
    p.reference_number AS transaction_ref,
    p.payment_method,
    p.payment_date AS original_payment_date,
    p.status AS payment_status,
    p.amount_paid,
    CASE
        WHEN p.notes LIKE '%[VOIDED]%'
        THEN SUBSTRING(p.notes, LOCATE('[VOIDED]', p.notes) + 10, 200)
        ELSE p.notes
    END AS void_reason
FROM citations c
LEFT JOIN payments p ON c.citation_id = p.citation_id
WHERE c.ticket_number = '06122'
ORDER BY p.payment_id DESC;

SELECT 'Ticket 06122 should now have citation_status = "pending"' AS ticket_06122_note;

-- ============================================================================
-- STEP 5: Payment method breakdown for voided payments
-- ============================================================================
SELECT
    '=== VOIDED PAYMENTS BY METHOD ===' AS report_section;

SELECT
    p.payment_method,
    COUNT(*) AS total_voided,
    SUM(p.amount_paid) AS total_amount_voided,
    COUNT(CASE WHEN p.reference_number IS NOT NULL THEN 1 END) AS with_reference,
    COUNT(CASE WHEN p.reference_number IS NULL THEN 1 END) AS no_reference
FROM payments p
WHERE p.status = 'voided'
GROUP BY p.payment_method
ORDER BY total_voided DESC;

-- ============================================================================
-- STEP 6: Overall payment status summary
-- ============================================================================
SELECT
    '=== OVERALL PAYMENT STATUS BREAKDOWN ===' AS report_section;

SELECT
    p.status AS payment_status,
    COUNT(*) AS total_payments,
    COUNT(DISTINCT c.citation_id) AS unique_citations,
    SUM(p.amount_paid) AS total_amount,
    MIN(p.payment_date) AS earliest_payment,
    MAX(p.payment_date) AS latest_payment
FROM payments p
LEFT JOIN citations c ON p.citation_id = c.citation_id
GROUP BY p.status
ORDER BY
    CASE p.status
        WHEN 'completed' THEN 1
        WHEN 'pending_print' THEN 2
        WHEN 'voided' THEN 3
        WHEN 'refunded' THEN 4
        ELSE 5
    END;

-- ============================================================================
-- STEP 7: Detailed voided payments report (useful for accounting)
-- ============================================================================
SELECT
    '=== DETAILED VOIDED PAYMENTS REPORT ===' AS report_section;

SELECT
    p.payment_id,
    c.ticket_number,
    CONCAT(c.first_name, ' ', c.last_name) AS driver_name,
    p.receipt_number AS or_number,
    p.reference_number AS transaction_ref,
    p.payment_method,
    p.amount_paid,
    DATE_FORMAT(p.payment_date, '%Y-%m-%d %H:%i') AS payment_datetime,
    u.full_name AS collected_by,
    CASE
        WHEN p.notes LIKE '%[VOIDED]%'
        THEN SUBSTRING(p.notes, LOCATE('[VOIDED]', p.notes) + 10, 200)
        ELSE 'No reason recorded'
    END AS void_reason,
    r.cancelled_by AS voided_by_user_id,
    DATE_FORMAT(r.cancelled_at, '%Y-%m-%d %H:%i') AS voided_datetime
FROM payments p
INNER JOIN citations c ON p.citation_id = c.citation_id
LEFT JOIN users u ON p.collected_by = u.user_id
LEFT JOIN receipts r ON p.payment_id = r.payment_id
WHERE p.status = 'voided'
ORDER BY p.payment_date DESC;

-- ============================================================================
-- FINAL SUMMARY
-- ============================================================================
SELECT
    '=== FIX COMPLETE ===' AS final_summary;

SELECT
    COUNT(CASE WHEN p.status = 'voided' THEN 1 END) AS total_voided_payments,
    COUNT(CASE WHEN p.status = 'voided' AND c.status = 'pending' THEN 1 END) AS correctly_pending,
    COUNT(CASE WHEN p.status = 'voided' AND c.status != 'pending' THEN 1 END) AS still_wrong
FROM payments p
LEFT JOIN citations c ON p.citation_id = c.citation_id;

-- ============================================================================
-- NOTES & COLUMN DESCRIPTIONS:
-- ============================================================================
-- or_number           = Official Receipt number (from physical receipt booklet)
-- transaction_ref     = GCash/PayMaya/Bank/Check reference number
-- payment_method      = How the payment was made (cash, gcash, bank_transfer, etc.)
-- original_payment_date = When the payment was first recorded
-- void_reason         = Why the payment was voided
--
-- WHAT TO DO NEXT:
-- 1. Run this entire script in phpMyAdmin
-- 2. Check that "still_wrong" = 0 in the final summary
-- 3. Verify ticket 06122 now appears in process_payment.php
-- 4. All voided payments should have citation_status = 'pending'
--
-- RUNNING THE SCRIPT:
-- - Copy ALL content of this file
-- - Open phpMyAdmin → Select 'traffic_system' database → SQL tab
-- - Paste and click "Go"
-- - Review all the result sets to understand what was fixed
-- ============================================================================
