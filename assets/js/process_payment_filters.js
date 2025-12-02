/**
 * Process Payment Filters & Pagination
 * High-performance AJAX-based filtering for 10,000-20,000+ records
 */

// Global state
let currentPage = 1;
let currentLimit = 25;
let currentFilters = {};
let currentData = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing Process Payment Filters...');

    // Load initial data
    loadCitations(1);

    // Set up Enter key to trigger search
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyFilters();
        }
    });

    // Set up real-time search (debounced)
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (this.value.length >= 3 || this.value.length === 0) {
                applyFilters();
            }
        }, 500);
    });

    // Quick sort change
    document.getElementById('sortBy').addEventListener('change', function() {
        applyFilters();
    });
});

/**
 * Load citations from API with filters and pagination
 */
function loadCitations(page = 1, limit = currentLimit) {
    currentPage = page;
    currentLimit = limit;

    // Show loading state
    showLoading();

    // Build query parameters
    const params = new URLSearchParams({
        page: page,
        limit: limit,
        search: document.getElementById('searchInput').value || '',
        date_from: document.getElementById('dateFrom').value || '',
        date_to: document.getElementById('dateTo').value || '',
        min_amount: document.getElementById('minAmount').value || '',
        max_amount: document.getElementById('maxAmount').value || '',
        violation_type: document.getElementById('violationType').value || '',
        sort_by: document.getElementById('sortBy').value || 'date_desc'
    });

    // Remove empty parameters
    for (let [key, value] of [...params.entries()]) {
        if (!value) params.delete(key);
    }

    currentFilters = Object.fromEntries(params);

    // Make AJAX request
    fetch(buildApiUrl(`api/pending_citations.php?${params.toString()}`))
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                currentData = data.data;
                renderTable(data.data);
                renderPagination(data.pagination);
                renderStatistics(data.statistics);
                populateViolationTypes(data.available_violations);
                updateCitationCount(data.pagination.total_records);

                // Show/hide export button
                document.getElementById('exportBtn').style.display =
                    data.data.length > 0 ? 'inline-block' : 'none';

                // Show/hide stats card
                document.getElementById('statsCard').style.display =
                    data.pagination.total_records > 0 ? 'block' : 'none';
            } else {
                showError(data.message || 'Failed to load citations');
            }
        })
        .catch(error => {
            console.error('Error loading citations:', error);
            showError('Failed to connect to the server. Please try again.');
        })
        .finally(() => {
            hideLoading();
        });
}

/**
 * Render table rows
 */
function renderTable(citations) {
    const tbody = document.getElementById('citationsTableBody');
    const noResults = document.getElementById('noResults');
    const tableContainer = document.getElementById('tableContainer');

    if (citations.length === 0) {
        tbody.innerHTML = '';
        noResults.style.display = 'block';
        tableContainer.style.display = 'none';
        return;
    }

    noResults.style.display = 'none';
    tableContainer.style.display = 'block';

    tbody.innerHTML = citations.map(citation => {
        // Store citation data as base64 to avoid JSON escaping issues
        const citationJson = btoa(JSON.stringify(citation));
        return `
        <tr>
            <td><strong>${escapeHtml(citation.ticket_number)}</strong></td>
            <td>${escapeHtml(citation.driver_name)}</td>
            <td>${escapeHtml(citation.license_number || 'N/A')}</td>
            <td>
                ${escapeHtml(citation.plate_number || 'N/A')}<br>
                <small class="text-muted">${escapeHtml(citation.vehicle_description || '')}</small>
            </td>
            <td>${escapeHtml(citation.violations || 'N/A')}</td>
            <td>${formatDate(citation.apprehension_datetime)}</td>
            <td><strong class="text-success">₱${formatMoney(citation.total_fine)}</strong></td>
            <td><span class="badge status-badge bg-warning">${escapeHtml(citation.status)}</span></td>
            <td>
                <button
                    class="btn btn-sm btn-primary"
                    data-citation="${citationJson}"
                    onclick="openPaymentModalFromData(this)"
                >
                    <i class="fas fa-money-bill-wave"></i> Process Payment
                </button>
            </td>
        </tr>
    `;
    }).join('');
}

/**
 * Render pagination controls
 */
