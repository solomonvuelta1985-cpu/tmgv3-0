-- ============================================================
-- BARANGAY DATA CLEANUP MIGRATION
-- Normalizes misspelled, inconsistent, and redundant barangay
-- values in the citations and drivers tables.
-- ============================================================
-- Date: 2026-03-26
-- Total unique barangay values before: 488
-- Expected after: ~50-55
-- ============================================================

-- Start transaction for safety
START TRANSACTION;

-- ============================================================
-- STEP 1: NORMALIZE BAGGAO BARANGAY MISSPELLINGS (citations)
-- ============================================================

-- 1. Adaoag
UPDATE citations SET barangay = 'Adaoag' WHERE barangay IN ('ADAOAG', 'ADAOG', 'ADOAG');

-- 2. Agaman (Proper)
UPDATE citations SET barangay = 'Agaman (Proper)' WHERE barangay IN ('AGAMAN PROPER', 'Agaman');

-- 3. Agaman Norte
UPDATE citations SET barangay = 'Agaman Norte' WHERE barangay IN ('AGAMAN NORTE', 'AGAMA NORTE');

-- 4. Agaman Sur
UPDATE citations SET barangay = 'Agaman Sur' WHERE barangay IN ('AGAMAN SUR', 'AGAMAN SUE');

-- 5. Alba (case fix)
UPDATE citations SET barangay = 'Alba' WHERE barangay = 'Alba';

-- 6. Annayatan
UPDATE citations SET barangay = 'Annayatan' WHERE barangay IN ('ANNAYATAN', 'AANNAYATAN', 'ANNNAYATAN', 'ANNYATAN');

-- 7. Asassi
UPDATE citations SET barangay = 'Asassi' WHERE barangay IN ('Asassi', 'ASSASI', 'ASSASSI', 'KAMARUNGGAYAN, ASASSI');

-- 8. Asinga-Via
UPDATE citations SET barangay = 'Asinga-Via' WHERE barangay IN ('ASINGA VIA', 'ASINGA-VIA', 'ASINGA', 'ASINGA VIA5');

-- 9. Awallan
UPDATE citations SET barangay = 'Awallan' WHERE barangay = 'AWALLAN';

-- 10. Bacagan
UPDATE citations SET barangay = 'Bacagan' WHERE barangay = 'BACAGAN';

-- 11. Bagunot
UPDATE citations SET barangay = 'Bagunot' WHERE barangay IN ('BAGUNOT', 'BAGANOT');

-- 12. Barsat East
UPDATE citations SET barangay = 'Barsat East' WHERE barangay IN ('BARSAT EAST', 'BARSAT EAT', 'BAESAT EAST', 'BARSAT');

-- 13. Barsat West
UPDATE citations SET barangay = 'Barsat West' WHERE barangay IN ('Barsat west');

-- 14. Bitag Grande
UPDATE citations SET barangay = 'Bitag Grande' WHERE barangay IN (
    'BITAG GRANDE', 'Bitag', 'B.GRANDE', 'BITAG GANDE', 'BITAG GARNDE',
    'BITAG GGRANDE', 'BITAG GRAND', 'BITAG GRANDSE', 'BITAG GRNDE',
    'BITAG, GRANDE', 'BITAGB GRANDE', 'BITAG SITIO ASAO', 'Bitag(tueg)',
    'ASSAO', 'ASSAO, BITAG GRANDE', 'NONEBITAG GRANDE'
);

-- 15. Bitag Pequeño
UPDATE citations SET barangay = 'Bitag Pequeño' WHERE barangay IN (
    'BITAG PEQUEÑO', 'BITAG PIQUEÑO', 'B.PEQUEÑO',
    'BITAG  PEQUEÑO', 'BITAG PEQENIO', 'BITAG PEQUENIO',
    'BITAG PIQUEN0', 'PITAG PEQUEÑO'
);
-- Also handle encoding variants
UPDATE citations SET barangay = 'Bitag Pequeño' WHERE barangay LIKE 'BITAG P%' AND barangay != 'Bitag Pequeño';
UPDATE citations SET barangay = 'Bitag Pequeño' WHERE barangay LIKE 'B.P%' AND barangay != 'Bitag Pequeño';
UPDATE citations SET barangay = 'Bitag Pequeño' WHERE barangay LIKE 'PITAG P%';

-- 16. Bunugan
UPDATE citations SET barangay = 'Bunugan' WHERE barangay = 'BUNUGAN';

