<?php
/**
 * Official Receipt Template
 *
 * This template generates the HTML for official receipt PDF
 * Uses data passed from ReceiptService
 *
 * Available variables:
 * - $data: Complete receipt data including payment, citation, driver, violations
 * - $copyNumber: Copy number (1=Original, 2=Duplicate, 3=Triplicate)
 *
 * @package TrafficCitationSystem
 * @subpackage Templates
 */

// Determine copy designation
$copyDesignation = RECEIPT_COPY_NAMES[$copyNumber] ?? 'ORIGINAL COPY';
$copyColor = RECEIPT_COPY_COLORS[$copyNumber] ?? '#000000';

// Format data
$receiptNumber = htmlspecialchars($data['receipt_number']);
$ticketNumber = htmlspecialchars($data['ticket_number']);
$paymentDate = formatReceiptDate($data['payment_date']);
$driverName = htmlspecialchars($data['driver_name']);
$licenseNumber = htmlspecialchars($data['license_number'] ?? 'N/A');
$plateNumber = htmlspecialchars($data['plate_number'] ?? 'N/A');
$vehicleInfo = htmlspecialchars($data['vehicle_description'] ?? 'N/A');
$totalFine = formatMoney($data['total_fine']);
$amountPaid = formatMoney($data['amount_paid']);
$paymentMethod = ucwords(str_replace('_', ' ', $data['payment_method']));
$collectorName = htmlspecialchars($data['collector_name']);
$change = formatMoney($data['amount_paid'] - $data['total_fine']);

