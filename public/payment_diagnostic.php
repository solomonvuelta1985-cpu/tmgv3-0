<?php
/**
 * Payment System Diagnostic Page
 * Helps identify why payments aren't displaying
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Diagnostic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-5">
<div class="container">
    <h1>Payment System Diagnostic</h1>
    <hr>

    <!-- 1. Session Check -->
    <div class="card mb-3">
        <div class="card-header">
            <h5>1. Session Status</h5>
        </div>
        <div class="card-body">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="alert alert-success">
                    ✓ You are logged in<br>
                    <strong>User ID:</strong> <?= $_SESSION['user_id'] ?><br>
                    <strong>Username:</strong> <?= $_SESSION['username'] ?? 'N/A' ?><br>
                    <strong>Full Name:</strong> <?= $_SESSION['full_name'] ?? 'N/A' ?><br>
                    <strong>Role:</strong> <?= $_SESSION['user_role'] ?? 'N/A' ?>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    ✗ You are NOT logged in!<br>
                    <a href="login.php" class="btn btn-primary mt-2">Go to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Database Check -->
    <div class="card mb-3">
        <div class="card-header">
            <h5>2. Database Status</h5>
        </div>
        <div class="card-body">
            <?php
            try {
                $pdo = getPDO();
                echo '<div class="alert alert-success">✓ Database connection: OK</div>';

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments");
                $count = $stmt->fetch();
                echo '<div class="alert alert-info">';
                echo '→ Payment records in database: <strong>' . $count['count'] . '</strong>';
                echo '</div>';

                if ($count['count'] > 0) {
                    echo '<div class="alert alert-success">';
                    echo '✓ There ARE payment records in the database';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">✗ Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>
    </div>

    <!-- 3. API Test -->
    <div class="card mb-3">
        <div class="card-header">
            <h5>3. API Test</h5>
        </div>
        <div class="card-body">
            <button id="testApiBtn" class="btn btn-primary">Test API Endpoint</button>
            <div id="apiResult" class="mt-3"></div>
        </div>
    </div>

    <!-- 4. JavaScript Console Test -->
    <div class="card mb-3">
        <div class="card-header">
            <h5>4. JavaScript Status</h5>
        </div>
        <div class="card-body">
            <div id="jsStatus" class="alert alert-warning">Checking JavaScript...</div>
        </div>
    </div>

    <!-- 5. Path Test -->
    <div class="card mb-3">
        <div class="card-header">
            <h5>5. File Paths</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Current file:</strong> <?= __FILE__ ?><br>
                <strong>Document root:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?? 'N/A' ?><br>
                <strong>Script name:</strong> <?= $_SERVER['SCRIPT_NAME'] ?? 'N/A' ?><br>
                <strong>Request URI:</strong> <?= $_SERVER['REQUEST_URI'] ?? 'N/A' ?>
            </div>
        </div>
    </div>

    <!-- Next Steps -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5>Next Steps</h5>
        </div>
        <div class="card-body">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <p><strong>You need to log in first!</strong></p>
                <a href="login.php" class="btn btn-primary">Go to Login Page</a>
            <?php else: ?>
                <p>Everything looks good! Try accessing the payment page:</p>
                <a href="payments.php" class="btn btn-success">Open Payment Management Page</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// JavaScript Test
document.getElementById('jsStatus').innerHTML = '<div class="alert alert-success">✓ JavaScript is working!</div>';

// API Test
document.getElementById('testApiBtn').addEventListener('click', function() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = '<div class="spinner-border" role="status"></div> Testing...';

    fetch('../api/payment_list.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <h6>✓ API Response Successful!</h6>
                    <strong>Success:</strong> ${data.success}<br>
                    <strong>Data count:</strong> ${data.data ? data.data.length : 0}<br>
                    <strong>Pagination:</strong> ${JSON.stringify(data.pagination)}
                </div>
                <pre class="bg-light p-3">${JSON.stringify(data, null, 2)}</pre>
            `;
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <h6>✗ API Error!</h6>
                    ${error.message}
                </div>
            `;
            console.error('API Test Error:', error);
        });
});
</script>
</body>
</html>