-- 17. C. Verzosa (Valley Cove)
UPDATE citations SET barangay = 'C. Verzosa (Valley Cove)' WHERE barangay IN (
    'C. VERZOSA', 'C. VERSOZA', 'CVERZOSA', 'C Verzosa', 'VERSOZA',
    'C-VERSOZA', 'VERSOOZA', 'VERSOSA', 'VERZOSA'
);

-- 18. Canagatan
UPDATE citations SET barangay = 'Canagatan' WHERE barangay IN ('CANAGATAN', 'Cannagatan');

-- 19. Carupian
UPDATE citations SET barangay = 'Carupian' WHERE barangay IN ('CARUPIAN', 'CARRUPIAN', 'CAROPIAN');

-- 20. Catugay
UPDATE citations SET barangay = 'Catugay' WHERE barangay = 'CATUGAY';

-- 21. Dabbac Grande (user confirmed: DABBAC = Dabbac Grande)
UPDATE citations SET barangay = 'Dabbac Grande' WHERE barangay IN ('Dabbac Grande', 'DABBAC');

-- 22. Dalin
UPDATE citations SET barangay = 'Dalin' WHERE barangay IN ('DALIN', 'DALLIN');

-- 23. Dalla
UPDATE citations SET barangay = 'Dalla' WHERE barangay IN ('DALLA', 'DALLA BAGGAO', 'Dalla2');

-- 24. Hacienda Intal
UPDATE citations SET barangay = 'Hacienda Intal' WHERE barangay IN (
    'HACIENDA INTAL', 'HACIENDA', 'HACIENDA-INTAL',
    'MARUS HACIENDA INTAL', 'MARUS, HACIENDA', 'HACIENDA 9NTAL',
    'HACINDA INTAL', 'HACINEDA INTAL', 'MARUS HACIENDA',
    'STIO MARUS, HACIENDA INTAL', 'BIRAO HACIENDA', 'Marus',
    'MARUS', 'BIRAO'
);

-- 25. Ibulo
UPDATE citations SET barangay = 'Ibulo' WHERE barangay IN ('IBULO', 'IBOLO');

-- 26. Immurung
UPDATE citations SET barangay = 'Immurung' WHERE barangay IN (
    'IMURUNG', 'Immurung', 'IMURONG', 'IMIRUNG', 'IMURUG',
    'IMURUN', 'IMURUNGA', 'DAMURUG'
);

-- 27. J. Pallagao
UPDATE citations SET barangay = 'J. Pallagao' WHERE barangay IN (
    'J. PALLAGAO', 'PALLAGAO', 'J PALLAGAO', 'J.PALLAGAO',
    'JPALLAGAO', 'Pallagai'
);

-- 28. Lasilat
UPDATE citations SET barangay = 'Lasilat' WHERE barangay = 'LASILAT';

-- 29. Mabini
UPDATE citations SET barangay = 'Mabini' WHERE barangay = 'MABINI';

-- 30. Masical
UPDATE citations SET barangay = 'Masical' WHERE barangay IN ('MASICAL', 'Masical Baggao', 'Masikal');

-- 31. Mocag
UPDATE citations SET barangay = 'Mocag' WHERE barangay IN ('MOCAG', 'MOCAG BAGGAO', 'MOAG', 'MAOCAG');

-- 32. Nangalinan
UPDATE citations SET barangay = 'Nangalinan' WHERE barangay IN ('NANGALINAN', 'NAMGALINAN', 'Nanarian');

-- 33. Poblacion (Centro)
UPDATE citations SET barangay = 'Poblacion (Centro)' WHERE barangay IN (
    'Poblacion', 'Centro', 'CENTRO BAGGAO', 'CENTRO, BAGGAO',
    'CENTRO POBLACION', 'Baggao', 'POBALCION', 'POBLACIOPN',
    'POBLASCION', 'PPOBLACION', 'POBLACION BAGGAO'
);

-- 34. Remus
UPDATE citations SET barangay = 'Remus' WHERE barangay IN ('REMUS', 'PUROK PAPAYA, REMUS');

-- 35. San Antonio
UPDATE citations SET barangay = 'San Antonio' WHERE barangay IN (
    'San antonio', 'SAN ANTONIA', 'SAN ATONIO', 'SN ANTONIO',
    'ZONE 5, SAN ANTONIO'
);

