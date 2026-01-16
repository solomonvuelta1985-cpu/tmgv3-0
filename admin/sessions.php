<?php
/**
 * Active Sessions Management Page
 * View and manage active login sessions
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session_manager.php';

// Require admin authentication
require_admin();
check_session_timeout();

$page_title = "Active Sessions";

// Get all active sessions for current user
$activeSessions = get_user_active_sessions($_SESSION['user_id']);
$currentSessionToken = $_SESSION['session_token'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Traffic Citation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .session-card {
            border-left: 4px solid #4e54c8;
            transition: all 0.3s;
        }

        .session-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .session-card.current {
            border-left-color: #28a745;
            background-color: #f0fff4;
        }

        .device-icon {
            font-size: 2.5rem;
            color: #4e54c8;
        }

        .badge-current {
            background-color: #28a745;
        }

        .badge-limit-warning {
            background-color: #ffc107;
        }

        .badge-limit-full {
            background-color: #dc3545;
        }
    </style>

    <!-- Application Configuration -->
    <?php include __DIR__ . '/../includes/js_config.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../public/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="page-header">
                <h2><i class="fas fa-desktop"></i> <?php echo $page_title; ?></h2>
                <span class="badge <?php
                    $sessionCount = count($activeSessions);
                    if ($sessionCount == 2) echo 'badge-limit-full';
                    elseif ($sessionCount == 1) echo 'badge-limit-warning';
                    else echo 'bg-success';
                ?>">
                    <?php echo $sessionCount; ?> / 2 Devices Active
                </span>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php
                    echo $_SESSION['flash_message'];
                    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle"></i> About Device Limits</h5>
                    <p class="mb-2">As an <strong>Admin</strong>, you can be logged in on a maximum of <strong>2 devices</strong> simultaneously for security purposes.</p>
                    <ul class="mb-0">
                        <li>Sessions automatically expire after <strong>30 minutes</strong> of inactivity</li>
                        <li>You can manually logout from specific devices below</li>
                        <li>If you've reached the limit, logout from an old device to login on a new one</li>
                    </ul>
                </div>
            </div>

            <?php if (empty($activeSessions)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No active sessions found. This might be a database sync issue.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($activeSessions as $session): ?>
                        <?php
                        $isCurrent = ($session['session_token'] === $currentSessionToken);
                        $deviceIcon = 'fa-desktop';
                        if (stripos($session['device_info'], 'Mobile') !== false) {
                            $deviceIcon = 'fa-mobile-alt';
                        } elseif (stripos($session['device_info'], 'Tablet') !== false) {
                            $deviceIcon = 'fa-tablet-alt';
                        }
                        ?>
                        <div class="col-md-6 mb-4">
                            <div class="card session-card <?php echo $isCurrent ? 'current' : ''; ?>">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-auto">
                                            <i class="fas <?php echo $deviceIcon; ?> device-icon"></i>
                                        </div>
                                        <div class="col">
                                            <h5 class="card-title">
                                                <?php echo htmlspecialchars($session['device_info']); ?>
                                                <?php if ($isCurrent): ?>
                                                    <span class="badge badge-current">Current Device</span>
                                                <?php endif; ?>
                                            </h5>

                                            <p class="mb-2">
                                                <i class="fas fa-network-wired text-muted"></i>
                                                <strong>IP:</strong> <?php echo htmlspecialchars($session['ip_address']); ?>
                                            </p>

                                            <p class="mb-2">
                                                <i class="fas fa-clock text-muted"></i>
                                                <strong>Login Time:</strong>
                                                <?php echo date('M d, Y h:i A', strtotime($session['login_time'])); ?>
                                            </p>

                                            <p class="mb-3">
                                                <i class="fas fa-history text-muted"></i>
                                                <strong>Last Active:</strong>
                                                <?php
                                                $lastActivity = strtotime($session['last_activity']);
                                                $minutesAgo = floor((time() - $lastActivity) / 60);
                                                if ($minutesAgo < 1) {
                                                    echo "Just now";
                                                } elseif ($minutesAgo < 60) {
                                                    echo $minutesAgo . " minute" . ($minutesAgo > 1 ? 's' : '') . " ago";
                                                } else {
                                                    echo date('M d, Y h:i A', $lastActivity);
                                                }
                                                ?>
                                            </p>

                                            <?php if (!$isCurrent): ?>
                                                <button class="btn btn-sm btn-danger" onclick="logoutSession(<?php echo $session['session_id']; ?>)">
                                                    <i class="fas fa-sign-out-alt"></i> Logout This Device
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-check-circle"></i> Current Session
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-3">
                    <button class="btn btn-warning" onclick="logoutAllOther()">
                        <i class="fas fa-sign-out-alt"></i> Logout All Other Devices
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function logoutSession(sessionId) {
            if (!confirm('Are you sure you want to logout from this device?')) {
                return;
            }

            // TODO: Implement API endpoint to logout specific session
            fetch(buildApiUrl('api/session_logout.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'session_id': sessionId,
                    'csrf_token': '<?php echo $_SESSION['csrf_token']; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error logging out session: ' + error.message);
            });
        }

        function logoutAllOther() {
            if (!confirm('Are you sure you want to logout from all other devices?')) {
                return;
            }

            // TODO: Implement API endpoint to logout all sessions except current
            fetch(buildApiUrl('api/session_logout_all.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'csrf_token': '<?php echo $_SESSION['csrf_token']; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error logging out sessions: ' + error.message);
            });
        }
    </script>
</body>
</html>
