<?php
/**
 * Student Attempt Details
 * Student Database System
 */

require_once '../config.php';
require_once '../functions.php';
require_once '../auth_check.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    redirect('../dashboard.php');
}

$attempt_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$attempt_id) {
    // Redirect to history if no ID provided
    redirect('../scorm_history.php');
}

// For students, get their own user_id. For admins, allow viewing any attempt
if ($_SESSION['role'] === 'student') {
    $user_id = $_SESSION['user_id'];
} else {
    // Admin can view any attempt
    $user_id = null;
}

// Get attempt info - ensure it belongs to this student (or admin viewing)
if ($user_id) {
    $attempt = db_fetch("
        SELECT 
            sa.*,
            sp.title as package_title,
            sp.description as package_description
        FROM scorm_attempts sa
        JOIN scorm_packages sp ON sa.package_id = sp.id
        WHERE sa.id = ? AND sa.user_id = ?
    ", [$attempt_id, $user_id]);
} else {
    // Admin viewing - no user restriction
    $attempt = db_fetch("
        SELECT 
            sa.*,
            sp.title as package_title,
            sp.description as package_description
        FROM scorm_attempts sa
        JOIN scorm_packages sp ON sa.package_id = sp.id
        WHERE sa.id = ?
    ", [$attempt_id]);
}

if (!$attempt) {
    // Attempt not found or access denied
    redirect('../student_dashboard.php');
}

$page_title = 'Test Results';

// Get test results for this attempt
$test_results = db_fetch_all("
    SELECT * FROM scorm_test_results 
    WHERE attempt_id = ?
    ORDER BY id
", [$attempt_id]);

// Calculate statistics
$total_questions = count($test_results);
$correct_answers = 0;
$incorrect_answers = 0;

foreach ($test_results as $result) {
    if ($result['is_correct']) {
        $correct_answers++;
    } else {
        $incorrect_answers++;
    }
}

// Fix includes paths
$path_to_root = '../';
require_once '../includes/header.php';
// Note: Sidebar usually expects to be in root, might need adjustment or manual include
require_once '../includes/sidebar.php'; 
?>

<div class="page-container" style="max-width: 1000px; width: 100%; margin: 0 auto; box-sizing: border-box; padding: 0 15px; overflow-x: hidden;">
    <div class="page-header" style="margin-bottom: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <h2 style="font-size: 1.5rem; margin: 0;">📊 Test Results</h2>
            <a href="../scorm_history.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.9rem;">
                <i class="fas fa-arrow-left"></i> Back to History
            </a>
        </div>
    </div>



    <div class="card">
        <div class="card-header" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
            <h3 style="color: var(--primary-color); font-size: 1.2rem; margin: 0;"><?php echo htmlspecialchars($attempt['package_title']); ?></h3>
        </div>
        
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div class="stat-card" style="padding: 20px; height: 100%; display: flex; flex-direction: column; justify-content: center; text-align: center;">
                <h3 style="font-size: 0.9rem; color: #666; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Score</h3>
                <p class="stat-number" style="font-size: 2rem; font-weight: 700; color: <?php echo $attempt['score'] >= 75 ? '#10B981' : '#EF4444'; ?>; margin: 0 0 10px 0;">
                    <?php echo $attempt['score'] !== null ? number_format($attempt['score'], 1) . '%' : 'N/A'; ?>
                </p>
                <div>
                    <?php if (($attempt['score'] ?? 0) >= 75): ?>
                        <span class="badge badge-active" style="font-size: 0.8rem; padding: 4px 8px; background-color: #d1fae5; color: #065f46; border-radius: 4px;">PASSED</span>
                    <?php else: ?>
                        <span class="badge badge-inactive" style="font-size: 0.8rem; padding: 4px 8px; background-color: #fee2e2; color: #991b1b; border-radius: 4px;">FAILED</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card" style="padding: 20px; height: 100%; display: flex; flex-direction: column; justify-content: center; text-align: center;">
                <h3 style="font-size: 0.9rem; color: #666; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Correct</h3>
                <p class="stat-number" style="color: #10B981; font-size: 2rem; font-weight: 700; margin: 0 0 10px 0;"><?php echo $correct_answers; ?></p>
                <p style="color: #999; font-size: 0.8rem; margin: 0;">/ <?php echo $total_questions; ?></p>
            </div>
            
            <div class="stat-card" style="padding: 20px; height: 100%; display: flex; flex-direction: column; justify-content: center; text-align: center;">
                <h3 style="font-size: 0.9rem; color: #666; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Incorrect</h3>
                <p class="stat-number" style="color: #EF4444; font-size: 2rem; font-weight: 700; margin: 0 0 10px 0;"><?php echo $incorrect_answers; ?></p>
                <p style="color: #999; font-size: 0.8rem; margin: 0;">To review</p>
            </div>
            
            <div class="stat-card" style="padding: 20px; height: 100%; display: flex; flex-direction: column; justify-content: center; text-align: center;">
                <h3 style="font-size: 0.9rem; color: #666; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Duration</h3>
                <p class="stat-number" style="font-size: 2rem; font-weight: 700; color: var(--secondary-color); margin: 0 0 10px 0;">
                    <?php 
                    $min = floor($attempt['duration_seconds'] / 60);
                    $sec = $attempt['duration_seconds'] % 60;
                    echo "{$min}m {$sec}s";
                    ?>
                </p>
                <p style="color: #999; font-size: 0.8rem; margin: 0;">Time</p>
            </div>
        </div>
    </div>

    <div style="padding: 15px; background: #f3f4f6; border-radius: 8px; margin-bottom: 30px; display: flex; justify-content: space-between;">
        <div><strong>Test Completed:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($attempt['completed_at'])); ?></div>
        <div><strong>Status:</strong> <?php echo ucfirst($attempt['status']); ?></div>
    </div>

    <?php if (false && !empty($test_results)): // Hidden as per user request ?>
        <h3 style="margin-bottom: 20px;">📝 Detailed Answer Review</h3>
        
        <?php foreach ($test_results as $index => $result): ?>
            <div class="question-result" style="padding: 20px; margin-bottom: 20px; border: 1px solid <?php echo $result['is_correct'] ? '#10B981' : '#EF4444'; ?>; border-left-width: 5px; border-radius: 8px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <h4 style="margin: 0;">Question <?php echo ($index + 1); ?></h4>
                    <span class="badge" style="padding: 4px 8px; border-radius: 4px; font-weight: bold; background-color: <?php echo $result['is_correct'] ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $result['is_correct'] ? '#065f46' : '#991b1b'; ?>;">
                        <?php echo $result['is_correct'] ? 'Correct' : 'Incorrect'; ?>
                    </span>
                </div>

                <?php if ($result['question_text']): ?>
                    <div style="margin-bottom: 15px; font-weight: 500; font-size: 1.1em;">
                        <?php echo nl2br(htmlspecialchars($result['question_text'])); ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                    <div style="padding: 15px; background: <?php echo $result['is_correct'] ? '#ecfdf5' : '#fef2f2'; ?>; border-radius: 6px; word-break: break-word;">
                        <small style="display: block; color: var(--text-muted); margin-bottom: 5px;">Your Answer:</small>
                        <strong style="color: <?php echo $result['is_correct'] ? '#065f46' : '#991b1b'; ?>;">
                            <?php echo nl2br(htmlspecialchars($result['user_answer'])); ?>
                        </strong>
                    </div>
                    
                    <div style="padding: 15px; background: #ecfdf5; border-radius: 6px; word-break: break-word;">
                        <small style="display: block; color: var(--text-muted); margin-bottom: 5px;">Correct Answer:</small>
                        <strong style="color: #065f46;">
                            <?php echo nl2br(htmlspecialchars($result['correct_answer'])); ?>
                        </strong>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; background: #f9fafb; border-radius: 8px;">
            <p style="color: var(--text-muted);">No detailed question data available for this attempt.</p>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="../scorm_player.php?id=<?php echo $attempt['package_id']; ?>" class="btn btn-primary" style="margin-right: 15px;">
            <i class="fas fa-redo"></i> Retake Test
        </a>
        <a href="../scorm_history.php" class="btn btn-secondary">
            <i class="fas fa-list"></i> View All Attempts
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
