<?php
require_once 'config.php';

try {
    echo "Starting Many-to-Many Migration...<br>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Create student_groups table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        group_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_pairing (student_id, group_id)
    )");
    echo "Created student_groups table.<br>";

    // 2. Migrate existing data (One-to-Many -> Many-to-Many)
    // Check if group_id exists in students table before trying to migrate
    $cols = $pdo->query("DESCRIBE students group_id")->fetchAll();
    if (count($cols) > 0) {
        // Migrate
        $sql = "INSERT IGNORE INTO student_groups (student_id, group_id) 
                SELECT id, group_id FROM students WHERE group_id IS NOT NULL AND group_id > 0";
        $pdo->exec($sql);
        echo "Migrated existing student-group relationships.<br>";

        // Note: We are NOT dropping students.group_id yet to maintain backward compatibility during transition,
        // or we can drop it if confirmed. User said "Replace single Group field".
        // I will keep it but stop using it in UI, or set it to NULL eventually.
    }

    // 3. Update Academic Records to store group_id
    // Check if column exists
    $cols_ar = $pdo->query("DESCRIBE academic_records group_id")->fetchAll();
    if (count($cols_ar) == 0) {
        $pdo->exec("ALTER TABLE academic_records ADD COLUMN group_id INT AFTER student_id");
        echo "Added group_id column to academic_records.<br>";

        // Backfill group_id based on 'program' name (best effort)
        // We match academic_records.program = groups.group_name (or program?)
        // In academic.php: $program = $stmt_group->fetchColumn(); (which was group_name or program field?)
        // In academic.php line 59: SELECT group_name FROM groups. 
        // So academic_records.program stores group_name.

        $sql_update = "UPDATE academic_records ar 
                       JOIN groups g ON ar.program = g.group_name 
                       SET ar.group_id = g.id 
                       WHERE ar.group_id IS NULL";
        $pdo->exec($sql_update);
        echo "Backfilled academic_records group_id.<br>";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Migration Complete!<br>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>