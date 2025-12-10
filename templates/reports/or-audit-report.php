<?php
// OR Usage & Audit Trail Report Template
$or_summary = $data['or_summary'] ?? [];
$or_daily = $data['or_daily'] ?? [];
$or_cashier = $data['or_cashier'] ?? [];
$audit_trail = $data['audit_trail'] ?? [];
$cancelled_voided = $data['cancelled_voided'] ?? [];
?>

<!-- Summary Cards -->
<div class="stats-row row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card blue">
            <div class="stat-icon blue">
                <i data-lucide="receipt"></i>
            </div>
            <div class="stat-value"><?php echo number_format($or_summary['total_or_used'] ?? 0); ?></div>
            <div class="stat-label">Total OR Used</div>
            <div class="stat-sublabel">CGVM receipts</div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card green">
            <div class="stat-icon green">
                <i data-lucide="dollar-sign"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($or_summary['total_amount'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Amount</div>
            <div class="stat-sublabel">Collected payments</div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card yellow">
            <div class="stat-icon yellow">
                <i data-lucide="ban"></i>
            </div>
            <div class="stat-value"><?php echo number_format($or_summary['total_cancelled'] ?? 0); ?></div>
            <div class="stat-label">Cancelled</div>
            <div class="stat-sublabel">Receipt never printed</div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card purple">
            <div class="stat-icon purple">
                <i data-lucide="clock"></i>
            </div>
            <div class="stat-value"><?php echo number_format($or_summary['pending_print'] ?? 0); ?></div>
            <div class="stat-label">Pending Print</div>
            <div class="stat-sublabel">Awaiting confirmation</div>
        </div>
    </div>
</div>

<!-- Daily OR Usage Chart -->
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="trending-up"></i> Daily OR Usage Trend</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($or_daily)): ?>
                    <div class="chart-container">
                        <canvas id="orDailyChart" data-chart-data='<?php echo json_encode($or_daily); ?>'></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="trending-up"></i>
                        <h5>No Data Available</h5>
                        <p>No OR usage data found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- OR Usage by Cashier -->
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="users"></i> OR Usage by Cashier</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($or_cashier)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Cashier</th>
                                    <th>OR Used</th>
                                    <th>Payments</th>
                                    <th>Total Amount</th>
                                    <th>Cancelled</th>
                                    <th>Success Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($or_cashier as $cashier): ?>
                                    <?php
                                    $successRate = $cashier['payment_count'] > 0
                                        ? (($cashier['payment_count'] - $cashier['cancelled_count']) / $cashier['payment_count'] * 100)
                                        : 100;
                                    $rateClass = $successRate >= 95 ? 'success' : ($successRate >= 90 ? 'warning' : 'danger');
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($cashier['cashier_name']); ?></strong></td>
                                        <td><span class="badge bg-secondary"><?php echo number_format($cashier['or_count']); ?></span></td>
                                        <td><?php echo number_format($cashier['payment_count']); ?></td>
                                        <td>₱<?php echo number_format($cashier['total_amount'], 2); ?></td>
                                        <td><?php echo number_format($cashier['cancelled_count']); ?></td>
                                        <td><span class="badge bg-<?php echo $rateClass; ?>"><?php echo number_format($successRate, 1); ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="users"></i>
                        <h5>No Cashier Data</h5>
                        <p>No cashier activity found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Audit Trail -->
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="history"></i> OR Audit Trail (Last 100 entries)</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($audit_trail)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Action</th>
                                    <th>OR Number</th>
                                    <th>Ticket</th>
                                    <th>Amount</th>
                                    <th>User</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($audit_trail as $log): ?>
                                    <?php
                                    $actionBadges = [
                                        'payment_created' => 'success',
                                        'payment_cancelled' => 'warning',
                                        'payment_voided' => 'danger',
                                        'or_number_changed' => 'info',
                                        'payment_finalized' => 'primary'
                                    ];
                                    $badgeClass = $actionBadges[$log['action_type']] ?? 'secondary';
                                    $actionLabel = str_replace('_', ' ', strtoupper($log['action_type']));
                                    ?>
                                    <tr>
                                        <td><small><?php echo date('M d, Y h:i A', strtotime($log['action_datetime'])); ?></small></td>
                                        <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $actionLabel; ?></span></td>
                                        <td><code><?php echo htmlspecialchars($log['or_number_new'] ?? $log['or_number_old'] ?? 'N/A'); ?></code></td>
                                        <td><?php echo htmlspecialchars($log['ticket_number'] ?? 'N/A'); ?></td>
                                        <td><?php echo $log['amount'] ? '₱' . number_format($log['amount'], 2) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($log['username']); ?></td>
                                        <td><small><?php echo htmlspecialchars($log['reason'] ?? '-'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="history"></i>
                        <h5>No Audit Logs</h5>
                        <p>No audit trail entries found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Cancelled/Voided Payments -->
<div class="row">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="x-circle"></i> Cancelled/Voided Payments</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($cancelled_voided)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>OR Number</th>
                                    <th>Ticket</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>User</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cancelled_voided as $item): ?>
                                    <?php
                                    $isCancelled = $item['action_type'] === 'payment_cancelled';
                                    $badgeClass = $isCancelled ? 'warning' : 'danger';
                                    $typeLabel = $isCancelled ? 'CANCELLED' : 'VOIDED';
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y h:i A', strtotime($item['action_datetime'])); ?></td>
                                        <td><code><?php echo htmlspecialchars($item['or_number_old']); ?></code></td>
                                        <td><?php echo htmlspecialchars($item['ticket_number']); ?></td>
                                        <td>₱<?php echo number_format($item['amount'], 2); ?></td>
                                        <td><span class="badge bg-<?php echo $badgeClass; ?>"><?php echo $typeLabel; ?></span></td>
                                        <td><?php echo htmlspecialchars($item['username']); ?></td>
                                        <td><small><?php echo htmlspecialchars($item['reason'] ?? '-'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="check-circle" class="text-success"></i>
                        <h5>No Cancelled or Voided Payments</h5>
                        <p>All payments in this period were successfully completed</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// OR Daily Usage Chart
document.addEventListener('DOMContentLoaded', function() {
    const chartElement = document.getElementById('orDailyChart');
    if (chartElement) {
        const chartData = JSON.parse(chartElement.dataset.chartData || '[]');

        if (chartData.length > 0) {
            const ctx = chartElement.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.map(d => d.date),
                    datasets: [{
                        label: 'OR Used',
                        data: chartData.map(d => d.or_count),
                        borderColor: '#495057',
                        backgroundColor: 'rgba(73, 80, 87, 0.1)',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'Amount (₱)',
                        data: chartData.map(d => d.total_amount),
                        borderColor: '#6c757d',
                        backgroundColor: 'rgba(108, 117, 125, 0.1)',
                        tension: 0.1,
                        fill: true,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'OR Count'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Amount (₱)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.dataset.label === 'Amount (₱)') {
                                        label += '₱' + parseFloat(context.parsed.y).toLocaleString('en-PH', {minimumFractionDigits: 2});
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>
