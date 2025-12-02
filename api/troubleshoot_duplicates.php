<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplicate Detection Troubleshooter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .check-item { padding: 10px; margin: 5px 0; border-left: 4px solid #ccc; background: white; }
        .check-item.pass { border-left-color: #28a745; }
        .check-item.fail { border-left-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-wrench"></i> Duplicate Detection Troubleshooter</h1>
        <p class="lead">Running diagnostic checks...</p>

        <?php
        define('ROOT_PATH', dirname(__DIR__));
        require_once ROOT_PATH . '/includes/config.php';
        require_once ROOT_PATH . '/services/DuplicateDetectionService.php';

        function checkPass($message) {
            echo "<div class='check-item pass'><i class='fas fa-check-circle text-success'></i> $message</div>";
        }

        function checkFail($message) {
            echo "<div class='check-item fail'><i class='fas fa-times-circle text-danger'></i> $message</div>";
        }

        // Check 1: JavaScript file exists
        $jsFile = ROOT_PATH . '/assets/js/duplicate-detection.js';
        if (file_exists($jsFile)) {
            checkPass("JavaScript file exists: /assets/js/duplicate-detection.js");
        } else {
            checkFail("JavaScript file NOT found: $jsFile");
        }

        // Check 2: API endpoint exists
        $apiFile = ROOT_PATH . '/api/check_duplicates.php';
        if (file_exists($apiFile)) {
            checkPass("API endpoint exists: /api/check_duplicates.php");
        } else {
            checkFail("API endpoint NOT found: $apiFile");
        }

        // Check 3: Database connection
        try {
            $pdo = getPDO();
            checkPass("Database connection successful");
        } catch (Exception $e) {
            checkFail("Database connection failed: " . $e->getMessage());
        }

        // Check 4: Service can be instantiated
        try {
            $service = new DuplicateDetectionService($pdo);
            checkPass("DuplicateDetectionService loaded successfully");
        } catch (Exception $e) {
            checkFail("DuplicateDetectionService failed: " . $e->getMessage());
        }

        // Check 5: Test data exists
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM citations WHERE last_name LIKE '%ROSETE%'");
            $count = $stmt->fetch()['count'];
            if ($count > 0) {
                checkPass("Test data found: $count citations with 'ROSETE' in last name");
            } else {
                checkFail("No test data found - no citations with 'ROSETE' in last name");
            }
        } catch (Exception $e) {
            checkFail("Could not check test data: " . $e->getMessage());
        }

        // Check 6: Test search functionality
        try {
            $service = new DuplicateDetectionService($pdo);
            $results = $service->directSearch('ROSETE');
            if (count($results) > 0) {
                checkPass("Search functionality works: Found " . count($results) . " results for 'ROSETE'");
                echo "<div class='alert alert-info mt-3'>";
                echo "<strong>Sample result:</strong><br>";
                $sample = $results[0];
                echo "Name: {$sample['last_name']}, {$sample['first_name']}<br>";
                echo "DOB: " . ($sample['date_of_birth'] ?? 'N/A') . "<br>";
                echo "Total Citations: {$sample['total_citations']}";
                echo "</div>";
            } else {
                checkFail("Search returned 0 results for 'ROSETE'");
            }
        } catch (Exception $e) {
            checkFail("Search functionality failed: " . $e->getMessage());
        }

        // Check 7: Test API endpoint directly
        echo "<div class='card mt-4'>";
        echo "<div class='card-header bg-primary text-white'><h5 class='mb-0'>Test API Endpoint Directly</h5></div>";
        echo "<div class='card-body'>";
        echo "<p>Click the button below to test the API endpoint:</p>";
        echo "<button class='btn btn-primary' onclick='testAPI()'>Test API Now</button>";
        echo "<div id='apiResult' class='mt-3'></div>";
        echo "</div></div>";

        echo "<div class='card mt-4'>";
        echo "<div class='card-header bg-success text-white'><h5 class='mb-0'>Next Steps</h5></div>";
        echo "<div class='card-body'>";
        echo "<ol>";
        echo "<li><strong>Open index2.php:</strong> <a href='/tmg/public/index2.php' target='_blank'>http://localhost/tmg/public/index2.php</a></li>";
        echo "<li><strong>Press F12</strong> to open browser console</li>";
        echo "<li><strong>Type:</strong> First Name = RICHMOND, Last Name = ROSETE</li>";
        echo "<li><strong>Click outside</strong> the field (blur event)</li>";
        echo "<li><strong>Watch console</strong> for debug messages</li>";
        echo "</ol>";
        echo "</div></div>";
        ?>

        <script>
        function testAPI() {
            const resultDiv = document.getElementById('apiResult');
            resultDiv.innerHTML = '<div class="alert alert-info">Testing API endpoint...</div>';

            const testData = {
                first_name: 'RICHMOND',
                last_name: 'ROSETE',
                license_number: '',
                plate_number: '',
                date_of_birth: '',
                barangay: ''
            };

            fetch('/tmg/api/check_duplicates.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(testData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('API Response:', data);

                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong><i class="fas fa-check-circle"></i> API Works!</strong><br>
                            Found ${data.match_count} duplicate(s)<br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <strong><i class="fas fa-times-circle"></i> API Error</strong><br>
                            ${data.error || data.message}<br>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <strong><i class="fas fa-times-circle"></i> Request Failed</strong><br>
                        ${error.message}
                    </div>
                `;
            });
        }
        </script>
    </div>
</body>
</html>
