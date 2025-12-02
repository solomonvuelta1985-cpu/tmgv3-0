/**
 * Process Payment JavaScript
 * Handles payment processing modal and cash/change calculations
 */

let paymentModal;
let currentCitation = null;
let formIsDirty = false;

// OR Number validation pattern - CGVM format (CGVM + exactly 8 digits)
const OR_NUMBER_PATTERN = /^CGVM[0-9]{8}$/;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal
    paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

    // Payment method change event
    document.getElementById('payment_method').addEventListener('change', handlePaymentMethodChange);

    // Cash received input event - calculate change
    document.getElementById('cash_received').addEventListener('input', calculateChange);

    // OR number validation
    document.getElementById('receipt_number').addEventListener('input', validateOrNumberFormat);
    document.getElementById('receipt_number').addEventListener('blur', validateOrNumberFormat);

    // Payment form submit
    document.getElementById('paymentForm').addEventListener('submit', handlePaymentSubmit);

    // Track form changes for dirty state
    document.getElementById('paymentForm').addEventListener('input', markFormAsDirty);

    // Warn before closing modal if form has data
    document.getElementById('paymentModal').addEventListener('hide.bs.modal', preventDataLoss);
});

/**
 * Open payment modal with citation data
 * First checks if citation already has a pending_print payment
 */
function openPaymentModal(citation) {
    currentCitation = citation;

    // Check for existing pending_print payment first
    fetch(buildApiUrl(`api/check_pending_payment.php?citation_id=${citation.citation_id}`))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.has_pending) {
                // Citation has a pending payment - show resume options
                showPendingPaymentOptions(data.payment);
            } else {
                // No pending payment - proceed with normal flow
                openNewPaymentForm(citation);
            }
        })
        .catch(error => {
            console.error('Error checking pending payment:', error);
            // On error, proceed with normal flow
            openNewPaymentForm(citation);
        });
}

/**
 * Open new payment form (normal flow)
 */
function openNewPaymentForm(citation) {
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
    document.getElementById('changeDisplay').style.display = 'none';

    // Reset dirty state
    formIsDirty = false;

    // Reset OR validation feedback
    removeOrValidationFeedback();

    // Show cash fields by default
    handlePaymentMethodChange();

    // Show modal
    paymentModal.show();
}

/**
 * Show options for existing pending payment
 */
function showPendingPaymentOptions(payment) {
    Swal.fire({
        title: 'Pending Payment Found',
        html: `
            <div class="text-start">
                <p class="mb-3">This citation already has a payment that wasn't finalized:</p>
                <div class="alert alert-warning mb-3">
                    <strong>OR Number:</strong> <span style="font-family: 'Courier New', monospace; font-weight: bold;">${payment.receipt_number}</span><br>
                    <strong>Amount:</strong> ₱${parseFloat(payment.amount_paid).toFixed(2)}<br>
                    <strong>Payment Method:</strong> ${payment.payment_method.replace('_', ' ').toUpperCase()}<br>
                    <strong>Date:</strong> ${new Date(payment.payment_date).toLocaleString()}
                </div>
                <p class="mb-0"><strong>What would you like to do?</strong></p>
            </div>
        `,
        icon: 'info',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check-circle"></i> Resume & Print Receipt',
        denyButtonText: '<i class="fas fa-trash"></i> Void & Start Over',
        cancelButtonText: '<i class="fas fa-times"></i> Cancel',
        confirmButtonColor: '#059669',
        denyButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        width: '600px'
    }).then((result) => {
        if (result.isConfirmed) {
            // Resume existing payment - show summary modal
            resumePendingPayment(payment);
        } else if (result.isDenied) {
            // Void and start over
            voidAndStartOver(payment.payment_id);
        }
        // If dismissed, do nothing
    });
}

/**
 * Resume pending payment - show summary and allow printing
 */
