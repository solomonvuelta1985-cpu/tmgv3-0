<?php
/**
 * DuplicateDetectionService
 * Handles duplicate driver detection using multiple matching strategies
 */
class DuplicateDetectionService {
    private $conn;

    public function __construct($pdo = null) {
        if ($pdo === null) {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } else {
            $this->conn = $pdo;
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
    }

    /**
     * Find possible duplicate drivers using multiple strategies
     * @param array $driver_info Driver information to check
     * @return array List of possible matches with confidence scores
     */
    public function findPossibleDuplicates($driver_info) {
        $matches = [];

        // Strategy 1: Exact license number match (Highest priority)
        if (!empty($driver_info['license_number'])) {
            $license_matches = $this->findByLicenseNumber($driver_info['license_number']);
            foreach ($license_matches as $match) {
                $match['match_type'] = 'license_number';
                $match['confidence'] = 100;
                $match['reason'] = 'Exact license number match';
                $matches[] = $match;
            }
        }

        // Strategy 2: Vehicle/Plate number match
        if (!empty($driver_info['plate_number'])) {
            $vehicle_matches = $this->findByPlateNumber($driver_info['plate_number']);
            foreach ($vehicle_matches as $match) {
                // Skip if already matched by license
                if ($this->isAlreadyMatched($matches, $match['driver_id'])) {
                    continue;
                }
                $match['match_type'] = 'plate_number';
                $match['confidence'] = 85;
                $match['reason'] = 'Same vehicle plate number';
                $matches[] = $match;
            }
        }

        // Strategy 3: Fuzzy name matching + DOB
        if (!empty($driver_info['first_name']) && !empty($driver_info['last_name'])) {
            $name_matches = $this->findByFuzzyName(
                $driver_info['first_name'],
                $driver_info['last_name'],
                $driver_info['date_of_birth'] ?? null,
                $driver_info['barangay'] ?? null
            );
            foreach ($name_matches as $match) {
                // Skip if already matched
                if ($this->isAlreadyMatched($matches, $match['driver_id'])) {
                    continue;
                }
                $match['match_type'] = 'fuzzy_name';
                $matches[] = $match;
            }
        }

        // Sort by confidence score (highest first)
        usort($matches, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });

        return $matches;
    }

