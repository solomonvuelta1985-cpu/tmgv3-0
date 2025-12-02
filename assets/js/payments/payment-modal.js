/**
 * Consolidated Payment Modal JavaScript
 * Handles payment processing modal and cash/change calculations
 *
 * @package TrafficCitationSystem
 * @subpackage Assets/JS
 */

let paymentModal;
let currentCitation = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal
    const modalElement = document.getElementById('paymentModal');
    if (modalElement) {
        paymentModal = new bootstrap.Modal(modalElement);

        // Payment method change event
        const paymentMethodSelect = document.getElementById('payment_method');
        if (paymentMethodSelect) {
            paymentMethodSelect.addEventListener('change', handlePaymentMethodChange);
        }

        // Cash received input event - calculate change
        const cashReceivedInput = document.getElementById('cash_received');
        if (cashReceivedInput) {
            cashReceivedInput.addEventListener('input', calculateChange);
        }

        // Payment form submit
        const paymentForm = document.getElementById('paymentForm');
        if (paymentForm) {
            paymentForm.addEventListener('submit', handlePaymentSubmit);
        }
    }
});

/**
 * Open payment modal with citation data
 *
 * @param {Object} citation Citation object with all details
 */
function openPaymentModal(citation) {
    currentCitation = citation;

    // Populate citation details
    document.getElementById('citation_id').value = citation.citation_id;
    document.getElementById('modal_ticket_number').textContent = citation.ticket_number;
    document.getElementById('modal_driver_name').textContent = citation.driver_name;
    document.getElementById('modal_license').textContent = citation.license_number || 'N/A';
    document.getElementById('modal_vehicle').textContent = (citation.plate_number || 'N/A') + ' - ' + (citation.vehicle_description || '');
    document.getElementById('modal_violation').textContent = citation.violations || 'N/A';
    document.getElementById('modal_date').textContent = formatDate(citation.apprehension_datetime);
    document.getElementById('modal_amount').textContent = parseFloat(citation.total_fine).toFixed(2);

    // Reset form
    document.getElementById('paymentForm').reset();
    document.getElementById('citation_id').value = citation.citation_id;
    document.getElementById('payment_method').value = 'cash';
    document.getElementById('receipt_number').value = '';
    document.getElementById('cash_received').value = '';

    const changeDisplay = document.getElementById('changeDisplay');
    if (changeDisplay) {
        changeDisplay.style.display = 'none';
    }

    // Show cash fields by default
    handlePaymentMethodChange();

    // Show modal
    paymentModal.show();
}

/**
 * Handle payment method change
 * Shows/hides appropriate fields based on payment method
 */
function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('payment_method').value;
    const cashFields = document.getElementById('cashFields');
    const referenceField = document.getElementById('referenceField');
    const checkFields = document.getElementById('checkFields');
    const cashReceivedInput = document.getElementById('cash_received');
    const referenceNumberInput = document.getElementById('reference_number');

    // Reset all fields
    if (cashFields) cashFields.style.display = 'none';
    if (referenceField) referenceField.style.display = 'none';
    if (checkFields) checkFields.style.display = 'none';

    // Show appropriate fields based on payment method
    if (paymentMethod === 'cash') {
        if (cashFields) cashFields.style.display = 'block';
        if (cashReceivedInput) cashReceivedInput.required = true;
        if (referenceNumberInput) referenceNumberInput.required = false;
    } else if (paymentMethod === 'check') {
        if (checkFields) checkFields.style.display = 'block';
        if (referenceField) referenceField.style.display = 'block';
        if (cashReceivedInput) cashReceivedInput.required = false;
        if (referenceNumberInput) referenceNumberInput.required = false;
    } else {
        // For online, gcash, paymaya, bank_transfer, money_order
        if (referenceField) referenceField.style.display = 'block';
        if (cashReceivedInput) cashReceivedInput.required = false;
        if (referenceNumberInput) referenceNumberInput.required = false;
    }

    // Reset change display
    const changeDisplay = document.getElementById('changeDisplay');
    if (changeDisplay) {
        changeDisplay.style.display = 'none';
    }
}

