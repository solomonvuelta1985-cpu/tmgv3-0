<?php
// Vehicle Reports Template
$vehicle_stats = $data['vehicle_stats'] ?? [];
?>

<!-- Vehicle Type Statistics -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-car"></i>Citations by Vehicle Type</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($vehicle_stats)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Vehicle Type</th>
                                    <th>Citations</th>
                                    <th>Percentage</th>
                                    <th>Total Fines</th>
                                    <th>Average Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_citations = array_sum(array_column($vehicle_stats, 'citation_count'));
                                $total_fines_sum = array_sum(array_column($vehicle_stats, 'total_fines'));

                                foreach ($vehicle_stats as $index => $vehicle):
                                    $percentage = $total_citations > 0 ? ($vehicle['citation_count'] / $total_citations) * 100 : 0;
                                ?>
                                    <tr>
                                        <td>#<?php echo $index + 1; ?></td>
                                        <td><strong><?php echo htmlspecialchars($vehicle['vehicle_type']); ?></strong></td>
                                        <td><?php echo number_format($vehicle['citation_count']); ?></td>
                                        <td>
                                            <div class="progress" style="min-width: 100px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                                    <?php echo number_format($percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>₱<?php echo number_format($vehicle['total_fines'], 2); ?></td>
                                        <td>₱<?php echo number_format($vehicle['average_fine'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Total Row -->
                                <tr class="total-row">
                                    <td colspan="2"><strong>TOTAL</strong></td>
                                    <td><strong><?php echo number_format($total_citations); ?></strong></td>
                                    <td><strong>100%</strong></td>
                                    <td><strong>₱<?php echo number_format($total_fines_sum, 2); ?></strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-car"></i>
                        <h5>No Vehicle Data</h5>
                        <p>No vehicle data found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vehicle Type Chart -->
    <div class="col-lg-4">
        <div class="report-card">
            <div class="report-card-header">
                <span><i class="fas fa-chart-pie"></i>Distribution</span>
            </div>
            <div class="report-card-body">
                <?php if (!empty($vehicle_stats)): ?>
                    <div class="chart-container">
                        <canvas id="vehicleChart" data-chart-data='<?php echo json_encode($vehicle_stats); ?>'></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-pie"></i>
                        <p>No data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Vehicle Type Summary Cards -->
<?php if (!empty($vehicle_stats)): ?>
    <?php
    // Get top 4 vehicle types for summary cards
    $top_vehicles = array_slice($vehicle_stats, 0, 4);
    $colors = ['blue', 'green', 'yellow', 'purple'];
    $icons = [
        'Motorcycle' => 'fa-motorcycle',
        'Car' => 'fa-car',
        'Truck' => 'fa-truck',
        'Van' => 'fa-shuttle-van',
        'Bus' => 'fa-bus',
        'SUV' => 'fa-car-side',
        'Tricycle' => 'fa-bicycle'
    ];
    ?>
    <div class="row">
        <?php foreach ($top_vehicles as $index => $vehicle): ?>
            <?php
            $color = $colors[$index % 4];
            $icon = $icons[$vehicle['vehicle_type']] ?? 'fa-car';
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="stat-card <?php echo $color; ?>">
                    <div class="stat-icon <?php echo $color; ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($vehicle['citation_count']); ?></div>
                    <div class="stat-label"><?php echo htmlspecialchars($vehicle['vehicle_type']); ?> Citations</div>
                    <div class="stat-sublabel">₱<?php echo number_format($vehicle['total_fines'], 2); ?> total</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
