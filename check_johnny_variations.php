<?php
require_once __DIR__ . '/includes/config.php';

try {
    $db = getPDO();

    echo "Searching for all variations of JOHNNY and AGUSTIN\n";
    echo "=================================================\n\n";

    // Search for all citations with JOHNNY in any field
    echo "1. Citations with 'JOHNNY' in first_name:\n";
    echo "-----------------------------------------\n";
    $stmt = $db->prepare("
        SELECT
            citation_id,
            ticket_number,
            first_name,
            middle_initial,
            last_name,
            status,
            deleted_at IS NOT NULL as is_deleted
        FROM citations
        WHERE first_name LIKE '%JOHNNY%'
        ORDER BY last_name, first_name
        LIMIT 20
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) > 0) {
        foreach ($results as $row) {
            $deleted = $row['is_deleted'] ? ' [DELETED]' : '';
            echo "  Ticket " . $row['ticket_number'] . ": " .
                 $row['first_name'] . " " .
                 ($row['middle_initial'] ? $row['middle_initial'] . ". " : "") .
                 $row['last_name'] . " (" . $row['status'] . ")" . $deleted . "\n";
        }
        echo "  Total: " . count($results) . "\n\n";
    } else {
        echo "  No results\n\n";
    }

    // Search for all citations with AGUSTIN in any field
    echo "2. Citations with 'AGUSTIN' in last_name:\n";
    echo "-----------------------------------------\n";
    $stmt = $db->prepare("
        SELECT
            citation_id,
            ticket_number,
            first_name,
            middle_initial,
            last_name,
            status,
            deleted_at IS NOT NULL as is_deleted
        FROM citations
        WHERE last_name LIKE '%AGUSTIN%'
        ORDER BY first_name, last_name
        LIMIT 100
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) > 0) {
        echo "  Found " . count($results) . " citations:\n\n";
        foreach ($results as $row) {
            $deleted = $row['is_deleted'] ? ' [DELETED]' : '';
            echo "  Ticket " . $row['ticket_number'] . ": " .
                 $row['first_name'] . " " .
                 ($row['middle_initial'] ? $row['middle_initial'] . ". " : "") .
                 $row['last_name'] . " (" . $row['status'] . ")" . $deleted . "\n";
        }
        echo "\n  Total: " . count($results) . "\n\n";
    } else {
        echo "  No results\n\n";
    }

    // Search using the same logic as lto_search.php name search
    echo "3. Using lto_search.php 'name' search logic for 'JOHNNY B. AGUSTIN':\n";
    echo "-------------------------------------------------------------------\n";
    $search_term = "JOHNNY B. AGUSTIN";
    $stmt = $db->prepare("
        SELECT
            citation_id,
            ticket_number,
            first_name,
            middle_initial,
            last_name,
            status,
            deleted_at IS NOT NULL as is_deleted
        FROM citations
        WHERE (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
        ORDER BY created_at DESC
    ");
    $stmt->execute(["%{$search_term}%", "%{$search_term}%", "%{$search_term}%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "  Search term: '$search_term'\n";
    echo "  Results: " . count($results) . "\n\n";

    if (count($results) > 0) {
        foreach ($results as $row) {
            $deleted = $row['is_deleted'] ? ' [DELETED]' : '';
            echo "  Ticket " . $row['ticket_number'] . ": " .
                 $row['first_name'] . " " .
                 ($row['middle_initial'] ? $row['middle_initial'] . ". " : "") .
                 $row['last_name'] . " (" . $row['status'] . ")" . $deleted . "\n";
        }
    }
    echo "\n";

    // Search using 'all fields' search logic
    echo "4. Using lto_search.php 'all fields' search logic for 'JOHNNY':\n";
    echo "-------------------------------------------------------------\n";
    $search_term = "JOHNNY";
    $stmt = $db->prepare("
        SELECT
            citation_id,
            ticket_number,
            first_name,
            middle_initial,
            last_name,
            license_number,
            plate_mv_engine_chassis_no,
            status,
            deleted_at IS NOT NULL as is_deleted
        FROM citations
        WHERE (license_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR plate_mv_engine_chassis_no LIKE ?)
        ORDER BY created_at DESC
    ");
    $stmt->execute(["%{$search_term}%", "%{$search_term}%", "%{$search_term}%", "%{$search_term}%"]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "  Search term: '$search_term'\n";
    echo "  Results: " . count($results) . "\n\n";

    if (count($results) > 0 && count($results) <= 10) {
        foreach ($results as $row) {
            $deleted = $row['is_deleted'] ? ' [DELETED]' : '';
            echo "  Ticket " . $row['ticket_number'] . ": " .
                 $row['first_name'] . " " .
                 ($row['middle_initial'] ? $row['middle_initial'] . ". " : "") .
                 $row['last_name'] . " (" . $row['status'] . ")" . $deleted . "\n";
        }
    } elseif (count($results) > 10) {
        echo "  (Too many to display - showing first 10)\n";
        for ($i = 0; $i < 10; $i++) {
            $row = $results[$i];
            $deleted = $row['is_deleted'] ? ' [DELETED]' : '';
            echo "  Ticket " . $row['ticket_number'] . ": " .
                 $row['first_name'] . " " .
                 ($row['middle_initial'] ? $row['middle_initial'] . ". " : "") .
                 $row['last_name'] . " (" . $row['status'] . ")" . $deleted . "\n";
        }
    }
    echo "\n";

    // Count by status
    echo "5. Breakdown by status for last_name LIKE '%AGUSTIN%':\n";
    echo "------------------------------------------------------\n";
    $stmt = $db->prepare("
        SELECT
            status,
            deleted_at IS NOT NULL as is_deleted,
            COUNT(*) as count
        FROM citations
        WHERE last_name LIKE '%AGUSTIN%'
        GROUP BY status, is_deleted
        ORDER BY count DESC
    ");
    $stmt->execute();
    $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($breakdown as $row) {
        $deleted_label = $row['is_deleted'] ? ' (SOFT-DELETED)' : ' (ACTIVE)';
        echo "  Status: " . ($row['status'] ?: 'NULL') . $deleted_label . " → " . $row['count'] . " citations\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
