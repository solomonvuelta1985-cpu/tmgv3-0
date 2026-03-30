/**
 * Duplicate Detection JavaScript
 * Real-time duplicate driver detection for citation form
 */

(function() {
    let selectedDuplicateDriver = null;
    let currentViolationContext = null; // Store which violation triggered the modal
    let formFieldsModified = false; // Track if driver fields were modified after selection
    let originalDriverData = null; // Store original driver data for comparison

    document.addEventListener('DOMContentLoaded', function() {
        // Add modal HTML to page if not exists
        if (!document.getElementById('duplicateWarningModal')) {
            addDuplicateModal();
        }

        // Setup duplicate detection listeners
        setupDuplicateDetection();

        // Setup form submission validation for data changes
        setupFormSubmissionValidation();
    });

    /**
     * Add duplicate warning modal to page
     */
    function addDuplicateModal() {
        const modalHTML = `
        <!-- Duplicate Warning Modal -->
        <div class="modal fade" id="duplicateWarningModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                    <div class="modal-header border-0 py-3 px-4" id="duplicateModalHeader" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
                        <div class="d-flex align-items-center">
                            <div class="me-3" id="duplicateModalIcon" style="width: 42px; height: 42px; background: rgba(251,191,36,0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="alert-triangle" style="color: #fbbf24; width: 20px; height: 20px;"></i>
                            </div>
                            <div>
                                <h5 class="modal-title mb-0 text-white fw-semibold" style="font-size: 1.1rem;">Possible Duplicate Driver Found</h5>
                                <small class="text-white-50" id="duplicateModalSubtitle">Review matching records below</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4" style="background: #f8fafc;">
                        <div class="alert border-0 mb-4 d-flex align-items-start" id="duplicateModalAlert" style="background: #fef3c7; border-left: 4px solid #f59e0b !important; border-radius: 8px;">
                            <i data-lucide="info" class="mt-1 me-3 flex-shrink-0" style="color: #d97706; width: 20px; height: 20px;"></i>
                            <div>
                                <strong style="color: #92400e;">Attention Required</strong>
                                <p class="mb-0 mt-1" style="color: #78350f; font-size: 0.9rem;">We found existing records that may match this driver. Please review and select the appropriate action.</p>
                            </div>
                        </div>

                        <div id="duplicateMatchesList"></div>

                        <div class="mt-4 p-3 border rounded-3" style="background: white; border-color: #e2e8f0 !important;">
                            <h6 class="mb-3 fw-semibold" style="color: #334155; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                <i data-lucide="pointer" class="me-2" style="color: #64748b; width: 16px; height: 16px;"></i>Select Action
                            </h6>
                            <div class="d-flex gap-3">
                                <label class="flex-fill p-3 border rounded-3 cursor-pointer duplicate-action-option" for="useExisting" style="cursor: pointer; transition: all 0.2s; border-color: #e2e8f0 !important;">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="radio" name="duplicateAction" id="useExisting" value="existing">
                                        <span class="form-check-label d-block">
                                            <strong style="color: #1e293b; font-size: 0.9rem;">Use Existing Record</strong>
                                            <small class="d-block mt-1" style="color: #64748b;">Link to an existing driver profile</small>
                                        </span>
                                    </div>
                                </label>
                                <label class="flex-fill p-3 border rounded-3 cursor-pointer duplicate-action-option" for="createNew" style="cursor: pointer; transition: all 0.2s; border-color: #e2e8f0 !important;">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="radio" name="duplicateAction" id="createNew" value="new" checked>
                                        <span class="form-check-label d-block">
                                            <strong style="color: #1e293b; font-size: 0.9rem;">Create New Record</strong>
                                            <small class="d-block mt-1" style="color: #64748b;">Different person with similar info</small>
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 py-3" style="background: white;">
                        <button type="button" class="btn px-4 py-2" data-bs-dismiss="modal" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 500;">Cancel</button>
                        <button type="button" class="btn px-4 py-2 text-white" id="confirmDuplicateAction" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none; border-radius: 8px; font-weight: 500;">
                            <i data-lucide="arrow-right" class="me-1" style="width: 16px; height: 16px;"></i> Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <style>
            #duplicateWarningModal .duplicate-action-option:hover {
                border-color: #3b82f6 !important;
                background: #f0f7ff;
            }
            #duplicateWarningModal .duplicate-action-option:has(input:checked) {
                border-color: #3b82f6 !important;
                background: #eff6ff;
                box-shadow: 0 0 0 1px #3b82f6;
            }
            #duplicateWarningModal .duplicate-match-card {
                transition: all 0.2s ease;
                border: 1px solid #e2e8f0 !important;
            }
            #duplicateWarningModal .duplicate-match-card:hover {
                border-color: #cbd5e1 !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            }
            #duplicateWarningModal .duplicate-match-card.border-primary {
                border-color: #3b82f6 !important;
                box-shadow: 0 0 0 1px #3b82f6, 0 4px 12px rgba(59,130,246,0.12);
            }
            #duplicateWarningModal .duplicate-match-card.border-primary .card-header {
                background: #eff6ff !important;
            }
            #duplicateWarningModal .match-stat-item {
                padding: 8px 12px;
                background: #f8fafc;
                border-radius: 8px;
                border: 1px solid #f1f5f9;
            }
            #duplicateWarningModal .match-detail-label {
                font-size: 0.75rem;
                color: #94a3b8;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                font-weight: 600;
                margin-bottom: 2px;
            }
            #duplicateWarningModal .match-detail-value {
                font-size: 0.875rem;
                color: #1e293b;
                font-weight: 500;
            }
            #duplicateWarningModal .violation-history-section {
                background: #fef2f2;
                border: 1px solid #fecaca;
                border-radius: 10px;
                padding: 16px;
            }
            #duplicateWarningModal .violation-history-item {
                padding: 8px 12px;
                background: white;
                border-radius: 6px;
                border: 1px solid #fecaca;
                margin-bottom: 6px;
                font-size: 0.85rem;
            }
            #duplicateWarningModal .other-violations-section {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 16px;
            }
            #duplicateWarningModal .confidence-bar {
                height: 4px;
                border-radius: 2px;
                background: #e2e8f0;
                overflow: hidden;
            }
            #duplicateWarningModal .confidence-bar-fill {
                height: 100%;
                border-radius: 2px;
                transition: width 0.5s ease;
            }
        </style>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Initialize Lucide icons in the modal
        if (typeof lucide !== 'undefined') lucide.createIcons();

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
            barangay: getValue('barangay'),
            middle_initial: getValue('middle_initial'),
            municipality: getValue('municipality'),
            vehicle_type: document.querySelector('input[name="vehicle_type"]:checked')?.value || ''
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
                    // Skip low-confidence fuzzy matches
                    if (match.confidence < 50) {
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

        // Summary bar showing match count
        const summaryBar = `
        <div class="d-flex align-items-center justify-content-between mb-3 px-1">
            <div>
                <span class="fw-semibold" style="color: #475569; font-size: 0.85rem;">
                    <i data-lucide="users" class="me-1" style="color: #64748b; width: 15px; height: 15px;"></i>
                    ${matches.length} matching record${matches.length > 1 ? 's' : ''} found
                </span>
            </div>
            <span class="badge px-3 py-2" style="background: #fee2e2; color: #991b1b; font-weight: 600; border-radius: 6px; font-size: 0.75rem;">
                <i data-lucide="repeat" class="me-1" style="width: 13px; height: 13px;"></i> REPEAT OFFENSE
            </span>
        </div>
        `;
        matchesList.insertAdjacentHTML('beforeend', summaryBar);

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

        // Update modal header for repeat offense
        const modalHeader = document.getElementById('duplicateModalHeader');
        modalHeader.style.background = 'linear-gradient(135deg, #7f1d1d 0%, #991b1b 50%, #b91c1c 100%)';

        const modalIcon = document.getElementById('duplicateModalIcon');
        modalIcon.style.background = 'rgba(254,202,202,0.2)';
        modalIcon.innerHTML = '<i data-lucide="shield-alert" style="color: #fca5a5; width: 20px; height: 20px;"></i>';

        const modalTitle = modalElement.querySelector('.modal-title');
        modalTitle.textContent = 'Repeat Offense Detected';

        const modalSubtitle = document.getElementById('duplicateModalSubtitle');
        modalSubtitle.textContent = 'Previous citation record found for this violation';

        // Update alert for repeat offense
        const modalAlert = document.getElementById('duplicateModalAlert');
        if (modalAlert) {
            modalAlert.style.background = '#fef2f2';
            modalAlert.style.borderLeft = '4px solid #ef4444';
            modalAlert.innerHTML = `
                <i data-lucide="alert-triangle" class="mt-1 me-3 flex-shrink-0" style="color: #dc2626; width: 20px; height: 20px;"></i>
                <div>
                    <strong style="color: #991b1b;">Repeat Offense Alert</strong>
                    <p class="mb-0 mt-1" style="color: #7f1d1d; font-size: 0.9rem;">
                        A matching driver has been previously cited for <strong>"${violationName}"</strong>.
                        Please verify this is the same person and select the appropriate action.
                    </p>
                </div>
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

        // Initialize Lucide icons for dynamically added content
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    /**
     * Create match card with violation highlight
     */
    function createMatchCardWithViolationHighlight(match, index, violationTypeId) {
        const fullName = `${match.last_name}, ${match.first_name}`.trim();
        const confidence = match.confidence;

        // Confidence bar color
        let confidenceColor = '#94a3b8';
        let confidenceLabel = 'Low';
        if (confidence >= 80) { confidenceColor = '#dc2626'; confidenceLabel = 'Very High'; }
        else if (confidence >= 60) { confidenceColor = '#f59e0b'; confidenceLabel = 'High'; }
        else if (confidence >= 50) { confidenceColor = '#3b82f6'; confidenceLabel = 'Moderate'; }

        // Find the specific violation in history
        const targetViolationHistory = match.offense_history.filter(
            offense => offense.violation_type_id == violationTypeId
        );

        let violationHistoryHTML = '';
        if (targetViolationHistory.length > 0) {
            let itemsHTML = '';
            targetViolationHistory.forEach(record => {
                itemsHTML += `
                    <div class="violation-history-item d-flex justify-content-between align-items-center">
                        <div>
                            <i data-lucide="file-text" class="me-2 flex-shrink-0" style="color: #dc2626; width: 14px; height: 14px;"></i>
                            <strong style="color: #991b1b;">${record.violation_type}</strong>
                            <span class="ms-1" style="color: #b91c1c;">(${getOffenseLabel(record.offense_count)})</span>
                        </div>
                        <div class="text-end">
                            <span style="color: #6b7280; font-size: 0.8rem;">${formatDate(record.apprehension_datetime)}</span>
                            <span class="ms-2 fw-bold" style="color: #991b1b;">&#8369;${parseFloat(record.fine_amount).toLocaleString()}</span>
                        </div>
                    </div>
                `;
            });
            violationHistoryHTML = `
            <div class="violation-history-section mt-3">
                <div class="d-flex align-items-center mb-2">
                    <i data-lucide="clock" class="me-2 flex-shrink-0" style="color: #dc2626; width: 15px; height: 15px;"></i>
                    <strong style="color: #991b1b; font-size: 0.85rem;">Previous Citations for This Violation</strong>
                </div>
                ${itemsHTML}
            </div>`;
        }

        // Other violations (non-targeted)
        const otherViolations = match.offense_history.filter(
            offense => offense.violation_type_id != violationTypeId
        );

        let otherViolationsHTML = '';
        if (otherViolations.length > 0) {
            let otherItemsHTML = '';
            otherViolations.slice(0, 3).forEach(record => {
                otherItemsHTML += `
                    <div class="d-flex justify-content-between align-items-center py-1" style="font-size: 0.85rem;">
                        <span style="color: #475569;">
                            ${record.violation_type} (${getOffenseLabel(record.offense_count)})
                        </span>
                        <span style="color: #94a3b8;">${formatDate(record.apprehension_datetime)} &mdash; &#8369;${parseFloat(record.fine_amount).toLocaleString()}</span>
                    </div>
                `;
            });
            otherViolationsHTML = `
            <div class="other-violations-section mt-3">
                <div class="d-flex align-items-center mb-2">
                    <i data-lucide="list" class="me-2 flex-shrink-0" style="color: #64748b; width: 15px; height: 15px;"></i>
                    <strong style="color: #475569; font-size: 0.85rem;">Other Recent Violations</strong>
                </div>
                ${otherItemsHTML}
            </div>`;
        }

        return `
        <div class="card duplicate-match-card mb-3" id="matchCard${index}" style="border-radius: 10px; overflow: hidden; background: white;">
            <div class="card-header d-flex justify-content-between align-items-center px-4 py-3" style="background: #fafafa; border-bottom: 1px solid #f1f5f9;">
                <div class="d-flex align-items-center">
                    <div class="me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #1e293b, #475569); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="user" style="color: white; width: 18px; height: 18px;"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <strong style="color: #1e293b; font-size: 1rem;">${fullName}</strong>
                            ${targetViolationHistory.length > 0 ? `<span class="badge px-2 py-1" style="background: #fef2f2; color: #dc2626; font-size: 0.7rem; font-weight: 600; border: 1px solid #fecaca; border-radius: 4px;">REPEAT OFFENDER</span>` : ''}
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span style="color: #64748b; font-size: 0.8rem;">${match.reason || 'Name match'}</span>
                            <span style="color: #cbd5e1;">|</span>
                            <div class="d-flex align-items-center gap-1">
                                <div class="confidence-bar" style="width: 50px;">
                                    <div class="confidence-bar-fill" style="width: ${confidence}%; background: ${confidenceColor};"></div>
                                </div>
                                <span style="color: ${confidenceColor}; font-size: 0.75rem; font-weight: 600;">${confidence}% ${confidenceLabel}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm px-3 py-2" id="selectMatch${index}" style="background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 8px; font-weight: 500; font-size: 0.85rem;">
                    <i data-lucide="check" class="me-1" style="width: 14px; height: 14px;"></i> Select
                </button>
            </div>
            <div class="card-body px-4 py-3">
                <div class="row g-3">
                    <div class="col-md-7">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="match-stat-item">
                                    <div class="match-detail-label">License No.</div>
                                    <div class="match-detail-value">${match.license_number || '<span style="color:#cbd5e1;">N/A</span>'}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="match-stat-item">
                                    <div class="match-detail-label">Plate / MV No.</div>
                                    <div class="match-detail-value">${match.plate_mv_engine_chassis_no || '<span style="color:#cbd5e1;">N/A</span>'}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="match-stat-item">
                                    <div class="match-detail-label">Date of Birth</div>
                                    <div class="match-detail-value">${match.date_of_birth ? formatDate(match.date_of_birth) : '<span style="color:#cbd5e1;">N/A</span>'}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="match-stat-item">
                                    <div class="match-detail-label">Barangay</div>
                                    <div class="match-detail-value">${match.barangay || '<span style="color:#cbd5e1;">N/A</span>'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="match-stat-item text-center">
                                    <div class="match-detail-label">Citations</div>
                                    <div class="match-detail-value" style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">${match.total_citations}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="match-stat-item text-center">
                                    <div class="match-detail-label">Offenses</div>
                                    <div class="match-detail-value" style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">${match.total_offenses || 0}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="match-stat-item text-center">
                                    <div class="match-detail-label">Last Citation</div>
                                    <div class="match-detail-value" style="font-size: 0.8rem;">${formatDate(match.last_citation_date)}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="match-stat-item text-center" style="background: #fef2f2; border-color: #fecaca !important;">
                                    <div class="match-detail-label" style="color: #dc2626;">This Violation</div>
                                    <div class="match-detail-value" style="font-size: 1.25rem; font-weight: 700; color: #dc2626;">${targetViolationHistory.length}x</div>
                                </div>
                            </div>
                        </div>
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
            barangay: getValue('barangay'),
            middle_initial: getValue('middle_initial'),
            municipality: getValue('municipality'),
            vehicle_type: document.querySelector('input[name="vehicle_type"]:checked')?.value || ''
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

        // Reset modal header to default style
        const modalHeader = document.getElementById('duplicateModalHeader');
        modalHeader.style.background = 'linear-gradient(135deg, #1e293b 0%, #334155 100%)';

        const modalIcon = document.getElementById('duplicateModalIcon');
        modalIcon.style.background = 'rgba(251,191,36,0.15)';
        modalIcon.innerHTML = '<i data-lucide="alert-triangle" style="color: #fbbf24; width: 20px; height: 20px;"></i>';
        if (typeof lucide !== 'undefined') lucide.createIcons();

        const modalTitle = modalElement.querySelector('.modal-title');
        modalTitle.textContent = 'Possible Duplicate Driver Found';

        const modalSubtitle = document.getElementById('duplicateModalSubtitle');
        modalSubtitle.textContent = 'Review matching records below';

        // Reset alert to default
        const modalAlert = document.getElementById('duplicateModalAlert');
        if (modalAlert) {
            modalAlert.style.background = '#fef3c7';
            modalAlert.style.borderLeft = '4px solid #f59e0b';
            modalAlert.innerHTML = `
                <i data-lucide="info" class="mt-1 me-3 flex-shrink-0" style="color: #d97706; width: 20px; height: 20px;"></i>
                <div>
                    <strong style="color: #92400e;">Attention Required</strong>
                    <p class="mb-0 mt-1" style="color: #78350f; font-size: 0.9rem;">We found existing records that may match this driver. Please review and select the appropriate action.</p>
                </div>
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

        // Initialize Lucide icons for dynamically added content
        if (typeof lucide !== 'undefined') lucide.createIcons();
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
                    <i data-lucide="check" style="width: 14px; height: 14px;"></i> Select This Driver
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

        // Store original data for comparison
        originalDriverData = {
            license_number: driver.license_number || '',
            first_name: driver.first_name || '',
            last_name: driver.last_name || '',
            date_of_birth: driver.date_of_birth || '',
            barangay: driver.barangay || '',
            plate_mv_engine_chassis_no: driver.plate_mv_engine_chassis_no || ''
        };
        formFieldsModified = false;

        // Add change listeners to track modifications
        const driverFields = ['license_number', 'first_name', 'last_name',
                             'date_of_birth', 'barangay', 'plate_mv_engine_chassis_no'];

        driverFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                // Remove any existing listener first to avoid duplicates
                field.removeEventListener('input', trackFieldChange);
                field.addEventListener('input', trackFieldChange);
            }
        });
    }

    /**
     * Track when driver fields are modified after selection
     */
    function trackFieldChange() {
        formFieldsModified = true;
    }

    /**
     * Setup form submission validation to detect data changes
     */
    function setupFormSubmissionValidation() {
        const form = document.getElementById('citationForm');
        if (!form) return;

        // Add event listener with high priority (capture phase)
        // This will run BEFORE the citation-form.js submit handler
        form.addEventListener('submit', function(e) {
            // Check if driver data was modified after selecting existing driver
            if (selectedDuplicateDriver && formFieldsModified) {
                // Prevent default submission
                e.preventDefault();
                e.stopImmediatePropagation();

                // Show confirmation dialog using SweetAlert2
                Swal.fire({
                    title: 'Driver Information Changed',
                    html: `
                        <p>You have modified driver information after selecting an existing record.</p>
                        <p><strong>What would you like to do?</strong></p>
                        <ul style="text-align: left; margin: 20px 40px;">
                            <li><strong>Keep Original:</strong> Use the driver's existing information (recommended)</li>
                            <li><strong>Create New:</strong> Create a new driver record with the modified information</li>
                            <li><strong>Cancel:</strong> Review and correct the information</li>
                        </ul>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: '<i data-lucide="undo" style="width: 14px; height: 14px;"></i> Keep Original',
                    denyButtonText: '<i data-lucide="user-plus" style="width: 14px; height: 14px;"></i> Create New Record',
                    cancelButtonText: '<i data-lucide="x" style="width: 14px; height: 14px;"></i> Cancel',
                    confirmButtonColor: '#3b82f6',
                    denyButtonColor: '#f59e0b',
                    cancelButtonColor: '#6c757d',
                    customClass: {
                        popup: 'swal-wide'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Restore original driver data
                        if (originalDriverData) {
                            Object.keys(originalDriverData).forEach(key => {
                                const field = document.getElementById(key);
                                if (field) {
                                    field.value = originalDriverData[key] || '';
                                }
                            });

                            // Trigger DOB change to recalculate age
                            const dobField = document.getElementById('date_of_birth');
                            if (dobField) {
                                dobField.dispatchEvent(new Event('change'));
                            }
                        }

                        // Reset modification flag
                        formFieldsModified = false;

                        // Re-submit the form
                        form.dispatchEvent(new Event('submit'));
                    } else if (result.isDenied) {
                        // Remove selected_driver_id to force new record creation
                        const driverIdField = document.getElementById('selected_driver_id');
                        if (driverIdField) {
                            driverIdField.remove();
                        }

                        // Clear selected driver reference
                        selectedDuplicateDriver = null;
                        formFieldsModified = false;

                        // Re-submit the form
                        form.dispatchEvent(new Event('submit'));
                    }
                    // If cancelled, do nothing - form won't submit
                });

                return false; // Prevent submission
            }
        }, true); // Use capture phase to run before citation-form.js handler
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
        let color = '#94a3b8';
        let bg = '#f1f5f9';
        let label = 'Low';
        if (confidence >= 80) { color = '#dc2626'; bg = '#fef2f2'; label = 'Very High'; }
        else if (confidence >= 60) { color = '#d97706'; bg = '#fffbeb'; label = 'High'; }
        else if (confidence >= 50) { color = '#2563eb'; bg = '#eff6ff'; label = 'Moderate'; }

        return `<span class="badge px-2 py-1" style="background: ${bg}; color: ${color}; font-weight: 600; font-size: 0.7rem; border: 1px solid ${color}20; border-radius: 4px;">${confidence}% ${label}</span>`;
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
