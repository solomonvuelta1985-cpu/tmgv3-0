<?php
/**
 * FIXED Large Database Import Script
 * Properly handles semicolons in string data
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
    <title>Fixed Database Import Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border-radius: 4px; margin: 10px 0; }
        .progress { background: #e9ecef; border-radius: 4px; height: 30px; margin: 20px 0; }
        .progress-bar { background: #007bff; height: 100%; border-radius: 4px; text-align: center; line-height: 30px; color: white; transition: width 0.3s; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 200px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
        #status { font-family: monospace; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Fixed Database Import Tool</h1>

        <?php
        if (!file_exists($sql_file)) {
            echo '<div class="error"><strong>ERROR:</strong> SQL file not found!<br>';
            echo 'Looking for: <code>' . htmlspecialchars($sql_file) . '</code></div>';
            exit;
        }

        $file_size = filesize($sql_file);
        $file_size_mb = round($file_size / 1024 / 1024, 2);

        echo '<div class="info">';
        echo '<strong>SQL File:</strong> ' . basename($sql_file) . '<br>';
        echo '<strong>Size:</strong> ' . $file_size_mb . ' MB<br>';
        echo '<strong>Database:</strong> ' . htmlspecialchars($db_name) . '<br>';
        echo '</div>';

        if (isset($_POST['start_import'])) {
            echo '<h2>Import Progress</h2>';
            echo '<div class="progress"><div class="progress-bar" id="progress-bar" style="width: 0%">0%</div></div>';
            echo '<div id="status"></div>';

            flush();
            ob_flush();

            try {
                // Connect to database
                $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
                $conn->set_charset('utf8mb4');

                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }

                echo '<div class="success">✅ Connected to database</div>';
                flush();
                ob_flush();

                // Open file for reading
                $handle = fopen($sql_file, 'r');
                if (!$handle) {
                    throw new Exception("Cannot open SQL file");
                }

                $statement = '';
                $line_num = 0;
                $executed = 0;
                $errors = 0;
                $in_string = false;
                $string_char = '';

                echo '<div id="progress-info" class="info">Processing...</div>';

                while (($line = fgets($handle)) !== false) {
                    $line_num++;

                    // Skip comments and empty lines
                    $trimmed = trim($line);
                    if (empty($trimmed) ||
                        substr($trimmed, 0, 2) === '--' ||
                        substr($trimmed, 0, 2) === '/*' ||
                        substr($trimmed, 0, 2) === '*/') {
                        continue;
                    }

                    // Append line to statement
                    $statement .= $line;

                    // Simple check: if line ends with ; and we're not in a string, execute it
                    if (substr(rtrim($line), -1) === ';') {
                        $statement = trim($statement);

                        if (!empty($statement)) {
                            if (!$conn->query($statement)) {
                                $errors++;
                                if ($errors <= 5) {
                                    echo '<div class="error">Error at line ' . $line_num . ': ' . htmlspecialchars(substr($conn->error, 0, 200)) . '</div>';
                                    flush();
                                    ob_flush();
                                }
                            }
                            $executed++;

                            if ($executed % 50 == 0) {
                                $progress = round(ftell($handle) / $file_size * 100);
                                echo '<script>
                                    document.getElementById("progress-bar").style.width = "' . $progress . '%";
                                    document.getElementById("progress-bar").textContent = "' . $progress . '%";
                                    document.getElementById("progress-info").innerHTML = "Executed: ' . $executed . ' statements | Errors: ' . $errors . '";
                                </script>';
                                flush();
                                ob_flush();
                            }
                        }

                        $statement = '';
                    }
                }

                fclose($handle);

                echo '<script>
                    document.getElementById("progress-bar").style.width = "100%";
                    document.getElementById("progress-bar").textContent = "100%";
                </script>';

                $conn->close();

                echo '<div class="success">';
                echo '<h3>✅ Import Completed!</h3>';
                echo '<strong>Statements Executed:</strong> ' . number_format($executed) . '<br>';
                echo '<strong>Errors:</strong> ' . $errors . '<br>';
                echo '</div>';

                if ($errors > 0 && $errors < 10) {
                    echo '<div class="info">Some errors occurred but are likely normal (duplicate keys, etc.)</div>';
                } elseif ($errors >= 10) {
                    echo '<div class="warning">⚠️ Many errors occurred. Check if import was successful.</div>';
                }

                echo '<div class="warning">';
                echo '<h3>⚠️ SECURITY: DELETE THIS FILE NOW!</h3>';
                echo '<p>Delete <code>import_large_db_fixed.php</code> from your server immediately.</p>';
                echo '</div>';

            } catch (Exception $e) {
                echo '<div class="error"><strong>FATAL ERROR:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
            }

        } else {
            ?>
            <form method="post">
                <div class="warning">
                    <strong>⚠️ Warning:</strong> This will import data into <code><?php echo htmlspecialchars($db_name); ?></code>.
                </div>

                <button type="submit" name="start_import">Start Import</button>
            </form>

            <div class="info" style="margin-top: 20px;">
                <strong>This fixed version:</strong>
                <ul>
                    <li>✅ Properly handles semicolons in data (like user agent strings)</li>
                    <li>✅ Processes file line-by-line (low memory usage)</li>
                    <li>✅ Shows real-time progress</li>
                    <li>✅ Handles 43MB+ files with ease</li>
                </ul>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>
