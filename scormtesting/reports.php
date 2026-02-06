<?php
/**
 * Reports - Overall Test Results
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role('admin');

$page_title = 'Reports';

// Get overall statistics
$total_attempts = db_fetch("SELECT COUNT(*) as count FROM scorm_attempts")['count'] ?? 0;
$completed_attempts = db_fetch("SELECT COUNT(*) as count FROM scorm_attempts WHERE status = 'completed'")['count'] ?? 0;
$avg_score = db_fetch("SELECT AVG(score) as avg FROM scorm_attempts WHERE score IS NOT NULL")['avg'] ?? 0;

// Get recent attempts
$recent_attempts = db_fetch_all("
    SELECT 
        sa.id,
        u.full_name,
        sp.title as package_title,
        sa.score,
        sa.status,
        sa.completed_at,
        sa.duration_seconds
    FROM scorm_attempts sa
    JOIN users u ON sa.user_id = u.id
    JOIN scorm_packages sp ON sa.package_id = sp.id
    ORDER BY sa.completed_at DESC
    LIMIT 50
");

include '../includes/header.php';
?>

<div class="dashboard">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>📋 Test Reports</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Attempts</h3>
                <p class="stat-number"><?php echo $total_attempts; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Completed Tests</h3>
                <p class="stat-number"><?php echo $completed_attempts; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Average Score</h3>
                <p class="stat-number"><?php echo round($avg_score, 2); ?>%</p>
            </div>
        </div>

        <div class="content-area">
            <div class="card">
                <div class="card-header">
                    <h3>Recent Test Attempts</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_attempts)): ?>
                        <p>No test attempts yet.</p>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Package</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th>Completed Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_attempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['package_title']); ?></td>
                                        <td>
                                            <strong>
                                                <?php echo $attempt['score'] !== null ? round($attempt['score'], 2) . '%' : 'N/A'; ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars($attempt['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($attempt['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo format_duration($attempt['duration_seconds']); ?></td>
                                        <td><?php echo $attempt['completed_at'] ? format_date($attempt['completed_at']) : 'N/A'; ?></td>
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
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
