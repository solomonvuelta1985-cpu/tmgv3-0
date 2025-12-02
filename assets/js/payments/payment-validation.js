/**
 * Advanced Payment Validation Module
 *
 * Provides comprehensive validation for payment processing to prevent
 * data inconsistencies and invalid payment states.
 *
 * Usage: Include this file after process_payment.js
 */

// ============================================================================
// Configuration
// ============================================================================

const VALIDATION_CONFIG = {
    // Maximum age for pending_print payments (in hours) before warning
    MAX_PENDING_PRINT_AGE: 24,

    // Minimum amount validation
    MIN_PAYMENT_AMOUNT: 10.00,

    // Maximum amount validation (to prevent typos)
    MAX_PAYMENT_AMOUNT: 50000.00,

    // OR number validation
    OR_NUMBER_PATTERNS: {
        CGVM: /^CGVM[0-9]{8}$/,  // CGVM + 8 digits
        Generic: /^[A-Z]{2,4}[0-9]{6,10}$/  // 2-4 letters + 6-10 digits
    },

    // Validation API endpoints
    // NOTE: These will be resolved using buildApiUrl() at runtime
    API_ENDPOINTS: {
        get checkCitationStatus() { return buildApiUrl('api/check_citation_status.php'); },
        get checkOrDuplicate() { return buildApiUrl('api/payments/check_or_duplicate.php'); },
        get checkPendingPrint() { return buildApiUrl('api/payments/check_pending_print.php'); }
    }
};

// ============================================================================
// Citation Status Validation
// ============================================================================

/**
 * Validate citation status before allowing payment
 * Prevents payments on void, dismissed, or already paid citations
 */
async function validateCitationStatus(citationId) {
    try {
        const response = await fetch(
            `${VALIDATION_CONFIG.API_ENDPOINTS.checkCitationStatus}?citation_id=${citationId}`
        );

        if (!response.ok) {
            throw new Error('Failed to check citation status');
        }

        const data = await response.json();

        // Check if citation is in valid state for payment
        if (!data.success) {
            return {
                valid: false,
                message: data.message || 'Citation not found'
            };
        }

        const citation = data.citation;

        // VALIDATION RULE 1: Citation must not be void or dismissed
        if (['void', 'dismissed'].includes(citation.status)) {
            return {
                valid: false,
                message: `Cannot process payment: This citation is ${citation.status.toUpperCase()}. ${
                    citation.status === 'void' ? 'Void citations cannot accept payments.' :
                    'Dismissed citations do not require payment.'
                }`,
                severity: 'error'
            };
        }

        // VALIDATION RULE 2: Warn if citation is already marked as paid
        if (citation.status === 'paid') {
            // Check if it has actual completed payments
            if (data.has_completed_payments) {
                return {
                    valid: false,
                    message: `This citation is already PAID (OR: ${data.receipt_numbers}). Do you want to issue a refund or void the payment instead?`,
                    severity: 'error',
                    suggestion: 'refund_or_void'
                };
            } else {
                // Data inconsistency: marked as paid but no completed payments
                return {
                    valid: true,
                    message: `⚠️ Warning: Citation is marked as PAID but has no completed payment records. This may be a data inconsistency. Proceeding with payment will fix this.`,
                    severity: 'warning',
                    auto_fix: true
                };
            }
        }

        // VALIDATION RULE 3: Warn if citation is contested
        if (citation.status === 'contested') {
            return {
                valid: true,
                message: `⚠️ Notice: This citation is currently CONTESTED. Accepting payment will change status to PAID.`,
                severity: 'info',
                requires_confirmation: true
            };
        }

        // All validations passed
        return {
            valid: true,
            message: 'Citation is valid for payment processing',
            citation: citation
        };

    } catch (error) {
        console.error('Citation status validation error:', error);
        return {
            valid: false,
            message: 'Unable to validate citation status. Please try again or contact support.',
            severity: 'error'
        };
    }
}

// ============================================================================
// OR Number Validation
// ============================================================================

/**
 * Validate OR number format
 */