/**
 * Calculate change automatically for cash payments
 */
function calculateChange() {
    const amountDue = parseFloat(document.getElementById('modal_amount').textContent);
    const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
    const change = cashReceived - amountDue;
    const changeDisplay = document.getElementById('changeDisplay');

    if (!changeDisplay) return;

    if (cashReceived > 0) {
        document.getElementById('change_amount').textContent = change.toFixed(2);
        changeDisplay.style.display = 'block';

        // Change color based on sufficient/insufficient amount
        if (change < 0) {
            changeDisplay.style.background = 'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)';
            changeDisplay.style.borderColor = '#fca5a5';
            document.getElementById('change_amount').style.color = '#dc2626';
        } else {
            changeDisplay.style.background = 'linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%)';
            changeDisplay.style.borderColor = '#7dd3fc';
            document.getElementById('change_amount').style.color = '#0284c7';
        }
    } else {
        changeDisplay.style.display = 'none';
    }
}

/**
 * Handle payment form submission
 *
 * @param {Event} e Form submit event
 */
function handlePaymentSubmit(e) {
    e.preventDefault();

    const paymentMethod = document.getElementById('payment_method').value;
    const amountDue = parseFloat(document.getElementById('modal_amount').textContent);
    const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
    const receiptNumber = document.getElementById('receipt_number').value.trim();

    // Validation for OR/receipt number (REQUIRED)
    if (!receiptNumber) {
        showAlert('OR/Receipt number is required! Please enter the OR number from the physical receipt.', 'danger');
        document.getElementById('receipt_number').focus();
        return;
    }

    // Validation for cash payments
    if (paymentMethod === 'cash' && cashReceived < amountDue) {
        showAlert('Cash received (₱' + cashReceived.toFixed(2) + ') is less than the amount due (₱' + amountDue.toFixed(2) + ')!', 'danger');
        return;
    }

    // Confirm payment
    const confirmMessage = `Confirm payment processing?\n\n` +
                          `Ticket: ${currentCitation.ticket_number}\n` +
                          `Driver: ${currentCitation.driver_name}\n` +
                          `Amount: ₱${amountDue.toFixed(2)}\n` +
                          `Method: ${paymentMethod.toUpperCase()}\n` +
                          `OR Number: ${receiptNumber}\n\n` +
                          `This action cannot be undone.`;

    if (!confirm(confirmMessage)) {
        return;
    }

    // Disable submit button
    const submitBtn = document.getElementById('confirmPaymentBtn');
    const originalBtnContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    // Prepare form data
    const formData = new FormData(e.target);
    formData.append('amount_paid', amountDue);

    // Calculate change for cash payments
    if (paymentMethod === 'cash') {
        formData.append('change_amount', (cashReceived - amountDue).toFixed(2));
    }

    // Submit payment
    fetch(buildApiUrl('api/payment_process.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('✓ Payment successful! Receipt Number: ' + data.receipt_number, 'success');
            paymentModal.hide();

            // Automatically open receipt in new window
            if (data.receipt_number) {
                window.open(buildPublicUrl('public/receipt.php?receipt=' + data.receipt_number), '_blank');
            }

            // Reload page after 2 seconds
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showAlert('Error: ' + data.message, 'danger');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnContent;
        }
    })
    .catch(error => {
        showAlert('Error processing payment: ' + error.message, 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnContent;
    });
}

// ====================================
// UTILITY FUNCTIONS
// ====================================

/**
 * Format date for display
 *
 * @param {string} dateString Date string to format
 * @return {string} Formatted date
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format currency for display
 *
 * @param {number} amount Amount to format
 * @return {string} Formatted currency
 */
function formatMoney(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Show alert message
 *
 * @param {string} message Alert message
 * @param {string} type Alert type (success, danger, warning, info)
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    const container = document.querySelector('.container-fluid');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);

        // Scroll to top to show alert
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

/**
 * Sanitize HTML to prevent XSS
 *
 * @param {string} text Text to sanitize
 * @return {string} Sanitized text
 */
function sanitizeHTML(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
