-- Migration: Create violation_categories table and update violation_types
-- Date: 2025-12-04
-- Description: Creates a proper category management system

-- Step 1: Create violation_categories table
CREATE TABLE IF NOT EXISTS violation_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    category_icon VARCHAR(50) NOT NULL DEFAULT 'list',
    category_color VARCHAR(7) NOT NULL DEFAULT '#6b7280',
    description TEXT NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB;

-- Step 2: Insert default categories
INSERT INTO violation_categories (category_name, category_icon, category_color, description, display_order) VALUES
('Helmet', 'shield', '#3b82f6', 'Helmet-related violations', 1),
('License', 'credit-card', '#10b981', 'License and registration violations', 2),
('Vehicle', 'wrench', '#f59e0b', 'Vehicle defects and modifications', 3),
('Driving', 'alert-circle', '#ef4444', 'Reckless driving and DUI violations', 4),
('Traffic', 'traffic-cone', '#8b5cf6', 'Traffic signs and road rules', 5),
('Misc', 'list', '#6366f1', 'Miscellaneous violations', 6),
('Other', 'more-horizontal', '#6b7280', 'Uncategorized violations', 7);

-- Step 3: Add category_id column to violation_types (if not exists)
-- First, add the column as nullable
ALTER TABLE violation_types
ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER description;

-- Step 4: Create foreign key relationship
ALTER TABLE violation_types
ADD CONSTRAINT fk_violation_category
FOREIGN KEY (category_id) REFERENCES violation_categories(category_id) ON DELETE SET NULL;

-- Step 5: Add index for better performance
ALTER TABLE violation_types
ADD INDEX IF NOT EXISTS idx_category_id (category_id);

-- Step 6: Migrate existing category data (if category column exists)
-- Update category_id based on existing category column values
UPDATE violation_types vt
JOIN violation_categories vc ON vt.category = vc.category_name
SET vt.category_id = vc.category_id
WHERE vt.category IS NOT NULL;

-- Set Other category for violations without a category
UPDATE violation_types
SET category_id = (SELECT category_id FROM violation_categories WHERE category_name = 'Other')
WHERE category_id IS NULL;

-- Step 7: Remove old category column (optional - keep for now for backward compatibility)
-- ALTER TABLE violation_types DROP COLUMN category;
