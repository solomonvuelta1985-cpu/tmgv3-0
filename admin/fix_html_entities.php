<?php
/**
 * Fix HTML Entities in Database
 * This script converts HTML entities back to normal characters
 * Run this ONCE to fix existing data
 */

require_once '../includes/config.php';

// Prevent running in production without confirmation
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$confirm) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fix HTML Entities</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="alert alert-warning">
                <h4>⚠️ Database Repair Tool</h4>
                <p>This script will convert HTML entities (like <code>&amp;#039;</code>) back to normal characters (like <code>'</code>) in your database.</p>
                <p><strong>This will affect:</strong></p>
                <ul>
                    <li>violation_types table - violation names</li>
                    <li>drivers table - names and addresses</li>
                    <li>citations table - all text fields</li>
                    <li>Other text fields with HTML entities</li>
                </ul>
                <p><strong>⚠️ Important:</strong> This is a one-time operation. Make sure you have a database backup!</p>
                <a href="?confirm=yes" class="btn btn-danger">Yes, Fix HTML Entities Now</a>
                <a href="../public/index2.php" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Confirmed - proceed with fixing
try {
    $pdo = getPDO();
    $fixed_count = 0;

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Fixing...</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'></head>";
    echo "<body><div class='container mt-5'><h3>🔧 Fixing HTML Entities...</h3><pre>";

    // Fix violation_types table
    echo "\n=== FIXING VIOLATION TYPES ===\n";
    $stmt = $pdo->query("SELECT violation_type_id, violation_type FROM violation_types");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $original = $row['violation_type'];
        $fixed = html_entity_decode($original, ENT_QUOTES, 'UTF-8');

        if ($original !== $fixed) {
            $update = $pdo->prepare("UPDATE violation_types SET violation_type = ? WHERE violation_type_id = ?");
            $update->execute([$fixed, $row['violation_type_id']]);
            echo "✓ Fixed: '$original' → '$fixed'\n";
            $fixed_count++;
        }
    }

    // Fix drivers table
    echo "\n=== FIXING DRIVERS TABLE ===\n";
    $stmt = $pdo->query("SELECT driver_id, last_name, first_name, middle_initial, suffix, barangay, municipality, province FROM drivers");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $needs_update = false;
        $updates = [];

        foreach (['last_name', 'first_name', 'middle_initial', 'suffix', 'barangay', 'municipality', 'province'] as $field) {
            if (!empty($row[$field])) {
                $fixed = html_entity_decode($row[$field], ENT_QUOTES, 'UTF-8');
                if ($row[$field] !== $fixed) {
                    $needs_update = true;
                    $updates[$field] = $fixed;
                }
            }
        }

        if ($needs_update) {
            $set_clauses = [];
            $params = [];
            foreach ($updates as $field => $value) {
                $set_clauses[] = "$field = ?";
                $params[] = $value;
            }
            $params[] = $row['driver_id'];

            $sql = "UPDATE drivers SET " . implode(', ', $set_clauses) . " WHERE driver_id = ?";
            $update = $pdo->prepare($sql);
            $update->execute($params);
            echo "✓ Fixed driver ID {$row['driver_id']}\n";
            $fixed_count++;
        }
    }

    // Fix citations table
    echo "\n=== FIXING CITATIONS TABLE ===\n";
    $fields = ['last_name', 'first_name', 'middle_initial', 'suffix', 'barangay', 'municipality', 'province',
               'place_of_apprehension', 'apprehension_officer', 'remarks', 'vehicle_description'];

    $stmt = $pdo->query("SELECT citation_id, " . implode(', ', $fields) . " FROM citations");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $needs_update = false;
        $updates = [];

        foreach ($fields as $field) {
            if (!empty($row[$field])) {
                $fixed = html_entity_decode($row[$field], ENT_QUOTES, 'UTF-8');
                if ($row[$field] !== $fixed) {
                    $needs_update = true;
                    $updates[$field] = $fixed;
                }
            }
        }

        if ($needs_update) {
            $set_clauses = [];
            $params = [];
            foreach ($updates as $field => $value) {
                $set_clauses[] = "$field = ?";
                $params[] = $value;
            }
            $params[] = $row['citation_id'];

            $sql = "UPDATE citations SET " . implode(', ', $set_clauses) . " WHERE citation_id = ?";
            $update = $pdo->prepare($sql);
            $update->execute($params);
            echo "✓ Fixed citation ID {$row['citation_id']}\n";
            $fixed_count++;
        }
    }

    echo "\n=== COMPLETE ===\n";
    echo "✓ Fixed $fixed_count records total\n";
    echo "</pre>";
    echo "<div class='alert alert-success mt-3'>";
    echo "<h4>✅ Done!</h4>";
    echo "<p>Fixed <strong>$fixed_count</strong> records with HTML entities.</p>";
    echo "<a href='../public/index2.php' class='btn btn-primary'>Return to Citation Form</a>";
    echo "</div></div></body></html>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("Fix HTML entities error: " . $e->getMessage());
}
?>
