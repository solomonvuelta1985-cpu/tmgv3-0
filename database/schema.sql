-- Traffic Citation System Database Schema
-- Database: traffic_system
-- Run this SQL in phpMyAdmin or MySQL CLI to create all necessary tables

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS traffic_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE traffic_system;

-- ========================================
-- USERS TABLE (Authentication System)
-- ========================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('user', 'admin', 'enforcer') NOT NULL DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,

    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ========================================
-- DRIVERS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS drivers (
    driver_id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_initial VARCHAR(10) NULL,
    suffix VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    age INT NULL,
    zone VARCHAR(50) NULL,
    barangay VARCHAR(100) NOT NULL,
    municipality VARCHAR(100) NOT NULL DEFAULT 'Baggao',
    province VARCHAR(100) NOT NULL DEFAULT 'Cagayan',
    license_number VARCHAR(50) NULL UNIQUE,
    license_type VARCHAR(50) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_license (license_number),
    INDEX idx_name (last_name, first_name),
    INDEX idx_barangay (barangay),
    INDEX idx_age (age)
) ENGINE=InnoDB;

-- ========================================
-- CITATIONS TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS citations (
    citation_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(50) NOT NULL UNIQUE,
    driver_id INT NULL,

    -- Driver info snapshot (preserved at time of citation)
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_initial VARCHAR(10) NULL,
    suffix VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    age INT NULL,
    zone VARCHAR(50) NULL,
    barangay VARCHAR(100) NOT NULL,
    municipality VARCHAR(100) NOT NULL DEFAULT 'Baggao',
    province VARCHAR(100) NOT NULL DEFAULT 'Cagayan',
    license_number VARCHAR(50) NULL,
    license_type VARCHAR(50) NULL,

    -- Vehicle info
    plate_mv_engine_chassis_no VARCHAR(100) NOT NULL,
    vehicle_description TEXT NULL,

    -- Apprehension details
    apprehension_datetime DATETIME NOT NULL,
    place_of_apprehension VARCHAR(255) NOT NULL,
    remarks TEXT NULL,

    -- Status tracking
    status ENUM('pending', 'paid', 'contested', 'dismissed', 'void') NOT NULL DEFAULT 'pending',
    payment_date DATETIME NULL,
    total_fine DECIMAL(10,2) NULL DEFAULT 0.00,

    -- Audit fields
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,

    FOREIGN KEY (driver_id) REFERENCES drivers(driver_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,

    INDEX idx_ticket (ticket_number),
    INDEX idx_driver (driver_id),
    INDEX idx_datetime (apprehension_datetime),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ========================================
-- VIOLATION TYPES TABLE
-- ========================================
CREATE TABLE IF NOT EXISTS violation_types (
    violation_type_id INT AUTO_INCREMENT PRIMARY KEY,
    violation_type VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    fine_amount_1 DECIMAL(10,2) NOT NULL DEFAULT 500.00,  -- 1st offense
    fine_amount_2 DECIMAL(10,2) NOT NULL DEFAULT 1000.00, -- 2nd offense
    fine_amount_3 DECIMAL(10,2) NOT NULL DEFAULT 1500.00, -- 3rd+ offense
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_violation_type (violation_type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- ========================================
-- VIOLATIONS TABLE (Citation-Violation Junction)
-- ========================================
CREATE TABLE IF NOT EXISTS violations (
    violation_id INT AUTO_INCREMENT PRIMARY KEY,
    citation_id INT NOT NULL,
    violation_type_id INT NOT NULL,
    offense_count INT NOT NULL DEFAULT 1,
    fine_amount DECIMAL(10,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (citation_id) REFERENCES citations(citation_id) ON DELETE CASCADE,
    FOREIGN KEY (violation_type_id) REFERENCES violation_types(violation_type_id) ON DELETE RESTRICT,

    INDEX idx_citation (citation_id),
    INDEX idx_violation_type (violation_type_id),
    UNIQUE KEY unique_citation_violation (citation_id, violation_type_id)
) ENGINE=InnoDB;

-- ========================================
-- CITATION VEHICLES TABLE (Multiple vehicle types per citation)
-- ========================================
CREATE TABLE IF NOT EXISTS citation_vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    citation_id INT NOT NULL,
    vehicle_type VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (citation_id) REFERENCES citations(citation_id) ON DELETE CASCADE,

    INDEX idx_citation (citation_id)
) ENGINE=InnoDB;

-- ========================================
-- AUDIT LOG TABLE (Optional - for tracking all changes)
-- ========================================
CREATE TABLE IF NOT EXISTS audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,

    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_table (table_name),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ========================================
-- INSERT DEFAULT DATA
-- ========================================

-- Create default admin user (password: admin123 - CHANGE THIS!)
INSERT INTO users (username, password_hash, full_name, email, role, status) VALUES
('admin', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'System Administrator', 'admin@traffic.gov', 'admin', 'active');

-- Insert traffic violation types (Baggao Municipality)
INSERT INTO violation_types (violation_type, fine_amount_1, fine_amount_2, fine_amount_3) VALUES
('NO HELMET (DRIVER)', 150.00, 150.00, 150.00),
('NO HELMET (BACKRIDER)', 150.00, 150.00, 150.00),
('NO DRIVER\'S LICENSE / MINOR', 500.00, 500.00, 500.00),
('NO / EXPIRED VEHICLE REGISTRATION', 2500.00, 2500.00, 2500.00),
('NO / DEFECTIVE PARTS & ACCESSORIES', 500.00, 500.00, 500.00),
('RECKLESS / ARROGANT DRIVING', 500.00, 750.00, 1000.00),
('DISREGARDING TRAFFIC SIGN', 150.00, 150.00, 150.00),
('ILLEGAL MODIFICATION', 500.00, 500.00, 500.00),
('PASSENGER ON TOP OF THE VEHICLE', 150.00, 150.00, 150.00),
('NOISY MUFFLER (98DB ABOVE)', 2500.00, 2500.00, 2500.00),
('NO MUFFLER ATTACHED', 2500.00, 2500.00, 2500.00),
('ILLEGAL PARKING', 200.00, 500.00, 2500.00),
('ROAD OBSTRUCTION', 200.00, 500.00, 2500.00),
('BLOCKING PEDESTRIAN LANE', 200.00, 500.00, 2500.00),
('LOADING/UNLOADING IN PROHIBITED ZONE', 200.00, 500.00, 2500.00),
('DOUBLE PARKING', 200.00, 500.00, 2500.00),
('DRUNK DRIVING', 500.00, 1000.00, 1500.00),
('COLORUM OPERATION', 2500.00, 3000.00, 3000.00),
('NO TRASHBIN', 1000.00, 2000.00, 2500.00),
('DRIVING IN SHORT / SANDO', 200.00, 500.00, 1000.00),
('OVERLOADED PASSENGER', 500.00, 750.00, 1000.00),
('OVER CHARGING / UNDER CHARGING', 500.00, 750.00, 1000.00),
('REFUSAL TO CONVEY PASSENGER/S', 500.00, 750.00, 1000.00),
('DRAG RACING', 1000.00, 1500.00, 2500.00),
('NO ENHANCED OPLAN VISA STICKER', 300.00, 300.00, 300.00),
('FAILURE TO PRESENT E-OV MATCH CARD', 200.00, 200.00, 200.00);

-- ========================================
-- USEFUL VIEWS
-- ========================================

-- View: Citation summary with driver info
CREATE OR REPLACE VIEW vw_citation_summary AS
SELECT
    c.citation_id,
    c.ticket_number,
    CONCAT(c.last_name, ', ', c.first_name, ' ', COALESCE(c.middle_initial, '')) AS driver_name,
    c.license_number,
    c.plate_mv_engine_chassis_no,
    c.apprehension_datetime,
    c.place_of_apprehension,
    c.status,
    c.total_fine,
    COUNT(v.violation_id) AS violation_count,
    c.created_at
FROM citations c
LEFT JOIN violations v ON c.citation_id = v.citation_id
GROUP BY c.citation_id;

-- View: Driver offense history
CREATE OR REPLACE VIEW vw_driver_offenses AS
SELECT
    d.driver_id,
    CONCAT(d.last_name, ', ', d.first_name) AS driver_name,
    d.license_number,
    COUNT(DISTINCT c.citation_id) AS total_citations,
    COUNT(v.violation_id) AS total_violations,
    SUM(c.total_fine) AS total_fines
FROM drivers d
LEFT JOIN citations c ON d.driver_id = c.driver_id
LEFT JOIN violations v ON c.citation_id = v.citation_id
GROUP BY d.driver_id;

-- ========================================
-- TRIGGERS (Auto-calculate total fine)
-- ========================================

DELIMITER //

-- After inserting a violation, update the fine amount
CREATE TRIGGER after_violation_insert
AFTER INSERT ON violations
FOR EACH ROW
BEGIN
    DECLARE fine DECIMAL(10,2);

    -- Get the appropriate fine based on offense count
    SELECT
        CASE
            WHEN NEW.offense_count = 1 THEN fine_amount_1
            WHEN NEW.offense_count = 2 THEN fine_amount_2
            ELSE fine_amount_3
        END INTO fine
    FROM violation_types
    WHERE violation_type_id = NEW.violation_type_id;

    -- Update the violation record with the fine
    UPDATE violations SET fine_amount = fine WHERE violation_id = NEW.violation_id;

    -- Update total fine on citation
    UPDATE citations
    SET total_fine = (SELECT SUM(fine_amount) FROM violations WHERE citation_id = NEW.citation_id)
    WHERE citation_id = NEW.citation_id;
END //

DELIMITER ;

-- ========================================
-- INDEXES FOR PERFORMANCE
-- ========================================
-- Additional composite indexes for common queries
ALTER TABLE citations ADD INDEX idx_date_status (apprehension_datetime, status);
ALTER TABLE violations ADD INDEX idx_offense_count (offense_count);
