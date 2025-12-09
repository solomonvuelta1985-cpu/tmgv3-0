/**
 * Duplicate Detection JavaScript
 * Real-time duplicate driver detection for citation form
 */

(function() {
    let selectedDuplicateDriver = null;
    let currentViolationContext = null; // Store which violation triggered the modal

    document.addEventListener('DOMContentLoaded', function() {
        // Add modal HTML to page if not exists
        if (!document.getElementById('duplicateWarningModal')) {
            addDuplicateModal();
        }

        // Setup duplicate detection listeners
        setupDuplicateDetection();
    });

    /**
     * Add duplicate warning modal to page
     */
    function addDuplicateModal() {
        const modalHTML = `
        <!-- Duplicate Warning Modal -->
        <div class="modal fade" id="duplicateWarningModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Possible Duplicate Driver Found
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <strong>Warning!</strong> We found existing records that may match this driver.
                            Please review and select the appropriate option below.
                        </div>

                        <div id="duplicateMatchesList"></div>

                        <div class="mt-3 p-3 bg-light border rounded">
                            <h6 class="mb-3">What would you like to do?</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="duplicateAction" id="useExisting" value="existing">
                                <label class="form-check-label" for="useExisting">
                                    <strong>Use existing driver record</strong> (recommended if same person)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="duplicateAction" id="createNew" value="new" checked>
                                <label class="form-check-label" for="createNew">
                                    <strong>Create new record</strong> (different person with similar information)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmDuplicateAction">Continue</button>
                    </div>
                </div>
            </div>
        </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Setup confirm button
        document.getElementById('confirmDuplicateAction').addEventListener('click', handleDuplicateAction);
    }

    /**
     * Setup duplicate detection on form fields
     */
    function setupDuplicateDetection() {
        let checkedViolationsSet = new Set();

        // Helper to check if we have enough data to check for duplicates
        function hasDriverInfo() {
            const licenseNumber = getValue('license_number');
            const plateNumber = getValue('plate_mv_engine_chassis_no');
            const firstName = getValue('first_name');
            const lastName = getValue('last_name');

            // Need at least one of these combinations to identify driver:
            // 1. License number OR Plate number
            // 2. First name AND Last name
            return (licenseNumber || plateNumber) || (firstName && lastName);
        }

        // NEW: Check for repeat offense when violation is selected
        const violationCheckboxes = document.querySelectorAll('.violation-checkbox');

        if (violationCheckboxes.length === 0) {
            // Try again after a delay if violations haven't loaded yet
            setTimeout(setupDuplicateDetection, 1000);
            return;
        }

        violationCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                // Only check when checkbox is CHECKED (not unchecked)
                if (!this.checked) {
                    checkedViolationsSet.delete(this.value);
                    return;
                }

                // Check if we have driver info
                const driverInfoAvailable = hasDriverInfo();

                if (!driverInfoAvailable) {
                    return; // Can't check without driver info
                }

                const violationTypeId = this.value;

                // Prevent duplicate checks for the same violation
                if (checkedViolationsSet.has(violationTypeId)) {
                    return;
                }

                checkedViolationsSet.add(violationTypeId);

                // Check if this specific violation is a repeat offense
                checkForRepeatOffense(violationTypeId, checkbox);
            });
        });

        // Reset when form is cleared
        const clearBtn = document.getElementById('clearFormBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                checkedViolationsSet.clear();
            });
        }

        // Also listen for form reset
        const form = document.getElementById('citationForm');
        if (form) {
            form.addEventListener('reset', () => {
                checkedViolationsSet.clear();
            });
        }
    }

    /**
     * Check if this violation is a repeat offense for this driver
     */
    function checkForRepeatOffense(violationTypeId, checkbox) {
        const driverInfo = {
            license_number: getValue('license_number'),
            plate_number: getValue('plate_mv_engine_chassis_no'),
            first_name: getValue('first_name'),
            last_name: getValue('last_name'),
            date_of_birth: getValue('date_of_birth'),
            barangay: getValue('barangay')
        };

        // Store violation context
        currentViolationContext = {
            violationTypeId: violationTypeId,
            checkbox: checkbox
        };

        // Show loading indicator
        showLoadingIndicator();

        const apiUrl = buildApiUrl('api/check_duplicates.php');

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(driverInfo)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            hideLoadingIndicator();

            if (data.success && data.match_count > 0) {
                // Filter matches that have this specific violation in their history
                const matchesWithThisViolation = data.matches.filter(match => {
                    if (!match.offense_history) {
                        return false;
                    }

                    return match.offense_history.some(offense =>
                        offense.violation_type_id == violationTypeId
                    );
                });

                if (matchesWithThisViolation.length > 0) {
                    // Show targeted modal for repeat offense
                    showRepeatOffenseWarning(matchesWithThisViolation, violationTypeId, checkbox);
                }
            }
        })
        .catch(error => {
            console.error('Repeat offense check error:', error);
            hideLoadingIndicator();
            showToast('Error checking for repeat offense: ' + error.message, 'warning');
        });
    }

    /**
     * Show repeat offense warning with targeted information
     */
    function showRepeatOffenseWarning(matches, violationTypeId, checkbox) {
        const violationLabel = document.querySelector(`label[for="${checkbox.id}"]`);
        const violationName = violationLabel ? violationLabel.textContent.split(' - ')[0].trim() : 'this violation';

        const matchesList = document.getElementById('duplicateMatchesList');
        matchesList.innerHTML = '';

        // Add a special header for repeat offense warning
        const warningHeader = `
        <div class="alert alert-danger mb-3">
            <h6 class="mb-2">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Repeat Offense Detected!</strong>
            </h6>
            <p class="mb-0">
                This driver has previously been cited for <strong>"${violationName}"</strong>.
                Please verify this is the same person before proceeding.
            </p>
        </div>
        `;
        matchesList.insertAdjacentHTML('beforeend', warningHeader);

        // Show matching drivers with this specific violation highlighted
        matches.forEach((match, index) => {
            const matchHTML = createMatchCardWithViolationHighlight(match, index, violationTypeId);
            matchesList.insertAdjacentHTML('beforeend', matchHTML);
        });

        // Reset selection
        selectedDuplicateDriver = null;
        document.getElementById('createNew').checked = true;

        // Setup match selection
        matches.forEach((match, index) => {
            const selectBtn = document.getElementById(`selectMatch${index}`);
            if (selectBtn) {
                selectBtn.addEventListener('click', () => {
                    selectedDuplicateDriver = match;
                    document.getElementById('useExisting').checked = true;

                    // Highlight selected card
                    document.querySelectorAll('.duplicate-match-card').forEach(card => {
                        card.classList.remove('border-primary', 'bg-light');
                    });
                    document.getElementById(`matchCard${index}`).classList.add('border-primary', 'bg-light');
                });
            }
        });

        // Show modal
        const modalElement = document.getElementById('duplicateWarningModal');
        const modal = new bootstrap.Modal(modalElement);

        // Update modal title for repeat offense
        const modalTitle = modalElement.querySelector('.modal-title');
        modalTitle.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            Repeat Offense Detected - Action Required
        `;

        // Update the warning alert in modal body
        const modalAlert = modalElement.querySelector('.modal-body .alert');
        if (modalAlert) {
            modalAlert.className = 'alert alert-danger';
            modalAlert.innerHTML = `
                <strong>Repeat Offense Alert!</strong>
                A matching driver has been found who was previously cited for <strong>"${violationName}"</strong>.
                Please verify this is the same person and select the appropriate action below.
            `;
        }

        // Add event listener to clean up backdrop when modal is hidden
        modalElement.addEventListener('hidden.bs.modal', function cleanupBackdrop() {
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 100);
            modalElement.removeEventListener('hidden.bs.modal', cleanupBackdrop);
        });

        modal.show();
    }

    /**
     * Create match card with violation highlight
     */
    function createMatchCardWithViolationHighlight(match, index, violationTypeId) {
        const confidenceBadge = getConfidenceBadge(match.confidence);
        const fullName = `${match.last_name}, ${match.first_name}`.trim();

        // Find the specific violation in history
        const targetViolationHistory = match.offense_history.filter(
            offense => offense.violation_type_id == violationTypeId
        );

        let violationHistoryHTML = '';
        if (targetViolationHistory.length > 0) {
            violationHistoryHTML = `
            <div class="mt-3 p-3 bg-danger bg-opacity-10 border border-danger rounded">
                <strong class="text-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    Previous Citations for This Violation:
                </strong>
                <ul class="mt-2 mb-0">
            `;
            targetViolationHistory.forEach(record => {
                violationHistoryHTML += `
                    <li class="text-danger fw-bold">
                        ${record.violation_type} (${getOffenseLabel(record.offense_count)}) -
                        ${formatDate(record.apprehension_datetime)} -
                        ₱${parseFloat(record.fine_amount).toLocaleString()}
                    </li>
                `;
            });
            violationHistoryHTML += '</ul></div>';
        }

        // Other violations (non-targeted)
        const otherViolations = match.offense_history.filter(
            offense => offense.violation_type_id != violationTypeId
        );

        let otherViolationsHTML = '';
        if (otherViolations.length > 0) {
            otherViolationsHTML = `
            <div class="mt-3">
                <strong>Other Recent Violations:</strong>
                <ul class="mt-2 mb-0">
            `;
            otherViolations.slice(0, 3).forEach(record => {
                otherViolationsHTML += `
                    <li>${record.violation_type} (${getOffenseLabel(record.offense_count)}) -
                    ${formatDate(record.apprehension_datetime)} -
                    ₱${parseFloat(record.fine_amount).toLocaleString()}</li>
                `;
            });
            otherViolationsHTML += '</ul></div>';
        }

        return `
        <div class="card duplicate-match-card mb-3" id="matchCard${index}">
            <div class="card-header d-flex justify-content-between align-items-center bg-warning bg-opacity-25">
                <div>
                    <strong>${fullName}</strong>
                    ${confidenceBadge}
                    <span class="badge bg-danger ms-2">REPEAT OFFENDER</span>
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="selectMatch${index}">
                    <i class="fas fa-check"></i> Select This Driver
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Match Reason:</strong> ${match.reason}</p>
                        <p class="mb-1"><strong>License #:</strong> ${match.license_number || 'N/A'}</p>
                        <p class="mb-1"><strong>DOB:</strong> ${match.date_of_birth || 'N/A'}</p>
                        <p class="mb-1"><strong>Barangay:</strong> ${match.barangay || 'N/A'}</p>
                        <p class="mb-1"><strong>Plate #:</strong> ${match.plate_mv_engine_chassis_no || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Total Citations:</strong> ${match.total_citations}</p>
                        <p class="mb-1"><strong>Total Offenses:</strong> ${match.total_offenses || 0}</p>
                        <p class="mb-1"><strong>Last Citation:</strong> ${formatDate(match.last_citation_date)}</p>
                        <p class="mb-1 text-danger fw-bold">
                            <strong>This Violation Count:</strong> ${targetViolationHistory.length}x
                        </p>
                    </div>
                </div>
                ${violationHistoryHTML}
                ${otherViolationsHTML}
            </div>
        </div>
        `;
    }

    /**
     * Check for duplicate drivers (Legacy function - kept for compatibility)
     */
    function checkForDuplicates() {
        const driverInfo = {
            license_number: getValue('license_number'),
            plate_number: getValue('plate_mv_engine_chassis_no'),
            first_name: getValue('first_name'),
            last_name: getValue('last_name'),
            date_of_birth: getValue('date_of_birth'),
            barangay: getValue('barangay')
        };

        // Need at least some information to check
        if (!driverInfo.license_number && !driverInfo.plate_number &&
            (!driverInfo.first_name || !driverInfo.last_name)) {
            return;
        }

        // Show loading indicator (optional)
        showLoadingIndicator();

        fetch(buildApiUrl('api/check_duplicates.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(driverInfo)
        })
        .then(response => response.json())
        .then(data => {
            hideLoadingIndicator();

            if (data.success && data.match_count > 0) {
                showDuplicateWarning(data.matches);
            }
        })
        .catch(error => {
            console.error('Duplicate check error:', error);
            hideLoadingIndicator();
        });
    }

    /**
     * Show duplicate warning modal (Legacy - general duplicate detection)
     */
    function showDuplicateWarning(matches) {
        const matchesList = document.getElementById('duplicateMatchesList');
        matchesList.innerHTML = '';

        matches.forEach((match, index) => {
            const matchHTML = createMatchCard(match, index);
            matchesList.insertAdjacentHTML('beforeend', matchHTML);
        });

        // Reset selection
        selectedDuplicateDriver = null;
        document.getElementById('createNew').checked = true;

        // Setup match selection
        matches.forEach((match, index) => {
            const selectBtn = document.getElementById(`selectMatch${index}`);
            if (selectBtn) {
                selectBtn.addEventListener('click', () => {
                    selectedDuplicateDriver = match;
                    document.getElementById('useExisting').checked = true;

                    // Highlight selected card
                    document.querySelectorAll('.duplicate-match-card').forEach(card => {
                        card.classList.remove('border-primary', 'bg-light');
                    });
                    document.getElementById(`matchCard${index}`).classList.add('border-primary', 'bg-light');
                });
            }
        });

        // Show modal
        const modalElement = document.getElementById('duplicateWarningModal');
        const modal = new bootstrap.Modal(modalElement);

        // Reset modal title to default
        const modalTitle = modalElement.querySelector('.modal-title');
        modalTitle.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            Possible Duplicate Driver Found
        `;

        // Reset alert to default
        const modalAlert = modalElement.querySelector('.modal-body .alert');
        if (modalAlert) {
            modalAlert.className = 'alert alert-warning';
            modalAlert.innerHTML = `
                <strong>Warning!</strong> We found existing records that may match this driver.
                Please review and select the appropriate option below.
            `;
        }

        // Add event listener to clean up backdrop when modal is hidden (safety mechanism)
        modalElement.addEventListener('hidden.bs.modal', function cleanupBackdrop() {
            // Remove any remaining modal backdrops
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());

                // Ensure body is properly reset
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 100);

            // Remove this listener after it runs once
            modalElement.removeEventListener('hidden.bs.modal', cleanupBackdrop);
        });

        modal.show();
    }

    /**
     * Create match card HTML
     */
    function createMatchCard(match, index) {
        const confidenceBadge = getConfidenceBadge(match.confidence);
        const fullName = `${match.last_name}, ${match.first_name}`.trim();

        return `
        <div class="card duplicate-match-card mb-3" id="matchCard${index}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>${fullName}</strong>
                    ${confidenceBadge}
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="selectMatch${index}">
                    <i class="fas fa-check"></i> Select This Driver
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Match Reason:</strong> ${match.reason}</p>
                        <p class="mb-1"><strong>License #:</strong> ${match.license_number || 'N/A'}</p>
                        <p class="mb-1"><strong>DOB:</strong> ${match.date_of_birth || 'N/A'}</p>
                        <p class="mb-1"><strong>Barangay:</strong> ${match.barangay || 'N/A'}</p>
                        <p class="mb-1"><strong>Plate #:</strong> ${match.plate_mv_engine_chassis_no || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Total Citations:</strong> ${match.total_citations}</p>
                        <p class="mb-1"><strong>Total Offenses:</strong> ${match.total_offenses || 0}</p>
                        <p class="mb-1"><strong>Last Citation:</strong> ${formatDate(match.last_citation_date)}</p>
                        ${match.total_vehicle_offenses ? `<p class="mb-1"><strong>Vehicle Offenses:</strong> ${match.total_vehicle_offenses}</p>` : ''}
                    </div>
                </div>
                ${match.offense_history && match.offense_history.length > 0 ? createOffenseHistoryHTML(match.offense_history) : ''}
            </div>
        </div>
        `;
    }

    /**
     * Create offense history HTML
     */
    function createOffenseHistoryHTML(history) {
        if (history.length === 0) return '';

        let html = '<div class="mt-3"><strong>Recent Violations:</strong><ul class="mt-2">';
        history.slice(0, 5).forEach(record => {
            html += `<li>${record.violation_type} (${getOffenseLabel(record.offense_count)}) - ${formatDate(record.apprehension_datetime)} - ₱${parseFloat(record.fine_amount).toLocaleString()}</li>`;
        });
        html += '</ul></div>';
        return html;
    }

    /**
     * Handle duplicate action confirmation
     */
    function handleDuplicateAction() {
        const action = document.querySelector('input[name="duplicateAction"]:checked').value;

        if (action === 'existing' && selectedDuplicateDriver) {
            // Pre-fill form with selected driver's data
            prefillDriverData(selectedDuplicateDriver);

            // Update ALL violation labels with correct offense counts
            updateAllViolationLabels(selectedDuplicateDriver.driver_id);

            // Show success message
            showToast('Driver record loaded successfully. Violation offense counts updated.', 'success');
        } else if (action === 'existing' && !selectedDuplicateDriver) {
            alert('Please select a driver record from the list above.');
            return;
        }

        // Close modal properly and remove backdrop
        const modalElement = document.getElementById('duplicateWarningModal');
        const modalInstance = bootstrap.Modal.getInstance(modalElement);
        if (modalInstance) {
            modalInstance.hide();
        }

        // Ensure backdrop is removed (fix for black screen issue)
        setTimeout(() => {
            // Remove any remaining modal backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());

            // Remove modal-open class from body and restore scrolling
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }, 300); // Wait for modal hide animation to complete
    }

    /**
     * Pre-fill form with driver data
     */
    function prefillDriverData(driver) {
        // Store driver ID in a hidden field or data attribute
        let driverIdField = document.getElementById('selected_driver_id');
        if (!driverIdField) {
            driverIdField = document.createElement('input');
            driverIdField.type = 'hidden';
            driverIdField.id = 'selected_driver_id';
            driverIdField.name = 'selected_driver_id';
            document.querySelector('form').appendChild(driverIdField);
        }
        driverIdField.value = driver.driver_id;

        // Pre-fill fields
        setValue('license_number', driver.license_number);
        setValue('first_name', driver.first_name);
        setValue('last_name', driver.last_name);
        setValue('date_of_birth', driver.date_of_birth);
        setValue('barangay', driver.barangay);
        setValue('plate_mv_engine_chassis_no', driver.plate_mv_engine_chassis_no);

        // Trigger DOB change to calculate age
        const dobField = document.getElementById('date_of_birth');
        if (dobField) {
            dobField.dispatchEvent(new Event('change'));
        }
    }

    /**
     * Update ALL violation labels with correct offense counts for a driver
     */
    function updateAllViolationLabels(driverId) {
        const licenseNumber = getValue('license_number');
        const plateNumber = getValue('plate_mv_engine_chassis_no');

        if (!driverId && !licenseNumber && !plateNumber) {
            return; // No way to track history
        }

        // Show loading indicator
        showToast('Updating violation offense counts...', 'info');

        const params = new URLSearchParams({
            driver_id: driverId || '',
            license_number: licenseNumber || '',
            plate_number: plateNumber || ''
        });

        fetch(buildApiUrl(`api/get_all_offense_counts.php?${params}`))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.violations) {
                    let updatedCount = 0;

                    // Update each violation checkbox label
                    Object.keys(data.violations).forEach(violationId => {
                        const violationData = data.violations[violationId];
                        const checkbox = document.querySelector(`input[name="violations[]"][value="${violationId}"]`);

                        if (checkbox) {
                            const label = document.querySelector(`label[for="${checkbox.id}"]`);
                            const violationItem = checkbox.closest('.violation-item');

                            if (label) {
                                const textSpan = label.querySelector('.checkbox-text');
                                if (textSpan) {
                                    // Update label with new offense count and fine
                                    textSpan.textContent = violationData.label;
                                    updatedCount++;

                                    // Highlight if 2nd or 3rd offense
                                    if (violationData.offense_count > 1) {
                                        // Add visual indicator for repeat offenses
                                        textSpan.style.fontWeight = 'bold';
                                        textSpan.style.color = violationData.offense_count === 3 ? '#dc3545' : '#fd7e14';

                                        // Add CSS class to violation item for styling
                                        if (violationItem) {
                                            violationItem.classList.remove('repeat-offense', 'repeat-offense-severe');
                                            if (violationData.offense_count === 3) {
                                                violationItem.classList.add('repeat-offense-severe');
                                            } else {
                                                violationItem.classList.add('repeat-offense');
                                            }
                                        }

                                        // Add a badge/indicator
                                        let badge = textSpan.querySelector('.offense-badge');
                                        if (!badge) {
                                            badge = document.createElement('span');
                                            badge.className = 'offense-badge badge bg-warning text-dark ms-2';
                                            badge.style.fontSize = '0.75rem';
                                            textSpan.appendChild(badge);
                                        }
                                        badge.textContent = violationData.offense_count === 3 ? 'REPEAT OFFENDER' : 'REPEAT';
                                        badge.className = violationData.offense_count === 3
                                            ? 'offense-badge badge bg-danger text-white ms-2'
                                            : 'offense-badge badge bg-warning text-dark ms-2';
                                    } else {
                                        // Remove any existing styling for first offense
                                        textSpan.style.fontWeight = '';
                                        textSpan.style.color = '';
                                        const existingBadge = textSpan.querySelector('.offense-badge');
                                        if (existingBadge) {
                                            existingBadge.remove();
                                        }
                                        if (violationItem) {
                                            violationItem.classList.remove('repeat-offense', 'repeat-offense-severe');
                                        }
                                    }

                                    // Store offense count in data attribute for form submission
                                    checkbox.setAttribute('data-offense', violationData.offense_count);
                                }
                            }
                        }
                    });

                    if (updatedCount > 0) {
                        showToast(`Updated ${updatedCount} violation labels with offense history`, 'success');
                    }
                }
            })
            .catch(error => {
                console.error('Error updating violation labels:', error);
                showToast('Could not update offense counts', 'warning');
            });
    }

    /**
     * Update offense counts for selected violations (Legacy - kept for compatibility)
     */
    function updateOffenseCountsForViolations() {
        const driverId = getValue('selected_driver_id');
        const licenseNumber = getValue('license_number');
        const plateNumber = getValue('plate_mv_engine_chassis_no');

        if (!driverId && !licenseNumber && !plateNumber) {
            return; // No way to track history
        }

        // Get all checked violations
        document.querySelectorAll('.violation-checkbox:checked').forEach(checkbox => {
            const violationTypeId = checkbox.value;
            updateOffenseCount(violationTypeId, driverId, licenseNumber, plateNumber);
        });
    }

    /**
     * Update offense count for a specific violation
     */
    function updateOffenseCount(violationTypeId, driverId, licenseNumber, plateNumber) {
        const params = new URLSearchParams({
            violation_type_id: violationTypeId,
            driver_id: driverId || '',
            license_number: licenseNumber || '',
            plate_number: plateNumber || ''
        });

        fetch(buildApiUrl(`api/get_offense_count.php?${params}`))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the offense count radio button
                    const offenseRadio = document.getElementById(`offense_${violationTypeId}_${data.offense_count}`);
                    if (offenseRadio) {
                        offenseRadio.checked = true;

                        // Show notification if 2nd or 3rd offense
                        if (data.offense_count > 1) {
                            const violationLabel = document.querySelector(`label[for="violation_${violationTypeId}"]`);
                            const violationName = violationLabel ? violationLabel.textContent.trim() : 'this violation';
                            showToast(`${getOffenseLabel(data.offense_count)} detected for ${violationName}`, 'warning');
                        }
                    }
                }
            })
            .catch(error => console.error('Offense count error:', error));
    }

    /**
     * Helper functions
     */
    function getValue(name) {
        // Try ID first, then name attribute
        let element = document.getElementById(name);
        if (!element) {
            element = document.querySelector(`[name="${name}"]`);
        }
        return element ? element.value.trim() : '';
    }

    function setValue(name, value) {
        // Try ID first, then name attribute
        let element = document.getElementById(name);
        if (!element) {
            element = document.querySelector(`[name="${name}"]`);
        }
        if (element) {
            element.value = value || '';
        }
    }

    function getConfidenceBadge(confidence) {
        let badgeClass = 'bg-secondary';
        if (confidence >= 80) badgeClass = 'bg-danger';
        else if (confidence >= 60) badgeClass = 'bg-warning';
        else if (confidence >= 40) badgeClass = 'bg-info';

        return `<span class="badge ${badgeClass}">${confidence}% match</span>`;
    }

    function getOffenseLabel(count) {
        const labels = ['', '1st Offense', '2nd Offense', '3rd Offense'];
        return labels[count] || '3rd Offense';
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function showLoadingIndicator() {
        // Optional: show a loading spinner
        const indicator = document.getElementById('duplicateCheckIndicator');
        if (indicator) {
            indicator.style.display = 'block';
        }
    }

    function hideLoadingIndicator() {
        const indicator = document.getElementById('duplicateCheckIndicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    function showToast(message, type = 'info') {
        // Create toast notification
        const toastHTML = `
        <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info')} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        `;

        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
        toast.show();

        // Remove after hiding
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    }

    // Make functions globally available if needed
    window.duplicateDetection = {
        checkForDuplicates,
        checkForRepeatOffense,
        updateOffenseCountsForViolations,
        updateAllViolationLabels
    };
})();
