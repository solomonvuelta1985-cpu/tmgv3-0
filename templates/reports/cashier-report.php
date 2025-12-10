<?php
// Cashier Performance Report Template
$performance = $data['performance'] ?? [];
$summary = $data['summary'] ?? [];
$recent_citations = $data['recent_citations'] ?? [];
$recent_payments = $data['recent_payments'] ?? [];
?>

<!-- Summary Cards -->
<div class="stats-row row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card blue">
            <div class="stat-icon blue">
                <i data-lucide="file-text"></i>
            </div>
            <div class="stat-value"><?php echo number_format($summary['total_citations'] ?? 0); ?></div>
            <div class="stat-label">Citations Created</div>
            <div class="stat-sublabel">
                Pending: <?php echo number_format($summary['pending_count'] ?? 0); ?> |
                Paid: <?php echo number_format($summary['paid_count'] ?? 0); ?>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card orange">
            <div class="stat-icon orange">
                <i data-lucide="dollar-sign"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($summary['total_fines'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Fines Issued</div>
            <div class="stat-sublabel">From citations created</div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card green">
            <div class="stat-icon green">
                <i data-lucide="banknote"></i>
            </div>
            <div class="stat-value"><?php echo number_format($summary['total_payments'] ?? 0); ?></div>
            <div class="stat-label">Payments Processed</div>
            <div class="stat-sublabel">
                Completed: <?php echo number_format($summary['completed_payments'] ?? 0); ?> |
                Voided: <?php echo number_format($summary['voided_payments'] ?? 0); ?>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="stat-card purple">
            <div class="stat-icon purple">
                <i data-lucide="coins"></i>
            </div>
            <div class="stat-value">₱<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Amount Collected</div>
            <div class="stat-sublabel">From payments processed</div>
        </div>
    </div>
</div>

<!-- Cashier Performance Table -->
<?php if (!empty($performance)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="users"></i>Staff Performance Comparison</span>
                <span class="badge bg-primary"><?php echo count($performance); ?> user(s)</span>
            </div>
            <div class="report-card-body no-padding">
                <div class="table-responsive">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Citations Created</th>
                                <th>Fines Issued</th>
                                <th>Payments Processed</th>
                                <th>Amount Collected</th>
                                <th>Avg. Transaction</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance as $cashier): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cashier['full_name']); ?></strong></td>
                                    <td><span class="badge bg-<?php echo $cashier['role'] === 'admin' ? 'danger' : 'primary'; ?>"><?php echo ucfirst($cashier['role']); ?></span></td>
                                    <td><?php echo number_format($cashier['citations_created'] ?? 0); ?></td>
                                    <td>₱<?php echo number_format($cashier['total_fines_issued'] ?? 0, 2); ?></td>
                                    <td><?php echo number_format($cashier['payments_processed'] ?? 0); ?></td>
                                    <td>₱<?php echo number_format($cashier['total_collected'] ?? 0, 2); ?></td>
                                    <td>₱<?php echo number_format($cashier['avg_transaction'] ?? 0, 2); ?></td>
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

<!-- Recent Citations -->
<div class="row mb-4">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="list"></i>Recent Citations Created</span>
                <span class="badge bg-info"><?php echo count($recent_citations); ?> recent</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($recent_citations)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Driver Name</th>
                                    <th>Fine Amount</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Date Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_citations as $citation): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($citation['ticket_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($citation['driver_name']); ?></td>
                                        <td>₱<?php echo number_format($citation['total_fine'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $citation['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($citation['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($citation['cashier_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($citation['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="inbox"></i>
                        <h5>No Citations Created</h5>
                        <p>No citations created in the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="row">
    <div class="col-12">
        <div class="report-card">
            <div class="report-card-header">
                <span><i data-lucide="receipt"></i>Recent Payments Processed</span>
                <span class="badge bg-success"><?php echo $data['payments_total_count'] ?? 0; ?> total</span>
            </div>
            <div class="report-card-body no-padding">
                <?php if (!empty($recent_payments)): ?>
                    <div class="table-responsive">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Ticket #</th>
                                    <th>Driver Name</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Processed By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($payment['receipt_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($payment['ticket_number']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['driver_name']); ?></td>
                                        <td>₱<?php echo number_format($payment['amount_paid'], 2); ?></td>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'voided' ? 'danger' : 'secondary'); ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['cashier_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($payment['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php
                    // Pagination
                    $payments_total_pages = $data['payments_total_pages'] ?? 1;
                    $payments_page = $data['payments_page'] ?? 1;
                    if ($payments_total_pages > 1):
                    ?>
                        <div class="p-3 border-top">
                            <nav aria-label="Payments pagination">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($payments_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?report_type=cashier&start_date=<?php echo urlencode($_GET['start_date'] ?? ''); ?>&end_date=<?php echo urlencode($_GET['end_date'] ?? ''); ?>&payments_page=<?php echo $payments_page - 1; ?>">Previous</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Previous</span>
                                        </li>
                                    <?php endif; ?>

                                    <?php
                                    // Show page numbers
                                    $start_page = max(1, $payments_page - 2);
                                    $end_page = min($payments_total_pages, $payments_page + 2);

                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?report_type=cashier&start_date=<?php echo urlencode($_GET['start_date'] ?? ''); ?>&end_date=<?php echo urlencode($_GET['end_date'] ?? ''); ?>&payments_page=1">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i === $payments_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?report_type=cashier&start_date=<?php echo urlencode($_GET['start_date'] ?? ''); ?>&end_date=<?php echo urlencode($_GET['end_date'] ?? ''); ?>&payments_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $payments_total_pages): ?>
                                        <?php if ($end_page < $payments_total_pages - 1): ?>
                                            <li class="page-item disabled"><span class="page-link">...</span></li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?report_type=cashier&start_date=<?php echo urlencode($_GET['start_date'] ?? ''); ?>&end_date=<?php echo urlencode($_GET['end_date'] ?? ''); ?>&payments_page=<?php echo $payments_total_pages; ?>"><?php echo $payments_total_pages; ?></a>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($payments_page < $payments_total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?report_type=cashier&start_date=<?php echo urlencode($_GET['start_date'] ?? ''); ?>&end_date=<?php echo urlencode($_GET['end_date'] ?? ''); ?>&payments_page=<?php echo $payments_page + 1; ?>">Next</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">Next</span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="inbox"></i>
                        <h5>No Payments Processed</h5>
                        <p>No payments processed in the selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