// Generate QR code URL
$qrCodeUrl = '';
if (RECEIPT_SHOW_QR_CODE && class_exists('ReceiptService')) {
    $receiptService = new ReceiptService($GLOBALS['pdo'] ?? null);
    $qrCodeUrl = $receiptService->generateQRCode($receiptNumber);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipt - <?= $receiptNumber ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            padding: 20px;
        }

        .receipt-container {
            max-width: 700px;
            margin: 0 auto;
            border: 2px solid #000;
            padding: 20px;
            position: relative;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80pt;
            font-weight: bold;
            color: rgba(0, 0, 0, 0.05);
            z-index: -1;
            white-space: nowrap;
        }

        /* Header */
        .receipt-header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo {
            max-width: 80px;
            height: auto;
            margin-bottom: 10px;
        }

        .lgu-name {
            font-size: 16pt;
            font-weight: bold;
            margin: 5px 0;
        }

        .lgu-info {
            font-size: 10pt;
            color: #333;
            margin: 3px 0;
        }

        .receipt-title {
            font-size: 18pt;
            font-weight: bold;
            margin: 15px 0 10px 0;
            letter-spacing: 2px;
        }

        .receipt-subtitle {
            font-size: 9pt;
            font-style: italic;
            color: #666;
        }

        /* Receipt Info Section */
        .receipt-info {
            margin: 20px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        .info-row {
            display: table;
            width: 100%;
            margin: 5px 0;
        }

        .info-label {
            display: table-cell;
            width: 40%;
            font-weight: bold;
            padding: 3px 0;
        }

        .info-value {
            display: table-cell;
            width: 60%;
            padding: 3px 0;
        }

        /* Section Headers */
        .section-header {
            font-size: 12pt;
            font-weight: bold;
            border-bottom: 2px solid #000;
            padding: 8px 0 5px 0;
            margin: 15px 0 10px 0;
        }

        /* Violations Table */
        table.violations-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        table.violations-table th {
            background-color: #333;
            color: #fff;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #000;
        }

        table.violations-table td {
            padding: 6px 8px;
            border: 1px solid #ccc;
        }

        table.violations-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Payment Summary */
        .payment-summary {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border: 2px solid #000;
        }

        .summary-row {
            display: table;
            width: 100%;
            margin: 8px 0;
            font-size: 12pt;
        }

        .summary-row.total {
            font-size: 14pt;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
        }

        .summary-label {
            display: table-cell;
            width: 70%;
            font-weight: bold;
        }

        .summary-value {
            display: table-cell;
            width: 30%;
            text-align: right;
        }

        /* Footer */
        .receipt-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #000;
        }

        .signature-section {
            margin: 20px 0;
        }

        .signature-line {
            margin-top: 40px;
            border-top: 2px solid #000;
            width: 250px;
            padding-top: 5px;
            text-align: center;
        }

        .qr-code {
            text-align: center;
            margin: 20px 0;
        }

        .qr-code img {
            width: 100px;
            height: 100px;
        }

        .disclaimer {
            font-size: 8pt;
            text-align: center;
            color: #666;
            margin: 15px 0;
            padding: 10px;
            border: 1px dashed #999;
        }

        .copy-designation {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            color: <?= $copyColor ?>;
            margin: 15px 0;
            padding: 10px;
            border: 2px solid <?= $copyColor ?>;
        }

        /* Print adjustments */
        @media print {
            body {
                padding: 0;
            }
            .receipt-container {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Watermark -->
        <?php if (RECEIPT_SHOW_WATERMARK): ?>
        <div class="watermark"><?= RECEIPT_WATERMARK_TEXT ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="receipt-header">
            <?php if (RECEIPT_SHOW_LOGO && file_exists(RECEIPT_LOGO_PATH)): ?>
            <img src="<?= RECEIPT_LOGO_PATH ?>" alt="Logo" class="logo">
            <?php endif; ?>

            <div class="lgu-name"><?= RECEIPT_LGU_NAME ?></div>
            <div class="lgu-info"><?= RECEIPT_LGU_PROVINCE ?></div>
            <div class="lgu-info"><?= RECEIPT_LGU_ADDRESS ?></div>
            <div class="lgu-info"><?= RECEIPT_LGU_CONTACT ?></div>

            <div class="receipt-title">OFFICIAL RECEIPT</div>
            <div class="receipt-subtitle">Traffic Citation Payment</div>
        </div>

        <!-- Receipt Information -->
        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Receipt No.:</div>
                <div class="info-value"><?= $receiptNumber ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date & Time:</div>
                <div class="info-value"><?= $paymentDate ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Ticket No.:</div>
                <div class="info-value"><?= $ticketNumber ?></div>
            </div>
        </div>

        <!-- Payor Information -->
        <div class="section-header">RECEIVED FROM:</div>
        <div class="info-row">
            <div class="info-label">Name:</div>
            <div class="info-value"><?= $driverName ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">License No.:</div>
            <div class="info-value"><?= $licenseNumber ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Plate No.:</div>
            <div class="info-value"><?= $plateNumber ?></div>
        </div>
        <?php if ($vehicleInfo): ?>
        <div class="info-row">
            <div class="info-label">Vehicle:</div>
            <div class="info-value"><?= $vehicleInfo ?></div>
        </div>
        <?php endif; ?>

        <!-- Violations -->
        <div class="section-header">VIOLATION DETAILS:</div>
        <table class="violations-table">
            <thead>
                <tr>
                    <th>Violation Type</th>
                    <th class="text-center">Offense</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data['violations'])): ?>
                    <?php foreach ($data['violations'] as $violation): ?>
                    <tr>
                        <td><?= htmlspecialchars($violation['violation_type']) ?></td>
                        <td class="text-center"><?= $violation['offense_count'] ?><?= $violation['offense_count'] == 1 ? 'st' : ($violation['offense_count'] == 2 ? 'nd' : ($violation['offense_count'] == 3 ? 'rd' : 'th')) ?></td>
                        <td class="text-right"><?= formatMoney($violation['fine_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No violations found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Payment Summary -->
        <div class="payment-summary">
            <div class="summary-row">
                <div class="summary-label">TOTAL FINE:</div>
                <div class="summary-value"><?= $totalFine ?></div>
            </div>
            <div class="summary-row">
                <div class="summary-label">AMOUNT PAID:</div>
                <div class="summary-value"><?= $amountPaid ?></div>
            </div>
            <div class="summary-row">
                <div class="summary-label">PAYMENT METHOD:</div>
                <div class="summary-value"><?= $paymentMethod ?></div>
            </div>
            <?php if ($data['payment_method'] === 'check' && !empty($data['check_number'])): ?>
            <div class="summary-row">
                <div class="summary-label">CHECK NO.:</div>
                <div class="summary-value"><?= htmlspecialchars($data['check_number']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['reference_number'])): ?>
            <div class="summary-row">
                <div class="summary-label">REFERENCE NO.:</div>
                <div class="summary-value"><?= htmlspecialchars($data['reference_number']) ?></div>
            </div>
            <?php endif; ?>
            <div class="summary-row">
                <div class="summary-label">CHANGE:</div>
                <div class="summary-value"><?= $change ?></div>
            </div>
        </div>

        <!-- Collector Signature -->
        <div class="signature-section">
            <div class="info-row">
                <div class="info-label">Collected by:</div>
                <div class="info-value"><?= $collectorName ?></div>
            </div>
            <div class="signature-line">
                Signature Over Printed Name
            </div>
        </div>

        <!-- QR Code -->
        <?php if (RECEIPT_SHOW_QR_CODE && $qrCodeUrl): ?>
        <div class="qr-code">
            <img src="<?= $qrCodeUrl ?>" alt="QR Code">
            <div style="font-size: 8pt; margin-top: 5px;">Scan to verify receipt</div>
        </div>
        <?php endif; ?>

        <!-- Disclaimer -->
        <div class="disclaimer">
            <?= RECEIPT_DISCLAIMER ?>
        </div>

        <!-- Copy Designation -->
        <div class="copy-designation">
            ─── <?= $copyDesignation ?> ───
        </div>
    </div>
</body>
</html>
