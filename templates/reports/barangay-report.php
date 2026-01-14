<?php
// Barangay Report Template
$barangay_summary = $data['barangay_summary'] ?? [];
$total_barangays = count($barangay_summary);
$total_drivers_all = array_sum(array_column($barangay_summary, 'total_drivers'));
$total_citations_all = array_sum(array_column($barangay_summary, 'total_citations'));
$total_fines_all = array_sum(array_column($barangay_summary, 'total_fines'));
$collected_fines_all = array_sum(array_column($barangay_summary, 'collected_fines'));
$pending_fines_all = array_sum(array_column($barangay_summary, 'pending_fines'));
?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card blue">
            <div class="stat-icon blue">
                <i data-lucide="map-pin"></i>
            </div>
            <div class="stat-value"><?php echo $total_barangays; ?></div>
            <div class="stat-label">Total Barangays</div>
            <div class="stat-sublabel">with citations</div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card purple">
            <div class="stat-icon purple">
                <i data-lucide="users"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_drivers_all); ?></div>
            <div class="stat-label">Total Drivers</div>
            <div class="stat-sublabel">across all barangays</div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card red">
            <div class="stat-icon red">
                <i data-lucide="file-text"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_citations_all); ?></div>
            <div class="stat-label">Total Citations</div>
            <div class="stat-sublabel">issued</div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card green">
            <div class="stat-icon green">
                <i data-lucide="dollar-sign"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($total_fines_all, 2); ?></div>
            <div class="stat-label">Total Fines</div>
            <div class="stat-sublabel">issued</div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card green">
            <div class="stat-icon green">
                <i data-lucide="check-circle"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($collected_fines_all, 2); ?></div>
            <div class="stat-label">Collected Fines</div>
            <div class="stat-sublabel"><?php echo $total_fines_all > 0 ? round(($collected_fines_all / $total_fines_all) * 100, 1) : 0; ?>% collected</div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card yellow">
            <div class="stat-icon yellow">
                <i data-lucide="clock"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($pending_fines_all, 2); ?></div>
            <div class="stat-label">Pending Fines</div>
            <div class="stat-sublabel"><?php echo $total_fines_all > 0 ? round(($pending_fines_all / $total_fines_all) * 100, 1) : 0; ?>% pending</div>
        </div>
    </div>
</div>

