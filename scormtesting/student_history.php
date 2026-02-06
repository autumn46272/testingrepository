<?php
/**
 * Student Test History
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role('student');

$user_id = $_SESSION['user_id'];
$page_title = 'Test History';

// Get all attempts for this student
$attempts = db_fetch_all("
    SELECT 
        sa.*,
        sp.title as package_title
    FROM scorm_attempts sa
    JOIN scorm_packages sp ON sa.package_id = sp.id
    WHERE sa.user_id = ?
    ORDER BY sa.started_at DESC
", [$user_id]);

include '../includes/header.php';
?>

<div class="dashboard">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>📚 Test History</h1>
            <a href="<?php echo APP_URL; ?>/student/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3>All Test Attempts (<?php echo count($attempts); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($attempts)): ?>
                        <p>You haven't taken any tests yet.</p>
                        <a href="<?php echo APP_URL; ?>/student/dashboard.php" class="btn btn-primary">Start Your First Test</a>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Test Name</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts as $attempt): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($attempt['package_title']); ?></strong></td>
                                        <td>
                                            <span style="font-size: 1.2em; font-weight: bold; color: <?php echo ($attempt['score'] !== null && $attempt['score'] >= 70) ? '#28a745' : '#dc3545'; ?>;">
                                                <?php echo $attempt['score'] !== null ? round($attempt['score'], 2) . '%' : 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $attempt['status']; ?>">
                                                <?php echo ucfirst($attempt['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_duration($attempt['duration_seconds']); ?></td>
                                        <td><?php echo format_date($attempt['completed_at'] ?: $attempt['started_at']); ?></td>
                                        <td>
                                            <?php if ($attempt['status'] === 'completed'): ?>
                                                <a href="<?php echo APP_URL; ?>/student/attempt_details.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">View Results</a>
                                            <?php else: ?>
                                                <a href="<?php echo APP_URL; ?>/student/scorm_player.php?package_id=<?php echo $attempt['package_id']; ?>&resume=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-warning">Resume</a>
                                            <?php endif; ?>
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
