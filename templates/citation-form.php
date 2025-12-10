<?php
/**
 * Citation Form Template
 * Renders the official traffic citation form
 *
 * Required variables:
 * - $next_ticket: Next citation number
 * - $driver_data: Driver information (optional)
 * - $offense_counts: Offense counts for driver (optional)
 * - $violation_types: Array of active violation types
 * - $apprehending_officers: Array of active officers
 */
?>
<form id="citationForm" action="../api/insert_citation.php" method="POST">
    <div class="ticket-container">
        <div class="header">
            <div class="header-text">
                <h4>Republic of the Philippines • Province of Cagayan</h4>
                <h1>Traffic Citation Ticket</h1>
            </div>
            <input type="hidden" name="csrf_token" id="csrfToken" value="<?php echo generate_token(); ?>">
        </div>

        <!-- Hybrid Citation Number Input with Toggle -->
        <div class="citation-number-input-group">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <label for="citation_no" class="mb-0">
                    <i data-lucide="hash"></i>
                    Citation Number
                    <span class="text-danger">*</span>
                </label>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="autoGenerateToggle" checked>
                    <label class="form-check-label" for="autoGenerateToggle">
                        <i data-lucide="sparkles" style="width: 14px; height: 14px;"></i>
                        Auto-generate
                    </label>
                </div>
            </div>

            <!-- Last Citation Reference -->
            <?php if (!empty($last_citation)): ?>
            <div class="citation-sequence-info">
                <div class="sequence-badge">
                    <i data-lucide="arrow-right-circle" style="width: 14px; height: 14px;"></i>
                    <span class="sequence-label">Last saved:</span>
                    <span class="sequence-number"><?php echo htmlspecialchars($last_citation); ?></span>
                    <i data-lucide="arrow-right" style="width: 14px; height: 14px; color: #94a3b8;"></i>
                    <span class="sequence-label">Next:</span>
                    <span class="sequence-number sequence-next"><?php echo htmlspecialchars($next_ticket); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <input
                type="text"
                class="form-control"
                id="citation_no"
                name="ticket_number"
                value="<?php echo htmlspecialchars($next_ticket); ?>"
                required
                readonly
                pattern="[A-Z0-9\-]{6,8}"
                placeholder="e.g., 061234"
                autocomplete="off"
                minlength="6"
                maxlength="8"
                data-auto-value="<?php echo htmlspecialchars($next_ticket); ?>"
                title="Citation number must be 6 to 8 characters (letters, numbers, or hyphens)"
            >
            <div class="citation-help-text" id="citationHelp">
                <i data-lucide="info"></i>
                <span id="citationHelpText">Auto-generated citation number. Toggle switch to enter manually.</span>
            </div>
            <div class="citation-validation-feedback" id="citationFeedback"></div>
        </div>

        <!-- Driver Info -->
        <div class="section">
            <h5><i data-lucide="user" style="width: 20px; height: 20px; margin-right: 8px;"></i>Driver Information</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Last Name *</label>
                    <input type="text" name="last_name" class="form-control" placeholder="Enter last name" value="<?php echo htmlspecialchars($driver_data['last_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">First Name *</label>
                    <input type="text" name="first_name" class="form-control" placeholder="Enter first name" value="<?php echo htmlspecialchars($driver_data['first_name'] ?? ''); ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">M.I.</label>
                    <input type="text" name="middle_initial" class="form-control" placeholder="M.I." value="<?php echo htmlspecialchars($driver_data['middle_initial'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Suffix</label>
                    <input type="text" name="suffix" class="form-control" placeholder="e.g., Jr." value="<?php echo htmlspecialchars($driver_data['suffix'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" id="dateOfBirth" value="<?php echo htmlspecialchars($driver_data['date_of_birth'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Age</label>
                    <input type="number" name="age" class="form-control" id="ageField" placeholder="Auto" value="<?php echo htmlspecialchars($driver_data['age'] ?? ''); ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Zone</label>
                    <input type="text" name="zone" class="form-control" placeholder="Enter zone" value="<?php echo htmlspecialchars($driver_data['zone'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Barangay *</label>
                    <select name="barangay" class="form-select" id="barangaySelect" required>
                        <option value="" disabled <?php echo (!isset($driver_data['barangay']) || $driver_data['barangay'] == '') ? 'selected' : ''; ?>>Select Barangay</option>
                        <?php
                        $barangays = [
                            'Adaoag', 'Agaman (Proper)', 'Agaman Norte', 'Agaman Sur', 'Alba', 'Annayatan',
                            'Asassi', 'Asinga-Via', 'Awallan', 'Bacagan', 'Bagunot', 'Barsat East',
                            'Barsat West', 'Bitag Grande', 'Bitag Pequeño', 'Bunugan', 'C. Verzosa (Valley Cove)',
                            'Canagatan', 'Carupian', 'Catugay', 'Dabbac Grande', 'Dalin', 'Dalla',
                            'Hacienda Intal', 'Ibulo', 'Immurung', 'J. Pallagao', 'Lasilat', 'Mabini',
                            'Masical', 'Mocag', 'Nangalinan', 'Poblacion (Centro)', 'Remus', 'San Antonio',
                            'San Francisco', 'San Isidro', 'San Jose', 'San Miguel', 'San Vicente',
                            'Santa Margarita', 'Santor', 'Taguing', 'Taguntungan', 'Tallang', 'Taytay',
                            'Temblique', 'Tungel', 'Other'
                        ];
                        foreach ($barangays as $barangay) {
                            $selected = (isset($driver_data['barangay']) && $driver_data['barangay'] == $barangay) ? 'selected' : '';
                            echo "<option value=\"$barangay\" $selected>$barangay</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3" id="otherBarangayDiv" style="display: none;">
                    <label class="form-label">Specify Other Barangay *</label>
                    <input type="text" name="other_barangay" class="form-control" id="otherBarangayInput" placeholder="Enter other barangay" value="<?php echo (isset($driver_data['barangay']) && $driver_data['barangay'] == 'Other') ? htmlspecialchars($driver_data['barangay']) : ''; ?>">
                </div>
                <div class="col-md-3" id="municipalityDiv" style="display: <?php echo (isset($driver_data['barangay']) && $driver_data['barangay'] != 'Other' && $driver_data['barangay'] != '') ? 'block' : 'none'; ?>;">
                    <label class="form-label">Municipality</label>
                    <input type="text" name="municipality" class="form-control" id="municipalityInput" value="<?php echo htmlspecialchars($driver_data['municipality'] ?? 'Baggao'); ?>" readonly>
                </div>
                <div class="col-md-3" id="provinceDiv" style="display: <?php echo (isset($driver_data['barangay']) && $driver_data['barangay'] != 'Other' && $driver_data['barangay'] != '') ? 'block' : 'none'; ?>;">
                    <label class="form-label">Province</label>
                    <input type="text" name="province" class="form-control" id="provinceInput" value="<?php echo htmlspecialchars($driver_data['province'] ?? 'Cagayan'); ?>" readonly>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="has_license" id="hasLicense" <?php echo (isset($driver_data['license_number']) && !empty($driver_data['license_number'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="hasLicense">Has License</label>
                    </div>
                </div>
                <div class="col-md-4 license-field" style="display: <?php echo (isset($driver_data['license_number']) && !empty($driver_data['license_number'])) ? 'block' : 'none'; ?>;">
                    <label class="form-label">License Number *</label>
                    <input type="text" name="license_number" class="form-control" placeholder="Enter license number" value="<?php echo htmlspecialchars($driver_data['license_number'] ?? ''); ?>" <?php echo (isset($driver_data['license_number']) && !empty($driver_data['license_number'])) ? 'required' : ''; ?>>
                </div>
                <div class="col-md-2 license-field" style="display: <?php echo (isset($driver_data['license_number']) && !empty($driver_data['license_number'])) ? 'block' : 'none'; ?>;">
                    <label class="form-label d-block">License Type *</label>
                    <div class="form-check">
                        <input type="radio" class="form-check-input" name="license_type" value="nonProf" id="nonProf" <?php echo (!isset($driver_data['license_type']) || $driver_data['license_type'] == 'Non-Professional') ? 'checked' : ''; ?> <?php echo (isset($driver_data['license_number']) && !empty($driver_data['license_number'])) ? 'required' : ''; ?>>
                        <label class="form-check-label" for="nonProf">Non-Prof</label>
                    </div>
                </div>
                <div class="col-md-2 license-field" style="display: <?php echo (isset($driver_data['license_number']) && !empty($driver_data['license_number'])) ? 'block' : 'none'; ?>;">
                    <label class="form-label d-block"> </label>
                    <div class="form-check">
                        <input type="radio" class="form-check-input" name="license_type" value="prof" id="prof" <?php echo (isset($driver_data['license_type']) && $driver_data['license_type'] == 'Professional') ? 'checked' : ''; ?> <?php echo (isset($driver_data['license_number']) && !empty($driver_data['license_number'])) ? 'required' : ''; ?>>
                        <label class="form-check-label" for="prof">Prof</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehicle Info -->
        <div class="section">
            <h5><i data-lucide="car" style="width: 20px; height: 20px; margin-right: 8px;"></i>Vehicle Information</h5>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Plate / MV File / Engine / Chassis No. *</label>
                    <input type="text" name="plate_mv_engine_chassis_no" class="form-control" placeholder="Enter plate or other number" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Vehicle Type *</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php
                        $vehicleTypes = ['Motorcycle', 'Tricycle', 'SUV', 'Van', 'Jeep', 'Truck', 'Kulong Kulong', 'Other'];
                        foreach ($vehicleTypes as $index => $type) {
                            $id = strtolower(str_replace(' ', '', $type));
                            $required = ($index === 0) ? 'required' : '';
                            echo "<div class='form-check'>";
                            echo "<input type='radio' class='form-check-input' name='vehicle_type' value='$type' id='$id' $required onchange='toggleOtherVehicle(this.value)'>";
                            echo "<label class='form-check-label' for='$id'>$type</label>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                    <input type="text" name="other_vehicle_input" class="form-control mt-2" id="otherVehicleInput" placeholder="Specify other vehicle type" style="display: none;">
                </div>
                <div class="col-12">
                    <label class="form-label">Vehicle Description</label>
                    <input type="text" name="vehicle_description" class="form-control" placeholder="Brand, Model, CC, Color, etc.">
                </div>
                <div class="col-12">
                    <label class="form-label">Apprehension Date & Time *</label>
                    <div class="input-group">
                        <input type="datetime-local" name="apprehension_datetime" class="form-control" id="apprehensionDateTime" required>
                        <button class="btn btn-outline-secondary" type="button" id="toggleDateTime" title="Set/Clear"><i class="fas fa-calendar-alt"></i></button>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Place of Apprehension *</label>
                    <input type="text" name="place_of_apprehension" class="form-control" placeholder="Enter place of apprehension" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Apprehension Officer *</label>
                    <select name="apprehension_officer" class="form-select" id="apprehensionOfficer" required>
                        <option value="" disabled selected>Select Apprehension Officer</option>
                        <?php if (!empty($apprehending_officers)): ?>
                            <?php foreach ($apprehending_officers as $officer): ?>
                                <option value="<?php echo htmlspecialchars($officer['officer_name']); ?>">
                                    <?php echo htmlspecialchars($officer['officer_name']); ?>
                                    <?php if (!empty($officer['badge_number'])): ?>
                                        (<?php echo htmlspecialchars($officer['badge_number']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Violations (Tabbed Interface) -->
        <div class="section">
            <h5 class="text-danger"><i data-lucide="alert-triangle" style="width: 20px; height: 20px; margin-right: 8px;"></i>Violation(s) *</h5>

            <!-- Search Box -->
            <div class="violation-search-box mb-3">
                <div class="search-wrapper">
                    <i data-lucide="search" class="search-icon"></i>
                    <input type="text" class="search-input" id="violationSearch" placeholder="Search all violations...">
                    <button class="search-clear" type="button" id="clearSearch" title="Clear search">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <small class="search-hint">
                    <i data-lucide="info" style="width: 14px; height: 14px;"></i> Search works across all categories
                </small>
            </div>

            <!-- Tabs Navigation - Modern Pills -->
            <div class="violation-tabs-wrapper">
                <div class="violation-tabs" id="violationTabs">
                    <?php
                    // Use dynamic categories from database
                    $tab_index = 0;
                    foreach ($violation_categories as $cat) {
                        $category_slug = strtolower(str_replace(' ', '-', $cat['category_name']));
                        $active_class = $tab_index === 0 ? ' active' : '';

                        echo "<button class='tab-pill$active_class' data-tab='$category_slug' data-category-id='" . $cat['category_id'] . "' type='button'>";
                        echo "<i data-lucide='" . htmlspecialchars($cat['category_icon']) . "' class='tab-icon'></i>";
                        echo "<span class='tab-label'>" . htmlspecialchars($cat['category_name']) . "</span>";
                        echo "<span class='tab-badge' data-tab='$category_slug'>0</span>";
                        echo "</button>";
                        $tab_index++;
                    }
                    ?>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="violation-tab-content" id="violationTabsContent">
                <?php
                $tab_index = 0;

                foreach ($violation_categories as $cat) {
                    $category_slug = strtolower(str_replace(' ', '-', $cat['category_name']));
                    $active_class = $tab_index === 0 ? ' active' : '';

                    echo "<div class='tab-pane$active_class' data-pane='$category_slug'>";
                    echo "<div class='violations-list'>";

                    // Check if this is "Other" category for custom violation input
                    $isOtherCategory = ($cat['category_name'] === 'Other');

                    if ($isOtherCategory) {
                        // Custom violation
                        echo "<div class='violation-item'>";
                        echo "<div class='custom-checkbox'>";
                        echo "<input type='checkbox' class='checkbox-input' name='other_violation' id='other_violation'>";
                        echo "<label class='checkbox-label' for='other_violation'>";
                        echo "<span class='checkbox-box'></span>";
                        echo "<span class='checkbox-text'>Other Violation (Specify below)</span>";
                        echo "</label>";
                        echo "</div></div>";
                        echo "<input type='text' name='other_violation_input' class='form-control mt-2' id='otherViolationInput' placeholder='Specify other violation' style='display: none;'>";
                    }

                    // Find violations matching this category
                    $violations_found = false;
                    foreach ($violation_types as $v) {
                        // Match violations by category_id
                        if (isset($v['category_id']) && $v['category_id'] == $cat['category_id']) {
                            $violations_found = true;
                            $offense_count = isset($offense_counts[$v['violation_type_id']]) ? min((int)$offense_counts[$v['violation_type_id']] + 1, 3) : 1;
                            $fine_key = "fine_amount_$offense_count";
                            $offense_suffix = $offense_count == 1 ? 'st' : ($offense_count == 2 ? 'nd' : 'rd');
                            $label = $v['violation_type'] . " - {$offense_count}{$offense_suffix} Offense (₱" . number_format($v[$fine_key], 2) . ")";
                            $input_id = 'violation_' . $v['violation_type_id'];

                            echo "<div class='violation-item' data-violation-text='" . htmlspecialchars(strtolower($v['violation_type'])) . "'>";
                            echo "<div class='custom-checkbox'>";
                            echo "<input type='checkbox' class='checkbox-input violation-checkbox' name='violations[]' value='" . (int)$v['violation_type_id'] . "' id='$input_id' data-offense='$offense_count' data-tab='$category_slug'>";
                            echo "<label class='checkbox-label' for='$input_id'>";
                            echo "<span class='checkbox-box'></span>";
                            echo "<span class='checkbox-text'>" . htmlspecialchars($label) . "</span>";
                            echo "</label>";
                            echo "</div></div>";
                        }
                    }

                    if (!$violations_found && !$isOtherCategory) {
                        echo "<div class='empty-state'>";
                        echo "<i data-lucide='inbox' style='width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 8px;'></i>";
                        echo "<p>No violations available in this category.</p>";
                        echo "</div>";
                    }

                    echo "</div></div>";
                    $tab_index++;
                }
                ?>
            </div>

            <!-- No Results Message -->
            <div class="no-results" id="noResultsAlert" style="display: none;">
                <i data-lucide="search" class="no-results-icon"></i>
                <div class="no-results-text">
                    No violations found matching "<strong id="searchQuery"></strong>"
                    <br><small>Try different keywords</small>
                </div>
            </div>
            <div class="mt-3 remarks">
                <label class="form-label">
                    Remarks
                    <span id="remarksCount" class="char-counter">(0/500)</span>
                </label>
                <textarea name="remarks"
                          class="form-control"
                          rows="4"
                          maxlength="500"
                          id="remarksField"
                          placeholder="Enter additional remarks (optional)"></textarea>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                All apprehensions are deemed admitted unless contested by filing a written contest at the Traffic Management Office within five (5) working days from date of issuance.
                Failure to pay the corresponding penalty at the Municipal Treasury Office within fifteen (15) days from date of apprehension, shall be the ground for filing a formal complaint against you.
                Likewise, a copy of this ticket shall be forwarded to concerned agencies for proper action/disposition.
            </p>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-custom" id="submitBtn">
                <i data-lucide="send" style="width: 18px; height: 18px; margin-right: 8px;"></i>Submit Citation
            </button>
            <button type="button" class="btn btn-outline-secondary" id="clearFormBtn">
                <i data-lucide="eraser" style="width: 18px; height: 18px; margin-right: 8px;"></i>Clear Form
            </button>
            <button type="button" class="btn btn-outline-info" id="saveDraftBtn">
                <i data-lucide="save" style="width: 18px; height: 18px; margin-right: 8px;"></i>Save Draft
            </button>
        </div>
    </div>
</form>
