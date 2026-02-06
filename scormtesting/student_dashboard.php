<?php
/**
 * Student Dashboard
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_login();

// Ensure student role
if ($_SESSION['role'] !== 'student') {
    set_flash('error', 'Access denied.');
    redirect(APP_URL . '/');
}

$page_title = 'My Dashboard';
$user_id = $_SESSION['user_id'];

// Get enrolled packages
$enrolled_packages = db_fetch_all("
    SELECT 
        sp.id,
        sp.title,
        sp.description,
        sp.created_at,
        COUNT(sa.id) as total_attempts,
        AVG(sa.score) as avg_score,
        MAX(sa.completed_at) as last_attempt
    FROM scorm_enrollments se
    JOIN scorm_packages sp ON se.package_id = sp.id
    LEFT JOIN scorm_attempts sa ON sp.id = sa.package_id AND sa.user_id = ?
    WHERE se.user_id = ?
    GROUP BY sp.id, sp.title, sp.description, sp.created_at
    ORDER BY sp.created_at DESC
", [$user_id, $user_id]);

// Get overall stats
$total_attempts = db_fetch("SELECT COUNT(*) as count FROM scorm_attempts WHERE user_id = ?", [$user_id])['count'] ?? 0;
$avg_score = db_fetch("SELECT AVG(score) as avg FROM scorm_attempts WHERE user_id = ? AND score IS NOT NULL", [$user_id])['avg'] ?? 0;

include '../includes/header.php';
?>

<div class="dashboard">
    <div class="main-content">
        <div class="top-bar">
            <h1>👋 Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Enrolled Packages</h3>
                <p class="stat-number"><?php echo count($enrolled_packages); ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Total Attempts</h3>
                <p class="stat-number"><?php echo $total_attempts; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Average Score</h3>
                <p class="stat-number"><?php echo round($avg_score, 2); ?>%</p>
            </div>
        </div>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3>📦 Your SCORM Packages</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($enrolled_packages)): ?>
                        <div class="empty-state">
                            <p>You're not enrolled in any packages yet.</p>
                            <p>Please contact your instructor for enrollment.</p>
                        </div>
                    <?php else: ?>
                        <div class="package-grid">
                            <?php foreach ($enrolled_packages as $package): ?>
                                <div class="package-card">
                                    <h3><?php echo htmlspecialchars($package['title']); ?></h3>
                                    <?php if ($package['description']): ?>
                                        <p><?php echo htmlspecialchars(substr($package['description'], 0, 100)); ?>...</p>
                                    <?php endif; ?>
                                    <div class="package-stats">
                                        <p>Attempts: <strong><?php echo $package['total_attempts']; ?></strong></p>
                                        <p>Avg Score: <strong><?php echo $package['avg_score'] ? round($package['avg_score'], 2) . '%' : 'N/A'; ?></strong></p>
                                        <p>Last: <strong><?php echo $package['last_attempt'] ? format_date($package['last_attempt']) : 'Never'; ?></strong></p>
                                    </div>
                                    <div class="package-actions">
                                        <a href="<?php echo APP_URL; ?>/student/scorm_player.php?id=<?php echo $package['id']; ?>" class="btn btn-primary">▶ Start Test</a>
                                        <a href="<?php echo APP_URL; ?>/student/package_report.php?id=<?php echo $package['id']; ?>" class="btn btn-secondary">📊 Report</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
