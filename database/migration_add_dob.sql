-- Migration: Add missing columns to citations table
-- Run this if you get "Unknown column" errors

-- Add date_of_birth column
ALTER TABLE citations
ADD COLUMN date_of_birth DATE NULL AFTER suffix;

-- Add age column
ALTER TABLE citations
ADD COLUMN age INT NULL AFTER date_of_birth;

-- Verify the columns were added
DESCRIBE citations;
