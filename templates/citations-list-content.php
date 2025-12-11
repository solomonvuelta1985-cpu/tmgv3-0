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
        <div class="header-content">
            <div>
                <h1 class="page-title">
                    <i data-lucide="file-text" style="width: 28px; height: 28px;"></i>
                    Traffic Citations
                </h1>
                <p class="page-subtitle">View and manage all traffic violation citations</p>
            </div>
            <div class="header-actions">
                <?php if (can_create_citation()): ?>
                <a href="index2.php" class="btn btn-primary">
                    <i data-lucide="plus" style="width: 18px; height: 18px;"></i>
                    <span>New Citation</span>
                </a>
                <?php else: ?>
                <button type="button" class="btn btn-outline-primary" disabled title="Enforcer/Admin access required to create citations">
                    <i data-lucide="lock" style="width: 18px; height: 18px;"></i>
                    <span>New Citation</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon">
                <i data-lucide="file-text" style="width: 24px; height: 24px;"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                <div class="stat-label">Total Citations</div>
            </div>
        </div>
        <div class="stat-card yellow">
            <div class="stat-icon">
                <i data-lucide="clock" style="width: 24px; height: 24px;"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon">
                <i data-lucide="check-circle-2" style="width: 24px; height: 24px;"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['paid']); ?></div>
                <div class="stat-label">Paid</div>
            </div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon">
                <i data-lucide="alert-circle" style="width: 24px; height: 24px;"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['contested']); ?></div>
                <div class="stat-label">Contested</div>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon">
                <i data-lucide="banknote" style="width: 24px; height: 24px;"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">₱<?php echo number_format($stats['total_fines'], 2); ?></div>
                <div class="stat-label">Total Fines</div>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="controls-section">
        <!-- Search Bar -->
        <div class="search-container">
            <form method="GET" action="" id="searchForm" class="search-form">
                <div class="search-input-wrapper">
                    <i data-lucide="search" class="search-icon" style="width: 20px; height: 20px;"></i>
                    <input type="text" name="search" id="searchInput" class="search-input" placeholder="Search by ticket number, name, license, or plate number..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="button" class="search-clear" id="searchClearBtn" onclick="clearSearch()" style="display: <?php echo !empty($search) ? 'flex' : 'none'; ?>">
                        <i data-lucide="x" style="width: 18px; height: 18px;"></i>
                    </button>
                </div>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            </form>
        </div>

        <!-- Filters and Actions Row -->
        <div class="filters-actions-row">
            <!-- Filters -->
            <div class="filters-group">
                <div class="filter-item">
                    <label class="filter-label">
                        <i data-lucide="filter" style="width: 16px; height: 16px;"></i>
                        Status
                    </label>
                    <select name="status" id="statusFilter" class="filter-select" onchange="filterByStatus(this.value)">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="contested" <?php echo $status_filter === 'contested' ? 'selected' : ''; ?>>Contested</option>
                        <option value="dismissed" <?php echo $status_filter === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label class="filter-label">
                        <i data-lucide="arrow-up-down" style="width: 16px; height: 16px;"></i>
                        Sort By
                    </label>
                    <select id="sortBy" class="filter-select" onchange="sortCitations(this.value)">
                        <option value="date_desc">Date (Newest First)</option>
                        <option value="date_asc">Date (Oldest First)</option>
                        <option value="ticket_asc">Ticket Number (A-Z)</option>
                        <option value="ticket_desc">Ticket Number (Z-A)</option>
                        <option value="fine_desc">Fine Amount (High to Low)</option>
                        <option value="fine_asc">Fine Amount (Low to High)</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons-group">
                <button type="button" class="btn btn-outline" onclick="exportCSV()" title="Export to CSV">
                    <i data-lucide="download" style="width: 18px; height: 18px;"></i>
                    <span>Export</span>
                </button>
                <button type="button" class="btn btn-outline" onclick="window.print()" title="Print">
                    <i data-lucide="printer" style="width: 18px; height: 18px;"></i>
                    <span>Print</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Citations Table Section -->
    <div class="table-section">
        <div class="table-section-header">
            <h2 class="table-section-title">
                <i data-lucide="list" style="width: 18px; height: 18px;"></i>
                All Citations
            </h2>
            <span class="table-section-count"><?php echo number_format($total_records); ?> records</span>
        </div>

        <!-- Data Table -->
        <div class="table-container">
        <!-- Mobile Scroll Hint -->
        <div class="mobile-scroll-hint d-md-none">
            <i data-lucide="chevrons-right" style="width: 14px; height: 14px;"></i>
            <span>Swipe to see more</span>
        </div>

        <!-- Skeleton Loader -->
        <div id="skeletonLoader" class="skeleton-loader" style="display: none;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
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
                    <?php for ($i = 0; $i < 10; $i++): ?>
                    <tr>
                        <td><div class="skeleton skeleton-text skeleton-sm"></div></td>
                        <td><div class="skeleton skeleton-text"></div></td>
                        <td><div class="skeleton skeleton-text"></div></td>
                        <td><div class="skeleton skeleton-text skeleton-lg"></div></td>
                        <td><div class="skeleton skeleton-text"></div></td>
                        <td><div class="skeleton skeleton-text"></div></td>
                        <td><div class="skeleton skeleton-text skeleton-lg"></div></td>
                        <td><div class="skeleton skeleton-text skeleton-sm"></div></td>
                        <td><div class="skeleton skeleton-badge"></div></td>
                        <td>
                            <div class="d-flex gap-1">
                                <div class="skeleton skeleton-btn"></div>
                                <div class="skeleton skeleton-btn"></div>
                                <div class="skeleton skeleton-btn"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <!-- Actual Content -->
        <div id="tableContent">
            <?php if (empty($citations)): ?>
                <div class="empty-state">
                    <i data-lucide="folder-open" style="width: 64px; height: 64px;"></i>
                    <h5>No citations found</h5>
                    <p>No records match your search criteria.</p>
                    <a href="index2.php" class="btn btn-primary mt-3">
                        <i data-lucide="plus" style="width: 18px; height: 18px;"></i> Create First Citation
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
    </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" onclick="showSkeletonLoader()">
                    <i data-lucide="chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="disabled"><i data-lucide="chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            if ($start > 1): ?>
                <a href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" onclick="showSkeletonLoader()">1</a>
                <?php if ($start > 2): ?>
                    <span>...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" onclick="showSkeletonLoader()">
                        <?php echo $i; ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($end < $total_pages): ?>
                <?php if ($end < $total_pages - 1): ?>
                    <span>...</span>
                <?php endif; ?>
                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" onclick="showSkeletonLoader()">
                    <?php echo $total_pages; ?>
                </a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" onclick="showSkeletonLoader()">
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
                        <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i data-lucide="x" style="width: 16px; height: 16px;"></i> Close
                </button>
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
                    <i data-lucide="edit" style="width: 16px; height: 16px;"></i> Edit
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-outline-warning" disabled title="Enforcer/Admin access required to edit citations">
                    <i data-lucide="lock" style="width: 16px; height: 16px;"></i> Edit (Restricted)
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-info" onclick="printCitation()">
                    <i data-lucide="printer" style="width: 16px; height: 16px;"></i> Print
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
                        <i data-lucide="info" style="width: 20px; height: 20px;"></i>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i data-lucide="x" style="width: 16px; height: 16px;"></i> Close
                </button>
                <button type="button" class="btn btn-info" id="viewFullDetailsBtn">
                    <i data-lucide="eye" style="width: 16px; height: 16px;"></i> View Full Details
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
                        <i data-lucide="list-checks" style="width: 20px; height: 20px;"></i>
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
                        <i data-lucide="info" style="width: 18px; height: 18px;"></i>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i data-lucide="x" style="width: 16px; height: 16px;"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmStatusBtn">
                    <i data-lucide="check" style="width: 16px; height: 16px;"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSRF Token for JS (hidden) -->
<div data-csrf-token="<?php echo generate_token(); ?>" style="display:none;"></div>
