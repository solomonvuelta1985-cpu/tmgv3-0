<?php
/**
 * Create Test Payment
 * This script creates a sample payment for testing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/services/PaymentService.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create Test Payment</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='p-5'>
<div class='container'>
    <h1>Create Test Payment</h1>
    <hr>
";

try {
    $pdo = getPDO();

    // Get a pending citation
    $sql = "SELECT * FROM citations WHERE status = 'pending' LIMIT 1";
    $stmt = $pdo->query($sql);
    $citation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$citation) {
        echo "<div class='alert alert-warning'>No pending citations found. Please create a citation first.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='alert alert-info'>";
    echo "<h5>Found Pending Citation:</h5>";
    echo "<strong>Ticket:</strong> {$citation['ticket_number']}<br>";
    echo "<strong>Driver:</strong> {$citation['first_name']} {$citation['last_name']}<br>";
    echo "<strong>Fine:</strong> ₱" . number_format($citation['total_fine'], 2) . "<br>";
    echo "</div>";

    // Get admin user (assuming user_id = 1)
    $sql = "SELECT user_id, full_name FROM users WHERE role = 'admin' LIMIT 1";
    $stmt = $pdo->query($sql);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<div class='alert alert-danger'>No admin user found.</div>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='alert alert-secondary'>";
    echo "<strong>Recording payment as:</strong> {$user['full_name']} (User ID: {$user['user_id']})<br>";
    echo "</div>";

    // Initialize PaymentService
    $paymentService = new PaymentService($pdo);

    // Record test payment
    echo "<h4>Processing Payment...</h4>";

    $result = $paymentService->recordPayment(
        $citation['citation_id'],
        $citation['total_fine'],
        'cash',
        $user['user_id'],
        [
            'notes' => 'Test payment created via create_test_payment.php'
        ]
    );

    if ($result['success']) {
        echo "<div class='alert alert-success'>";
        echo "<h4>✅ Payment Recorded Successfully!</h4>";
        echo "<strong>Payment ID:</strong> {$result['payment_id']}<br>";
        echo "<strong>Receipt Number:</strong> {$result['receipt_number']}<br>";
        echo "<strong>Payment Date:</strong> {$result['payment_date']}<br>";
        echo "</div>";

        echo "<div class='mt-4'>";
        echo "<h5>Next Steps:</h5>";
        echo "<ol>";
        echo "<li>Visit the <a href='public/payments.php' class='btn btn-primary btn-sm'>Payment Management Page</a></li>";
        echo "<li>You should see this payment in the list</li>";
        echo "<li>Try downloading the receipt PDF</li>";
        echo "</ol>";
        echo "</div>";

        echo "<div class='alert alert-warning mt-3'>";
        echo "<strong>⚠️ Security Note:</strong> Delete this file after testing!<br>";
        echo "<code>rm create_test_payment.php</code>";
        echo "</div>";

    } else {
        echo "<div class='alert alert-danger'>";
        echo "<h4>❌ Payment Failed</h4>";
        echo "<strong>Error:</strong> {$result['message']}";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>Error:</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
?>
