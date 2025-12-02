<?php
/**
 * Configuration Test Page
 * Use this to verify that the JavaScript configuration is loading correctly
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Test - TMG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Application Configuration -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>

    <style>
        body {
            padding: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .test-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 800px;
            margin: 0 auto;
        }
        .test-result {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
        }
        .test-pass {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        .test-fail {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        .test-info {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            color: #0c5460;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .status-icon {
            font-size: 24px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="test-card">
        <h1 class="text-center mb-4">
            <i class="fas fa-flask"></i> Configuration Test
        </h1>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Purpose:</strong> This page tests if the JavaScript configuration is loading correctly.
        </div>

        <hr>

        <!-- PHP Configuration -->
        <h3><i class="fas fa-server"></i> PHP Configuration</h3>
        <div class="test-result test-info">
            <strong>BASE_PATH:</strong> <code><?php echo defined('BASE_PATH') ? BASE_PATH : 'NOT DEFINED'; ?></code>
        </div>
        <div class="test-result test-info">
            <strong>Current URL:</strong> <code><?php echo $_SERVER['REQUEST_URI'] ?? 'UNKNOWN'; ?></code>
        </div>
        <div class="test-result test-info">
            <strong>Server Name:</strong> <code><?php echo $_SERVER['HTTP_HOST'] ?? 'UNKNOWN'; ?></code>
        </div>

        <hr>

        <!-- JavaScript Tests -->
        <h3><i class="fas fa-code"></i> JavaScript Tests</h3>

        <div id="jsTests">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Running tests...</span>
            </div>
            <p>Running JavaScript tests...</p>
        </div>

        <hr>

        <!-- API URL Examples -->
        <h3><i class="fas fa-link"></i> API URL Examples</h3>
        <div id="urlExamples"></div>

        <hr>

        <div class="text-center mt-4">
            <a href="process_payment.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Payment Processing
            </a>
            <a href="index2.php" class="btn btn-success">
                <i class="fas fa-file-alt"></i> Create Citation
            </a>
        </div>
    </div>

    <script>
        // Wait for DOM to load
        document.addEventListener('DOMContentLoaded', function() {
            runTests();
        });

        function runTests() {
            const resultsDiv = document.getElementById('jsTests');
            const urlExamplesDiv = document.getElementById('urlExamples');
            const tests = [];

            // Test 1: Check if APP_CONFIG exists
            tests.push({
                name: 'APP_CONFIG object exists',
                pass: typeof window.APP_CONFIG !== 'undefined',
                details: typeof window.APP_CONFIG !== 'undefined' ?
                    `✓ window.APP_CONFIG is defined` :
                    `✗ window.APP_CONFIG is undefined`
            });

            // Test 2: Check if BASE_PATH is set
            tests.push({
                name: 'BASE_PATH is configured',
                pass: typeof window.APP_CONFIG !== 'undefined' && window.APP_CONFIG.BASE_PATH !== undefined,
                details: typeof window.APP_CONFIG !== 'undefined' && window.APP_CONFIG.BASE_PATH !== undefined ?
                    `✓ BASE_PATH = "${window.APP_CONFIG.BASE_PATH}"` :
                    `✗ BASE_PATH is not set`
            });

            // Test 3: Check if buildApiUrl exists
            tests.push({
                name: 'buildApiUrl() function exists',
                pass: typeof window.buildApiUrl === 'function',
                details: typeof window.buildApiUrl === 'function' ?
                    `✓ buildApiUrl is a function` :
                    `✗ buildApiUrl is ${typeof window.buildApiUrl}`
            });

            // Test 4: Check if buildPublicUrl exists
            tests.push({
                name: 'buildPublicUrl() function exists',
                pass: typeof window.buildPublicUrl === 'function',
                details: typeof window.buildPublicUrl === 'function' ?
                    `✓ buildPublicUrl is a function` :
                    `✗ buildPublicUrl is ${typeof window.buildPublicUrl}`
            });

            // Test 5: Test buildApiUrl functionality
            let apiUrlTest = false;
            let apiUrlResult = '';
            try {
                apiUrlResult = buildApiUrl('api/test.php');
                apiUrlTest = apiUrlResult.includes('/api/test.php');
            } catch (e) {
                apiUrlResult = 'ERROR: ' + e.message;
            }
            tests.push({
                name: 'buildApiUrl() works correctly',
                pass: apiUrlTest,
                details: apiUrlTest ?
                    `✓ buildApiUrl('api/test.php') = "${apiUrlResult}"` :
                    `✗ ${apiUrlResult}`
            });

            // Test 6: Test buildPublicUrl functionality
            let publicUrlTest = false;
            let publicUrlResult = '';
            try {
                publicUrlResult = buildPublicUrl('public/test.php');
                publicUrlTest = publicUrlResult.includes('/public/test.php');
            } catch (e) {
                publicUrlResult = 'ERROR: ' + e.message;
            }
            tests.push({
                name: 'buildPublicUrl() works correctly',
                pass: publicUrlTest,
                details: publicUrlTest ?
                    `✓ buildPublicUrl('public/test.php') = "${publicUrlResult}"` :
                    `✗ ${publicUrlResult}`
            });

            // Render test results
            let html = '';
            let passCount = 0;
            tests.forEach(test => {
                if (test.pass) passCount++;
                html += `
                    <div class="test-result ${test.pass ? 'test-pass' : 'test-fail'}">
                        <span class="status-icon">${test.pass ? '✅' : '❌'}</span>
                        <strong>${test.name}</strong><br>
                        <small>${test.details}</small>
                    </div>
                `;
            });

            html = `
                <div class="alert ${passCount === tests.length ? 'alert-success' : 'alert-warning'}">
                    <h5>
                        ${passCount === tests.length ?
                            '<i class="fas fa-check-circle"></i> All Tests Passed!' :
                            '<i class="fas fa-exclamation-triangle"></i> Some Tests Failed'}
                    </h5>
                    <p>Passed: ${passCount}/${tests.length}</p>
                </div>
            ` + html;

            resultsDiv.innerHTML = html;

            // Show URL examples
            if (typeof window.buildApiUrl === 'function' && typeof window.buildPublicUrl === 'function') {
                urlExamplesDiv.innerHTML = `
                    <div class="test-result test-info">
                        <strong>Payment API:</strong><br>
                        <code>${buildApiUrl('api/payment_process.php')}</code>
                    </div>
                    <div class="test-result test-info">
                        <strong>Citation API:</strong><br>
                        <code>${buildApiUrl('api/insert_citation.php')}</code>
                    </div>
                    <div class="test-result test-info">
                        <strong>Receipt URL:</strong><br>
                        <code>${buildPublicUrl('public/receipt.php?receipt=TEST123')}</code>
                    </div>
                    <div class="test-result test-info">
                        <strong>Check Pending Payment:</strong><br>
                        <code>${buildApiUrl('api/check_pending_payment.php?citation_id=1')}</code>
                    </div>
                `;
            } else {
                urlExamplesDiv.innerHTML = `
                    <div class="test-result test-fail">
                        ❌ Cannot generate examples - helper functions not loaded
                    </div>
                `;
            }

            // Log to console
            console.log('=== CONFIGURATION TEST RESULTS ===');
            console.log('Tests Passed:', passCount + '/' + tests.length);
            console.log('APP_CONFIG:', window.APP_CONFIG);
            console.log('buildApiUrl:', typeof window.buildApiUrl);
            console.log('buildPublicUrl:', typeof window.buildPublicUrl);
            console.log('===================================');
        }
    </script>
</body>
</html>
