/**
 * Payment Modal JavaScript
 * Handles payment recording modal functionality
 */

/**
 * Open payment modal for a citation
 */
function openPaymentModal(citationData) {
    // Populate citation summary
    document.getElementById('citation-id').value = citationData.citation_id;
    document.getElementById('modal-ticket-number').textContent = citationData.ticket_number;
    document.getElementById('modal-driver-name').textContent = citationData.driver_name;
    document.getElementById('modal-license-number').textContent = citationData.license_number || 'N/A';
    document.getElementById('modal-plate-number').textContent = citationData.plate_number || 'N/A';
    document.getElementById('modal-citation-date').textContent = formatDate(citationData.citation_date);
    document.getElementById('modal-total-fine').textContent = '₱' + formatMoney(citationData.total_fine);

    // Set status badge
    const statusBadge = document.getElementById('modal-status');
    statusBadge.textContent = citationData.status.toUpperCase();
    statusBadge.className = 'badge ' + getStatusClass(citationData.status);

    // Set amount to total fine
    document.getElementById('amount-paid').value = citationData.total_fine;

    // Reset form
    document.getElementById('paymentForm').reset();
    document.getElementById('citation-id').value = citationData.citation_id;
    document.getElementById('amount-paid').value = citationData.total_fine;

    // Hide conditional fields
    document.getElementById('check-details').style.display = 'none';
    document.getElementById('reference-number-field').style.display = 'none';

    // Hide alert
    document.getElementById('payment-alert').style.display = 'none';

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

/**
 * Submit payment
 */
function submitPayment() {
    const form = document.getElementById('paymentForm');
    const submitBtn = document.getElementById('submit-payment-btn');
    const alertBox = document.getElementById('payment-alert');
    const alertMessage = document.getElementById('payment-alert-message');

    // Validate form
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Disable submit button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Prepare form data
    const formData = new FormData(form);

    // Submit via AJAX
    fetch('../api/payment_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            showPaymentAlert('success', 'Payment recorded successfully! Generating receipt...');

            // Wait a moment then download receipt
            setTimeout(() => {
                // Download receipt
                window.open(`../api/receipt_generate.php?payment_id=${data.payment_id}&mode=inline`, '_blank');

                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();

                // Reload page or update citation list
                if (typeof loadCitations === 'function') {
                    loadCitations();
                } else if (typeof location !== 'undefined') {
                    location.reload();
                }
            }, 1500);
        } else {
            showPaymentAlert('danger', data.message || 'Error recording payment');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Record Payment & Generate Receipt';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showPaymentAlert('danger', 'Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Record Payment & Generate Receipt';
    });
}

/**
 * Show payment alert message
 */
function showPaymentAlert(type, message) {
    const alertBox = document.getElementById('payment-alert');
    const alertMessage = document.getElementById('payment-alert-message');

    alertBox.className = `alert alert-${type} alert-dismissible fade show mt-3`;
    alertMessage.innerHTML = message;
    alertBox.style.display = 'block';
}

/**
 * Close payment alert
 */
function closePaymentAlert() {
    document.getElementById('payment-alert').style.display = 'none';
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
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
 * Get status class for badge
 */
function getStatusClass(status) {
    const classes = {
        'pending': 'bg-warning',
        'paid': 'bg-success',
        'contested': 'bg-info',
        'dismissed': 'bg-secondary',
        'void': 'bg-dark'
    };
    return classes[status] || 'bg-secondary';
}
