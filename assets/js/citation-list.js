/**
 * Citation List JavaScript
 * Handles all client-side logic for the citations listing page
 */

// Search input handling
const searchInput = document.getElementById('searchInput');
const searchClearBtn = document.getElementById('searchClearBtn');

// Show/hide clear button based on input
if (searchInput) {
    searchInput.addEventListener('input', function() {
        if (this.value.trim().length > 0) {
            searchClearBtn.style.display = 'flex';
            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        } else {
            searchClearBtn.style.display = 'none';
        }
    });

    // Search on enter
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            showSkeletonLoader();
            this.form.submit();
        }
    });
}

// Clear search
function clearSearch() {
    if (searchInput) {
        searchInput.value = '';
        searchClearBtn.style.display = 'none';
    }

    const url = new URL(window.location);
    url.searchParams.delete('search');
    url.searchParams.set('page', '1');

    showSkeletonLoader();
    window.location = url;
}

// Filter by status
function filterByStatus(status) {
    showSkeletonLoader();
    const url = new URL(window.location);
    url.searchParams.set('status', status);
    url.searchParams.set('page', '1');
    window.location = url;
}

// Sort citations
function sortCitations(sortBy) {
    showSkeletonLoader();
    const url = new URL(window.location);
    url.searchParams.set('sort', sortBy);
    url.searchParams.set('page', '1');
    window.location = url;
}

// Show skeleton loader
function showSkeletonLoader() {
    const skeleton = document.getElementById('skeletonLoader');
    const content = document.getElementById('tableContent');
    if (skeleton && content) {
        skeleton.style.display = 'block';
        content.style.display = 'none';
    }
}

// Hide skeleton loader
function hideSkeletonLoader() {
    const skeleton = document.getElementById('skeletonLoader');
    const content = document.getElementById('tableContent');
    if (skeleton && content) {
        skeleton.style.display = 'none';
        content.style.display = 'block';
    }
}

// Sticky column scroll hint for mobile
document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.querySelector('.table-container');
    const scrollHint = document.querySelector('.mobile-scroll-hint');

    if (tableContainer) {
        // Hide hint on scroll
        tableContainer.addEventListener('scroll', function() {
            if (this.scrollLeft > 10) {
                this.classList.add('scrolled');
            } else {
                this.classList.remove('scrolled');
            }
        });

        // Auto-hide hint after 5 seconds
        if (scrollHint) {
            setTimeout(function() {
                if (scrollHint && tableContainer) {
                    tableContainer.classList.add('scrolled');
                }
            }, 5000);
        }

        // Hide hint on touch/click
        tableContainer.addEventListener('touchstart', function() {
            this.classList.add('scrolled');
        }, { once: true });
    }

    // Re-initialize Lucide icons for the hint
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

// Current citation ID for status updates
let currentCitationId = null;