-- 36. San Francisco
UPDATE citations SET barangay = 'San Francisco' WHERE barangay IN (
    'SAN FRANCISCO', 'SAN FRANCIDCO', 'SAN FRANCISO',
    'SA FRANCISCO', 'SAN  FRANCISCO', 'SN FRANCISCO', 'SN.FRANCISCO'
);

-- 37. San Isidro
UPDATE citations SET barangay = 'San Isidro' WHERE barangay IN ('SAN ISIDRO', 'SAN ISIDRO4');

-- 38. San Jose
UPDATE citations SET barangay = 'San Jose' WHERE barangay IN ('SAN  JOSE', 'SANJOSE', 'SALVADOR ST. SAN JOSE');

-- 39. San Miguel
UPDATE citations SET barangay = 'San Miguel' WHERE barangay IN ('SAN MIGUEL', 'SANMIGUEL');

-- 40. San Vicente
UPDATE citations SET barangay = 'San Vicente' WHERE barangay = 'SAN VICENTE';

-- 41. Santa Margarita
UPDATE citations SET barangay = 'Santa Margarita' WHERE barangay IN (
    'Santa margarita', 'STA MARGARITA', 'STA. MARGARITA',
    'STA.MARGARITA', 'SITIO DLIGADIG, STA MARGARITA'
);

-- 42. Santor
UPDATE citations SET barangay = 'Santor' WHERE barangay = 'SANTOR';

-- 43. Taguing
UPDATE citations SET barangay = 'Taguing' WHERE barangay = 'TAGUING';

-- 44. Taguntungan
UPDATE citations SET barangay = 'Taguntungan' WHERE barangay IN ('TAGUNTUNGAN', 'Tagungtungan');

-- 45. Tallang
UPDATE citations SET barangay = 'Tallang' WHERE barangay = 'TALLANG';

-- 46. Taytay
UPDATE citations SET barangay = 'Taytay' WHERE barangay IN (
    'TAYTAY', 'Taytay bantay', 'TAYTAY LABBEN', 'TAY TAY', 'TAY-TAY', 'TATAY'
);

-- 47. Temblique
UPDATE citations SET barangay = 'Temblique' WHERE barangay IN (
    'TEMBLIQUE', 'TEMBLEQUE', 'TEMBBLIQUE', 'TEMBLLIQUE', 'TRMBLIQUE'
);

-- 48. Tungel
UPDATE citations SET barangay = 'Tungel' WHERE barangay IN ('TUNGEL', 'TUNGUEL');

-- 49. Dapir (not in dropdown but valid Baggao barangay)
UPDATE citations SET barangay = 'Dapir' WHERE barangay = 'DAPIR';

-- 50. Masisit (not in dropdown but valid Baggao barangay)
UPDATE citations SET barangay = 'Masisit' WHERE barangay = 'MASISIT';

-- 51. Calantac (not in dropdown but valid Baggao barangay)
UPDATE citations SET barangay = 'Calantac' WHERE barangay IN (
    'CALANTAC', 'CALANTAC ALCALA', 'CALANTAC, ALCALA', 'Calantac,Alcala'
);

-- 52. Tabuan
UPDATE citations SET barangay = 'Tabuan' WHERE barangay IN ('Tabuan', 'TABUAN');

-- ============================================================
-- STEP 2: OUT-OF-MUNICIPALITY ENTRIES → "Other"
-- ============================================================

-- Alcala and its barangays
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'ALCALA', 'ALCALA, CAGAYAN', 'ALCALA, MARABURAB',
    'AFUSING ALCALA', 'AGANI, ALCALA', 'ARANAAR, AQLCALA',
    'BACOLOD ALCALA', 'BACULOD', 'BACULOD ALCALA', 'Baculud', 'Baculud ,alcala',
    'BAYBAYOG', 'BAYBAYOG ALCALA', 'BAYBAYOG, ALCALA',
    'CENTRO SUR ALCALA', 'Dalaoig Alcala', 'DURUMUG ALCALA',
    'JURISDICTION, ALCALA', 'PAGBANGKERUAN ALCALA', 'PAGBANGKERUAN, ALCALA',
    'PARED ALCALA', 'PARED, ALCALA', 'PARET ALCALA',
    'PINOPOC ALCALA', 'PINUCPOC ALCALA', 'PINUKPUK, ALCALA',
    'PINUPOC ALCALA', 'PINUPOK', 'PINUPOK ALCALA',
    'PUSSIAN ALCALA', 'TUPANG ALCALA',
    'SAN ESTEBAN', 'SAN ESTEBAN ALCALA', 'SAN ESTEBAN, ALCALA',
    'SAN CARLOA', 'San carlos'
);

