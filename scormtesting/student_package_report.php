<?php
/**
 * Student Package Report
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_login();

if ($_SESSION['role'] !== 'student') {
    set_flash('error', 'Access denied.');
    redirect(APP_URL . '/');
}

$package_id = get('id');
if (!$package_id) {
    set_flash('error', 'Package not found.');
    redirect(APP_URL . '/student/dashboard.php');
}

// Get package info
$package = db_fetch("SELECT * FROM scorm_packages WHERE id = ?", [$package_id]);
if (!$package) {
    set_flash('error', 'Package not found.');
    redirect(APP_URL . '/student/dashboard.php');
}

// Check if student is enrolled
$enrollment = db_fetch(
    "SELECT * FROM scorm_enrollments WHERE package_id = ? AND user_id = ?",
    [$package_id, $_SESSION['user_id']]
);

if (!$enrollment) {
    set_flash('error', 'You are not enrolled in this package.');
    redirect(APP_URL . '/student/dashboard.php');
}

$page_title = 'Report: ' . $package['title'];

// Get attempts for this package
$attempts = db_fetch_all("
    SELECT 
        id,
        attempt_number,
        score,
        status,
        completed_at,
        duration_seconds,
        correct_answers,
        total_questions
    FROM scorm_attempts
    WHERE user_id = ? AND package_id = ?
    ORDER BY completed_at DESC
", [$_SESSION['user_id'], $package_id]);

// Get performance summary
$perf = db_fetch("
    SELECT *
    FROM student_performance_summary
    WHERE user_id = ? AND package_id = ?
", [$_SESSION['user_id'], $package_id]);

include '../includes/header.php';
?>

<div class="dashboard">
    <div class="main-content">
        <div class="top-bar">
            <h1>📊 <?php echo htmlspecialchars($package['title']); ?> - Your Report</h1>
            <a href="<?php echo APP_URL; ?>/student/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <div class="content-area">
            <?php if ($perf): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Attempts</h3>
                        <p class="stat-number"><?php echo $perf['total_attempts']; ?></p>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Highest Score</h3>
                        <p class="stat-number"><?php echo round($perf['highest_score'], 2); ?>%</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Average Score</h3>
                        <p class="stat-number"><?php echo round($perf['average_score'], 2); ?>%</p>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Time Spent</h3>
                        <p class="stat-number"><?php echo format_duration($perf['total_time_spent_seconds']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3>Attempt History</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($attempts)): ?>
                        <p>No attempts yet. <a href="<?php echo APP_URL; ?>/student/scorm_player.php?id=<?php echo $package_id; ?>">Start a test →</a></p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Attempt #</th>
                                    <th>Score</th>
                                    <th>Correct/Total</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo $attempt['attempt_number']; ?></td>
                                        <td><strong><?php echo $attempt['score'] !== null ? round($attempt['score'], 2) . '%' : 'N/A'; ?></strong></td>
                                        <td><?php echo $attempt['correct_answers'] . '/' . $attempt['total_questions']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($attempt['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($attempt['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_duration($attempt['duration_seconds']); ?></td>
                                        <td><?php echo $attempt['completed_at'] ? format_date($attempt['completed_at']) : 'N/A'; ?></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/student/attempt_details.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
