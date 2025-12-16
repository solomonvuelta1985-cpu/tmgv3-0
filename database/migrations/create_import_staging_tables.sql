-- ============================================
-- IMPORT STAGING TABLES
-- For Excel Citation Import System
-- ============================================

-- Drop existing staging tables if they exist
DROP TABLE IF EXISTS import_violations_staging;
DROP TABLE IF EXISTS import_citations_staging;
DROP TABLE IF EXISTS import_drivers_staging;
DROP TABLE IF EXISTS import_logs;
DROP TABLE IF EXISTS import_batches;

-- ============================================
-- IMPORT BATCHES - Track import sessions
-- ============================================
CREATE TABLE import_batches (
    batch_id VARCHAR(50) PRIMARY KEY,
    excel_file VARCHAR(255) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    status ENUM('running', 'completed', 'failed', 'rolled_back') DEFAULT 'running',

    -- Statistics
    total_rows INT DEFAULT 0,
    processed_rows INT DEFAULT 0,
    skipped_rows INT DEFAULT 0,
    error_rows INT DEFAULT 0,

    -- Results
    citations_created INT DEFAULT 0,
    drivers_created INT DEFAULT 0,
    drivers_matched INT DEFAULT 0,
    violations_created INT DEFAULT 0,
    duplicates_removed INT DEFAULT 0,
    tickets_generated INT DEFAULT 0,

    -- Summary data
    summary JSON NULL,

    -- User tracking
    imported_by INT NULL,

    INDEX idx_status (status),
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- IMPORT LOGS - Detailed logging
-- ============================================
CREATE TABLE import_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) NOT NULL,
    log_level ENUM('debug', 'info', 'warning', 'error', 'success') DEFAULT 'info',
    log_type VARCHAR(50) NULL,
    message TEXT NOT NULL,

    -- Context
    excel_row INT NULL,
    ticket_number VARCHAR(50) NULL,
    driver_name VARCHAR(200) NULL,

    -- Details
    details JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_batch (batch_id),
    INDEX idx_level (log_level),
    INDEX idx_type (log_type),
    INDEX idx_excel_row (excel_row),

    FOREIGN KEY (batch_id) REFERENCES import_batches(batch_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- IMPORT STAGING - Main staging table
-- ============================================
CREATE TABLE import_staging (
    staging_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) NOT NULL,

    -- Excel tracking
    excel_row INT NOT NULL,

    -- Original ticket data
    original_ticket VARCHAR(50) NULL,
    final_ticket VARCHAR(50) NULL,
    ticket_generated BOOLEAN DEFAULT 0,
    generation_reason VARCHAR(200) NULL,

    -- Driver information
    driver_key VARCHAR(64) NOT NULL COMMENT 'Hash for driver matching',
    last_name VARCHAR(100) NULL,
    first_name VARCHAR(100) NULL,
    middle_initial VARCHAR(10) NULL,
    suffix VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    age INT NULL,
    zone VARCHAR(50) NULL,
    barangay VARCHAR(100) NULL,
    municipality VARCHAR(100) DEFAULT 'Baggao',
    province VARCHAR(100) DEFAULT 'Cagayan',
    license_number VARCHAR(50) NULL,
    license_type VARCHAR(50) NULL,

    -- Vehicle information
    plate_mv_engine_chassis_no VARCHAR(100) NULL,
    vehicle_type VARCHAR(100) NULL,
    vehicle_description TEXT NULL,

    -- Apprehension details
    apprehension_date DATE NULL,
    apprehension_time TIME NULL,
    apprehension_datetime DATETIME NULL,
    place_of_apprehension VARCHAR(255) NULL,
    apprehending_officer VARCHAR(100) NULL,

    -- Violations (raw from Excel)
    violations_raw TEXT NULL,

    -- Remarks
    remarks TEXT NULL,

    -- Grouping keys
    grouping_key VARCHAR(64) NOT NULL COMMENT 'Hash for citation grouping',
    ticket_date_key VARCHAR(120) NOT NULL COMMENT 'Ticket + Date combination',
    citation_group_id INT NULL COMMENT 'Group ID for multi-violation citations',

    -- Processing flags
    is_exact_duplicate BOOLEAN DEFAULT 0,
    is_grouped BOOLEAN DEFAULT 0,
    is_conflict BOOLEAN DEFAULT 0,
    process_status ENUM('pending', 'processed', 'skipped', 'error') DEFAULT 'pending',
    error_message TEXT NULL,

    -- Result tracking (after import)
    driver_id INT NULL COMMENT 'Created/matched driver ID',
    citation_id INT NULL COMMENT 'Created citation ID',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for performance
    INDEX idx_batch (batch_id),
    INDEX idx_grouping (grouping_key),
    INDEX idx_driver (driver_key),
    INDEX idx_ticket (final_ticket),
    INDEX idx_ticket_date (ticket_date_key),
    INDEX idx_status (process_status),
    INDEX idx_group (citation_group_id),
    INDEX idx_excel_row (excel_row),

    FOREIGN KEY (batch_id) REFERENCES import_batches(batch_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VIOLATION MAPPING CACHE
-- ============================================
CREATE TABLE import_violation_mappings (
    mapping_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) NOT NULL,

    excel_text VARCHAR(500) NOT NULL,
    violation_type_id INT NULL,
    violation_type_name VARCHAR(255) NULL,

    match_type ENUM('exact', 'case_insensitive', 'fuzzy', 'partial', 'new') NOT NULL,
    match_confidence DECIMAL(5,2) NULL COMMENT 'Confidence score 0-100',

    times_used INT DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_batch (batch_id),
    INDEX idx_excel_text (excel_text(100)),

    FOREIGN KEY (batch_id) REFERENCES import_batches(batch_id) ON DELETE CASCADE,
    FOREIGN KEY (violation_type_id) REFERENCES violation_types(violation_type_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TICKET NUMBER CONFLICTS
-- ============================================
CREATE TABLE import_ticket_conflicts (
    conflict_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) NOT NULL,

    original_ticket VARCHAR(50) NOT NULL,
    conflicting_rows TEXT NOT NULL COMMENT 'Comma-separated Excel row numbers',
    conflict_type ENUM('different_date', 'different_person', 'both') NOT NULL,

    resolution VARCHAR(200) NULL,
    tickets_generated TEXT NULL COMMENT 'New ticket numbers generated',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_batch (batch_id),
    INDEX idx_original_ticket (original_ticket),

    FOREIGN KEY (batch_id) REFERENCES import_batches(batch_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DUPLICATE RECORDS LOG
-- ============================================
CREATE TABLE import_duplicates (
    duplicate_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) NOT NULL,

    grouping_key VARCHAR(64) NOT NULL,
    excel_rows TEXT NOT NULL COMMENT 'Comma-separated row numbers',
    kept_row INT NOT NULL,
    removed_rows TEXT NOT NULL,

    duplicate_type ENUM('exact', 'near_exact') DEFAULT 'exact',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_batch (batch_id),

    FOREIGN KEY (batch_id) REFERENCES import_batches(batch_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CITATION GROUPS (Multi-Violation)
-- ============================================
CREATE TABLE import_citation_groups (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) NOT NULL,

    grouping_key VARCHAR(64) NOT NULL,
    final_ticket VARCHAR(50) NOT NULL,

    excel_rows TEXT NOT NULL COMMENT 'Comma-separated row numbers',
    row_count INT NOT NULL,

    violations_combined TEXT NOT NULL COMMENT 'All violations in this group',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_batch (batch_id),
    INDEX idx_grouping (grouping_key),

    FOREIGN KEY (batch_id) REFERENCES import_batches(batch_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Add import tracking to main tables
-- ============================================

-- Add import batch tracking to citations
ALTER TABLE citations
ADD COLUMN import_batch_id VARCHAR(50) NULL AFTER created_by,
ADD INDEX idx_import_batch (import_batch_id);

-- Add import batch tracking to drivers
ALTER TABLE drivers
ADD COLUMN import_batch_id VARCHAR(50) NULL AFTER created_at,
ADD INDEX idx_import_batch (import_batch_id);

-- Add import batch tracking to violations
ALTER TABLE violations
ADD COLUMN import_batch_id VARCHAR(50) NULL AFTER created_at,
ADD INDEX idx_import_batch (import_batch_id);

-- ============================================
-- Stored Procedures
-- ============================================

DELIMITER //

-- Generate next auto ticket number
CREATE PROCEDURE sp_generate_auto_ticket(
    IN p_batch_id VARCHAR(50),
    OUT p_ticket_number VARCHAR(50)
)
BEGIN
    DECLARE v_sequence INT;

    -- Get next sequence number for this batch
    SELECT COALESCE(MAX(CAST(SUBSTRING(final_ticket, 5) AS UNSIGNED)), 19000) + 1
    INTO v_sequence
    FROM import_staging
    WHERE batch_id = p_batch_id
    AND final_ticket LIKE 'AUT-%';

    SET p_ticket_number = CONCAT('AUT-', LPAD(v_sequence, 6, '0'));
END //

-- Clean up old import batches
CREATE PROCEDURE sp_cleanup_old_imports(
    IN p_days_old INT
)
BEGIN
    DELETE FROM import_batches
    WHERE status IN ('completed', 'failed')
    AND started_at < DATE_SUB(NOW(), INTERVAL p_days_old DAY);
END //

DELIMITER ;

-- ============================================
-- Initial data
-- ============================================

-- Grant permissions (if needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON import_batches TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON import_logs TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON import_staging TO 'your_user'@'localhost';

-- ============================================
-- Verification queries
-- ============================================

-- Verify tables created
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME LIKE 'import_%'
ORDER BY TABLE_NAME;

-- ============================================
-- SCHEMA COMPLETE
-- ============================================