// View citation
function viewCitation(id) {
    currentCitationId = id;
    const modal = new bootstrap.Modal(document.getElementById('viewModal'));
    modal.show();

    // Fetch citation details and driver history in parallel
    Promise.all([
        fetch(`../api/citation_get.php?id=${id}`).then(r => r.json()),
        // Only fetch history if citation has a driver_id
        fetch(`../api/citation_get.php?id=${id}`)
            .then(r => r.json())
            .then(citationData => {
                if (citationData.status === 'success' && citationData.citation.driver_id) {
                    return fetch(`../api/get_driver_history.php?driver_id=${citationData.citation.driver_id}`)
                        .then(r => r.json());
                }
                return { success: false };
            })
    ])
    .then(([citationData, historyData]) => {
        if (citationData.status === 'success') {
            displayCitationDetails(citationData.citation, historyData);

            // Handle Edit button based on citation status
            const editBtn = document.getElementById('editFromViewBtn');
            if (editBtn) {
                if (citationData.citation.status === 'paid') {
                    editBtn.disabled = true;
                    editBtn.classList.remove('btn-warning');
                    editBtn.classList.add('btn-outline');
                    editBtn.title = 'Paid citations cannot be edited';
                    editBtn.innerHTML = '<i data-lucide="lock" style="width: 16px; height: 16px;"></i><span>Edit (Locked)</span>';
                    editBtn.onclick = null;
                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                } else {
                    editBtn.disabled = false;
                    editBtn.classList.remove('btn-outline');
                    editBtn.classList.add('btn-warning');
                    editBtn.title = 'Edit Citation';
                    editBtn.innerHTML = '<i data-lucide="edit" style="width: 16px; height: 16px;"></i><span>Edit</span>';
                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                    editBtn.onclick = () => editCitation(id);
                }
            }

            // Handle Update Status dropdown based on citation status
            const statusDropdown = document.getElementById('statusDropdown');
            if (statusDropdown) {
                if (citationData.citation.status === 'paid') {
                    statusDropdown.disabled = true;
                    statusDropdown.classList.remove('btn-primary', 'dropdown-toggle');
                    statusDropdown.classList.add('btn-outline');
                    statusDropdown.title = 'Paid citations cannot have status changed';
                    statusDropdown.innerHTML = '<i data-lucide="lock" style="width: 16px; height: 16px;"></i><span>Status (Locked)</span>';
                    statusDropdown.removeAttribute('data-bs-toggle');
                    // Re-initialize Lucide icons for the new lock icon
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                } else {
                    statusDropdown.disabled = false;
                    statusDropdown.classList.remove('btn-outline');
                    statusDropdown.classList.add('btn-primary', 'dropdown-toggle');
                    statusDropdown.title = '';
                    statusDropdown.innerHTML = '<i data-lucide="list-checks" style="width: 16px; height: 16px;"></i><span>Update Status</span>';
                    statusDropdown.setAttribute('data-bs-toggle', 'dropdown');
                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            }
        } else {
            document.getElementById('viewModalContent').innerHTML = `
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i> ${citationData.message}
                </div>
            `;
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    })
        .catch(error => {
            document.getElementById('viewModalContent').innerHTML = `
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i> Failed to load citation details.
                </div>
            `;
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
}

function displayCitationDetails(citation, historyData) {
    const violationsList = citation.violations.map(v => `
        <div class="violation-item">
            <div class="violation-item-content">
                <div class="violation-item-name">${v.violation_type}</div>
                <div class="violation-item-meta">Offense #${v.offense_count}</div>
            </div>
            <div class="violation-item-amount">₱${parseFloat(v.fine_amount).toFixed(2)}</div>
        </div>
    `).join('');

    // Generate driver history section (exclude current citation)
    let driverHistoryHtml = '';
    if (historyData && historyData.success && historyData.citations && historyData.citations.length > 1) {
        const otherCitations = historyData.citations.filter(c => c.citation_id != citation.citation_id).slice(0, 5);

        if (otherCitations.length > 0) {
            const historyCards = otherCitations.map(c => `
                <div class="history-card">
                    <div class="history-card-header">
                        <span class="history-ticket-number">Citation #${c.ticket_number}</span>
                        <span class="history-date">
                            <i data-lucide="calendar" style="width: 12px; height: 12px;"></i>
                            ${new Date(c.apprehension_datetime).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                        </span>
                    </div>
                    <div class="history-card-body">
                        <div class="history-detail">
                            <i data-lucide="alert-triangle" style="width: 14px; height: 14px;"></i>
                            <span>${c.violations || 'No violations'}</span>
                        </div>
                        <div class="history-detail">
                            <i data-lucide="map-pin" style="width: 14px; height: 14px;"></i>
                            <span>${c.place_of_apprehension}</span>
                        </div>
                        <div class="history-detail">
                            <i data-lucide="user" style="width: 14px; height: 14px;"></i>
                            <span>${c.apprehension_officer || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `).join('');

            driverHistoryHtml = `
                <div class="driver-history-section">
                    <div class="driver-history-header">
                        <div class="driver-history-title">
                            <i data-lucide="history" style="width: 18px; height: 18px;"></i>
                            <h6>Driver History</h6>
                        </div>
                        <span class="driver-history-count">${otherCitations.length} previous citation${otherCitations.length !== 1 ? 's' : ''}</span>
                    </div>
                    <div class="driver-history-cards">
                        ${historyCards}
                    </div>
                    ${historyData.total_citations > 6 ? `
                        <div class="driver-history-footer">
                            <a href="driver_history.php?driver_id=${citation.driver_id}" class="btn btn-sm btn-outline-primary">
                                View Complete History (${historyData.total_citations} total)
                                <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                            </a>
                        </div>
                    ` : ''}
                </div>
            `;
        }
    }

    const html = `
        <div class="citation-detail-container">
            <!-- Header Section with Status -->
            <div class="citation-detail-header">
                <div class="citation-header-main">
                    <div class="citation-header-icon">
                        <i data-lucide="file-text" style="width: 20px; height: 20px;"></i>
                    </div>
                    <div>
                        <div class="citation-ticket-number">${citation.ticket_number}</div>
                        <div class="citation-datetime">
                            <i data-lucide="calendar" style="width: 14px; height: 14px;"></i>
                            ${new Date(citation.apprehension_datetime).toLocaleString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            })}
                        </div>
                    </div>
                </div>
                <span class="badge badge-${citation.status} badge-lg">${citation.status.toUpperCase()}</span>
            </div>

            <!-- Two Column Layout -->
            <div class="citation-detail-grid">
                <!-- Left Column -->
                <div class="citation-detail-col">
                    <!-- Driver Section -->
                    <div class="detail-section">
                        <div class="detail-section-header">
                            <i data-lucide="user" style="width: 18px; height: 18px;"></i>
                            <h6>Driver Information</h6>
                        </div>
                        <div class="detail-rows">
                            <div class="detail-row">
                                <span class="detail-row-label">Full Name</span>
                                <span class="detail-row-value">${citation.last_name}, ${citation.first_name} ${citation.middle_initial || ''} ${citation.suffix || ''}</span>
                            </div>
                            <div class="detail-row detail-row-split">
                                <div class="detail-row-half">
                                    <span class="detail-row-label">Age</span>
                                    <span class="detail-row-value">${citation.age || 'N/A'}</span>
                                </div>
                                <div class="detail-row-half">
                                    <span class="detail-row-label">License Type</span>
                                    <span class="detail-row-value">${citation.license_type || 'N/A'}</span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <span class="detail-row-label">License Number</span>
                                <span class="detail-row-value">${citation.license_number || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-row-label">Address</span>
                                <span class="detail-row-value">${citation.zone ? 'Zone ' + citation.zone + ', ' : ''}${citation.barangay}, ${citation.municipality}, ${citation.province}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Section -->
                    <div class="detail-section">
                        <div class="detail-section-header">
                            <i data-lucide="car" style="width: 18px; height: 18px;"></i>
                            <h6>Vehicle Information</h6>
                        </div>
                        <div class="detail-rows">
                            <div class="detail-row detail-row-split">
                                <div class="detail-row-half">
                                    <span class="detail-row-label">Plate/MV Number</span>
                                    <span class="detail-row-value">${citation.plate_mv_engine_chassis_no}</span>
                                </div>
                                <div class="detail-row-half">
                                    <span class="detail-row-label">Vehicle Type</span>
                                    <span class="detail-row-value">${citation.vehicle_type || 'N/A'}</span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <span class="detail-row-label">Description</span>
                                <span class="detail-row-value">${citation.vehicle_description || 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Apprehension Section -->
                    <div class="detail-section">
                        <div class="detail-section-header">
                            <i data-lucide="map-pin" style="width: 18px; height: 18px;"></i>
                            <h6>Apprehension Details</h6>
                        </div>
                        <div class="detail-rows">
                            <div class="detail-row">
                                <span class="detail-row-label">Location</span>
                                <span class="detail-row-value">${citation.place_of_apprehension}</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-row-label">Officer</span>
                                <span class="detail-row-value">${citation.apprehension_officer || 'N/A'}</span>
                            </div>
                        </div>
                    </div>

                    ${citation.remarks ? `
                        <div class="detail-section">
                            <div class="detail-section-header">
                                <i data-lucide="message-square" style="width: 18px; height: 18px;"></i>
                                <h6>Remarks</h6>
                            </div>
                            <div class="detail-rows">
                                <div class="detail-row">
                                    <p class="remarks-text">${citation.remarks}</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                </div>

                <!-- Right Column - Violations -->
                <div class="citation-detail-col">
                    <div class="violations-section">
                        <div class="violations-header">
                            <div class="violations-header-title">
                                <i data-lucide="alert-triangle" style="width: 18px; height: 18px;"></i>
                                <h6>Violations</h6>
                            </div>
                            <span class="violations-count">${citation.violations.length} ${citation.violations.length === 1 ? 'violation' : 'violations'}</span>
                        </div>
                        <div class="violations-list">
                            ${violationsList}
                        </div>
                        <div class="violations-total">
                            <span class="violations-total-label">Total Fine</span>
                            <span class="violations-total-amount">₱${parseFloat(citation.total_fine).toFixed(2)}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Driver History Section -->
            ${driverHistoryHtml}
        </div>
    `;

    document.getElementById('viewModalContent').innerHTML = html;

    // Re-initialize Lucide icons if available
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Edit citation
function editCitation(id) {
    window.location.href = `edit_citation.php?id=${id}`;
}

// Delete citation - requires CSRF token from page
function deleteCitation(id) {
    if (confirm('Are you sure you want to delete this citation? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('citation_id', id);
        // CSRF token will be passed from the PHP page
        const csrfToken = document.querySelector('[data-csrf-token]').getAttribute('data-csrf-token');
        formData.append('csrf_token', csrfToken);

        fetch('../api/citation_delete.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Citation deleted successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Failed to delete citation.');
        });
    }
}

// Export CSV
function exportCSV() {
    const searchParam = new URLSearchParams(window.location.search).get('search') || '';
    const statusParam = new URLSearchParams(window.location.search).get('status') || '';
    window.location.href = `../api/citations_export.php?search=${encodeURIComponent(searchParam)}&status=${encodeURIComponent(statusParam)}`;
}

// Print citation
function printCitation() {
    window.print();
}

// Status update functions
function openStatusModal(newStatus) {
    if (!currentCitationId) {
        alert('No citation selected');
        return;
    }

    const statusMessages = {
        'contested': 'You are about to mark this citation as <strong>CONTESTED</strong>. This indicates the violator is disputing the citation.',
        'dismissed': 'You are about to <strong>DISMISS</strong> this citation. This removes the violation without payment.',
        'void': 'You are about to <strong>VOID</strong> this citation. This permanently invalidates the citation.',
        'pending': 'You are about to reset this citation to <strong>PENDING</strong> status.'
    };

    document.getElementById('statusCitationId').value = currentCitationId;
    document.getElementById('newStatus').value = newStatus;
    document.getElementById('statusMessage').innerHTML = statusMessages[newStatus] || 'Update citation status.';
    document.querySelector('#statusForm textarea[name="reason"]').value = '';

    // Change alert color based on status
    const alertBox = document.getElementById('statusAlertInfo');
    alertBox.className = 'alert';
    if (newStatus === 'void' || newStatus === 'dismissed') {
        alertBox.classList.add('alert-warning');
    } else {
        alertBox.classList.add('alert-info');
    }

    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    statusModal.show();
}

// Quick status update from table
function quickStatusUpdate(citationId, newStatus) {
    currentCitationId = citationId;
    openStatusModal(newStatus);
}

// Quick info modal
function quickInfo(id) {
    currentCitationId = id;
    const modal = new bootstrap.Modal(document.getElementById('quickInfoModal'));
    modal.show();

    fetch(`../api/citation_get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                displayQuickInfo(data.citation);
                document.getElementById('viewFullDetailsBtn').onclick = () => {
                    bootstrap.Modal.getInstance(document.getElementById('quickInfoModal')).hide();
                    viewCitation(id);
                };
            } else {
                document.getElementById('quickInfoContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i> ${data.message}
                    </div>
                `;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }
        })
        .catch(error => {
            document.getElementById('quickInfoContent').innerHTML = `
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="width: 18px; height: 18px;"></i> Failed to load citation info.
                </div>
            `;
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
}

function displayQuickInfo(citation) {
    const violationCards = citation.violations.map(v => `
        <div class="qsm-violation-item">
            <div class="qsm-violation-content">
                <div class="qsm-violation-name">${v.violation_type}</div>
                <div class="qsm-violation-meta">Offense #${v.offense_count}</div>
            </div>
            <div class="qsm-violation-price">₱${parseFloat(v.fine_amount).toFixed(2)}</div>
        </div>
    `).join('');

    const html = `
        <div class="qsm-header">
            <div class="qsm-driver-info">
                <div class="qsm-driver-name">${citation.last_name}, ${citation.first_name} ${citation.middle_initial || ''}</div>
                <div class="qsm-ticket-info">
                    <span class="qsm-ticket-label">Ticket:</span>
                    <span class="qsm-ticket-number">${citation.ticket_number}</span>
                </div>
            </div>
            <span class="badge badge-${citation.status}">${citation.status.toUpperCase()}</span>
        </div>

        <div class="qsm-details-grid">
            <div class="qsm-detail-item">
                <div class="qsm-detail-label">AGE</div>
                <div class="qsm-detail-value">${citation.age || 'N/A'}</div>
            </div>
            <div class="qsm-detail-item">
                <div class="qsm-detail-label">LICENSE NUMBER</div>
                <div class="qsm-detail-value">${citation.license_number || 'N/A'}</div>
            </div>
            <div class="qsm-detail-item">
                <div class="qsm-detail-label">VEHICLE</div>
                <div class="qsm-detail-value">${citation.plate_mv_engine_chassis_no}</div>
            </div>
            <div class="qsm-detail-item">
                <div class="qsm-detail-label">DATE</div>
                <div class="qsm-detail-value">${new Date(citation.apprehension_datetime).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' })}</div>
            </div>
        </div>

        <div class="qsm-violations">
            <div class="qsm-violations-title">
                <i data-lucide="alert-triangle" style="width: 16px; height: 16px;"></i>
                <span>Violations (${citation.violations.length})</span>
            </div>
            ${violationCards}
        </div>

        <div class="qsm-total">
            <span class="qsm-total-label">TOTAL FINE</span>
            <span class="qsm-total-amount">₱${parseFloat(citation.total_fine).toFixed(2)}</span>
        </div>
    `;

    document.getElementById('quickInfoContent').innerHTML = html;

    // Re-initialize Lucide icons if available
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Confirm status update
document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('confirmStatusBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            const formData = new FormData(document.getElementById('statusForm'));

            this.disabled = true;
            this.innerHTML = '<i data-lucide="loader" style="width: 16px; height: 16px;"></i> Processing...';

            fetch('../api/citation_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    // Close both modals and reload
                    const statusModalEl = document.getElementById('statusModal');
                    const viewModalEl = document.getElementById('viewModal');

                    if (statusModalEl) {
                        const statusModalInstance = bootstrap.Modal.getInstance(statusModalEl);
                        if (statusModalInstance) statusModalInstance.hide();
                    }
                    if (viewModalEl) {
                        const viewModalInstance = bootstrap.Modal.getInstance(viewModalEl);
                        if (viewModalInstance) viewModalInstance.hide();
                    }
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    if (data.new_csrf_token) {
                        document.querySelector('#statusForm input[name="csrf_token"]').value = data.new_csrf_token;
                    }
                }
            })
            .catch(error => {
                alert('Failed to update status: ' + error.message);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i data-lucide="check" style="width: 16px; height: 16px;"></i> Confirm';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            });
        });
    }
});
