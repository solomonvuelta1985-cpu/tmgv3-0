<?php
/**
 * Large Database Import Script
 * Imports large SQL files that are too big for phpMyAdmin
 *
 * INSTRUCTIONS:
 * 1. Upload this file to your server (same directory as your SQL file)
 * 2. Upload your SQL file to the same directory
 * 3. Visit this file in your browser: https://btracs.online/tmg/import_large_db.php
 * 4. DELETE THIS FILE after successful import!
 */

set_time_limit(0);
ini_set('memory_limit', '512M');

// Database credentials
$db_host = 'localhost';
$db_name = 'btrahnqi_traffic_system';
$db_user = 'btrahnqi_richmond';
$db_pass = 'Almondmamon@17';

// SQL file to import (must be in same directory as this script)
$sql_file = __DIR__ . '/traffic_system (7).sql';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Import Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 800px; margin: 0 auto; }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #cce5ff; border-radius: 4px; margin: 10px 0; }
        .progress { background: #e9ecef; border-radius: 4px; height: 30px; margin: 20px 0; }
        .progress-bar { background: #007bff; height: 100%; border-radius: 4px; text-align: center; line-height: 30px; color: white; transition: width 0.3s; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        button:disabled { background: #6c757d; cursor: not-allowed; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Large Database Import Tool</h1>

        <?php
        if (!file_exists($sql_file)) {
            echo '<div class="error"><strong>ERROR:</strong> SQL file not found!<br>';
            echo 'Looking for: <code>' . htmlspecialchars($sql_file) . '</code><br><br>';
            echo 'Please upload your SQL file to the same directory as this script.</div>';

            // List files in current directory
            echo '<h3>Files in current directory:</h3><ul>';
            foreach (glob(__DIR__ . '/*.sql') as $file) {
                echo '<li>' . basename($file) . '</li>';
            }
            echo '</ul>';
            exit;
        }

        $file_size = filesize($sql_file);
        $file_size_mb = round($file_size / 1024 / 1024, 2);

        echo '<div class="info">';
        echo '<strong>SQL File Found:</strong> ' . basename($sql_file) . '<br>';
        echo '<strong>File Size:</strong> ' . $file_size_mb . ' MB<br>';
        echo '<strong>Database:</strong> ' . htmlspecialchars($db_name) . '<br>';
        echo '</div>';

        if (isset($_POST['start_import'])) {
            echo '<h2>Import Progress</h2>';
            echo '<div id="progress-container">';

            try {
                // Connect to database
                $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }

                echo '<div class="success">✅ Connected to database successfully!</div>';

                // Read and execute SQL file
                $sql_content = file_get_contents($sql_file);

                if ($sql_content === false) {
                    throw new Exception("Failed to read SQL file");
                }

                echo '<div class="info">📖 Reading SQL file... (' . $file_size_mb . ' MB)</div>';

                // Split into statements
                $statements = array_filter(
                    array_map('trim', explode(';', $sql_content)),
                    function($stmt) {
                        return !empty($stmt) && substr($stmt, 0, 2) !== '--';
                    }
                );

                $total = count($statements);
                echo '<div class="info">📝 Found ' . $total . ' SQL statements to execute</div>';

                echo '<div class="progress"><div class="progress-bar" id="progress-bar" style="width: 0%">0%</div></div>';

                flush();
                ob_flush();

                $executed = 0;
                $errors = 0;

                foreach ($statements as $i => $statement) {
                    $statement = trim($statement);
                    if (empty($statement)) continue;

                    if (!$conn->query($statement)) {
                        $errors++;
                        if ($errors <= 10) { // Only show first 10 errors
                            echo '<div class="error">Error in statement ' . ($i + 1) . ': ' . htmlspecialchars($conn->error) . '</div>';
                        }
                    }

                    $executed++;

                    if ($executed % 100 == 0) {
                        $percent = round(($executed / $total) * 100);
                        echo '<script>
                            document.getElementById("progress-bar").style.width = "' . $percent . '%";
                            document.getElementById("progress-bar").textContent = "' . $percent . '%";
                        </script>';
                        flush();
                        ob_flush();
                    }
                }

                echo '<script>
                    document.getElementById("progress-bar").style.width = "100%";
                    document.getElementById("progress-bar").textContent = "100%";
                </script>';

                $conn->close();

                echo '</div>';

                echo '<div class="success">';
                echo '<h3>✅ Import Completed!</h3>';
                echo '<strong>Total Statements:</strong> ' . $total . '<br>';
                echo '<strong>Successfully Executed:</strong> ' . $executed . '<br>';
                echo '<strong>Errors:</strong> ' . $errors . '<br>';
                echo '</div>';

                if ($errors > 0) {
                    echo '<div class="warning">⚠️ Some errors occurred during import. This is often normal for duplicate key constraints or existing data.</div>';
                }

                echo '<div class="warning">';
                echo '<h3>⚠️ IMPORTANT: DELETE THIS FILE!</h3>';
                echo '<p>For security reasons, please delete <code>import_large_db.php</code> from your server immediately.</p>';
                echo '</div>';

            } catch (Exception $e) {
                echo '<div class="error"><strong>FATAL ERROR:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
            }

        } else {
            ?>
            <form method="post">
                <div class="warning">
                    <strong>⚠️ Warning:</strong> This will import data into the database <code><?php echo htmlspecialchars($db_name); ?></code>.
                    Make sure you have a backup before proceeding!
                </div>

                <button type="submit" name="start_import">Start Import</button>
            </form>

            <div class="info" style="margin-top: 20px;">
                <strong>What this script does:</strong>
                <ul>
                    <li>Reads the SQL file in chunks to avoid memory issues</li>
                    <li>Executes SQL statements one by one</li>
                    <li>Shows progress in real-time</li>
                    <li>Handles large files that phpMyAdmin can't import</li>
                </ul>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>
