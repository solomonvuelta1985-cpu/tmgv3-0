<?php
/**
 * Comprehensive Excel Data Analyzer & Inconsistency Checker
 * For Citation Ticket Import System
 */

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
set_time_limit(300);

$excelFile = __DIR__ . '/NEW 2023-2024 CITATION TICKET  (Responses) (24) (1).xlsx';

echo str_repeat("=", 100) . "\n";
echo "COMPREHENSIVE CITATION DATA ANALYSIS & INCONSISTENCY REPORT\n";
echo str_repeat("=", 100) . "\n";
echo "File: " . basename($excelFile) . "\n";
echo "Analysis Date: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 100) . "\n\n";

// Load the spreadsheet
echo "Loading Excel file...\n";
$spreadsheet = IOFactory::load($excelFile);
$worksheet = $spreadsheet->getActiveSheet();

// Get the highest row and column
$highestRow = $worksheet->getHighestRow();
$highestColumn = $worksheet->getHighestColumn();
$highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

echo "✓ Excel file loaded successfully\n";
echo "  Total Rows: " . number_format($highestRow) . "\n";
echo "  Total Columns: " . $highestColumnIndex . " ($highestColumn)\n";
echo "  Data Rows: " . number_format($highestRow - 1) . " (excluding header)\n\n";

// Extract headers
echo str_repeat("-", 100) . "\n";
echo "COLUMN HEADERS\n";
echo str_repeat("-", 100) . "\n";
$headers = [];
for ($col = 1; $col <= $highestColumnIndex; $col++) {
    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $cellValue = $worksheet->getCell($columnLetter . '1')->getValue();
    $headers[$col] = $cellValue;
    echo sprintf("Column %s [%2d]: %s\n", $columnLetter, $col, $cellValue);
}
echo "\n";

// Database schema mapping
$dbMapping = [
    'TICKET NUMBER' => 'ticket_number',
    'LAST NAME' => 'last_name',
    'FIRST NAME' => 'first_name',
    'MIDDLE INITIAL' => 'middle_initial',
    'ADDRESS (Barangay)' => 'barangay',
    'ZONE' => 'zone',
    'LICENSE NUMBER' => 'license_number',
    'PLATE NUMBER or ENGINE/CHASSIS #' => 'plate_mv_engine_chassis_no',
    'VEHICLE TYPE' => 'vehicle_type',
    'VEHICLE DESCRIPTION' => 'vehicle_description',
    'DATE APREHENDED' => 'apprehension_datetime',
    'TIME OF APPREHENSION' => 'apprehension_time',
    'PLACE OF APPREHENSION' => 'place_of_apprehension',
    'VIOLATION/S' => 'violations',
    'NAME OF APPREHENDING OFFICER' => 'apprehending_officer',
    'REMARKS' => 'remarks'
];

// Initialize analysis arrays
$issues = [
    'missing_ticket_number' => [],
    'duplicate_ticket_numbers' => [],
    'missing_required_fields' => [],
    'invalid_dates' => [],
    'invalid_times' => [],
    'missing_violations' => [],
    'long_values' => [],
    'suspicious_data' => [],
    'encoding_issues' => []
];

$statistics = [
    'total_records' => 0,
    'valid_records' => 0,
    'records_with_issues' => 0,
    'unique_violations' => [],
    'unique_vehicle_types' => [],
    'unique_barangays' => [],
    'unique_officers' => [],
    'ticket_numbers' => [],
    'date_range' => ['min' => null, 'max' => null]
];

echo str_repeat("=", 100) . "\n";
echo "ANALYZING DATA...\n";
echo str_repeat("=", 100) . "\n";

