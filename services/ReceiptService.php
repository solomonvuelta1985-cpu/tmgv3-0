<?php
/**
 * ReceiptService Class
 *
 * Handles all receipt-related functionality including:
 * - Receipt PDF generation
 * - Receipt printing and reprinting
 * - Receipt cancellation/voiding
 * - Receipt verification
 *
 * @package TrafficCitationSystem
 * @subpackage Services
 */

require_once __DIR__ . '/../includes/pdf_config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class ReceiptService {
    private $pdo;
    private $tableName = 'receipts';

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get complete receipt data for PDF generation
     *
     * @param int $paymentId Payment ID
     * @return array|false Receipt data
     */
    public function getReceiptData($paymentId) {
        $sql = "SELECT
                    p.*,
                    c.ticket_number,
                    c.apprehension_datetime as citation_date,
                    c.place_of_apprehension as citation_location,
                    c.total_fine,
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
                    r.status as receipt_status,
                    r.generated_at
                FROM payments p
                LEFT JOIN citations c ON p.citation_id = c.citation_id
                LEFT JOIN users u ON p.collected_by = u.user_id
                LEFT JOIN receipts r ON p.payment_id = r.payment_id
                WHERE p.payment_id = :payment_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':payment_id' => $paymentId]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            return false;
        }

        // Get violations for this citation
        $sql = "SELECT
                    v.violation_id,
                    v.offense_count,
                    v.fine_amount,
                    vt.violation_type
                FROM violations v
                LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                WHERE v.citation_id = :citation_id
                ORDER BY v.violation_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':citation_id' => $receipt['citation_id']]);
        $receipt['violations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $receipt;
    }

    /**
     * Generate receipt PDF
     *
     * @param int $paymentId Payment ID
     * @param int $copyNumber Copy number (1=Original, 2=Duplicate, 3=Triplicate)
     * @param string $outputMode 'download', 'inline', or 'save'
     * @return mixed PDF output or file path
     */
    public function generateReceiptPDF($paymentId, $copyNumber = 1, $outputMode = 'download') {
        // Get receipt data
        $data = $this->getReceiptData($paymentId);

        if (!$data) {
            throw new Exception('Receipt data not found');
        }

        // Generate HTML from template
        $html = $this->generateReceiptHTML($data, $copyNumber);

        // Initialize Dompdf
        $dompdf = initDompdf();

        // Load HTML
        $dompdf->loadHtml($html);

        // Set paper size and orientation
        $dompdf->setPaper(PDF_PAPER_SIZE, PDF_ORIENTATION);

        // Render PDF
        $dompdf->render();

        // Update print tracking
        if ($outputMode !== 'preview') {
            $this->updatePrintTracking($data['receipt_id'], $_SESSION['user_id'] ?? null);
        }

        // Output based on mode
        $filename = 'Receipt_' . $data['receipt_number'] . '.pdf';

        switch ($outputMode) {
            case 'download':
                return $dompdf->stream($filename, ['Attachment' => 1]);

            case 'inline':
                return $dompdf->stream($filename, ['Attachment' => 0]);

            case 'save':
                $directory = __DIR__ . '/../receipts/pdf/';
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                $filepath = $directory . $filename;
                file_put_contents($filepath, $dompdf->output());
                return $filepath;

            case 'preview':
                return $dompdf->output();

            default:
                return $dompdf->stream($filename, ['Attachment' => 1]);
        }
    }

    /**
     * Generate HTML for receipt
     *
     * @param array $data Receipt data
     * @param int $copyNumber Copy number
     * @return string HTML content
     */
    private function generateReceiptHTML($data, $copyNumber = 1) {
        // Load receipt template
        ob_start();
        include __DIR__ . '/../templates/receipts/official-receipt.php';
        $html = ob_get_clean();

        return $html;
    }

    /**
     * Update print tracking for receipt
     *
     * @param int $receiptId Receipt ID
     * @param int $userId User ID who printed
     * @return bool Success status
     */
    private function updatePrintTracking($receiptId, $userId = null) {
        $sql = "UPDATE receipts
                SET print_count = print_count + 1,
                    last_printed_by = :user_id,
                    last_printed_at = NOW()
                WHERE receipt_id = :receipt_id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':receipt_id' => $receiptId,
            ':user_id' => $userId
        ]);
    }

    /**
     * Reprint receipt
     *
     * @param int $receiptId Receipt ID
     * @param int $userId User ID requesting reprint
     * @param string $outputMode Output mode
     * @return mixed PDF output
     */
    public function reprintReceipt($receiptId, $userId, $outputMode = 'download') {
        // Get payment ID from receipt
        $sql = "SELECT payment_id, status FROM receipts WHERE receipt_id = :receipt_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':receipt_id' => $receiptId]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            throw new Exception('Receipt not found');
        }

        if ($receipt['status'] !== 'active') {
            throw new Exception('Cannot reprint cancelled or void receipt');
        }

        // Generate PDF (copy number 2 for reprints to indicate "DUPLICATE")
        return $this->generateReceiptPDF($receipt['payment_id'], 2, $outputMode);
    }

    /**
     * Cancel/void a receipt
     *
     * @param int $receiptId Receipt ID
     * @param string $reason Cancellation reason
     * @param int $userId User ID performing cancellation
     * @return array Result
     */
    public function cancelReceipt($receiptId, $reason, $userId) {
        try {
            $sql = "UPDATE receipts
                    SET status = 'cancelled',
                        cancellation_reason = :reason,
                        cancelled_by = :user_id,
                        cancelled_at = NOW()
                    WHERE receipt_id = :receipt_id
                    AND status = 'active'";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':receipt_id' => $receiptId,
                ':reason' => $reason,
                ':user_id' => $userId
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Receipt not found or already cancelled');
            }

            return [
                'success' => true,
                'message' => 'Receipt cancelled successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error cancelling receipt: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify receipt authenticity
     *
     * @param string $receiptNumber Receipt number
     * @return array Verification result
     */
    public function verifyReceipt($receiptNumber) {
        $sql = "SELECT
                    r.*,
                    p.citation_id,
                    p.amount_paid,
                    p.payment_date,
                    p.payment_method,
                    c.ticket_number,
                    CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                    u.full_name as collector_name
                FROM receipts r
                LEFT JOIN payments p ON r.payment_id = p.payment_id
                LEFT JOIN citations c ON p.citation_id = c.citation_id
                LEFT JOIN users u ON p.collected_by = u.user_id
                WHERE r.receipt_number = :receipt_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':receipt_number' => $receiptNumber]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt) {
            return [
                'valid' => false,
                'message' => 'Receipt not found'
            ];
        }

        if ($receipt['status'] !== 'active') {
            return [
                'valid' => false,
                'message' => 'Receipt has been ' . $receipt['status'],
                'receipt' => $receipt
            ];
        }

        return [
            'valid' => true,
            'message' => 'Receipt is valid',
            'receipt' => $receipt
        ];
    }

    /**
     * Get receipt by receipt number
     *
     * @param string $receiptNumber Receipt number
     * @return array|false Receipt data
     */
    public function getReceiptByNumber($receiptNumber) {
        $sql = "SELECT
                    r.*,
                    p.payment_id
                FROM receipts r
                LEFT JOIN payments p ON r.payment_id = p.payment_id
                WHERE r.receipt_number = :receipt_number";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':receipt_number' => $receiptNumber]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Generate QR code for receipt verification
     *
     * @param string $receiptNumber Receipt number
     * @return string QR code URL or base64 data
     */
    public function generateQRCode($receiptNumber) {
        // Using Google Charts API for QR code generation (simple solution)
        $verifyUrl = RECEIPT_QR_CODE_VERIFY_URL . urlencode($receiptNumber);
        $qrCodeUrl = 'https://chart.googleapis.com/chart?chs=' . RECEIPT_QR_CODE_SIZE . 'x' . RECEIPT_QR_CODE_SIZE . '&cht=qr&chl=' . urlencode($verifyUrl);

        return $qrCodeUrl;
    }

    /**
     * Get receipt statistics
     *
     * @param array $dateRange Optional date range
     * @return array Statistics
     */
    public function getReceiptStatistics($dateRange = []) {
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($dateRange['from'])) {
            $where .= " AND r.generated_at >= :date_from";
            $params[':date_from'] = $dateRange['from'] . ' 00:00:00';
        }
        if (!empty($dateRange['to'])) {
            $where .= " AND r.generated_at <= :date_to";
            $params[':date_to'] = $dateRange['to'] . ' 23:59:59';
        }

        $sql = "SELECT
                    COUNT(*) as total_receipts,
                    SUM(CASE WHEN r.status = 'active' THEN 1 ELSE 0 END) as active_receipts,
                    SUM(CASE WHEN r.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_receipts,
                    SUM(r.print_count) as total_prints,
                    AVG(r.print_count) as avg_prints_per_receipt,
                    MAX(r.print_count) as max_prints
                FROM receipts r
                $where";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
