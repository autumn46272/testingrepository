<?php
require_once 'config.php';

try {
    echo "Starting Database Reset and Migration...<br>";

    // 1. Disable Foreign Keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 2. Truncate Tables
    $tables = ['students', 'groups', 'academic_records', 'users']; // Keeping users? User said "remove existing data". Maybe keep users to login?
    // User said "remove the existing data... also the test data". "I want to test adding student...". 
    // Usually admin user is needed. I will keep 'users' table content or create a default admin.
    // I'll truncate 'users' and re-insert 'admin'.

    $pdo->exec("TRUNCATE TABLE students");
    echo "Truncated students table.<br>";
    $pdo->exec("TRUNCATE TABLE groups");
    echo "Truncated groups table.<br>";
    $pdo->exec("TRUNCATE TABLE academic_records");
    echo "Truncated academic_records table.<br>";

    // Check if we should reset users. I'll stick to mostly students data reset. 
    // If I reset users, I logout the user. I'll SKIP users table reset to allow continued access, unless requested.
    // User said "remove the existing data...". Safest to keep the Admin user.

    // 3. Alter Students Table
    $alter_students = [
        "ADD COLUMN IF NOT EXISTS bon_country VARCHAR(100) AFTER city",
        "ADD COLUMN IF NOT EXISTS work_status VARCHAR(50) AFTER bon_country",
        "ADD COLUMN IF NOT EXISTS school VARCHAR(150) AFTER status",
        "ADD COLUMN IF NOT EXISTS exam_type VARCHAR(50) AFTER school",
        "ADD COLUMN IF NOT EXISTS exam_takes INT DEFAULT 0 AFTER exam_type",
        "ADD COLUMN IF NOT EXISTS emergency_name VARCHAR(100) AFTER remarks",
        "ADD COLUMN IF NOT EXISTS emergency_number VARCHAR(50) AFTER emergency_name"
    ];

    foreach ($alter_students as $sql) {
        try {
            $pdo->exec("ALTER TABLE students $sql");
            echo "Applied: $sql<br>";
        } catch (PDOException $e) {
            echo "Skipped/Error (Students): " . $e->getMessage() . "<br>";
        }
    }

    // 4. Alter Groups Table
    $alter_groups = [
        "ADD COLUMN IF NOT EXISTS batch_sequence INT DEFAULT 1 AFTER batch_year",
        "ADD COLUMN IF NOT EXISTS program_start_date DATE AFTER batch_sequence",
        "ADD COLUMN IF NOT EXISTS program_end_date DATE AFTER program_start_date"
    ];

    foreach ($alter_groups as $sql) {
        try {
            $pdo->exec("ALTER TABLE groups $sql");
            echo "Applied: $sql<br>";
        } catch (PDOException $e) {
            echo "Skipped/Error (Groups): " . $e->getMessage() . "<br>";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Migration Complete!<br>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>