function resumePendingPayment(payment) {
    // Store payment info
    window.currentPaymentId = payment.payment_id;
    window.currentReceiptNumber = payment.receipt_number;

    // Build summary content
    const summaryContent = `
        <div class="text-center mb-4">
            <div class="success-icon-wrapper">
                <i class="fas fa-clock fa-5x text-warning"></i>
            </div>
            <h3 class="mt-3 mb-2">Resuming Pending Payment</h3>
            <p class="text-muted">This payment was recorded but not finalized</p>
        </div>

        <div class="payment-summary-card">
            <h6 class="summary-section-title">
                <i class="fas fa-receipt"></i> Transaction Details
            </h6>
            <div class="summary-row">
                <span class="summary-label">OR Number:</span>
                <span class="summary-value or-number">${payment.receipt_number}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Ticket Number:</span>
                <span class="summary-value">${payment.ticket_number}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Driver:</span>
                <span class="summary-value">${payment.driver_name}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Payment Method:</span>
                <span class="summary-value text-capitalize">${payment.payment_method.replace('_', ' ')}</span>
            </div>
            ${payment.reference_number ? `
            <div class="summary-row">
                <span class="summary-label">Reference Number:</span>
                <span class="summary-value">${payment.reference_number}</span>
            </div>
            ` : ''}
        </div>

        <div class="payment-summary-card mt-3">
            <h6 class="summary-section-title">
                <i class="fas fa-money-bill-wave"></i> Payment Breakdown
            </h6>
            <div class="summary-row">
                <span class="summary-label">Amount Paid:</span>
                <span class="summary-value">₱${parseFloat(payment.amount_paid).toFixed(2)}</span>
            </div>
            ${payment.cash_received ? `
            <div class="summary-row">
                <span class="summary-label">Cash Received:</span>
                <span class="summary-value">₱${parseFloat(payment.cash_received).toFixed(2)}</span>
            </div>
            <div class="summary-row highlight-change">
                <span class="summary-label"><strong>Change:</strong></span>
                <span class="summary-value"><strong>₱${parseFloat(payment.change_amount).toFixed(2)}</strong></span>
            </div>
            ` : ''}
        </div>

        <div class="alert alert-warning mt-4">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Important:</strong> Print the receipt and confirm to complete this transaction.
        </div>
    `;

    // Update modal content
    document.getElementById('paymentSummaryContent').innerHTML = summaryContent;

    // Show modal
    const summaryModal = new bootstrap.Modal(document.getElementById('printPreviewModal'));
    summaryModal.show();
}

/**
 * Void existing payment and start over
 */
function voidAndStartOver(paymentId) {
    // Check CSRF token exists
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
    if (!csrfToken) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Security token missing. Please reload the page and try again.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Show loading
    Swal.fire({
        title: 'Voiding Payment...',
        text: 'Please wait',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send void request
    const formData = new FormData();
    formData.append('payment_id', paymentId);
    formData.append('reason', 'Voided by cashier - starting new payment transaction');
    formData.append('csrf_token', csrfToken);

    fetch(buildApiUrl('api/payments/void_payment.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Payment Voided',
                text: 'Previous payment voided. Opening new payment form...',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                // Now open new payment form
                openNewPaymentForm(currentCitation);
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#dc2626'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error voiding payment: ' + error.message,
            confirmButtonColor: '#dc2626'
        });
    });
}

/**
 * Handle payment method change
 */
function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('payment_method').value;
    const cashReceivedField = document.getElementById('cashReceivedField');
    const referenceField = document.getElementById('referenceField');
    const cashReceivedInput = document.getElementById('cash_received');
    const referenceNumberInput = document.getElementById('reference_number');

    if (paymentMethod === 'cash') {
        // Show cash field, hide reference field
        cashReceivedField.style.display = 'block';
        referenceField.style.display = 'none';
        cashReceivedInput.required = true;
        referenceNumberInput.required = false;
    } else {
        // Hide cash field, show reference field
        cashReceivedField.style.display = 'none';
        referenceField.style.display = 'block';
        cashReceivedInput.required = false;
        referenceNumberInput.required = false;
    }

    // Reset change display
    document.getElementById('changeDisplay').style.display = 'none';
}

/**
 * Calculate change automatically
 */
function calculateChange() {
    const amountDue = parseFloat(document.getElementById('modal_amount').textContent);
    const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
    const change = cashReceived - amountDue;

    if (cashReceived > 0) {
        document.getElementById('change_amount').textContent = change.toFixed(2);
        document.getElementById('changeDisplay').style.display = 'block';

        // Change color based on sufficient/insufficient amount
        const changeDisplay = document.getElementById('changeDisplay');
        if (change < 0) {
            changeDisplay.style.backgroundColor = '#f8d7da';
            changeDisplay.style.color = '#dc3545';
        } else {
            changeDisplay.style.backgroundColor = '#cfe2ff';
            changeDisplay.style.color = '#0d6efd';
        }
    } else {
        document.getElementById('changeDisplay').style.display = 'none';
    }
}

