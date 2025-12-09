/**
 * Payment Management JavaScript
 * Handles payment list, filters, and interactions
 */

let currentPage = 1;
const itemsPerPage = 50;
let currentFilters = {};

// Load payments on page load
document.addEventListener('DOMContentLoaded', function() {
    loadPayments();

    // Set up filter form
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        currentPage = 1;
        loadPayments();
    });
});

/**
 * Load payments list with current filters
 */
function loadPayments() {
    const loadingSpinner = document.getElementById('loadingSpinner');
    const tableBody = document.getElementById('paymentsTableBody');

    // Show loading
    loadingSpinner.style.display = 'block';
    tableBody.innerHTML = '<tr><td colspan="10" class="text-center">Loading...</td></tr>';

    // Get filter values
    currentFilters = {
        date_from: document.getElementById('dateFrom').value,
        date_to: document.getElementById('dateTo').value,
        payment_method: document.getElementById('paymentMethod').value,
        collected_by: document.getElementById('cashier').value,
        receipt_number: document.getElementById('receiptNumber').value,
        ticket_number: document.getElementById('ticketNumber').value,
        status: document.getElementById('status').value,
        limit: itemsPerPage,
        offset: (currentPage - 1) * itemsPerPage,
        include_stats: 'false'
    };

    // Build query string
    const queryString = Object.keys(currentFilters)
        .filter(key => currentFilters[key])
        .map(key => `${key}=${encodeURIComponent(currentFilters[key])}`)
        .join('&');

    // Fetch payments
    fetch(`../api/payment_list.php?${queryString}`)
        .then(response => response.json())
        .then(data => {
            loadingSpinner.style.display = 'none';

            if (data.success) {
                displayPayments(data.data);
                updatePagination(data.pagination);
            } else {
                tableBody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(error => {
            loadingSpinner.style.display = 'none';
            tableBody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error loading payments: ${error.message}</td></tr>`;
            console.error('Error:', error);
        });
}

/**
 * Display payments in table
 */
function displayPayments(payments) {
    const tableBody = document.getElementById('paymentsTableBody');

    if (!payments || payments.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="10" class="text-center">No payments found</td></tr>';
        return;
    }

    let html = '';
    payments.forEach(payment => {
        const statusBadge = getStatusBadge(payment.status);
        const paymentDate = formatDateTime(payment.payment_date);
        const citationDate = formatDate(payment.citation_date);
        const paymentMethod = formatPaymentMethod(payment.payment_method);

        html += `
            <tr>
                <td>${paymentDate}</td>
                <td>${citationDate}</td>
                <td><strong>${escapeHtml(payment.receipt_number)}</strong></td>
                <td>${escapeHtml(payment.ticket_number)}</td>
                <td>${escapeHtml(payment.driver_name || 'N/A')}</td>
                <td class="text-end"><strong>₱${formatMoney(payment.amount_paid)}</strong></td>
                <td>${paymentMethod}</td>
                <td>${escapeHtml(payment.collector_name || 'N/A')}</td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewReceipt(${payment.payment_id})" title="View Receipt">
                        <i class="fas fa-receipt"></i> View
                    </button>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

/**
 * Update pagination
 */
function updatePagination(pagination) {
    const paginationEl = document.getElementById('pagination');
    const totalPages = Math.ceil(pagination.count / itemsPerPage) || 1;

    let html = '';

    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Previous</a>
        </li>
    `;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>
                </li>
            `;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    // Next button
    html += `
        <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Next</a>
        </li>
    `;

    paginationEl.innerHTML = html;
}

/**
 * Change page
 */
function changePage(page) {
    currentPage = page;
    loadPayments();
}

/**
 * View payment details in modal
 */
let currentPaymentId = null;

function viewReceipt(paymentId) {
    currentPaymentId = paymentId;

    // Show loading in modal
    const modalContent = document.getElementById('paymentDetailsContent');
    modalContent.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading payment details...</p>
        </div>
    `;

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
    modal.show();

    // Fetch payment details
    fetch(`../api/payment_details.php?payment_id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPaymentDetails(data.payment);
            } else {
                modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error loading payment details: ${error.message}
                </div>
            `;
        });
}

/**
 * Display payment details in modal
 */
function displayPaymentDetails(payment) {
    const modalContent = document.getElementById('paymentDetailsContent');

    // Get amount paid
    const amountPaid = parseFloat(payment.amount_paid);

    // Format status badge
    const statusBadge = getStatusBadge(payment.status);

    const html = `
        <!-- Transaction Details Section -->
        <div style="background: #ffffff; border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; border: 1px solid #e5e7eb;">
            <h6 style="font-size: 0.875rem; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid #f3f4f6;">
                <i class="fas fa-info-circle" style="color: #6b7280; margin-right: 0.5rem;"></i> Transaction Details
            </h6>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">OR Number:</div>
                    <div style="font-size: 1rem; font-weight: 600; color: #059669; font-family: 'Courier New', monospace; background: #d1fae5; padding: 0.375rem 0.75rem; border-radius: 4px; display: inline-block;">
                        ${escapeHtml(payment.receipt_number)}
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Ticket Number:</div>
                    <div style="font-size: 0.9375rem; font-weight: 500; color: #111827;">${escapeHtml(payment.ticket_number)}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Driver:</div>
                    <div style="font-size: 0.9375rem; font-weight: 500; color: #111827;">${escapeHtml(payment.driver_name || 'N/A')}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Payment Method:</div>
                    <div style="font-size: 0.9375rem; font-weight: 500; color: #111827; text-transform: capitalize;">${formatPaymentMethod(payment.payment_method)}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Processed By:</div>
                    <div style="font-size: 0.9375rem; font-weight: 500; color: #111827;">${escapeHtml(payment.collector_name || 'N/A')}</div>
                </div>
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Date Processed:</div>
                    <div style="font-size: 0.9375rem; font-weight: 500; color: #111827;">${formatDateTime(payment.payment_date)}</div>
                </div>
                ${payment.reference_number ? `
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Reference Number:</div>
                    <div style="font-size: 0.9375rem; font-weight: 500; color: #111827;">${escapeHtml(payment.reference_number)}</div>
                </div>
                ` : ''}
                <div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem;">Status:</div>
                    <div>${statusBadge}</div>
                </div>
            </div>
        </div>

        <!-- Payment Breakdown Section -->
        <div style="background: #ffffff; border-radius: 8px; padding: 1.25rem; border: 1px solid #e5e7eb;">
            <h6 style="font-size: 0.875rem; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 2px solid #f3f4f6;">
                <i class="fas fa-money-bill-wave" style="color: #6b7280; margin-right: 0.5rem;"></i> Payment Breakdown
            </h6>

            <div style="background: #dbeafe; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; border-radius: 6px; padding: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.875rem; color: #1e40af; font-weight: 600;">Amount Paid:</span>
                    <span style="font-size: 1.5rem; font-weight: 700; color: #2563eb;">₱${formatMoney(amountPaid)}</span>
                </div>
            </div>
        </div>

        ${payment.notes ? `
        <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 1rem; margin-top: 1rem;">
            <div style="font-size: 0.75rem; color: #92400e; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-sticky-note" style="margin-right: 0.5rem;"></i> NOTES
            </div>
            <div style="font-size: 0.875rem; color: #78350f; white-space: pre-wrap;">${escapeHtml(payment.notes)}</div>
        </div>
        ` : ''}

        <div style="background: #eff6ff; border: 1px solid #dbeafe; border-radius: 6px; padding: 1rem; margin-top: 1rem;">
            <div style="font-size: 0.875rem; color: #1e40af;">
                <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
                <strong>Next Step:</strong> Click "Print Receipt" below to print the official receipt.
            </div>
        </div>
    `;

    modalContent.innerHTML = html;
}

/**
 * Print payment receipt
 */
function printPaymentReceipt() {
    if (currentPaymentId) {
        window.open(`../api/receipt_generate.php?payment_id=${currentPaymentId}&mode=inline`, '_blank');
    }
}

/**
 * Reset filters
 */
function resetFilters() {
    document.getElementById('filterForm').reset();
    currentPage = 1;
    loadPayments();
}

/**
 * Export payments to CSV
 */
function exportPayments(format) {
    if (format === 'csv') {
        const queryString = Object.keys(currentFilters)
            .filter(key => currentFilters[key] && key !== 'limit' && key !== 'offset')
            .map(key => `${key}=${encodeURIComponent(currentFilters[key])}`)
            .join('&');

        window.location.href = `../api/payment_export.php?format=csv&${queryString}`;
    }
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const badges = {
        'completed': '<span class="badge bg-success">Completed</span>',
        'pending': '<span class="badge bg-warning">Pending</span>',
        'failed': '<span class="badge bg-danger">Failed</span>',
        'refunded': '<span class="badge bg-info">Refunded</span>',
        'cancelled': '<span class="badge bg-secondary">Cancelled</span>'
    };
    return badges[status] || `<span class="badge bg-secondary">${status}</span>`;
}

/**
 * Format payment method
 */
function formatPaymentMethod(method) {
    const methods = {
        'cash': 'Cash',
        'check': 'Check',
        'online': 'Online Transfer',
        'gcash': 'GCash',
        'paymaya': 'PayMaya',
        'bank_transfer': 'Bank Transfer',
        'money_order': 'Money Order'
    };
    return methods[method] || method;
}

/**
 * Format date time
 */
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format date only (without time)
 */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Format money amount
 */
function formatMoney(amount) {
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Quick search by OR number
 * Clears other filters and searches only by OR number
 */
function quickSearchOR() {
    const orInput = document.getElementById('receiptNumber');
    const orNumber = orInput.value.trim().toUpperCase();

    if (!orNumber) {
        alert('Please enter an OR number');
        orInput.focus();
        return;
    }

    // Validate CGVM format (optional - will still search if invalid)
    const cgvmPattern = /^CGVM[0-9]{8}$/;
    if (!cgvmPattern.test(orNumber)) {
        const proceed = confirm('OR number format may be incorrect. Expected: CGVM + 8 digits\n\nProceed with search anyway?');
        if (!proceed) {
            orInput.focus();
            return;
        }
    }

    // Clear all other filters
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    document.getElementById('paymentMethod').value = '';
    document.getElementById('cashier').value = '';
    document.getElementById('ticketNumber').value = '';
    document.getElementById('status').value = '';

    // Set OR number (already uppercase)
    orInput.value = orNumber;

    // Reset page and load
    currentPage = 1;
    loadPayments();
}
