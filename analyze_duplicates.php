<?php
/**
 * Duplicate Ticket Number Analyzer
 * Generates detailed report of all duplicate tickets for manual review
 */

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');

$excelFile = __DIR__ . '/NEW 2023-2024 CITATION TICKET  (Responses) (24) (1).xlsx';

echo str_repeat("=", 120) . "\n";
echo "DUPLICATE TICKET NUMBER DETAILED ANALYSIS\n";
echo str_repeat("=", 120) . "\n\n";

// Load the spreadsheet
$spreadsheet = IOFactory::load($excelFile);
$worksheet = $spreadsheet->getActiveSheet();
$highestRow = $worksheet->getHighestRow();

// Extract headers
$headers = [];
for ($col = 1; $col <= 17; $col++) {
    $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    $headers[$col] = $worksheet->getCell($columnLetter . '1')->getValue();
}

// Collect all records with ticket numbers
$allRecords = [];
$ticketIndex = [];
$emptyTicketRows = [];

for ($row = 2; $row <= $highestRow; $row++) {
    $ticketNumber = trim($worksheet->getCell('B' . $row)->getValue() ?? '');

    if (empty($ticketNumber)) {
        $emptyTicketRows[] = $row;
        continue;
    }

    $record = [
        'row' => $row,
        'ticket' => $ticketNumber,
        'last_name' => trim($worksheet->getCell('C' . $row)->getValue() ?? ''),
        'first_name' => trim($worksheet->getCell('D' . $row)->getValue() ?? ''),
        'middle_initial' => trim($worksheet->getCell('E' . $row)->getValue() ?? ''),
        'barangay' => trim($worksheet->getCell('F' . $row)->getValue() ?? ''),
        'license' => trim($worksheet->getCell('H' . $row)->getValue() ?? ''),
        'plate' => trim($worksheet->getCell('I' . $row)->getValue() ?? ''),
        'vehicle_type' => trim($worksheet->getCell('J' . $row)->getValue() ?? ''),
        'date_raw' => $worksheet->getCell('L' . $row)->getValue(),
        'place' => trim($worksheet->getCell('N' . $row)->getValue() ?? ''),
        'violation' => trim($worksheet->getCell('O' . $row)->getValue() ?? ''),
        'officer' => trim($worksheet->getCell('P' . $row)->getValue() ?? ''),
    ];

    // Format date
    if (is_numeric($record['date_raw'])) {
        try {
            $record['date'] = Date::excelToDateTimeObject($record['date_raw'])->format('Y-m-d');
        } catch (Exception $e) {
            $record['date'] = 'INVALID';
        }
    } else {
        $record['date'] = $record['date_raw'] ?? 'EMPTY';
    }

    $allRecords[] = $record;

    if (!isset($ticketIndex[$ticketNumber])) {
        $ticketIndex[$ticketNumber] = [];
    }
    $ticketIndex[$ticketNumber][] = count($allRecords) - 1;
}

// Find duplicates
$duplicates = array_filter($ticketIndex, function($rows) {
    return count($rows) > 1;
});

echo "SUMMARY:\n";
echo str_repeat("-", 120) . "\n";
echo sprintf("Total records with ticket numbers: %s\n", number_format(count($allRecords)));
echo sprintf("Unique ticket numbers: %s\n", number_format(count($ticketIndex)));
echo sprintf("Empty ticket numbers: %s (rows: %s)\n", count($emptyTicketRows), implode(', ', $emptyTicketRows));
echo sprintf("Duplicate ticket numbers: %s\n\n", number_format(count($duplicates)));

// Analyze duplicate patterns
$exactDuplicates = [];
$differentPeople = [];
$differentDates = [];
$differentViolations = [];

foreach ($duplicates as $ticket => $indices) {
    $records = array_map(function($idx) use ($allRecords) {
        return $allRecords[$idx];
    }, $indices);

    // Check if exact duplicates (same person, date, violation)
    $first = $records[0];
    $isExactDuplicate = true;
    $hasDifferentPeople = false;
    $hasDifferentDates = false;
    $hasDifferentViolations = false;

    foreach ($records as $rec) {
        if ($rec['last_name'] !== $first['last_name'] || $rec['first_name'] !== $first['first_name']) {
            $hasDifferentPeople = true;
            $isExactDuplicate = false;
        }
        if ($rec['date'] !== $first['date']) {
            $hasDifferentDates = true;
            $isExactDuplicate = false;
        }
        if ($rec['violation'] !== $first['violation']) {
            $hasDifferentViolations = true;
            $isExactDuplicate = false;
        }
    }

    if ($isExactDuplicate) {
        $exactDuplicates[$ticket] = $records;
    }
    if ($hasDifferentPeople) {
        $differentPeople[$ticket] = $records;
    }
    if ($hasDifferentDates) {
        $differentDates[$ticket] = $records;
    }
    if ($hasDifferentViolations) {
        $differentViolations[$ticket] = $records;
    }
}

echo "DUPLICATE ANALYSIS:\n";
echo str_repeat("-", 120) . "\n";
echo sprintf("Exact duplicates (same person, date, violation): %d tickets\n", count($exactDuplicates));
echo sprintf("Same ticket but different people: %d tickets\n", count($differentPeople));
echo sprintf("Same ticket but different dates: %d tickets\n", count($differentDates));
echo sprintf("Same ticket but different violations: %d tickets\n\n", count($differentViolations));

