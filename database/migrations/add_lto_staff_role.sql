-- ============================================================================
-- Migration: Add LTO Staff Role to Users Table
-- ============================================================================
-- Date: 2025-12-11
-- Purpose: Enable LTO Gattaran Branch staff to access citation search
-- Description: Adds 'lto_staff' role for read-only access to driver citations
-- ============================================================================

USE traffic_system;

-- Add 'lto_staff' role to users table ENUM
ALTER TABLE users
MODIFY COLUMN role ENUM('user', 'admin', 'enforcer', 'cashier', 'lto_staff')
NOT NULL DEFAULT 'user'
COMMENT 'User roles: user=basic, admin=full access, enforcer=field officer, cashier=payment processor, lto_staff=LTO read-only';

-- Verify the change
SELECT
    COLUMN_TYPE as 'Role Column Type',
    COLUMN_COMMENT as 'Comment'
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'traffic_system'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'role';

-- Success message
SELECT 'Migration completed successfully: lto_staff role added to users table' AS Status;

-- ============================================================================
-- ROLLBACK (if needed)
-- ============================================================================
-- To remove the lto_staff role, run:
-- ALTER TABLE users
-- MODIFY COLUMN role ENUM('user', 'admin', 'enforcer', 'cashier')
-- NOT NULL DEFAULT 'user';
-- ============================================================================
