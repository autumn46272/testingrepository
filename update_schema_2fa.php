<?php
require 'config.php';

try {
    echo "Checking 'users' table columns...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('email', $columns)) {
        echo "Adding 'email' column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER last_name");
    } else {
        echo "'email' column already exists.\n";
    }

    if (!in_array('two_factor_code', $columns)) {
        echo "Adding 'two_factor_code' column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_code VARCHAR(6) NULL AFTER password_hash");
    } else {
        echo "'two_factor_code' column already exists.\n";
    }

    if (!in_array('two_factor_expires_at', $columns)) {
        echo "Adding 'two_factor_expires_at' column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_expires_at DATETIME NULL AFTER two_factor_code");
    } else {
        echo "'two_factor_expires_at' column already exists.\n";
    }

    echo "Database schema update completed successfully.\n";

} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
?>
