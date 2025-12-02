-- ============================================================================
-- Soft Deletes Migration
-- ============================================================================
-- Created: 2025-11-29
-- Description: Adds soft delete functionality to maintain audit trail
--              and preserve referential integrity
-- ============================================================================

USE traffic_system;

-- ============================================================================
-- PART 1: Add soft delete columns to citations table
-- ============================================================================

ALTER TABLE citations
ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
ADD COLUMN deleted_by INT NULL COMMENT 'User who deleted the citation',
ADD COLUMN deletion_reason TEXT NULL COMMENT 'Reason for deletion',
ADD INDEX idx_deleted_at (deleted_at),
ADD INDEX idx_deleted_by (deleted_by);

-- Add foreign key for deleted_by (optional - can be null if user is deleted)
ALTER TABLE citations
ADD CONSTRAINT fk_citations_deleted_by
FOREIGN KEY (deleted_by) REFERENCES users(user_id)
ON DELETE SET NULL;

-- ============================================================================
-- PART 2: Create view for active (non-deleted) citations
-- ============================================================================

CREATE OR REPLACE VIEW vw_active_citations AS
SELECT
    c.*,
    GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violations,
    cv.vehicle_type,
    u.full_name as created_by_name,
    du.full_name as deleted_by_name
FROM citations c
LEFT JOIN violations v ON c.citation_id = v.citation_id
LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
LEFT JOIN citation_vehicles cv ON c.citation_id = cv.citation_id
LEFT JOIN users u ON c.created_by = u.user_id
LEFT JOIN users du ON c.deleted_by = du.user_id
WHERE c.deleted_at IS NULL
GROUP BY c.citation_id
ORDER BY c.created_at DESC;

-- ============================================================================
-- PART 3: Create view for deleted citations (trash bin)
-- ============================================================================

CREATE OR REPLACE VIEW vw_deleted_citations AS
SELECT
    c.*,
    GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violations,
    cv.vehicle_type,
    u.full_name as created_by_name,
    du.full_name as deleted_by_name,
    DATEDIFF(NOW(), c.deleted_at) as days_in_trash
FROM citations c
LEFT JOIN violations v ON c.citation_id = v.citation_id
LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
LEFT JOIN citation_vehicles cv ON c.citation_id = cv.citation_id
LEFT JOIN users u ON c.created_by = u.user_id
LEFT JOIN users du ON c.deleted_by = du.user_id
WHERE c.deleted_at IS NOT NULL
GROUP BY c.citation_id
ORDER BY c.deleted_at DESC;

-- ============================================================================
-- PART 4: Create stored procedures for soft delete operations
-- ============================================================================

DELIMITER //

-- Procedure to soft delete a citation
CREATE PROCEDURE sp_soft_delete_citation(
    IN p_citation_id INT,
    IN p_deleted_by INT,
    IN p_reason TEXT
)
BEGIN
    DECLARE citation_exists INT;
    DECLARE is_already_deleted INT;

    -- Check if citation exists
    SELECT COUNT(*) INTO citation_exists
    FROM citations
    WHERE citation_id = p_citation_id;

    IF citation_exists = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Citation not found';
    END IF;

    -- Check if already deleted
    SELECT COUNT(*) INTO is_already_deleted
    FROM citations
    WHERE citation_id = p_citation_id
      AND deleted_at IS NOT NULL;

    IF is_already_deleted > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Citation is already deleted';
    END IF;

    -- Perform soft delete
    UPDATE citations
    SET
        deleted_at = NOW(),
        deleted_by = p_deleted_by,
        deletion_reason = p_reason,
        updated_at = NOW()
    WHERE citation_id = p_citation_id;

    -- Return success message
    SELECT
        citation_id,
        ticket_number,
        deleted_at,
        deleted_by,
        deletion_reason
    FROM citations
    WHERE citation_id = p_citation_id;
END//

