<?php
require_once 'config.php';

try {
    echo "Starting Group-Courses Migration...<br>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Create group_courses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        course_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_group_course (group_id, course_id),
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    )");
    echo "Created group_courses table.<br>";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Migration Complete!<br>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>