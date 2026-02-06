<?php
require_once 'config.php';

try {
    echo "Starting Student-Courses Migration...<br>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Check if students table has a primary key
    $stmt = $pdo->query("SHOW KEYS FROM students WHERE Key_name = 'PRIMARY'");
    if ($stmt->rowCount() == 0) {
        echo "Adding PRIMARY KEY to students table...<br>";
        // Make sure id is INT and AUTO_INCREMENT
        $pdo->exec("ALTER TABLE students MODIFY id INT AUTO_INCREMENT PRIMARY KEY");
    }

    // Create student_courses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_student_course (student_id, course_id),
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Created student_courses table.<br>";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Migration Complete!<br>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>