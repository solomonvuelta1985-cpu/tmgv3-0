-- ============================================================
-- VIOLATION TYPES DATA CLEANUP MIGRATION
-- Merges duplicates, fixes misspellings, assigns categories,
-- and deactivates garbage entries.
-- ============================================================
-- Date: 2026-03-26
-- ============================================================

START TRANSACTION;

-- ============================================================
-- STEP 1: MERGE DUPLICATE VIOLATION TYPES
-- Update violations table to point to canonical IDs, then deactivate duplicates
-- ============================================================

-- 1. NO HELMET (Backride) id:37 → NO HELMET (BACKRIDER) id:3
UPDATE violations SET violation_type_id = 3 WHERE violation_type_id = 37;
DELETE FROM violation_types WHERE violation_type_id = 37;

-- 2. NO DRIVERS LICENSE id:32 → NO DRIVER'S LICENSE id:29
UPDATE violations SET violation_type_id = 29 WHERE violation_type_id = 32;
DELETE FROM violation_types WHERE violation_type_id = 32;

-- 3. DRIVING IN SHORT/SANDO id:30 → DRIVING IN SHORT / SANDO id:21
UPDATE violations SET violation_type_id = 21 WHERE violation_type_id = 30;
DELETE FROM violation_types WHERE violation_type_id = 30;

-- 4. RECKLESS / ARROGANT DRIVER id:33 → RECKLESS / ARROGANT DRIVING id:7
UPDATE violations SET violation_type_id = 7 WHERE violation_type_id = 33;
DELETE FROM violation_types WHERE violation_type_id = 33;

-- 5. NOT WAERING SEATBELT id:46 → Not wearing seatbelt id:41
UPDATE violations SET violation_type_id = 41 WHERE violation_type_id = 46;
DELETE FROM violation_types WHERE violation_type_id = 46;

-- ============================================================
-- STEP 2: FIX MISSPELLINGS
-- ============================================================

-- Fix "DISREGARDING TRAFFIC ENFOCER" → "DISREGARDING TRAFFIC ENFORCER"
UPDATE violation_types SET violation_type = 'DISREGARDING TRAFFIC ENFORCER' WHERE violation_type_id = 38;

-- Fix "Not wearing seatbelt" → "NOT WEARING SEATBELT"
UPDATE violation_types SET violation_type = 'NOT WEARING SEATBELT' WHERE violation_type_id = 41;

-- Fix "No Plate" → "NO PLATE NUMBER"
UPDATE violation_types SET violation_type = 'NO PLATE NUMBER' WHERE violation_type_id = 39;

-- Fix "UNCARRIED OR/CR" → "UNCARRIED OR/CR" (already correct, just assign category)

-- ============================================================
-- STEP 3: DEACTIVATE GARBAGE ENTRIES
-- ============================================================

-- 109.1 (id:35) - a number, not a violation
UPDATE violation_types SET is_active = 0 WHERE violation_type_id = 35;

-- N/A (id:31) - not a valid violation
UPDATE violation_types SET is_active = 0 WHERE violation_type_id = 31;

-- SAN JOSE (id:44) - a barangay, not a violation
UPDATE violation_types SET is_active = 0 WHERE violation_type_id = 44;

-- WARNING (id:45) - not a violation type
UPDATE violation_types SET is_active = 0 WHERE violation_type_id = 45;

-- ============================================================
-- STEP 4: ASSIGN CATEGORIES TO UNCATEGORIZED ENTRIES
-- ============================================================

-- CONFISCATED MUFFLER → Vehicle (3)
UPDATE violation_types SET category_id = 3 WHERE violation_type_id = 34;

-- DISREGARDING TRAFFIC ENFORCER → Traffic (5)
UPDATE violation_types SET category_id = 5 WHERE violation_type_id = 38;

-- MUFFLER AND NO SIDE MIRROR → Vehicle (3)
UPDATE violation_types SET category_id = 3 WHERE violation_type_id = 47;

-- NO PLATE NUMBER → License (2)
UPDATE violation_types SET category_id = 2 WHERE violation_type_id = 39;

-- NO SIDE MIRROR → Vehicle (3)
UPDATE violation_types SET category_id = 3 WHERE violation_type_id = 40;

-- NOT WEARING SEATBELT → Driving (4)
UPDATE violation_types SET category_id = 4 WHERE violation_type_id = 41;

-- OUT OF ROUTE / LINE → Traffic (5)
UPDATE violation_types SET category_id = 5 WHERE violation_type_id = 42;

-- REFUSE TO GET TICKET → Misc (6)
UPDATE violation_types SET category_id = 6 WHERE violation_type_id = 43;

-- UNCARRIED OR/CR → License (2)
UPDATE violation_types SET category_id = 2 WHERE violation_type_id = 36;

-- DRAG RACING → already has category 4 (Driving)

-- Assign garbage entries to Other (7) before deactivating
UPDATE violation_types SET category_id = 7 WHERE violation_type_id IN (35, 31, 44, 45);

COMMIT;
