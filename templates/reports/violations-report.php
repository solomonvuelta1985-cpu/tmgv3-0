<?php
// Violations Report Template
$statistics = $data['statistics'] ?? [];
$offense_distribution = $data['offense_distribution'] ?? [];

// Debug: Show if data exists
if (isset($_GET['debug'])) {
    echo '<div class="alert alert-info">';
    echo '<strong>Debug Info:</strong><br>';
    echo 'Statistics count: ' . count($statistics) . '<br>';
    echo 'Offense distribution count: ' . count($offense_distribution) . '<br>';
    if (!empty($statistics)) {
        echo 'Sample data: <pre>' . print_r(array_slice($statistics, 0, 2), true) . '</pre>';
    }
    echo '</div>';
}
?>

<!-- Violation Statistics Table -->
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-list-alt"></i>Violation Type Statistics</span>
                <span class="badge bg-primary"><?php echo count($statistics); ?> types</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($statistics)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Violation Type</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                    <th>Total Fines</th>
                                    <th>Avg Fine</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_violations = array_sum(array_column($statistics, 'violation_count'));
                                $total_fines = array_sum(array_column($statistics, 'total_fines'));
                                foreach ($statistics as $index => $stat):
                                ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($stat['violation_type']); ?></strong></td>
                                        <td><?php echo number_format($stat['violation_count']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="width: 100px;">
                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $stat['percentage']; ?>%">
                                                        <?php echo number_format($stat['percentage'], 1); ?>%
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>₱<?php echo number_format($stat['total_fines'], 2); ?></td>
                                        <td>₱<?php echo number_format($stat['average_fine'], 2); ?></td>
                                        <td>
                                            <?php if ($stat['percentage'] > 10): ?>
                                                <i class="fas fa-arrow-up text-danger"></i> High
                                            <?php elseif ($stat['percentage'] > 5): ?>
                                                <i class="fas fa-minus text-warning"></i> Medium
                                            <?php else: ?>
                                                <i class="fas fa-arrow-down text-success"></i> Low
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Total Row -->
                                <tr class="total-row">
                                    <td colspan="2"><strong>TOTAL</strong></td>
                                    <td><strong><?php echo number_format($total_violations); ?></strong></td>
                                    <td><strong>100%</strong></td>
                                    <td><strong>₱<?php echo number_format($total_fines, 2); ?></strong></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>No Violation Data</h5>
                        <p>No violations found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Violation Chart -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-chart-bar"></i>Top Violations (Bar Chart)</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($statistics)): ?>
                    <div class="chart-container">
                        <canvas id="violationStatsChart" data-chart-data='<?php echo json_encode(array_slice($statistics, 0, 10)); ?>'></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <h5>No Chart Data</h5>
                        <p>No violation data to display</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Offense Count Distribution -->
    <div class="col-lg-4">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-layer-group"></i>Offense Count Distribution</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($offense_distribution)): ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Offense #</th>
                                <th>Count</th>
                                <th>Total Fines</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($offense_distribution as $dist): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo $dist['offense_count'] == 1 ? 'bg-success' : ($dist['offense_count'] == 2 ? 'bg-warning' : 'bg-danger'); ?>">
                                            <?php echo $dist['offense_count']; ?>
                                            <?php echo $dist['offense_count'] == 1 ? 'st' : ($dist['offense_count'] == 2 ? 'nd' : 'rd'); ?> Offense
                                        </span>
                                    </td>
                                    <td><?php echo number_format($dist['citation_count']); ?></td>
                                    <td>₱<?php echo number_format($dist['total_fines'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-layer-group"></i>
                        <p class="mb-0">No data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
