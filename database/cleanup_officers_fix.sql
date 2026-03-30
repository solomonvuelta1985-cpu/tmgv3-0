-- Fix duplicate R. PEÑALBER (encoding issue on officer_id 30)
DELETE FROM apprehending_officers WHERE officer_id = 30;

-- Fix encoding on citations that might still have the old encoding
UPDATE citations SET apprehension_officer = 'R. PEÑALBER' WHERE apprehension_officer LIKE 'R. PE%ALBER%' AND apprehension_officer != 'R. PEÑALBER';
