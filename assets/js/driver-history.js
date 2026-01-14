/**
 * Driver History JavaScript
 * Displays complete citation history for a driver
 */

(function() {
    'use strict';

    const driverId = new URLSearchParams(window.location.search).get('driver_id');

    if (!driverId) {
        window.location.href = 'citations.php';
        return;
    }

    // Fetch and display driver history on page load
    document.addEventListener('DOMContentLoaded', function() {
        fetchDriverHistory();
    });

    /**
     * Fetch driver history from API
     */
    function fetchDriverHistory() {
        fetch(`../api/get_driver_history.php?driver_id=${driverId}`)
            .then(response => response.json())
            .then(data => {
                // Hide loading spinner
                document.getElementById('loadingSpinner').style.display = 'none';

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load driver history');
                }

                // Show content
                document.getElementById('historyContent').style.display = 'block';

                // Render all sections
                renderDriverInfo(data.driver);
                renderOffenseStats(data.offense_stats);
                renderCitationTimeline(data.citations);

                // Reinitialize icons
                lucide.createIcons();
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingSpinner').style.display = 'none';
                document.getElementById('errorMessage').style.display = 'block';
                document.getElementById('errorText').textContent = 'Failed to load driver history: ' + error.message;
                lucide.createIcons();
            });
    }

    /**
     * Render driver information
     */
    function renderDriverInfo(driver) {
        const html = `
            <h2 class="driver-name">
                <i data-lucide="user"></i>
                ${driver.last_name}, ${driver.first_name} ${driver.middle_initial || ''}
            </h2>
            <div class="driver-info-grid">
                <div class="info-field">
                    <div class="info-label">License Number</div>
                    <div class="info-value">${driver.license_number || '<span class="text-muted">N/A</span>'}</div>
                </div>
                <div class="info-field">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value">${driver.date_of_birth ? formatDate(driver.date_of_birth) : '<span class="text-muted">N/A</span>'}</div>
                </div>
                <div class="info-field">
                    <div class="info-label">Age</div>
                    <div class="info-value">${driver.age || '<span class="text-muted">N/A</span>'}</div>
                </div>
                <div class="info-field">
                    <div class="info-label">Address</div>
                    <div class="info-value">${formatAddress(driver)}</div>
                </div>
                <div class="info-field">
                    <div class="info-label">Driver ID</div>
                    <div class="info-value"><span class="badge bg-primary">#${driver.driver_id}</span></div>
                </div>
            </div>
            <div class="driver-note">
                <strong><i data-lucide="info"></i> Master Record Note:</strong>
                This shows the driver's current master record. Individual citation records below preserve the exact information provided at the time of each citation.
            </div>
        `;
        document.getElementById('driverInfo').innerHTML = html;
    }

    /**
     * Render offense statistics
     */
    function renderOffenseStats(stats) {
        if (!stats || stats.length === 0) {
            document.getElementById('offenseStats').innerHTML = `
                <div class="text-center py-4">
                    <i data-lucide="info" style="width: 32px; height: 32px; color: #cbd5e0;"></i>
                    <p class="text-muted mt-2 mb-0">No violation statistics available.</p>
                </div>
            `;
            return;
        }

        const html = stats.map(stat => `
            <div class="stat-item">
                <div class="stat-header">
                    <div class="stat-title">
                        <i data-lucide="alert-triangle"></i>
                        ${stat.violation_type}
                    </div>
                    ${getOffenseBadge(stat.highest_offense)}
                </div>
                <div class="stat-count">
                    ${stat.count}<small>violation${stat.count > 1 ? 's' : ''}</small>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-footer">
                    Highest level: ${stat.highest_offense}${getOrdinalSuffix(stat.highest_offense)} offense
                </div>
            </div>
        `).join('');

        document.getElementById('offenseStats').innerHTML = html;
    }

    /**
     * Render citation timeline
     */
    function renderCitationTimeline(citations) {
        if (!citations || citations.length === 0) {
            document.getElementById('citationTimeline').innerHTML = `
                <div class="timeline-empty">
                    <i data-lucide="inbox"></i>
                    <p>No citations found for this driver.</p>
                </div>
            `;
            return;
        }

        const html = citations.map((citation, index) => {
            const dataChanges = detectDataChanges(citations, index);

            return `
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="timeline-title">
                                <div class="timeline-ticket">
                                    <i data-lucide="file-text"></i>
                                    Citation #${citation.ticket_number}
                                </div>
                                <div class="timeline-date">
                                    <i data-lucide="calendar"></i>
                                    ${formatDateTime(citation.apprehension_datetime)}
                                </div>
                            </div>
                            ${getStatusBadge(citation.status)}
                        </div>
                        <div class="timeline-body">
                            <div class="timeline-field">
                                <div class="timeline-field-label">Violations</div>
                                <div class="timeline-field-value">${citation.violations || '<span class="text-muted">None</span>'}</div>
                            </div>
                            <div class="timeline-field">
                                <div class="timeline-field-label">Total Fine</div>
                                <div class="timeline-field-value timeline-fine">₱${parseFloat(citation.total_fine || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                            </div>
                            <div class="timeline-field">
                                <div class="timeline-field-label">Location</div>
                                <div class="timeline-field-value">
                                    <i data-lucide="map-pin"></i>
                                    ${citation.place_of_apprehension}
                                </div>
                            </div>
                            <div class="timeline-field">
                                <div class="timeline-field-label">Apprehending Officer</div>
                                <div class="timeline-field-value">
                                    <i data-lucide="user"></i>
                                    ${citation.apprehension_officer || 'N/A'}
                                </div>
                            </div>
                        </div>

                        ${dataChanges.length > 0 ? `
                            <div class="data-changes">
                                <strong><i data-lucide="info"></i> Driver Information Changes Recorded:</strong>
                                <ul>
                                    ${dataChanges.map(change => `<li>${change}</li>`).join('')}
                                </ul>
                                <span class="data-changes-note">
                                    These changes show what information the driver provided at the time of this citation compared to the previous citation.
                                </span>
                            </div>
                        ` : ''}

                        ${citation.remarks ? `
                            <div class="timeline-remarks">
                                <div class="timeline-remarks-label">Remarks</div>
                                <div class="timeline-remarks-content">${citation.remarks}</div>
                            </div>
                        ` : ''}

                        <div class="timeline-footer">
                            <i data-lucide="clock"></i>
                            Created: ${formatDateTime(citation.created_at)}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        document.getElementById('citationTimeline').innerHTML = `<div class="timeline-container">${html}</div>`;
    }

    /**
     * Detect data changes between citations
     */
    function detectDataChanges(citations, currentIndex) {
        if (currentIndex >= citations.length - 1) {
            return []; // First (oldest) citation, no comparison
        }

        const currentCitation = citations[currentIndex];
        const previousCitation = citations[currentIndex + 1];
        const changes = [];

        // Check for address changes
        if (currentCitation.barangay !== previousCitation.barangay) {
            changes.push(`<strong>Address:</strong> ${previousCitation.barangay || 'None'} → ${currentCitation.barangay || 'None'}`);
        }

        // Check for plate number changes
        if (currentCitation.plate_mv_engine_chassis_no !== previousCitation.plate_mv_engine_chassis_no) {
            changes.push(`<strong>Plate Number:</strong> ${previousCitation.plate_mv_engine_chassis_no || 'None'} → ${currentCitation.plate_mv_engine_chassis_no || 'None'}`);
        }

        // Check for license changes
        if (currentCitation.license_number !== previousCitation.license_number) {
            changes.push(`<strong>License:</strong> ${previousCitation.license_number || 'None'} → ${currentCitation.license_number || 'None'}`);
        }

        // Check for name changes (possible typo corrections)
        const prevName = `${previousCitation.first_name} ${previousCitation.last_name}`.trim();
        const currName = `${currentCitation.first_name} ${currentCitation.last_name}`.trim();
        if (prevName !== currName) {
            changes.push(`<strong>Name:</strong> ${prevName} → ${currName}`);
        }

        return changes;
    }

    /**
     * Helper functions
     */
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function formatDateTime(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatAddress(driver) {
        const parts = [];
        if (driver.zone) parts.push(`Zone ${driver.zone}`);
        if (driver.barangay) parts.push(driver.barangay);
        if (driver.municipality) parts.push(driver.municipality);
        if (driver.province) parts.push(driver.province);
        return parts.length > 0 ? parts.join(', ') : '<span class="text-muted">N/A</span>';
    }

    function getOrdinalSuffix(num) {
        const j = num % 10;
        const k = num % 100;
        if (j == 1 && k != 11) return 'st';
        if (j == 2 && k != 12) return 'nd';
        if (j == 3 && k != 13) return 'rd';
        return 'th';
    }

    function getOffenseBadge(offenseCount) {
        if (offenseCount === 1) {
            return '<span class="badge bg-success">1st Offense</span>';
        } else if (offenseCount === 2) {
            return '<span class="badge bg-warning text-dark">2nd Offense</span>';
        } else if (offenseCount >= 3) {
            return '<span class="badge bg-danger">3rd+ Offense</span>';
        }
        return '';
    }

    function getStatusBadge(status) {
        const colors = {
            'pending': 'warning',
            'paid': 'success',
            'unpaid': 'danger',
            'settled': 'info',
            'completed': 'success'
        };
        const color = colors[status?.toLowerCase()] || 'secondary';
        const displayStatus = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';
        return `<span class="badge bg-${color}">${displayStatus}</span>`;
    }

})();
