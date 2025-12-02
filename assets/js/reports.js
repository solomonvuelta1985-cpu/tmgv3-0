// Reports JavaScript
document.addEventListener('DOMContentLoaded', function() {

    // Export functionality
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            showExportMenu();
        });
    }

    // Auto-submit form when report type changes
    const reportTypeSelect = document.getElementById('reportTypeSelect');
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', function() {
            document.getElementById('reportFilterForm').submit();
        });
    }

    // Initialize charts if data is available
    initializeCharts();
});

/**
 * Show export menu
 */
function showExportMenu() {
    const menu = document.createElement('div');
    menu.className = 'export-menu show';
    menu.innerHTML = `
        <a href="#" onclick="exportToPDF(); return false;">
            <i class="fas fa-file-pdf"></i>Export to PDF
        </a>
        <a href="#" onclick="exportToCSV(); return false;">
            <i class="fas fa-file-csv"></i>Export to CSV
        </a>
        <a href="#" onclick="exportToExcel(); return false;">
            <i class="fas fa-file-excel"></i>Export to Excel
        </a>
    `;

    // Remove existing menu if any
    const existing = document.querySelector('.export-menu');
    if (existing) {
        existing.remove();
    }

    // Position menu
    const exportBtn = document.getElementById('exportBtn');
    const wrapper = document.createElement('div');
    wrapper.className = 'export-dropdown';
    wrapper.style.position = 'relative';
    wrapper.style.display = 'inline-block';

    exportBtn.parentNode.insertBefore(wrapper, exportBtn);
    wrapper.appendChild(exportBtn.cloneNode(true));
    wrapper.appendChild(menu);
    exportBtn.remove();

    // Close menu when clicking outside
    document.addEventListener('click', function closeMenu(e) {
        if (!wrapper.contains(e.target)) {
            wrapper.parentNode.insertBefore(wrapper.querySelector('#exportBtn'), wrapper);
            wrapper.remove();
            document.removeEventListener('click', closeMenu);
        }
    });
}

/**
 * Export to PDF
 */
function exportToPDF() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', 'pdf');
    window.location.href = '../api/report_export.php?' + urlParams.toString();
}

/**
 * Export to CSV
 */
function exportToCSV() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', 'csv');
    window.location.href = '../api/report_export.php?' + urlParams.toString();
}

/**
 * Export to Excel
 */
function exportToExcel() {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', 'excel');
    window.location.href = '../api/report_export.php?' + urlParams.toString();
}

/**
 * Initialize all charts
 */
function initializeCharts() {
    // Revenue Trend Chart
    const revenueTrendCanvas = document.getElementById('revenueTrendChart');
    if (revenueTrendCanvas) {
        createRevenueTrendChart(revenueTrendCanvas);
    }

    // Violation Statistics Chart
    const violationStatsCanvas = document.getElementById('violationStatsChart');
    if (violationStatsCanvas) {
        createViolationStatsChart(violationStatsCanvas);
    }

    // Time-based Charts
    const hourlyChartCanvas = document.getElementById('hourlyChart');
    if (hourlyChartCanvas) {
        createHourlyChart(hourlyChartCanvas);
    }

    const dayOfWeekChartCanvas = document.getElementById('dayOfWeekChart');
    if (dayOfWeekChartCanvas) {
        createDayOfWeekChart(dayOfWeekChartCanvas);
    }

    const monthlyChartCanvas = document.getElementById('monthlyChart');
    if (monthlyChartCanvas) {
        createMonthlyChart(monthlyChartCanvas);
    }

    // Status Distribution Chart
    const statusChartCanvas = document.getElementById('statusChart');
    if (statusChartCanvas) {
        createStatusChart(statusChartCanvas);
    }

    // Vehicle Type Chart
    const vehicleChartCanvas = document.getElementById('vehicleChart');
    if (vehicleChartCanvas) {
        createVehicleChart(vehicleChartCanvas);
    }

    // Officer Performance Chart
    const officerChartCanvas = document.getElementById('officerChart');
    if (officerChartCanvas) {
        createOfficerChart(officerChartCanvas);
    }
}

/**
 * Create Revenue Trend Chart
 */
