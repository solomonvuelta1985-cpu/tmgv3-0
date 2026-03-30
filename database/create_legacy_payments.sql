-- ============================================================
-- CREATE LEGACY PAYMENT RECORDS
-- For 2704 citations marked 'paid' before the payment tracking
-- system was introduced (Dec 2025).
-- ============================================================
-- Date: 2026-03-26
-- ============================================================
-- Uses a temp table to avoid MySQL trigger conflict (trigger
-- cannot update citations while INSERT reads from it).
-- ============================================================

-- Step 1: Collect legacy citation data into temp table
CREATE TEMPORARY TABLE tmp_legacy_citations AS
SELECT
    c.citation_id,
    c.total_fine,
    c.created_at
FROM citations c
LEFT JOIN payments p ON c.citation_id = p.citation_id
WHERE c.status = 'paid'
    AND c.deleted_at IS NULL
    AND p.payment_id IS NULL;

-- Verify count before proceeding
SELECT COUNT(*) AS legacy_citations_found FROM tmp_legacy_citations;

-- Step 2: Insert legacy payment records from temp table
-- The after_payment_insert trigger will automatically:
--   1. Set payment_date on each citation
--   2. Create payment_audit entries
INSERT INTO payments (
    citation_id,
    amount_paid,
    payment_method,
    payment_date,
    receipt_number,
    collected_by,
    notes,
    status,
    created_at,
    updated_at
)
SELECT
    t.citation_id,
    t.total_fine,
    'cash',
    t.created_at,
    CONCAT('LEGACY-', LPAD(t.citation_id, 5, '0')),
    1,
    'Legacy payment record - pre-payment-tracking system (imported 2026-03-26)',
    'completed',
    t.created_at,
    NOW()
FROM tmp_legacy_citations t;

-- Step 3: Cleanup
DROP TEMPORARY TABLE tmp_legacy_citations;

-- ============================================================
-- Verification:
--   SELECT COUNT(*) FROM payments WHERE receipt_number LIKE 'LEGACY-%';
--   -- Expected: 2704
--
--   SELECT COUNT(*) FROM citations c
--   LEFT JOIN payments p ON c.citation_id = p.citation_id
--   WHERE c.status = 'paid' AND c.deleted_at IS NULL AND p.payment_id IS NULL;
--   -- Expected: 0
-- ============================================================
