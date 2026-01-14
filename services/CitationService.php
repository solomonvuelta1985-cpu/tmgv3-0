<?php
/**
 * CitationService
 * Handles all database operations related to citations
 */
class CitationService {
    private $conn;

    public function __construct($pdo = null) {
        if ($pdo === null) {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } else {
            $this->conn = $pdo;
        }
    }

    /**
     * Generate next available ticket number
     * Automatically detects and preserves the citation number format (number of digits)
     * @return string Next ticket number
     */
    public function generateNextTicketNumber() {
        try {
            // Fetch the most recently created citation (by timestamp, not numeric value)
            // This ensures manual entries with different formats are respected
            $stmt = $this->conn->query(
                "SELECT ticket_number FROM citations
                 ORDER BY created_at DESC, citation_id DESC LIMIT 1"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Detect format length and extract numeric value
            if ($row && !empty($row['ticket_number'])) {
                $last_ticket = $row['ticket_number'];

                // Detect the format length (number of digits in the last citation)
                $format_length = strlen($last_ticket);

                // Extract numeric value (remove any non-numeric characters)
                $max_ticket = (int)preg_replace('/[^0-9]/', '', $last_ticket);
            } else {
                // No citations exist yet - use default 6-digit format starting from 000001
                $format_length = 6;
                $max_ticket = 0;
            }

            // Generate next ticket number with the same format length
            $next_ticket = sprintf("%0{$format_length}d", $max_ticket + 1);

            // Ensure unique ticket number (in case of duplicates)
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM citations WHERE ticket_number = :ticket_number"
            );
            $stmt->execute([':ticket_number' => $next_ticket]);

            while ($stmt->fetchColumn() > 0) {
                $max_ticket++;
                $next_ticket = sprintf("%0{$format_length}d", $max_ticket + 1);
                $stmt->execute([':ticket_number' => $next_ticket]);
            }

            return $next_ticket;
        } catch (PDOException $e) {
            error_log("Error generating ticket number: " . $e->getMessage());
            // Return default 6-digit format on error
            return "000001";
        }
    }

