<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require authentication and check session timeout
require_login();
check_session_timeout();

// Get dashboard statistics
$stats = [
    'today_citations' => 0,
    'pending_citations' => 0
];

$pdo = getPDO();
if ($pdo) {
    // Today's citations
    $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE DATE(created_at) = CURDATE()");
    $result = $stmt->fetch();
    $stats['today_citations'] = $result['count'] ?? 0;

    // Pending citations
    $stmt = db_query("SELECT COUNT(*) as count FROM citations WHERE status = 'pending'");
    $result = $stmt->fetch();
    $stats['pending_citations'] = $result['count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Citation System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <?php echo show_flash(); ?>

            <!-- Page Title -->
            <h1 class="page-title">Traffic Citation System</h1>

            <!-- Welcome Message -->
            <div class="welcome-alert">
                Welcome<?php echo is_logged_in() ? ', ' . htmlspecialchars($_SESSION['full_name'] ?? 'User') : ''; ?>!
                Manage traffic violations, issue citations, and track enforcement activities.
            </div>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card blue">
                    <div class="stat-number"><?php echo $stats['today_citations']; ?></div>
                    <div class="stat-label">Today's Citations</div>
                </div>

                <div class="stat-card red">
                    <div class="stat-number"><?php echo $stats['pending_citations']; ?></div>
                    <div class="stat-label">Pending Citations</div>
                </div>

                <div class="stat-card green">
                    <div class="stat-number">42</div>
                    <div class="stat-label">Resolved This Week</div>
                </div>

                <div class="stat-card yellow">
                    <div class="stat-number">18</div>
                    <div class="stat-label">Overdue Citations</div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-number">7</div>
                    <div class="stat-label">Active Officers</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h2 class="section-title">Quick Actions</h2>

            <div class="actions-grid">
                <div class="action-card">
                    <div class="action-icon blue">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h5 class="action-title">New Citation</h5>
                    <p class="action-description">Issue a new traffic citation ticket</p>
                    <a href="index2.php" class="btn btn-primary">
                        <i class="fas fa-file-alt me-2"></i>Create
                    </a>
                </div>

                <div class="action-card">
                    <div class="action-icon cyan">
                        <i class="fas fa-search"></i>
                    </div>
                    <h5 class="action-title">Search Records</h5>
                    <p class="action-description">Find citations by ticket number or driver</p>
                    <a href="search.php" class="btn btn-outline-primary">
                        <i class="fas fa-search me-2"></i>Search
                    </a>
                </div>

                <div class="action-card">
                    <div class="action-icon green">
                        <i class="fas fa-list-alt"></i>
                    </div>
                    <h5 class="action-title">View Citations</h5>
                    <p class="action-description">Browse all issued citations</p>
                    <a href="citations.php" class="btn btn-outline-primary">
                        <i class="fas fa-eye me-2"></i>View All
                    </a>
                </div>

                <div class="action-card">
                    <div class="action-icon yellow">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h5 class="action-title">Reports</h5>
                    <p class="action-description">Generate statistics and reports</p>
                    <a href="reports.php" class="btn btn-outline-primary">
                        <i class="fas fa-chart-line me-2"></i>Reports
                    </a>
                </div>
            </div>

            <!-- System Information -->
            <div class="info-card">
                <h5 class="info-title">System Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="info-list">
                            <li><i class="fas fa-check success"></i>CSRF Protection: Active</li>
                            <li><i class="fas fa-check success"></i>Rate Limiting: Active</li>
                            <li><i class="fas fa-check success"></i>Input Sanitization: Active</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="info-list">
                            <li><i class="fas fa-check success"></i>PDO Prepared Statements: Active</li>
                            <li><i class="fas fa-check success"></i>Security Headers: Active</li>
                            <li><i class="fas fa-database primary"></i>Database: traffic_system</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
