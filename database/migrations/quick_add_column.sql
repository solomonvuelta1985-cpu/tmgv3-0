-- Quick Migration: Just add the category column
-- Run this if you want to add the column and categorize violations manually

-- Add category column to violation_types table
ALTER TABLE violation_types
ADD COLUMN category VARCHAR(50) NULL DEFAULT 'Other' AFTER description;

-- Create index on category for better performance
ALTER TABLE violation_types
ADD INDEX idx_category (category);

-- Done! All violations will default to 'Other' category
-- You can now manually update categories through the admin panel