function renderPagination(pagination) {
    const controls = document.getElementById('paginationControls');
    const info = document.getElementById('paginationInfo');

    // Update info text
    info.textContent = `Showing ${pagination.from} to ${pagination.to} of ${pagination.total_records} citations`;

    // Build pagination buttons
    let html = '';

    // Previous button
    html += `
        <li class="page-item ${!pagination.has_prev ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadCitations(${pagination.current_page - 1}); return false;">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
        </li>
    `;

    // Page numbers (smart pagination)
    const maxPages = 7; // Show max 7 page buttons
    let startPage = Math.max(1, pagination.current_page - 3);
    let endPage = Math.min(pagination.total_pages, startPage + maxPages - 1);

    if (endPage - startPage < maxPages - 1) {
        startPage = Math.max(1, endPage - maxPages + 1);
    }

    // First page
    if (startPage > 1) {
        html += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadCitations(1); return false;">1</a>
            </li>
        `;
        if (startPage > 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    // Page number buttons
    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadCitations(${i}); return false;">${i}</a>
            </li>
        `;
    }

    // Last page
    if (endPage < pagination.total_pages) {
        if (endPage < pagination.total_pages - 1) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        html += `
            <li class="page-item">
                <a class="page-link" href="#" onclick="loadCitations(${pagination.total_pages}); return false;">${pagination.total_pages}</a>
            </li>
        `;
    }

    // Next button
    html += `
        <li class="page-item ${!pagination.has_next ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadCitations(${pagination.current_page + 1}); return false;">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        </li>
    `;

    controls.innerHTML = html;
}

/**
 * Render statistics
 */
function renderStatistics(stats) {
    document.getElementById('statTotalCitations').textContent = stats.total_citations.toLocaleString();
    document.getElementById('statTotalAmount').textContent = '₱' + formatMoney(stats.total_amount);
    document.getElementById('statAvgFine').textContent = '₱' + formatMoney(stats.avg_fine);
    document.getElementById('statFineRange').textContent =
        `₱${formatMoney(stats.min_fine)} - ₱${formatMoney(stats.max_fine)}`;
}

/**
 * Populate violation types dropdown
 */
function populateViolationTypes(violations) {
    const select = document.getElementById('violationType');
    const currentValue = select.value;

    // Keep "All Violations" option and add dynamic ones
    const options = ['<option value="">All Violations</option>'];
    violations.forEach(violation => {
        options.push(`<option value="${escapeHtml(violation)}">${escapeHtml(violation)}</option>`);
    });

    select.innerHTML = options.join('');
    select.value = currentValue; // Restore selection
}

/**
 * Update citation count in header
 */
function updateCitationCount(count) {
    document.getElementById('citationCount').textContent = `(${count})`;
}

/**
 * Apply filters (called by filter buttons)
 */
function applyFilters() {
    loadCitations(1, currentLimit);
}

/**
 * Clear all filters
 */
function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    document.getElementById('minAmount').value = '';
    document.getElementById('maxAmount').value = '';
    document.getElementById('violationType').value = '';
    document.getElementById('sortBy').value = 'date_desc';

    loadCitations(1, currentLimit);
}

/**
 * Change page size
 */
function changePageSize(newLimit) {
    loadCitations(1, parseInt(newLimit));
}

/**
 * Export to CSV
 */
function exportToCSV() {
    if (currentData.length === 0) {
        alert('No data to export');
        return;
    }

    // Build CSV content
    const headers = ['Ticket Number', 'Driver Name', 'License', 'Plate Number', 'Violation', 'Date', 'Amount Due', 'Status'];
    const rows = currentData.map(citation => [
        citation.ticket_number,
        citation.driver_name,
        citation.license_number || 'N/A',
        citation.plate_number || 'N/A',
        citation.violations || 'N/A',
        formatDate(citation.apprehension_datetime),
        citation.total_fine,
        citation.status
    ]);

    let csvContent = headers.join(',') + '\n';
    csvContent += rows.map(row =>
        row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
    ).join('\n');

    // Create download link
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', `pending_citations_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Show loading state
 */
function showLoading() {
    document.getElementById('loadingSpinner').style.display = 'flex';
    document.getElementById('tableContainer').style.opacity = '0.5';
    document.getElementById('noResults').style.display = 'none';
}

/**
 * Hide loading state
 */
function hideLoading() {
    document.getElementById('loadingSpinner').style.display = 'none';
    document.getElementById('tableContainer').style.opacity = '1';
}

/**
 * Show error message
 */
function showError(message) {
    const tbody = document.getElementById('citationsTableBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="9" class="text-center text-danger py-4">
                <i class="fas fa-exclamation-triangle"></i> ${escapeHtml(message)}
            </td>
        </tr>
    `;
    document.getElementById('tableContainer').style.display = 'block';
    document.getElementById('noResults').style.display = 'none';
}

/**
 * Utility: Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Utility: Format date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Utility: Format money
 */
function formatMoney(amount) {
    return parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Open payment modal from button data attribute
 */
function openPaymentModalFromData(button) {
    try {
        const citationBase64 = button.getAttribute('data-citation');
        const citationJson = atob(citationBase64); // Decode from base64
        const citation = JSON.parse(citationJson);
        openPaymentModal(citation);
    } catch (error) {
        console.error('Error parsing citation data:', error);
        alert('Error loading citation data. Please try again.');
    }
}
