<?php
require_once 'config.php';

try {
    // Create programs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS programs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        program_name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Table 'programs' created successfully.\n";

    // Seed data
    $defaults = ['25-day Program', 'Final Coaching Program', '21-day Program'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO programs (program_name) VALUES (?)");
    foreach ($defaults as $prog) {
        $stmt->execute([$prog]);
    }
    echo "Default programs seeded.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>