    /**
     * Get driver data by ID
     * @param int $driver_id Driver ID
     * @return array|false Driver data or false if not found
     */
    public function getDriverById($driver_id) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT * FROM drivers WHERE driver_id = :driver_id"
            );
            $stmt->execute([':driver_id' => $driver_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching driver: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get offense counts for a specific driver
     * @param int $driver_id Driver ID
     * @return array Offense counts indexed by violation_type_id
     */
    public function getOffenseCountsByDriverId($driver_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vt.violation_type_id, MAX(v.offense_count) AS offense_count
                FROM violations v
                JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                JOIN citations c ON v.citation_id = c.citation_id
                WHERE c.driver_id = :driver_id AND c.deleted_at IS NULL
                GROUP BY vt.violation_type_id
            ");
            $stmt->execute([':driver_id' => $driver_id]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            error_log("Error fetching offense counts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active violation types
     * @return array Violation types
     */
    public function getActiveViolationTypes() {
        try {
            $stmt = $this->conn->query(
                "SELECT violation_type_id, violation_type, category_id, fine_amount_1, fine_amount_2, fine_amount_3
                 FROM violation_types
                 WHERE is_active = 1
                 ORDER BY violation_type"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching violation types: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active apprehending officers
     * @return array Apprehending officers
     */
    public function getActiveApprehendingOfficers() {
        try {
            $stmt = $this->conn->query(
                "SELECT officer_id, officer_name, badge_number, position
                 FROM apprehending_officers
                 WHERE is_active = 1
                 ORDER BY officer_name"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching officers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get citation statistics
     * @return array Statistics array with total, pending, paid, contested, total_fines
     */
    public function getStatistics() {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'paid' => 0,
            'contested' => 0,
            'total_fines' => 0
        ];

        try {
            // Check if deleted_at column exists
            $hasDeletedAt = false;
            try {
                $stmt = $this->conn->query("SHOW COLUMNS FROM citations LIKE 'deleted_at'");
                $hasDeletedAt = $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                // Column doesn't exist, use old queries
            }

            $deletedFilter = $hasDeletedAt ? " WHERE deleted_at IS NULL" : "";

            // Total citations
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM citations" . $deletedFilter);
            $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Pending
            $whereClause = $hasDeletedAt ? "WHERE status = 'pending' AND deleted_at IS NULL" : "WHERE status = 'pending'";
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM citations $whereClause");
            $stats['pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Paid
            $whereClause = $hasDeletedAt ? "WHERE status = 'paid' AND deleted_at IS NULL" : "WHERE status = 'paid'";
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM citations $whereClause");
            $stats['paid'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Contested
            $whereClause = $hasDeletedAt ? "WHERE status = 'contested' AND deleted_at IS NULL" : "WHERE status = 'contested'";
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM citations $whereClause");
            $stats['contested'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Total fines
            $stmt = $this->conn->query("SELECT SUM(total_fine) as total FROM citations" . $deletedFilter);
            $stats['total_fines'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error fetching statistics: " . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Get total citations count with filters
     * @param string $search Search term
     * @param string $status_filter Status filter
     * @return int Total count
     */
    public function getCitationsCount($search = '', $status_filter = '') {
        $where_clauses = [];
        $params = [];

        // Check if deleted_at column exists and add filter
        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM citations LIKE 'deleted_at'");
            if ($stmt->rowCount() > 0) {
                $where_clauses[] = "c.deleted_at IS NULL";
            }
        } catch (PDOException $e) {
            // Column doesn't exist, continue without filter
        }

        if (!empty($search)) {
            $where_clauses[] = "(c.ticket_number LIKE ? OR c.last_name LIKE ? OR c.first_name LIKE ? OR c.license_number LIKE ? OR c.plate_mv_engine_chassis_no LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
        }

        if (!empty($status_filter)) {
            $where_clauses[] = "c.status = ?";
            $params[] = $status_filter;
        }

        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

        try {
            $count_sql = "SELECT COUNT(*) as total FROM citations c $where_sql";
            $stmt = $this->conn->prepare($count_sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error fetching citations count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get paginated citations with search and filter
     * @param int $page Page number
     * @param int $per_page Records per page
     * @param string $search Search term
     * @param string $status_filter Status filter
     * @return array Citations array
     */
    public function getCitations($page = 1, $per_page = 15, $search = '', $status_filter = '') {
        $offset = ($page - 1) * $per_page;
        $where_clauses = [];
        $params = [];

        // Check if deleted_at column exists and add filter
        try {
            $stmt = $this->conn->query("SHOW COLUMNS FROM citations LIKE 'deleted_at'");
            if ($stmt->rowCount() > 0) {
                $where_clauses[] = "c.deleted_at IS NULL";
            }
        } catch (PDOException $e) {
            // Column doesn't exist, continue without filter
        }

        if (!empty($search)) {
            $where_clauses[] = "(c.ticket_number LIKE ? OR c.last_name LIKE ? OR c.first_name LIKE ? OR c.license_number LIKE ? OR c.plate_mv_engine_chassis_no LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
        }

        if (!empty($status_filter)) {
            $where_clauses[] = "c.status = ?";
            $params[] = $status_filter;
        }

        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

        try {
            $sql = "SELECT c.*,
                    GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violations,
                    u.username as created_by_username,
                    u.full_name as created_by_name
                    FROM citations c
                    LEFT JOIN violations v ON c.citation_id = v.citation_id
                    LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                    LEFT JOIN users u ON c.created_by = u.user_id
                    $where_sql
                    GROUP BY c.citation_id
                    ORDER BY c.created_at DESC
                    LIMIT $per_page OFFSET $offset";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching citations: " . $e->getMessage());
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
