/**
 * Admin Payment Adjustments - Frontend Logic
 *
 * Handles citation lookup, form validation, and adjustment submission
 *
 * @package TrafficCitationSystem
 * @subpackage AdminJS
 */

// Global variables
let currentCitation = null;
let passwordModal = null;

// Adjustment type descriptions
const adjustmentDescriptions = {
    'external_payment': 'Payment was made at LTO office, bank, or other authorized location',
    'manual_correction': 'Payment was processed but status did not update correctly in the system',
    'waived': 'Citation fine waived or dismissed by authorized official',
    'backlog_entry': 'Retroactive entry for payment with lost or missing paperwork',
    'court_settlement': 'Payment through court order or legal settlement'
};

// Initialize on document ready
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializePasswordModal();
});

/**
 * Initialize all event listeners
 */
function initializeEventListeners() {
    // Search button click
    document.getElementById('searchBtn').addEventListener('click', searchCitation);

    // Enter key on search input
    document.getElementById('ticketNumberInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchCitation();
        }
    });

    // Adjustment type change
    document.getElementById('adjustment_type').addEventListener('change', handleAdjustmentTypeChange);

    // Reason character count
    document.getElementById('reason').addEventListener('input', updateReasonCount);

    // Form submission
    document.getElementById('paymentAdjustmentForm').addEventListener('submit', handleFormSubmit);

    // Cancel button
    document.getElementById('cancelBtn').addEventListener('click', resetForm);

    // Password confirmation
    document.getElementById('confirmPasswordBtn').addEventListener('click', submitAdjustment);

    // Enter key on password input
    document.getElementById('admin_password').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('confirmPasswordBtn').click();
        }
    });
}

/**
 * Initialize password modal
 */
function initializePasswordModal() {
    passwordModal = new bootstrap.Modal(document.getElementById('passwordModal'));
}

/**
 * Search for citation by ticket number
 */
async function searchCitation() {
    const ticketNumber = document.getElementById('ticketNumberInput').value.trim();

    if (!ticketNumber) {
        Swal.fire({
            icon: 'warning',
            title: 'Ticket Number Required',
            text: 'Please enter a ticket number to search'
        });
        return;
    }

    // Show loading
    Swal.fire({
        title: 'Searching...',
        text: 'Looking up citation',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch(`${APP_CONFIG.BASE_PATH}/api/lookup_citation_for_adjustment.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ticket_number: ticketNumber
            })
        });

        const data = await response.json();

        if (data.success && data.eligible) {
            currentCitation = data.citation;
            displayCitationDetails(data.citation);
            Swal.close();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Citation Not Eligible',
                html: data.message + (data.citation ? '<br><br>Current Status: <strong>' + data.citation.status + '</strong>' : '')
            });
            resetForm();
        }
    } catch (error) {
        console.error('Search error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Search Failed',
            text: 'An error occurred while searching for the citation'
        });
    }
}

/**
 * Display citation details and show adjustment form
 */
function displayCitationDetails(citation) {
    // Populate citation details
    document.getElementById('detail_ticket_number').textContent = citation.ticket_number;
    document.getElementById('detail_driver_name').textContent = citation.driver_name;
    document.getElementById('detail_license_number').textContent = citation.license_number;
    document.getElementById('detail_plate_number').textContent = citation.plate_number || 'N/A';
    document.getElementById('detail_vehicle').textContent = citation.vehicle_description || 'N/A';
    document.getElementById('detail_date').textContent = citation.apprehension_datetime;
    document.getElementById('detail_location').textContent = citation.apprehension_location || 'N/A';
    document.getElementById('detail_violations').textContent = citation.violations || 'N/A';
    document.getElementById('detail_amount').textContent = citation.total_fine_formatted;

    // Set form values
    document.getElementById('citation_id').value = citation.citation_id;
    document.getElementById('total_fine').value = citation.total_fine;
    document.getElementById('amount').value = citation.total_fine;

    // Show citation details and form
    document.getElementById('citationDetails').classList.add('show');
    document.getElementById('adjustmentForm').classList.add('show');

    // Scroll to form
    document.getElementById('citationDetails').scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Re-initialize icons
    if (typeof reinitLucideIcons === 'function') {
        reinitLucideIcons();
    }
}

/**
 * Handle adjustment type change
 */
function handleAdjustmentTypeChange(e) {
    const select = e.target;
    const selectedOption = select.options[select.selectedIndex];

    if (!selectedOption.value) {
        return;
    }

    const requiresOr = selectedOption.dataset.requiresOr === '1';
    const allowsEdit = selectedOption.dataset.allowsEdit === '1';
    const requiresPassword = selectedOption.dataset.requiresPassword === '1';

    // Update description
    const description = adjustmentDescriptions[selectedOption.value] || '';
    document.getElementById('adjustment_type_description').textContent = description;

    // Handle OR number field
    const orNumberField = document.getElementById('or_number');
    const orRequired = document.getElementById('or_required');

    if (requiresOr) {
        orNumberField.required = true;
        orRequired.textContent = '*';
        orNumberField.parentElement.style.display = 'block';
    } else {
        orNumberField.required = false;
        orRequired.textContent = '';
        orNumberField.value = '';
        // Still show the field but make it optional
    }

    // Handle amount field
    const amountField = document.getElementById('amount');
    const amountHelp = document.getElementById('amount_help');

    if (selectedOption.value === 'waived') {
        // Waived fines are always 0
        amountField.value = '0.00';
        amountField.readOnly = true;
        amountHelp.textContent = 'Waived fines are set to ₱0.00';
    } else if (allowsEdit) {
        // Court settlements can be edited
        amountField.readOnly = false;
        amountHelp.textContent = 'You can modify the amount for settlements';
    } else {
        // Other types use the full fine amount
        amountField.value = document.getElementById('total_fine').value;
        amountField.readOnly = true;
        amountHelp.textContent = 'Auto-filled from citation total fine';
    }

    // Store password requirement for later
    select.dataset.currentRequiresPassword = requiresPassword ? '1' : '0';
}

/**
 * Update reason character count
 */
function updateReasonCount(e) {
    const count = e.target.value.length;
    const counter = document.getElementById('reason_count');
    counter.textContent = count;

    if (count >= 20) {
        counter.classList.remove('text-danger');
        counter.classList.add('text-success');
    } else {
        counter.classList.remove('text-success');
        counter.classList.add('text-danger');
    }
}

/**
 * Handle form submission
 */
function handleFormSubmit(e) {
    e.preventDefault();

    // Validate form
    if (!validateForm()) {
        return;
    }

    // Check if password is required
    const adjustmentType = document.getElementById('adjustment_type');
    const requiresPassword = adjustmentType.dataset.currentRequiresPassword === '1';

    if (requiresPassword) {
        // Show password modal
        document.getElementById('admin_password').value = '';
        passwordModal.show();
    } else {
        // Submit directly
        submitAdjustment();
    }
}

/**
 * Validate form before submission
 */
function validateForm() {
    const adjustmentType = document.getElementById('adjustment_type').value;
    const orNumber = document.getElementById('or_number').value.trim();
    const paymentDate = document.getElementById('payment_date').value;
    const amount = parseFloat(document.getElementById('amount').value);
    const reason = document.getElementById('reason').value.trim();

    if (!adjustmentType) {
        Swal.fire({
            icon: 'warning',
            title: 'Adjustment Type Required',
            text: 'Please select an adjustment type'
        });
        return false;
    }

    const selectedOption = document.querySelector(`#adjustment_type option[value="${adjustmentType}"]`);
    const requiresOr = selectedOption.dataset.requiresOr === '1';

    if (requiresOr && !orNumber) {
        Swal.fire({
            icon: 'warning',
            title: 'OR Number Required',
            text: 'This adjustment type requires an OR number'
        });
        return false;
    }

    if (!paymentDate) {
        Swal.fire({
            icon: 'warning',
            title: 'Payment Date Required',
            text: 'Please select a payment date'
        });
        return false;
    }

    // Check date is not in future
    if (new Date(paymentDate) > new Date()) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Date',
            text: 'Payment date cannot be in the future'
        });
        return false;
    }

    if (amount < 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Amount',
            text: 'Amount cannot be negative'
        });
        return false;
    }

    if (reason.length < 20) {
        Swal.fire({
            icon: 'warning',
            title: 'Reason Too Short',
            text: 'Please provide a detailed reason (minimum 20 characters)'
        });
        return false;
    }

    return true;
}

