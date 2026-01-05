<?php
/**
 * Web-based Excel Import Interface
 *
 * Access via browser: http://localhost/tmg/web_import.php
 *
 * Features:
 * - Dry run mode (preview without importing)
 * - Live import mode
 * - Real-time progress display
 * - Comprehensive results reporting
 */

session_start();
require_once __DIR__ . '/services/ExcelImporter.php';

// Default Excel file
$defaultExcelFile = __DIR__ . '/NEW 2023-2024 CITATION TICKET  (Responses) (24) (1).xlsx';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $dryRun = ($action === 'dry_run');
    $excelFile = $_POST['excel_file'] ?? $defaultExcelFile;

    // Validate file exists
    if (!file_exists($excelFile)) {
        $error = "Excel file not found: " . htmlspecialchars($excelFile);
    } else {
        // Run the import
        try {
            set_time_limit(600); // 10 minutes max
            ini_set('memory_limit', '512M'); // Increase memory for large imports

            $importer = new ExcelImporter($excelFile);
            $result = $importer->import($dryRun);

            // Store result in session for display
            $_SESSION['import_result'] = $result;
            $_SESSION['import_mode'] = $dryRun ? 'DRY RUN' : 'LIVE IMPORT';

            // Redirect to avoid form resubmission
            header('Location: web_import.php?show_results=1');
            exit;

        } catch (Exception $e) {
            $error = "Import failed: " . $e->getMessage();
        }
    }
}

// Get results from session if available
$showResults = isset($_GET['show_results']) && isset($_SESSION['import_result']);
$result = $showResults ? $_SESSION['import_result'] : null;
$importMode = $showResults ? $_SESSION['import_mode'] : null;

