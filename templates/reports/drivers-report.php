<?php
// Driver Report Template
$repeat_offenders = $data['repeat_offenders'] ?? [];
?>

<!-- Repeat Offenders Table -->
<div class="row">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="users"></i>Repeat Offenders Report</span>
                <span class="badge bg-danger"><?php echo count($repeat_offenders); ?> drivers</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($repeat_offenders)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Driver Name</th>
                                    <th>License Number</th>
                                    <th>Citations</th>
                                    <th>Total Fines</th>
                                    <th>First Citation</th>
                                    <th>Latest Citation</th>
                                    <th>Violations</th>
                                    <th>Risk Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repeat_offenders as $index => $driver):
                                    // Calculate risk level based on citation count
                                    if ($driver['citation_count'] >= 5) {
                                        $risk_level = 'High Risk';
                                        $risk_badge = 'bg-danger';
                                    } elseif ($driver['citation_count'] >= 3) {
                                        $risk_level = 'Medium Risk';
                                        $risk_badge = 'bg-warning';
                                    } else {
                                        $risk_level = 'Low Risk';
                                        $risk_badge = 'bg-info';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($index == 0): ?>
                                                <i data-lucide="alert-triangle" class="text-danger" style="width: 16px; height: 16px;"></i> #<?php echo $index + 1; ?>
                                            <?php else: ?>
                                                #<?php echo $index + 1; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($driver['driver_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($driver['license_number'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge <?php echo $driver['citation_count'] >= 5 ? 'bg-danger' : ($driver['citation_count'] >= 3 ? 'bg-warning' : 'bg-info'); ?>">
                                                <?php echo $driver['citation_count']; ?> citations
                                            </span>
                                        </td>
                                        <td>₱<?php echo number_format($driver['total_fines'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($driver['first_citation'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($driver['latest_citation'])); ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?php
                                                $violations = explode(', ', $driver['violations']);
                                                echo implode(', ', array_slice($violations, 0, 2));
                                                if (count($violations) > 2) {
                                                    echo ' <span class="badge bg-secondary">+' . (count($violations) - 2) . ' more</span>';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $risk_badge; ?>">
                                                <?php echo $risk_level; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="users"></i>
                        <h5>No Repeat Offenders</h5>
                        <p>No drivers with multiple citations found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Summary -->
<?php if (!empty($repeat_offenders)): ?>
<div class="row mt-4">
    <div class="col-md-3">
        <div class="stat-card red">
            <div class="stat-icon red">
                <i data-lucide="user-x"></i>
            </div>
            <div class="stat-value"><?php echo count(array_filter($repeat_offenders, fn($d) => $d['citation_count'] >= 5)); ?></div>
            <div class="stat-label">High Risk Drivers</div>
            <div class="stat-sublabel">5+ citations</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card yellow">
            <div class="stat-icon yellow">
                <i data-lucide="alert-circle"></i>
            </div>
            <div class="stat-value"><?php echo count(array_filter($repeat_offenders, fn($d) => $d['citation_count'] >= 3 && $d['citation_count'] < 5)); ?></div>
            <div class="stat-label">Medium Risk Drivers</div>
            <div class="stat-sublabel">3-4 citations</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card blue">
            <div class="stat-icon blue">
                <i data-lucide="rotate-cw"></i>
            </div>
            <div class="stat-value"><?php echo count($repeat_offenders); ?></div>
            <div class="stat-label">Total Repeat Offenders</div>
            <div class="stat-sublabel">2+ citations</div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card green">
            <div class="stat-icon green">
                <i data-lucide="dollar-sign"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format(array_sum(array_column($repeat_offenders, 'total_fines')), 2); ?></div>
            <div class="stat-label">Total from Repeat Offenders</div>
            <div class="stat-sublabel">Combined fines</div>
        </div>
    </div>
</div>
<?php endif; ?>
