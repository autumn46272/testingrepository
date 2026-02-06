<?php
/**
 * SCORM Package Details
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role('admin');

$package_id = get('id');
if (!$package_id) {
    set_flash('error', 'Package not found.');
    redirect(APP_URL . '/admin/scorm_packages.php');
}

// Get package info
$package = db_fetch("SELECT * FROM scorm_packages WHERE id = ?", [$package_id]);
if (!$package) {
    set_flash('error', 'Package not found.');
    redirect(APP_URL . '/admin/scorm_packages.php');
}

$page_title = 'Package Details: ' . $package['title'];

// Get creator info
$creator = db_fetch("SELECT full_name FROM users WHERE id = ?", [$package['created_by']]);

// Get enrollment count
$enrollment_count = db_fetch("SELECT COUNT(*) as count FROM scorm_enrollments WHERE package_id = ?", [$package_id])['count'] ?? 0;

// Get attempt statistics
$attempt_stats = db_fetch("
    SELECT 
        COUNT(*) as total_attempts,
        COUNT(DISTINCT user_id) as unique_students,
        AVG(score) as avg_score,
        MAX(score) as highest_score,
        MIN(score) as lowest_score
    FROM scorm_attempts 
    WHERE package_id = ? AND status = 'completed'
", [$package_id]);

// Get recent attempts
$recent_attempts = db_fetch_all("
    SELECT 
        sa.id,
        u.full_name,
        sa.score,
        sa.completed_at,
        sa.duration_seconds,
        sa.user_id
    FROM scorm_attempts sa
    JOIN users u ON sa.user_id = u.id
    WHERE sa.package_id = ?
    ORDER BY sa.completed_at DESC
    LIMIT 10
", [$package_id]);

include '../includes/header.php';
?>

<div class="dashboard">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>📦 Package Details</h1>
            <div>
                <a href="<?php echo APP_URL; ?>/admin/package_edit.php?id=<?php echo $package_id; ?>" class="btn btn-warning">Edit</a>
                <a href="<?php echo APP_URL; ?>/admin/scorm_packages.php" class="btn btn-secondary">Back to List</a>
            </div>
        </div>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3>Package Information</h3>
                </div>
                <div class="card-body">
                    <table style="width: 100%;">
                        <tr>
                            <td style="width: 200px; font-weight: bold;">Title:</td>
                            <td><?php echo htmlspecialchars($package['title']); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Description:</td>
                            <td><?php echo htmlspecialchars($package['description'] ?: 'No description'); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Folder Path:</td>
                            <td><code><?php echo htmlspecialchars($package['folder_path']); ?></code></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">SCORM Version:</td>
                            <td><?php echo htmlspecialchars($package['version']); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Status:</td>
                            <td>
                                <span class="status-badge <?php echo $package['is_published'] ? 'status-published' : 'status-draft'; ?>">
                                    <?php echo $package['is_published'] ? 'Published' : 'Draft'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Created By:</td>
                            <td><?php echo htmlspecialchars($creator['full_name'] ?? 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Created Date:</td>
                            <td><?php echo format_date($package['created_at'], 'F j, Y \a\t g:i A'); ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;">Enrollments:</td>
                            <td><?php echo $enrollment_count; ?> students</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Statistics</h3>
                </div>
                <div class="card-body">
                    <?php if ($attempt_stats['total_attempts'] > 0): ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h3>Total Attempts</h3>
                                <p class="stat-number"><?php echo $attempt_stats['total_attempts']; ?></p>
                            </div>
                            <div class="stat-card">
                                <h3>Unique Students</h3>
                                <p class="stat-number"><?php echo $attempt_stats['unique_students']; ?></p>
                            </div>
                            <div class="stat-card">
                                <h3>Average Score</h3>
                                <p class="stat-number"><?php echo round($attempt_stats['avg_score'], 2); ?>%</p>
                            </div>
                            <div class="stat-card">
                                <h3>Highest Score</h3>
                                <p class="stat-number"><?php echo round($attempt_stats['highest_score'], 2); ?>%</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <p>No attempts recorded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Recent Attempts</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_attempts)): ?>
                        <p>No attempts yet.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Score</th>
                                    <th>Duration</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_attempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['full_name']); ?></td>
                                        <td><strong><?php echo $attempt['score'] !== null ? round($attempt['score'], 2) . '%' : 'N/A'; ?></strong></td>
                                        <td><?php echo format_duration($attempt['duration_seconds']); ?></td>
                                        <td><?php echo format_date($attempt['completed_at']); ?></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/admin/attempt_details.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                    <a href="<?php echo APP_URL; ?>/admin/enrollments.php" class="btn btn-primary">Manage Enrollments</a>
                    <a href="<?php echo APP_URL; ?>/admin/package_edit.php?id=<?php echo $package_id; ?>" class="btn btn-warning">Edit Package</a>
                    <?php if ($package['is_published']): ?>
                        <a href="<?php echo APP_URL; ?>/student/scorm_player.php?id=<?php echo $package_id; ?>" class="btn btn-info" target="_blank">Preview Test</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
