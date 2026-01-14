<?php
/**
 * Process Payment Page
 *
 * Displays pending/unpaid citations with payment processing capability
 * Cashiers can process payments with cash/change calculator
 *
 * @package TrafficCitationSystem
 * @subpackage Public
 */

session_start();

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// Require authentication
require_login();

// Require cashier or admin privileges
if (!can_process_payment()) {
    set_flash('Access denied. Only cashiers can process payments.', 'danger');
    header('Location: /tmg/public/index.php');
    exit;
}

// Page title
$pageTitle = 'Process Payments';

// Get all pending citations (excluding those with active payments)
$pdo = getPDO();

// Check if database connection failed
if ($pdo === null) {
    set_flash('Database connection failed. Please check if MySQL is running and try again.', 'danger');
    $pendingCitations = [];
    $pendingPrintCount = 0;
} else {
    $sql = "SELECT
                c.citation_id,
                c.ticket_number,
                c.apprehension_datetime,
                c.total_fine,
                c.status,
                CONCAT(c.first_name, ' ', c.last_name) as driver_name,
                c.license_number,
                c.plate_mv_engine_chassis_no as plate_number,
                c.vehicle_description,
                GROUP_CONCAT(vt.violation_type SEPARATOR ', ') as violations
            FROM citations c
            LEFT JOIN violations v ON c.citation_id = v.citation_id
            LEFT JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
            LEFT JOIN payments p ON c.citation_id = p.citation_id
                AND p.status IN ('pending_print', 'completed')
            WHERE c.status = 'pending'
            AND p.payment_id IS NULL
            AND c.deleted_at IS NULL
            GROUP BY c.citation_id
            ORDER BY c.apprehension_datetime DESC";

    $stmt = $pdo->query($sql);
    $pendingCitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pending print count for the warning banner
    $pendingPrintCount = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending_print'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Traffic Citation System</title>

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/process-payment.css">

    <!-- Application Configuration - MUST be loaded before other JS files -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>

    <style>
        /* Base Styles - Inter Font Family */
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background-color: #f8f9fa;
        }

        /* Enhanced Button Styles */
        .btn {
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn i[data-lucide] {
            width: 18px;
            height: 18px;
        }

        .btn-sm i[data-lucide] {
            width: 16px;
            height: 16px;
        }

        .btn-lg i[data-lucide] {
            width: 20px;
            height: 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #0a58ca 0%, #084298 100%);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            border: none;
            box-shadow: 0 2px 4px rgba(25, 135, 84, 0.2);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #157347 0%, #0f5132 100%);
            box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
            transform: translateY(-1px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);
            border: none;
            color: #000;
            box-shadow: 0 2px 4px rgba(255, 193, 7, 0.2);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #ffb300 0%, #ffa000 100%);
            box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
            transform: translateY(-1px);
            color: #000;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%);
            border: none;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #bb2d3b 0%, #a02834 100%);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
            transform: translateY(-1px);
        }

        /* Enhanced Card Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 2px solid #e9ecef;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.25rem 1.5rem;
        }

        .card-header h5 {
            font-weight: 600;
            color: #212529;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Enhanced Page Header */
        .page-header {
            margin-bottom: 1.5rem;
        }

        .page-header h2 {
            font-weight: 700;
            color: #212529;
            font-size: 2rem;
            margin: 0;
        }

        /* Additional modal styles that are specific to this page */

        /* Modal-specific styles */

        /* Modal Two-Column Layout */
        .modal-two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }

        .modal-column-left,
        .modal-column-right {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .modal-column-left .summary-section,
        .modal-column-right .amount-display,
        .modal-column-right .payment-info-section,
        .modal-column-right .change-display,
        .modal-column-right > div:last-child {
            margin-bottom: 20px;
        }

        .modal-column-left .summary-section:last-child,
        .modal-column-right > div:last-child {
            margin-bottom: 0;
        }

        .modal-title {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #212529;
        }

        .modal-title i[data-lucide] {
            color: #6c757d;
            width: 18px;
            height: 18px;
        }

        .modal-body {
            padding: 1.25rem;
            font-size: 0.875rem;
            background: #f8f9fa;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        /* Custom scrollbar for modal body */
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Citation Summary Box - Enhanced Modern Design */
        .summary-section {
            background: #ffffff;
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .summary-section:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .summary-section h6 {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-section h6 i[data-lucide] {
            color: #6c757d;
            width: 16px;
            height: 16px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .summary-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 400;
        }

        .summary-value {
            font-size: 0.875rem;
            color: #212529;
            font-weight: 500;
        }

        /* Amount Display - Enhanced Modern Design */
        .amount-display {
            background: linear-gradient(135deg, #e8f4f8 0%, #f0f9ff 100%);
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
            border: 2px solid #b8daff;
            border-left: 5px solid #0d6efd;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.1);
            transition: all 0.3s ease;
        }

        .amount-display:hover {
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.15);
            transform: translateY(-2px);
        }

        .amount-display small {
            display: block;
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 0.5rem;
        }

        .amount-display .amount-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: #212529;
        }

        /* Change Display - Enhanced Modern Design */
        .change-display {
            background: linear-gradient(135deg, #d1fae5 0%, #e8fdf4 100%);
            padding: 1.5rem;
            border-radius: 12px;
            margin: 1rem 0;
            border: 2px solid #a7f3d0;
            border-left: 5px solid #198754;
            box-shadow: 0 2px 8px rgba(25, 135, 84, 0.1);
            transition: all 0.3s ease;
        }

        .change-display:hover {
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.15);
            transform: translateY(-2px);
        }

        .change-display small {
            display: block;
            font-size: 0.75rem;
            color: #0f5132;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 0.5rem;
        }

        .change-display .change-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: #0f5132;
        }

        /* Payment Information Section - Enhanced Modern Design */
        .payment-info-section {
            background: #ffffff;
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .payment-info-section:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .payment-info-section h6 {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .payment-info-section h6 i[data-lucide] {
            color: #6c757d;
            width: 16px;
            height: 16px;
        }

        .payment-info-section .row {
            margin-top: 0.5rem;
        }

        /* Form Elements - Clean Design */
        .form-label {
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i[data-lucide] {
            color: #6c757d;
            width: 16px;
            height: 16px;
        }

        .form-control, .form-select {
            font-size: 0.875rem;
            padding: 0.625rem 0.875rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.2s ease;
            background: #ffffff;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
            outline: none;
            background: #ffffff;
        }

        .form-control:hover:not(:focus), .form-select:hover:not(:focus) {
            border-color: #cbd5e1;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .form-text {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .form-text i[data-lucide] {
            color: #6c757d;
            width: 14px;
            height: 14px;
        }

        .modal-footer {
            padding: 1rem 1.25rem;
            background-color: #ffffff;
            border-top: 1px solid #dee2e6;
            gap: 0.5rem;
            display: flex;
            justify-content: flex-end;
        }

        .modal-footer .btn {
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .modal-footer .btn-secondary {
            background: #ffffff;
            border: 1px solid #dee2e6;
            color: #495057;
        }

        .modal-footer .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
        }

        /* Alerts - Enhanced Modern Design */
        .alert {
            font-size: 0.875rem;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
        }

        .alert i[data-lucide] {
            margin-right: 0.5rem;
            width: 18px;
            height: 18px;
            vertical-align: text-bottom;
        }

        .alert-info {
            background-color: #e8f4f8;
            color: #004085;
            border-color: #b8daff;
        }

        .alert-warning {
            background-color: #fff8e6;
            color: #856404;
            border-color: #ffeaa7;
        }

        /* Print Preview Modal - Enhanced Modern Design */
        .modern-modal {
            border: none;
            border-radius: 16px !important;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .modal-content {
            border-radius: 16px !important;
            border: none;
        }

        /* Enhanced Gradient Header */
        .gradient-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: none;
            padding: 1.5rem 1.75rem;
            color: #212529;
            border-bottom: 2px solid #e9ecef;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            background: #d1fae5;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #198754;
        }

        .header-text {
            flex: 1;
        }

        .header-text .modal-title {
            color: #212529;
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        .header-text small {
            display: block;
            font-size: 0.8125rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        /* Modern Loading State */
        .receipt-loading-modern {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
            background: #f8fafc;
        }

        .loading-content {
            text-align: center;
            padding: 2rem;
        }

        /* Spinner Animation */
        .spinner-modern {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }

        .spinner-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 4px solid transparent;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1.5s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
        }

        .spinner-ring:nth-child(2) {
            width: 80%;
            height: 80%;
            top: 10%;
            left: 10%;
            border-top-color: #2563eb;
            animation-delay: 0.2s;
        }

        .spinner-ring:nth-child(3) {
            width: 60%;
            height: 60%;
            top: 20%;
            left: 20%;
            border-top-color: #60a5fa;
            animation-delay: 0.4s;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .spinner-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            color: #3b82f6;
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            50% { opacity: 0.5; transform: translate(-50%, -50%) scale(0.9); }
        }

        .loading-dots {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .loading-dots span {
            width: 8px;
            height: 8px;
            background: #3b82f6;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out;
        }

        .loading-dots span:nth-child(1) { animation-delay: -0.32s; }
        .loading-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        /* Preview Wrapper */
        .receipt-preview-wrapper {
            background: #f8fafc;
            min-height: 500px;
        }

        .preview-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: white;
            border-bottom: 2px solid #e2e8f0;
        }

        .toolbar-info {
            display: flex;
            align-items: center;
            font-weight: 500;
            color: #475569;
            font-size: 0.95rem;
        }

        .toolbar-actions {
            display: flex;
            gap: 0.5rem;
        }

        .receipt-container {
            padding: 2rem;
            display: flex;
            justify-content: center;
            background: #e2e8f0;
        }

        .receipt-iframe {
            width: 100%;
            max-width: 800px;
            min-height: 600px;
            border: none;
            background: white;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
        }

        /* Modern Footer - Clean Design */
        .modern-footer {
            background: #ffffff;
            border-top: 1px solid #dee2e6;
            padding: 1rem 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .footer-instructions {
            background: #e8f4f8;
            border-left: 4px solid #0d6efd;
            padding: 0.75rem 1rem;
            border-radius: 4px;
        }

        .instruction-steps {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
        }

        .step-number {
            width: 24px;
            height: 24px;
            background: #0d6efd;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .step-text {
            color: #495057;
            font-weight: 400;
        }

        .footer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .footer-actions .btn-lg {
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            border-radius: 4px;
        }

        .footer-actions .btn-light {
            background: #ffffff;
            border: 1px solid #dee2e6;
            color: #495057;
        }

        .footer-actions .btn-light:hover {
            background: #f8f9fa;
            border-color: #adb5bd;
        }

        .footer-actions .btn-success {
            background-color: #198754;
            border-color: #198754;
            color: #ffffff;
        }

        .footer-actions .btn-success:hover {
            background-color: #157347;
            border-color: #157347;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .instruction-steps {
                flex-direction: column;
                gap: 1rem;
            }

            .footer-actions {
                flex-direction: column;
            }

            .footer-actions .btn-lg {
                width: 100%;
            }

            .receipt-container {
                padding: 1rem;
            }
        }

        /* Enhanced Table Styles */
        .table {
            font-size: 0.875rem;
        }

        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #495057;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 1rem 0.75rem;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: #f1f3f5;
            transition: background-color 0.2s ease;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Row number column styling */
        .table tbody td:first-child {
            font-weight: 600;
            color: #6c757d;
            font-family: 'Inter', monospace;
            font-variant-numeric: tabular-nums;
            font-size: 0.8125rem;
            border-right: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }

        .table thead th:first-child {
            text-align: center;
            font-weight: 700;
            border-right: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }

        /* Filter & Search Styles - Enhanced Design */
        .input-group {
            border-radius: 8px;
            overflow: hidden;
        }

        .input-group-lg .input-group-text {
            font-size: 0.875rem;
            background: #ffffff;
            border-right: none;
            border: 2px solid #e9ecef;
            color: #495057;
            border-radius: 8px 0 0 8px;
        }

        .input-group-lg .input-group-text i[data-lucide] {
            width: 18px;
            height: 18px;
        }

        .input-group-lg .form-control {
            border-left: none;
            border: 2px solid #e9ecef;
            border-left: none;
            font-size: 0.875rem;
            padding: 0.75rem 1rem;
        }

        .input-group-lg .form-control:focus {
            border-left: none;
            border-color: #0d6efd;
            box-shadow: none;
        }

        .input-group-lg .form-control:focus + .input-group-text,
        .input-group:focus-within .input-group-text {
            border-color: #0d6efd;
        }

        .stat-item {
            padding: 0.5rem;
        }

        .stat-item h6 {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .stat-item h4 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #212529;
        }

        .pagination {
            margin: 0;
        }

        .pagination .page-link {
            border-radius: 4px;
            margin: 0 2px;
            font-weight: 400;
            color: #495057;
            font-size: 0.875rem;
            border: 1px solid #dee2e6;
        }

        .pagination .page-link:hover {
            background-color: #f8f9fa;
        }

        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .pagination .page-item.disabled .page-link {
            opacity: 0.5;
        }

        #loadingSpinner {
            min-height: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* Payment Summary Styles - Clean Design */
        .payment-summary-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 1rem;
        }

        .summary-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.625rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-size: 0.8125rem;
            color: #6c757d;
            font-weight: 400;
        }

        .summary-value {
            font-size: 0.875rem;
            color: #212529;
            font-weight: 500;
        }

        .summary-value.or-number {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #0d6efd;
            background: #e8f4f8;
            padding: 0.25rem 0.625rem;
            border-radius: 4px;
            border: 1px solid #b8daff;
        }

        .highlight-change {
            background: #e8f4f8;
            padding: 0.75rem;
            margin-top: 0.5rem;
            border-radius: 4px;
            border: 1px solid #b8daff;
        }

        .highlight-change .summary-value {
            color: #0d6efd;
            font-size: 1rem;
            font-weight: 600;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                padding: 1rem;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .modal-two-column {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .amount-display .amount-value,
            .change-display .change-value {
                font-size: 1.5rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .payment-info-section,
            .summary-section {
                padding: 0.875rem;
            }

            .payment-info-section .row {
                row-gap: 0.75rem;
            }

            #paginationContainer {
                flex-direction: column;
                gap: 1rem;
            }

            .summary-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }

            .footer-actions {
                flex-direction: column;
            }

            .footer-actions .btn-lg {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/loader.php'; ?>
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Process Payments</h2>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php
                    echo $_SESSION['flash_message'];
                    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php
            // Check if there are pending_print payments
            if ($pendingPrintCount > 0):
            ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <i data-lucide="alert-triangle"></i>
                    <strong>Notice:</strong> You have <strong><?= $pendingPrintCount ?></strong> payment(s) waiting for print confirmation.
                    <a href="/tmg/public/pending_print_payments.php" class="alert-link">Click here to review them</a>.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search & Filters Card -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Search & Filters</h5>
                </div>
                <div class="card-body">
                    <form id="filterForm" onsubmit="return false;">
                        <!-- Search Bar -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i data-lucide="search"></i></span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="searchInput"
                                        placeholder="Search by ticket number, driver name, license, or plate number..."
                                        autocomplete="off"
                                    >
                                    <button class="btn btn-primary" type="button" onclick="applyFilters()">
                                        <i data-lucide="search"></i> Search
                                    </button>
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearFilters()">
                                        <i data-lucide="x"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Advanced Filters (Collapsible) -->
                        <div class="collapse" id="advancedFilters">
                            <div class="row g-3">
                                <!-- Date Range -->
                                <div class="col-md-3">
                                    <label class="form-label">Date From</label>
                                    <input type="date" class="form-control" id="dateFrom">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date To</label>
                                    <input type="date" class="form-control" id="dateTo">
                                </div>

                                <!-- Amount Range -->
                                <div class="col-md-3">
                                    <label class="form-label">Min Amount (₱)</label>
                                    <input type="number" class="form-control" id="minAmount" step="0.01" placeholder="0.00">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Max Amount (₱)</label>
                                    <input type="number" class="form-control" id="maxAmount" step="0.01" placeholder="99999.99">
                                </div>

                                <!-- Violation Type -->
                                <div class="col-md-6">
                                    <label class="form-label">Violation Type</label>
                                    <select class="form-select" id="violationType">
                                        <option value="">All Violations</option>
                                        <!-- Will be populated via AJAX -->
                                    </select>
                                </div>

                                <!-- Sort By -->
                                <div class="col-md-6">
                                    <label class="form-label">Sort By</label>
                                    <select class="form-select" id="sortBy">
                                        <option value="date_desc">Newest First</option>
                                        <option value="date_asc">Oldest First</option>
                                        <option value="amount_desc">Highest Amount</option>
                                        <option value="amount_asc">Lowest Amount</option>
                                        <option value="driver_name">Driver Name (A-Z)</option>
                                        <option value="ticket_number">Ticket Number</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-primary" type="button" onclick="applyFilters()">
                                    <i data-lucide="check"></i> Apply Filters
                                </button>
                                <button class="btn btn-outline-secondary" type="button" onclick="clearFilters()">
                                    <i data-lucide="rotate-ccw"></i> Reset All
                                </button>
                            </div>
                        </div>

                        <!-- Toggle Advanced Filters -->
                        <div class="text-center mt-2">
                            <a
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="collapse"
                                href="#advancedFilters"
                                role="button"
                                aria-expanded="false"
                                aria-controls="advancedFilters"
                            >
                                <i data-lucide="sliders-horizontal"></i> Advanced Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="card mb-3" id="statsCard" style="display: none;">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h6 class="text-muted mb-1">Total Citations</h6>
                                <h4 class="mb-0" id="statTotalCitations">0</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h6 class="text-muted mb-1">Total Amount</h6>
                                <h4 class="mb-0 text-success" id="statTotalAmount">₱0.00</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h6 class="text-muted mb-1">Average Fine</h6>
                                <h4 class="mb-0 text-primary" id="statAvgFine">₱0.00</h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <h6 class="text-muted mb-1">Fine Range</h6>
                                <h4 class="mb-0 text-info" id="statFineRange">₱0 - ₱0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Citations Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Pending Citations <span id="citationCount">(0)</span></h5>
                    <div>
                        <small class="text-muted me-3">
                            <i data-lucide="info"></i> Showing citations awaiting payment
                        </small>
                        <button class="btn btn-sm btn-success" onclick="exportToCSV()" id="exportBtn" style="display: none;">
                            <i data-lucide="file-spreadsheet"></i> Export to CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Loading Spinner -->
                    <div id="loadingSpinner" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading citations...</p>
                    </div>

                    <!-- No Results Message -->
                    <div id="noResults" class="alert alert-info mb-0" style="display: none;">
                        <i data-lucide="info"></i> No pending citations found matching your filters.
                    </div>

                    <!-- Table Container -->
                    <div id="tableContainer">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="citationsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Ticket Number</th>
                                        <th>Driver</th>
                                        <th>License</th>
                                        <th>Vehicle</th>
                                        <th>Violation</th>
                                        <th>Citation Date</th>
                                        <th>Amount Due</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="citationsTableBody">
                                    <!-- Will be populated via AJAX -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div id="paginationContainer" class="d-flex justify-content-between align-items-center mt-3">
                            <div class="pagination-info">
                                <span id="paginationInfo">Showing 0 to 0 of 0 citations</span>
                            </div>
                            <nav aria-label="Page navigation">
                                <ul class="pagination mb-0" id="paginationControls">
                                    <!-- Will be populated via AJAX -->
                                </ul>
                            </nav>
                            <div class="page-size-selector">
                                <select class="form-select form-select-sm" id="pageSizeSelect" onchange="changePageSize(this.value)" style="width: auto;">
                                    <option value="10">10 per page</option>
                                    <option value="25" selected>25 per page</option>
                                    <option value="50">50 per page</option>
                                    <option value="100">100 per page</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Processing Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">Process Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="paymentForm">
                    <div class="modal-body">
                        <input type="hidden" id="citation_id" name="citation_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="modal-two-column">
                            <!-- Left Column: Citation Details -->
                            <div class="modal-column-left">
                                <!-- Citation Summary -->
                                <div class="summary-section">
                                    <h6><i data-lucide="ticket"></i> Citation Details</h6>
                                    <div class="summary-grid">
                                        <div class="summary-item">
                                            <span class="summary-label">Ticket Number</span>
                                            <span class="summary-value" id="modal_ticket_number"></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Date</span>
                                            <span class="summary-value" id="modal_date"></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Driver Name</span>
                                            <span class="summary-value" id="modal_driver_name"></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">License Number</span>
                                            <span class="summary-value" id="modal_license"></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Vehicle</span>
                                            <span class="summary-value" id="modal_vehicle"></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Violation(s)</span>
                                            <span class="summary-value" id="modal_violation"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Payment Information -->
                            <div class="modal-column-right">
                                <!-- Amount Due -->
                                <div class="amount-display">
                                    <div class="amount-content">
                                        <small>Amount Due</small>
                                        <div class="amount-value">₱<span id="modal_amount">0.00</span></div>
                                    </div>
                                    <div class="amount-icon">
                                        <i data-lucide="peso-sign"></i>
                                    </div>
                                </div>

                                <!-- Payment Information Section -->
                                <div class="payment-info-section">
                                    <h6><i data-lucide="credit-card"></i> Payment Information</h6>

                                    <div class="row g-3">
                                        <!-- Payment Method -->
                                        <div class="col-md-6">
                                            <label for="payment_method" class="form-label">
                                                <i data-lucide="wallet"></i> Payment Method *
                                            </label>
                                            <select class="form-select" id="payment_method" name="payment_method" required>
                                                <option value="">Select Method</option>
                                                <option value="cash" selected>Cash</option>
                                                <option value="gcash">GCash</option>
                                                <option value="paymaya">PayMaya</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="check">Check</option>
                                            </select>
                                        </div>

                                        <!-- OR/Receipt Number -->
                                        <div class="col-md-6">
                                            <label for="receipt_number" class="form-label">
                                                <i data-lucide="receipt"></i> OR Number *
                                            </label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="receipt_number"
                                                name="receipt_number"
                                                required
                                                placeholder="e.g., 15320501 or CGVM15320501"
                                                style="font-family: 'Courier New', monospace; font-weight: bold; font-size: 1rem;"
                                            >
                                        </div>

                                        <!-- Cash Received (only for cash payments) -->
                                        <div class="col-md-6" id="cashReceivedField">
                                            <label for="cash_received" class="form-label">
                                                <i data-lucide="banknote"></i> Cash Received *
                                            </label>
                                            <input
                                                type="number"
                                                class="form-control"
                                                id="cash_received"
                                                name="cash_received"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                            >
                                        </div>

                                        <!-- Reference Number (for non-cash payments) -->
                                        <div class="col-md-6" id="referenceField" style="display: none;">
                                            <label for="reference_number" class="form-label">
                                                <i data-lucide="hash"></i> Reference Number
                                            </label>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="reference_number"
                                                name="reference_number"
                                                placeholder="Transaction reference"
                                            >
                                        </div>
                                    </div>

                                    <div class="form-text mt-2">
                                        <i data-lucide="info"></i> Enter the OR number exactly as it appears on the physical receipt booklet.
                                    </div>
                                </div>

                                <!-- Change Display (only for cash payments) -->
                                <div class="change-display" id="changeDisplay" style="display: none;">
                                    <div class="change-content">
                                        <small>Change</small>
                                        <div class="change-value">₱<span id="change_amount">0.00</span></div>
                                    </div>
                                    <div class="change-icon">
                                        <i data-lucide="hand-coins"></i>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="mb-0">
                                    <label for="notes" class="form-label">
                                        <i data-lucide="sticky-note"></i> Notes (Optional)
                                    </label>
                                    <textarea
                                        class="form-control"
                                        id="notes"
                                        name="notes"
                                        rows="2"
                                        placeholder="Add any additional notes"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="confirmPaymentBtn">
                            <i data-lucide="check-circle"></i> Confirm Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reprint Options Modal -->
    <div class="modal fade" id="reprintOptionsModal" tabindex="-1" aria-labelledby="reprintOptionsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: #ffffff; border-bottom: 1px solid #e5e7eb;">
                    <h5 class="modal-title" id="reprintOptionsModalLabel" style="color: #0f172a; display: flex; align-items: center; gap: 12px;">
                        <i data-lucide="alert-triangle" style="color: #f59e0b; background: #fef3c7; padding: 8px; border-radius: 8px; font-size: 1.25rem;"></i>
                        <span>Printer Problem</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reprint_payment_id">
                    <input type="hidden" id="reprint_current_or">

                    <p class="mb-3">
                        <strong>What would you like to do?</strong>
                    </p>

                    <div class="d-grid gap-3">
                        <!-- Option 1: Reprint -->
                        <button class="btn btn-primary btn-lg" onclick="reprintReceipt()">
                            <i data-lucide="rotate-cw"></i> REPRINT
                            <div class="small">Use same OR: <span id="display_current_or"></span></div>
                        </button>

                        <!-- Option 2: Use New Receipt -->
                        <button class="btn btn-warning btn-lg" onclick="showNewOrInput()">
                            <i data-lucide="edit"></i> USE NEW RECEIPT
                            <div class="small">Enter new OR number</div>
                        </button>

                        <!-- New OR Input (hidden by default) -->
                        <div id="newOrInputSection" style="display: none;">
                            <div class="mb-3">
                                <label for="new_or_input" class="form-label">New OR Number</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="new_or_input"
                                    placeholder="Enter new OR number"
                                    style="font-family: 'Courier New', monospace; font-weight: bold;"
                                >
                            </div>
                            <button class="btn btn-success w-100" onclick="confirmNewOr()">
                                <i data-lucide="check"></i> Confirm New OR
                            </button>
                        </div>

                        <!-- Option 3: Cancel Payment -->
                        <button class="btn btn-danger btn-lg" onclick="voidPaymentConfirm()">
                            <i data-lucide="x-circle"></i> CANCEL PAYMENT
                            <div class="small">Void this transaction</div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Summary Modal -->
    <div class="modal fade" id="printPreviewModal" tabindex="-1" aria-labelledby="printPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content modern-modal">
                <!-- Modern Header -->
                <div class="modal-header gradient-header">
                    <div class="header-content">
                        <div class="header-icon">
                            <i data-lucide="check-circle"></i>
                        </div>
                        <div class="header-text">
                            <h5 class="modal-title mb-0" id="printPreviewModalLabel">
                                Payment Confirmed
                            </h5>
                            <small class="text-white-50">Transaction completed successfully</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Payment Summary Content -->
                <div class="modal-body p-4">
                    <div id="paymentSummaryContent">
                        <!-- Content will be populated via JavaScript -->
                    </div>
                </div>

                <!-- Modern Footer with Instructions -->
                <div class="modal-footer modern-footer">
                    <div class="footer-instructions">
                        <div class="instruction-steps">
                            <div class="step">
                                <span class="step-number">1</span>
                                <span class="step-text">Review payment details above</span>
                            </div>
                            <div class="step">
                                <span class="step-number">2</span>
                                <span class="step-text">Click "Print Receipt" button</span>
                            </div>
                            <div class="step">
                                <span class="step-number">3</span>
                                <span class="step-text">Confirm print was successful</span>
                            </div>
                        </div>
                    </div>
                    <div class="footer-actions">
                        <button type="button" class="btn btn-light btn-lg" data-bs-dismiss="modal">
                            <i data-lucide="x"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-success btn-lg" onclick="printReceiptFromPreview()">
                            <i data-lucide="printer"></i> Print Receipt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Process Payment Filters & Pagination -->
    <script src="../assets/js/process_payment_filters.js"></script>
    <!-- Payment Validation Module -->
    <script src="../assets/js/payments/payment-validation.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/process_payment.js"></script>
    <!-- Initialize Lucide Icons -->
    <script>
        // Initialize Lucide icons after DOM content is loaded
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });

        // Re-initialize icons after dynamic content updates
        function reinitLucideIcons() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    </script>
</body>
</html>