-- Amulung and its barangays
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'Amulong', 'AMULUNG', 'AMULUNG CAGAYAN', 'AMULUNG WEST',
    'AMULUNG, MAGOGOD', 'ANQUIRAY,AMULUNG',
    'BABAYUAN AMULUNG', 'BABAYUWAN AMULUNG', 'BACULUD AMULUNG',
    'BACOLOD, AMULUNG', 'CALAMAGUI AMULUNG', 'CALAMAGUI, AMULUNG',
    'Caratcatan,amulung', 'Dadda, Amulung', 'ESTEFANIA AMULUNG',
    'GABUT AMULUNG', 'Gangawan, amulung', 'MAGOGOD AMULUNG',
    'MANALO AMULUNG', 'MONTE ALEGRO, AMULONG', 'Monte aligre',
    'MONTEALEGRE AMULUNG', 'ST. TOMAS, AMULUNG',
    'UNAG AMULUNG', 'UNAG, AMULUNG'
);

-- Gattaran and its barangays
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'GATTARAN', 'GATTARAN DUMMUN', 'GATTARAN PALAGAO',
    'AGUIGICAN GATTARAN', 'AGUIGUICAN',
    'BARACAUIT, GATTARAN', 'BARACCAUIT GATTARAN',
    'BASAO GATTARAN', 'BASAO, GATTARAN',
    'CAPASSAYAN NORTE, GATTARAN', 'CAPISSAYAN', 'CAPISSAYAN GATTARAN',
    'CAPISSAYAN NORTE', 'CAPISYAN SUR',
    'CULLIT GATTARAN', 'CUMAO GATTARAN',
    'LAPOGAN GATTARAN', 'MABONO GATTARAN', 'MABUNO', 'MABUNO GATTARAN', 'MABUNO, GATTARAN',
    'NABBACAYAN GATTARAN', 'NABACCAYAN', 'NIWAGAK GATTARAN',
    'PALLAGAO SUR, GATTARAN', 'Panungsul gattaran',
    'SIDEM GATTARAN', 'SAN CARLOS GATTARAN', 'SAN VICENTE GATTARAN',
    'PE\u00d1A ESTE', 'PE\u00d1A WESTE', 'PE\u00d1A WESTE GATTARAN',
    'PE\u00d1AWESTE, GATTARAN', 'PI\u00d1A WESTE', 'PI\u00d1A WESTE GATTARAN', 'PI\u00d1AWESTE GATTARAN'
);
-- Handle PEÑA/PIÑA variants with LIKE for encoding issues
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'PE_A WESTE%' AND barangay NOT IN (SELECT barangay FROM (SELECT DISTINCT barangay FROM citations WHERE barangay IN ('Bitag Pequeño')) t);
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'PE_A ESTE%';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'PI_A WESTE%';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'PI_AWESTE%';

-- Tuguegarao
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'TUGUEGARAO', 'TUGUEGARAO CITY', 'TUGUEGARAO LARION ALTO', 'TUNGUEGARAO',
    'ANNAFUNAN', 'ANNAFUNAN EAST',
    'BALZAIN WEST TUGUGARAO CITY',
    'Buntun', 'BUNTUN, TUG. CITY',
    'CAGGAY', 'CAGGAY TUGUEGARAO CITY',
    'CARIG', 'CARITAN CENTRO', 'CARITAN TUGUEGARAO',
    'CATAGGAMAN PARDO',
    'GOSI TUGUEGARAO CITY',
    'Linao', 'LINAO EAST TUGUEGARAO CITY', 'LINAO EAST, TUG. CITY', 'LINAO, TUGUEGARAO',
    'NASIPING',
    'PALLUA SUR, TUG. CITY', 'PALUA, TUGUEGARAO',
    'UGAC SUR', 'UGAC SUR TUG, CITY', 'UGAC SUR, TUG, CITY',
    'Z-C BASSIG ST. EXT UGAC NORTE'
);

