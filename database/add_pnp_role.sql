-- Add PNP role to users table ENUM
-- PNP accounts are read-only and can only view citations apprehended by PNP officers
ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','enforcer','cashier','lto_staff','pnp') NOT NULL DEFAULT 'user';
