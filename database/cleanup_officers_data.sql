-- ============================================================
-- OFFICER DATA CLEANUP MIGRATION
-- Normalizes misspelled and inconsistent officer names
-- in the citations table, and adds missing officers to
-- the apprehending_officers table.
-- ============================================================
-- Date: 2026-03-26
-- Total unique officer values before: 76
-- Expected after: ~35 (official officers + PNP stations + Other)
-- ============================================================

START TRANSACTION;

-- ============================================================
-- STEP 1: NORMALIZE OFFICER NAME MISSPELLINGS (citations)
-- ============================================================

-- 1. CJ OMNES (466 total)
UPDATE citations SET apprehension_officer = 'CJ OMNES' WHERE apprehension_officer IN (
    'C.J OMNES', 'C. OMNES', 'CJ. OMNES', 'C J OMNES'
);

-- 2. N. BUCO (401 total)
UPDATE citations SET apprehension_officer = 'N. BUCO' WHERE apprehension_officer IN (
    'N.BUCO'
);

-- 3. N. BURGOS (206 total)
UPDATE citations SET apprehension_officer = 'N. BURGOS' WHERE apprehension_officer IN (
    'N.BURGOS', 'N. BRUGOS', 'N. BUGOS'
);

-- 4. N. FLORES (150 total)
UPDATE citations SET apprehension_officer = 'N. FLORES' WHERE apprehension_officer IN (
    'N. FLores', 'N.Flores', 'N Flores', 'N.FLORES'
);

-- 5. JA. CRUZ (442 total)
UPDATE citations SET apprehension_officer = 'JA. CRUZ' WHERE apprehension_officer IN (
    'J.A CRUZ', 'J. CRUZ', 'J. A CRUZ'
);

-- 6. J.A TAGUIAM (41 total)
UPDATE citations SET apprehension_officer = 'J.A TAGUIAM' WHERE apprehension_officer IN (
    'J. A TAGUIAM', 'J.A TAGUAIM'
);

-- 7. W. MACAPULAY (197 total)
UPDATE citations SET apprehension_officer = 'W. MACAPULAY' WHERE apprehension_officer IN (
    'W Macapulay', 'W.Macapulay'
);

-- 8. F. SILVA (153 total)
UPDATE citations SET apprehension_officer = 'F. SILVA' WHERE apprehension_officer IN (
    'F.SILVA'
);

-- 9. H. VILLEGAS (41 total)
UPDATE citations SET apprehension_officer = 'H. VILLEGAS' WHERE apprehension_officer IN (
    'H.VILLEGAS'
);

-- 10. E. MARTINEZ (84 total)
UPDATE citations SET apprehension_officer = 'E. MARTINEZ' WHERE apprehension_officer IN (
    'EDWARD MARTINEZ', 'Edward R Martinez', 'Edward'
);

-- 11. R. QUILANG (33 total)
UPDATE citations SET apprehension_officer = 'R. QUILANG' WHERE apprehension_officer IN (
    'R.QUILANG'
);

-- 12. J. PAMITTAN (164 total)
UPDATE citations SET apprehension_officer = 'J. PAMITTAN' WHERE apprehension_officer IN (
    'PAMITTAN J.L', 'PSS. PAMITTAN'
);

-- 13. R. PEÑALBER - handle encoding variants
UPDATE citations SET apprehension_officer = 'R. PEÑALBER' WHERE apprehension_officer LIKE 'R. PE%ALBER%';
UPDATE citations SET apprehension_officer = 'R. PEÑALBER' WHERE apprehension_officer LIKE 'R. PE_ALBER%';

-- 14. S. SILVA → probably S. ESLABRA? Leave as-is since only 1 record, set to Other
-- 15. P. SALVADOR → probably K. SALVADOR? Only 1 record, set to Other
-- 16. N. ISUCO → only 1, set to Other
-- 17. James Carlo → only 1, set to Other

-- ============================================================
-- STEP 2: GARBAGE/INVALID ENTRIES → "Other"
-- ============================================================
UPDATE citations SET apprehension_officer = 'Other' WHERE apprehension_officer IN (
    '', '105.6', '107.3', '1ST OFFENCE', 'FD', 'BARSAT EAST',
    'N/A', 'NONE', 'S. SILVA', 'P. SALVADOR', 'N. ISUCO',
    'James Carlo', 'RICHMOND', 'James Carlo'
);
-- Also catch empty strings
UPDATE citations SET apprehension_officer = 'Other' WHERE apprehension_officer = '' OR apprehension_officer IS NULL;

-- ============================================================
-- STEP 3: ADD MISSING OFFICERS TO apprehending_officers TABLE
-- ============================================================

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'A. PATTUNG', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'A. PATTUNG');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'D. COLLADO', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'D. COLLADO');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'G. ROMERO', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'G. ROMERO');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'H. VILLEGAS', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'H. VILLEGAS');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'J. ANCHETA', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'J. ANCHETA');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'J. PAMITTAN', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'J. PAMITTAN');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'J. QUEZADA', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'J. QUEZADA');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'J. SALADINO', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'J. SALADINO');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'J.A TAGUIAM', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'J.A TAGUIAM');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'K. SALVADOR', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'K. SALVADOR');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'L. CARIAGA', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'L. CARIAGA');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'L. MACAHILOS', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'L. MACAHILOS');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'PJ REMUDARO', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'PJ REMUDARO');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'R. DE LEON', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'R. DE LEON');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'R. PEÑALBER', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'R. PEÑALBER');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'R. QUILANG', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'R. QUILANG');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'S. ESLABRA', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'S. ESLABRA');

INSERT INTO apprehending_officers (officer_name, badge_number, position, is_active)
SELECT 'A. PATTUNG', NULL, 'TRAFFIC ENFORCER', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM apprehending_officers WHERE officer_name = 'A. PATTUNG');

COMMIT;
