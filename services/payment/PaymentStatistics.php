<?php
/**
 * PaymentStatistics Class
 *
 * Handles payment statistics and analytics
 *
 * @package TrafficCitationSystem
 * @subpackage Services\Payment
 */

class PaymentStatistics {
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
     * Get payment statistics with optional date range
     *
     * @param array $dateRange Optional date range with 'from' and 'to' keys (YYYY-MM-DD)
     * @return array Statistics array
     */
    public function getPaymentStatistics($dateRange = []) {
        $where = "WHERE p.status = 'completed'";
        $params = [];

        if (!empty($dateRange['from'])) {
            $where .= " AND p.payment_date >= :date_from";
            $params[':date_from'] = $dateRange['from'] . ' 00:00:00';
        }
        if (!empty($dateRange['to'])) {
            $where .= " AND p.payment_date <= :date_to";
            $params[':date_to'] = $dateRange['to'] . ' 23:59:59';
        }

        $sql = "SELECT
                    COUNT(*) as total_payments,
                    SUM(amount_paid) as total_amount,
                    AVG(amount_paid) as average_payment,
                    MIN(amount_paid) as min_payment,
                    MAX(amount_paid) as max_payment,
                    COUNT(DISTINCT collected_by) as unique_collectors
                FROM payments p
                $where";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get daily payment totals for a date range
     *
     * @param string $dateFrom Start date (YYYY-MM-DD)
     * @param string $dateTo End date (YYYY-MM-DD)
     * @return array Daily totals
     */
    public function getDailyTotals($dateFrom, $dateTo) {
        $sql = "SELECT
                    DATE(payment_date) as payment_day,
                    COUNT(*) as total_count,
                    SUM(amount_paid) as total_amount
                FROM payments
                WHERE status = 'completed'
                  AND payment_date >= :date_from
                  AND payment_date <= :date_to
                GROUP BY DATE(payment_date)
                ORDER BY payment_day ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':date_from' => $dateFrom . ' 00:00:00',
            ':date_to' => $dateTo . ' 23:59:59'
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get monthly payment totals for a year
     *
     * @param int $year Year (YYYY)
     * @return array Monthly totals
     */
    public function getMonthlyTotals($year) {
        $sql = "SELECT
                    MONTH(payment_date) as payment_month,
                    MONTHNAME(payment_date) as month_name,
                    COUNT(*) as total_count,
                    SUM(amount_paid) as total_amount
                FROM payments
                WHERE status = 'completed'
                  AND YEAR(payment_date) = :year
                GROUP BY MONTH(payment_date), MONTHNAME(payment_date)
                ORDER BY payment_month ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':year' => $year]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get cashier performance statistics
     *
     * @param array $dateRange Optional date range
     * @param int $limit Top N cashiers
     * @return array Cashier performance data
     */
    public function getCashierPerformance($dateRange = [], $limit = 10) {
        $where = "WHERE p.status = 'completed'";
        $params = [];

        if (!empty($dateRange['from'])) {
            $where .= " AND p.payment_date >= :date_from";
            $params[':date_from'] = $dateRange['from'] . ' 00:00:00';
        }
        if (!empty($dateRange['to'])) {
            $where .= " AND p.payment_date <= :date_to";
            $params[':date_to'] = $dateRange['to'] . ' 23:59:59';
        }

        $sql = "SELECT
                    u.user_id,
                    u.full_name,
                    u.username,
                    COUNT(*) as total_payments,
                    SUM(p.amount_paid) as total_amount,
                    AVG(p.amount_paid) as average_payment,
                    MIN(p.payment_date) as first_payment,
                    MAX(p.payment_date) as last_payment
                FROM payments p
                INNER JOIN users u ON p.collected_by = u.user_id
                $where
                GROUP BY u.user_id, u.full_name, u.username
                ORDER BY total_amount DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment method breakdown
     *
     * @param array $dateRange Optional date range
     * @return array Payment method statistics
     */
    public function getPaymentMethodBreakdown($dateRange = []) {
        $where = "WHERE status = 'completed'";
        $params = [];

        if (!empty($dateRange['from'])) {
            $where .= " AND payment_date >= :date_from";
            $params[':date_from'] = $dateRange['from'] . ' 00:00:00';
        }
        if (!empty($dateRange['to'])) {
            $where .= " AND payment_date <= :date_to";
            $params[':date_to'] = $dateRange['to'] . ' 23:59:59';
        }

        $sql = "SELECT
                    payment_method,
                    COUNT(*) as total_count,
                    SUM(amount_paid) as total_amount,
                    AVG(amount_paid) as average_amount
                FROM payments
                $where
                GROUP BY payment_method
                ORDER BY total_amount DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment status breakdown
     *
     * @return array Payment status counts
     */
    public function getPaymentStatusBreakdown() {
        $sql = "SELECT
                    status,
                    COUNT(*) as total_count,
                    SUM(amount_paid) as total_amount
                FROM payments
                GROUP BY status
                ORDER BY total_count DESC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get today's payment summary
     *
     * @return array Today's statistics
     */
    public function getTodaysSummary() {
        return $this->getPaymentStatistics([
            'from' => date('Y-m-d'),
            'to' => date('Y-m-d')
        ]);
    }

    /**
     * Get this month's payment summary
     *
     * @return array This month's statistics
     */
    public function getThisMonthsSummary() {
        return $this->getPaymentStatistics([
            'from' => date('Y-m-01'),
            'to' => date('Y-m-t')
        ]);
    }

    /**
     * Get this year's payment summary
     *
     * @return array This year's statistics
     */
    public function getThisYearsSummary() {
        return $this->getPaymentStatistics([
            'from' => date('Y-01-01'),
            'to' => date('Y-12-31')
        ]);
    }
}
