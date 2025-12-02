# Second/Third Offense Tracking Implementation Guide

## Current System Analysis
The current system tracks offenses by matching drivers based on:
1. **License Number** (primary identifier)
2. **Driver ID** (when pre-filled from search)

**Problem**: When drivers provide incorrect information (different names, addresses, etc.), the system creates new driver records, breaking offense count continuity.

## Recommended Solutions (Priority Order)

### 1. Primary: License Number Matching ⭐ MOST RELIABLE
- **Current State**: Already implemented
- **Strength**: License numbers are consistent even if names are misspelled
- **Limitation**: Drivers without licenses or those providing fake numbers

### 2. Secondary: Vehicle/Plate Number Matching
- **Rationale**: Same vehicle = likely same driver
- **Implementation**: When no license match found, search by `plate_mv_engine_chassis_no`
- **Benefit**: Even if driver lies about name, they can't hide the vehicle plate
- **Logic**: Track offense history per vehicle, not just per driver

### 3. Fuzzy Name Matching + Date of Birth
- **Algorithm**: Use Soundex or Levenshtein distance for name similarity
- **Examples**:
  - "Juan Dela Cruz" vs "JUAN DE LA CRUZ" vs "Juan D. Cruz"
- **Strength**: Combine with DOB for stronger matching confidence
- **UI**: Show warning: "Possible duplicate found - similar name & same birthday"

### 4. Real-Time Duplicate Detection (Best UX)
- **Implementation**: AJAX search runs as officer enters data
- **UI**: Warning modal: "We found similar records..."
- **Options**: Officer chooses "Use existing driver" or "Create new"
- **Benefit**: Display offense history immediately for decision making

### 5. Administrative Tools
- **Driver Merge Feature**: Link duplicate records manually
- **Audit Trail**: Track when drivers are merged
- **Reports**: Flag suspicious patterns (same vehicle, different drivers)

## Implementation Plan

### Phase 1: Enhanced Driver Matching (High Priority)

#### 1. Add Functions to `includes/functions.php`:
```php
function find_similar_drivers($name, $dob, $license, $plate, $barangay) {
    $pdo = getPDO();
    $matches = [];

    // Exact license match (highest priority)
    if (!empty($license)) {
        $stmt = $pdo->prepare("SELECT * FROM drivers WHERE license_number = ?");
        $stmt->execute([$license]);
        if ($driver = $stmt->fetch()) {
            $matches[] = ['driver' => $driver, 'confidence' => 100, 'reason' => 'License Match'];
        }
    }

    // Vehicle plate match (high priority)
    if (!empty($plate)) {
        $stmt = $pdo->prepare("
            SELECT d.*, COUNT(c.citation_id) as citation_count
            FROM drivers d
            JOIN citations c ON d.driver_id = c.driver_id
            WHERE c.plate_mv_engine_chassis_no = ?
            GROUP BY d.driver_id
            ORDER BY citation_count DESC
        ");
        $stmt->execute([$plate]);

        while ($driver = $stmt->fetch()) {
            $matches[] = ['driver' => $driver, 'confidence' => 80, 'reason' => 'Vehicle Match'];
        }
    }

    // Fuzzy name + DOB match (medium priority)
    if (!empty($name) && !empty($dob)) {
        $matches = array_merge($matches, fuzzy_name_match($name, $dob, $barangay));
    }

    return array_slice($matches, 0, 5); // Return top 5 matches
}

function fuzzy_name_match($full_name, $dob, $barangay) {
    $pdo = getPDO();
    $matches = [];

    // Simple fuzzy matching - split name and search
    $name_parts = explode(' ', strtoupper($full_name));
    $last_name = $name_parts[0] ?? '';
    $first_name = $name_parts[1] ?? '';

    if (!empty($last_name) && !empty($first_name)) {
        $stmt = $pdo->prepare("
            SELECT *,
                   (LEVENSHTEIN_RATIO(UPPER(last_name), ?) +
                    LEVENSHTEIN_RATIO(UPPER(first_name), ?)) / 2 as name_similarity
            FROM drivers
            WHERE date_of_birth = ?
               OR barangay = ?
            HAVING name_similarity > 70
            ORDER BY name_similarity DESC
            LIMIT 3
        ");

        // Note: LEVENSHTEIN_RATIO is a MySQL function that may need to be installed
        // Alternative: Use PHP's similar_text() function
        $stmt->execute([$last_name, $first_name, $dob, $barangay]);

        while ($driver = $stmt->fetch()) {
            $confidence = min(90, 50 + ($driver['name_similarity'] ?? 50));
            $matches[] = [
                'driver' => $driver,
                'confidence' => $confidence,
                'reason' => 'Similar Name & DOB'
            ];
        }
    }

    return $matches;
}
```

