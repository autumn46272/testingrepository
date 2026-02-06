<?php
/**
 * Individual Attempt Details
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role('admin');

$attempt_id = get('id');
if (!$attempt_id) {
    set_flash('error', 'Attempt not found.');
    redirect(APP_URL . '/admin/reports.php');
}

// Get attempt details
$attempt = db_fetch("
    SELECT 
        sa.*,
        u.full_name,
        u.email,
        sp.title as package_title
    FROM scorm_attempts sa
    JOIN users u ON sa.user_id = u.id
    JOIN scorm_packages sp ON sa.package_id = sp.id
    WHERE sa.id = ?
", [$attempt_id]);

if (!$attempt) {
    set_flash('error', 'Attempt not found.');
    redirect(APP_URL . '/admin/reports.php');
}

$page_title = 'Attempt Details - ' . $attempt['full_name'];

// Get test results for this attempt
$test_results = db_fetch_all("
    SELECT *
    FROM scorm_test_results
    WHERE attempt_id = ?
    ORDER BY id ASC
", [$attempt_id]);

include '../includes/header.php';
?>

<div class="dashboard">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>📋 Attempt Details</h1>
            <a href="<?php echo APP_URL; ?>/admin/reports.php" class="btn btn-secondary">Back to Reports</a>
        </div>

        <div class="content-area">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Student</h3>
                    <p class="stat-info">
                        <strong><?php echo htmlspecialchars($attempt['full_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($attempt['email']); ?></small>
                    </p>
                </div>
                
                <div class="stat-card">
                    <h3>Package</h3>
                    <p class="stat-info">
                        <strong><?php echo htmlspecialchars($attempt['package_title']); ?></strong>
                    </p>
                </div>
                
                <div class="stat-card">
                    <h3>Score</h3>
                    <p class="stat-number" style="margin-bottom: 0;">
                        <?php echo $attempt['score'] !== null ? round($attempt['score'], 2) . '%' : 'N/A'; ?>
                    </p>
                </div>
                
                <div class="stat-card">
                    <h3>Status</h3>
                    <p class="stat-info">
                        <span class="status-badge status-<?php echo htmlspecialchars($attempt['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($attempt['status'])); ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Attempt Summary</h3>
                </div>
                <div class="card-body">
                    <table class="settings-table">
                        <tr>
                            <td><strong>Started:</strong></td>
                            <td><?php echo format_date($attempt['started_at'], 'M d, Y H:i A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Completed:</strong></td>
                            <td><?php echo $attempt['completed_at'] ? format_date($attempt['completed_at'], 'M d, Y H:i A') : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Duration:</strong></td>
                            <td><?php echo format_duration($attempt['duration_seconds']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Questions:</strong></td>
                            <td><?php echo $attempt['correct_answers'] . ' / ' . $attempt['total_questions']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Score:</strong></td>
                            <td><?php echo $attempt['score'] !== null ? round($attempt['score'], 2) . '%' : 'N/A'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h3>Question Results</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($test_results)): ?>
                        <p>No question details available.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Question</th>
                                    <th>User Answer</th>
                                    <th>Correct?</th>
                                    <th>Points</th>
                                    <th>Time (s)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($test_results as $idx => $result): ?>
                                    <tr>
                                        <td><?php echo $idx + 1; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($result['question_text']); ?></strong>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($result['user_answer']); ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $result['is_correct'] ? 'status-published' : 'status-failed'; ?>">
                                                <?php echo $result['is_correct'] ? '✓ Correct' : '✗ Wrong'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo round($result['points_earned'], 2); ?> / <?php echo round($result['points_possible'], 2); ?>
                                        </td>
                                        <td><?php echo $result['answer_time_seconds']; ?></td>
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

<style>
.settings-table {
    width: 100%;
    border-collapse: collapse;
}

.settings-table tr {
    border-bottom: 1px solid var(--border-color);
}

.settings-table td {
    padding: 0.75rem;
}

.settings-table td:first-child {
    width: 30%;
    font-weight: 600;
    color: var(--primary-color);
}

.stat-info {
    margin-bottom: 0;
    line-height: 1.6;
}
</style>

<?php include '../includes/footer.php'; ?>