-- Peñablanca and its barangays
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'PE\u00d1ABLANCA', 'PE\u00d1ALANCA', 'PE\u00d1BLACA',
    'BALIUAG PE\u00d1ABLANCA', 'BUGATAY PE\u00d1ABLANCA',
    'CABBO PE\u00d1ABLANCA', 'Callao, Pe\u00f1ablanca',
    'DODAN PE\u00d1ABLANCA', 'GUABAN PE\u00d1ABLANCA', 'GUAVA PE\u00d1ABLANCA',
    'QUIBAL', 'QUIBAL PE\u00d1ABLANCA',
    'TUMBALI, PE\u00d1ABLANCA'
);
-- Handle PEÑABLANCA encoding variants
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE '%PE_ABLANCA%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE '%PE_ALANCA%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE '%PE_BLACA%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'Nanguilat%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'NANGUILAT%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'NANGUILLAT%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'NANGILAT%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'NANGUILTAN%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'NANGUILTAT%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'NANGUILTTAN%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'NANNGUILAT%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'Nangillat%' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay IN ('NAGUILATTAN', 'NAGUILLATAN', 'NAGUILLATTAN');
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'GUAVAN NANGUILAT%' AND barangay != 'Other';

-- Other Cagayan municipalities
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'APARRI', 'APPARI', 'PUNTA APPARI', 'BANGAG APARRI',
    'ALLACAPAN', 'PACAC, ALLACAPAN',
    'BUGUEY', 'BALLANG BUGUEY', 'Centro west buguey',
    'ENRILE', 'BAYU ENRILE',
    'GONZAGA CAGAYAN', 'SANTA CLARA, GONZAGA',
    'IGUIG', 'MALABBAC IGUIG',
    'Lasam', 'ALANNAY LASAM', 'CABATACAN LASAM', 'NICOLAS, LASAM',
    'CALAMANIUGAN',
    'PIAT', 'PENGUE',
    'SOLANA', 'BANGAG, SOLANA', 'BASI EAST, SOLANA', 'CADAMAN SOLANA', 'SAMPAGUITA SOLANA',
    'SANTA ANA', 'CENTRO STA ANA', 'STA ANA CAGAYAN',
    'TUAO', 'TUAO WEST', 'BUGNAY TUAO', 'BAGUMBAYA, TUAO, CAG',
    'BINAG LALLO', 'CATUGAN LALLO', 'Santa Maria, Lallo', 'STA. MARIA, LALLO',
    'LUCBAN, ABULUG, CAGAYAN', 'LUKBAN ABULUG', 'SIRIT ABULUG',
    'NATAPPIAN EAST', 'Cataruan',
    'CALAOAGAN SAN JOSE', 'CALAOGAN', 'CALLAO'
);
-- Handle CALAMAÑUGAN encoding
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'CALAMA%UGAN' AND barangay != 'Other';

-- STA TERESITA (Lallo/other)
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'SANTA TERESITA', 'STA TERESITA', 'STA TERESITA LALLO', 'STA. TERESITA'
);

-- Other provinces/regions/cities
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'ABRA', 'ANTIQUE', 'CAVITE', 'CAMARINES',
    'ILOCOS', 'ILOCOS NORTE', 'ILOCOS SUR',
    'ISABELA', 'ILAGAN CITY ISABELA', 'ILAGAN ISABELA', 'SAN MARIANO ISABELA',
    'San Mateo Isabela', 'Apanay isabela', 'BALACAYU ISABELA',
    'CORDON ISABELA', 'DIBULOS, ISABELA', 'LABINAB CAUAYAN ISABELA',
    'MALLIG', 'TUMAUINI', 'TUMAUINI ISABELA',
    'STA MARIA ISABLELA', 'Sta Maria, Isabela',
    'LAGUNA', 'CALAMBA LAGUNA',
    'NUEVA VISCAYA',
    'PAMPANGA',
    'SURIGAO',
    'TAGUIG',
    'TABUK KALINGA',
    'MANDALUYONG CITY, NCR',
    'SAN PABLO CITY',
    'SAN JOSE STA MARIA BULACAN',
    'SAN RODRIGO, ILAGAN',
    'SAN VICENTE FERRER, CALOOCAN',
    'SAN FABIAN PANGASINAN', 'CABINUANGAN PANGASINAN',
    'SAN MARCOS ALTOSI LISTA POTCA',
    'BANCAL CARMONA CAVITE',
    'LAOAG CITY, ILOCOS NORTE',
    'NAGBACALAN, ILOCOS',
    'BALLAIGI, SINAIT, ILOCOS',
    'PUGIL, SANTOL, LA UNION',
    'PANDENO,SINOLOAN',
    'SINIKKING RIZAL',
    'MAHARLIKA HWY, SAN ANDRES, SANCHEZ',
    'DO\u00d1A AURORA QUEZON',
    '4058 KALAYAAN AVE. MAKATI',
    '979A METRICA ST. SAMPALOC MLA',
    'USA',
    'HOT SPRING', 'SITIO HOTSPRING'
);
-- Handle DOÑA encoding variant
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'DO_A AURORA%' AND barangay != 'Other';

