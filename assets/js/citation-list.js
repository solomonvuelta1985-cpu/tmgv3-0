/**
 * Citation List JavaScript
 * Handles all client-side logic for the citations listing page
 */

// Search on enter
document.querySelector('#searchForm input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

// Filter by status
function filterByStatus(status) {
    const url = new URL(window.location);
    url.searchParams.set('status', status);
    url.searchParams.set('page', '1');
    window.location = url;
}

// Current citation ID for status updates
let currentCitationId = null;

// View citation
function viewCitation(id) {
    currentCitationId = id;
    const modal = new bootstrap.Modal(document.getElementById('viewModal'));
    modal.show();

    fetch(`../api/citation_get.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                displayCitationDetails(data.citation);

                // Handle Edit button based on citation status
                const editBtn = document.getElementById('editFromViewBtn');
                if (editBtn) {
                    if (data.citation.status === 'paid') {
                        editBtn.disabled = true;
                        editBtn.classList.remove('btn-warning');
                        editBtn.classList.add('btn-secondary');
                        editBtn.title = 'Paid citations cannot be edited';
                        editBtn.innerHTML = '<i class="fas fa-lock"></i> Edit (Paid)';
                        editBtn.onclick = null;
                    } else {
                        editBtn.disabled = false;
                        editBtn.classList.remove('btn-secondary');
                        editBtn.classList.add('btn-warning');
                        editBtn.title = 'Edit Citation';
                        editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit';
                        editBtn.onclick = () => editCitation(id);
                    }
                }

                // Handle Update Status dropdown based on citation status
                const statusDropdown = document.getElementById('statusDropdown');
                if (statusDropdown) {
                    if (data.citation.status === 'paid') {
                        statusDropdown.disabled = true;
                        statusDropdown.classList.remove('btn-primary');
                        statusDropdown.classList.add('btn-secondary');
                        statusDropdown.title = 'Paid citations cannot have status changed';
                        statusDropdown.innerHTML = '<i data-lucide="lock" style="width: 16px; height: 16px;"></i> Status Locked';
                        // Re-initialize Lucide icons for the new lock icon
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    } else {
                        statusDropdown.disabled = false;
                        statusDropdown.classList.remove('btn-secondary');
                        statusDropdown.classList.add('btn-primary');
                        statusDropdown.title = '';
                        statusDropdown.innerHTML = '<i data-lucide="list-checks" style="width: 16px; height: 16px;"></i> Update Status';
                        // Re-initialize Lucide icons
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }
                }
            } else {
                document.getElementById('viewModalContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('viewModalContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load citation details.
                </div>
            `;
        });
}