/**
 * Handle payment form submission
 */
function handlePaymentSubmit(e) {
    e.preventDefault();

    const paymentMethod = document.getElementById('payment_method').value;
    const amountDue = parseFloat(document.getElementById('modal_amount').textContent);
    const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
    const receiptNumber = document.getElementById('receipt_number').value.trim();
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;

    // Check CSRF token exists
    if (!csrfToken) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Security token missing. Please reload the page and try again.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Validation for OR/receipt number (REQUIRED)
    if (!receiptNumber) {
        Swal.fire({
            icon: 'error',
            title: 'OR Number Required',
            text: 'Please enter the OR number from the physical receipt.',
            confirmButtonColor: '#dc2626'
        });
        document.getElementById('receipt_number').focus();
        return;
    }

    // Validate OR number format (CGVM + 8 digits)
    if (!OR_NUMBER_PATTERN.test(receiptNumber)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid OR Format',
            text: 'Expected format: CGVM followed by exactly 8 digits (e.g., CGVM15320501)',
            confirmButtonColor: '#dc2626'
        });
        document.getElementById('receipt_number').focus();
        return;
    }

    // Check for duplicate OR number
    // Show loading while checking
    Swal.fire({
        title: 'Validating OR Number...',
        text: 'Checking if OR number is available',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Check duplicate OR number
    fetch(buildApiUrl(`api/payments/check_or_duplicate.php?or_number=${encodeURIComponent(receiptNumber)}`))
        .then(response => response.json())
        .then(data => {
            Swal.close(); // Close loading dialog

            if (!data.available) {
                // OR number already used
                Swal.fire({
                    icon: 'error',
                    title: 'OR Number Already Used',
                    html: `
                        <div class="text-start">
                            <p class="mb-3"><strong>This OR number has already been used:</strong></p>
                            <div class="alert alert-danger mb-3">
                                <strong>OR Number:</strong> <span style="font-family: 'Courier New', monospace;">${data.existing_payment.receipt_number}</span><br>
                                <strong>Used For:</strong> Ticket #${data.existing_payment.ticket_number}<br>
                                <strong>Driver:</strong> ${data.existing_payment.driver_name}<br>
                                <strong>Amount:</strong> ₱${parseFloat(data.existing_payment.amount_paid).toFixed(2)}<br>
                                <strong>Date:</strong> ${new Date(data.existing_payment.payment_date).toLocaleString()}<br>
                                <strong>Status:</strong> <span class="badge bg-${data.existing_payment.status === 'completed' ? 'success' : 'warning'}">${data.existing_payment.status.toUpperCase()}</span>
                            </div>
                            <p class="mb-0 text-muted"><small><i class="fas fa-info-circle"></i> Please use a different OR number from your receipt booklet.</small></p>
                        </div>
                    `,
                    confirmButtonColor: '#dc2626',
                    width: '600px'
                });
                document.getElementById('receipt_number').focus();
                document.getElementById('receipt_number').select();
                return;
            }

            // OR number is available - proceed with validation and submission
            validateAndSubmitPayment(e, paymentMethod, amountDue, cashReceived);
        })
        .catch(error => {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: 'Error checking OR number: ' + error.message,
                confirmButtonColor: '#dc2626'
            });
        });
}

/**
 * Validate and submit payment after OR duplicate check passes
 */
