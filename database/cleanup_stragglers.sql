UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'PE%AWESTE%GATTARAN%';
UPDATE citations SET barangay = 'Bitag Pequeño' WHERE barangay LIKE 'BITAG%PEQUE%' AND barangay != 'Bitag Pequeño';
UPDATE drivers SET barangay = 'Other' WHERE barangay LIKE 'PE%AWESTE%GATTARAN%';
UPDATE drivers SET barangay = 'Bitag Pequeño' WHERE barangay LIKE 'BITAG%PEQUE%' AND barangay != 'Bitag Pequeño';