#### 2. Modify `api/insert_citation.php`:
```php
// Before creating new driver, check for matches
$driver_matches = find_similar_drivers(
    $data['last_name'] . ' ' . $data['first_name'],
    $data['date_of_birth'] ?? null,
    $data['license_number'] ?? null,
    $data['plate_mv_engine_chassis_no'],
    $data['barangay']
);

// If high-confidence matches found, return them for user decision
$high_confidence_matches = array_filter($driver_matches, function($match) {
    return $match['confidence'] >= 80;
});

if (!empty($high_confidence_matches)) {
    echo json_encode([
        'status' => 'matches_found',
        'matches' => $high_confidence_matches,
        'message' => 'Potential duplicate drivers found. Please review before proceeding.'
    ]);
    exit;
}

// Continue with existing logic if no matches or low confidence
```

#### 3. Update `public/index2.php` - Add Duplicate Detection UI:
```html
<!-- Add after form opening -->
<div class="modal fade" id="duplicateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Possible Duplicate Driver Found
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">We found similar driver records. Please review and choose the correct action:</p>

                <div id="duplicateList" class="mb-3">
                    <!-- Matches will be populated here -->
                </div>

                <div class="alert alert-info">
                    <strong>Tip:</strong> Using an existing driver record ensures accurate offense tracking and prevents duplicate entries.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-plus me-1"></i>Create New Driver Anyway
                </button>
                <button type="button" class="btn btn-primary" id="useExistingDriver" disabled>
                    <i class="fas fa-check me-1"></i>Use Selected Driver
                </button>
            </div>
        </div>
    </div>
</div>
```

#### 4. Add JavaScript for Duplicate Handling:
```javascript
// Add to existing JavaScript in index2.php
let selectedDriverId = null;

document.getElementById('citationForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Check for duplicates before submission
    checkForDuplicates().then(hasDuplicates => {
        if (hasDuplicates) {
            // Show duplicate modal instead of submitting
            return;
        }

        // Proceed with normal submission
        submitForm();
    });
});

async function checkForDuplicates() {
    const formData = new FormData(document.getElementById('citationForm'));

    try {
        const response = await fetch('../api/check_duplicates.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.status === 'matches_found') {
            showDuplicateModal(result.matches);
            return true; // Has duplicates, don't submit yet
        }

        return false; // No duplicates, can proceed
    } catch (error) {
        console.error('Duplicate check failed:', error);
        return false;
    }
}

function showDuplicateModal(matches) {
    const duplicateList = document.getElementById('duplicateList');
    duplicateList.innerHTML = '';

    matches.forEach((match, index) => {
        const driver = match.driver;
        const card = document.createElement('div');
        card.className = `card mb-2 ${index === 0 ? 'border-primary' : ''}`;
        card.innerHTML = `
            <div class="card-body p-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="selectedDriver"
                           value="${driver.driver_id}" id="driver_${driver.driver_id}"
                           ${index === 0 ? 'checked' : ''}>
                    <label class="form-check-label w-100" for="driver_${driver.driver_id}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${driver.last_name}, ${driver.first_name}</strong>
                                ${driver.license_number ? `<br><small class="text-muted">License: ${driver.license_number}</small>` : ''}
                                <br><small class="text-muted">${driver.barangay}, ${driver.municipality}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-${match.confidence >= 90 ? 'success' : match.confidence >= 80 ? 'warning' : 'secondary'}">
                                    ${match.confidence}% match
                                </span>
                                <br><small class="text-muted">${match.reason}</small>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        `;
        duplicateList.appendChild(card);
    });

    const modal = new bootstrap.Modal(document.getElementById('duplicateModal'));
    modal.show();

    // Handle driver selection
    document.querySelectorAll('input[name="selectedDriver"]').forEach(radio => {
        radio.addEventListener('change', function() {
            selectedDriverId = this.value;
            document.getElementById('useExistingDriver').disabled = false;
        });
    });

    // Handle "Use Existing Driver" button
    document.getElementById('useExistingDriver').addEventListener('click', function() {
        if (selectedDriverId) {
            // Pre-fill form with selected driver data
            loadDriverData(selectedDriverId);
            modal.hide();
        }
    });
}

async function loadDriverData(driverId) {
    try {
        const response = await fetch(`../api/driver_get.php?driver_id=${driverId}`);
        const driver = await response.json();

        // Pre-fill form fields
        document.querySelector('[name="last_name"]').value = driver.last_name || '';
        document.querySelector('[name="first_name"]').value = driver.first_name || '';
        document.querySelector('[name="middle_initial"]').value = driver.middle_initial || '';
        document.querySelector('[name="suffix"]').value = driver.suffix || '';
        document.querySelector('[name="date_of_birth"]').value = driver.date_of_birth || '';
        document.querySelector('[name="barangay"]').value = driver.barangay || '';
        document.querySelector('[name="license_number"]').value = driver.license_number || '';

        // Trigger age calculation and other dynamic updates
        document.querySelector('[name="date_of_birth"]').dispatchEvent(new Event('change'));
        document.querySelector('[name="barangay"]').dispatchEvent(new Event('change'));

        // Show license fields if license exists
        if (driver.license_number) {
            document.getElementById('hasLicense').checked = true;
            document.getElementById('hasLicense').dispatchEvent(new Event('change'));
        }

    } catch (error) {
        console.error('Failed to load driver data:', error);
    }
}
```