function validateAndSubmitPayment(e, paymentMethod, amountDue, cashReceived) {
    // Validation for cash payments
    if (paymentMethod === 'cash' && cashReceived < amountDue) {
        Swal.fire({
            icon: 'error',
            title: 'Insufficient Cash',
            text: 'Cash received is less than the amount due!',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Confirm payment
    Swal.fire({
        title: 'Confirm Payment',
        text: 'This action cannot be undone. Proceed with payment processing?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Process Payment',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#6b7280'
    }).then((confirmResult) => {
        if (!confirmResult.isConfirmed) {
            return;
        }

        // Disable submit button
        const submitBtn = document.getElementById('confirmPaymentBtn');
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
                // Payment recorded with 'pending_print' status
                formIsDirty = false; // Reset dirty state
                paymentModal.hide();

                // Store payment info for later use
                window.currentPaymentId = data.payment_id;
                window.currentReceiptNumber = data.receipt_number;

                // Show receipt in preview modal instead of new tab
                showReceiptPreview(data.receipt_number, data.payment_id);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Payment Failed',
                    text: data.message,
                    confirmButtonColor: '#dc2626'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Payment';
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error processing payment: ' + error.message,
                confirmButtonColor: '#dc2626'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Payment';
        });
    });
}

/**
 * Show payment summary modal instead of receipt preview
 */
function showReceiptPreview(receiptNumber, paymentId) {
    // Store payment info
    window.currentPaymentId = paymentId;
    window.currentReceiptNumber = receiptNumber;

    // Get current citation details
    const citation = currentCitation;
    const paymentMethod = document.getElementById('payment_method').value;
    const amountPaid = parseFloat(document.getElementById('modal_amount').textContent);
    const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
    const changeAmount = (cashReceived - amountPaid).toFixed(2);
    const referenceNumber = document.getElementById('reference_number').value;

    // Build payment summary HTML
    const summaryContent = `
        <div class="text-center mb-4">
            <div class="success-icon-wrapper">
                <i class="fas fa-check-circle fa-5x text-success"></i>
            </div>
            <h3 class="mt-3 mb-2">Payment Successful!</h3>
            <p class="text-muted">Transaction has been recorded</p>
        </div>

        <div class="payment-summary-card">
            <h6 class="summary-section-title">
                <i class="fas fa-receipt"></i> Transaction Details
            </h6>
            <div class="summary-row">
                <span class="summary-label">OR Number:</span>
                <span class="summary-value or-number">${receiptNumber}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Ticket Number:</span>
                <span class="summary-value">${citation.ticket_number}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Driver:</span>
                <span class="summary-value">${citation.driver_name}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Payment Method:</span>
                <span class="summary-value text-capitalize">${paymentMethod.replace('_', ' ')}</span>
            </div>
            ${referenceNumber ? `
            <div class="summary-row">
                <span class="summary-label">Reference Number:</span>
                <span class="summary-value">${referenceNumber}</span>
            </div>
            ` : ''}
        </div>

        <div class="payment-summary-card mt-3">
            <h6 class="summary-section-title">
                <i class="fas fa-money-bill-wave"></i> Payment Breakdown
            </h6>
            <div class="summary-row">
                <span class="summary-label">Amount Due:</span>
                <span class="summary-value">₱${amountPaid.toFixed(2)}</span>
            </div>
            ${paymentMethod === 'cash' ? `
            <div class="summary-row">
                <span class="summary-label">Cash Received:</span>
                <span class="summary-value">₱${cashReceived.toFixed(2)}</span>
            </div>
            <div class="summary-row highlight-change">
                <span class="summary-label"><strong>Change:</strong></span>
                <span class="summary-value"><strong>₱${changeAmount}</strong></span>
            </div>
            ` : `
            <div class="summary-row">
                <span class="summary-label">Amount Paid:</span>
                <span class="summary-value text-success"><strong>₱${amountPaid.toFixed(2)}</strong></span>
            </div>
            `}
        </div>

        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle"></i>
            <strong>Next Step:</strong> Click "Print Receipt" below to print the official receipt for the driver.
        </div>
    `;

    // Update modal content
    document.getElementById('paymentSummaryContent').innerHTML = summaryContent;

    // Show modal
    const summaryModal = new bootstrap.Modal(document.getElementById('printPreviewModal'));
    summaryModal.show();
}

/**
 * Print receipt directly and confirm
 */
function printReceiptFromPreview() {
    const paymentId = window.currentPaymentId;
    const receiptNumber = window.currentReceiptNumber;

    // Close summary modal
    const previewModal = bootstrap.Modal.getInstance(document.getElementById('printPreviewModal'));
    previewModal.hide();

    // Open receipt in new window and trigger print
    const receiptUrl = window.location.origin + buildPublicUrl('public/receipt.php?receipt=' + encodeURIComponent(receiptNumber));
    const printWindow = window.open(receiptUrl, '_blank');

    // Wait for window to load, then print
    if (printWindow) {
        printWindow.onload = function() {
            printWindow.print();
        };
    }

    // Show print confirmation dialog after a brief moment
    setTimeout(() => {
        showPrintConfirmation(paymentId, receiptNumber);
    }, 1000);
}

/**
 * Show print confirmation dialog using SweetAlert2
 */
function showPrintConfirmation(paymentId, receiptNumber) {
    Swal.fire({
        title: 'Receipt Print Confirmation',
        text: 'Did the receipt print successfully?',
        icon: 'question',
        showDenyButton: true,
        showCancelButton: false,
        confirmButtonText: '<i class="fas fa-check"></i> Yes - Print OK',
        denyButtonText: '<i class="fas fa-times"></i> No - Printer Problem',
        confirmButtonColor: '#059669',
        denyButtonColor: '#dc2626',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Print successful - finalize payment
            finalizePayment(paymentId);
        } else if (result.isDenied) {
            // Printer problem - show reprint options
            showReprintOptions(paymentId, receiptNumber);
        }
    });
}

