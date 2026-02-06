<?php
// Custom config to force 127.0.0.1
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'u591504149_radatabase');
define('DB_PASS', 'raDB@2026');
define('DB_NAME', 'u591504149_student_db');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected successfully to " . DB_HOST . "\n";

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
