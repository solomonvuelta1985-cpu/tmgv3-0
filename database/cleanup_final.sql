-- Final cleanup: merge sub-barangays and remove garbage entries

-- Masisit → Asassi
UPDATE citations SET barangay = 'Asassi' WHERE barangay = 'Masisit';
UPDATE drivers SET barangay = 'Asassi' WHERE barangay = 'Masisit';

-- Tabuan → Mocag
UPDATE citations SET barangay = 'Mocag' WHERE barangay = 'Tabuan';
UPDATE drivers SET barangay = 'Mocag' WHERE barangay = 'Tabuan';

-- Dapir → Santa Margarita
UPDATE citations SET barangay = 'Santa Margarita' WHERE barangay = 'Dapir';
UPDATE drivers SET barangay = 'Santa Margarita' WHERE barangay = 'Dapir';

-- Garbage entries → Other
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'B', 'D', 'E', '_____', 'NONE', 'N/A',
    'B03-22-302259', 'B04-19-006797', 'B04-23-003473',
    'B04-99-043870', 'B14-19-00506', 'B14-21-000218',
    'B14-23-001608', 'B14-24-803758'
);
UPDATE drivers SET barangay = 'Other' WHERE barangay IN (
    'B', 'D', 'E', '_____', 'NONE', 'N/A',
    'B03-22-302259', 'B04-19-006797', 'B04-23-003473',
    'B04-99-043870', 'B14-19-00506', 'B14-21-000218',
    'B14-23-001608', 'B14-24-803758'
);
