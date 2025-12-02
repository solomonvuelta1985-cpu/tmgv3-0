<?php
/**
 * RefundHandler Class
 *
 * Handles payment refund operations and citation status reversion
 *
 * @package TrafficCitationSystem
 * @subpackage Services\Payment
 */

class RefundHandler {
    private $pdo;
    private $auditService;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;

        // Initialize AuditService for logging
        require_once dirname(__DIR__) . '/AuditService.php';
        $this->auditService = new AuditService($pdo);
    }

    /**
     * Refund a payment and revert citation status
     *
     * @param int $paymentId Payment ID
     * @param string $reason Refund reason
     * @param int $userId User ID performing the refund
     * @return array Result with success status and message
     */
    public function refundPayment($paymentId, $reason, $userId) {
        try {
            $this->pdo->beginTransaction();

            // Get payment details including citation_id
            $sql = "SELECT citation_id, amount_paid, payment_method
                    FROM payments
                    WHERE payment_id = :payment_id
                    AND status = 'completed'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':payment_id' => $paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new Exception('Payment not found or cannot be refunded');
            }

            // Update payment status
            $sql = "UPDATE payments
                    SET status = 'refunded',
                        notes = CONCAT(COALESCE(notes, ''), '\n[REFUNDED] ', :reason),
                        updated_at = NOW()
                    WHERE payment_id = :payment_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':payment_id' => $paymentId,
                ':reason' => $reason
            ]);

            // Revert citation status to 'pending'
            $this->updateCitationStatus(
                $payment['citation_id'],
                'pending',
                $userId,
                'Payment refunded: ' . $reason
            );

            // Log refund action
            $this->auditService->logPaymentAction(
                $paymentId,
                'refunded',
                ['status' => 'completed'],
                ['status' => 'refunded', 'reason' => $reason],
                $userId
            );

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Payment refunded successfully. Citation status reverted to pending.'
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => 'Error refunding payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update citation status with audit logging
     *
     * @param int $citationId Citation ID
     * @param string $newStatus New status
     * @param int|null $userId User ID making the change
     * @param string|null $reason Reason for status change
     * @return bool Success status
     * @throws Exception If update fails
     */
    public function updateCitationStatus($citationId, $newStatus, $userId = null, $reason = null) {
        try {
            // Get current status
            $sql = "SELECT status FROM citations WHERE citation_id = :citation_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':citation_id' => $citationId]);
            $citation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$citation) {
                throw new Exception('Citation not found');
            }

            $oldStatus = $citation['status'];

            // Don't update if status is the same
            if ($oldStatus === $newStatus) {
                return true;
            }

            // Update citation status and payment_date
            if ($newStatus === 'paid') {
                $sql = "UPDATE citations
                        SET status = :status,
                            payment_date = NOW()
                        WHERE citation_id = :citation_id";
            } else {
                $sql = "UPDATE citations
                        SET status = :status,
                            payment_date = NULL
                        WHERE citation_id = :citation_id";
            }

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':status' => $newStatus,
                ':citation_id' => $citationId
            ]);

            // Log status change to audit trail
            if ($result) {
                $this->auditService->logCitationStatusChange(
                    $citationId,
                    $oldStatus,
                    $newStatus,
                    $userId,
                    $reason
                );
            }

            return $result;

        } catch (Exception $e) {
            error_log("Error updating citation status: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate refund eligibility
     *
     * @param int $paymentId Payment ID
     * @return array Validation result
     */
    public function validateRefundEligibility($paymentId) {
        $sql = "SELECT payment_id, status, amount_paid, payment_date, citation_id
                FROM payments
                WHERE payment_id = :payment_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':payment_id' => $paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            return [
                'eligible' => false,
                'message' => 'Payment not found'
            ];
        }

        if ($payment['status'] !== 'completed') {
            return [
                'eligible' => false,
                'message' => 'Only completed payments can be refunded. Current status: ' . $payment['status']
            ];
        }

        return [
            'eligible' => true,
            'message' => 'Payment is eligible for refund',
            'payment' => $payment
        ];
    }

    /**
     * Get refund history for a citation
     *
     * @param int $citationId Citation ID
     * @return array Refunded payments
     */
    public function getRefundHistory($citationId) {
        $sql = "SELECT
                    p.*,
                    u.full_name as refunded_by_name
                FROM payments p
                LEFT JOIN users u ON p.collected_by = u.user_id
                WHERE p.citation_id = :citation_id
                  AND p.status = 'refunded'
                ORDER BY p.updated_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':citation_id' => $citationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
