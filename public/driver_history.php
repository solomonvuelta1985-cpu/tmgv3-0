<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

require_login();

$driver_id = (int)($_GET['driver_id'] ?? 0);

if ($driver_id <= 0) {
    header('Location: citations.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Citation History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/citation-list.css">
    <link rel="stylesheet" href="../assets/css/driver-history.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i data-lucide="user"></i> Driver Citation History
                </h1>
                <button class="btn btn-secondary" onclick="window.history.back()">
                    <i data-lucide="arrow-left"></i> Back
                </button>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Loading Spinner -->
            <div id="loadingSpinner" class="history-loading">
                <div class="text-center">
                    <i data-lucide="loader-2" class="lucide-spin" style="width: 48px; height: 48px; color: #0d6efd;"></i>
                    <p class="mt-3 text-muted fw-semibold">Loading driver history...</p>
                </div>
            </div>

            <!-- Content (hidden until loaded) -->
            <div id="historyContent" style="display: none;">
                <!-- Driver Info Section -->
                <div class="history-section driver-info-section mb-4">
                    <div id="driverInfo">
                        <!-- Loaded via JavaScript -->
                    </div>
                </div>

                <!-- Offense Statistics -->
                <div class="history-section stats-section mb-4">
                    <div class="section-header">
                        <i data-lucide="bar-chart-3"></i>
                        <h5>VIOLATION STATISTICS</h5>
                    </div>
                    <div id="offenseStats" class="stats-grid">
                        <!-- Loaded via JavaScript -->
                    </div>
                </div>

                <!-- Citation History Timeline -->
                <div class="history-section timeline-section">
                    <div class="section-header mb-4">
                        <i data-lucide="clock"></i>
                        <h5>CITATION TIMELINE</h5>
                    </div>
                    <div id="citationTimeline">
                        <!-- Loaded via JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Error Message (hidden by default) -->
            <div id="errorMessage" class="alert alert-danger border-0 border-start border-4" style="display: none;">
                <i data-lucide="alert-circle"></i>
                <span id="errorText"></span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/driver-history.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