/**
 * Finalize payment after print confirmation
 */
function finalizePayment(paymentId) {
    // Check CSRF token exists
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
    if (!csrfToken) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Security token missing. Please reload the page and try again.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Show loading
    Swal.fire({
        title: 'Finalizing Payment...',
        text: 'Please wait',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send finalize request
    const formData = new FormData();
    formData.append('payment_id', paymentId);
    formData.append('csrf_token', csrfToken);

    fetch(buildApiUrl('api/payments/finalize_payment.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Payment Completed!',
                text: 'Citation status updated to PAID',
                confirmButtonColor: '#059669'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#dc2626'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error finalizing payment: ' + error.message,
            confirmButtonColor: '#dc2626'
        });
    });
}

/**
 * Show reprint options modal
 */
function showReprintOptions(paymentId, receiptNumber) {
    // Store values in hidden fields
    document.getElementById('reprint_payment_id').value = paymentId;
    document.getElementById('reprint_current_or').value = receiptNumber;
    document.getElementById('display_current_or').textContent = receiptNumber;

    // Reset new OR input section
    document.getElementById('newOrInputSection').style.display = 'none';
    document.getElementById('new_or_input').value = '';

    // Show modal
    const reprintModal = new bootstrap.Modal(document.getElementById('reprintOptionsModal'));
    reprintModal.show();
}

/**
 * Reprint receipt with same OR number
 */
function reprintReceipt() {
    const receiptNumber = document.getElementById('reprint_current_or').value;
    const paymentId = document.getElementById('reprint_payment_id').value;

    // Close reprint modal
    bootstrap.Modal.getInstance(document.getElementById('reprintOptionsModal')).hide();

    // Show receipt in preview modal instead of new tab
    showReceiptPreview(receiptNumber, paymentId);
}

/**
 * Show new OR input section
 */
function showNewOrInput() {
    document.getElementById('newOrInputSection').style.display = 'block';
    document.getElementById('new_or_input').focus();
}

/**
 * Confirm new OR number
 */
function confirmNewOr() {
    const paymentId = document.getElementById('reprint_payment_id').value;
    const newOrNumber = document.getElementById('new_or_input').value.trim().toUpperCase();

    if (!newOrNumber) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Please enter a new OR number',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Validate OR number format (CGVM + 8 digits)
    if (!OR_NUMBER_PATTERN.test(newOrNumber)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Format',
            text: 'Invalid OR number format! Expected: CGVM followed by exactly 8 digits (e.g., CGVM15320501)',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Check CSRF token exists
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
    if (!csrfToken) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Security token missing. Please reload the page and try again.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Show loading
    Swal.fire({
        title: 'Updating OR Number...',
        text: 'Please wait',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send update request
    const formData = new FormData();
    formData.append('payment_id', paymentId);
    formData.append('new_or_number', newOrNumber);
    formData.append('reason', 'Printer jam - using different receipt');
    formData.append('csrf_token', csrfToken);

    fetch(buildApiUrl('api/payments/update_or_number.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close reprint modal
            bootstrap.Modal.getInstance(document.getElementById('reprintOptionsModal')).hide();

            // Show receipt in preview modal instead of new tab
            showReceiptPreview(data.new_or, paymentId);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#dc2626'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error updating OR number: ' + error.message,
            confirmButtonColor: '#dc2626'
        });
    });
}

/**
 * Void payment confirmation
 */
function voidPaymentConfirm() {
    Swal.fire({
        title: 'Void Payment?',
        text: 'This will cancel the payment transaction. This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, void payment',
        cancelButtonText: 'No, go back',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            voidPayment();
        }
    });
}

/**
 * Void payment
 */
