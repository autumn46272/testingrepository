<?php
/**
 * Parse SCORM Tracking Data into Test Results
 * Converts cmi.interactions data into readable question results
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    set_flash('error', 'Access denied. Admin only.');
    redirect(APP_URL . '/');
}

$page_title = 'Parse SCORM Data';

// Process parsing
if (is_post()) {
    $attempt_id = post('attempt_id');
    
    if ($attempt_id) {
        // Get attempt info
        $attempt = db_fetch("SELECT * FROM scorm_attempts WHERE id = ?", [$attempt_id]);
        
        if ($attempt) {
            $user_id = $attempt['user_id'];
            $package_id = $attempt['package_id'];
            
            // Get all SCORM tracking data for this user and package
            $tracking_data = db_fetch_all(
                "SELECT element, value FROM scorm_tracking 
                 WHERE user_id = ? AND package_id = ? 
                 ORDER BY element",
                [$user_id, $package_id]
            );
            
            // Parse interactions
            $interactions = [];
            foreach ($tracking_data as $row) {
                $element = $row['element'];
                $value = $row['value'];
                
                // Extract interaction index and property
                if (preg_match('/cmi\.interactions\.(\d+|NaN)\.(.+)/', $element, $matches)) {
                    $index = $matches[1];
                    $property = $matches[2];
                    
                    if (!isset($interactions[$index])) {
                        $interactions[$index] = [];
                    }
                    
                    $interactions[$index][$property] = $value;
                }
            }
            
            // Delete existing results for this attempt
            db_query("DELETE FROM scorm_test_results WHERE attempt_id = ?", [$attempt_id]);
            
            // Insert parsed results
            $inserted = 0;
            foreach ($interactions as $index => $interaction) {
                $question_id = $interaction['id'] ?? "Q_{$index}";
                $question_text = isset($interaction['id']) ? str_replace('_', ' ', $interaction['id']) : '';
                $student_response = $interaction['student_response'] ?? '';
                $correct_pattern = $interaction['correct_responses.0.pattern'] ?? '';
                $result = $interaction['result'] ?? 'unknown';
                $is_correct = ($result === 'correct' || $result === 'right') ? 1 : 0;
                $weighting = floatval($interaction['weighting'] ?? 1);
                $latency = $interaction['latency'] ?? '00:00:00';
                
                // Convert latency to seconds
                $time_parts = explode(':', $latency);
                $answer_time_seconds = 0;
                if (count($time_parts) == 3) {
                    $answer_time_seconds = ($time_parts[0] * 3600) + ($time_parts[1] * 60) + $time_parts[2];
                }
                
                $points_earned = $is_correct ? $weighting : 0;
                
                db_query(
                    "INSERT INTO scorm_test_results 
                     (attempt_id, user_id, package_id, question_id, question_text, 
                      user_answer, correct_answer, is_correct, points_earned, 
                      points_possible, answer_time_seconds)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $attempt_id,
                        $user_id,
                        $package_id,
                        $question_id,
                        $question_text,
                        $student_response,
                        $correct_pattern,
                        $is_correct,
                        $points_earned,
                        $weighting,
                        $answer_time_seconds
                    ]
                );
                
                $inserted++;
            }
            
            set_flash('success', "Successfully parsed {$inserted} questions for attempt {$attempt_id}!");
            redirect(APP_URL . '/student/attempt_details.php?id=' . $attempt_id);
        } else {
            set_flash('error', 'Attempt not found.');
        }
    }
}

// Get all attempts that might need parsing
$attempts = db_fetch_all("
    SELECT 
        sa.id,
        sa.user_id,
        sa.package_id,
        sa.score,
        sa.status,
        sa.completed_at,
        u.full_name,
        sp.title as package_title,
        (SELECT COUNT(*) FROM scorm_test_results WHERE attempt_id = sa.id) as result_count,
        (SELECT COUNT(*) FROM scorm_tracking WHERE user_id = sa.user_id AND package_id = sa.package_id AND element LIKE 'cmi.interactions.%') as tracking_count
    FROM scorm_attempts sa
    JOIN users u ON sa.user_id = u.id
    JOIN scorm_packages sp ON sa.package_id = sp.id
    ORDER BY sa.id DESC
");

include '../includes/header.php';
?>

<div class="dashboard">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>🔧 Parse SCORM Data</h1>
            <a href="<?php echo APP_URL; ?>/admin/reports.php" class="btn btn-secondary">Back to Reports</a>
        </div>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3>Attempts with SCORM Tracking Data</h3>
                </div>
                <div class="card-body">
                    <p>This tool parses raw SCORM tracking data and converts it into readable question-by-question results.</p>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Attempt ID</th>
                                <th>Student</th>
                                <th>Package</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Results</th>
                                <th>Tracking Data</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): ?>
                                <tr>
                                    <td><?php echo $attempt['id']; ?></td>
                                    <td><?php echo htmlspecialchars($attempt['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($attempt['package_title']); ?></td>
                                    <td><?php echo $attempt['score'] ? round($attempt['score'], 2) . '%' : 'N/A'; ?></td>
                                    <td><?php echo ucfirst($attempt['status']); ?></td>
                                    <td>
                                        <?php if ($attempt['result_count'] > 0): ?>
                                            <span style="color: green;">✓ <?php echo $attempt['result_count']; ?> questions</span>
                                        <?php else: ?>
                                            <span style="color: red;">✗ No results</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attempt['tracking_count'] > 0): ?>
                                            <span style="color: blue;">📊 <?php echo $attempt['tracking_count']; ?> interactions</span>
                                        <?php else: ?>
                                            <span style="color: gray;">No data</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attempt['tracking_count'] > 0): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="attempt_id" value="<?php echo $attempt['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <?php echo $attempt['result_count'] > 0 ? 'Re-parse' : 'Parse'; ?>
                                                </button>
                                            </form>
                                            <a href="<?php echo APP_URL; ?>/student/attempt_details.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">View</a>
                                        <?php else: ?>
                                            <span style="color: gray;">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