// Process each row
for ($row = 2; $row <= $highestRow; $row++) {
    $statistics['total_records']++;
    $rowHasIssues = false;
    $rowData = [];

    // Extract row data
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $cell = $worksheet->getCell($columnLetter . $row);
        $value = $cell->getValue();

        // Handle date/time columns
        if ($headers[$col] === 'DATE APREHENDED' || $headers[$col] === 'Timestamp') {
            if (is_numeric($value)) {
                try {
                    $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                } catch (Exception $e) {
                    $value = null;
                }
            }
        } elseif ($headers[$col] === 'TIME OF APPREHENSION') {
            if (is_numeric($value)) {
                try {
                    $value = Date::excelToDateTimeObject($value)->format('H:i:s');
                } catch (Exception $e) {
                    $value = null;
                }
            }
        }

        $rowData[$headers[$col]] = $value;
    }

    // VALIDATION CHECKS

    // 1. Check for missing ticket number
    $ticketNumber = trim($rowData['TICKET NUMBER'] ?? '');
    if (empty($ticketNumber)) {
        $issues['missing_ticket_number'][] = $row;
        $rowHasIssues = true;
    } else {
        // Check for duplicates
        if (isset($statistics['ticket_numbers'][$ticketNumber])) {
            $issues['duplicate_ticket_numbers'][$ticketNumber][] = $row;
            $rowHasIssues = true;
        }
        $statistics['ticket_numbers'][$ticketNumber] = $row;
    }

    // 2. Check for missing required fields
    $requiredFields = ['LAST NAME', 'FIRST NAME', 'ADDRESS (Barangay)', 'PLATE NUMBER or ENGINE/CHASSIS #'];
    foreach ($requiredFields as $field) {
        if (empty(trim($rowData[$field] ?? ''))) {
            $issues['missing_required_fields'][] = [
                'row' => $row,
                'field' => $field,
                'ticket' => $ticketNumber
            ];
            $rowHasIssues = true;
        }
    }

    // 3. Check for invalid dates
    $dateValue = $rowData['DATE APREHENDED'] ?? '';
    if (empty($dateValue) || $dateValue === null) {
        $issues['invalid_dates'][] = [
            'row' => $row,
            'ticket' => $ticketNumber,
            'value' => $worksheet->getCell('L' . $row)->getValue()
        ];
        $rowHasIssues = true;
    } else {
        // Track date range
        if ($statistics['date_range']['min'] === null || $dateValue < $statistics['date_range']['min']) {
            $statistics['date_range']['min'] = $dateValue;
        }
        if ($statistics['date_range']['max'] === null || $dateValue > $statistics['date_range']['max']) {
            $statistics['date_range']['max'] = $dateValue;
        }
    }

    // 4. Check for missing violations
    $violation = trim($rowData['VIOLATION/S'] ?? '');
    if (empty($violation)) {
        $issues['missing_violations'][] = [
            'row' => $row,
            'ticket' => $ticketNumber
        ];
        $rowHasIssues = true;
    } else {
        $statistics['unique_violations'][$violation] = ($statistics['unique_violations'][$violation] ?? 0) + 1;
    }

    // 5. Check for unusually long values (potential data issues)
    foreach ($rowData as $field => $value) {
        if (strlen($value) > 255 && $field !== 'REMARKS') {
            $issues['long_values'][] = [
                'row' => $row,
                'field' => $field,
                'length' => strlen($value),
                'ticket' => $ticketNumber
            ];
            $rowHasIssues = true;
        }
    }

    // 6. Collect statistics
    $vehicleType = trim($rowData['VEHICLE TYPE'] ?? '');
    if (!empty($vehicleType)) {
        $statistics['unique_vehicle_types'][$vehicleType] = ($statistics['unique_vehicle_types'][$vehicleType] ?? 0) + 1;
    }

    $barangay = trim($rowData['ADDRESS (Barangay)'] ?? '');
    if (!empty($barangay)) {
        $statistics['unique_barangays'][$barangay] = ($statistics['unique_barangays'][$barangay] ?? 0) + 1;
    }

    $officer = trim($rowData['NAME OF APPREHENDING OFFICER'] ?? '');
    if (!empty($officer)) {
        $statistics['unique_officers'][$officer] = ($statistics['unique_officers'][$officer] ?? 0) + 1;
    }

    if (!$rowHasIssues) {
        $statistics['valid_records']++;
    } else {
        $statistics['records_with_issues']++;
    }

    // Progress indicator
    if ($row % 1000 == 0) {
        echo "  Processed: " . number_format($row - 1) . " records...\n";
    }
}

echo "✓ Analysis complete: " . number_format($statistics['total_records']) . " records analyzed\n\n";

// GENERATE REPORT
echo str_repeat("=", 100) . "\n";
echo "ANALYSIS RESULTS\n";
echo str_repeat("=", 100) . "\n\n";

echo "SUMMARY STATISTICS:\n";
echo str_repeat("-", 100) . "\n";
echo sprintf("Total Records:              %s\n", number_format($statistics['total_records']));
echo sprintf("Valid Records:              %s (%d%%)\n",
    number_format($statistics['valid_records']),
    round($statistics['valid_records'] / $statistics['total_records'] * 100));
echo sprintf("Records with Issues:        %s (%d%%)\n",
    number_format($statistics['records_with_issues']),
    round($statistics['records_with_issues'] / $statistics['total_records'] * 100));
