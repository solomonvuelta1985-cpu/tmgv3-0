-- Add vehicle_type column to citations table
-- This column stores the type of vehicle (Motorcycle, Tricycle, Car, etc.)
-- Date: 2025-12-23

ALTER TABLE citations
ADD COLUMN vehicle_type VARCHAR(50) NULL AFTER plate_mv_engine_chassis_no;

-- Add comment to column
ALTER TABLE citations
MODIFY COLUMN vehicle_type VARCHAR(50) NULL COMMENT 'Type of vehicle (Motorcycle, Tricycle, Car, etc.)';

-- Update existing records to extract vehicle type from vehicle_description if possible
-- This is optional and can be commented out if not needed
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
