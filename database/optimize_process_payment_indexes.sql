-- ============================================================================
-- Optimization Indexes for Process Payment Page
-- Handles 10,000-20,000+ pending citations efficiently
-- ============================================================================
-- Run this in phpMyAdmin or MySQL Workbench
-- ============================================================================

USE traffic_system;

-- ============================================================================
-- ADD MISSING PERFORMANCE INDEXES
-- ============================================================================

-- 1. Composite index for the main query (status + date sorting)
--    This is CRITICAL for fast pending citations retrieval
CREATE INDEX idx_status_date ON citations(status, apprehension_datetime DESC);

-- 2. Index for driver name searches (first_name + last_name)
CREATE INDEX idx_driver_names ON citations(last_name, first_name);

-- 3. Index for plate number searches
CREATE INDEX idx_plate ON citations(plate_mv_engine_chassis_no);

-- 4. Index for fine amount filtering
CREATE INDEX idx_fine ON citations(total_fine);

-- 5. Composite index for payment exclusion logic (speeds up LEFT JOIN)
CREATE INDEX idx_citation_payment_status ON payments(citation_id, status);

-- ============================================================================
-- UPDATE TABLE STATISTICS (Important for query optimizer)
-- ============================================================================

ANALYZE TABLE citations;
ANALYZE TABLE payments;
ANALYZE TABLE violations;

-- ============================================================================
-- VERIFICATION - Check if indexes were created
-- ============================================================================

SHOW INDEX FROM citations WHERE Key_name LIKE 'idx_%';

-- ============================================================================
-- PERFORMANCE TEST QUERY
-- ============================================================================
-- This simulates the main query - should be FAST with indexes
-- ============================================================================

EXPLAIN SELECT
    c.citation_id,
    c.ticket_number,
    c.apprehension_datetime,
    c.total_fine,
    c.status,
    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
    c.license_number,
    c.plate_mv_engine_chassis_no as plate_number
FROM citations c
LEFT JOIN payments p ON c.citation_id = p.citation_id
    AND p.status IN ('pending_print', 'completed')
WHERE c.status = 'pending'
AND p.payment_id IS NULL
ORDER BY c.apprehension_datetime DESC
LIMIT 25;

-- You should see:
-- - type: ref or range (GOOD)
-- - key: idx_status_date or similar (using index)
-- - rows: Should be reasonable, not scanning whole table

