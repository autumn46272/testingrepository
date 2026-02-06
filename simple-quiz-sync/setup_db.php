<?php
require_once '../config.php';

// Connect using config constants
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Database selection is handled by the connection, but we can double check or just proceed to table creation.
echo "Connected to database: " . DB_NAME . "<br>";


// Create tables
$tables = [
    // 1. Exam Sessions Table (8-digit code)
    "CREATE TABLE IF NOT EXISTS quiz_exams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_code VARCHAR(8) UNIQUE NOT NULL,
        title VARCHAR(100),
        status ENUM('active', 'completed') DEFAULT 'active',
        current_question_index INT DEFAULT 0,
        is_revealed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 2. Questions Table (Answer Key linked to Exam)
    "CREATE TABLE IF NOT EXISTS quiz_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT,
        question_text TEXT,
        type VARCHAR(20) DEFAULT 'choice',
        options JSON,
        answer TEXT, /* Expected Answer Key */
        display_order INT,
        FOREIGN KEY (exam_id) REFERENCES quiz_exams(id) ON DELETE CASCADE
    )",

    // 3. Students Table (Simple login for Quiz)
    "CREATE TABLE IF NOT EXISTS quiz_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    // 4. Responses Table (Student Answer + Expected Answer)
    "CREATE TABLE IF NOT EXISTS quiz_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT,
        student_id INT,
        question_id INT,
        student_answer TEXT,
        expected_answer TEXT, /* Snapshot of correct answer */
        is_correct BOOLEAN,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES quiz_exams(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES quiz_students(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table created successfully<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

// Seed State
$conn->query("INSERT IGNORE INTO state (id, current_question_index) VALUES (1, 0)");

// Seed Questions (from JSON if available, or hardcoded)
$jsonFile = 'data/questions.json';
if (file_exists($jsonFile)) {
    $questions = json_decode(file_get_contents($jsonFile), true);
    // Clear existing questions to avoid duplicates on re-run
    $conn->query("TRUNCATE TABLE questions");
    
    $stmt = $conn->prepare("INSERT INTO questions (type, question, options, answer) VALUES (?, ?, ?, ?)");
    foreach ($questions as $q) {
        $type = $q['type'] ?? 'choice';
        $opts = json_encode($q['options']);
        $ans = $q['answer'];
        $stmt->bind_param("ssss", $type, $q['question'], $opts, $ans);
        $stmt->execute();
    }
    echo "Questions seeded from JSON.<br>";
}

echo "Setup complete! You can close this page.";
$conn->close();
?>