function validateOrNumberFormat(orNumber) {
    if (!orNumber || orNumber.trim() === '') {
        return {
            valid: false,
            message: 'OR number is required'
        };
    }

    const trimmed = orNumber.trim().toUpperCase();

    // Check against configured patterns
    const patterns = VALIDATION_CONFIG.OR_NUMBER_PATTERNS;

    // Try CGVM pattern first (most common)
    if (patterns.CGVM.test(trimmed)) {
        return {
            valid: true,
            message: 'Valid CGVM format'
        };
    }

    // Try generic pattern
    if (patterns.Generic.test(trimmed)) {
        return {
            valid: true,
            message: 'Valid OR format',
            warning: 'Non-standard format detected. Please verify OR number is correct.'
        };
    }

    return {
        valid: false,
        message: 'Invalid OR number format. Expected format: CGVM########'
    };
}

/**
 * Check for duplicate OR number
 */
async function checkOrDuplicate(orNumber) {
    try {
        const response = await fetch(
            `${VALIDATION_CONFIG.API_ENDPOINTS.checkOrDuplicate}?or_number=${encodeURIComponent(orNumber)}`
        );

        if (!response.ok) {
            throw new Error('Failed to check OR duplicate');
        }

        const data = await response.json();

        if (data.is_duplicate) {
            return {
                valid: false,
                message: `⛔ DUPLICATE OR NUMBER DETECTED!<br><br>
                         OR Number <strong>${orNumber}</strong> was already used for:<br>
                         <strong>Ticket:</strong> ${data.existing.ticket_number}<br>
                         <strong>Driver:</strong> ${data.existing.driver_name}<br>
                         <strong>Date:</strong> ${new Date(data.existing.payment_date).toLocaleString()}<br>
                         <strong>Amount:</strong> ₱${parseFloat(data.existing.amount_paid).toFixed(2)}<br><br>
                         Please use a different OR number.`,
                severity: 'error',
                existing_payment: data.existing
            };
        }

        return {
            valid: true,
            message: 'OR number is available'
        };

    } catch (error) {
        console.error('OR duplicate check error:', error);
        return {
            valid: true,  // Allow on error (fail open)
            message: '⚠️ Could not verify OR number uniqueness. Proceed with caution.',
            severity: 'warning'
        };
    }
}

/**
 * Comprehensive OR number validation (format + duplicate check)
 */
async function validateOrNumber(orNumber) {
    // Step 1: Format validation
    const formatValidation = validateOrNumberFormat(orNumber);

    if (!formatValidation.valid) {
        return formatValidation;
    }

    // Step 2: Duplicate check
    const duplicateCheck = await checkOrDuplicate(orNumber);

    if (!duplicateCheck.valid) {
        return duplicateCheck;
    }

    // Combine results
    return {
        valid: true,
        message: formatValidation.warning || 'OR number is valid and available',
        warning: formatValidation.warning
    };
}

// ============================================================================
// Payment Amount Validation
// ============================================================================

/**
 * Validate payment amount
 */
function validatePaymentAmount(amount, expectedAmount) {
    const numAmount = parseFloat(amount);
    const numExpected = parseFloat(expectedAmount);

    // Check if valid number
    if (isNaN(numAmount) || numAmount <= 0) {
        return {
            valid: false,
            message: 'Payment amount must be a positive number'
        };
    }

    // Check minimum amount
    if (numAmount < VALIDATION_CONFIG.MIN_PAYMENT_AMOUNT) {
        return {
            valid: false,
            message: `Payment amount is too small. Minimum: ₱${VALIDATION_CONFIG.MIN_PAYMENT_AMOUNT.toFixed(2)}`
        };
    }

    // Check maximum amount (prevent typos)
    if (numAmount > VALIDATION_CONFIG.MAX_PAYMENT_AMOUNT) {
        return {
            valid: false,
            message: `Payment amount is unusually high (₱${numAmount.toFixed(2)}). Please verify this is correct. Maximum allowed: ₱${VALIDATION_CONFIG.MAX_PAYMENT_AMOUNT.toFixed(2)}`
        };
    }

    // Check if amount matches citation fine
    if (Math.abs(numAmount - numExpected) > 0.01) {
        return {
            valid: true,
            message: `⚠️ Warning: Payment amount (₱${numAmount.toFixed(2)}) does not match citation fine (₱${numExpected.toFixed(2)})`,
            severity: 'warning',
            requires_confirmation: true,
            difference: numAmount - numExpected
        };
    }

    return {
        valid: true,
        message: 'Payment amount is valid'
    };
}

/**
 * Validate cash and change calculation
 */
