/**
 * Duplicate Detection JavaScript
 * Real-time duplicate driver detection for citation form
 */

(function() {
    let duplicateCheckTimeout = null;
    let selectedDuplicateDriver = null;

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
        const fieldsToMonitor = [
            'license_number',
            'plate_mv_engine_chassis_no',
            'last_name',
            'first_name',
            'date_of_birth',
            'barangay'
        ];

        let fieldsFound = 0;
        fieldsToMonitor.forEach(fieldName => {
            // Try to find by ID first, then by name attribute
            let field = document.getElementById(fieldName);
            if (!field) {
                field = document.querySelector(`[name="${fieldName}"]`);
            }

            if (field) {
                fieldsFound++;
                field.addEventListener('blur', () => {
                    clearTimeout(duplicateCheckTimeout);
                    duplicateCheckTimeout = setTimeout(checkForDuplicates, 500);
                });
            }
        });

        // Also check when violations are selected
        const violationCheckboxes = document.querySelectorAll('.violation-checkbox');
        violationCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateOffenseCountsForViolations);
        });
    }

    /**
     * Check for duplicate drivers
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
     * Show duplicate warning modal
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

            // Update offense counts for selected violations
            updateOffenseCountsForViolations();

            // Show success message
            showToast('Driver record loaded successfully', 'success');
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
     * Update offense counts for selected violations
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
        updateOffenseCountsForViolations
    };
})();
