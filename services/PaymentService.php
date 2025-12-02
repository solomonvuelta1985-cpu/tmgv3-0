<?php
/**
 * PaymentService Class (Facade Pattern)
 *
 * Coordinates between specialized payment services.
 * Maintains backward compatibility with existing code by delegating
 * method calls to appropriate specialized services.
 *
 * @package TrafficCitationSystem
 * @subpackage Services
 */

class PaymentService {
    private $pdo;
    private $tableName = 'payments';
    private $auditService;

    // Specialized services
    private $processor;
    private $validator;
    private $query;
    private $refundHandler;
    private $statistics;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;

        // Initialize AuditService for logging
        require_once __DIR__ . '/AuditService.php';
        $this->auditService = new AuditService($pdo);

        // Initialize specialized services
        require_once __DIR__ . '/payment/PaymentProcessor.php';
        require_once __DIR__ . '/payment/PaymentValidator.php';
        require_once __DIR__ . '/payment/PaymentQuery.php';
        require_once __DIR__ . '/payment/RefundHandler.php';
        require_once __DIR__ . '/payment/PaymentStatistics.php';

        $this->processor = new PaymentProcessor($pdo);
        $this->validator = new PaymentValidator($pdo);
        $this->query = new PaymentQuery($pdo);
        $this->refundHandler = new RefundHandler($pdo);
        $this->statistics = new PaymentStatistics($pdo);
    }

    /**
     * Record a new payment for a citation
     *
     * @param int $citationId Citation ID
     * @param float $amountPaid Amount paid
     * @param string $paymentMethod Payment method (cash, check, online, etc.)
     * @param int $collectedBy User ID of cashier/collector
     * @param array $additionalData Additional payment data (check details, notes, receipt_number, etc.)
     * @return array Result with success status and payment/receipt data
     */
    public function recordPayment($citationId, $amountPaid, $paymentMethod, $collectedBy, $additionalData = []) {
        return $this->processor->recordPayment($citationId, $amountPaid, $paymentMethod, $collectedBy, $additionalData);
    }

    /**
     * Validate payment before recording
     *
     * @param int $citationId Citation ID
     * @param float $amountPaid Amount to be paid
     * @return array Validation result
     */
    public function validatePayment($citationId, $amountPaid) {
        return $this->validator->validatePayment($citationId, $amountPaid);
    }

    /**
     * Get payment history for a citation
     *
     * @param int $citationId Citation ID
     * @return array Payment history records
     */
    public function getPaymentHistory($citationId) {
        return $this->query->getPaymentHistory($citationId);
    }

    /**
     * Get all payments with optional filters
     *
     * @param array $filters Filter criteria
     * @param int $limit Results limit
     * @param int $offset Results offset
     * @return array Payments list
     */
    public function getAllPayments($filters = [], $limit = 50, $offset = 0) {
        return $this->query->getAllPayments($filters, $limit, $offset);
    }

    /**
     * Get payment by ID
     *
     * @param int $paymentId Payment ID
     * @return array|false Payment record
     */
    public function getPaymentById($paymentId) {
        return $this->query->getPaymentById($paymentId);
    }

    /**
     * Refund a payment and revert citation status
     *
     * @param int $paymentId Payment ID
     * @param string $reason Refund reason
     * @param int $userId User ID performing the refund
     * @return array Result
     */
    public function refundPayment($paymentId, $reason, $userId) {
        return $this->refundHandler->refundPayment($paymentId, $reason, $userId);
    }

    /**
     * Get payment statistics
     *
     * @param array $dateRange Optional date range
     * @return array Statistics
     */
    public function getPaymentStatistics($dateRange = []) {
        return $this->statistics->getPaymentStatistics($dateRange);
    }

    /**
     * Manually change citation status (for admin use)
     *
     * @param int $citationId Citation ID
     * @param string $newStatus New status
     * @param int $userId User ID making the change
     * @param string|null $reason Reason for status change
     * @return array Result
     */
    public function changeCitationStatus($citationId, $newStatus, $userId, $reason = null) {
        return $this->processor->changeCitationStatus($citationId, $newStatus, $userId, $reason);
    }

    /**
     * Finalize payment after successful print confirmation
     *
     * @param int $paymentId Payment ID
     * @param int $userId User ID confirming the print
     * @return array Result
     */
    public function finalizePayment($paymentId, $userId) {
        return $this->processor->finalizePayment($paymentId, $userId);
    }

    /**
     * Update OR number for a pending_print payment
     *
     * @param int $paymentId Payment ID
     * @param string $newOrNumber New OR number
     * @param int $userId User ID making the change
     * @param string $reason Reason for OR change
     * @return array Result
     */
    public function updateOrNumber($paymentId, $newOrNumber, $userId, $reason) {
        return $this->processor->updateOrNumber($paymentId, $newOrNumber, $userId, $reason);
    }

    /**
     * Void a pending_print payment
     *
     * @param int $paymentId Payment ID
     * @param int $userId User ID voiding the payment
     * @param string $reason Reason for voiding
     * @return array Result
     */
    public function voidPayment($paymentId, $userId, $reason) {
        return $this->processor->voidPayment($paymentId, $userId, $reason);
    }

    // ============================================
    // ADDITIONAL CONVENIENCE METHODS
    // ============================================

    /**
     * Search payments by term
     *
     * @param string $searchTerm Search term
     * @param int $limit Results limit
     * @return array Matching payments
     */
    public function searchPayments($searchTerm, $limit = 20) {
        return $this->query->searchPayments($searchTerm, $limit);
    }

    /**
     * Get payments by date range
     *
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @return array Payments
     */
    public function getPaymentsByDateRange($dateFrom, $dateTo) {
        return $this->query->getPaymentsByDateRange($dateFrom, $dateTo);
    }

    /**
     * Get payments by cashier
     *
     * @param int $userId User ID
     * @param int $limit Results limit
     * @return array Payments
     */
    public function getPaymentsByCashier($userId, $limit = 100) {
        return $this->query->getPaymentsByCashier($userId, $limit);
    }

    /**
     * Get daily payment totals
     *
     * @param string $dateFrom Start date
     * @param string $dateTo End date
     * @return array Daily totals
     */
    public function getDailyTotals($dateFrom, $dateTo) {
        return $this->statistics->getDailyTotals($dateFrom, $dateTo);
    }

    /**
     * Get monthly payment totals
     *
     * @param int $year Year
     * @return array Monthly totals
     */
    public function getMonthlyTotals($year) {
        return $this->statistics->getMonthlyTotals($year);
    }

    /**
     * Get cashier performance
     *
     * @param array $dateRange Optional date range
     * @param int $limit Top N cashiers
     * @return array Cashier performance data
     */
    public function getCashierPerformance($dateRange = [], $limit = 10) {
        return $this->statistics->getCashierPerformance($dateRange, $limit);
    }

    /**
     * Get payment method breakdown
     *
     * @param array $dateRange Optional date range
     * @return array Payment method statistics
     */
    public function getPaymentMethodBreakdown($dateRange = []) {
        return $this->statistics->getPaymentMethodBreakdown($dateRange);
    }

    /**
     * Get today's payment summary
     *
     * @return array Today's statistics
     */
    public function getTodaysSummary() {
        return $this->statistics->getTodaysSummary();
    }

    /**
     * Get this month's payment summary
     *
     * @return array This month's statistics
     */
    public function getThisMonthsSummary() {
        return $this->statistics->getThisMonthsSummary();
    }

    /**
     * Get payment count
     *
     * @param array $filters Optional filters
     * @return int Total count
     */
    public function getPaymentCount($filters = []) {
        return $this->query->getPaymentCount($filters);
    }

    /**
     * Validate refund eligibility
     *
     * @param int $paymentId Payment ID
     * @return array Validation result
     */
    public function validateRefundEligibility($paymentId) {
        return $this->refundHandler->validateRefundEligibility($paymentId);
    }

    /**
     * Get refund history for a citation
     *
     * @param int $citationId Citation ID
     * @return array Refunded payments
     */
    public function getRefundHistory($citationId) {
        return $this->refundHandler->getRefundHistory($citationId);
    }

    /**
     * Validate payment amount
     *
     * @param float $amount Amount
     * @return array Validation result
     */
    public function validatePaymentAmount($amount) {
        return $this->validator->validatePaymentAmount($amount);
    }

    /**
     * Check for existing active payments
     *
     * @param int $citationId Citation ID
     * @return array Result
     */
    public function checkExistingPayments($citationId) {
        return $this->validator->checkExistingPayments($citationId);
    }
}
