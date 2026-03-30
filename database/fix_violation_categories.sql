-- Sync the category text column with the actual category_name from category_id
UPDATE violation_types vt
JOIN violation_categories vc ON vt.category_id = vc.category_id
SET vt.category = vc.category_name
WHERE vt.category != vc.category_name OR vt.category IS NULL;
