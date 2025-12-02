-- Migration: Add 'cashier' role to users table
-- Date: 2025-11-25
-- Purpose: Implement RBAC with separate cashier role for payment processing

USE traffic_system;

-- Add 'cashier' to role enum
ALTER TABLE users
MODIFY COLUMN role ENUM('user', 'admin', 'enforcer', 'cashier')
NOT NULL DEFAULT 'user'
COMMENT 'User role: user=read-only, enforcer=field officer, cashier=payment processor, admin=full access';

-- Verify the change
SELECT COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'traffic_system'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'role';

-- Success message
SELECT 'Migration completed: cashier role added successfully' AS status;