/**
 * Submit adjustment to server
 */
async function submitAdjustment() {
    // Get form data
    const formData = {
        citation_id: document.getElementById('citation_id').value,
        adjustment_type: document.getElementById('adjustment_type').value,
        or_number: document.getElementById('or_number').value.trim() || null,
        payment_date: document.getElementById('payment_date').value,
        amount: parseFloat(document.getElementById('amount').value) || 0,
        reason: document.getElementById('reason').value.trim(),
        admin_password: document.getElementById('admin_password').value || null,
        csrf_token: document.querySelector('input[name="csrf_token"]').value
    };

    // Hide password modal if open
    if (passwordModal) {
        passwordModal.hide();
    }

    // Show loading
    Swal.fire({
        title: 'Processing...',
        text: 'Creating payment adjustment',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await fetch(`${APP_CONFIG.BASE_PATH}/api/process_payment_adjustment.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Adjustment Created',
                html: `
                    <p>Payment adjustment has been successfully created.</p>
                    <div class="text-start mt-3">
                        <strong>Details:</strong><br>
                        Ticket: ${currentCitation.ticket_number}<br>
                        Type: ${formData.adjustment_type.replace(/_/g, ' ')}<br>
                        Amount: ₱${parseFloat(formData.amount).toFixed(2)}<br>
                        ${formData.or_number ? 'OR Number: ' + formData.or_number + '<br>' : ''}
                        New Status: ${data.data.new_status}
                    </div>
                `,
                confirmButtonText: 'OK'
            }).then(() => {
                // Reload page to show in recent adjustments
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Adjustment Failed',
                text: data.message
            });
        }
    } catch (error) {
        console.error('Submit error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Submission Failed',
            text: 'An error occurred while creating the adjustment'
        });
    }
}

/**
 * Reset form and hide details
 */
function resetForm() {
    // Reset form
    document.getElementById('paymentAdjustmentForm').reset();

    // Reset form values
    document.getElementById('citation_id').value = '';
    document.getElementById('total_fine').value = '';
    document.getElementById('adjustment_type_description').textContent = '';
    document.getElementById('reason_count').textContent = '0';

    // Hide citation details and form
    document.getElementById('citationDetails').classList.remove('show');
    document.getElementById('adjustmentForm').classList.remove('show');

    // Clear search input
    document.getElementById('ticketNumberInput').value = '';

    // Clear current citation
    currentCitation = null;

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
