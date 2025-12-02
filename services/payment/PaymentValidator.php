<?php
/**
 * PaymentValidator Class
 *
 * Handles all payment validation logic
 *
 * @package TrafficCitationSystem
 * @subpackage Services\Payment
 */

class PaymentValidator {
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
     * Validate payment before recording
     *
     * @param int $citationId Citation ID
     * @param float $amountPaid Amount to be paid
     * @return array Validation result with 'valid', 'message', and optionally 'citation'
     */
    public function validatePayment($citationId, $amountPaid) {
        // 1. Check if citation exists
        $sql = "SELECT citation_id, status, total_fine, payment_date
                FROM citations
                WHERE citation_id = :citation_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':citation_id' => $citationId]);
        $citation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$citation) {
            return [
                'valid' => false,
                'message' => 'Citation not found'
            ];
        }

        // 2. Check if citation is already paid
        if ($citation['status'] === 'paid') {
            return [
                'valid' => false,
                'message' => 'Citation has already been paid'
            ];
        }

        // 3. Check if citation is voided or dismissed
        if (in_array($citation['status'], ['void', 'dismissed'])) {
            return [
                'valid' => false,
                'message' => 'Cannot process payment for ' . $citation['status'] . ' citation'
            ];
        }

        // 4. Validate amount (must match total fine)
        if ($amountPaid != $citation['total_fine']) {
            return [
                'valid' => false,
                'message' => 'Payment amount must match total fine of ₱' . number_format($citation['total_fine'], 2)
            ];
        }

        // 5. Check for active/completed payments (excluding refunded/cancelled)
        // Allow payment if citation is 'pending' even if there are refunded payments
        $sql = "SELECT payment_id, status FROM payments
                WHERE citation_id = :citation_id
                AND status IN ('completed', 'pending')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':citation_id' => $citationId]);
        $existingPayment = $stmt->fetch();

        if ($existingPayment) {
            return [
                'valid' => false,
                'message' => 'Active payment already exists for this citation. Cannot process duplicate payment.'
            ];
        }

        // Additional check: Verify no finalized payments exist
        // Note: pending_print and voided payments are allowed (they can be resumed or are cancelled)
        if ($citation['status'] === 'pending') {
            $sql = "SELECT COUNT(*) as active_payments FROM payments
                    WHERE citation_id = :citation_id
                    AND status IN ('completed', 'pending')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':citation_id' => $citationId]);
            $check = $stmt->fetch();

            if ($check['active_payments'] > 0) {
                return [
                    'valid' => false,
                    'message' => 'Cannot process payment. Previous payment must be refunded or cancelled first.'
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'Validation passed',
            'citation' => $citation
        ];
    }

    /**
     * Validate receipt/OR number format
     *
     * @param string $receiptNumber Receipt/OR number to validate
     * @return array Validation result with 'valid' and 'message'
     */
    public function validateReceiptNumberFormat($receiptNumber) {
        // Trim and convert to uppercase
        $receiptNumber = strtoupper(trim($receiptNumber));

        // OR Number format: 2-4 uppercase letters followed by 6-10 digits
        // Examples: CGVM15320501, OR123456, ABC1234567890
        $pattern = '/^[A-Z]{2,4}[0-9]{6,10}$/';

        if (!preg_match($pattern, $receiptNumber)) {
            return [
                'valid' => false,
                'message' => 'Invalid OR number format. Expected format: 2-4 letters followed by 6-10 digits (e.g., CGVM15320501)'
            ];
        }

        return [
            'valid' => true,
            'message' => 'OR number format is valid'
        ];
    }

    /**
     * Validate receipt/OR number uniqueness and format
     *
     * @param string $receiptNumber Receipt/OR number to validate
     * @return array Validation result with 'valid' and 'message'
     */
    public function validateReceiptNumber($receiptNumber) {
        try {
            // Trim and sanitize the receipt number
            $receiptNumber = strtoupper(trim($receiptNumber));

            // First, validate the format
            $formatValidation = $this->validateReceiptNumberFormat($receiptNumber);
            if (!$formatValidation['valid']) {
                return $formatValidation;
            }

            // Check if OR number already exists in payments table
            $sql = "SELECT payment_id, receipt_number, amount_paid, payment_date
                    FROM payments
                    WHERE receipt_number = :receipt_number
                    AND status != 'cancelled'";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':receipt_number' => $receiptNumber]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                return [
                    'valid' => false,
                    'message' => 'OR Number "' . $receiptNumber . '" has already been used (Payment ID: ' .
                                $existing['payment_id'] . ', Amount: ₱' . number_format($existing['amount_paid'], 2) .
                                ', Date: ' . $existing['payment_date'] . '). Please check the physical receipt and enter the correct OR number.'
                ];
            }

            return [
                'valid' => true,
                'message' => 'Receipt number is available'
            ];

        } catch (Exception $e) {
            error_log("Error validating receipt number: " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Error validating receipt number: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate payment amount
     *
     * @param float $amount Amount to validate
     * @return array Validation result
     */
    public function validatePaymentAmount($amount) {
        if (!is_numeric($amount) || $amount <= 0) {
            return [
                'valid' => false,
                'message' => 'Invalid payment amount'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Amount is valid'
        ];
    }

    /**
     * Validate citation status for payment eligibility
     *
     * @param string $status Citation status
     * @return array Validation result
     */
    public function validateCitationStatus($status) {
        $allowedStatuses = ['pending', 'contested'];

        if (!in_array($status, $allowedStatuses)) {
            return [
                'valid' => false,
                'message' => 'Citations with status "' . $status . '" cannot be paid'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Status is eligible for payment'
        ];
    }

    /**
     * Check for existing active payments
     *
     * @param int $citationId Citation ID
     * @return array Result with 'has_active' boolean and payment details if exists
     */
    public function checkExistingPayments($citationId) {
        $sql = "SELECT payment_id, status, amount_paid, payment_date
                FROM payments
                WHERE citation_id = :citation_id
                AND status IN ('completed', 'pending')
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':citation_id' => $citationId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'has_active' => (bool) $payment,
            'payment' => $payment
        ];
    }
}
