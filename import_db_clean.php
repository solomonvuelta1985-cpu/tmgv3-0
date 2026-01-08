<?php
/**
 * Clean Database Import Script
 * Removes DEFINER clauses and handles triggers properly
 *
 * DELETE THIS FILE after successful import!
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

// Database credentials
$db_host = 'localhost';
$db_name = 'btrahnqi_traffic_system';
$db_user = 'btrahnqi_richmond';
$db_pass = 'Almondmamon@17';

// SQL file to import
$sql_file = __DIR__ . '/traffic_system (7).sql';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Clean Database Import</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 900px; margin: 0 auto; }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; font-size: 12px; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border-radius: 4px; margin: 10px 0; }
        .progress { background: #e9ecef; border-radius: 4px; height: 30px; margin: 20px 0; }
        .progress-bar { background: #007bff; height: 100%; border-radius: 4px; text-align: center; line-height: 30px; color: white; transition: width 0.3s; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 5px; border-radius: 4px; font-size: 11px; max-height: 100px; overflow: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Clean Database Import</h1>

        <?php
        if (!file_exists($sql_file)) {
            echo '<div class="error"><strong>ERROR:</strong> SQL file not found: <code>' . htmlspecialchars($sql_file) . '</code></div>';
            exit;
        }

        $file_size = filesize($sql_file);
        $file_size_mb = round($file_size / 1024 / 1024, 2);

        echo '<div class="info">';
        echo '<strong>File:</strong> ' . basename($sql_file) . ' (' . $file_size_mb . ' MB)<br>';
        echo '<strong>Database:</strong> ' . htmlspecialchars($db_name);
        echo '</div>';

        if (isset($_POST['start_import'])) {
            echo '<h2>Import Progress</h2>';
            echo '<div class="info">Reading and cleaning SQL file...</div>';
            flush();
            ob_flush();

            try {
                $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
                $conn->set_charset('utf8mb4');

                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }

                echo '<div class="success">✅ Connected to database</div>';
                flush();
                ob_flush();

                // Read and clean SQL file
                $sql = file_get_contents($sql_file);
                if ($sql === false) {
                    throw new Exception("Cannot read SQL file");
                }

                echo '<div class="info">🧹 Cleaning SQL...</div>';
                flush();
                ob_flush();

                // Remove DEFINER clauses
                $sql = preg_replace('/DEFINER\s*=\s*`[^`]+`@`[^`]+`/i', '', $sql);
                $sql = preg_replace('/DEFINER\s*=\s*\'[^\']+\'@\'[^\']+\'/i', '', $sql);

                // Remove SQL_SECURITY clauses that might cause issues
                $sql = preg_replace('/SQL SECURITY (DEFINER|INVOKER)/i', '', $sql);

                // Remove comments
                $sql = preg_replace('/^--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

                // Remove DELIMITER commands and extract trigger content
                $triggers = [];
                if (preg_match_all('/DELIMITER\s+\$\$(.*?)DELIMITER\s+;/is', $sql, $matches)) {
                    foreach ($matches[1] as $trigger_block) {
                        // Split multiple triggers in the block
                        $trigger_statements = preg_split('/\$\$\s*(?=CREATE)/i', $trigger_block);
                        foreach ($trigger_statements as $trigger) {
                            $trigger = trim($trigger);
                            $trigger = rtrim($trigger, '$');
                            $trigger = trim($trigger);
                            if (!empty($trigger)) {
                                $triggers[] = $trigger;
                            }
                        }
                    }
                    $sql = preg_replace('/DELIMITER\s+\$\$.*?DELIMITER\s+;/is', '', $sql);
                }

                // Remove any remaining DELIMITER commands
                $sql = preg_replace('/DELIMITER.*/i', '', $sql);

                echo '<div class="success">✅ Cleaned DEFINER clauses and DELIMITER commands</div>';
                echo '<div class="info">Found ' . count($triggers) . ' triggers/procedures</div>';
                flush();
                ob_flush();

                // Disable foreign key checks temporarily
                $conn->query('SET FOREIGN_KEY_CHECKS=0');

                echo '<div class="progress"><div class="progress-bar" id="progress-bar">0%</div></div>';
                echo '<div id="status" class="info">Processing...</div>';
                flush();
                ob_flush();

                // Split into statements
                $statements = array_filter(
                    array_map('trim', preg_split('/;[\s]*$/m', $sql)),
                    function($stmt) {
                        return !empty($stmt) && strlen($stmt) > 5;
                    }
                );

                $total = count($statements) + count($triggers);
                $executed = 0;
                $errors = 0;
                $error_log = [];

                // Execute regular statements
                foreach ($statements as $i => $statement) {
                    $statement = trim($statement);
                    if (empty($statement) || substr($statement, 0, 2) === '--') {
                        continue;
                    }

                    $result = @$conn->multi_query($statement . ';');
                    if (!$result && $conn->error) {
                        $errors++;
                        if (count($error_log) < 10) {
                            $error_log[] = [
                                'num' => $i + 1,
                                'error' => $conn->error,
                                'sql' => substr($statement, 0, 150)
                            ];
                        }
                    }

                    // Clear any remaining results
                    while ($conn->more_results()) {
                        $conn->next_result();
                        if ($res = $conn->store_result()) {
                            $res->free();
                        }
                    }

                    $executed++;

                    if ($executed % 100 == 0) {
                        $percent = round(($executed / $total) * 100);
                        echo '<script>
                            document.getElementById("progress-bar").style.width = "' . $percent . '%";
                            document.getElementById("progress-bar").textContent = "' . $percent . '%";
                            document.getElementById("status").innerHTML = "Executed: ' . $executed . '/' . $total . ' | Errors: ' . $errors . '";
                        </script>';
                        flush();
                        ob_flush();
                    }
                }

                echo '<div class="info">Executing triggers...</div>';
                flush();
                ob_flush();

                // Execute triggers
                foreach ($triggers as $i => $trigger) {
                    $trigger = trim($trigger);
                    if (empty($trigger)) continue;

                    $result = @$conn->query($trigger);
                    if (!$result) {
                        $errors++;
                        if (count($error_log) < 15) {
                            $error_log[] = [
                                'num' => 'Trigger ' . ($i + 1),
                                'error' => $conn->error,
                                'sql' => substr($trigger, 0, 150)
                            ];
                        }
                    }
                    $executed++;
                }

                // Re-enable foreign key checks
                $conn->query('SET FOREIGN_KEY_CHECKS=1');

                echo '<script>
                    document.getElementById("progress-bar").style.width = "100%";
                    document.getElementById("progress-bar").textContent = "100%";
                </script>';

                $conn->close();

                echo '<div class="success">';
                echo '<h3>✅ Import Completed!</h3>';
                echo '<strong>Total Items:</strong> ' . number_format($total) . '<br>';
                echo '<strong>Executed:</strong> ' . number_format($executed) . '<br>';
                echo '<strong>Errors:</strong> ' . $errors . '<br>';
                echo '</div>';

                if (!empty($error_log)) {
                    echo '<div class="warning"><strong>Errors (first ' . count($error_log) . '):</strong></div>';
                    foreach ($error_log as $err) {
                        echo '<div class="error">';
                        echo '<strong>' . $err['num'] . ':</strong> ' . htmlspecialchars($err['error']) . '<br>';
                        echo '<pre>' . htmlspecialchars($err['sql']) . '...</pre>';
                        echo '</div>';
                    }

                    if ($errors < 50) {
                        echo '<div class="info">✅ These errors are likely normal (duplicate keys, etc.)</div>';
                    }
                }

                echo '<div class="warning">';
                echo '<h3>⚠️ IMPORTANT: DELETE THIS FILE!</h3>';
                echo '<p>Delete <code>import_db_clean.php</code> from your server now!</p>';
                echo '</div>';

                echo '<div class="info">';
                echo '<strong>Next Step:</strong> Visit <a href="test_db_connection.php" target="_blank">test_db_connection.php</a> to verify the import.';
                echo '</div>';

            } catch (Exception $e) {
                echo '<div class="error"><strong>FATAL ERROR:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
            }

        } else {
            ?>
            <form method="post">
                <div class="warning">
                    <strong>⚠️ This will import into:</strong> <code><?php echo htmlspecialchars($db_name); ?></code>
                </div>

                <button type="submit" name="start_import">🚀 Start Clean Import</button>
            </form>

            <div class="info" style="margin-top: 20px;">
                <strong>✨ This script will:</strong>
                <ul>
                    <li>✅ Remove all DEFINER clauses (fixes permission errors)</li>
                    <li>✅ Handle DELIMITER commands properly</li>
                    <li>✅ Process triggers and stored procedures</li>
                    <li>✅ Temporarily disable foreign key checks</li>
                    <li>✅ Work with your hosting permissions</li>
                </ul>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>