function voidPayment() {
    const paymentId = document.getElementById('reprint_payment_id').value;

    // Check CSRF token exists
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
    if (!csrfToken) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Security token missing. Please reload the page and try again.',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    // Show loading
    Swal.fire({
        title: 'Voiding Payment...',
        text: 'Please wait',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Send void request
    const formData = new FormData();
    formData.append('payment_id', paymentId);
    formData.append('reason', 'Payment cancelled by cashier - printer issue');
    formData.append('csrf_token', csrfToken);

    fetch(buildApiUrl('api/payments/void_payment.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close reprint modal
            bootstrap.Modal.getInstance(document.getElementById('reprintOptionsModal')).hide();

            Swal.fire({
                icon: 'success',
                title: 'Payment Voided',
                text: 'The payment has been cancelled. Citation remains pending.',
                confirmButtonColor: '#059669'
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#dc2626'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error voiding payment: ' + error.message,
            confirmButtonColor: '#dc2626'
        });
    });
}

/**
 * Validate OR number format in real-time
 */
function validateOrNumberFormat() {
    const input = document.getElementById('receipt_number');
    const value = input.value.trim().toUpperCase();

    // Auto-convert to uppercase
    if (input.value !== value) {
        input.value = value;
    }

    // Remove any existing feedback
    removeOrValidationFeedback();

    if (value.length === 0) {
        return; // Don't show validation for empty input
    }

    // Create feedback element
    const feedbackDiv = document.createElement('div');
    feedbackDiv.id = 'or-validation-feedback';
    feedbackDiv.style.marginTop = '0.5rem';
    feedbackDiv.style.fontSize = '0.875rem';

    if (OR_NUMBER_PATTERN.test(value)) {
        // Valid format
        feedbackDiv.className = 'text-success';
        feedbackDiv.innerHTML = '<i class="fas fa-check-circle"></i> Valid OR format (CGVM + 8 digits)';
        input.style.borderColor = '#198754';
        input.style.backgroundColor = '#f0fdf4';
    } else {
        // Invalid format
        feedbackDiv.className = 'text-danger';
        feedbackDiv.innerHTML = '<i class="fas fa-times-circle"></i> Invalid format. Expected: CGVM followed by exactly 8 digits (e.g., CGVM15320501)';
        input.style.borderColor = '#dc3545';
        input.style.backgroundColor = '#fef2f2';
    }

    // Insert feedback after input
    input.parentNode.appendChild(feedbackDiv);
}

/**
 * Remove OR validation feedback
 */
function removeOrValidationFeedback() {
    const existing = document.getElementById('or-validation-feedback');
    if (existing) {
        existing.remove();
    }
    const input = document.getElementById('receipt_number');
    input.style.borderColor = '';
    input.style.backgroundColor = '';
}

/**
 * Mark form as dirty (has unsaved changes)
 */
function markFormAsDirty() {
    formIsDirty = true;
}

/**
 * Prevent data loss when closing modal
 */
function preventDataLoss(e) {
    // Check if form has data
    const receiptNumber = document.getElementById('receipt_number').value.trim();
    const cashReceived = document.getElementById('cash_received').value.trim();
    const referenceNumber = document.getElementById('reference_number').value.trim();
    const notes = document.getElementById('notes').value.trim();

    const hasData = receiptNumber || cashReceived || referenceNumber || notes;

    if (formIsDirty && hasData) {
        e.preventDefault();
        Swal.fire({
            title: 'Discard Changes?',
            text: 'You have entered data. Are you sure you want to close and discard it?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Discard',
            cancelButtonText: 'Keep Editing',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280'
        }).then((result) => {
            if (result.isConfirmed) {
                formIsDirty = false;
                paymentModal.hide();
            }
        });
        return false;
    }

    // Reset dirty state when closing
    formIsDirty = false;
}


/**
 * Utility functions
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function showAlert(message, type) {
    // Remove any existing alert of the same type first
    const existingAlert = document.querySelector(`.alert-${type}`);
    if (existingAlert) {
        existingAlert.remove();
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;

    // Add icon based on type
    let icon = '';
    if (type === 'success') icon = '<i class="fas fa-check-circle"></i> ';
    if (type === 'danger') icon = '<i class="fas fa-exclamation-circle"></i> ';
    if (type === 'warning') icon = '<i class="fas fa-exclamation-triangle"></i> ';
    if (type === 'info') icon = '<i class="fas fa-info-circle"></i> ';

    alertDiv.innerHTML = `
        ${icon}${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);

    // Only auto-dismiss success and info alerts (NOT errors/warnings)
    if (type === 'success' || type === 'info') {
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    // Danger and warning alerts stay until manually dismissed

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
