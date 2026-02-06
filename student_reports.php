<?php
/**
 * Student Reports
 * Displays comprehensive performance summary and attempt history for the logged-in student.
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Ensure user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    redirect('dashboard.php');
}

$student_id = $_SESSION['user_id'];
$page_title = 'My Reports';

// Get student's attempts (Detailed History)
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

// Get performance summary (Aggregated Stats)
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

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>📊 My Performance Reports</h2>
    <p class="text-muted">Overview of your progress and test history.</p>
</div>

<div class="card" style="margin-bottom: 30px;">
    <div class="card-header">
        <h3 style="display: flex; align-items: center;">
            <i class="fas fa-chart-pie" style="margin-right: 10px; color: var(--primary-color);"></i>
            Performance Summary
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($performance)): ?>
            <p>No performance data available yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Package / Module</th>
                            <th class="text-center">Attempts</th>
                            <th class="text-center">Highest Score</th>
                            <th class="text-center">Average</th>
                            <th class="text-center">Total Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performance as $perf): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($perf['title']); ?></td>
                                <td class="text-center"><?php echo $perf['total_attempts']; ?></td>
                                <td class="text-center">
                                    <span style="font-weight: 600; color: <?php echo $perf['highest_score'] >= 75 ? '#10B981' : '#EF4444'; ?>">
                                        <?php echo round($perf['highest_score'], 1); ?>%
                                    </span>
                                </td>
                                <td class="text-center"><?php echo round($perf['average_score'], 1); ?>%</td>
                                <td class="text-center"><?php 
                                    $min = floor($perf['total_time_spent_seconds'] / 60);
                                    echo "{$min} mins"; 
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 style="display: flex; align-items: center;">
            <i class="fas fa-history" style="margin-right: 10px; color: var(--secondary-color);"></i>
            Detailed Attempt History
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($attempts)): ?>
            <p>No test attempts yet. <a href="my_training.php">Go to Training</a> to start.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Module</th>
                            <th class="text-center">Score</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Time</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <tr>
                                <td><?php echo $attempt['completed_at'] ? date('M j, Y g:i A', strtotime($attempt['completed_at'])) : 'In Progress'; ?></td>
                                <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                <td class="text-center">
                                    <strong><?php echo $attempt['score'] !== null ? round($attempt['score'], 1) . '%' : '-'; ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="status-badge status-<?php echo htmlspecialchars($attempt['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($attempt['status'])); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    $min = floor($attempt['duration_seconds'] / 60);
                                    $sec = $attempt['duration_seconds'] % 60;
                                    echo "{$min}m {$sec}s";
                                    ?>
                                </td>
                                <td class="text-center">
                                    <a href="scormtesting/student_attempt_details.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">
                                        View Report
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
