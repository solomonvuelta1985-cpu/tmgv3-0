<?php
// Officer Performance Report Template
$performance = $data['performance'] ?? [];
?>

<!-- Officer Performance Table -->
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-user-shield"></i>Officer Performance Summary</span>
                <span class="badge bg-primary"><?php echo count($performance); ?> officers</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($performance)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Officer Name</th>
                                    <th>Badge Number</th>
                                    <th>Position</th>
                                    <th>Citations Issued</th>
                                    <th>Total Fines</th>
                                    <th>Avg Fine</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_citations = array_sum(array_column($performance, 'citation_count'));
                                $total_fines_sum = array_sum(array_column($performance, 'total_fines'));

                                foreach ($performance as $index => $officer):
                                    $performance_percentage = $total_citations > 0 ? ($officer['citation_count'] / $total_citations) * 100 : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($index == 0): ?>
                                                <i class="fas fa-trophy text-warning"></i> #<?php echo $index + 1; ?>
                                            <?php elseif ($index == 1): ?>
                                                <i class="fas fa-medal" style="color: silver;"></i> #<?php echo $index + 1; ?>
                                            <?php elseif ($index == 2): ?>
                                                <i class="fas fa-medal" style="color: #cd7f32;"></i> #<?php echo $index + 1; ?>
                                            <?php else: ?>
                                                #<?php echo $index + 1; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($officer['officer_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($officer['badge_number'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($officer['position'] ?? ''); ?></td>
                                        <td><?php echo number_format($officer['citation_count']); ?></td>
                                        <td>₱<?php echo number_format($officer['total_fines'], 2); ?></td>
                                        <td>₱<?php echo number_format($officer['average_fine'], 2); ?></td>
                                        <td>
                                            <div class="progress" style="min-width: 100px;">
                                                <div class="progress-bar
                                                    <?php echo $performance_percentage >= 20 ? 'bg-success' : ($performance_percentage >= 10 ? 'bg-info' : 'bg-secondary'); ?>"
                                                    role="progressbar"
                                                    style="width: <?php echo min(100, $performance_percentage * 5); ?>%">
                                                    <?php echo number_format($performance_percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Total Row -->
                                <tr class="total-row">
                                    <td colspan="4"><strong>TOTAL</strong></td>
                                    <td><strong><?php echo number_format($total_citations); ?></strong></td>
                                    <td><strong>₱<?php echo number_format($total_fines_sum, 2); ?></strong></td>
                                    <td colspan="2"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-shield"></i>
                        <h5>No Officer Data</h5>
                        <p>No officer performance data found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Officer Performance Chart -->
<?php if (!empty($performance)): ?>
<div class="row">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-chart-bar"></i>Citations Issued by Officer</span>
            </div>
            <div class="report-card-body">
                <div class="chart-container large">
                    <canvas id="officerChart" data-chart-data='<?php echo json_encode($performance); ?>'></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
