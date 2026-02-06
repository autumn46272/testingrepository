<?php
require_once 'config.php';

try {
    echo "Starting V2 Migration...<br>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Create topics table
    $pdo->exec("CREATE TABLE IF NOT EXISTS topics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        topic_name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created topics table.<br>";

    // Populate default topics
    $topics = [
        'Anatomy',
        'Physiology',
        'Pharmacology',
        'Pathology',
        'Medical-Surgical Nursing',
        'Maternal and Child Nursing',
        'Mental Health Nursing',
        'Community Health Nursing',
        'Fundamentals of Nursing',
        'Leadership and Management'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO topics (topic_name) VALUES (:name)");
    foreach ($topics as $t) {
        $stmt->execute([':name' => $t]);
    }
    echo "Populated default topics.<br>";

    // 2. Add status to Groups
    $cols = $pdo->query("DESCRIBE groups status")->fetchAll();
    if (count($cols) == 0) {
        $pdo->exec("ALTER TABLE groups ADD COLUMN status VARCHAR(20) DEFAULT 'Upcoming'");
        echo "Added status column to groups.<br>";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Migration Complete!<br>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>