-- Remaining miscellaneous out-of-Baggao entries
UPDATE citations SET barangay = 'Other' WHERE barangay IN (
    'BAGUMBAYAN', 'BALAGAN', 'BAYAN', 'BINOGAN', 'BINAS',
    'BURONG', 'CAMASI', 'DUGAYONG', 'ERNESTO',
    'KALLIDIGAN', 'LUBIGAN', 'MAGALLAYAO', 'MAINI',
    'MANSAPUNG', 'MANSARONG', 'PAGAPAG', 'PAUA', 'PUSIAN',
    'RAGARAG', 'RUBEN', 'SANTO CRISTO', 'SANTOS',
    'STO NI\u00d1O', 'TABAO', 'TABBAC', 'TALANG', 'TALIGAN',
    'TANGLAGAN', 'TUEG',
    'Cabuluan west', 'DAMURUG'
);
-- Handle STO NIÑO encoding
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'STO NI%O' AND barangay != 'Other';
UPDATE citations SET barangay = 'Other' WHERE barangay LIKE 'STA. MARTINEZ%' AND barangay != 'Other';

-- ============================================================
-- STEP 3: APPLY SAME NORMALIZATION TO DRIVERS TABLE
-- ============================================================

-- 1. Adaoag
UPDATE drivers SET barangay = 'Adaoag' WHERE barangay IN ('ADAOAG', 'ADAOG', 'ADOAG');

-- 2. Agaman (Proper)
UPDATE drivers SET barangay = 'Agaman (Proper)' WHERE barangay IN ('AGAMAN PROPER', 'Agaman');

-- 3. Agaman Norte
UPDATE drivers SET barangay = 'Agaman Norte' WHERE barangay IN ('AGAMAN NORTE', 'AGAMA NORTE');

-- 4. Agaman Sur
UPDATE drivers SET barangay = 'Agaman Sur' WHERE barangay IN ('AGAMAN SUR', 'AGAMAN SUE');

-- 6. Annayatan
UPDATE drivers SET barangay = 'Annayatan' WHERE barangay IN ('ANNAYATAN', 'AANNAYATAN', 'ANNNAYATAN', 'ANNYATAN');

-- 7. Asassi
UPDATE drivers SET barangay = 'Asassi' WHERE barangay IN ('Asassi', 'ASSASI', 'ASSASSI', 'KAMARUNGGAYAN, ASASSI');

-- 8. Asinga-Via
UPDATE drivers SET barangay = 'Asinga-Via' WHERE barangay IN ('ASINGA VIA', 'ASINGA-VIA', 'ASINGA', 'ASINGA VIA5');

-- 9. Awallan
UPDATE drivers SET barangay = 'Awallan' WHERE barangay = 'AWALLAN';

-- 10. Bacagan
UPDATE drivers SET barangay = 'Bacagan' WHERE barangay = 'BACAGAN';

-- 11. Bagunot
UPDATE drivers SET barangay = 'Bagunot' WHERE barangay IN ('BAGUNOT', 'BAGANOT');

-- 12. Barsat East
UPDATE drivers SET barangay = 'Barsat East' WHERE barangay IN ('BARSAT EAST', 'BARSAT EAT', 'BAESAT EAST', 'BARSAT');

-- 13. Barsat West
UPDATE drivers SET barangay = 'Barsat West' WHERE barangay IN ('Barsat west');

-- 14. Bitag Grande
UPDATE drivers SET barangay = 'Bitag Grande' WHERE barangay IN (
    'BITAG GRANDE', 'Bitag', 'B.GRANDE', 'BITAG GANDE', 'BITAG GARNDE',
    'BITAG GGRANDE', 'BITAG GRAND', 'BITAG GRANDSE', 'BITAG GRNDE',
    'BITAG, GRANDE', 'BITAGB GRANDE', 'BITAG SITIO ASAO', 'Bitag(tueg)',
    'ASSAO', 'ASSAO, BITAG GRANDE', 'NONEBITAG GRANDE'
);

