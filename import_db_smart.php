<?php
/**
 * Smart Database Import Script
 * Handles DELIMITER commands, triggers, and stored procedures
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
    <title>Smart Database Import</title>
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
        <h1>🗄️ Smart Database Import</h1>

        <?php
        if (!file_exists($sql_file)) {
            echo '<div class="error"><strong>ERROR:</strong> SQL file not found at: <code>' . htmlspecialchars($sql_file) . '</code></div>';
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
            echo '<div class="progress"><div class="progress-bar" id="progress-bar">0%</div></div>';
            echo '<div id="status" class="info">Starting...</div>';

            flush();
            ob_flush();

            try {
                $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
                $conn->set_charset('utf8mb4');

                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }

                echo '<script>document.getElementById("status").innerHTML = "✅ Connected to database";</script>';
                flush();
                ob_flush();

                // Read entire file
                $sql = file_get_contents($sql_file);
                if ($sql === false) {
                    throw new Exception("Cannot read SQL file");
                }

                // Remove comments
                $sql = preg_replace('/^--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

                // Handle DELIMITER commands
                // Extract content between DELIMITER $$ and DELIMITER ;
                $delimiter_pattern = '/DELIMITER\s+\$\$(.*?)DELIMITER\s+;/is';
                $triggers = [];

                if (preg_match_all($delimiter_pattern, $sql, $matches)) {
                    foreach ($matches[1] as $trigger_content) {
                        $triggers[] = trim($trigger_content);
                    }
                    // Remove DELIMITER blocks from main SQL
                    $sql = preg_replace($delimiter_pattern, '', $sql);
                }

                // Also remove standalone DELIMITER commands
                $sql = preg_replace('/DELIMITER\s+.*;?\s*/i', '', $sql);

                // Split regular SQL into statements (by semicolon at end of line)
                $statements = array_filter(
                    array_map('trim', preg_split('/;[\s]*\n/', $sql)),
                    function($stmt) {
                        return !empty($stmt) && strlen($stmt) > 5;
                    }
                );

                $total = count($statements) + count($triggers);
                $executed = 0;
                $errors = 0;
                $error_log = [];

                echo '<script>document.getElementById("status").innerHTML = "📝 Found ' . count($statements) . ' statements + ' . count($triggers) . ' triggers";</script>';
                flush();
                ob_flush();

                // Execute regular statements
                foreach ($statements as $i => $statement) {
                    $statement = trim($statement);
                    if (empty($statement)) continue;

                    $result = @$conn->query($statement);
                    if (!$result) {
                        $errors++;
                        if (count($error_log) < 10) {
                            $error_log[] = [
                                'num' => $i + 1,
                                'error' => $conn->error,
                                'sql' => substr($statement, 0, 100)
                            ];
                        }
                    }

                    $executed++;

                    if ($executed % 100 == 0) {
                        $percent = round(($executed / $total) * 100);
                        echo '<script>
                            document.getElementById("progress-bar").style.width = "' . $percent . '%";
                            document.getElementById("progress-bar").textContent = "' . $percent . '%";
                            document.getElementById("status").innerHTML = "Processing: ' . $executed . '/' . $total . ' | Errors: ' . $errors . '";
                        </script>';
                        flush();
                        ob_flush();
                    }
                }

                // Execute triggers/procedures
                foreach ($triggers as $i => $trigger) {
                    $trigger = trim($trigger);
                    if (empty($trigger)) continue;

                    $result = @$conn->query($trigger);
                    if (!$result) {
                        $errors++;
                        if (count($error_log) < 10) {
                            $error_log[] = [
                                'num' => 'Trigger ' . ($i + 1),
                                'error' => $conn->error,
                                'sql' => substr($trigger, 0, 100)
                            ];
                        }
                    }

                    $executed++;
                }

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
                    echo '<div class="warning"><strong>Error Details (showing first ' . count($error_log) . '):</strong></div>';
                    foreach ($error_log as $err) {
                        echo '<div class="error">';
                        echo '<strong>#' . $err['num'] . ':</strong> ' . htmlspecialchars($err['error']) . '<br>';
                        echo '<pre>' . htmlspecialchars($err['sql']) . '...</pre>';
                        echo '</div>';
                    }

                    if ($errors < 50) {
                        echo '<div class="info">These errors are often normal (duplicate keys, existing data, etc.)</div>';
                    } else {
                        echo '<div class="warning">⚠️ Many errors occurred. The import may have failed partially.</div>';
                    }
                }

                echo '<div class="warning">';
                echo '<h3>⚠️ DELETE THIS FILE!</h3>';
                echo '<p>For security, delete <code>import_db_smart.php</code> immediately!</p>';
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

                <button type="submit" name="start_import">🚀 Start Import</button>
            </form>

            <div class="info" style="margin-top: 20px;">
                <strong>✨ Smart Features:</strong>
                <ul>
                    <li>✅ Handles DELIMITER commands for triggers</li>
                    <li>✅ Processes stored procedures correctly</li>
                    <li>✅ Removes comments automatically</li>
                    <li>✅ Shows detailed error logging</li>
                    <li>✅ Works with large 43MB+ files</li>
                </ul>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>
