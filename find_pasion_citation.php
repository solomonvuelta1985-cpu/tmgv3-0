<?php
require_once __DIR__ . '/includes/config.php';

try {
    $db = getPDO();

    echo "Searching for PASION, EMERSON M citation...\n";
    echo "===========================================\n\n";

    // Search for the citation
    $stmt = $db->prepare("
        SELECT
            citation_id,
            ticket_number,
            last_name,
            first_name,
            middle_initial,
            plate_mv_engine_chassis_no,
            vehicle_type,
            vehicle_description,
            import_batch_id
        FROM citations
        WHERE last_name LIKE '%PASION%'
        AND first_name LIKE '%EMERSON%'
        AND plate_mv_engine_chassis_no LIKE '%B380QD%'
    ");

    $stmt->execute();
    $citations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($citations) > 0) {
        foreach ($citations as $cit) {
            echo "Found citation:\n";
            echo "---------------\n";
            echo "Citation ID: " . $cit['citation_id'] . "\n";
            echo "Ticket: " . $cit['ticket_number'] . "\n";
            echo "Name: " . $cit['last_name'] . ", " . $cit['first_name'] . " " . $cit['middle_initial'] . "\n";
            echo "Plate: " . $cit['plate_mv_engine_chassis_no'] . "\n";
            echo "Vehicle Type: " . ($cit['vehicle_type'] ?: 'NULL') . " ← PROBLEM\n";
            echo "Vehicle Desc: " . $cit['vehicle_description'] . "\n";
            echo "Import Batch: " . $cit['import_batch_id'] . "\n\n";

            // Check if this was imported from Excel
            if ($cit['import_batch_id']) {
                echo "Checking import staging data for this citation...\n";
                $stmt2 = $db->prepare("
                    SELECT
                        excel_row,
                        vehicle_type,
                        vehicle_description,
                        final_ticket
                    FROM import_staging
                    WHERE batch_id = ?
                    AND final_ticket = ?
                    LIMIT 1
                ");

                $stmt2->execute([$cit['import_batch_id'], $cit['ticket_number']]);
                $staging = $stmt2->fetch(PDO::FETCH_ASSOC);

                if ($staging) {
                    echo "Excel Row: " . $staging['excel_row'] . "\n";
                    echo "Staging Vehicle Type: " . ($staging['vehicle_type'] ?: 'NULL') . "\n";
                    echo "Staging Vehicle Desc: " . $staging['vehicle_description'] . "\n\n";

                    if ($staging['vehicle_type']) {
                        echo "✅ The staging table HAS vehicle_type data!\n";
                        echo "❌ But it was NOT copied to the citations table!\n\n";

                        echo "SOLUTION: The import code needs to be checked.\n";
                    } else {
                        echo "❌ The staging table also has NULL vehicle_type.\n";
                        echo "This means the data was not extracted from Excel properly.\n\n";
                    }
                }
            }
        }
    } else {
        echo "Citation not found in database.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
