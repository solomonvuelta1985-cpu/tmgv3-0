-- Rollback: Remove 'cashier' role from users table
-- Date: 2025-11-25
-- Purpose: Rollback RBAC implementation if needed
-- WARNING: This will convert all cashier users to 'user' role

USE traffic_system;

-- First, check if any users have cashier role
SELECT
    COUNT(*) as cashier_count,
    GROUP_CONCAT(username SEPARATOR ', ') as cashier_usernames
FROM users
WHERE role = 'cashier';

-- Convert any cashier users to 'user' role (for safety)
UPDATE users
SET role = 'user'
WHERE role = 'cashier';

-- Remove 'cashier' from role enum
ALTER TABLE users
MODIFY COLUMN role ENUM('user', 'admin', 'enforcer')
NOT NULL DEFAULT 'user';

-- Verify the change
SELECT COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'traffic_system'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'role';

-- Success message
SELECT 'Rollback completed: cashier role removed' AS status;