// Clear session results after displaying
if ($showResults) {
    unset($_SESSION['import_result']);
    unset($_SESSION['import_mode']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Citation Import System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-error {
            background: #fee;
            border-color: #c33;
            color: #c33;
        }

        .alert-success {
            background: #efe;
            border-color: #3c3;
            color: #3c3;
        }

        .alert-warning {
            background: #ffc;
            border-color: #cc3;
            color: #885;
        }

        .alert-info {
            background: #def;
            border-color: #39c;
            color: #39c;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        button {
            flex: 1;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
        }

        .btn-dry-run {
            background: #ffc107;
            color: #333;
        }

        .btn-dry-run:hover {
            background: #ffb300;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
        }

        .btn-live {
            background: #28a745;
            color: white;
        }

        .btn-live:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }

        .results {
            margin-top: 30px;
        }

        .results-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .results-header h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
            border: 2px solid #e9ecef;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section h3 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .item-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        .item-list .item {
            padding: 8px;
            border-bottom: 1px solid #dee2e6;
        }

        .item-list .item:last-child {
            border-bottom: none;
        }

        .sql-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 10px;
        }

        .sql-comment {
            color: #6c757d;
        }

        .back-button {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
        }

        .back-button:hover {
            background: #5a6268;
        }

        .warning-icon::before {
            content: "⚠️ ";
        }

        .success-icon::before {
            content: "✅ ";
        }

        .info-icon::before {
            content: "ℹ️ ";
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Excel Citation Import System</h1>
            <p>Import traffic citation data from Excel into the database</p>
        </div>

        <div class="content">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$showResults): ?>
                <!-- Import Form -->
                <div class="alert alert-info">
                    <span class="info-icon"></span>
                    <strong>Before you start:</strong> Make sure you've created the staging tables by running the SQL migration file.
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="excel_file">Excel File Path:</label>
                        <input type="text"
                               id="excel_file"
                               name="excel_file"
                               value="<?php echo htmlspecialchars($defaultExcelFile); ?>"
                               placeholder="Full path to Excel file">
                    </div>

                    <div class="alert alert-warning">
                        <span class="warning-icon"></span>
                        <strong>DRY RUN (Recommended First!):</strong> Test the import without making any database changes. This will show you what would happen.
                    </div>

                    <div class="alert alert-success">
                        <span class="success-icon"></span>
                        <strong>LIVE IMPORT:</strong> Actually import the data into your database. Only use this after verifying the dry run results!
                    </div>

                    <div class="button-group">
                        <button type="submit" name="action" value="dry_run" class="btn-dry-run">
                            🔍 Run Dry Run (Preview Only)
                        </button>
                        <button type="submit" name="action" value="live" class="btn-live"
                                onclick="return confirm('Are you sure you want to import data into the database? This will create real records!');">
                            🚀 Run Live Import
                        </button>
                    </div>
                </form>

                <div class="section" style="margin-top: 40px;">
                    <h3>📋 What This Import Does</h3>
                    <div class="item-list">
                        <div class="item">✅ Phase 1: Extracts and normalizes all Excel data</div>
                        <div class="item">✅ Phase 2: Identifies and removes exact duplicates</div>
                        <div class="item">✅ Phase 3: Groups multi-violation citations</div>
                        <div class="item">✅ Phase 4: Resolves ticket number conflicts</div>
                        <div class="item">✅ Phase 5: Auto-generates missing ticket numbers (AUT- prefix)</div>
                        <div class="item">✅ Phase 6: Imports and deduplicates drivers</div>
                        <div class="item">✅ Phase 7: Creates citation records</div>
                        <div class="item">✅ Phase 8: Imports violations with fuzzy matching</div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Results Display -->
                <div class="results">
                    <?php if ($result['success']): ?>
                        <div class="results-header">
                            <h2>✅ Import <?php echo $importMode; ?> Completed Successfully!</h2>
                            <?php if ($importMode === 'DRY RUN'): ?>
                                <p style="color: #666; margin-top: 10px;">
                                    No data was actually imported. This was a preview of what would happen.
                                </p>
                            <?php else: ?>
                                <p style="color: #666; margin-top: 10px;">
                                    Data has been successfully imported into the database.
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php $stats = $result['stats']; ?>

                        <div class="section">
                            <h3>📊 Import Statistics</h3>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="number"><?php echo number_format($stats['total_rows']); ?></div>
                                    <div class="label">Total Excel Rows</div>
                                </div>
                                <div class="stat-card">
                                    <div class="number"><?php echo number_format($stats['citations_created']); ?></div>
                                    <div class="label">Citations Created</div>
                                </div>
                                <div class="stat-card" style="border: 2px solid #28a745;">
                                    <div class="number" style="color: #28a745;"><?php echo number_format($stats['citations_paid'] ?? 0); ?></div>
                                    <div class="label">Citations Marked PAID</div>
                                </div>
                                <div class="stat-card">
                                    <div class="number"><?php echo number_format($stats['drivers_created']); ?></div>
                                    <div class="label">Drivers Created</div>
                                </div>
                                <div class="stat-card">
                                    <div class="number"><?php echo number_format($stats['violations_created']); ?></div>
                                    <div class="label">Violations Created</div>
                                </div>
                                <div class="stat-card">
                                    <div class="number"><?php echo number_format($stats['duplicates_removed']); ?></div>
                                    <div class="label">Duplicates Removed</div>
                                </div>
                                <div class="stat-card">
                                    <div class="number"><?php echo number_format($stats['tickets_generated']); ?></div>
                                    <div class="label">Tickets Generated</div>
                                </div>
                            </div>
                        </div>

                        <?php $report = $result['report']; ?>

                        <?php if (!empty($report['auto_generated_tickets'])): ?>
                            <div class="section">
                                <h3><span class="warning-icon"></span>Auto-Generated Tickets (Need Manual Review)</h3>
                                <div class="alert alert-warning">
                                    These tickets were auto-generated with AUT- prefix. You should update them with real ticket numbers.
                                </div>
                                <div class="item-list">
                                    <?php foreach (array_slice($report['auto_generated_tickets'], 0, 10) as $ticket): ?>
                                        <div class="item">
                                            Row <?php echo str_pad($ticket['excel_row'], 4, '0', STR_PAD_LEFT); ?>:
                                            <strong><?php echo htmlspecialchars($ticket['final_ticket']); ?></strong>
                                            (<?php echo htmlspecialchars($ticket['generation_reason']); ?>)
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($report['auto_generated_tickets']) > 10): ?>
                                        <div class="item" style="font-style: italic;">
                                            ... and <?php echo count($report['auto_generated_tickets']) - 10; ?> more
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($report['ticket_conflicts'])): ?>
                            <div class="section">
                                <h3><span class="warning-icon"></span>Ticket Conflicts Resolved</h3>
                                <div class="item-list">
                                    <?php foreach (array_slice($report['ticket_conflicts'], 0, 10) as $conflict): ?>
                                        <div class="item">
                                            Ticket <strong><?php echo htmlspecialchars($conflict['original_ticket']); ?></strong>:
                                            <?php echo htmlspecialchars($conflict['conflict_type']); ?> conflict -
                                            Generated: <?php echo htmlspecialchars($conflict['tickets_generated']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($report['ticket_conflicts']) > 10): ?>
                                        <div class="item" style="font-style: italic;">
                                            ... and <?php echo count($report['ticket_conflicts']) - 10; ?> more
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($report['multi_violation_groups'])): ?>
                            <div class="section">
                                <h3><span class="success-icon"></span>Multi-Violation Citations</h3>
                                <div class="item-list">
                                    <?php foreach (array_slice($report['multi_violation_groups'], 0, 10) as $group): ?>
                                        <div class="item">
                                            Ticket <strong><?php echo htmlspecialchars($group['final_ticket']); ?></strong>:
                                            Combined <?php echo $group['row_count']; ?> Excel rows into 1 citation
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($report['multi_violation_groups']) > 10): ?>
                                        <div class="item" style="font-style: italic;">
                                            ... and <?php echo count($report['multi_violation_groups']) - 10; ?> more
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($report['fuzzy_matches'])): ?>
                            <div class="section">
                                <h3><span class="warning-icon"></span>Fuzzy Violation Matches (Verify Accuracy)</h3>
                                <div class="item-list">
                                    <?php foreach (array_slice($report['fuzzy_matches'], 0, 10) as $match): ?>
                                        <div class="item">
                                            '<strong><?php echo htmlspecialchars(substr($match['excel_text'], 0, 40)); ?></strong>' →
                                            '<?php echo htmlspecialchars(substr($match['violation_type_name'], 0, 40)); ?>'
                                            (<?php echo htmlspecialchars($match['match_type']); ?>,
                                            <?php echo $match['match_confidence']; ?>% confidence)
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($report['fuzzy_matches']) > 10): ?>
                                        <div class="item" style="font-style: italic;">
                                            ... and <?php echo count($report['fuzzy_matches']) - 10; ?> more
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="section">
                            <h3>📋 Next Steps</h3>
                            <div class="item-list">
                                <div class="item">1. Review auto-generated tickets (AUT- prefix) and update with real numbers</div>
                                <div class="item">2. Verify ticket conflict resolutions (tickets with -A, -B suffixes)</div>
                                <div class="item">3. Check fuzzy violation matches for accuracy</div>
                                <div class="item">4. Run data integrity checks</div>
                            </div>
                        </div>

                        <div class="section">
                            <h3>🔍 SQL Queries to Run</h3>

                            <p><strong>Find auto-generated tickets:</strong></p>
                            <div class="sql-block">SELECT * FROM citations WHERE ticket_number LIKE 'AUT-%';</div>

                            <p style="margin-top: 20px;"><strong>Find conflict-resolved tickets:</strong></p>
                            <div class="sql-block">SELECT * FROM citations WHERE ticket_number LIKE '%-_';</div>

                            <p style="margin-top: 20px;"><strong>View import logs:</strong></p>
                            <div class="sql-block">SELECT * FROM import_logs
WHERE batch_id = '<?php echo htmlspecialchars($result['batch_id']); ?>'
ORDER BY created_at DESC;</div>

                            <p style="margin-top: 20px;"><strong>View import summary:</strong></p>
                            <div class="sql-block">SELECT * FROM import_batches
WHERE batch_id = '<?php echo htmlspecialchars($result['batch_id']); ?>';</div>
                        </div>

                        <div class="section">
                            <p><strong>Batch ID:</strong> <code><?php echo htmlspecialchars($result['batch_id']); ?></code></p>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-error">
                            <h2>❌ Import Failed</h2>
                            <p><?php echo htmlspecialchars($result['error']); ?></p>
                        </div>

                        <div class="section">
                            <h3>Check import logs:</h3>
                            <div class="sql-block">SELECT * FROM import_logs
WHERE batch_id = '<?php echo htmlspecialchars($result['batch_id']); ?>'
AND log_level = 'error';</div>
                        </div>
                    <?php endif; ?>

                    <a href="web_import.php" class="back-button">← Back to Import Form</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
