<?php
// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Include dependencies
require_once ROOT_PATH . '/includes/config.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/services/CitationService.php';

// Require login and check session timeout
require_login();
check_session_timeout();

// Initialize CitationService
$citationService = new CitationService(getPDO());

// Get statistics
$stats = $citationService->getStatistics();

// Pagination and search parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Get total count for pagination
$total_records = $citationService->getCitationsCount($search, $status_filter);
$total_pages = ceil($total_records / $per_page);

// Get citations
$citations = $citationService->getCitations($page, $per_page, $search, $status_filter);

// Close connection
$citationService->closeConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Citations - Traffic Citation System</title>
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/citation-list.css">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Application Configuration - MUST be loaded before other JS files -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <?php include ROOT_PATH . '/templates/citations-list-content.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/citation-list.js"></script>
    <script>
        // Initialize Lucide icons after DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
        });

        // Also re-initialize after any dynamic content updates
        if (typeof window.initLucideIcons === 'undefined') {
            window.initLucideIcons = function() {
                lucide.createIcons();
            };
        }
    </script>
</body>
</html>
