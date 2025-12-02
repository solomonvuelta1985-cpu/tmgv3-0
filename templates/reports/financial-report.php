<?php
// Financial Report Template
$summary = $data['summary'] ?? [];
$trends = $data['trends'] ?? [];
$outstanding = $data['outstanding'] ?? [];
?>

<!-- Summary Cards -->
<div class="stats-row row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card blue">
            <div class="stat-icon blue">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($summary['total_fines_issued'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Fines Issued</div>
            <div class="stat-sublabel"><?php echo number_format($summary['total_citations'] ?? 0); ?> citations</div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card green">
            <div class="stat-icon green">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($summary['total_fines_collected'] ?? 0, 2); ?></div>
            <div class="stat-label">Fines Collected</div>
            <div class="stat-sublabel"><?php echo number_format($summary['paid_count'] ?? 0); ?> paid</div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card yellow">
            <div class="stat-icon yellow">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($summary['total_fines_pending'] ?? 0, 2); ?></div>
            <div class="stat-label">Pending Fines</div>
            <div class="stat-sublabel"><?php echo number_format($summary['pending_count'] ?? 0); ?> pending</div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card purple">
            <div class="stat-icon purple">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($summary['average_fine'] ?? 0, 2); ?></div>
            <div class="stat-label">Average Fine</div>
            <div class="stat-sublabel">Per citation</div>
        </div>
    </div>
</div>

<!-- Revenue Trend Chart -->
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-chart-area"></i>Revenue Trends</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($trends)): ?>
                    <div class="chart-container">
                        <canvas id="revenueTrendChart" data-chart-data='<?php echo json_encode($trends); ?>'></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <h5>No Trend Data Available</h5>
                        <p>No revenue data found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Outstanding Fines by Aging -->
<div class="row">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-exclamation-triangle"></i>Outstanding Fines (Aging Analysis)</span>
                <span class="badge bg-warning"><?php echo count($outstanding); ?> pending</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($outstanding)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Driver Name</th>
                                    <th>License Number</th>
                                    <th>Amount</th>
                                    <th>Date Issued</th>
                                    <th>Days Outstanding</th>
                                    <th>Aging</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $aging_groups = [
                                    '0-30 days' => ['total' => 0, 'count' => 0],
                                    '31-60 days' => ['total' => 0, 'count' => 0],
                                    '61-90 days' => ['total' => 0, 'count' => 0],
                                    '90+ days' => ['total' => 0, 'count' => 0]
                                ];

                                foreach ($outstanding as $fine):
                                    $aging_groups[$fine['aging_category']]['total'] += $fine['total_fine'];
                                    $aging_groups[$fine['aging_category']]['count']++;

                                    $aging_class = match($fine['aging_category']) {
                                        '0-30 days' => 'aging-0-30',
                                        '31-60 days' => 'aging-31-60',
                                        '61-90 days' => 'aging-61-90',
                                        '90+ days' => 'aging-90-plus',
                                        default => ''
                                    };
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($fine['ticket_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($fine['driver_name']); ?></td>
                                        <td><?php echo htmlspecialchars($fine['license_number']); ?></td>
                                        <td>₱<?php echo number_format($fine['total_fine'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($fine['created_at'])); ?></td>
                                        <td><?php echo $fine['days_outstanding']; ?> days</td>
                                        <td><span class="<?php echo $aging_class; ?>"><?php echo $fine['aging_category']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Summary Rows -->
                                <tr class="total-row">
                                    <td colspan="7"><strong>AGING SUMMARY</strong></td>
                                </tr>
                                <?php foreach ($aging_groups as $category => $data): ?>
                                    <?php if ($data['count'] > 0): ?>
                                        <tr>
                                            <td colspan="3"><strong><?php echo $category; ?></strong></td>
                                            <td><strong>₱<?php echo number_format($data['total'], 2); ?></strong></td>
                                            <td colspan="3"><strong><?php echo $data['count']; ?> citation(s)</strong></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h5>No Outstanding Fines</h5>
                        <p>All citations have been paid or resolved</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