-- 15. Bitag Pequeño
UPDATE drivers SET barangay = 'Bitag Pequeño' WHERE barangay IN (
    'BITAG PEQUEÑO', 'BITAG PIQUEÑO', 'B.PEQUEÑO',
    'BITAG  PEQUEÑO', 'BITAG PEQENIO', 'BITAG PEQUENIO',
    'BITAG PIQUEN0', 'PITAG PEQUEÑO'
);
UPDATE drivers SET barangay = 'Bitag Pequeño' WHERE barangay LIKE 'BITAG P%' AND barangay != 'Bitag Pequeño';
UPDATE drivers SET barangay = 'Bitag Pequeño' WHERE barangay LIKE 'B.P%' AND barangay != 'Bitag Pequeño';
UPDATE drivers SET barangay = 'Bitag Pequeño' WHERE barangay LIKE 'PITAG P%';

-- 16. Bunugan
UPDATE drivers SET barangay = 'Bunugan' WHERE barangay = 'BUNUGAN';

-- 17. C. Verzosa (Valley Cove)
UPDATE drivers SET barangay = 'C. Verzosa (Valley Cove)' WHERE barangay IN (
    'C. VERZOSA', 'C. VERSOZA', 'CVERZOSA', 'C Verzosa', 'VERSOZA',
    'C-VERSOZA', 'VERSOOZA', 'VERSOSA', 'VERZOSA'
);

-- 18. Canagatan
UPDATE drivers SET barangay = 'Canagatan' WHERE barangay IN ('CANAGATAN', 'Cannagatan');

-- 19. Carupian
UPDATE drivers SET barangay = 'Carupian' WHERE barangay IN ('CARUPIAN', 'CARRUPIAN', 'CAROPIAN');

-- 20. Catugay
UPDATE drivers SET barangay = 'Catugay' WHERE barangay = 'CATUGAY';

-- 21. Dabbac Grande
UPDATE drivers SET barangay = 'Dabbac Grande' WHERE barangay IN ('Dabbac Grande', 'DABBAC');

-- 22. Dalin
UPDATE drivers SET barangay = 'Dalin' WHERE barangay IN ('DALIN', 'DALLIN');

-- 23. Dalla
UPDATE drivers SET barangay = 'Dalla' WHERE barangay IN ('DALLA', 'DALLA BAGGAO', 'Dalla2');

-- 24. Hacienda Intal
UPDATE drivers SET barangay = 'Hacienda Intal' WHERE barangay IN (
    'HACIENDA INTAL', 'HACIENDA', 'HACIENDA-INTAL',
    'MARUS HACIENDA INTAL', 'MARUS, HACIENDA', 'HACIENDA 9NTAL',
    'HACINDA INTAL', 'HACINEDA INTAL', 'MARUS HACIENDA',
    'STIO MARUS, HACIENDA INTAL', 'BIRAO HACIENDA', 'Marus',
    'MARUS', 'BIRAO'
);

-- 25. Ibulo
UPDATE drivers SET barangay = 'Ibulo' WHERE barangay IN ('IBULO', 'IBOLO');

-- 26. Immurung
UPDATE drivers SET barangay = 'Immurung' WHERE barangay IN (
    'IMURUNG', 'Immurung', 'IMURONG', 'IMIRUNG', 'IMURUG',
    'IMURUN', 'IMURUNGA', 'DAMURUG'
);

-- 27. J. Pallagao
UPDATE drivers SET barangay = 'J. Pallagao' WHERE barangay IN (
    'J. PALLAGAO', 'PALLAGAO', 'J PALLAGAO', 'J.PALLAGAO',
    'JPALLAGAO', 'Pallagai'
);

-- 28. Lasilat
UPDATE drivers SET barangay = 'Lasilat' WHERE barangay = 'LASILAT';

-- 29. Mabini
UPDATE drivers SET barangay = 'Mabini' WHERE barangay = 'MABINI';

-- 30. Masical
UPDATE drivers SET barangay = 'Masical' WHERE barangay IN ('MASICAL', 'Masical Baggao', 'Masikal');

-- 31. Mocag
UPDATE drivers SET barangay = 'Mocag' WHERE barangay IN ('MOCAG', 'MOCAG BAGGAO', 'MOAG', 'MAOCAG');

-- 32. Nangalinan
UPDATE drivers SET barangay = 'Nangalinan' WHERE barangay IN ('NANGALINAN', 'NAMGALINAN', 'Nanarian');