// Generate detailed report
echo str_repeat("=", 120) . "\n";
echo "DETAILED DUPLICATE REPORT\n";
echo str_repeat("=", 120) . "\n\n";

$count = 0;
foreach ($duplicates as $ticket => $indices) {
    $count++;
    $records = array_map(function($idx) use ($allRecords) {
        return $allRecords[$idx];
    }, $indices);

    echo sprintf("[%d] TICKET #%s - %d occurrences\n", $count, $ticket, count($records));
    echo str_repeat("-", 120) . "\n";

    foreach ($records as $idx => $rec) {
        $status = '';

        // Determine status
        $first = $records[0];
        if ($rec['last_name'] !== $first['last_name'] || $rec['first_name'] !== $first['first_name']) {
            $status .= '⚠ DIFFERENT PERSON ';
        }
        if ($rec['date'] !== $first['date']) {
            $status .= '⚠ DIFFERENT DATE ';
        }
        if ($rec['violation'] !== $first['violation']) {
            $status .= '⚠ DIFFERENT VIOLATION ';
        }
        if (empty($status)) {
            $status = '✓ EXACT DUPLICATE (can auto-delete)';
        }

        echo sprintf("  [%d] Row %d: %s\n", $idx + 1, $rec['row'], $status);
        echo sprintf("      Name: %s, %s %s\n", $rec['last_name'], $rec['first_name'], $rec['middle_initial']);
        echo sprintf("      Barangay: %s | Plate: %s | License: %s\n", $rec['barangay'], $rec['plate'], $rec['license']);
        echo sprintf("      Date: %s | Place: %s\n", $rec['date'], substr($rec['place'], 0, 50));
        echo sprintf("      Violation: %s\n", substr($rec['violation'], 0, 80));
        echo sprintf("      Officer: %s\n", $rec['officer']);
        echo "\n";
    }

    echo "\n";

    if ($count >= 50) {
        echo "\n... (Showing first 50 duplicate groups. See full report in output file)\n\n";
        break;
    }
}

// Generate recommendations
echo str_repeat("=", 120) . "\n";
echo "RECOMMENDATIONS\n";
echo str_repeat("=", 120) . "\n\n";

echo "STRATEGY FOR HANDLING DUPLICATES:\n\n";

echo "1. EXACT DUPLICATES (" . count($exactDuplicates) . " tickets):\n";
echo "   - These are likely data entry errors (double submission)\n";
echo "   - SAFE TO AUTO-DELETE: Keep first occurrence, remove duplicates\n\n";

echo "2. DIFFERENT PEOPLE WITH SAME TICKET (" . count($differentPeople) . " tickets):\n";
echo "   - CRITICAL ERROR: One ticket number used for multiple different people!\n";
echo "   - ACTION REQUIRED: Generate new ticket numbers for duplicates\n";
echo "   - Suggested format: Original number + suffix (e.g., 010109-A, 010109-B)\n\n";

echo "3. DIFFERENT DATES (" . count($differentDates) . " tickets):\n";
echo "   - Could be legitimate (same person cited multiple times)\n";
echo "   - OR could be data entry error\n";
echo "   - MANUAL REVIEW RECOMMENDED\n\n";

echo "4. DIFFERENT VIOLATIONS (" . count($differentViolations) . " tickets):\n";
echo "   - Could be multiple violations on same ticket\n";
echo "   - OR data entry error\n";
echo "   - MANUAL REVIEW RECOMMENDED\n\n";

echo str_repeat("=", 120) . "\n";
echo "IMPORT STRATEGIES\n";
echo str_repeat("=", 120) . "\n\n";

echo "OPTION 1: CONSERVATIVE (Safest)\n";
echo "  - Skip ALL duplicate tickets\n";
echo "  - Manually fix duplicates in Excel first\n";
echo "  - Re-import cleaned data\n";
echo "  - Records imported: ~" . number_format(count($ticketIndex) - count($duplicates)) . "\n\n";

echo "OPTION 2: AUTO-CLEAN EXACT DUPLICATES (Recommended)\n";
echo "  - Auto-remove exact duplicates (keep first)\n";
echo "  - Skip problematic duplicates (different people/dates)\n";
echo "  - Generate report of skipped records for manual review\n";
echo "  - Records imported: ~" . number_format(count($ticketIndex) - count($differentPeople)) . "\n\n";

echo "OPTION 3: AUTO-FIX WITH NEW TICKET NUMBERS\n";
echo "  - Keep first occurrence with original ticket number\n";
echo "  - Generate new ticket numbers for duplicates (add -A, -B suffix)\n";
echo "  - Import all records with modifications\n";
echo "  - Records imported: ~" . number_format(count($allRecords)) . "\n\n";

echo "Which option would you like to use?\n";
echo str_repeat("=", 120) . "\n";

// Save to file
$reportFile = __DIR__ . '/duplicate_analysis_report.txt';
ob_start();
file_put_contents($reportFile, ob_get_contents());
ob_end_flush();

echo "\n✓ Full report saved to: duplicate_analysis_report.txt\n";
