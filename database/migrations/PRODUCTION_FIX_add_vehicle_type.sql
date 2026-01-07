-- PRODUCTION FIX: Add missing vehicle_type column
-- Run this on your production database immediately
-- Date: 2026-01-07

-- Check and add vehicle_type column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'citations';
SET @columnname = 'vehicle_type';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1',
  'ALTER TABLE citations ADD COLUMN vehicle_type VARCHAR(50) NULL COMMENT "Type of vehicle (Motorcycle, Tricycle, Car, etc.)" AFTER plate_mv_engine_chassis_no'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Optional: Update existing records to extract vehicle type from vehicle_description
-- Uncomment if you want to populate existing data
/*
UPDATE citations
SET vehicle_type = CASE
    WHEN UPPER(vehicle_description) LIKE '%MOTORCYCLE%' THEN 'Motorcycle'
    WHEN UPPER(vehicle_description) LIKE '%TRICYCLE%' THEN 'Tricycle'
    WHEN UPPER(vehicle_description) LIKE '%CAR%' THEN 'Car'
    WHEN UPPER(vehicle_description) LIKE '%TRUCK%' THEN 'Truck'
    WHEN UPPER(vehicle_description) LIKE '%VAN%' THEN 'Van'
    WHEN UPPER(vehicle_description) LIKE '%BUS%' THEN 'Bus'
    ELSE NULL
END
WHERE vehicle_type IS NULL;
*/
