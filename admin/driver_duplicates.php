<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../services/DuplicateDetectionService.php';

// Require admin access
require_admin();
check_session_timeout();

// Initialize service
$duplicateService = new DuplicateDetectionService(getPDO());

// Handle merge action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['merge_drivers'])) {
    $primary_driver_id = (int)$_POST['primary_driver_id'];
    $duplicate_driver_ids = $_POST['duplicate_driver_ids'] ?? [];

    if ($primary_driver_id && !empty($duplicate_driver_ids)) {
        try {
            $pdo = getPDO();
            $pdo->beginTransaction();

            // Update all citations from duplicate drivers to point to primary driver
            foreach ($duplicate_driver_ids as $dup_id) {
                $dup_id = (int)$dup_id;
                if ($dup_id !== $primary_driver_id) {
                    $stmt = $pdo->prepare("
                        UPDATE citations
                        SET driver_id = :primary_id
                        WHERE driver_id = :duplicate_id
                    ");
                    $stmt->execute([
                        ':primary_id' => $primary_driver_id,
                        ':duplicate_id' => $dup_id
                    ]);
                }
            }

            $pdo->commit();
            set_flash_message('Drivers merged successfully!', 'success');
            header('Location: driver_duplicates.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash_message('Error merging drivers: ' . $e->getMessage(), 'danger');
        }
    }
}

// Get potential duplicates
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$potential_duplicates = [];

if ($search) {
    // Try to intelligently parse the search query
    $names = preg_split('/\s+/', trim($search));

    // Try different name combinations
    if (count($names) >= 2) {
        // Try: "FIRST LAST" format
        $driver_info_1 = [
            'first_name' => $names[0],
            'last_name' => implode(' ', array_slice($names, 1)),
            'license_number' => $search,
            'plate_number' => $search
        ];

        // Try: "LAST FIRST" format (reversed)
        $driver_info_2 = [
            'first_name' => implode(' ', array_slice($names, 1)),
            'last_name' => $names[0],
            'license_number' => $search,
            'plate_number' => $search
        ];

        // Get matches from both attempts
        $matches_1 = $duplicateService->findPossibleDuplicates($driver_info_1);
        $matches_2 = $duplicateService->findPossibleDuplicates($driver_info_2);

        // Merge and deduplicate results
        $all_matches = array_merge($matches_1, $matches_2);
        $seen_ids = [];
        foreach ($all_matches as $match) {
            if (!in_array($match['driver_id'], $seen_ids)) {
                $potential_duplicates[] = $match;
                $seen_ids[] = $match['driver_id'];
            }
        }

        // Sort by confidence
        usort($potential_duplicates, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
    } else {
        // Single word search - try as both first and last name
        $driver_info = [
            'first_name' => $search,
            'last_name' => $search,
            'license_number' => $search,
            'plate_number' => $search
        ];

        $potential_duplicates = $duplicateService->findPossibleDuplicates($driver_info);
    }

    // If no results found, try direct database search as fallback
    if (empty($potential_duplicates)) {
        $potential_duplicates = $duplicateService->directSearch($search);
    }
}

$duplicateService->closeConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Duplicate Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .content {
            padding: 20px;
        }
        .driver-card {
            transition: all 0.3s;
            cursor: pointer;
        }
        .driver-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .driver-card.selected {
            border-color: #0d6efd;
            background: #e7f3ff;
        }
        .driver-card.primary {
            border-color: #198754;
            background: #d1f4e0;
        }
    </style>
</head>
<body>
    <?php include '../public/sidebar.php'; ?>

    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3><i class="fas fa-user-friends"></i> Driver Duplicate Management</h3>
                    <p class="text-muted mb-0">Find and merge duplicate driver records</p>
                </div>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <?php echo show_flash(); ?>

            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-search"></i> Search for Duplicates</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-8">
                            <input type="text" name="search" class="form-control"
                                   placeholder="Enter name, license number, or plate number..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <small class="text-muted">
                                Examples: "RICHMOND ROSETE", "ROSETE RICHMOND", "ABC1234", "L1234567"
                            </small>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results -->
            <?php if ($search && !empty($potential_duplicates)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            Found <?php echo count($potential_duplicates); ?> Potential Duplicates
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Select the primary driver record (keep) and duplicate records (merge into primary):</p>

                        <form method="POST" id="mergeForm">
                            <input type="hidden" name="merge_drivers" value="1">
                            <input type="hidden" name="primary_driver_id" id="primaryDriverId">

                            <div class="row">
                                <?php foreach ($potential_duplicates as $index => $driver): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card driver-card" data-driver-id="<?php echo $driver['driver_id']; ?>">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($driver['last_name'] . ', ' . $driver['first_name']); ?></strong>
                                                    <span class="badge bg-<?php echo $driver['confidence'] >= 80 ? 'danger' : ($driver['confidence'] >= 60 ? 'warning' : 'info'); ?>">
                                                        <?php echo $driver['confidence']; ?>% match
                                                    </span>
                                                </div>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-success set-primary" data-driver-id="<?php echo $driver['driver_id']; ?>">
                                                        <i class="fas fa-star"></i> Set as Primary
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary toggle-duplicate" data-driver-id="<?php echo $driver['driver_id']; ?>">
                                                        <i class="fas fa-check"></i> Mark Duplicate
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <p class="mb-1"><strong>Match:</strong> <?php echo $driver['reason']; ?></p>
                                                <p class="mb-1"><strong>License:</strong> <?php echo htmlspecialchars($driver['license_number'] ?: 'N/A'); ?></p>
                                                <p class="mb-1"><strong>Plate:</strong> <?php echo htmlspecialchars($driver['plate_mv_engine_chassis_no'] ?: 'N/A'); ?></p>
                                                <p class="mb-1"><strong>DOB:</strong> <?php echo htmlspecialchars($driver['date_of_birth'] ?: 'N/A'); ?></p>
                                                <p class="mb-1"><strong>Barangay:</strong> <?php echo htmlspecialchars($driver['barangay'] ?: 'N/A'); ?></p>
                                                <p class="mb-0"><strong>Total Citations:</strong> <?php echo $driver['total_citations']; ?></p>
                                            </div>
                                        </div>
                                        <input type="checkbox" name="duplicate_driver_ids[]" value="<?php echo $driver['driver_id']; ?>"
                                               class="duplicate-checkbox" id="dup_<?php echo $driver['driver_id']; ?>" style="display:none;">
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary btn-lg" id="mergeBtn" disabled>
                                    <i class="fas fa-compress-arrows-alt"></i> Merge Selected Drivers
                                </button>
                                <button type="button" class="btn btn-secondary btn-lg" onclick="location.reload()">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($search): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No matching drivers found for "<?php echo htmlspecialchars($search); ?>".
                    Try searching with different name formats (e.g., "LAST FIRST" or "FIRST LAST") or use license/plate numbers.
                </div>
            <?php endif; ?>

            <!-- Instructions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-question-circle"></i> How to Use</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Search for a driver by name, license number, or plate number</li>
                        <li>Review the potential duplicate matches found</li>
                        <li>Click "Set as Primary" on the record you want to KEEP (usually the most complete one)</li>
                        <li>Click "Mark Duplicate" on any records that should be MERGED into the primary</li>
                        <li>Click "Merge Selected Drivers" to combine the records</li>
                    </ol>
                    <div class="alert alert-warning mt-3">
                        <strong>Warning:</strong> Merging drivers will update all citations from duplicate records to point to the primary driver.
                        This action cannot be undone. Please review carefully before merging.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let primaryDriverId = null;
        let duplicateDriverIds = [];

        document.querySelectorAll('.set-primary').forEach(btn => {
            btn.addEventListener('click', function() {
                const driverId = this.dataset.driverId;
                primaryDriverId = driverId;

                // Update UI
                document.querySelectorAll('.driver-card').forEach(card => {
                    card.classList.remove('primary');
                });
                this.closest('.driver-card').classList.add('primary');

                document.getElementById('primaryDriverId').value = driverId;

                // Enable merge button if duplicates selected
                updateMergeButton();
            });
        });

        document.querySelectorAll('.toggle-duplicate').forEach(btn => {
            btn.addEventListener('click', function() {
                const driverId = this.dataset.driverId;
                const card = this.closest('.driver-card');
                const checkbox = document.getElementById('dup_' + driverId);

                if (card.classList.contains('selected')) {
                    card.classList.remove('selected');
                    checkbox.checked = false;
                    const index = duplicateDriverIds.indexOf(driverId);
                    if (index > -1) duplicateDriverIds.splice(index, 1);
                } else {
                    card.classList.add('selected');
                    checkbox.checked = true;
                    duplicateDriverIds.push(driverId);
                }

                updateMergeButton();
            });
        });

        function updateMergeButton() {
            const mergeBtn = document.getElementById('mergeBtn');
            mergeBtn.disabled = !primaryDriverId || duplicateDriverIds.length === 0;
        }

        // Confirm before merge
        document.getElementById('mergeForm').addEventListener('submit', function(e) {
            const count = duplicateDriverIds.length;
            if (!confirm(`Are you sure you want to merge ${count} duplicate record(s) into the primary driver? This cannot be undone.`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
