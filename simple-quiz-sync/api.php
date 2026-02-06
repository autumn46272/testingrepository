<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

// Global $pdo is available from config.php

$action = $_GET['action'] ?? '';

try {
    // --- 1. Create New Exam (Presenter) ---
    if ($action === 'create_exam') {
        $title = 'Live Quiz ' . date('H:i');
        $examCode = rand(10000000, 99999999); // 8-Digit unique code

        // Load Default Questions
        $questionsData = file_get_contents('data/questions.json');
        if ($questionsData === false) {
             throw new Exception("Could not read data/questions.json");
        }
        $questions = json_decode($questionsData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in questions.json');
        }

        $pdo->beginTransaction();

        // Create Exam
        $stmt = $pdo->prepare("INSERT INTO quiz_exams (exam_code, title) VALUES (?, ?)");
        if (!$stmt->execute([$examCode, $title])) {
            throw new Exception("Failed to create exam record.");
        }
        $examId = $pdo->lastInsertId();

        // Create Questions
        $stmtQ = $pdo->prepare("INSERT INTO quiz_questions (exam_id, type, question_text, options, answer, display_order) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($questions as $index => $q) {
            $type = $q['type'] ?? 'choice';
            $opts = json_encode($q['options']);
            $ans = $q['answer'];
            $order = $index;
            
            // Ensure options is a valid JSON string
            if (!$opts) $opts = '[]';
            
            $stmtQ->execute([$examId, $type, $q['question'], $opts, $ans, $order]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'exam_code' => $examCode, 'exam_id' => $examId]);

    // --- 2. Join Quiz (Student) ---
    } elseif ($action === 'join_quiz') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? '');

        if (empty($name) || empty($code)) {
            throw new Exception('Name and Code are required');
        }

        // Verify Code
        $stmt = $pdo->prepare("SELECT id, title FROM quiz_exams WHERE exam_code = ? AND status='active'");
        $stmt->execute([$code]);
        $exam = $stmt->fetch();

        if (!$exam) {
            throw new Exception('Invalid or Inactive Exam Code');
        }

        // Register Student (Simple)
        // Check if student exists or create temp
        $stmt = $pdo->prepare("SELECT id FROM quiz_students WHERE name = ?");
        $stmt->execute([$name]);
        if ($row = $stmt->fetch()) {
            $studentId = $row['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO quiz_students (name) VALUES (?)");
            $stmt->execute([$name]);
            $studentId = $pdo->lastInsertId();
        }

        echo json_encode([
            'status' => 'success', 
            'student_id' => $studentId, 
            'name' => $name,
            'exam_id' => $exam['id'],
            'exam_title' => $exam['title']
        ]);

    // --- 3. Get State (Sync) ---
    } elseif ($action === 'get_state') {
        $code = $_GET['code'] ?? '';
        
        $stmt = $pdo->prepare("SELECT * FROM quiz_exams WHERE exam_code = ?");
        $stmt->execute([$code]);
        $exam = $stmt->fetch();

        if (!$exam) {
            throw new Exception('Exam not found');
        }

        $currentIndex = $exam['current_question_index'];

        // Get Total Questions
        $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM quiz_questions WHERE exam_id = ?");
        $stmt->execute([$exam['id']]);
        $total = $stmt->fetch()['c'];

        // Get Current Question
        $stmt = $pdo->prepare("SELECT id, type, question_text as question, options FROM quiz_questions WHERE exam_id = ? ORDER BY display_order LIMIT 1 OFFSET ?");
        $stmt->execute([$exam['id'], $currentIndex]);
        $question = $stmt->fetch();

        if ($question) {
            $question['options'] = json_decode($question['options']);
        }

        echo json_encode([
            'status' => 'success',
            'state' => ['currentQuestionIndex' => (int)$currentIndex],
            'question' => $question,
            'totalQuestions' => (int)$total,
            'exam_status' => $exam['status'] ?? 'active'
        ]);

    // --- 4. Update State (Presenter) ---
    } elseif ($action === 'update_state') {
        $input = json_decode(file_get_contents('php://input'), true);
        $code = $input['code'] ?? '';
        $newIndex = $input['currentQuestionIndex'] ?? 0;

        $stmt = $pdo->prepare("UPDATE quiz_exams SET current_question_index = ? WHERE exam_code = ?");
        $stmt->execute([$newIndex, $code]);

        echo json_encode(['status' => 'success']);

    // --- 5. Submit Answer (Student) ---
    } elseif ($action === 'submit_answer') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $studentId = $input['student_id'];
        $examCode = $input['code'];
        $questionId = $input['question_id'];
        $answerText = trim($input['answer']); 

        // Get Exam ID
        $stmt = $pdo->prepare("SELECT id FROM quiz_exams WHERE exam_code = ?");
        $stmt->execute([$examCode]);
        $examRes = $stmt->fetch();
        if (!$examRes) throw new Exception("Invalid Exam Code");
        $examId = $examRes['id'];

        // Get Expected Answer for Checking
        $stmt = $pdo->prepare("SELECT answer, type FROM quiz_questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $qData = $stmt->fetch();
        
        if (!$qData) throw new Exception("Invalid Question");

        $expectedAnswer = $qData['answer'];
        $isCorrect = false;

        // Check Correctness
        if ($qData['type'] === 'text') {
            $isCorrect = (strcasecmp($answerText, $expectedAnswer) === 0);
        } else {
            // Choice
            $isCorrect = ($answerText == $expectedAnswer);
        }

        // Check if already submitted
        $checkStmt = $pdo->prepare("SELECT id FROM quiz_responses WHERE exam_id = ? AND student_id = ? AND question_id = ?");
        $checkStmt->execute([$examId, $studentId, $questionId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['status' => 'success', 'message' => 'Already submitted']);
            exit;
        }

        // Save Response
        $stmt = $pdo->prepare("INSERT INTO quiz_responses (exam_id, student_id, question_id, answer_text, expected_answer, is_correct) VALUES (?, ?, ?, ?, ?, ?)");
        $intCorrect = $isCorrect ? 1 : 0;
        
        if ($stmt->execute([$examId, $studentId, $questionId, $answerText, $expectedAnswer, $intCorrect])) {
            echo json_encode(['status' => 'success']);
        } else {
            throw new Exception("Failed to save response");
        }


    // --- 6. End Exam (Presenter) ---
    } elseif ($action === 'end_exam') {
        $input = json_decode(file_get_contents('php://input'), true);
        $examId = $input['id'] ?? 0; // Changed from code to id

        $stmt = $pdo->prepare("UPDATE quiz_exams SET status = 'completed' WHERE id = ?");
        $stmt->execute([$examId]);

        echo json_encode(['status' => 'success']);

    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}