function createRevenueTrendChart(canvas) {
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    if (data.length === 0) return;

    const labels = data.map(item => item.period);
    const totalFines = data.map(item => parseFloat(item.total_fines || 0));
    const collectedFines = data.map(item => parseFloat(item.collected_fines || 0));

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Fines Issued',
                data: totalFines,
                borderColor: 'rgb(13, 110, 253)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Fines Collected',
                data: collectedFines,
                borderColor: 'rgb(25, 135, 84)',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Revenue Trends Over Time'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₱' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

/**
 * Create Violation Statistics Chart
 */
function createViolationStatsChart(canvas) {
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    if (data.length === 0) return;

    const labels = data.map(item => item.violation_type);
    const counts = data.map(item => parseInt(item.violation_count || 0));
    const colors = generateColors(data.length);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Number of Violations',
                data: counts,
                backgroundColor: colors.background,
                borderColor: colors.border,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Violations by Type'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Create Hourly Chart
 */
function createHourlyChart(canvas) {
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    if (data.length === 0) return;

    // Create array for all 24 hours
    const hours = Array.from({length: 24}, (_, i) => i);
    const counts = hours.map(hour => {
        const found = data.find(item => parseInt(item.hour_of_day) === hour);
        return found ? parseInt(found.citation_count) : 0;
    });

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: hours.map(h => h + ':00'),
            datasets: [{
                label: 'Citations',
                data: counts,
                backgroundColor: 'rgba(13, 110, 253, 0.6)',
                borderColor: 'rgb(13, 110, 253)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Citations by Hour of Day'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Create Day of Week Chart
 */
function createDayOfWeekChart(canvas) {
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    if (data.length === 0) return;

    const labels = data.map(item => item.day_name);
    const counts = data.map(item => parseInt(item.citation_count || 0));

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Citations',
                data: counts,
                backgroundColor: 'rgba(111, 66, 193, 0.6)',
                borderColor: 'rgb(111, 66, 193)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Citations by Day of Week'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Create Monthly Chart
 */
function createMonthlyChart(canvas) {
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    if (data.length === 0) return;

    const labels = data.map(item => item.month);
    const counts = data.map(item => parseInt(item.citation_count || 0));
    const fines = data.map(item => parseFloat(item.total_fines || 0));

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Citations',
                data: counts,
                borderColor: 'rgb(13, 110, 253)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                yAxisID: 'y',
            }, {
                label: 'Total Fines (₱)',
                data: fines,
                borderColor: 'rgb(25, 135, 84)',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4,
                yAxisID: 'y1',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Trends'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

/**
 * Create Status Distribution Chart
 */
function createStatusChart(canvas) {
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    if (data.length === 0) return;

    const labels = data.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1));
    const counts = data.map(item => parseInt(item.count || 0));

    const statusColors = {
        'Paid': { bg: 'rgba(25, 135, 84, 0.6)', border: 'rgb(25, 135, 84)' },
        'Pending': { bg: 'rgba(255, 193, 7, 0.6)', border: 'rgb(255, 193, 7)' },
        'Contested': { bg: 'rgba(13, 202, 240, 0.6)', border: 'rgb(13, 202, 240)' },
        'Dismissed': { bg: 'rgba(108, 117, 125, 0.6)', border: 'rgb(108, 117, 125)' }
    };

    const backgroundColors = labels.map(label => statusColors[label]?.bg || 'rgba(13, 110, 253, 0.6)');
    const borderColors = labels.map(label => statusColors[label]?.border || 'rgb(13, 110, 253)');

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'Status Distribution'
                }
            }
        }
    });
}

/**
 * Create Vehicle Type Chart
 */
function createVehicleChart(canvas) {
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    if (data.length === 0) return;

    const labels = data.map(item => item.vehicle_type || 'Unknown');
    const counts = data.map(item => parseInt(item.citation_count || 0));
    const colors = generateColors(data.length);

    new Chart(canvas, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: counts,
                backgroundColor: colors.background,
                borderColor: colors.border,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'Citations by Vehicle Type'
                }
            }
        }
    });
}

/**
 * Create Officer Performance Chart
 */
function createOfficerChart(canvas) {
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    if (data.length === 0) return;

    const labels = data.map(item => item.officer_name);
    const counts = data.map(item => parseInt(item.citation_count || 0));

    new Chart(canvas, {
        type: 'horizontalBar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Citations Issued',
                data: counts,
                backgroundColor: 'rgba(13, 110, 253, 0.6)',
                borderColor: 'rgb(13, 110, 253)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: 'Officer Performance'
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Generate colors for charts
 */
function generateColors(count) {
    const baseColors = [
        { bg: 'rgba(13, 110, 253, 0.6)', border: 'rgb(13, 110, 253)' },
        { bg: 'rgba(25, 135, 84, 0.6)', border: 'rgb(25, 135, 84)' },
        { bg: 'rgba(255, 193, 7, 0.6)', border: 'rgb(255, 193, 7)' },
        { bg: 'rgba(220, 53, 69, 0.6)', border: 'rgb(220, 53, 69)' },
        { bg: 'rgba(13, 202, 240, 0.6)', border: 'rgb(13, 202, 240)' },
        { bg: 'rgba(111, 66, 193, 0.6)', border: 'rgb(111, 66, 193)' },
        { bg: 'rgba(253, 126, 20, 0.6)', border: 'rgb(253, 126, 20)' },
        { bg: 'rgba(214, 51, 132, 0.6)', border: 'rgb(214, 51, 132)' }
    ];

    const background = [];
    const border = [];

    for (let i = 0; i < count; i++) {
        const color = baseColors[i % baseColors.length];
        background.push(color.bg);
        border.push(color.border);
    }

    return { background, border };
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Format number
 */
function formatNumber(number) {
    return parseInt(number).toLocaleString('en-PH');
}
