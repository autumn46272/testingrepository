<?php
/**
 * Individual Student Report
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role('admin');

$student_id = get('id');
if (!$student_id) {
    set_flash('error', 'Student not found.');
    redirect(APP_URL . '/admin/students.php');
}

// Get student info
$student = db_fetch("SELECT * FROM users WHERE id = ? AND role = 'student'", [$student_id]);
if (!$student) {
    set_flash('error', 'Student not found.');
    redirect(APP_URL . '/admin/students.php');
}

$page_title = 'Student Report - ' . $student['full_name'];

// Get student's attempts
$attempts = db_fetch_all("
    SELECT 
        sa.id,
        sp.title,
        sa.score,
        sa.status,
        sa.completed_at,
        sa.duration_seconds,
        sa.correct_answers,
        sa.total_questions
    FROM scorm_attempts sa
    JOIN scorm_packages sp ON sa.package_id = sp.id
    WHERE sa.user_id = ?
    ORDER BY sa.completed_at DESC
", [$student_id]);

// Get performance summary
$performance = db_fetch_all("
    SELECT 
        sp.title,
        sps.total_attempts,
        sps.highest_score,
        sps.average_score,
        sps.total_time_spent_seconds
    FROM student_performance_summary sps
    JOIN scorm_packages sp ON sps.package_id = sp.id
    WHERE sps.user_id = ?
", [$student_id]);

include '../includes/header.php';
?>

<div class="dashboard">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>📊 Report for <?php echo htmlspecialchars($student['full_name']); ?></h1>
            <a href="<?php echo APP_URL; ?>/admin/students.php" class="btn btn-secondary">Back to Students</a>
        </div>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3>Student Information</h3>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                    <p><strong>Registered:</strong> <?php echo format_date($student['created_at']); ?></p>
                    <p><strong>Last Login:</strong> <?php echo $student['last_login'] ? format_date($student['last_login']) : 'Never'; ?></p>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3>Performance Summary</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($performance)): ?>
                        <p>No performance data available yet.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Package</th>
                                    <th>Total Attempts</th>
                                    <th>Highest Score</th>
                                    <th>Average Score</th>
                                    <th>Time Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance as $perf): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($perf['title']); ?></td>
                                        <td><?php echo $perf['total_attempts']; ?></td>
                                        <td><?php echo round($perf['highest_score'], 2); ?>%</td>
                                        <td><?php echo round($perf['average_score'], 2); ?>%</td>
                                        <td><?php echo format_duration($perf['total_time_spent_seconds']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3>Test Attempts</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($attempts)): ?>
                        <p>No test attempts yet.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Package</th>
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
                                        <td><?php echo htmlspecialchars($attempt['title']); ?></td>
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
                                            <a href="<?php echo APP_URL; ?>/admin/attempt_details.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">Details</a>
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