    /**
     * Find driver by exact license number
     * @param string $license_number License number
     * @return array Matching drivers
     */
    private function findByLicenseNumber($license_number) {
        try {
            $sql = "SELECT DISTINCT
                    c.driver_id,
                    c.last_name,
                    c.first_name,
                    c.license_number,
                    c.date_of_birth,
                    c.barangay,
                    c.plate_mv_engine_chassis_no,
                    COUNT(DISTINCT c.citation_id) as total_citations,
                    MAX(c.created_at) as last_citation_date
                    FROM citations c
                    WHERE c.license_number = :license_number
                    GROUP BY c.driver_id, c.last_name, c.first_name,
                             c.license_number, c.date_of_birth, c.barangay, c.plate_mv_engine_chassis_no
                    ORDER BY c.created_at DESC
                    LIMIT 5";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':license_number' => $license_number]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error finding by license number: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find drivers by vehicle plate number
     * @param string $plate_number Plate number
     * @return array Matching drivers
     */
    private function findByPlateNumber($plate_number) {
        try {
            $sql = "SELECT DISTINCT
                    c.driver_id,
                    c.last_name,
                    c.first_name,
                    c.license_number,
                    c.date_of_birth,
                    c.barangay,
                    c.plate_mv_engine_chassis_no,
                    COUNT(DISTINCT c.citation_id) as total_citations,
                    MAX(c.created_at) as last_citation_date
                    FROM citations c
                    WHERE c.plate_mv_engine_chassis_no = :plate_number
                    GROUP BY c.driver_id, c.last_name, c.first_name,
                             c.license_number, c.date_of_birth, c.barangay, c.plate_mv_engine_chassis_no
                    ORDER BY c.created_at DESC
                    LIMIT 5";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':plate_number' => $plate_number]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error finding by plate number: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find drivers by fuzzy name matching
     * @param string $first_name First name
     * @param string $last_name Last name
     * @param string $dob Date of birth (optional)
     * @param string $barangay Barangay (optional)
     * @return array Matching drivers with confidence scores
     */
    private function findByFuzzyName($first_name, $last_name, $dob = null, $barangay = null) {
        try {
            $matches = [];

            // Normalize input
            $first_name_clean = $this->normalizeName($first_name);
            $last_name_clean = $this->normalizeName($last_name);

            // Get all drivers with similar names (using broader search)
            $sql = "SELECT DISTINCT
                    c.driver_id,
                    c.last_name,
                    c.first_name,
                    c.license_number,
                    c.date_of_birth,
                    c.barangay,
                    c.plate_mv_engine_chassis_no,
                    COUNT(DISTINCT c.citation_id) as total_citations,
                    MAX(c.created_at) as last_citation_date
                    FROM citations c
                    WHERE (
                        SOUNDEX(c.last_name) = SOUNDEX(:last_name_soundex1)
                        OR SOUNDEX(c.first_name) = SOUNDEX(:first_name_soundex1)
                        OR UPPER(c.last_name) LIKE :last_name_like
                        OR UPPER(c.first_name) LIKE :first_name_like
                        OR SOUNDEX(c.last_name) = SOUNDEX(:first_name_soundex2)
                        OR SOUNDEX(c.first_name) = SOUNDEX(:last_name_soundex2)
                        OR CONCAT(UPPER(c.first_name), ' ', UPPER(c.last_name)) LIKE :full_name_like
                        OR CONCAT(UPPER(c.last_name), ' ', UPPER(c.first_name)) LIKE :full_name_reversed_like
                    )
                    GROUP BY c.driver_id, c.last_name, c.first_name,
                             c.license_number, c.date_of_birth, c.barangay, c.plate_mv_engine_chassis_no
                    LIMIT 50";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':last_name_soundex1' => $last_name,
                ':first_name_soundex1' => $first_name,
                ':last_name_like' => '%' . $last_name_clean . '%',
                ':first_name_like' => '%' . $first_name_clean . '%',
                ':first_name_soundex2' => $first_name,
                ':last_name_soundex2' => $last_name,
                ':full_name_like' => '%' . $first_name_clean . '%' . $last_name_clean . '%',
                ':full_name_reversed_like' => '%' . $last_name_clean . '%' . $first_name_clean . '%'
            ]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate confidence for each candidate
            foreach ($candidates as $candidate) {
                $confidence = 0;
                $reasons = [];

                // Name similarity (max 50 points)
                $name_similarity = $this->calculateNameSimilarity(
                    $first_name,
                    $last_name,
                    $candidate['first_name'],
                    $candidate['last_name']
                );
                $confidence += $name_similarity * 50;
                if ($name_similarity > 0.7) {
                    $reasons[] = 'Similar name';
                }

                // Date of birth match (30 points)
                if ($dob && $candidate['date_of_birth'] && $dob === $candidate['date_of_birth']) {
                    $confidence += 30;
                    $reasons[] = 'Same date of birth';
                }

                // Barangay match (20 points)
                if ($barangay && $candidate['barangay'] &&
                    strtolower($barangay) === strtolower($candidate['barangay'])) {
                    $confidence += 20;
                    $reasons[] = 'Same barangay';
                }

                // Only include if confidence is above threshold (lowered to catch more)
                if ($confidence >= 30) {
                    $candidate['confidence'] = round($confidence);
                    $candidate['reason'] = implode(', ', $reasons);
                    $matches[] = $candidate;
                }
            }

            return $matches;
        } catch (PDOException $e) {
            error_log("Error finding by fuzzy name: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get offense history for a driver
     * @param int $driver_id Driver ID
     * @param int $violation_type_id Violation type ID (optional)
     * @return array Offense history
     */
    public function getOffenseHistory($driver_id, $violation_type_id = null) {
        try {
            $where_clauses = ["c.driver_id = :driver_id"];
            $params = [':driver_id' => $driver_id];

            if ($violation_type_id) {
                $where_clauses[] = "v.violation_type_id = :violation_type_id";
                $params[':violation_type_id'] = $violation_type_id;
            }

            $where_sql = implode(" AND ", $where_clauses);

            $sql = "SELECT
                    c.citation_id,
                    c.ticket_number,
                    c.apprehension_datetime,
                    c.status,
                    c.total_fine,
                    vt.violation_type,
                    v.offense_count,
                    v.fine_amount
                    FROM citations c
                    JOIN violations v ON c.citation_id = v.citation_id
                    JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                    WHERE $where_sql
                    ORDER BY c.apprehension_datetime DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting offense history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get offense history by vehicle plate number
     * @param string $plate_number Plate number
     * @param int $violation_type_id Violation type ID (optional)
     * @return array Offense history
     */
    public function getVehicleOffenseHistory($plate_number, $violation_type_id = null) {
        try {
            $where_clauses = ["c.plate_mv_engine_chassis_no = :plate_number"];
            $params = [':plate_number' => $plate_number];

            if ($violation_type_id) {
                $where_clauses[] = "v.violation_type_id = :violation_type_id";
                $params[':violation_type_id'] = $violation_type_id;
            }

            $where_sql = implode(" AND ", $where_clauses);

            $sql = "SELECT
                    c.citation_id,
                    c.ticket_number,
                    c.apprehension_datetime,
                    c.status,
                    c.total_fine,
                    CONCAT(c.last_name, ', ', c.first_name) as driver_name,
                    vt.violation_type,
                    v.offense_count,
                    v.fine_amount
                    FROM citations c
                    JOIN violations v ON c.citation_id = v.citation_id
                    JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                    WHERE $where_sql
                    ORDER BY c.apprehension_datetime DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting vehicle offense history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Normalize name for comparison
     * @param string $name Name to normalize
     * @return string Normalized name
     */
    private function normalizeName($name) {
        // Remove common titles and suffixes
        $name = preg_replace('/\b(Jr|Sr|II|III|IV)\b\.?/i', '', $name);

        // Remove extra spaces and convert to uppercase
        $name = strtoupper(trim(preg_replace('/\s+/', ' ', $name)));

        // Remove special characters except spaces
        $name = preg_replace('/[^A-Z\s]/', '', $name);

        return $name;
    }

    /**
     * Calculate name similarity score (0-1)
     * @param string $first1 First name 1
     * @param string $last1 Last name 1
     * @param string $first2 First name 2
     * @param string $last2 Last name 2
     * @return float Similarity score
     */
    private function calculateNameSimilarity($first1, $last1, $first2, $last2) {
        $first1_clean = $this->normalizeName($first1);
        $last1_clean = $this->normalizeName($last1);
        $first2_clean = $this->normalizeName($first2);
        $last2_clean = $this->normalizeName($last2);

        // Calculate Levenshtein distance for both names
        $first_similarity = $this->levenshteinSimilarity($first1_clean, $first2_clean);
        $last_similarity = $this->levenshteinSimilarity($last1_clean, $last2_clean);

        // Weight last name more heavily (60% last, 40% first)
        return ($last_similarity * 0.6) + ($first_similarity * 0.4);
    }

    /**
     * Calculate similarity using Levenshtein distance (0-1)
     * @param string $str1 String 1
     * @param string $str2 String 2
     * @return float Similarity score
     */
    private function levenshteinSimilarity($str1, $str2) {
        $max_len = max(strlen($str1), strlen($str2));
        if ($max_len === 0) return 1.0;

        $distance = levenshtein($str1, $str2);
        return 1 - ($distance / $max_len);
    }

    /**
     * Check if driver already matched
     * @param array $matches Existing matches
     * @param int $driver_id Driver ID to check
     * @return bool True if already matched
     */
    private function isAlreadyMatched($matches, $driver_id) {
        foreach ($matches as $match) {
            if ($match['driver_id'] == $driver_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Direct search for drivers (fallback method)
     * @param string $search_term Search term
     * @return array Matching drivers
     */
    public function directSearch($search_term) {
        try {
            $search_term = strtoupper(trim($search_term));
            $search_like = '%' . $search_term . '%';

            $sql = "SELECT DISTINCT
                    c.driver_id,
                    c.last_name,
                    c.first_name,
                    c.license_number,
                    c.date_of_birth,
                    c.barangay,
                    c.plate_mv_engine_chassis_no,
                    COUNT(DISTINCT c.citation_id) as total_citations,
                    MAX(c.created_at) as last_citation_date
                    FROM citations c
                    WHERE UPPER(c.last_name) LIKE :search1
                       OR UPPER(c.first_name) LIKE :search2
                       OR UPPER(CONCAT(c.first_name, ' ', c.last_name)) LIKE :search3
                       OR UPPER(CONCAT(c.last_name, ' ', c.first_name)) LIKE :search4
                       OR UPPER(CONCAT(c.last_name, ', ', c.first_name)) LIKE :search5
                       OR c.license_number LIKE :search6
                       OR c.plate_mv_engine_chassis_no LIKE :search7
                    GROUP BY c.driver_id, c.last_name, c.first_name,
                             c.license_number, c.date_of_birth, c.barangay, c.plate_mv_engine_chassis_no
                    ORDER BY c.created_at DESC
                    LIMIT 50";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':search1' => $search_like,
                ':search2' => $search_like,
                ':search3' => $search_like,
                ':search4' => $search_like,
                ':search5' => $search_like,
                ':search6' => $search_like,
                ':search7' => $search_like
            ]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add default metadata
            foreach ($results as &$result) {
                $result['match_type'] = 'direct_search';
                $result['confidence'] = 70;
                $result['reason'] = 'Direct database match';
            }

            return $results;
        } catch (PDOException $e) {
            error_log("Error in direct search: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
}
