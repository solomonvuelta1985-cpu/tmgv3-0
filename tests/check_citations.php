<?php
require_once 'includes/config.php';

try {
    $pdo = getPDO();

    // Check total citations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM citations");
    $count = $stmt->fetch();
    echo "Total citations: " . $count['count'] . "\n";

    // Check unpaid citations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM citations WHERE status = 'unpaid'");
    $unpaid = $stmt->fetch();
    echo "Unpaid citations: " . $unpaid['count'] . "\n\n";

    // Show ALL citations with their status
    echo "All citations:\n";
    $stmt = $pdo->query("SELECT citation_id, ticket_number, CONCAT(first_name, ' ', last_name) as driver, total_fine, status
                         FROM citations");
    $citations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($citations as $cit) {
        echo "  ID: {$cit['citation_id']}, Ticket: {$cit['ticket_number']}, Driver: {$cit['driver']}, Fine: ₱{$cit['total_fine']}, Status: {$cit['status']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
