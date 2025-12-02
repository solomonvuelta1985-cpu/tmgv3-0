<?php
// Status & Operations Report Template
$distribution = $data['distribution'] ?? [];
$contested = $data['contested'] ?? [];
$resolution_time = $data['resolution_time'] ?? [];
?>

<!-- Status Distribution Chart -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-chart-pie"></i>Status Distribution</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($distribution)): ?>
                    <div class="chart-container">
                        <canvas id="statusChart" data-chart-data='<?php echo json_encode($distribution); ?>'></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-pie"></i>
                        <h5>No Status Data</h5>
                        <p>No data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Statistics -->
    <div class="col-lg-6">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-list"></i>Status Breakdown</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($distribution)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>Percentage</th>
                                    <th>Total Fines</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_count = array_sum(array_column($distribution, 'count'));
                                $total_fines_all = array_sum(array_column($distribution, 'total_fines'));

                                foreach ($distribution as $status):
                                    $status_badge = match($status['status']) {
                                        'paid' => 'bg-success',
                                        'pending' => 'bg-warning',
                                        'contested' => 'bg-info',
                                        'dismissed' => 'bg-secondary',
                                        default => 'bg-primary'
                                    };
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php echo $status_badge; ?>">
                                                <?php echo ucfirst($status['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($status['count']); ?></td>
                                        <td>
                                            <div class="progress" style="min-width: 100px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?php echo $status['percentage']; ?>%">
                                                    <?php echo number_format($status['percentage'], 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>₱<?php echo number_format($status['total_fines'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="total-row">
                                    <td><strong>TOTAL</strong></td>
                                    <td><strong><?php echo number_format($total_count); ?></strong></td>
                                    <td><strong>100%</strong></td>
                                    <td><strong>₱<?php echo number_format($total_fines_all, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-list"></i>
                        <p>No data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Case Resolution Time -->
<?php if (!empty($resolution_time)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-hourglass-half"></i>Average Case Resolution Time</span>
            </div>
            <div class="report-card-body">
                <div class="row">
                    <?php foreach ($resolution_time as $res): ?>
                        <div class="col-md-6">
                            <div class="stat-card <?php echo $res['status'] === 'paid' ? 'green' : 'blue'; ?>">
                                <div class="stat-icon <?php echo $res['status'] === 'paid' ? 'green' : 'blue'; ?>">
                                    <i class="fas fa-<?php echo $res['status'] === 'paid' ? 'money-bill-wave' : 'gavel'; ?>"></i>
                                </div>
                                <div class="stat-value"><?php echo round($res['avg_days_to_resolve']); ?> days</div>
                                <div class="stat-label">Average Resolution Time - <?php echo ucfirst($res['status']); ?></div>
                                <div class="stat-sublabel">
                                    Min: <?php echo $res['min_days']; ?> days | Max: <?php echo $res['max_days']; ?> days
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Contested Citations -->
<div class="row">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-exclamation-circle"></i>Contested Citations</span>
                <span class="badge bg-info"><?php echo count($contested); ?> contested</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($contested)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Driver Name</th>
                                    <th>License Number</th>
                                    <th>Amount</th>
                                    <th>Date Issued</th>
                                    <th>Violations</th>
                                    <th>Officer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contested as $citation): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($citation['ticket_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($citation['driver_name']); ?></td>
                                        <td><?php echo htmlspecialchars($citation['license_number']); ?></td>
                                        <td>₱<?php echo number_format($citation['total_fine'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($citation['created_at'])); ?></td>
                                        <td><small><?php echo htmlspecialchars($citation['violations']); ?></small></td>
                                        <td><?php echo htmlspecialchars($citation['officer_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h5>No Contested Citations</h5>
                        <p>No contested citations found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