-- 33. Poblacion (Centro)
UPDATE drivers SET barangay = 'Poblacion (Centro)' WHERE barangay IN (
    'Poblacion', 'Centro', 'CENTRO BAGGAO', 'CENTRO, BAGGAO',
    'CENTRO POBLACION', 'Baggao', 'POBALCION', 'POBLACIOPN',
    'POBLASCION', 'PPOBLACION', 'POBLACION BAGGAO'
);

-- 34. Remus
UPDATE drivers SET barangay = 'Remus' WHERE barangay IN ('REMUS', 'PUROK PAPAYA, REMUS');

-- 35. San Antonio
UPDATE drivers SET barangay = 'San Antonio' WHERE barangay IN (
    'San antonio', 'SAN ANTONIA', 'SAN ATONIO', 'SN ANTONIO',
    'ZONE 5, SAN ANTONIO'
);

-- 36. San Francisco
UPDATE drivers SET barangay = 'San Francisco' WHERE barangay IN (
    'SAN FRANCISCO', 'SAN FRANCIDCO', 'SAN FRANCISO',
    'SA FRANCISCO', 'SAN  FRANCISCO', 'SN FRANCISCO', 'SN.FRANCISCO'
);

-- 37. San Isidro
UPDATE drivers SET barangay = 'San Isidro' WHERE barangay IN ('SAN ISIDRO', 'SAN ISIDRO4');

-- 38. San Jose
UPDATE drivers SET barangay = 'San Jose' WHERE barangay IN ('SAN  JOSE', 'SANJOSE', 'SALVADOR ST. SAN JOSE');

-- 39. San Miguel
UPDATE drivers SET barangay = 'San Miguel' WHERE barangay IN ('SAN MIGUEL', 'SANMIGUEL');

-- 40. San Vicente
UPDATE drivers SET barangay = 'San Vicente' WHERE barangay = 'SAN VICENTE';

-- 41. Santa Margarita
UPDATE drivers SET barangay = 'Santa Margarita' WHERE barangay IN (
    'Santa margarita', 'STA MARGARITA', 'STA. MARGARITA',
    'STA.MARGARITA', 'SITIO DLIGADIG, STA MARGARITA'
);

-- 42. Santor
UPDATE drivers SET barangay = 'Santor' WHERE barangay = 'SANTOR';

-- 43. Taguing
UPDATE drivers SET barangay = 'Taguing' WHERE barangay = 'TAGUING';

-- 44. Taguntungan
UPDATE drivers SET barangay = 'Taguntungan' WHERE barangay IN ('TAGUNTUNGAN', 'Tagungtungan');

-- 45. Tallang
UPDATE drivers SET barangay = 'Tallang' WHERE barangay = 'TALLANG';

-- 46. Taytay
UPDATE drivers SET barangay = 'Taytay' WHERE barangay IN (
    'TAYTAY', 'Taytay bantay', 'TAYTAY LABBEN', 'TAY TAY', 'TAY-TAY', 'TATAY'
);

-- 47. Temblique
UPDATE drivers SET barangay = 'Temblique' WHERE barangay IN (
    'TEMBLIQUE', 'TEMBLEQUE', 'TEMBBLIQUE', 'TEMBLLIQUE', 'TRMBLIQUE'
);

-- 48. Tungel
UPDATE drivers SET barangay = 'Tungel' WHERE barangay IN ('TUNGEL', 'TUNGUEL');

-- 49. Dapir
UPDATE drivers SET barangay = 'Dapir' WHERE barangay = 'DAPIR';

-- 50. Masisit
UPDATE drivers SET barangay = 'Masisit' WHERE barangay = 'MASISIT';

-- 51. Calantac
UPDATE drivers SET barangay = 'Calantac' WHERE barangay IN (
    'CALANTAC', 'CALANTAC ALCALA', 'CALANTAC, ALCALA', 'Calantac,Alcala'
);

-- 52. Tabuan
UPDATE drivers SET barangay = 'Tabuan' WHERE barangay IN ('Tabuan', 'TABUAN');

-- Commit all changes
COMMIT;

-- ============================================================
-- VERIFICATION QUERIES (run after migration)
-- ============================================================
-- SELECT COUNT(DISTINCT barangay) FROM citations WHERE deleted_at IS NULL;
-- SELECT barangay, COUNT(*) as cnt FROM citations WHERE deleted_at IS NULL GROUP BY barangay ORDER BY barangay;
