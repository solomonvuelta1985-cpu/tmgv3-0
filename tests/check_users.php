<?php
require_once 'includes/config.php';

try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT user_id, username, full_name, role, status FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        echo "Users in database:\n";
        echo "==================\n";
        foreach ($users as $u) {
            echo "ID: {$u['user_id']}, Username: {$u['username']}, Name: {$u['full_name']}, Role: {$u['role']}, Status: {$u['status']}\n";
        }
    } else {
        echo "No users found in database!\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
