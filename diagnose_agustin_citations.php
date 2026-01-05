<?php
require_once __DIR__ . '/includes/config.php';

try {
    $db = getPDO();

    echo "Diagnosing JOHNNY B. AGUSTIN citations\n";
    echo "======================================\n\n";

    // Total citations for this name
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM citations
        WHERE (first_name LIKE '%JOHNNY%' AND last_name LIKE '%AGUSTIN%')
           OR (first_name LIKE '%AGUSTIN%' AND last_name LIKE '%JOHNNY%')
    ");
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total citations matching name: " . $total . "\n\n";

    // Breakdown by status and deleted_at
    echo "Breakdown by status and soft-delete:\n";
    echo "------------------------------------\n";
    $stmt = $db->prepare("
        SELECT
            status,
            (deleted_at IS NOT NULL) as is_deleted,
            COUNT(*) as count
        FROM citations
        WHERE (first_name LIKE '%JOHNNY%' AND last_name LIKE '%AGUSTIN%')
           OR (first_name LIKE '%AGUSTIN%' AND last_name LIKE '%JOHNNY%')
        GROUP BY status, is_deleted
        ORDER BY status, is_deleted
    ");
    $stmt->execute();
    $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($breakdown as $row) {
        $deleted_label = $row['is_deleted'] ? ' (SOFT-DELETED)' : ' (ACTIVE)';
        echo "  Status: " . ($row['status'] ?: 'NULL') . $deleted_label . " → " . $row['count'] . " citations\n";
    }
    echo "\n";

    // Check payment records
    echo "Breakdown by payment status:\n";
    echo "----------------------------\n";
    $stmt = $db->prepare("
        SELECT
            c.status as citation_status,
            (p.payment_id IS NOT NULL) as has_payment,
            p.status as payment_status,
            COUNT(DISTINCT c.citation_id) as count
        FROM citations c
        LEFT JOIN payments p ON c.citation_id = p.citation_id
        WHERE ((c.first_name LIKE '%JOHNNY%' AND c.last_name LIKE '%AGUSTIN%')
           OR (c.first_name LIKE '%AGUSTIN%' AND c.last_name LIKE '%JOHNNY%'))
        GROUP BY c.status, has_payment, p.status
        ORDER BY c.status, has_payment, p.status
    ");
    $stmt->execute();
    $payment_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($payment_breakdown as $row) {
        $payment_label = $row['has_payment'] ? ' WITH payment (' . ($row['payment_status'] ?: 'unknown') . ')' : ' WITHOUT payment';
        echo "  Citation Status: " . ($row['citation_status'] ?: 'NULL') . $payment_label . " → " . $row['count'] . " citations\n";
    }
    echo "\n";

    // Check what citations.php would show (deleted_at IS NULL)
    echo "Citations that would appear in citations.php (deleted_at IS NULL):\n";
    echo "----------------------------------------------------------------\n";
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM citations c
        WHERE ((c.first_name LIKE '%JOHNNY%' AND c.last_name LIKE '%AGUSTIN%')
           OR (c.first_name LIKE '%AGUSTIN%' AND c.last_name LIKE '%JOHNNY%'))
        AND c.deleted_at IS NULL
    ");
    $stmt->execute();
    $citations_page_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  Count: " . $citations_page_count . "\n\n";

    // Check what process_payment.php would show (status='pending' AND no payment)
    echo "Citations that would appear in process_payment.php:\n";
    echo "--------------------------------------------------\n";
    echo "(status='pending' AND no existing payment record)\n";
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT c.citation_id) as count
        FROM citations c
        LEFT JOIN payments p ON c.citation_id = p.citation_id
            AND p.status IN ('pending_print', 'completed')
        WHERE ((c.first_name LIKE '%JOHNNY%' AND c.last_name LIKE '%AGUSTIN%')
           OR (c.first_name LIKE '%AGUSTIN%' AND c.last_name LIKE '%JOHNNY%'))
        AND c.status = 'pending'
        AND p.payment_id IS NULL
    ");
    $stmt->execute();
    $payment_page_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  Count: " . $payment_page_count . "\n\n";

    // Sample data
    echo "Sample citations (first 5):\n";
    echo "---------------------------\n";
    $stmt = $db->prepare("
        SELECT
            citation_id,
            ticket_number,
            first_name,
            last_name,
            status,
            deleted_at,
            created_at
        FROM citations
        WHERE ((first_name LIKE '%JOHNNY%' AND last_name LIKE '%AGUSTIN%')
           OR (first_name LIKE '%AGUSTIN%' AND last_name LIKE '%JOHNNY%'))
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($samples as $sample) {
        echo "\nTicket: " . $sample['ticket_number'] . "\n";
        echo "  Name: " . $sample['first_name'] . " " . $sample['last_name'] . "\n";
        echo "  Status: " . ($sample['status'] ?: 'NULL') . "\n";
        echo "  Deleted: " . ($sample['deleted_at'] ?: 'NO') . "\n";
        echo "  Created: " . $sample['created_at'] . "\n";

        // Check if this citation has payments
        $stmt2 = $db->prepare("SELECT payment_id, status, amount FROM payments WHERE citation_id = ?");
        $stmt2->execute([$sample['citation_id']]);
        $payments = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        if (count($payments) > 0) {
            echo "  Payments:\n";
            foreach ($payments as $payment) {
                echo "    - Payment #" . $payment['payment_id'] . ": " . $payment['status'] . " (" . number_format($payment['amount'], 2) . ")\n";
            }
        } else {
            echo "  Payments: NONE\n";
        }
    }

    echo "\n\n";
    echo "SUMMARY\n";
    echo "=======\n";
    echo "lto_search.php shows: 83 citations (searches ALL citations)\n";
    echo "citations.php shows: " . $citations_page_count . " citation(s) (filters deleted_at IS NULL)\n";
    echo "process_payment.php shows: " . $payment_page_count . " citation(s) (pending + no payment)\n\n";

    if ($total == 83 && $citations_page_count == 1) {
        echo "EXPLANATION: 82 out of 83 citations are SOFT-DELETED (deleted_at IS NOT NULL)\n";
    } else if ($total == 83 && $payment_page_count == 0) {
        echo "EXPLANATION: All citations either have payment records or are not in 'pending' status\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
