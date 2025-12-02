<?php
/**
 * Receipt PDF Generator
 * Generates a 100×200 mm PDF receipt for payment
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require authentication and check session timeout
require_login();
check_session_timeout();

// Only cashiers and admins can view receipts
if (!can_process_payment()) {
    http_response_code(403);
    die('ACCESS DENIED');
}

// Get receipt number from query string
$receipt_number = filter_input(INPUT_GET, 'receipt', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$receipt_number) {
    http_response_code(400);
    die('INVALID RECEIPT NUMBER');
}

// Helper: Format date
function fmtDate(string $d): string {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $d);
    if (!$dt) $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? strtoupper($dt->format('m/d/Y')) : strtoupper($d);
}

// Helper: Convert number to words
function convertNumber(int $num): string {
    static $ones = ['ZERO','ONE','TWO','THREE','FOUR','FIVE','SIX','SEVEN','EIGHT','NINE','TEN','ELEVEN','TWELVE','THIRTEEN','FOURTEEN','FIFTEEN','SIXTEEN','SEVENTEEN','EIGHTEEN','NINETEEN'];
    static $tens = [2=>'TWENTY',3=>'THIRTY',4=>'FORTY',5=>'FIFTY',6=>'SIXTY',7=>'SEVENTY',8=>'EIGHTY',9=>'NINETY'];

    if ($num < 20) return $ones[$num];
    if ($num < 100) {
        $t = intdiv($num,10); $r = $num % 10;
        return $tens[$t] . ($r ? ' '.$ones[$r] : '');
    }
    if ($num < 1000) {
        $h = intdiv($num,100); $r = $num % 100;
        return $ones[$h].' HUNDRED'.($r ? ' '.convertNumber($r):'');
    }
    foreach ([1000000000=>'BILLION',1000000=>'MILLION',1000=>'THOUSAND'] as $div=>$label) {
        if ($num >= $div) {
            $cnt = intdiv($num,$div);
            $rem = $num % $div;
            return convertNumber($cnt).' '.$label.($rem ? ' '.convertNumber($rem):'');
        }
    }
    return '';
}

try {
    $pdo = getPDO();

    // Check if database connection failed
    if ($pdo === null) {
        throw new Exception("DATABASE CONNECTION FAILED - MYSQL NOT AVAILABLE");
    }

    // Fetch payment record with citation info
    $stmt = $pdo->prepare("
        SELECT
            p.payment_id,
            p.citation_id,
            p.receipt_number,
            p.amount_paid,
            p.payment_method,
            p.payment_date,
            p.reference_number,
            c.ticket_number,
            c.first_name,
            c.last_name,
            c.total_fine
        FROM payments p
        JOIN citations c ON p.citation_id = c.citation_id
        WHERE p.receipt_number = ?
    ");
    $stmt->execute([$receipt_number]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception("RECEIPT NOT FOUND");
    }

    // Get payor name
    $payor = strtoupper(trim($payment['first_name'] . ' ' . $payment['last_name']));

    // Fetch violations + fines
    $violStmt = $pdo->prepare("
        SELECT
            UPPER(vt.violation_type) AS violation_type,
            COALESCE(
                CASE v.offense_count
                    WHEN 1 THEN vt.fine_amount_1
                    WHEN 2 THEN vt.fine_amount_2
                    WHEN 3 THEN vt.fine_amount_3
                    ELSE vt.fine_amount_1
                END, vt.fine_amount_1
            ) AS fine
        FROM violations v
        JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
        WHERE v.citation_id = ?
    ");
    $violStmt->execute([$payment['citation_id']]);
    $rows = $violStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        throw new Exception("NO VIOLATIONS FOUND");
    }

} catch (Exception $e) {
    http_response_code(500);
    $errorMessage = htmlspecialchars($e->getMessage());
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                background-color: #f8f9fa;
                padding: 20px;
            }
            .error-container {
                text-align: center;
                max-width: 500px;
            }
            .error-icon {
                font-size: 4rem;
                color: #dc3545;
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="mb-3">Receipt Error</h2>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo $errorMessage; ?>
            </div>
            <?php if (strpos($errorMessage, 'DATABASE CONNECTION FAILED') !== false): ?>
                <p class="text-muted">
                    The database server is not responding. Please ensure MySQL is running and try again.
                </p>
            <?php endif; ?>
        </div>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    </body>
    </html>
    <?php
    exit;
}

// Build violation lines & calculate total
$violation_lines = [];
$total = 0.0;
foreach ($rows as $r) {
    $fine = (float)$r['fine'];
    $total += $fine;
    $violation_lines[] = $r['violation_type'];
}

// Amount in words
$integer  = (int)floor($total);
$centavos = (int)round(($total - $integer) * 100);
$words    = convertNumber($integer) . ' PESOS';
if ($centavos) $words .= ' AND ' . convertNumber($centavos) . ' CENTAVOS';
$amount_in_words = $words . ' ONLY';

// Initialize TCPDF (100×200 mm)
$pdf = new TCPDF('P', 'mm', [100, 200], true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(0, 0, 0);
$pdf->AddPage();

// Check if background image exists
$bg_image = __DIR__ . '/../resibo.png';
if (file_exists($bg_image)) {
    $pdf->Image($bg_image, 0, 0, 100, 200);
}

// Set font
$pdf->SetFont('dejavusans', '', 8);

// Print Receipt Number
$pdf->SetXY(6.6, 44.4);
$pdf->Cell(30, 4, $receipt_number, 0, 0, 'L', false);

// Print DATE
$pdf->SetXY(6.6, 49.4);
$pdf->Cell(30, 4, fmtDate($payment['payment_date']), 0, 0, 'L', false);

// Print PAYOR
$pdf->SetXY(7.1, 65.0);
$pdf->Cell(85, 6, $payor, 0, 0, 'L', false);

// Print violations + fines
$startX = 5.1;
$amountX = 62.0;
$startY = 79.2;
$rowH = 8.0;

foreach ($violation_lines as $i => $label) {
    if ($i >= 10) break;
    $y = $startY + $i * $rowH;
    $pdf->SetXY($startX, $y);
    $pdf->Cell(60, 6, $label, 0, 0, 'L', false);
    $amt = (float)$rows[$i]['fine'];
    $pdf->SetXY($amountX, $y);
    $pdf->Cell(20, 6, '₱' . number_format($amt, 2), 0, 0, 'R', false);
}

// Print TOTAL
$pdf->SetXY(10.6, 128.2);
$pdf->Cell(60, 6, 'TOTAL:', 0, 0, 'L', false);
$pdf->SetXY(61.9, 128.7);
$pdf->Cell(20, 6, '₱' . number_format($total, 2), 0, 0, 'R', false);

// Amount in Words
$pdf->SetXY(7.1, 140.7);
$pdf->MultiCell(85, 4, $amount_in_words, 0, 'L', false);

// Mark payment method checkbox
$payment_method = strtoupper($payment['payment_method']);
if ($payment_method === 'CASH') {
    $pdf->SetXY(20.9, 150.6);
    $pdf->Cell(4, 4, 'X', 0, 0, 'L', false);
} elseif (in_array($payment_method, ['GCASH', 'PAYMAYA', 'BANK_TRANSFER', 'ONLINE'])) {
    // Mark CHECK/ONLINE checkbox
    $pdf->SetXY(46.5, 150.6);
    $pdf->Cell(4, 4, 'X', 0, 0, 'L', false);
}

// Output PDF
$filename = 'Receipt_' . $receipt_number . '.pdf';
$pdf->Output($filename, 'I');
exit;