-- Procedure to restore a deleted citation
CREATE PROCEDURE sp_restore_citation(
    IN p_citation_id INT,
    IN p_restored_by INT
)
BEGIN
    DECLARE citation_exists INT;
    DECLARE is_deleted INT;

    -- Check if citation exists
    SELECT COUNT(*) INTO citation_exists
    FROM citations
    WHERE citation_id = p_citation_id;

    IF citation_exists = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Citation not found';
    END IF;

    -- Check if actually deleted
    SELECT COUNT(*) INTO is_deleted
    FROM citations
    WHERE citation_id = p_citation_id
      AND deleted_at IS NOT NULL;

    IF is_deleted = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Citation is not deleted';
    END IF;

    -- Restore citation
    UPDATE citations
    SET
        deleted_at = NULL,
        deleted_by = NULL,
        deletion_reason = NULL,
        updated_at = NOW()
    WHERE citation_id = p_citation_id;

    -- Log restoration
    INSERT INTO audit_log (
        user_id,
        action,
        table_name,
        record_id,
        new_values,
        created_at
    ) VALUES (
        p_restored_by,
        'restore',
        'citations',
        p_citation_id,
        JSON_OBJECT(
            'action', 'Citation restored from trash',
            'restored_by', p_restored_by,
            'restored_at', NOW()
        ),
        NOW()
    );

    -- Return success message
    SELECT
        citation_id,
        ticket_number,
        'Citation restored successfully' as message
    FROM citations
    WHERE citation_id = p_citation_id;
END//

-- Procedure to permanently delete citations older than N days
CREATE PROCEDURE sp_permanently_delete_old_citations(
    IN p_days_old INT,
    IN p_limit INT
)
BEGIN
    DECLARE deleted_count INT DEFAULT 0;

    -- Safety check: require at least 30 days
    IF p_days_old < 30 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Must keep deleted citations for at least 30 days';
    END IF;

    -- Create temporary table to store citations to delete
    CREATE TEMPORARY TABLE IF NOT EXISTS temp_citations_to_delete AS
    SELECT citation_id
    FROM citations
    WHERE deleted_at IS NOT NULL
      AND DATEDIFF(NOW(), deleted_at) >= p_days_old
    LIMIT p_limit;

    -- Get count
    SELECT COUNT(*) INTO deleted_count FROM temp_citations_to_delete;

    -- Delete related records
    DELETE FROM violations
    WHERE citation_id IN (SELECT citation_id FROM temp_citations_to_delete);

    DELETE FROM citation_vehicles
    WHERE citation_id IN (SELECT citation_id FROM temp_citations_to_delete);

    -- Note: DO NOT delete payments - keep for financial audit trail
    -- Just orphan them (they will show in orphaned payments report)

    -- Delete citations
    DELETE FROM citations
    WHERE citation_id IN (SELECT citation_id FROM temp_citations_to_delete);

    -- Clean up
    DROP TEMPORARY TABLE IF EXISTS temp_citations_to_delete;

    -- Return count
    SELECT deleted_count as permanently_deleted_count;
END//

DELIMITER ;

-- ============================================================================
-- PART 5: Verification
-- ============================================================================

-- Check if columns were added
SHOW COLUMNS FROM citations LIKE 'deleted%';

-- Check if views were created
SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_traffic_system LIKE 'vw_%citations';

-- Check if procedures were created
SHOW PROCEDURE STATUS WHERE Db = 'traffic_system' AND Name LIKE 'sp_%citation%';

-- ============================================================================
-- PART 6: Migration Info
-- ============================================================================

SELECT
    'Soft deletes migration completed successfully' as status,
    'Citations can now be soft-deleted and restored' as info,
    'Use sp_soft_delete_citation() to soft delete' as usage_1,
    'Use sp_restore_citation() to restore' as usage_2,
    'Use vw_active_citations for normal queries' as usage_3,
    'Use vw_deleted_citations to view trash bin' as usage_4;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
