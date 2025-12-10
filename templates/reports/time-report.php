<?php
// Time-Based Analytics Report Template
$hourly = $data['hourly'] ?? [];
$day_of_week = $data['day_of_week'] ?? [];
$monthly = $data['monthly'] ?? [];
?>

<!-- Hourly Analytics Chart -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="clock"></i>Citations by Hour of Day</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($hourly)): ?>
                    <div class="chart-container">
                        <canvas id="hourlyChart" data-chart-data='<?php echo json_encode($hourly); ?>'></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="clock"></i>
                        <h5>No Hourly Data</h5>
                        <p>No data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Day of Week Chart -->
    <div class="col-lg-6">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="calendar"></i>Citations by Day of Week</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($day_of_week)): ?>
                    <div class="chart-container">
                        <canvas id="dayOfWeekChart" data-chart-data='<?php echo json_encode($day_of_week); ?>'></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="calendar"></i>
                        <h5>No Weekly Data</h5>
                        <p>No data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Peak Hours Summary -->
<?php if (!empty($hourly)): ?>
    <?php
    // Find peak hours
    usort($hourly, function($a, $b) {
        return $b['citation_count'] - $a['citation_count'];
    });
    $peak_hours = array_slice($hourly, 0, 3);
    ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="report-card">
                <div class="report-card-header">
                    <span><i data-lucide="trending-up"></i>Peak Citation Hours</span>
                </div>
                <div class="report-card-body">
                    <div class="row">
                        <?php foreach ($peak_hours as $index => $hour): ?>
                            <div class="col-md-4">
                                <div class="stat-card <?php echo $index == 0 ? 'red' : ($index == 1 ? 'yellow' : 'blue'); ?>">
                                    <div class="stat-value"><?php echo str_pad($hour['hour_of_day'], 2, '0', STR_PAD_LEFT); ?>:00</div>
                                    <div class="stat-label"><?php echo number_format($hour['citation_count']); ?> citations</div>
                                    <div class="stat-sublabel">₱<?php echo number_format($hour['total_fines'], 2); ?> in fines</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Day of Week Table -->
<?php if (!empty($day_of_week)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="table"></i>Day of Week Statistics</span>
            </div>
            <div class="report-card-body no-padding">
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Citations</th>
                                <th>Total Fines</th>
                                <th>Average Fine</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_citations_week = array_sum(array_column($day_of_week, 'citation_count'));
                            foreach ($day_of_week as $day):
                                $percentage = $total_citations_week > 0 ? ($day['citation_count'] / $total_citations_week) * 100 : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo $day['day_name']; ?></strong></td>
                                    <td><?php echo number_format($day['citation_count']); ?></td>
                                    <td>₱<?php echo number_format($day['total_fines'], 2); ?></td>
                                    <td>₱<?php echo number_format($day['average_fine'], 2); ?></td>
                                    <td>
                                        <div class="progress" style="min-width: 150px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Monthly Trends -->
<?php if (!empty($monthly)): ?>
<div class="row">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="area-chart"></i>Monthly Trends</span>
            </div>
            <div class="report-card-body">
                <div class="chart-container large">
                    <canvas id="monthlyChart" data-chart-data='<?php echo json_encode($monthly); ?>'></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
