<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Require admin access
require_admin();

$pdo = getPDO();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <?php include '../public/sidebar.php'; ?>

    <div class="content" style="margin-left: 250px; padding: 20px;">
        <div class="container-fluid">
            <h2><i class="fas fa-database"></i> Database Diagnostics</h2>
            <p class="text-muted">Check your database structure and data</p>

            <div class="row mt-4">
                <!-- Table Counts -->
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Table Record Counts</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Table</th>
                                        <th>Count</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $tables = [
                                        'citations' => 'Total Citations',
                                        'violations' => 'Total Violations',
                                        'violation_types' => 'Violation Types',
                                        'apprehending_officers' => 'Officers',
                                        'citation_vehicles' => 'Citation Vehicles',
                                        'users' => 'System Users'
                                    ];

                                    foreach ($tables as $table => $label) {
                                        try {
                                            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                                            $count = $stmt->fetch()['count'];
                                            $status = $count > 0 ? '<span class="badge bg-success">Has Data</span>' : '<span class="badge bg-warning">Empty</span>';
                                            echo "<tr><td><strong>$label</strong> ($table)</td><td>$count</td><td>$status</td></tr>";
                                        } catch (Exception $e) {
                                            echo "<tr><td><strong>$label</strong> ($table)</td><td colspan='2'><span class='badge bg-danger'>Error: " . $e->getMessage() . "</span></td></tr>";
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sample Citations -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Sample Citations (Last 5)</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT citation_id, ticket_number, last_name, first_name,
                                   created_at, status, total_fine
                            FROM citations
                            ORDER BY created_at DESC
                            LIMIT 5
                        ");
                        $citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($citations)) {
                            echo '<div class="alert alert-warning">No citations found in database</div>';
                        } else {
                            echo '<table class="table table-sm">';
                            echo '<thead><tr><th>ID</th><th>Ticket</th><th>Driver</th><th>Date</th><th>Status</th><th>Fine</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($citations as $c) {
                                echo '<tr>';
                                echo '<td>' . $c['citation_id'] . '</td>';
                                echo '<td>' . $c['ticket_number'] . '</td>';
                                echo '<td>' . $c['last_name'] . ', ' . $c['first_name'] . '</td>';
                                echo '<td>' . date('M d, Y', strtotime($c['created_at'])) . '</td>';
                                echo '<td>' . $c['status'] . '</td>';
                                echo '<td>₱' . number_format($c['total_fine'], 2) . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
                    }
                    ?>
                </div>
            </div>

            <!-- Sample Violations -->
            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Sample Violations (Last 10)</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT v.*, vt.violation_type, c.ticket_number
                            FROM violations v
                            JOIN violation_types vt ON v.violation_type_id = vt.violation_type_id
                            JOIN citations c ON v.citation_id = c.citation_id
                            ORDER BY v.created_at DESC
                            LIMIT 10
                        ");
                        $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($violations)) {
                            echo '<div class="alert alert-warning">No violations found in database</div>';
                        } else {
                            echo '<table class="table table-sm">';
                            echo '<thead><tr><th>Ticket</th><th>Violation Type</th><th>Offense#</th><th>Fine</th><th>Date</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($violations as $v) {
                                echo '<tr>';
                                echo '<td>' . $v['ticket_number'] . '</td>';
                                echo '<td>' . $v['violation_type'] . '</td>';
                                echo '<td>' . $v['offense_count'] . '</td>';
                                echo '<td>₱' . number_format($v['fine_amount'], 2) . '</td>';
                                echo '<td>' . date('M d, Y', strtotime($v['created_at'])) . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
                    }
                    ?>
                </div>
            </div>

            <!-- Date Range Check -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Citation Date Range</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("
                            SELECT
                                MIN(created_at) as earliest,
                                MAX(created_at) as latest,
                                COUNT(*) as total
                            FROM citations
                        ");
                        $range = $stmt->fetch(PDO::FETCH_ASSOC);

                        echo '<p><strong>Earliest Citation:</strong> ' . ($range['earliest'] ?? 'N/A') . '</p>';
                        echo '<p><strong>Latest Citation:</strong> ' . ($range['latest'] ?? 'N/A') . '</p>';
                        echo '<p><strong>Total Citations:</strong> ' . ($range['total'] ?? '0') . '</p>';

                        // Suggest date range
                        if ($range['earliest'] && $range['latest']) {
                            $start = date('Y-m-d', strtotime($range['earliest']));
                            $end = date('Y-m-d', strtotime($range['latest']));
                            echo '<div class="alert alert-info mt-3">';
                            echo '<strong>Recommended Report Date Range:</strong><br>';
                            echo '<code>' . $start . '</code> to <code>' . $end . '</code><br>';
                            echo '<a href="../public/reports.php?start_date=' . $start . '&end_date=' . $end . '" class="btn btn-primary btn-sm mt-2">View Reports with This Range</a>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
                    }
                    ?>
                </div>
            </div>

            <div class="alert alert-info">
                <h6><i class="fas fa-lightbulb"></i> Tips:</h6>
                <ul class="mb-0">
                    <li>If any tables show "Empty", you need to add data first</li>
                    <li>Check that your date range in reports includes the dates shown above</li>
                    <li>Use the suggested date range link to view reports with data</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
