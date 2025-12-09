<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();
check_session_timeout();

// Fetch dashboard statistics
$stats = [];
try {
    // Total citations
    $stmt = db_query("SELECT COUNT(*) as total FROM citations");
    $stats['total_citations'] = $stmt->fetch()['total'] ?? 0;

    // Today's citations
    $stmt = db_query("SELECT COUNT(*) as today FROM citations WHERE DATE(created_at) = CURDATE()");
    $stats['today_citations'] = $stmt->fetch()['today'] ?? 0;

    // Total drivers
    $stmt = db_query("SELECT COUNT(*) as total FROM drivers");
    $stats['total_drivers'] = $stmt->fetch()['total'] ?? 0;

    // Total users
    $stmt = db_query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch()['total'] ?? 0;

    // Pending citations
    $stmt = db_query("SELECT COUNT(*) as pending FROM citations WHERE status = 'pending'");
    $stats['pending_citations'] = $stmt->fetch()['pending'] ?? 0;

    // Total fines collected
    $stmt = db_query("SELECT COALESCE(SUM(total_fine), 0) as total FROM citations WHERE status = 'paid'");
    $stats['total_fines'] = $stmt->fetch()['total'] ?? 0;

    // Recent citations
    $stmt = db_query(
        "SELECT ticket_number, CONCAT(last_name, ', ', first_name) as driver_name,
                apprehension_datetime, status, total_fine, created_at
         FROM citations ORDER BY created_at DESC LIMIT 10"
    );
    $recent_citations = $stmt->fetchAll();

    // Top violations
    $stmt = db_query(
        "SELECT vt.violation_type, COUNT(*) as count
         FROM violations v
         JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
         GROUP BY vt.violation_type_id
         ORDER BY count DESC LIMIT 5"
    );
    $top_violations = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $stats = array_fill_keys(['total_citations', 'today_citations', 'total_drivers', 'total_users', 'pending_citations', 'total_fines'], 0);
    $recent_citations = [];
    $top_violations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
</head>
<body>
    <?php include '../includes/loader.php'; ?>
    <?php include '../public/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h3>Admin Dashboard</h3>
                    <p>Overview of traffic citation system</p>
                </div>
                <div class="date-display">
                    <?php echo date('F d, Y'); ?>
                </div>
            </div>

            <?php echo show_flash(); ?>

            <!-- Statistics Cards - Clean Flat Design -->
            <div class="row g-3 mb-4">
                <div class="col-md-4 col-lg-2">
                    <div class="stat-card blue">
                        <div class="stat-value"><?php echo number_format($stats['total_citations']); ?></div>
                        <div class="stat-label">Total Citations</div>
                    </div>
                </div>

                <div class="col-md-4 col-lg-2">
                    <div class="stat-card green">
                        <div class="stat-value"><?php echo number_format($stats['today_citations']); ?></div>
                        <div class="stat-label">Today's Citations</div>
                    </div>
                </div>

                <div class="col-md-4 col-lg-2">
                    <div class="stat-card red">
                        <div class="stat-value"><?php echo number_format($stats['pending_citations']); ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>

                <div class="col-md-4 col-lg-2">
                    <div class="stat-card purple">
                        <div class="stat-value"><?php echo number_format($stats['total_drivers']); ?></div>
                        <div class="stat-label">Drivers</div>
                    </div>
                </div>

                <div class="col-md-4 col-lg-2">
                    <div class="stat-card yellow">
                        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">System Users</div>
                    </div>
                </div>

                <div class="col-md-4 col-lg-2">
                    <div class="stat-card cyan">
                        <div class="stat-value">₱<?php echo number_format($stats['total_fines'], 0); ?></div>
                        <div class="stat-label">Fines Collected</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Citations -->
                <div class="col-lg-8">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <span>Recent Citations</span>
                            <a href="../public/citations.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Ticket #</th>
                                            <th>Driver</th>
                                            <th>Date</th>
                                            <th>Fine</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_citations)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">
                                                    No citations found
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_citations as $citation): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($citation['ticket_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($citation['driver_name']); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($citation['apprehension_datetime'])); ?></td>
                                                    <td>₱<?php echo number_format($citation['total_fine'], 2); ?></td>
                                                    <td>
                                                        <?php
                                                        $status_class = match($citation['status']) {
                                                            'paid' => 'bg-success',
                                                            'pending' => 'bg-warning text-dark',
                                                            'contested' => 'bg-info',
                                                            'dismissed' => 'bg-secondary',
                                                            default => 'bg-light text-dark'
                                                        };
                                                        ?>
                                                        <span class="badge <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($citation['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Violations -->
                <div class="col-lg-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <span>Top Violations</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($top_violations)): ?>
                                <p class="text-center text-muted">
                                    No violation data
                                </p>
                            <?php else: ?>
                                <?php foreach ($top_violations as $index => $violation): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                            <?php echo htmlspecialchars($violation['violation_type']); ?>
                                        </div>
                                        <span class="badge bg-secondary"><?php echo $violation['count']; ?></span>
                                    </div>
                                    <?php if ($index < count($top_violations) - 1): ?>
                                        <hr class="my-2">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