<!-- Barangay Summary Table -->
<div class="row">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="map"></i>Drivers by Barangay Report</span>
                <span class="badge bg-primary"><?php echo $total_barangays; ?> barangays</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($barangay_summary)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Barangay</th>
                                    <th>Total Drivers</th>
                                    <th>Total Citations</th>
                                    <th>Total Fines</th>
                                    <th>Collected</th>
                                    <th>Pending</th>
                                    <th>Collection Rate</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($barangay_summary as $index => $barangay):
                                    $collection_rate = $barangay['total_fines'] > 0
                                        ? round(($barangay['collected_fines'] / $barangay['total_fines']) * 100, 1)
                                        : 0;

                                    // Determine collection rate badge color
                                    if ($collection_rate >= 80) {
                                        $rate_badge = 'bg-success';
                                    } elseif ($collection_rate >= 50) {
                                        $rate_badge = 'bg-warning';
                                    } else {
                                        $rate_badge = 'bg-danger';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <?php if ($index < 3): ?>
                                                <i data-lucide="award" class="text-warning" style="width: 16px; height: 16px;"></i> #<?php echo $index + 1; ?>
                                            <?php else: ?>
                                                #<?php echo $index + 1; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($barangay['barangay']); ?></strong></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo number_format($barangay['total_drivers']); ?> drivers
                                            </span>
                                        </td>
                                        <td><?php echo number_format($barangay['total_citations']); ?></td>
                                        <td>₱<?php echo number_format($barangay['total_fines'], 2); ?></td>
                                        <td class="text-success">₱<?php echo number_format($barangay['collected_fines'], 2); ?></td>
                                        <td class="text-warning">₱<?php echo number_format($barangay['pending_fines'], 2); ?></td>
                                        <td>
                                            <span class="badge <?php echo $rate_badge; ?>">
                                                <?php echo $collection_rate; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-barangay-details"
                                                    data-barangay="<?php echo htmlspecialchars($barangay['barangay']); ?>"
                                                    onclick="viewBarangayDetails('<?php echo htmlspecialchars($barangay['barangay'], ENT_QUOTES); ?>')">
                                                <i data-lucide="eye" style="width: 14px; height: 14px;"></i>
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="map-pin"></i>
                        <h5>No Data Available</h5>
                        <p>No barangay data found for the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top 5 Barangays Chart -->
<?php if (!empty($barangay_summary) && count($barangay_summary) > 0): ?>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="bar-chart"></i>Top 5 Barangays by Citations</span>
            </div>
            <div class="report-card-body">
                <canvas id="topBarangaysChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="pie-chart"></i>Collection Rate by Top 5 Barangays</span>
            </div>
            <div class="report-card-body">
                <canvas id="collectionRateChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Chart data
const topBarangays = <?php echo json_encode(array_slice(array_column($barangay_summary, 'barangay'), 0, 5)); ?>;
const topCitations = <?php echo json_encode(array_slice(array_column($barangay_summary, 'total_citations'), 0, 5)); ?>;
const topFines = <?php echo json_encode(array_slice(array_column($barangay_summary, 'total_fines'), 0, 5)); ?>;
const collectionRates = <?php echo json_encode(array_map(function($item) {
    return $item['total_fines'] > 0 ? round(($item['collected_fines'] / $item['total_fines']) * 100, 1) : 0;
}, array_slice($barangay_summary, 0, 5))); ?>;

// Top Barangays Chart
const topBarangaysCtx = document.getElementById('topBarangaysChart');
if (topBarangaysCtx) {
    new Chart(topBarangaysCtx, {
        type: 'bar',
        data: {
            labels: topBarangays,
            datasets: [{
                label: 'Citations',
                data: topCitations,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Collection Rate Chart
const collectionRateCtx = document.getElementById('collectionRateChart');
if (collectionRateCtx) {
    new Chart(collectionRateCtx, {
        type: 'doughnut',
        data: {
            labels: topBarangays,
            datasets: [{
                label: 'Collection Rate (%)',
                data: collectionRates,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(153, 102, 255, 0.5)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}

// Function to view barangay details
function viewBarangayDetails(barangay) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('barangay_filter', barangay);
    window.location.href = currentUrl.toString();
}
</script>
<?php endif; ?>

<?php
// Display detailed driver list if a specific barangay is selected
if (isset($data['barangay_drivers']) && !empty($data['barangay_drivers'])):
    $barangay_drivers = $data['barangay_drivers'];
    $selected_barangay = $_GET['barangay_filter'] ?? '';
?>
<div class="row mt-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="users"></i>Drivers in <?php echo htmlspecialchars($selected_barangay); ?></span>
                <span class="badge bg-info"><?php echo count($barangay_drivers); ?> drivers</span>
            </div>
            <div class="report-card-body no-padding">
                <div class="mb-3 p-3">
                    <button class="btn btn-secondary btn-sm" onclick="window.location.href='<?php
                        $url = new URL(window.location.href);
                        $url.searchParams.delete('barangay_filter');
                        echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query(array_diff_key($_GET, ['barangay_filter' => ''])));
                    ?>'">
                        <i data-lucide="arrow-left" style="width: 14px; height: 14px;"></i>
                        Back to All Barangays
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Driver Name</th>
                                <th>License Number</th>
                                <th>Citations</th>
                                <th>Total Fines</th>
                                <th>Paid</th>
                                <th>Pending</th>
                                <th>First Citation</th>
                                <th>Latest Citation</th>
                                <th>Violations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($barangay_drivers as $index => $driver): ?>
                                <tr>
                                    <td>#<?php echo $index + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($driver['driver_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($driver['license_number'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo $driver['citation_count']; ?> citations
                                        </span>
                                    </td>
                                    <td>₱<?php echo number_format($driver['total_fines'], 2); ?></td>
                                    <td class="text-success">₱<?php echo number_format($driver['paid_fines'], 2); ?></td>
                                    <td class="text-warning">₱<?php echo number_format($driver['pending_fines'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($driver['first_citation'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($driver['latest_citation'])); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php
                                            $violations = explode(', ', $driver['violations']);
                                            echo htmlspecialchars(implode(', ', array_slice($violations, 0, 2)));
                                            if (count($violations) > 2) {
                                                echo ' <span class="badge bg-secondary">+' . (count($violations) - 2) . ' more</span>';
                                            }
                                            ?>
                                        </small>
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