function validateCashChange(amountPaid, cashReceived) {
    const numPaid = parseFloat(amountPaid);
    const numReceived = parseFloat(cashReceived);

    if (isNaN(numReceived) || numReceived <= 0) {
        return {
            valid: false,
            message: 'Cash received must be a positive number'
        };
    }

    if (numReceived < numPaid) {
        return {
            valid: false,
            message: `Insufficient cash received. Need ₱${numPaid.toFixed(2)}, received ₱${numReceived.toFixed(2)}. Short by ₱${(numPaid - numReceived).toFixed(2)}`
        };
    }

    const change = numReceived - numPaid;

    return {
        valid: true,
        message: 'Cash amount is valid',
        change: change
    };
}

// ============================================================================
// Pre-submission Validation
// ============================================================================

/**
 * Perform all validations before submitting payment
 */
async function validatePaymentSubmission(formData) {
    const validationResults = [];

    // Extract form data
    const citationId = formData.get('citation_id');
    const orNumber = formData.get('receipt_number');
    const paymentMethod = formData.get('payment_method');
    const amountPaid = formData.get('amount_paid');
    const cashReceived = formData.get('cash_received');

    // Validation 1: Citation Status
    const citationValidation = await validateCitationStatus(citationId);
    validationResults.push({
        name: 'Citation Status',
        ...citationValidation
    });

    if (!citationValidation.valid && citationValidation.severity === 'error') {
        return {
            valid: false,
            results: validationResults
        };
    }

    // Validation 2: OR Number
    const orValidation = await validateOrNumber(orNumber);
    validationResults.push({
        name: 'OR Number',
        ...orValidation
    });

    if (!orValidation.valid) {
        return {
            valid: false,
            results: validationResults
        };
    }

    // Validation 3: Payment Amount
    const expectedAmount = citationValidation.citation?.total_fine || amountPaid;
    const amountValidation = validatePaymentAmount(amountPaid, expectedAmount);
    validationResults.push({
        name: 'Payment Amount',
        ...amountValidation
    });

    if (!amountValidation.valid) {
        return {
            valid: false,
            results: validationResults
        };
    }

    // Validation 4: Cash/Change (if cash payment)
    if (paymentMethod === 'cash' && cashReceived) {
        const cashValidation = validateCashChange(amountPaid, cashReceived);
        validationResults.push({
            name: 'Cash & Change',
            ...cashValidation
        });

        if (!cashValidation.valid) {
            return {
                valid: false,
                results: validationResults
            };
        }
    }

    // Check if any validations require user confirmation
    const requiresConfirmation = validationResults.some(r => r.requires_confirmation);

    return {
        valid: true,
        results: validationResults,
        requires_confirmation: requiresConfirmation
    };
}

// ============================================================================
// UI Helper Functions
// ============================================================================

/**
 * Display validation results to user
 */
function displayValidationResults(results) {
    const errors = results.filter(r => !r.valid);
    const warnings = results.filter(r => r.valid && r.severity === 'warning');
    const confirmations = results.filter(r => r.requires_confirmation);

    if (errors.length > 0) {
        // Show errors
        const errorMessages = errors.map(e => `<strong>${e.name}:</strong> ${e.message}`).join('<br><br>');

        Swal.fire({
            icon: 'error',
            title: 'Validation Failed',
            html: errorMessages,
            confirmButtonColor: '#dc2626'
        });
        return false;
    }

    if (confirmations.length > 0) {
        // Show confirmation dialog
        const confirmMessages = confirmations.map(c => `• ${c.message}`).join('<br>');

        return Swal.fire({
            icon: 'warning',
            title: 'Confirmation Required',
            html: `<div class="text-start">${confirmMessages}</div><br><strong>Do you want to proceed?</strong>`,
            showCancelButton: true,
            confirmButtonText: 'Yes, Proceed',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6b7280'
        });
    }

    if (warnings.length > 0) {
        // Show warnings as toast
        warnings.forEach(w => {
            showToast(w.message, 'warning');
        });
    }

    return true;
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    Toast.fire({
        icon: type,
        title: message
    });
}

// ============================================================================
// Export functions for use in other files
// ============================================================================

window.PaymentValidation = {
    validateCitationStatus,
    validateOrNumber,
    validateOrNumberFormat,
    validatePaymentAmount,
    validateCashChange,
    validatePaymentSubmission,
    displayValidationResults,
    showToast,
    VALIDATION_CONFIG
};

console.log('Payment Validation Module loaded successfully');