echo sprintf("Unique Ticket Numbers:      %s\n", number_format(count($statistics['ticket_numbers'])));
echo sprintf("Unique Violations:          %s\n", number_format(count($statistics['unique_violations'])));
echo sprintf("Unique Vehicle Types:       %s\n", number_format(count($statistics['unique_vehicle_types'])));
echo sprintf("Unique Barangays:           %s\n", number_format(count($statistics['unique_barangays'])));
echo sprintf("Unique Officers:            %s\n", number_format(count($statistics['unique_officers'])));
echo sprintf("Date Range:                 %s to %s\n",
    $statistics['date_range']['min'] ?? 'N/A',
    $statistics['date_range']['max'] ?? 'N/A');
echo "\n";

// DATA INCONSISTENCIES
echo str_repeat("=", 100) . "\n";
echo "DATA INCONSISTENCIES FOUND\n";
echo str_repeat("=", 100) . "\n\n";

// 1. Missing Ticket Numbers
if (!empty($issues['missing_ticket_number'])) {
    echo "❌ CRITICAL: Missing Ticket Numbers\n";
    echo "   Count: " . count($issues['missing_ticket_number']) . " records\n";
    echo "   Rows: " . implode(', ', array_slice($issues['missing_ticket_number'], 0, 20));
    if (count($issues['missing_ticket_number']) > 20) {
        echo " ... and " . (count($issues['missing_ticket_number']) - 20) . " more";
    }
    echo "\n\n";
} else {
    echo "✓ No missing ticket numbers\n\n";
}

// 2. Duplicate Ticket Numbers
if (!empty($issues['duplicate_ticket_numbers'])) {
    echo "❌ CRITICAL: Duplicate Ticket Numbers\n";
    echo "   Count: " . count($issues['duplicate_ticket_numbers']) . " unique ticket numbers are duplicated\n";
    echo "   Details:\n";
    $count = 0;
    foreach ($issues['duplicate_ticket_numbers'] as $ticket => $rows) {
        if ($count++ < 20) {
            echo "   - Ticket #$ticket appears in rows: " . implode(', ', $rows) . "\n";
        }
    }
    if (count($issues['duplicate_ticket_numbers']) > 20) {
        echo "   ... and " . (count($issues['duplicate_ticket_numbers']) - 20) . " more duplicates\n";
    }
    echo "\n";
} else {
    echo "✓ No duplicate ticket numbers\n\n";
}

// 3. Missing Required Fields
if (!empty($issues['missing_required_fields'])) {
    echo "❌ WARNING: Missing Required Fields\n";
    echo "   Count: " . count($issues['missing_required_fields']) . " instances\n";
    echo "   Sample (first 20):\n";
    foreach (array_slice($issues['missing_required_fields'], 0, 20) as $issue) {
        echo sprintf("   - Row %d (Ticket: %s): Missing '%s'\n",
            $issue['row'], $issue['ticket'], $issue['field']);
    }
    if (count($issues['missing_required_fields']) > 20) {
        echo "   ... and " . (count($issues['missing_required_fields']) - 20) . " more\n";
    }
    echo "\n";
} else {
    echo "✓ All required fields populated\n\n";
}

// 4. Invalid Dates
if (!empty($issues['invalid_dates'])) {
    echo "❌ WARNING: Invalid Dates\n";
    echo "   Count: " . count($issues['invalid_dates']) . " records\n";
    echo "   Sample (first 20):\n";
    foreach (array_slice($issues['invalid_dates'], 0, 20) as $issue) {
        echo sprintf("   - Row %d (Ticket: %s): Invalid date value: %s\n",
            $issue['row'], $issue['ticket'], $issue['value']);
    }
    if (count($issues['invalid_dates']) > 20) {
        echo "   ... and " . (count($issues['invalid_dates']) - 20) . " more\n";
    }
    echo "\n";
} else {
    echo "✓ All dates valid\n\n";
}

// 5. Missing Violations
if (!empty($issues['missing_violations'])) {
    echo "❌ WARNING: Missing Violations\n";
    echo "   Count: " . count($issues['missing_violations']) . " records\n";
    echo "   Sample (first 20):\n";
    foreach (array_slice($issues['missing_violations'], 0, 20) as $issue) {
        echo sprintf("   - Row %d (Ticket: %s)\n", $issue['row'], $issue['ticket']);
    }
    if (count($issues['missing_violations']) > 20) {
        echo "   ... and " . (count($issues['missing_violations']) - 20) . " more\n";
    }
    echo "\n";
} else {
    echo "✓ All violations specified\n\n";
}

// 6. Long Values
if (!empty($issues['long_values'])) {
    echo "⚠ INFO: Unusually Long Values (may need truncation)\n";
    echo "   Count: " . count($issues['long_values']) . " instances\n";
    echo "   Sample (first 10):\n";
    foreach (array_slice($issues['long_values'], 0, 10) as $issue) {
        echo sprintf("   - Row %d (Ticket: %s): Field '%s' has %d characters\n",
            $issue['row'], $issue['ticket'], $issue['field'], $issue['length']);
    }
    if (count($issues['long_values']) > 10) {
        echo "   ... and " . (count($issues['long_values']) - 10) . " more\n";
    }
    echo "\n";
}

