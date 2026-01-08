<?php
/**
 * Debug Reports - Check what dates your data actually spans
 * DELETE THIS FILE after debugging!
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Reports Data</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug Reports Data</h1>

        <?php
        try {
            $pdo = getPDO();

            if (!$pdo) {
                throw new Exception("Database connection failed");
            }

            echo '<div class="success">✅ Database connected successfully</div>';

            // Check citations table
            echo '<h2>Citations Table Analysis</h2>';

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM citations");
            $total = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<div class="info"><strong>Total Citations:</strong> ' . number_format($total['total']) . '</div>';

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM citations WHERE deleted_at IS NULL");
            $active = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<div class="info"><strong>Active Citations (not deleted):</strong> ' . number_format($active['total']) . '</div>';

            $stmt = $pdo->query("SELECT COUNT(*) as total FROM citations WHERE deleted_at IS NOT NULL");
            $deleted = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<div class="info"><strong>Deleted Citations:</strong> ' . number_format($deleted['total']) . '</div>';

            // Date range of data
            echo '<h3>Date Range of Your Data</h3>';
            $stmt = $pdo->query("SELECT
                MIN(created_at) as earliest_date,
                MAX(created_at) as latest_date,
                MIN(DATE(created_at)) as earliest_day,
                MAX(DATE(created_at)) as latest_day
                FROM citations
                WHERE deleted_at IS NULL");
            $dates = $stmt->fetch(PDO::FETCH_ASSOC);

            echo '<table>';
            echo '<tr><th>Metric</th><th>Value</th></tr>';
            echo '<tr><td>Earliest Citation</td><td>' . htmlspecialchars($dates['earliest_date'] ?? 'N/A') . '</td></tr>';
            echo '<tr><td>Latest Citation</td><td>' . htmlspecialchars($dates['latest_date'] ?? 'N/A') . '</td></tr>';
            echo '<tr><td>Earliest Day</td><td>' . htmlspecialchars($dates['earliest_day'] ?? 'N/A') . '</td></tr>';
            echo '<tr><td>Latest Day</td><td>' . htmlspecialchars($dates['latest_day'] ?? 'N/A') . '</td></tr>';
            echo '</table>';

            // Test current report query
            echo '<h3>Test Report Query (2000-01-01 to today)</h3>';
            $start_date = '2000-01-01';
            $end_date = date('Y-m-d');

            echo '<div class="info">Date Range: ' . $start_date . ' to ' . $end_date . '</div>';

            $sql = "SELECT
                    COUNT(*) as total_citations,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(total_fine) as total_fines_issued,
                    SUM(CASE WHEN status = 'paid' THEN total_fine ELSE 0 END) as total_fines_collected
                    FROM citations c
                    WHERE DATE(c.created_at) BETWEEN :start_date AND :end_date
                    AND c.deleted_at IS NULL";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':start_date', $start_date);
            $stmt->bindValue(':end_date', $end_date);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            echo '<table>';
            echo '<tr><th>Metric</th><th>Value</th></tr>';
            foreach ($result as $key => $value) {
                echo '<tr><td>' . htmlspecialchars($key) . '</td><td>' . htmlspecialchars($value ?? '0') . '</td></tr>';
            }
            echo '</table>';

            // Test without date filter
            echo '<h3>Test Query WITHOUT Date Filter</h3>';

            $sql = "SELECT
                    COUNT(*) as total_citations,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(total_fine) as total_fines_issued
                    FROM citations c
                    WHERE c.deleted_at IS NULL";

            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            echo '<table>';
            echo '<tr><th>Metric</th><th>Value</th></tr>';
            foreach ($result as $key => $value) {
                echo '<tr><td>' . htmlspecialchars($key) . '</td><td>' . htmlspecialchars($value ?? '0') . '</td></tr>';
            }
            echo '</table>';

            // Sample of data
            echo '<h3>Sample Citations (First 10)</h3>';
            $stmt = $pdo->query("SELECT
                citation_id,
                ticket_number,
                created_at,
                DATE(created_at) as created_date,
                status,
                total_fine,
                deleted_at
                FROM citations
                ORDER BY created_at DESC
                LIMIT 10");
            $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo '<table>';
            echo '<tr><th>ID</th><th>Ticket #</th><th>Created At</th><th>Created Date</th><th>Status</th><th>Fine</th><th>Deleted?</th></tr>';
            foreach ($samples as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['citation_id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['ticket_number']) . '</td>';
                echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                echo '<td>' . htmlspecialchars($row['created_date']) . '</td>';
                echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                echo '<td>₱' . number_format($row['total_fine'], 2) . '</td>';
                echo '<td>' . ($row['deleted_at'] ? 'Yes' : 'No') . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            // Check payments table
            echo '<h2>Payments Table Analysis</h2>';
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM payments");
            $payments = $stmt->fetch(PDO::FETCH_ASSOC);
            echo '<div class="info"><strong>Total Payments:</strong> ' . number_format($payments['total']) . '</div>';

        } catch (Exception $e) {
            echo '<div class="error"><strong>ERROR:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        ?>

        <hr>
        <div class="info">
            <strong>Next Steps:</strong>
            <ol>
                <li>Check if the date range matches your data</li>
                <li>Verify deleted_at filter isn't removing all records</li>
                <li>Delete this debug file after troubleshooting</li>
            </ol>
        </div>
    </div>
</body>
</html>
