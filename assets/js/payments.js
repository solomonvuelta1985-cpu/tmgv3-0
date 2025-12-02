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
    tableBody.innerHTML = '<tr><td colspan="9" class="text-center">Loading...</td></tr>';

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
                tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(error => {
            loadingSpinner.style.display = 'none';
            tableBody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading payments: ${error.message}</td></tr>`;
            console.error('Error:', error);
        });
}

/**
 * Display payments in table
 */
function displayPayments(payments) {
    const tableBody = document.getElementById('paymentsTableBody');

    if (!payments || payments.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="9" class="text-center">No payments found</td></tr>';
        return;
    }

    let html = '';
    payments.forEach(payment => {
        const statusBadge = getStatusBadge(payment.status);
        const paymentDate = formatDateTime(payment.payment_date);
        const paymentMethod = formatPaymentMethod(payment.payment_method);

        html += `
            <tr>
                <td>${paymentDate}</td>
                <td><strong>${escapeHtml(payment.receipt_number)}</strong></td>
                <td>${escapeHtml(payment.ticket_number)}</td>
                <td>${escapeHtml(payment.driver_name || 'N/A')}</td>
                <td class="text-end"><strong>₱${formatMoney(payment.amount_paid)}</strong></td>
                <td>${paymentMethod}</td>
                <td>${escapeHtml(payment.collector_name || 'N/A')}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="viewReceipt(${payment.payment_id})" title="View Receipt">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="printReceipt(${payment.receipt_id})" title="Print Receipt">
                            <i class="fas fa-print"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="downloadReceipt(${payment.payment_id})" title="Download PDF">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
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
 * View receipt (opens in new window)
 */
function viewReceipt(paymentId) {
    window.open(`../api/receipt_generate.php?payment_id=${paymentId}&mode=inline`, '_blank');
}

/**
 * Print receipt
 */
function printReceipt(receiptId) {
    const printWindow = window.open(`../api/receipt_print.php?receipt_id=${receiptId}&mode=inline`, '_blank');
    printWindow.addEventListener('load', function() {
        printWindow.print();
    });
}

/**
 * Download receipt PDF
 */
function downloadReceipt(paymentId) {
    window.location.href = `../api/receipt_generate.php?payment_id=${paymentId}&mode=download`;
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
