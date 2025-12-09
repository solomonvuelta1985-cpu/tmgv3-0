<?php
/**
 * PaymentQuery Class
 *
 * Handles all payment data retrieval and filtering operations
 *
 * @package TrafficCitationSystem
 * @subpackage Services\Payment
 */

class PaymentQuery {
    private $pdo;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get payment history for a citation
     *
     * @param int $citationId Citation ID
     * @return array Payment history records
     */
    public function getPaymentHistory($citationId) {
        $sql = "SELECT
                    p.*,
                    u.full_name as collector_name,
                    u.username as collector_username,
                    r.receipt_id,
                    r.print_count,
                    r.printed_at,
                    r.status as receipt_status
                FROM payments p
                LEFT JOIN users u ON p.collected_by = u.user_id
                LEFT JOIN receipts r ON p.payment_id = r.payment_id
                WHERE p.citation_id = :citation_id
                ORDER BY p.payment_date DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':citation_id' => $citationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all payments with optional filters
     *
     * @param array $filters Filter criteria (date_from, date_to, payment_method, collected_by, status, receipt_number, ticket_number)
     * @param int $limit Results limit
     * @param int $offset Results offset for pagination
     * @return array Payments list
     */
    public function getAllPayments($filters = [], $limit = 50, $offset = 0) {
        $where = [];
        $params = [];

        // Date range filter
        if (!empty($filters['date_from'])) {
            $where[] = "p.payment_date >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "p.payment_date <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        // Payment method filter
        if (!empty($filters['payment_method'])) {
            $where[] = "p.payment_method = :payment_method";
            $params[':payment_method'] = $filters['payment_method'];
        }

        // Cashier filter
        if (!empty($filters['collected_by'])) {
            $where[] = "p.collected_by = :collected_by";
            $params[':collected_by'] = $filters['collected_by'];
        }

        // Status filter
        if (!empty($filters['status'])) {
            $where[] = "p.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Receipt number search
        if (!empty($filters['receipt_number'])) {
            $where[] = "p.receipt_number LIKE :receipt_number";
            $params[':receipt_number'] = '%' . $filters['receipt_number'] . '%';
        }

        // Ticket number search
        if (!empty($filters['ticket_number'])) {
            $where[] = "c.ticket_number LIKE :ticket_number";
            $params[':ticket_number'] = '%' . $filters['ticket_number'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    p.*,
                    c.ticket_number,
                    c.apprehension_datetime as citation_date,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.license_number,
                    u.full_name as collector_name,
                    r.print_count,
                    r.status as receipt_status
                FROM payments p
                LEFT JOIN citations c ON p.citation_id = c.citation_id
                LEFT JOIN users u ON p.collected_by = u.user_id
                LEFT JOIN receipts r ON p.payment_id = r.payment_id
                $whereClause
                ORDER BY p.payment_date DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        // Bind filter parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        // Bind limit and offset
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment by ID with complete details
     *
     * @param int $paymentId Payment ID
     * @return array|false Payment record with citation and collector details
     */
    public function getPaymentById($paymentId) {
        $sql = "SELECT
                    p.*,
                    c.ticket_number,
                    c.apprehension_datetime as citation_date,
                    c.place_of_apprehension as location,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.license_number,
                    CONCAT(c.barangay, ', ', c.municipality, ', ', c.province) as driver_address,
                    c.plate_mv_engine_chassis_no as plate_number,
                    c.vehicle_description,
                    u.full_name as collector_name,
                    u.username as collector_username,
                    r.receipt_id,
                    r.print_count,
                    r.printed_at,
                    r.status as receipt_status
                FROM payments p
                LEFT JOIN citations c ON p.citation_id = c.citation_id
                LEFT JOIN users u ON p.collected_by = u.user_id
                LEFT JOIN receipts r ON p.payment_id = r.payment_id
                WHERE p.payment_id = :payment_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':payment_id' => $paymentId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search payments by multiple criteria
     *
     * @param string $searchTerm Search term (ticket number, receipt number, driver name)
     * @param int $limit Results limit
     * @return array Matching payments
     */
    public function searchPayments($searchTerm, $limit = 20) {
        $sql = "SELECT
                    p.*,
                    c.ticket_number,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    c.license_number,
                    u.full_name as collector_name
                FROM payments p
                LEFT JOIN citations c ON p.citation_id = c.citation_id
                LEFT JOIN users u ON p.collected_by = u.user_id
                WHERE c.ticket_number LIKE :search
                   OR p.receipt_number LIKE :search
                   OR CONCAT(c.first_name, ' ', c.last_name) LIKE :search
                   OR c.license_number LIKE :search
                ORDER BY p.payment_date DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':search', '%' . $searchTerm . '%');
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payments by date range
     *
     * @param string $dateFrom Start date (YYYY-MM-DD)
     * @param string $dateTo End date (YYYY-MM-DD)
     * @return array Payments within date range
     */
    public function getPaymentsByDateRange($dateFrom, $dateTo) {
        return $this->getAllPayments([
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ], 1000, 0);
    }

    /**
     * Get payments by cashier/collector
     *
     * @param int $userId User ID of cashier
     * @param int $limit Results limit
     * @return array Payments collected by user
     */
    public function getPaymentsByCashier($userId, $limit = 100) {
        return $this->getAllPayments([
            'collected_by' => $userId
        ], $limit, 0);
    }

    /**
     * Get payments by payment method
     *
     * @param string $method Payment method (cash, check, online, etc.)
     * @param int $limit Results limit
     * @return array Payments using specified method
     */
    public function getPaymentsByMethod($method, $limit = 100) {
        return $this->getAllPayments([
            'payment_method' => $method
        ], $limit, 0);
    }

    /**
     * Get total payment count
     *
     * @param array $filters Optional filters
     * @return int Total count
     */
    public function getPaymentCount($filters = []) {
        $where = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "p.payment_date >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = "p.payment_date <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['status'])) {
            $where[] = "p.status = :status";
            $params[':status'] = $filters['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT COUNT(*) as total FROM payments p $whereClause";
        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $result['total'];
    }
}