function displayCitationDetails(citation) {
    const violationCards = citation.violations.map(v => `
        <div class="violation-card">
            <div class="violation-info">
                <div class="violation-type">${v.violation_type}</div>
                <div class="violation-offense">Offense #${v.offense_count}</div>
            </div>
            <div class="violation-fine">₱${parseFloat(v.fine_amount).toFixed(2)}</div>
        </div>
    `).join('');

    const html = `
        <div class="modal-two-column">
            <!-- Left Column -->
            <div class="modal-column-left">
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fas fa-ticket-alt"></i>
                        <h6 class="detail-card-title">Citation Information</h6>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Ticket Number</span>
                            <span class="detail-value"><strong>${citation.ticket_number}</strong></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status</span>
                            <span class="detail-value"><span class="badge badge-${citation.status}">${citation.status.toUpperCase()}</span></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Date/Time</span>
                            <span class="detail-value">${new Date(citation.apprehension_datetime).toLocaleString()}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Place of Apprehension</span>
                            <span class="detail-value">${citation.place_of_apprehension}</span>
                        </div>
                    </div>
                </div>

                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fas fa-user"></i>
                        <h6 class="detail-card-title">Driver Information</h6>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="detail-label">Full Name</span>
                            <span class="detail-value"><strong>${citation.last_name}, ${citation.first_name} ${citation.middle_initial || ''} ${citation.suffix || ''}</strong></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Age</span>
                            <span class="detail-value">${citation.age || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">License Number</span>
                            <span class="detail-value">${citation.license_number || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">License Type</span>
                            <span class="detail-value">${citation.license_type || 'N/A'}</span>
                        </div>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="detail-label">Address</span>
                            <span class="detail-value">${citation.zone ? 'Zone ' + citation.zone + ', ' : ''}${citation.barangay}, ${citation.municipality}, ${citation.province}</span>
                        </div>
                    </div>
                </div>

                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fas fa-car"></i>
                        <h6 class="detail-card-title">Vehicle Information</h6>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Plate/MV/Engine/Chassis No.</span>
                            <span class="detail-value"><strong>${citation.plate_mv_engine_chassis_no}</strong></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Vehicle Type</span>
                            <span class="detail-value">${citation.vehicle_type || 'N/A'}</span>
                        </div>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="detail-label">Vehicle Description</span>
                            <span class="detail-value">${citation.vehicle_description || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="modal-column-right">
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h6 class="detail-card-title">Violations (${citation.violations.length})</h6>
                    </div>
                    ${violationCards}
                    <div class="total-fine-display">
                        <span class="total-fine-label">Total Fine</span>
                        <span class="total-fine-amount">₱${parseFloat(citation.total_fine).toFixed(2)}</span>
                    </div>
                </div>

                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fas fa-user-shield"></i>
                        <h6 class="detail-card-title">Apprehension Officer</h6>
                    </div>
                    <div class="detail-value">${citation.apprehension_officer || 'N/A'}</div>
                </div>

                ${citation.remarks ? `
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-comment"></i>
                            <h6 class="detail-card-title">Remarks</h6>
                        </div>
                        <div class="remarks-box">${citation.remarks}</div>
                    </div>
                ` : ''}
            </div>
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
                        <i class="fas fa-exclamation-circle"></i> ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('quickInfoContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to load citation info.
                </div>
            `;
        });
}

function displayQuickInfo(citation) {
    const violationCards = citation.violations.map(v => `
        <div class="violation-card" style="margin-bottom: 8px;">
            <div class="violation-info">
                <div class="violation-type" style="font-size: 0.9rem;">${v.violation_type}</div>
                <div class="violation-offense" style="font-size: 0.75rem;">Offense #${v.offense_count}</div>
            </div>
            <div class="violation-fine" style="font-size: 1rem;">₱${parseFloat(v.fine_amount).toFixed(2)}</div>
        </div>
    `).join('');

    const html = `
        <div style="background: #f8f9fa; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0" style="color: #0f172a; font-weight: 600;">
                    <i class="fas fa-user" style="color: #3b82f6;"></i>
                    ${citation.last_name}, ${citation.first_name} ${citation.middle_initial || ''}
                </h6>
                <span class="badge badge-${citation.status}">${citation.status.toUpperCase()}</span>
            </div>
            <div style="font-size: 0.85rem; color: #6b7280;">
                <i class="fas fa-ticket-alt"></i> Ticket: <strong style="color: #0f172a;">${citation.ticket_number}</strong>
            </div>
        </div>

        <div class="detail-grid" style="margin-bottom: 16px;">
            <div class="detail-item">
                <span class="detail-label">Age</span>
                <span class="detail-value">${citation.age || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">License #</span>
                <span class="detail-value">${citation.license_number || 'N/A'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Vehicle</span>
                <span class="detail-value">${citation.plate_mv_engine_chassis_no}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Date</span>
                <span class="detail-value">${new Date(citation.apprehension_datetime).toLocaleDateString()}</span>
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 10px; font-weight: 600;">
                <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Violations (${citation.violations.length})
            </div>
            ${violationCards}
        </div>

        <div class="total-fine-display" style="margin-bottom: 0;">
            <span class="total-fine-label">Total Fine</span>
            <span class="total-fine-amount">₱${parseFloat(citation.total_fine).toFixed(2)}</span>
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
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

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
                this.innerHTML = '<i class="fas fa-check"></i> Confirm';
            });
        });
    }
});