// TOP VIOLATIONS
echo str_repeat("=", 100) . "\n";
echo "TOP 20 VIOLATIONS\n";
echo str_repeat("=", 100) . "\n";
arsort($statistics['unique_violations']);
$count = 0;
foreach ($statistics['unique_violations'] as $violation => $freq) {
    if ($count++ < 20) {
        echo sprintf("%4d. %-70s (%s occurrences)\n", $count, substr($violation, 0, 70), number_format($freq));
    }
}
echo "\n";

// TOP VEHICLE TYPES
echo str_repeat("=", 100) . "\n";
echo "TOP VEHICLE TYPES\n";
echo str_repeat("=", 100) . "\n";
arsort($statistics['unique_vehicle_types']);
$count = 0;
foreach ($statistics['unique_vehicle_types'] as $type => $freq) {
    if ($count++ < 20) {
        echo sprintf("%4d. %-50s (%s occurrences)\n", $count, substr($type, 0, 50), number_format($freq));
    }
}
echo "\n";

// TOP BARANGAYS
echo str_repeat("=", 100) . "\n";
echo "TOP BARANGAYS\n";
echo str_repeat("=", 100) . "\n";
arsort($statistics['unique_barangays']);
$count = 0;
foreach ($statistics['unique_barangays'] as $barangay => $freq) {
    if ($count++ < 20) {
        echo sprintf("%4d. %-50s (%s occurrences)\n", $count, substr($barangay, 0, 50), number_format($freq));
    }
}
echo "\n";

// RECOMMENDATIONS
echo str_repeat("=", 100) . "\n";
echo "RECOMMENDATIONS\n";
echo str_repeat("=", 100) . "\n";

$criticalIssues = count($issues['missing_ticket_number']) + count($issues['duplicate_ticket_numbers']);
$warnings = count($issues['missing_required_fields']) + count($issues['invalid_dates']) + count($issues['missing_violations']);

if ($criticalIssues > 0) {
    echo "❌ CRITICAL ISSUES FOUND: $criticalIssues records\n";
    echo "   You must fix these before importing:\n";
    if (!empty($issues['missing_ticket_number'])) {
        echo "   1. Add ticket numbers to " . count($issues['missing_ticket_number']) . " records\n";
    }
    if (!empty($issues['duplicate_ticket_numbers'])) {
        echo "   2. Resolve " . count($issues['duplicate_ticket_numbers']) . " duplicate ticket numbers\n";
    }
    echo "\n";
}

if ($warnings > 0) {
    echo "⚠ WARNINGS: $warnings records with non-critical issues\n";
    echo "   These can be imported with default values, but review is recommended:\n";
    if (!empty($issues['missing_required_fields'])) {
        echo "   - " . count($issues['missing_required_fields']) . " missing required fields\n";
    }
    if (!empty($issues['invalid_dates'])) {
        echo "   - " . count($issues['invalid_dates']) . " invalid dates\n";
    }
    if (!empty($issues['missing_violations'])) {
        echo "   - " . count($issues['missing_violations']) . " missing violations\n";
    }
    echo "\n";
}

if ($criticalIssues == 0 && $warnings == 0) {
    echo "✓ DATA QUALITY: EXCELLENT\n";
    echo "  All records are ready for import!\n\n";
} elseif ($criticalIssues == 0) {
    echo "✓ DATA QUALITY: GOOD\n";
    echo "  Data can be imported with minor cleanup during import process.\n\n";
} else {
    echo "❌ DATA QUALITY: NEEDS ATTENTION\n";
    echo "  Please fix critical issues before proceeding with import.\n\n";
}

echo str_repeat("=", 100) . "\n";
echo "NEXT STEPS\n";
echo str_repeat("=", 100) . "\n";
echo "1. Review and fix critical issues (if any)\n";
echo "2. Verify violation names match database violation types\n";
echo "3. Run the import script with data cleaning enabled\n";
echo "4. Test with a small batch first (e.g., 100 records)\n";
echo "5. Review imported data before proceeding with full import\n";
echo str_repeat("=", 100) . "\n";

// Save detailed report to file
$reportFile = __DIR__ . '/excel_analysis_report_' . date('Y-m-d_His') . '.txt';
ob_start();
// (The report is already being echoed, so we'll save what's been output)
file_put_contents($reportFile, ob_get_flush());

echo "\n✓ Detailed report saved to: " . basename($reportFile) . "\n";
