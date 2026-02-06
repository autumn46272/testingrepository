<?php
require_once 'config.php';

try {
    // 1. Create student_groups table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        group_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_student_group (student_id, group_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
    )");
    echo "Table 'student_groups' created successfully.\n";

    // 2. Migrate existing data
    // Check if we need to migrate: select students who have a group_id but no entry in student_groups
    $stmt = $pdo->query("SELECT id, group_id FROM students WHERE group_id IS NOT NULL AND group_id != 0");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    $insertStmt = $pdo->prepare("INSERT IGNORE INTO student_groups (student_id, group_id) VALUES (?, ?)");

    foreach ($students as $s) {
        $insertStmt->execute([$s['id'], $s['group_id']]);
        if ($insertStmt->rowCount() > 0) {
            $count++;
        }
    }

    echo "Migrated $count existing student-group relationships.\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>