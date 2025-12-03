<?php
// Define root path and fix require paths
define('ROOT_PATH', dirname(__DIR__));

// Updated require path - adjust based on your actual file structure
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/services/CitationService.php';

// Require login and check session timeout
require_login();
check_session_timeout();

// Initialize CitationService
$citationService = new CitationService();

// Generate next ticket number
$next_ticket = $citationService->generateNextTicketNumber();

// Pre-fill driver info if driver_id is provided
$driver_data = [];
$offense_counts = [];
if (isset($_GET['driver_id'])) {
    $driver_id = (int)$_GET['driver_id'];
    $driver_data = $citationService->getDriverById($driver_id);
    if ($driver_data) {
        $offense_counts = $citationService->getOffenseCountsByDriverId($driver_id);
    }
}

// Cache violation types (only active ones)
if (!isset($_SESSION['violation_types'])) {
    $_SESSION['violation_types'] = $citationService->getActiveViolationTypes();
}
$violation_types = $_SESSION['violation_types'];

// Fetch active apprehending officers
$apprehending_officers = $citationService->getActiveApprehendingOfficers();

// Close connection
$citationService->closeConnection();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Traffic Citation Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/citation-form.css">

    <!-- Application Configuration - MUST be loaded before other JS files -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>

    <style>
        /* Override and ensure proper layout with sidebar */
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 72px;
            --white: #ffffff;
            --border-gray: #dee2e6;
        }

        /* Remove all default margins */
        body {
            margin: 0 !important;
            padding: 0 !important;
            font-family: 'Inter', sans-serif !important;
        }

        /* Force proper content positioning - NO GAPS */
        .content {
            margin-left: 260px !important;
            padding: 0 20px 20px 20px !important;
            padding-top: 64px !important; /* Only top navbar height */
            min-height: 100vh;
            background: #f8fafc !important;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-collapsed .content {
            margin-left: 72px !important;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0 !important;
                padding: 0 10px 10px 10px !important;
                padding-top: 64px !important;
            }
        }

        /* Add padding inside the ticket container instead */
        .ticket-container {
            border-radius: 8px !important;
            min-height: calc(100vh - 120px);
        }

        .swal-wide {
            width: 500px !important;
        }
        .swal2-popup {
            font-size: 1rem !important;
        }
        .swal2-title {
            font-size: 1.75rem !important;
        }

        /* Skeleton Loader Styles */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s ease-in-out infinite;
            border-radius: 4px;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .skeleton-loader .ticket-container {
            margin: 0 !important;
            border-radius: 0 !important;
            min-height: calc(100vh - 64px);
        }

        .skeleton-section {
            background-color: var(--white);
            padding: 25px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid var(--border-gray);
        }

        /* Form Header Skeleton */
        .skeleton-header {
            background-color: var(--white);
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 25px;
            border: 1px solid var(--border-gray);
            border-left: 4px solid var(--primary);
        }

        .skeleton-header-line {
            height: 16px;
            margin: 0 auto 10px;
        }

        .skeleton-header-line:nth-child(1) { width: 250px; }
        .skeleton-header-line:nth-child(2) { width: 350px; }
        .skeleton-header-line:nth-child(3) { width: 300px; height: 24px; margin-top: 15px; }

        /* Section Title */
        .skeleton-section-title {
            height: 22px;
            width: 220px;
            margin-bottom: 20px;
        }

        /* Form Row */
        .skeleton-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .skeleton-field {
            margin-bottom: 15px;
        }

        /* Label Skeleton */
        .skeleton-label {
            height: 18px;
            width: 120px;
            margin-bottom: 8px;
        }

        /* Input Skeleton */
        .skeleton-input {
            height: 44px;
            width: 100%;
            border-radius: 4px;
        }

        /* Checkbox Group */
        .skeleton-checkbox-group {
            margin-bottom: 15px;
        }

        .skeleton-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .skeleton-checkbox-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .skeleton-checkbox-label {
            height: 16px;
            width: 200px;
        }

        /* Accordion Skeleton */
        .skeleton-accordion {
            height: 50px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        /* Textarea Skeleton */
        .skeleton-textarea {
            height: 100px;
            width: 100%;
            border-radius: 4px;
        }

        /* Button Group */
        .skeleton-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .skeleton-button {
            height: 48px;
            width: 180px;
            border-radius: 4px;
        }

        .skeleton-button:nth-child(1) { width: 160px; }
        .skeleton-button:nth-child(2) { width: 130px; }
        .skeleton-button:nth-child(3) { width: 140px; }

        /* Footer Skeleton */
        .skeleton-footer {
            height: 80px;
            margin-top: 20px;
            border-radius: 4px;
        }

        .skeleton-loader {
            display: none;
        }

        .loading .skeleton-loader {
            display: block;
        }

        .loading #citationForm {
            display: none;
        }

        /* Smooth fade-in transition */
        #citationForm {
            opacity: 0;
            animation: fadeIn 0.5s ease-in forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Ensure form is visible after loading */
        body:not(.loading) #citationForm {
            opacity: 1;
        }

        /* Ensure ticket container fits properly */
        .ticket-container {
            max-width: 100%;
            overflow: visible;
        }

        /* Page Header Styles */
        .page-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 0;
            position: sticky;
            top: 64px;
            z-index: 10;
        }

        .page-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #111827;
            display: flex;
            align-items: center;
        }

        .page-header p {
            margin: 5px 0 0 0;
            color: #6b7280;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Citation Number Badge - Subtle Design */
        .citation-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f1f5f9;
            color: #334155;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1px solid #cbd5e1;
        }

        .citation-badge-icon {
            color: #64748b;
        }

        /* Lucide icon spinning animation */
        .lucide-spin {
            animation: lucide-spin 1s linear infinite;
        }

        @keyframes lucide-spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .citation-badge-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            letter-spacing: 0.3px;
            color: #1e293b;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        /* Character Counter */
        .char-counter {
            color: #6b7280;
            font-size: 0.85rem;
            float: right;
        }

        /* Mobile Improvements */
        @media (max-width: 768px) {
            .page-header {
                padding: 15px 20px;
                top: 64px;
            }

            .page-header h1 {
                font-size: 1.25rem;
            }

            .header-actions {
                margin-top: 10px;
                width: 100%;
            }

            .header-actions .btn {
                width: 100%;
            }

            .form-control, .form-select {
                font-size: 16px !important; /* Prevents zoom on iOS */
                min-height: 44px; /* Touch-friendly */
            }

            .form-check-input {
                width: 24px;
                height: 24px;
            }
        }

        /* Loading button state */
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }

        /* ==========================================
           STICKY SECTION NAVIGATION
           ========================================== */
        .form-navigation {
            position: fixed;
            right: 30px;
            top: 120px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px 15px;
            z-index: 100;
            min-width: 180px;
            transition: all 0.3s ease;
        }

        .form-navigation h6 {
            margin: 0 0 15px 0;
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-item-link {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            margin-bottom: 5px;
            position: relative;
        }

        .nav-item-link:hover {
            background: #f1f5f9;
            color: #3b82f6;
        }

        .nav-item-link.active {
            background: #dbeafe;
            color: #3b82f6;
            font-weight: 600;
        }

        .nav-item-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: #3b82f6;
            border-radius: 3px;
        }

        .nav-item-link i {
            margin-right: 10px;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        .nav-item-check {
            margin-left: auto;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: white;
        }

        .nav-item-link.completed .nav-item-check {
            background: #10b981;
            border-color: #10b981;
        }

        /* Progress Bar */
        .progress-container {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .progress-bar-container {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            border-radius: 10px;
            transition: width 0.3s ease;
            width: 0%;
        }

        /* ==========================================
           COLLAPSIBLE SECTIONS
           ========================================== */
        .section {
            position: relative;
            transition: all 0.3s ease;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            margin-bottom: 15px;
        }

        .section-header h5 {
            margin: 0;
            display: flex;
            align-items: center;
        }

        .section-toggle {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #64748b;
        }

        .section-toggle:hover {
            background: #e2e8f0;
            color: #3b82f6;
        }

        .section-toggle i {
            transition: transform 0.3s ease;
        }

        .section.collapsed .section-toggle i {
            transform: rotate(180deg);
        }

        .section-content {
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease;
        }

        .section.collapsed .section-content {
            max-height: 0 !important;
            opacity: 0;
        }

        /* ==========================================
           SCROLL TO TOP BUTTON
           ========================================== */
        .scroll-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .scroll-to-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-top:hover {
            background: #2563eb;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
        }

        /* ==========================================
           MOBILE RESPONSIVE
           ========================================== */
        @media (max-width: 768px) {
            .form-navigation {
                display: none; /* Hide on mobile */
            }

            /* Mobile section toggle - always show */
            .section-toggle {
                display: flex !important;
            }

            /* Auto-collapse all sections on mobile */
            .section.collapsed .section-content {
                max-height: 0 !important;
            }

            .scroll-to-top {
                bottom: 20px;
                right: 20px;
                width: 45px;
                height: 45px;
            }
        }

        @media (min-width: 769px) {
            /* Desktop - sections expanded by default */
            .section .section-content {
                max-height: 5000px !important;
            }
        }

        /* Mobile navigation at top */
        .mobile-progress {
            display: none;
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 64px;
            z-index: 50;
        }

        @media (max-width: 768px) {
            .mobile-progress {
                display: block;
            }

            .page-header {
                position: relative;
                top: 0;
            }
        }
    </style>
</head>
<body class="loading">
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <div>
                    <h1>
                        <i data-lucide="plus-circle" style="color: #3b82f6; margin-right: 10px; width: 24px; height: 24px;"></i>
                        Create New Citation
                    </h1>
                    <p>
                        <span>Traffic Citation Form</span>
                        <span class="citation-badge">
                            <i data-lucide="file-text" class="citation-badge-icon" style="width: 14px; height: 14px;"></i>
                            <span>Citation No.:</span>
                            <span class="citation-badge-number"><?php echo htmlspecialchars($next_ticket); ?></span>
                        </span>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="/tmg/public/citations.php" class="btn btn-outline-secondary">
                        <i data-lucide="list" style="width: 18px; height: 18px;"></i> View All
                    </a>
                </div>
            </div>
        </div>

        <!-- Skeleton Loader -->
        <div class="skeleton-loader">
            <div class="ticket-container">
                <!-- Header Skeleton -->
                <div class="skeleton-header">
                    <div class="skeleton skeleton-header-line"></div>
                    <div class="skeleton skeleton-header-line"></div>
                    <div class="skeleton skeleton-header-line"></div>
                </div>

                <!-- Driver Information Skeleton -->
                <div class="skeleton-section">
                    <div class="skeleton skeleton-section-title"></div>

                    <!-- Row 1: Last Name, First Name, Middle Name -->
                    <div class="skeleton-row">
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                    </div>

                    <!-- Row 2: License Number, Address -->
                    <div class="skeleton-row">
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                    </div>

                    <!-- Row 3: Barangay, Birthday, Gender -->
                    <div class="skeleton-row">
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Information Skeleton -->
                <div class="skeleton-section">
                    <div class="skeleton skeleton-section-title"></div>

                    <!-- Row 1: Type, Plate Number -->
                    <div class="skeleton-row">
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                    </div>

                    <!-- Row 2: Owner, Make/Model -->
                    <div class="skeleton-row">
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                    </div>
                </div>

                <!-- Citation Details Skeleton -->
                <div class="skeleton-section">
                    <div class="skeleton skeleton-section-title"></div>

                    <!-- Row 1: Officer, Date, Time -->
                    <div class="skeleton-row">
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                        <div class="skeleton-field">
                            <div class="skeleton skeleton-label"></div>
                            <div class="skeleton skeleton-input"></div>
                        </div>
                    </div>

                    <!-- Row 2: Location -->
                    <div class="skeleton-field">
                        <div class="skeleton skeleton-label"></div>
                        <div class="skeleton skeleton-input"></div>
                    </div>
                </div>

                <!-- Violations Skeleton -->
                <div class="skeleton-section">
                    <div class="skeleton skeleton-section-title"></div>

                    <!-- Search Box Skeleton -->
                    <div class="skeleton skeleton-input" style="margin-bottom: 20px;"></div>

                    <!-- Category Skeletons -->
                    <div style="margin-bottom: 15px;">
                        <div class="skeleton" style="height: 45px; margin-bottom: 10px;"></div>
                        <div class="skeleton" style="height: 35px; width: 90%; margin-left: 15px; margin-bottom: 5px;"></div>
                        <div class="skeleton" style="height: 35px; width: 85%; margin-left: 15px;"></div>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <div class="skeleton" style="height: 45px; margin-bottom: 10px;"></div>
                        <div class="skeleton" style="height: 35px; width: 88%; margin-left: 15px; margin-bottom: 5px;"></div>
                        <div class="skeleton" style="height: 35px; width: 92%; margin-left: 15px;"></div>
                    </div>

                    <!-- Remarks -->
                    <div class="skeleton-field" style="margin-top: 20px;">
                        <div class="skeleton skeleton-label"></div>
                        <div class="skeleton skeleton-textarea"></div>
                    </div>
                </div>

                <!-- Footer Skeleton -->
                <div class="skeleton skeleton-footer"></div>

                <!-- Buttons Skeleton -->
                <div class="skeleton-buttons">
                    <div class="skeleton skeleton-button"></div>
                    <div class="skeleton skeleton-button"></div>
                    <div class="skeleton skeleton-button"></div>
                </div>
            </div>
        </div>

        <!-- Actual Form (hidden during loading) -->
        <?php include ROOT_PATH . '/templates/citation-form.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/citation-form.js"></script>
    <script src="../assets/js/duplicate-detection.js"></script>

    <!-- Initialize Lucide Icons -->
    <script>
        lucide.createIcons();
    </script>

    <!-- Remove loading class when page is fully loaded -->
    <script>
        // IMMEDIATELY remove loading class
        document.body.classList.remove('loading');

        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Also remove on window load as backup
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.body.classList.remove('loading');
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 100);
        });
    </script>

    <!-- Enhanced Form Features -->
    <script>
        (function() {
            'use strict';

            const form = document.getElementById('citationForm');
            const submitBtn = document.getElementById('submitBtn');
            const clearBtn = document.getElementById('clearFormBtn');
            const saveDraftBtn = document.getElementById('saveDraftBtn');
            const remarksField = document.getElementById('remarksField');
            const remarksCount = document.getElementById('remarksCount');

            let formModified = false;
            const DRAFT_KEY = 'citation_draft';
            const DRAFT_TIMESTAMP_KEY = 'citation_draft_timestamp';

            // ==========================================
            // 1. CHARACTER COUNTER
            // ==========================================
            if (remarksField && remarksCount) {
                remarksField.addEventListener('input', function() {
                    const count = this.value.length;
                    const max = this.maxLength || 500;
                    remarksCount.textContent = `(${count}/${max})`;

                    // Change color when approaching limit
                    if (count > max * 0.9) {
                        remarksCount.style.color = '#dc3545';
                    } else if (count > max * 0.7) {
                        remarksCount.style.color = '#ffc107';
                    } else {
                        remarksCount.style.color = '#6b7280';
                    }
                });
            }

            // ==========================================
            // 2. AUTO-SAVE FUNCTIONALITY
            // ==========================================

            // Check if localStorage is available
            function isLocalStorageAvailable() {
                try {
                    const test = '__localStorage_test__';
                    localStorage.setItem(test, test);
                    localStorage.removeItem(test);
                    return true;
                } catch(e) {
                    return false;
                }
            }

            const hasLocalStorage = isLocalStorageAvailable();

            function autoSaveForm() {
                if (!hasLocalStorage) return; // Skip if localStorage is blocked

                try {
                    const formData = new FormData(form);
                    const data = {};

                    for (let [key, value] of formData.entries()) {
                        if (data[key]) {
                            // Handle multiple values (like checkboxes)
                            if (Array.isArray(data[key])) {
                                data[key].push(value);
                            } else {
                                data[key] = [data[key], value];
                            }
                        } else {
                            data[key] = value;
                        }
                    }

                    localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
                    localStorage.setItem(DRAFT_TIMESTAMP_KEY, new Date().toISOString());
                } catch (error) {
                    // Silently fail - tracking prevention is blocking localStorage
                }
            }

            // Auto-save every 30 seconds
            let autoSaveInterval = setInterval(function() {
                if (formModified) {
                    autoSaveForm();
                }
            }, 30000);

            // ==========================================
            // 3. RESTORE DRAFT ON LOAD
            // ==========================================
            window.addEventListener('load', function() {
                if (!hasLocalStorage) return; // Skip if localStorage is blocked

                try {
                    const savedData = localStorage.getItem(DRAFT_KEY);
                    const savedTimestamp = localStorage.getItem(DRAFT_TIMESTAMP_KEY);

                    if (savedData && savedTimestamp) {
                        const savedDate = new Date(savedTimestamp);
                        const now = new Date();
                        const hoursSince = (now - savedDate) / (1000 * 60 * 60);

                        // Only restore if less than 24 hours old
                        if (hoursSince < 24) {
                            Swal.fire({
                                title: 'Restore Draft?',
                                html: `Found unsaved citation draft from <strong>${savedDate.toLocaleString()}</strong>`,
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Restore',
                                cancelButtonText: 'Discard'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    restoreDraft(JSON.parse(savedData));
                                    Swal.fire('Restored!', 'Your draft has been restored.', 'success');
                                } else {
                                    clearDraft();
                                }
                            });
                        } else {
                            // Clear old drafts
                            clearDraft();
                        }
                    }
                } catch (error) {
                    console.error('Failed to restore draft:', error);
                }
            });

            function restoreDraft(data) {
                Object.keys(data).forEach(key => {
                    const field = form.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'checkbox' || field.type === 'radio') {
                            if (Array.isArray(data[key])) {
                                data[key].forEach(val => {
                                    const checkbox = form.querySelector(`[name="${key}"][value="${val}"]`);
                                    if (checkbox) checkbox.checked = true;
                                });
                            } else {
                                const radio = form.querySelector(`[name="${key}"][value="${data[key]}"]`);
                                if (radio) radio.checked = true;
                            }
                        } else {
                            field.value = data[key];
                        }
                    }
                });

                // Update character counter if remarks were restored
                if (remarksField && data.remarks) {
                    remarksField.dispatchEvent(new Event('input'));
                }

                formModified = true;
            }

            function clearDraft() {
                if (!hasLocalStorage) return;
                try {
                    localStorage.removeItem(DRAFT_KEY);
                    localStorage.removeItem(DRAFT_TIMESTAMP_KEY);
                } catch (error) {
                    // Silently fail
                }
            }

            // ==========================================
            // 4. TRACK FORM MODIFICATIONS
            // ==========================================
            form.addEventListener('change', function() {
                formModified = true;
            });

            form.addEventListener('input', function() {
                formModified = true;
            });

            // ==========================================
            // 5. UNSAVED CHANGES WARNING
            // ==========================================
            window.addEventListener('beforeunload', function(e) {
                if (formModified) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Leave anyway?';
                    return e.returnValue;
                }
            });

            // ==========================================
            // 6. CLEAR FORM BUTTON
            // ==========================================
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    Swal.fire({
                        title: 'Clear Form?',
                        text: 'This will clear all form data and cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, clear it!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.reset();
                            clearDraft();
                            formModified = false;

                            // Reset character counter
                            if (remarksCount) {
                                remarksCount.textContent = '(0/500)';
                                remarksCount.style.color = '#6b7280';
                            }

                            Swal.fire('Cleared!', 'Form has been reset.', 'success');
                        }
                    });
                });
            }

            // ==========================================
            // 7. MANUAL SAVE DRAFT BUTTON
            // ==========================================
            if (saveDraftBtn) {
                saveDraftBtn.addEventListener('click', function() {
                    autoSaveForm();

                    Swal.fire({
                        title: 'Draft Saved!',
                        text: 'Your citation draft has been saved locally.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            }

            // ==========================================
            // 8. FORM SUBMISSION WITH LOADING STATE
            // ==========================================
            form.addEventListener('submit', function(e) {
                // Show loading state on submit button
                submitBtn.disabled = true;
                submitBtn.classList.add('btn-loading');
                submitBtn.innerHTML = '<i data-lucide="loader-2" class="lucide-spin me-2" style="width: 18px; height: 18px;"></i>Submitting...';
                lucide.createIcons();

                // Clear draft on successful submission
                formModified = false;
                clearInterval(autoSaveInterval);
            });

            // ==========================================
            // 9. KEYBOARD SHORTCUTS
            // ==========================================
            document.addEventListener('keydown', function(e) {
                // Ctrl+S or Cmd+S to save draft
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    autoSaveForm();

                    // Show toast notification
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Draft saved!',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                }

                // Ctrl+Enter or Cmd+Enter to submit (be careful with this)
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    if (confirm('Submit citation form?')) {
                        form.submit();
                    }
                }
            });

            // ==========================================
            // 10. CLEAR DRAFT ON SUCCESSFUL SUBMISSION
            // ==========================================
            // This will be triggered by the citation-form.js success handler
            window.addEventListener('citationSubmitted', function() {
                clearDraft();
                formModified = false;
            });

        })();
    </script>

    <!-- Violation Tabs & Search - Vanilla JS -->
    <script>
        (function() {
            'use strict';

            // Wait for DOM to be fully loaded
            function initViolationTabs() {
                const searchInput = document.getElementById('violationSearch');
                const clearSearchBtn = document.getElementById('clearSearch');
                const tabContent = document.getElementById('violationTabsContent');
                const noResultsAlert = document.getElementById('noResultsAlert');
                const searchQuerySpan = document.getElementById('searchQuery');
                const tabButtons = document.querySelectorAll('.tab-pill');
                const tabPanes = document.querySelectorAll('.tab-pane');

                if (!searchInput || !tabContent) {
                    return;
                }

            // ==========================================
            // TAB SWITCHING - VANILLA JS
            // ==========================================
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // Remove active from all tabs
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabPanes.forEach(pane => pane.classList.remove('active'));

                    // Add active to clicked tab
                    this.classList.add('active');
                    const targetPane = document.querySelector(`[data-pane="${targetTab}"]`);
                    if (targetPane) {
                        targetPane.classList.add('active');
                    }

                    // Reinitialize Lucide icons for the active pane
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            });

            // ==========================================
            // SEARCH FUNCTIONALITY
            // ==========================================
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                filterViolations(query);
            });

            // Clear search button
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    filterViolations('');
                    searchInput.focus();
                });
            }

            function filterViolations(query) {
                const allItems = tabContent.querySelectorAll('.violation-item');
                let totalVisible = 0;
                let visiblePerTab = {};

                // Initialize counters for each tab
                tabPanes.forEach(pane => {
                    const tabId = pane.getAttribute('data-pane');
                    visiblePerTab[tabId] = 0;
                });

                allItems.forEach(item => {
                    const violationText = item.getAttribute('data-violation-text') || '';
                    const label = item.querySelector('.checkbox-text');
                    const labelText = label ? label.textContent.toLowerCase() : '';

                    // Check if item matches search query
                    const matches = query === '' || violationText.includes(query) || labelText.includes(query);

                    if (matches) {
                        item.classList.remove('hidden');
                        totalVisible++;

                        // Count visible items per tab
                        const parentPane = item.closest('.tab-pane');
                        if (parentPane) {
                            const tabId = parentPane.getAttribute('data-pane');
                            visiblePerTab[tabId]++;
                        }
                    } else {
                        item.classList.add('hidden');
                    }
                });

                // Show/hide no results message
                if (totalVisible === 0 && query !== '') {
                    noResultsAlert.style.display = 'flex';
                    if (searchQuerySpan) {
                        searchQuerySpan.textContent = query;
                    }
                } else {
                    noResultsAlert.style.display = 'none';
                }

                // If searching, switch to first tab with results
                if (query !== '' && totalVisible > 0) {
                    for (let tabId in visiblePerTab) {
                        if (visiblePerTab[tabId] > 0) {
                            const firstTabWithResults = document.querySelector(`[data-tab="${tabId}"]`);
                            if (firstTabWithResults && !firstTabWithResults.classList.contains('active')) {
                                firstTabWithResults.click();
                            }
                            break;
                        }
                    }
                }
            }

            // ==========================================
            // TAB BADGE COUNTS
            // ==========================================
            function updateTabCounts() {
                const badges = document.querySelectorAll('.tab-badge');

                badges.forEach(badge => {
                    const tabName = badge.getAttribute('data-tab');
                    const checkboxes = tabContent.querySelectorAll(`.violation-checkbox[data-tab="${tabName}"]:checked`);
                    const count = checkboxes.length;

                    badge.textContent = count;

                    if (count > 0) {
                        badge.classList.add('has-selections');
                    } else {
                        badge.classList.remove('has-selections');
                    }
                });
            }

            // Listen for checkbox changes to update counts
            const allCheckboxes = tabContent.querySelectorAll('.violation-checkbox');
            allCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateTabCounts);
            });

            // Listen for "other_violation" checkbox
            const otherViolationCheckbox = document.getElementById('other_violation');
            const otherViolationInput = document.getElementById('otherViolationInput');
            if (otherViolationCheckbox && otherViolationInput) {
                otherViolationCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        otherViolationInput.style.display = 'block';
                        otherViolationInput.focus();
                    } else {
                        otherViolationInput.style.display = 'none';
                        otherViolationInput.value = '';
                    }
                });
            }

            // Initialize counts on page load
            updateTabCounts();

            // Keyboard shortcut: Ctrl+F or Cmd+F to focus search (within form)
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'f' && e.target.closest('#citationForm')) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            });
            }

            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initViolationTabs);
            } else {
                // DOM is already loaded
                initViolationTabs();
            }

        })();
    </script>


</body>
</html>