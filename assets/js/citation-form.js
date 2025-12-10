/**
 * Citation Form JavaScript
 * Handles all client-side logic for the traffic citation form
 */

// Global function for toggling Other Vehicle input
function toggleOtherVehicle(value) {
    const otherInput = document.getElementById('otherVehicleInput');
    if (value === 'Other') {
        otherInput.style.display = 'block';
        otherInput.required = true;
        otherInput.focus();
    } else {
        otherInput.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
    }
}

document.addEventListener('DOMContentLoaded', () => {

    const csrfTokenInput = document.getElementById('csrfToken');
    const otherViolationCheckbox = document.getElementById('other_violation');
    const otherViolationInput = document.getElementById('otherViolationInput');
    const vehicleTypeRadios = document.querySelectorAll('input[name="vehicle_type"]');
    const otherVehicleRadio = document.getElementById('othersVehicle');
    const otherVehicleInput = document.getElementById('otherVehicleInput');
    const hasLicenseCheckbox = document.getElementById('hasLicense');
    const licenseFields = document.querySelectorAll('.license-field');
    const barangaySelect = document.getElementById('barangaySelect');
    const otherBarangayInput = document.getElementById('otherBarangayInput');
    const otherBarangayDiv = document.getElementById('otherBarangayDiv');
    const municipalityDiv = document.getElementById('municipalityDiv');
    const provinceDiv = document.getElementById('provinceDiv');
    const dateOfBirthInput = document.getElementById('dateOfBirth');
    const ageField = document.getElementById('ageField');

    // === AUTOMATIC AGE CALCULATION ===
    function calculateAge(birthDate) {
        const today = new Date();
        const birth = new Date(birthDate);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        return age;
    }

    // Helper function to find violation checkbox by label text
    function findViolationCheckboxByText(searchText) {
        // Search in both .form-check-label (driver/license fields) and .checkbox-label (violations)
        const labels = document.querySelectorAll('.form-check-label, .checkbox-label');

        for (let label of labels) {
            const labelText = label.textContent.trim();
            if (labelText.toUpperCase().includes(searchText.toUpperCase())) {
                const forAttr = label.getAttribute('for');
                if (forAttr) {
                    const checkbox = document.getElementById(forAttr);
                    return checkbox;
                }
            }
        }
        return null;
    }

    // Track if minor violation prompt was already shown
    let minorViolationPrompted = false;

    // Use 'blur' event instead of 'change' - only triggers when user leaves the field
    dateOfBirthInput.addEventListener('blur', () => {
        if (dateOfBirthInput.value) {
            const age = calculateAge(dateOfBirthInput.value);
            if (age >= 0 && age <= 120) {
                ageField.value = age;

                // Show SweetAlert prompt for minor violation if under 18
                let minorCheckbox = findViolationCheckboxByText("MINOR");
                if (!minorCheckbox) {
                    minorCheckbox = findViolationCheckboxByText("UNDERAGE");
                }

                if (age <= 18 && minorCheckbox && !minorCheckbox.checked && !minorViolationPrompted) {
                    minorViolationPrompted = true;

                    // Get the violation name from the label
                    const violationLabel = document.querySelector(`label[for="${minorCheckbox.id}"]`);
                    const violationName = violationLabel ? violationLabel.textContent.split(' - ')[0].trim() : 'MINOR';

                    Swal.fire({
                        title: 'Minor Detected',
                        html: `The driver's age is <strong>${age} years old</strong> (18 or below).<br><br>Do you want to automatically add the<br><strong>"${violationName}"</strong> violation?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Add Violation',
                        cancelButtonText: 'No, Skip',
                        customClass: {
                            popup: 'swal-wide'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            minorCheckbox.checked = true;

                            // Expand the accordion section containing this violation
                            const accordionBody = minorCheckbox.closest('.accordion-collapse');
                            if (accordionBody && !accordionBody.classList.contains('show')) {
                                new bootstrap.Collapse(accordionBody, { show: true });
                            }

                            // Show success message
                            Swal.fire({
                                title: 'Violation Added!',
                                text: `${violationName} violation has been added.`,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    });
                }
            } else {
                ageField.value = '';
                Swal.fire({
                    title: 'Invalid Date of Birth',
                    text: 'Please enter a valid date of birth.',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                });
            }
        } else {
            ageField.value = '';
        }
    });

    // Reset minor violation flag when date changes
    dateOfBirthInput.addEventListener('input', () => {
        minorViolationPrompted = false;
    });

    // Calculate age on page load if DOB exists
    if (dateOfBirthInput.value) {
        const age = calculateAge(dateOfBirthInput.value);
        if (age >= 0 && age <= 120) {
            ageField.value = age;
        }
    }

    // === AUTO-CHECK NO LICENSE VIOLATION ===
    // Try multiple search terms since violations might be separated
    let noLicenseViolationCheckbox = findViolationCheckboxByText("NO DRIVER'S LICENSE");
    if (!noLicenseViolationCheckbox) {
        noLicenseViolationCheckbox = findViolationCheckboxByText("NO LICENSE");
    }
    if (!noLicenseViolationCheckbox) {
        noLicenseViolationCheckbox = findViolationCheckboxByText("WITHOUT LICENSE");
    }
    let licenseValidationPrompted = false;

    hasLicenseCheckbox.addEventListener('change', () => {
        const isChecked = hasLicenseCheckbox.checked;
        licenseFields.forEach(field => {
            field.style.display = isChecked ? 'block' : 'none';
            const inputs = field.querySelectorAll('input');
            inputs.forEach(input => {
                input.required = isChecked;
                if (!isChecked) {
                    input.value = '';
                    if (input.type === 'radio') input.checked = false;
                }
            });
        });

        // Reset validation flag when license status changes
        licenseValidationPrompted = false;
    });

    // Prompt when user selects a Vehicle Type
    vehicleTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {

            // Check for no license violation after selecting vehicle type (with 3 second delay)
            if (!hasLicenseCheckbox.checked && !licenseValidationPrompted && noLicenseViolationCheckbox && !noLicenseViolationCheckbox.checked) {
                licenseValidationPrompted = true;

                setTimeout(() => {
                    // Get the violation name from the label
                    const violationLabel = document.querySelector(`label[for="${noLicenseViolationCheckbox.id}"]`);
                    const violationName = violationLabel ? violationLabel.textContent.split(' - ')[0].trim() : 'NO DRIVER\'S LICENSE';

                    Swal.fire({
                        title: 'No License Detected',
                        html: `The <strong>"Has License"</strong> checkbox is unchecked.<br><br>Do you want to automatically add the<br><strong>"${violationName}"</strong> violation?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, Add Violation',
                        cancelButtonText: 'No, Skip',
                        customClass: {
                            popup: 'swal-wide'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            noLicenseViolationCheckbox.checked = true;

                            // Expand the accordion section containing this violation
                            const accordionBody = noLicenseViolationCheckbox.closest('.accordion-collapse');
                            if (accordionBody && !accordionBody.classList.contains('show')) {
                                new bootstrap.Collapse(accordionBody, { show: true });
                            }

                            // Show success message
                            Swal.fire({
                                title: 'Violation Added!',
                                text: `${violationName} violation has been added.`,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    });
                }, 3000); // 3 second delay
            }
        });
    });

    // Auto-populate Municipality and Province
    barangaySelect.addEventListener('change', () => {
        const isOther = barangaySelect.value === 'Other';
        if (isOther) {
            otherBarangayDiv.style.display = 'block';
            otherBarangayInput.required = true;
            otherBarangayInput.focus();
            municipalityDiv.style.display = 'block';
            provinceDiv.style.display = 'block';
            municipalityDiv.querySelector('input').value = '';
            provinceDiv.querySelector('input').value = '';
            municipalityDiv.querySelector('input').removeAttribute('readonly');
            provinceDiv.querySelector('input').removeAttribute('readonly');
        } else {
            otherBarangayDiv.style.display = 'none';
            otherBarangayInput.required = false;
            otherBarangayInput.value = '';
            if (barangaySelect.value) {
                municipalityDiv.style.display = 'block';
                provinceDiv.style.display = 'block';
                municipalityDiv.querySelector('input').value = 'Baggao';
                provinceDiv.querySelector('input').value = 'Cagayan';
                municipalityDiv.querySelector('input').setAttribute('readonly', true);
                provinceDiv.querySelector('input').setAttribute('readonly', true);
            } else {
                municipalityDiv.style.display = 'none';
                provinceDiv.style.display = 'none';
                municipalityDiv.querySelector('input').value = '';
                provinceDiv.querySelector('input').value = '';
            }
        }
    });

    // Toggle DateTime button
    const toggleBtn = document.getElementById('toggleDateTime');
    const dateTimeInput = document.getElementById('apprehensionDateTime');
    let isAutoFilled = false;
    toggleBtn.addEventListener('click', () => {
        if (!isAutoFilled) {
            const now = new Date();
            const offset = now.getTimezoneOffset();
            now.setMinutes(now.getMinutes() - offset);
            dateTimeInput.value = now.toISOString().slice(0, 16);
            isAutoFilled = true;
            toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
            toggleBtn.classList.remove('btn-outline-secondary');
            toggleBtn.classList.add('btn-outline-danger');
        } else {
            dateTimeInput.value = '';
            isAutoFilled = false;
            toggleBtn.innerHTML = '<i class="fas fa-calendar-alt"></i>';
            toggleBtn.classList.remove('btn-outline-danger');
            toggleBtn.classList.add('btn-outline-secondary');
        }
    });

    // Show/hide Other Violation input
    if (otherViolationCheckbox && otherViolationInput) {
        otherViolationCheckbox.addEventListener('change', function() {
            console.log('Other Violation checkbox changed:', this.checked);
            if (this.checked) {
                otherViolationInput.style.cssText = 'display: block !important; margin-top: 8px;';
                otherViolationInput.required = true;
                otherViolationInput.focus();
            } else {
                otherViolationInput.style.cssText = 'display: none !important; margin-top: 8px;';
                otherViolationInput.required = false;
                otherViolationInput.value = '';
            }
        });
    }

    // Show/hide Other Vehicle input (for radio buttons)
    if (vehicleTypeRadios.length > 0 && otherVehicleInput) {
        vehicleTypeRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'Other') {
                    otherVehicleInput.style.cssText = 'display: block !important; margin-top: 8px;';
                    otherVehicleInput.required = true;
                    otherVehicleInput.focus();
                } else {
                    otherVehicleInput.style.cssText = 'display: none !important; margin-top: 8px;';
                    otherVehicleInput.required = false;
                    otherVehicleInput.value = '';
                }
            });
        });
    }

    // Ensure only one license type
    const nonProfCheckbox = document.getElementById('nonProf');
    const profCheckbox = document.getElementById('prof');
    nonProfCheckbox.addEventListener('change', () => {
        if (nonProfCheckbox.checked) profCheckbox.checked = false;
    });
    profCheckbox.addEventListener('change', () => {
        if (profCheckbox.checked) nonProfCheckbox.checked = false;
    });

    // Form validation and submission
    const violationCheckboxes = document.querySelectorAll('input[name="violations[]"], input[name="other_violation"]');
    document.getElementById('citationForm').addEventListener('submit', function(e) {
        e.preventDefault();

        // Validate vehicle type (radio buttons - required attribute handles this)
        const selectedVehicleType = document.querySelector('input[name="vehicle_type"]:checked');
        if (!selectedVehicleType) {
            alert('Please select a vehicle type.');
            return;
        }

        // If "Other" is selected, make sure the input field is filled
        if (selectedVehicleType.value === 'Other' && !otherVehicleInput.value.trim()) {
            alert('Please specify the other vehicle type.');
            otherVehicleInput.focus();
            return;
        }

        // Validate violations
        let violationSelected = false;
        const selectedViolations = [];
        violationCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                violationSelected = true;
                if (checkbox.name === 'violations[]') {
                    selectedViolations.push(checkbox.value);
                } else if (checkbox.name === 'other_violation' && otherViolationInput.value.trim()) {
                    selectedViolations.push(otherViolationInput.value.trim());
                }
            }
        });
        if (!violationSelected) {
            alert('Please select at least one violation. If no violations are available, please contact the system administrator.');
            return;
        }

        const formData = new FormData(this);
        formData.append('csrf_token', csrfTokenInput.value);

        fetch('../api/insert_citation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Store the status for duplicate detection
            const responseStatus = response.status;

            // Parse JSON regardless of status
            return response.json().then(data => {
                return { data, status: responseStatus };
            });
        })
        .then(({ data, status }) => {
            if (data.status === 'success') {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'OK'
                }).then(() => {
                    document.getElementById('citationForm').reset();
                    municipalityDiv.querySelector('input').value = 'Baggao';
                    provinceDiv.querySelector('input').value = 'Cagayan';
                    // Hide "Others" inputs
                    otherViolationInput.style.cssText = 'display: none !important; margin-top: 8px;';
                    otherVehicleInput.style.cssText = 'display: none !important; margin-top: 8px;';
                    otherBarangayDiv.style.display = 'none';
                    otherViolationInput.required = false;
                    otherVehicleInput.required = false;
                    otherBarangayInput.required = false;
                    otherViolationInput.value = '';
                    otherVehicleInput.value = '';
                    otherBarangayInput.value = '';
                    hasLicenseCheckbox.checked = false;
                    licenseFields.forEach(field => {
                        field.style.display = 'none';
                        field.querySelectorAll('input').forEach(input => {
                            input.value = '';
                            if (input.type === 'radio') input.checked = false;
                            input.required = false;
                        });
                    });
                    isAutoFilled = false;
                    toggleBtn.innerHTML = '<i class="fas fa-calendar-alt"></i>';
                    toggleBtn.classList.remove('btn-outline-danger');
                    toggleBtn.classList.add('btn-outline-secondary');
                    // Reset age field
                    ageField.value = '';
                    if (data.new_csrf_token) {
                        csrfTokenInput.value = data.new_csrf_token;
                    }

                    // Trigger event to clear draft (for enhanced form features)
                    window.dispatchEvent(new Event('citationSubmitted'));

                    window.location.reload();
                });
            } else {
                // Check if this is a duplicate citation error (HTTP 409)
                if (status === 409 && data.error_type === 'duplicate_citation') {
                    const duplicateInfo = data.duplicate_info || {};
                    const createdDate = duplicateInfo.created_at ?
                        new Date(duplicateInfo.created_at).toLocaleString() : 'Unknown';

                    Swal.fire({
                        title: 'Citation Already Exists!',
                        html: `
                            <div style="text-align: left; padding: 15px;">
                                <p style="font-size: 1.1rem; margin-bottom: 15px;">
                                    <strong>Citation Number:</strong>
                                    <span style="color: #dc3545; font-family: monospace; font-size: 1.2rem;">
                                        ${duplicateInfo.ticket_number || 'Unknown'}
                                    </span>
                                </p>
                                <p style="color: #6c757d;">
                                    <strong>Previously created:</strong> ${createdDate}
                                </p>
                                <hr style="margin: 15px 0;">
                                <p style="font-size: 0.95rem; color: #495057;">
                                    This citation number has already been used in the system.
                                    Please use a different citation number or toggle to auto-generate mode.
                                </p>
                            </div>
                        `,
                        icon: 'warning',
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: 'OK, I\'ll Change It',
                        width: '600px'
                    }).then(() => {
                        // Focus on citation number field
                        const citationInput = document.getElementById('citation_no');
                        if (citationInput) {
                            citationInput.focus();
                            citationInput.select();
                        }
                    });
                } else {
                    // Generic error message
                    Swal.fire({
                        title: 'Failed!',
                        text: data.message || 'An error occurred while saving the citation.',
                        icon: 'error',
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'OK'
                    });
                }
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'Error submitting form: ' + error.message,
                icon: 'error',
                confirmButtonColor: '#d33',
                confirmButtonText: 'OK'
            });
        });
    });

    // Real-time form validation
    const requiredInputs = document.querySelectorAll('input[required], select[required]');
    requiredInputs.forEach(input => {
        input.addEventListener('input', () => {
            if (input.value.trim() === '') {
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });
    });

    // Initialize "Other Barangay" visibility on page load
    if (barangaySelect && barangaySelect.value === 'Other') {
        otherBarangayDiv.style.display = 'block';
        otherBarangayInput.required = true;
    }
});
