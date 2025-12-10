<?php
/**
 * Citations List Content Template
 * Renders the citations listing page content
 *
 * Required variables:
 * - $stats: Statistics array
 * - $search: Search term
 * - $status_filter: Status filter
 * - $citations: Array of citations
 * - $page: Current page
 * - $total_pages: Total pages
 * - $per_page: Records per page
 * - $total_records: Total records
 * - $offset: Offset for pagination
 */

// Check user permissions
$can_edit = function_exists('can_edit_citation') && can_edit_citation();
$can_change_status = function_exists('can_change_status') && can_change_status();
$can_pay = function_exists('can_process_payment') && can_process_payment();
?>
<div class="main-card">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-list"></i> Traffic Citations</h1>
        <p class="page-subtitle">View and manage all traffic violation citations</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label"><i class="fas fa-file-alt"></i> Total Citations</div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
            <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
        </div>
        <div class="stat-card green">
            <div class="stat-number"><?php echo number_format($stats['paid']); ?></div>
            <div class="stat-label"><i class="fas fa-check-circle"></i> Paid</div>
        </div>
        <div class="stat-card red">
            <div class="stat-number"><?php echo number_format($stats['contested']); ?></div>
            <div class="stat-label"><i class="fas fa-exclamation-circle"></i> Contested</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-number">P<?php echo number_format($stats['total_fines'], 2); ?></div>
            <div class="stat-label"><i class="fas fa-peso-sign"></i> Total Fines</div>
        </div>
    </div>

    <!-- Action Section -->
    <div class="action-section">
        <div class="search-box">
            <form method="GET" action="" id="searchForm">
                <input type="text" name="search" placeholder="Search ticket #, name, license, plate..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            </form>
        </div>

        <div class="filter-box">
            <select name="status" id="statusFilter" onchange="filterByStatus(this.value)">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="contested" <?php echo $status_filter === 'contested' ? 'selected' : ''; ?>>Contested</option>
                <option value="dismissed" <?php echo $status_filter === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
            </select>
        </div>

        <div class="action-buttons">
            <?php if (can_create_citation()): ?>
            <a href="index2.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Citation
            </a>
            <?php else: ?>
            <button type="button" class="btn btn-outline-primary" disabled title="Enforcer/Admin access required to create citations">
                <i class="fas fa-lock"></i> New Citation (Restricted)
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-success" onclick="exportCSV()">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
            <button type="button" class="btn btn-info" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Data Table -->
    <div class="table-container">
        <?php if (empty($citations)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h5>No citations found</h5>
                <p>No records match your search criteria.</p>
                <a href="index2.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus"></i> Create First Citation
                </a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">#</th>
                        <th>Ticket #</th>
                        <th>Date/Time</th>
                        <th>Driver Name</th>
                        <th>License #</th>
                        <th>Plate/MV #</th>
                        <th>Violations</th>
                        <th>Fine</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rowNumber = $offset + 1; // Start row number from offset + 1
                    foreach ($citations as $citation):
                    ?>
                        <tr>
                            <td style="text-align: center; color: #6c757d; font-weight: 600;"><strong><?php echo $rowNumber++; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($citation['ticket_number']); ?></strong></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($citation['apprehension_datetime'])); ?></td>
                            <td>
                                <a href="#" class="text-decoration-none text-primary fw-bold" onclick="quickInfo(<?php echo $citation['citation_id']; ?>); return false;" title="Click for quick info">
                                <?php
                                $name = $citation['last_name'] . ', ' . $citation['first_name'];
                                if (!empty($citation['middle_initial'])) {
                                    $name .= ' ' . $citation['middle_initial'] . '.';
                                }
                                echo htmlspecialchars($name);
                                ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($citation['license_number'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($citation['plate_mv_engine_chassis_no']); ?></td>
                            <td>
                                <?php
                                $violations = $citation['violations'] ?? 'N/A';
                                if (strlen($violations) > 40) {
                                    $violations = substr($violations, 0, 40) . '...';
                                }
                                echo htmlspecialchars($violations);
                                ?>
                            </td>
                            <td><strong>P<?php echo number_format($citation['total_fine'], 2); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo $citation['status']; ?>">
                                    <?php echo ucfirst($citation['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1" role="group">
                                    <!-- View Details Button -->
                                    <button type="button" class="btn btn-info btn-sm" onclick="viewCitation(<?php echo $citation['citation_id']; ?>)" title="View Details">
                                        <i data-lucide="eye" style="width: 16px; height: 16px;"></i>
                                    </button>

                                    <!-- Process Payment Button -->
                                    <?php if ($can_pay && $citation['status'] !== 'paid'): ?>
                                    <a href="/tmg/public/process_payment.php?citation_id=<?php echo $citation['citation_id']; ?>" class="btn btn-success btn-sm" title="Process Payment">
                                        <i data-lucide="dollar-sign" style="width: 16px; height: 16px;"></i>
                                    </a>
                                    <?php endif; ?>

                                    <!-- Edit Button -->
                                    <?php if ($can_edit): ?>
                                        <?php if ($citation['status'] === 'paid'): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled title="Paid citations cannot be edited">
                                            <i data-lucide="lock" style="width: 16px; height: 16px;"></i>
                                        </button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-warning btn-sm" onclick="editCitation(<?php echo $citation['citation_id']; ?>); return false;" title="Edit Citation">
                                            <i data-lucide="edit" style="width: 16px; height: 16px;"></i>
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Quick Summary Button -->
                                    <button type="button" class="btn btn-primary btn-sm" onclick="quickInfo(<?php echo $citation['citation_id']; ?>); return false;" title="Quick Summary">
                                        <i data-lucide="info" style="width: 16px; height: 16px;"></i>
                                    </button>

                                    <!-- Delete Button -->
                                    <?php if (is_admin()): ?>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteCitation(<?php echo $citation['citation_id']; ?>); return false;" title="Delete Citation">
                                        <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-sm" disabled title="Admin access required to delete citations">
                                        <i data-lucide="lock" style="width: 16px; height: 16px;"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                    <i data-lucide="chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i data-lucide="chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            if ($start > 1): ?>
                <a href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">1</a>
                <?php if ($start > 2): ?>
                    <span>...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $total_pages): ?>
                <?php if ($end < $total_pages - 1): ?>
                    <span>...</span>
                <?php endif; ?>
                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                    <?php echo $total_pages; ?>
                </a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                    <i data-lucide="chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i data-lucide="chevron-right"></i></span>
            <?php endif; ?>
        </div>

        <div class="text-center mt-2">
            <small class="text-muted">
                Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_records); ?>
                of <?php echo number_format($total_records); ?> records
            </small>
        </div>
    <?php endif; ?>
</div>

<!-- View Citation Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <div class="modal-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <span>Citation Details</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewModalContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($can_change_status): ?>
                <div class="dropdown" id="statusDropdownContainer">
                    <button class="btn btn-primary dropdown-toggle" type="button" id="statusDropdown" data-bs-toggle="dropdown">
                        <i data-lucide="list-checks" style="width: 16px; height: 16px;"></i> Update Status
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="openStatusModal('contested')"><i data-lucide="shield-question" class="text-primary" style="width: 16px; height: 16px;"></i> Contest Citation</a></li>
                        <li><a class="dropdown-item" href="#" onclick="openStatusModal('dismissed')"><i data-lucide="x-circle" class="text-secondary" style="width: 16px; height: 16px;"></i> Dismiss Citation</a></li>
                        <li><a class="dropdown-item" href="#" onclick="openStatusModal('void')"><i data-lucide="ban" class="text-danger" style="width: 16px; height: 16px;"></i> Void Citation</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="openStatusModal('pending')"><i data-lucide="clock" class="text-warning" style="width: 16px; height: 16px;"></i> Reset to Pending</a></li>
                    </ul>
                </div>
                <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" disabled title="Enforcer/Admin access required to change citation status">
                    <i data-lucide="lock" style="width: 16px; height: 16px;"></i> Update Status (Restricted)
                </button>
                <?php endif; ?>
                <?php if ($can_edit): ?>
                <button type="button" class="btn btn-warning" id="editFromViewBtn">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-outline-warning" disabled title="Enforcer/Admin access required to edit citations">
                    <i class="fas fa-lock"></i> Edit (Restricted)
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-info" onclick="printCitation()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Info Modal -->
<div class="modal fade" id="quickInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <div class="modal-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <span>Quick Summary</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="quickInfoContent">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" id="viewFullDetailsBtn">
                    <i class="fas fa-eye"></i> View Full Details
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <div class="modal-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <span>Update Citation Status</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" name="citation_id" id="statusCitationId">
                    <input type="hidden" name="new_status" id="newStatus">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">

                    <div class="alert alert-info" id="statusAlertInfo">
                        <i class="fas fa-info-circle"></i>
                        <span id="statusMessage"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason/Notes (Optional)</label>
                        <textarea name="reason" class="form-control" rows="4" placeholder="Enter reason for status change..."></textarea>
                        <small class="text-muted">This will be appended to the citation remarks.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStatusBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF Token for JS (hidden) -->
<div data-csrf-token="<?php echo generate_token(); ?>" style="display:none;"></div>
