-- Migration: Add category column to violation_types table
-- Date: 2025-12-04
-- Description: Adds a category field to allow organizing violations into categories

-- Add category column to violation_types table
ALTER TABLE violation_types
ADD COLUMN category VARCHAR(50) NULL DEFAULT 'Other' AFTER description;

-- Create index on category for better performance
ALTER TABLE violation_types
ADD INDEX idx_category (category);

-- Update existing violations with appropriate categories based on keywords
UPDATE violation_types SET category = 'Helmet'
WHERE violation_type LIKE '%HELMET%';

UPDATE violation_types SET category = 'License'
WHERE violation_type LIKE '%LICENSE%'
   OR violation_type LIKE '%REGISTRATION%'
   OR violation_type LIKE '%OPLAN VISA%'
   OR violation_type LIKE '%E-OV MATCH%';

UPDATE violation_types SET category = 'Vehicle'
WHERE violation_type LIKE '%DEFECTIVE%'
   OR violation_type LIKE '%MUFFLER%'
   OR violation_type LIKE '%MODIFICATION%'
   OR violation_type LIKE '%PARTS%';

UPDATE violation_types SET category = 'Driving'
WHERE violation_type LIKE '%RECKLESS%'
   OR violation_type LIKE '%DRAG RACING%'
   OR violation_type LIKE '%DRUNK%'
   OR violation_type LIKE '%DRIVING IN SHORT%'
   OR violation_type LIKE '%ARROGANT%';

UPDATE violation_types SET category = 'Traffic'
WHERE violation_type LIKE '%TRAFFIC SIGN%'
   OR violation_type LIKE '%PARKING%'
   OR violation_type LIKE '%OBSTRUCTION%'
   OR violation_type LIKE '%PEDESTRIAN%'
   OR violation_type LIKE '%LOADING%'
   OR violation_type LIKE '%PASSENGER ON TOP%';

UPDATE violation_types SET category = 'Misc'
WHERE violation_type LIKE '%COLORUM%'
   OR violation_type LIKE '%TRASHBIN%'
   OR violation_type LIKE '%OVERLOADED%'
   OR violation_type LIKE '%CHARGING%'
   OR violation_type LIKE '%REFUSAL%';

-- Any violations not categorized above remain as 'Other'