#### 5. Create New API Endpoint: `api/check_duplicates.php`:
```php
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $data = sanitize($_POST);

    // Build search criteria
    $search_name = trim(($data['last_name'] ?? '') . ' ' . ($data['first_name'] ?? ''));
    $search_criteria = [
        'name' => $search_name,
        'dob' => $data['date_of_birth'] ?? null,
        'license' => $data['license_number'] ?? null,
        'plate' => $data['plate_mv_engine_chassis_no'] ?? null,
        'barangay' => $data['barangay'] ?? null
    ];

    $matches = find_similar_drivers(
        $search_criteria['name'],
        $search_criteria['dob'],
        $search_criteria['license'],
        $search_criteria['plate'],
        $search_criteria['barangay']
    );

    // Filter for high-confidence matches only
    $high_confidence_matches = array_filter($matches, function($match) {
        return $match['confidence'] >= 75; // Lower threshold for duplicate checking
    });

    if (!empty($high_confidence_matches)) {
        echo json_encode([
            'status' => 'matches_found',
            'matches' => array_values($high_confidence_matches)
        ]);
    } else {
        echo json_encode([
            'status' => 'no_matches',
            'message' => 'No similar drivers found'
        ]);
    }

} catch (Exception $e) {
    error_log("Duplicate check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to check for duplicates'
    ]);
}
?>
```

### Phase 2: Vehicle-Based Tracking (Medium Priority)

#### 1. Database Enhancement:
```sql
-- Add vehicle history tracking table
CREATE TABLE IF NOT EXISTS vehicle_offense_history (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    plate_mv_engine_chassis_no VARCHAR(100) NOT NULL,
    driver_name VARCHAR(255) NULL, -- For cases where driver info is inconsistent
    last_offense_date DATETIME NOT NULL,
    total_offenses INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_plate (plate_mv_engine_chassis_no),
    INDEX idx_date (last_offense_date)
) ENGINE=InnoDB;
```

#### 2. Update Citation Insertion Logic:
```php
// In insert_citation.php, after successful citation creation
update_vehicle_history($data['plate_mv_engine_chassis_no'], $data['last_name'] . ' ' . $data['first_name']);
```

#### 3. Add Vehicle History Function:
```php
function update_vehicle_history($plate_number, $driver_name) {
    $pdo = getPDO();

    // Check if vehicle exists
    $stmt = $pdo->prepare("SELECT * FROM vehicle_offense_history WHERE plate_mv_engine_chassis_no = ?");
    $stmt->execute([$plate_number]);
    $vehicle = $stmt->fetch();

    if ($vehicle) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE vehicle_offense_history
            SET driver_name = ?, last_offense_date = NOW(), total_offenses = total_offenses + 1, updated_at = NOW()
            WHERE vehicle_id = ?
        ");
        $stmt->execute([$driver_name, $vehicle['vehicle_id']]);
    } else {
        // Create new record
        $stmt = $pdo->prepare("
            INSERT INTO vehicle_offense_history (plate_mv_engine_chassis_no, driver_name, last_offense_date, total_offenses)
            VALUES (?, ?, NOW(), 1)
        ");
        $stmt->execute([$plate_number, $driver_name]);
    }
}
```

### Phase 3: Admin Consolidation Tools (Low Priority)

#### 1. Create Admin Interface: `admin/driver_consolidation.php`:
```php
<?php
// Admin interface for merging duplicate drivers
require_once '../includes/auth.php';
require_admin(); // Custom function to check admin role

// Display potential duplicates and merge interface
// Implementation details would include:
// - List drivers with similar names/licenses
// - Allow admin to select primary driver
// - Merge citations and update references
// - Log all merge operations
?>
```

## Benefits of Implementation
1. **Accurate Offense Tracking**: Prevents drivers from avoiding penalties by lying
2. **Data Integrity**: Reduces duplicate driver records
3. **Officer Efficiency**: Real-time feedback helps make correct decisions
4. **Audit Trail**: All driver merges and decisions are tracked
5. **Scalable**: System can handle growing driver databases

## Testing Scenarios
1. **License Match**: Driver provides same license, different name → Should match existing
2. **Vehicle Match**: Same plate, different driver info → Should flag for review
3. **Fuzzy Match**: Similar name + same DOB → Should suggest existing driver
4. **No Match**: Completely new driver → Should create new record
5. **Admin Merge**: Manual consolidation of duplicates → Should update all references

## Performance Considerations
- Add database indexes on frequently searched fields
- Implement caching for common duplicate checks
- Consider background processing for complex fuzzy matching
- Monitor query performance and optimize as needed
