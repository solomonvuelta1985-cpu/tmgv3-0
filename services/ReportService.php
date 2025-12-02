<?php
/**
 * ReportService
 * Handles all reporting and analytics queries
 */
class ReportService {
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
     * Get financial summary for a date range
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Financial statistics
     */
    public function getFinancialSummary($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $sql = "SELECT
                    COUNT(*) as total_citations,
                    SUM(CASE WHEN c.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN c.status = 'contested' THEN 1 ELSE 0 END) as contested_count,
                    SUM(c.total_fine) as total_fines_issued,
                    SUM(CASE WHEN c.status = 'paid' THEN c.total_fine ELSE 0 END) as total_fines_collected,
                    SUM(CASE WHEN c.status = 'pending' THEN c.total_fine ELSE 0 END) as total_fines_pending,
                    AVG(c.total_fine) as average_fine
                    FROM citations c
                    $where_clause";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting financial summary: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get revenue trends over time
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param string $interval Interval (day, week, month)
     * @return array Revenue data by period
     */
    public function getRevenueTrends($start_date = null, $end_date = null, $interval = 'day') {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $date_format = match($interval) {
                'week' => '%Y-W%u',
                'month' => '%Y-%m',
                'year' => '%Y',
                default => '%Y-%m-%d'
            };

            $sql = "SELECT
                    DATE_FORMAT(c.created_at, '$date_format') as period,
                    COUNT(*) as citation_count,
                    SUM(c.total_fine) as total_fines,
                    SUM(CASE WHEN c.status = 'paid' THEN c.total_fine ELSE 0 END) as collected_fines
                    FROM citations c
                    $where_clause
                    GROUP BY period
                    ORDER BY period ASC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting revenue trends: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get outstanding fines with aging
     * @param string $status Status filter (default: pending)
     * @return array Outstanding citations
     */
    public function getOutstandingFines($status = 'pending') {
        try {
            $sql = "SELECT
                    c.ticket_number,
                    CONCAT(c.last_name, ', ', c.first_name) as driver_name,
                    c.license_number,
                    c.total_fine,
                    c.created_at,
                    c.apprehension_datetime,
                    DATEDIFF(CURDATE(), c.created_at) as days_outstanding,
                    CASE
                        WHEN DATEDIFF(CURDATE(), c.created_at) <= 30 THEN '0-30 days'
                        WHEN DATEDIFF(CURDATE(), c.created_at) <= 60 THEN '31-60 days'
                        WHEN DATEDIFF(CURDATE(), c.created_at) <= 90 THEN '61-90 days'
                        ELSE '90+ days'
                    END as aging_category
                    FROM citations c
                    WHERE c.status = :status
                    ORDER BY c.created_at ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':status' => $status]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting outstanding fines: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get violation statistics
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Violation type statistics
     */
    public function getViolationStatistics($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            // First get the data without percentage
            $sql = "SELECT
                    vt.violation_type_id,
                    vt.violation_type,
                    vt.fine_amount_1,
                    COUNT(*) as violation_count,
                    SUM(v.fine_amount) as total_fines,
                    AVG(v.fine_amount) as average_fine
                    FROM violations v
                    JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                    JOIN citations c ON v.citation_id = c.citation_id
                    $where_clause
                    GROUP BY vt.violation_type_id, vt.violation_type, vt.fine_amount_1
                    ORDER BY violation_count DESC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate percentages
            $total = array_sum(array_column($results, 'violation_count'));
            foreach ($results as &$row) {
                $row['percentage'] = $total > 0 ? round(($row['violation_count'] / $total) * 100, 2) : 0;
            }

            return $results;
        } catch (PDOException $e) {
            error_log("Error getting violation statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get violation trends over time
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Violation trends by month
     */
    public function getViolationTrends($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $sql = "SELECT
                    DATE_FORMAT(c.created_at, '%Y-%m') as period,
                    vt.violation_type,
                    COUNT(*) as count
                    FROM violations v
                    JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                    JOIN citations c ON v.citation_id = c.citation_id
                    $where_clause
                    GROUP BY period, vt.violation_type_id
                    ORDER BY period ASC, count DESC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting violation trends: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get repeat offenders
     * @param int $min_citations Minimum number of citations (default: 2)
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Repeat offender data
     */
    public function getRepeatOffenders($min_citations = 2, $start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $sql = "SELECT
                    c.driver_id,
                    CONCAT(c.last_name, ', ', c.first_name) as driver_name,
                    c.license_number,
                    COUNT(DISTINCT c.citation_id) as citation_count,
                    SUM(c.total_fine) as total_fines,
                    MIN(c.created_at) as first_citation,
                    MAX(c.created_at) as latest_citation,
                    GROUP_CONCAT(DISTINCT vt.violation_type SEPARATOR ', ') as violations
                    FROM citations c
                    LEFT JOIN violations v ON c.citation_id = v.citation_id
                    LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                    $where_clause
                    GROUP BY c.driver_id, c.last_name, c.first_name, c.license_number
                    HAVING citation_count >= :min_citations
                    ORDER BY citation_count DESC, total_fines DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':min_citations', $min_citations, PDO::PARAM_INT);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting repeat offenders: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get offense count distribution
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Offense count distribution
     */
    public function getOffenseCountDistribution($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $sql = "SELECT
                    v.offense_count,
                    COUNT(*) as citation_count,
                    SUM(v.fine_amount) as total_fines
                    FROM violations v
                    JOIN citations c ON v.citation_id = c.citation_id
                    $where_clause
                    GROUP BY v.offense_count
                    ORDER BY v.offense_count ASC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting offense count distribution: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get officer performance statistics
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Officer performance data
     */
    public function getOfficerPerformance($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'created_at');

            $sql = "SELECT
                    apprehension_officer as officer_name,
                    COUNT(citation_id) as citation_count,
                    SUM(total_fine) as total_fines,
                    AVG(total_fine) as average_fine,
                    MIN(created_at) as first_citation,
                    MAX(created_at) as latest_citation
                    FROM citations
                    $where_clause
                    AND apprehension_officer IS NOT NULL
                    AND apprehension_officer != ''
                    GROUP BY apprehension_officer
                    ORDER BY citation_count DESC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting officer performance: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get officer activity by time
     * @param string $officer_name Officer name (optional)
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Officer activity timeline
     */
    public function getOfficerActivityTimeline($officer_name = null, $start_date = null, $end_date = null) {
        try {
            $where_clauses = [];
            if ($start_date && $end_date) {
                $where_clauses[] = "created_at BETWEEN :start_date AND :end_date";
            }
            if ($officer_name) {
                $where_clauses[] = "apprehension_officer = :officer_name";
            }

            $where_clause = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

            $sql = "SELECT
                    DATE_FORMAT(created_at, '%Y-%m-%d') as citation_date,
                    HOUR(apprehension_datetime) as hour_of_day,
                    COUNT(*) as citation_count
                    FROM citations
                    $where_clause
                    GROUP BY citation_date, hour_of_day
                    ORDER BY citation_date ASC, hour_of_day ASC";

            $stmt = $this->conn->prepare($sql);
            if ($start_date && $end_date) {
                $stmt->bindValue(':start_date', $start_date);
                $stmt->bindValue(':end_date', $end_date);
            }
            if ($officer_name) {
                $stmt->bindValue(':officer_name', $officer_name, PDO::PARAM_STR);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting officer activity timeline: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get time-based analytics
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Time-based statistics
     */
    public function getTimeBasedAnalytics($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $sql = "SELECT
                    HOUR(c.apprehension_datetime) as hour_of_day,
                    COUNT(*) as citation_count,
                    SUM(c.total_fine) as total_fines
                    FROM citations c
                    $where_clause
                    GROUP BY hour_of_day
                    ORDER BY hour_of_day ASC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting time-based analytics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get day of week analytics
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Day of week statistics
     */
    public function getDayOfWeekAnalytics($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $sql = "SELECT
                    DAYNAME(c.apprehension_datetime) as day_name,
                    DAYOFWEEK(c.apprehension_datetime) as day_number,
                    COUNT(*) as citation_count,
                    SUM(c.total_fine) as total_fines,
                    AVG(c.total_fine) as average_fine
                    FROM citations c
                    $where_clause
                    GROUP BY day_name, day_number
                    ORDER BY day_number ASC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting day of week analytics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get monthly analytics
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Monthly statistics
     */
    public function getMonthlyAnalytics($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $sql = "SELECT
                    DATE_FORMAT(c.created_at, '%Y-%m') as month,
                    MONTHNAME(c.created_at) as month_name,
                    COUNT(*) as citation_count,
                    SUM(c.total_fine) as total_fines,
                    AVG(c.total_fine) as average_fine,
                    SUM(CASE WHEN c.status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_count
                    FROM citations c
                    $where_clause
                    GROUP BY month, month_name
                    ORDER BY month ASC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting monthly analytics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get status distribution
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Status statistics
     */
    public function getStatusDistribution($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'created_at');

            $sql = "SELECT
                    status,
                    COUNT(*) as count,
                    SUM(total_fine) as total_fines
                    FROM citations
                    $where_clause
                    GROUP BY status
                    ORDER BY count DESC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate percentages
            $total = array_sum(array_column($results, 'count'));
            foreach ($results as &$row) {
                $row['percentage'] = $total > 0 ? round(($row['count'] / $total) * 100, 2) : 0;
            }

            return $results;
        } catch (PDOException $e) {
            error_log("Error getting status distribution: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get contested citations report
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Contested citations
     */
    public function getContestedCitations($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');
            if ($where_clause) {
                $where_clause .= " AND c.status = 'contested'";
            } else {
                $where_clause = "WHERE c.status = 'contested'";
            }

            $sql = "SELECT
                    c.ticket_number,
                    CONCAT(c.last_name, ', ', c.first_name) as driver_name,
                    c.license_number,
                    c.total_fine,
                    c.created_at,
                    c.apprehension_datetime,
                    GROUP_CONCAT(vt.violation_type SEPARATOR ', ') as violations,
                    c.apprehension_officer as officer_name
                    FROM citations c
                    LEFT JOIN violations v ON c.citation_id = v.citation_id
                    LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                    $where_clause
                    GROUP BY c.citation_id
                    ORDER BY c.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting contested citations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get vehicle type statistics
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Vehicle type statistics
     */
    public function getVehicleTypeStatistics($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'c.created_at');

            $sql = "SELECT
                    cv.vehicle_type,
                    COUNT(*) as citation_count,
                    SUM(c.total_fine) as total_fines,
                    AVG(c.total_fine) as average_fine
                    FROM citation_vehicles cv
                    JOIN citations c ON cv.citation_id = c.citation_id
                    $where_clause
                    GROUP BY cv.vehicle_type
                    ORDER BY citation_count DESC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting vehicle type statistics: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get average case resolution time
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Resolution time statistics
     */
    public function getCaseResolutionTime($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'created_at');
            if ($where_clause) {
                $where_clause .= " AND status IN ('paid', 'dismissed')";
            } else {
                $where_clause = "WHERE status IN ('paid', 'dismissed')";
            }

            $sql = "SELECT
                    status,
                    AVG(DATEDIFF(updated_at, created_at)) as avg_days_to_resolve,
                    MIN(DATEDIFF(updated_at, created_at)) as min_days,
                    MAX(DATEDIFF(updated_at, created_at)) as max_days,
                    COUNT(*) as case_count
                    FROM citations
                    $where_clause
                    GROUP BY status";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting case resolution time: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get OR usage summary
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array OR usage statistics
     */
    public function getOrUsageSummary($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'action_datetime');

            $sql = "SELECT
                    COUNT(DISTINCT or_number_new) as total_or_used,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN action_type = 'payment_cancelled' THEN 1 ELSE 0 END) as total_cancelled,
                    SUM(CASE WHEN payment_status_new = 'pending_print' THEN 1 ELSE 0 END) as pending_print
                    FROM audit_logs
                    $where_clause
                    AND action_type IN ('payment_created', 'payment_finalized')";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting OR usage summary: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get daily OR usage
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Daily OR usage data
     */
    public function getOrDailyUsage($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'action_datetime');

            $sql = "SELECT
                    DATE(action_datetime) as date,
                    COUNT(DISTINCT or_number_new) as or_count,
                    COUNT(*) as payment_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                    FROM audit_logs
                    $where_clause
                    AND action_type IN ('payment_created', 'payment_finalized')
                    GROUP BY DATE(action_datetime)
                    ORDER BY date ASC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting daily OR usage: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get OR usage by cashier
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Cashier performance data
     */
    public function getOrUsageByCashier($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'action_datetime');

            $sql = "SELECT
                    username as cashier_name,
                    COUNT(DISTINCT CASE WHEN action_type IN ('payment_created', 'payment_finalized') THEN or_number_new END) as or_count,
                    COUNT(CASE WHEN action_type IN ('payment_created', 'payment_finalized') THEN 1 END) as payment_count,
                    SUM(CASE WHEN action_type IN ('payment_created', 'payment_finalized') THEN amount ELSE 0 END) as total_amount,
                    COUNT(CASE WHEN action_type IN ('payment_cancelled', 'payment_voided') THEN 1 END) as cancelled_count
                    FROM audit_logs
                    $where_clause
                    GROUP BY username
                    ORDER BY or_count DESC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting OR usage by cashier: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get OR audit trail
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param int $limit Number of records to return
     * @return array Audit trail entries
     */
    public function getOrAuditTrail($start_date = null, $end_date = null, $limit = 100) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'action_datetime');

            $sql = "SELECT *
                    FROM audit_logs
                    $where_clause
                    ORDER BY action_datetime DESC
                    LIMIT :limit";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting OR audit trail: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get cancelled and voided payments
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Cancelled/voided payment records
     */
    public function getCancelledVoidedPayments($start_date = null, $end_date = null) {
        try {
            $where_clause = $this->buildDateWhereClause($start_date, $end_date, 'action_datetime');
            if ($where_clause) {
                $where_clause .= " AND action_type IN ('payment_cancelled', 'payment_voided')";
            } else {
                $where_clause = "WHERE action_type IN ('payment_cancelled', 'payment_voided')";
            }

            $sql = "SELECT *
                    FROM audit_logs
                    $where_clause
                    ORDER BY action_datetime DESC";

            $stmt = $this->conn->prepare($sql);
            $this->bindDateParams($stmt, $start_date, $end_date);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting cancelled/voided payments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Build date WHERE clause
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param string $column Column name
     * @return string WHERE clause
     */
    private function buildDateWhereClause($start_date, $end_date, $column) {
        if ($start_date && $end_date) {
            // Use DATE() to ensure we match full days regardless of time
            return "WHERE DATE($column) BETWEEN :start_date AND :end_date";
        }
        return "";
    }

    /**
     * Bind date parameters to statement
     * @param PDOStatement $stmt Statement
     * @param string $start_date Start date
     * @param string $end_date End date
     */
    private function bindDateParams($stmt, $start_date, $end_date) {
        if ($start_date && $end_date) {
            $stmt->bindValue(':start_date', $start_date);
            $stmt->bindValue(':end_date', $end_date);
        }
    }

    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
}
