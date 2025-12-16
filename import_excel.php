<?php
/**
 * Excel Import Command Line Script
 *
 * Usage:
 *   php import_excel.php [--dry-run] [--file=path]
 *
 * Options:
 *   --dry-run    Run analysis without importing to database
 *   --file       Path to Excel file (optional, uses default if not specified)
 */

require_once __DIR__ . '/services/ExcelImporter.php';

// Parse command line arguments
$options = getopt('', ['dry-run', 'file:']);
$dryRun = isset($options['dry-run']);
$excelFile = $options['file'] ?? __DIR__ . '/NEW 2023-2024 CITATION TICKET  (Responses) (24) (1).xlsx';

// Check if file exists
if (!file_exists($excelFile)) {
    die("ERROR: Excel file not found: $excelFile\n");
}

echo str_repeat("=", 80) . "\n";
echo "EXCEL CITATION IMPORT SYSTEM\n";
echo str_repeat("=", 80) . "\n";
echo "File: " . basename($excelFile) . "\n";
echo "Mode: " . ($dryRun ? "DRY RUN (No database changes)" : "LIVE IMPORT") . "\n";
echo str_repeat("=", 80) . "\n\n";

if ($dryRun) {
    echo "⚠️  DRY RUN MODE - No data will be imported to database\n";
    echo "   This will show you what WOULD happen without making changes\n\n";
}

echo "Press ENTER to continue, or Ctrl+C to cancel...";
fgets(STDIN);

echo "\nStarting import...\n\n";

// Create importer instance
$importer = new ExcelImporter($excelFile);

// Run import
$result = $importer->import($dryRun);

// Display results
echo "\n";
echo str_repeat("=", 80) . "\n";
echo "IMPORT " . ($result['success'] ? "COMPLETED" : "FAILED") . "\n";
echo str_repeat("=", 80) . "\n\n";

if ($result['success']) {
    $stats = $result['stats'];

    echo "SUMMARY:\n";
    echo str_repeat("-", 80) . "\n";
    echo sprintf("Total Excel Rows:         %s\n", number_format($stats['total_rows']));
    echo sprintf("Processed Successfully:   %s\n", number_format($stats['processed_rows']));
    echo sprintf("Skipped (Duplicates):     %s\n", number_format($stats['skipped_rows']));
    echo sprintf("Errors:                   %s\n", number_format($stats['error_rows']));
    echo "\n";

    echo "RESULTS:\n";
    echo str_repeat("-", 80) . "\n";
    echo sprintf("Citations Created:        %s\n", number_format($stats['citations_created']));
    echo sprintf("Drivers Created:          %s\n", number_format($stats['drivers_created']));
    echo sprintf("Drivers Matched:          %s\n", number_format($stats['drivers_matched']));
    echo sprintf("Violations Created:       %s\n", number_format($stats['violations_created']));
    echo sprintf("Duplicates Removed:       %s\n", number_format($stats['duplicates_removed']));
    echo sprintf("Tickets Generated:        %s\n", number_format($stats['tickets_generated']));
    echo "\n";

    echo "BATCH ID: {$result['batch_id']}\n\n";

    // Show warnings
    $report = $result['report'];

    if (!empty($report['auto_generated_tickets'])) {
        echo "⚠️  AUTO-GENERATED TICKETS (Need Manual Review):\n";
        echo str_repeat("-", 80) . "\n";
        foreach (array_slice($report['auto_generated_tickets'], 0, 10) as $ticket) {
            echo sprintf("   Row %4d: %s (%s)\n",
                $ticket['excel_row'],
                $ticket['final_ticket'],
                $ticket['generation_reason']
            );
        }
        if (count($report['auto_generated_tickets']) > 10) {
            echo "   ... and " . (count($report['auto_generated_tickets']) - 10) . " more\n";
        }
        echo "\n";
    }

    if (!empty($report['ticket_conflicts'])) {
        echo "⚠️  TICKET CONFLICTS RESOLVED:\n";
        echo str_repeat("-", 80) . "\n";
        foreach (array_slice($report['ticket_conflicts'], 0, 10) as $conflict) {
            echo sprintf("   Ticket %s: %s conflict - Generated: %s\n",
                $conflict['original_ticket'],
                $conflict['conflict_type'],
                $conflict['tickets_generated']
            );
        }
        if (count($report['ticket_conflicts']) > 10) {
            echo "   ... and " . (count($report['ticket_conflicts']) - 10) . " more\n";
        }
        echo "\n";
    }

    if (!empty($report['multi_violation_groups'])) {
        echo "✅ MULTI-VIOLATION CITATIONS:\n";
        echo str_repeat("-", 80) . "\n";
        foreach (array_slice($report['multi_violation_groups'], 0, 10) as $group) {
            echo sprintf("   Ticket %s: Combined %d Excel rows into 1 citation\n",
                $group['final_ticket'],
                $group['row_count']
            );
        }
        if (count($report['multi_violation_groups']) > 10) {
            echo "   ... and " . (count($report['multi_violation_groups']) - 10) . " more\n";
        }
        echo "\n";
    }

    if (!empty($report['fuzzy_matches'])) {
        echo "⚠️  FUZZY VIOLATION MATCHES (Verify Accuracy):\n";
        echo str_repeat("-", 80) . "\n";
        foreach (array_slice($report['fuzzy_matches'], 0, 10) as $match) {
            echo sprintf("   '%s' → '%s' (%s, %d%% confidence)\n",
                substr($match['excel_text'], 0, 40),
                substr($match['violation_type_name'], 0, 40),
                $match['match_type'],
                $match['match_confidence']
            );
        }
        if (count($report['fuzzy_matches']) > 10) {
            echo "   ... and " . (count($report['fuzzy_matches']) - 10) . " more\n";
        }
        echo "\n";
    }

    echo str_repeat("=", 80) . "\n";
    echo "NEXT STEPS:\n";
    echo str_repeat("=", 80) . "\n";
    echo "1. Review auto-generated tickets (AUT- prefix) and update with real numbers\n";
    echo "2. Verify ticket conflict resolutions (tickets with -A, -B suffixes)\n";
    echo "3. Check fuzzy violation matches for accuracy\n";
    echo "4. Run data integrity checks\n";
    echo "\n";

    echo "SQL QUERIES TO RUN:\n";
    echo str_repeat("-", 80) . "\n";
    echo "-- Find auto-generated tickets\n";
    echo "SELECT * FROM citations WHERE ticket_number LIKE 'AUT-%';\n\n";

    echo "-- Find conflict-resolved tickets\n";
    echo "SELECT * FROM citations WHERE ticket_number LIKE '%-_';\n\n";

    echo "-- View import logs\n";
    echo "SELECT * FROM import_logs WHERE batch_id = '{$result['batch_id']}' ORDER BY created_at DESC;\n\n";

    echo "-- View import summary\n";
    echo "SELECT * FROM import_batches WHERE batch_id = '{$result['batch_id']}';\n\n";

} else {
    echo "ERROR: " . $result['error'] . "\n\n";
    echo "Check import logs for details:\n";
    echo "SELECT * FROM import_logs WHERE batch_id = '{$result['batch_id']}' AND log_level = 'error';\n\n";
}

echo str_repeat("=", 80) . "\n";
echo "Import process completed.\n";
echo str_repeat("=", 80) . "\n";
