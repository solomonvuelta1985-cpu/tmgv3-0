<?php
require_once __DIR__ . '/includes/config.php';

try {
    $db = getPDO();

    echo "Checking vehicle_type data in citations...\n";
    echo "==========================================\n\n";

    // Count total citations
    $stmt = $db->query("SELECT COUNT(*) as total FROM citations");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total citations: " . number_format($total) . "\n\n";

    // Count citations with vehicle_type
    $stmt = $db->query("SELECT COUNT(*) as count FROM citations WHERE vehicle_type IS NOT NULL AND vehicle_type != ''");
    $withType = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Citations WITH vehicle_type: " . number_format($withType) . "\n";

    // Count citations without vehicle_type
    $stmt = $db->query("SELECT COUNT(*) as count FROM citations WHERE vehicle_type IS NULL OR vehicle_type = ''");
    $withoutType = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Citations WITHOUT vehicle_type: " . number_format($withoutType) . "\n\n";

    // Show sample citations with vehicle_type
    echo "Sample citations WITH vehicle_type:\n";
    echo "------------------------------------\n";
    $stmt = $db->query("SELECT ticket_number, vehicle_type, vehicle_description FROM citations WHERE vehicle_type IS NOT NULL AND vehicle_type != '' LIMIT 5");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($samples) > 0) {
        foreach ($samples as $sample) {
            echo "Ticket: " . $sample['ticket_number'] . "\n";
            echo "  Type: " . $sample['vehicle_type'] . "\n";
            echo "  Desc: " . substr($sample['vehicle_description'], 0, 50) . "\n\n";
        }
    } else {
        echo "No citations found with vehicle_type data.\n\n";
    }

    // Show sample citations without vehicle_type
    echo "Sample citations WITHOUT vehicle_type:\n";
    echo "--------------------------------------\n";
    $stmt = $db->query("SELECT ticket_number, vehicle_type, vehicle_description FROM citations WHERE vehicle_type IS NULL OR vehicle_type = '' LIMIT 5");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($samples) > 0) {
        foreach ($samples as $sample) {
            echo "Ticket: " . $sample['ticket_number'] . "\n";
            echo "  Type: " . ($sample['vehicle_type'] ?: 'NULL') . "\n";
            echo "  Desc: " . substr($sample['vehicle_description'], 0, 50) . "\n\n";
        }
    } else {
        echo "All citations have vehicle_type data!\n\n";
    }

    // Show distribution of vehicle types
    echo "Vehicle type distribution:\n";
    echo "-------------------------\n";
    $stmt = $db->query("
        SELECT
            COALESCE(vehicle_type, 'NULL/Empty') as type,
            COUNT(*) as count
        FROM citations
        GROUP BY vehicle_type
        ORDER BY count DESC
    ");
    $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($distribution as $row) {
        echo $row['type'] . ": " . number_format($row['count']) . "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
