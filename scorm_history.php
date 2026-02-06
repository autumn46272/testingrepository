<?php
/**
 * SCORM Attempt History
 * Student Database System
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Ensure user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    redirect('dashboard.php');
}

$page_title = 'My Attempt History';
$user_id = $_SESSION['user_id'];

// Get all attempts for this user with package details
try {
    $sql = "SELECT a.*, p.title as package_title, p.folder_path 
            FROM scorm_attempts a 
            JOIN scorm_packages p ON a.package_id = p.id 
            WHERE a.user_id = ? 
            ORDER BY a.started_at DESC";
    $attempts = db_fetch_all($sql, [$user_id]);
} catch (PDOException $e) {
    $attempts = [];
    $error = "Error loading history: " . $e->getMessage();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>⏳ My Attempt History</h2>
</div>

<div class="card">
    <?php if (empty($attempts)): ?>
        <div style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-history" style="font-size: 64px; color: var(--text-muted); margin-bottom: 20px;"></i>
            <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 20px;">You haven't taken any tests yet.</p>

        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date Taken</th>
                        <th>Package / Test Name</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Time Taken</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td>
                                <div><?php echo date('M d, Y', strtotime($attempt['started_at'])); ?></div>
                                <div style="font-size: 0.85em; color: var(--text-muted);">
                                    <?php echo date('g:i A', strtotime($attempt['started_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($attempt['package_title']); ?></strong>
                            </td>
                            <td>
                                <?php
                                $statusClass = 'badge-inactive';
                                $statusLabel = 'In Progress';
                                
                                if ($attempt['status'] === 'completed') {
                                    if (($attempt['score'] ?? 0) >= 75) { // Assuming 75 is passing
                                        $statusClass = 'badge-active';
                                        $statusLabel = 'Passed';
                                    } else {
                                        $statusClass = 'badge-inactive'; // Or a specific warning class
                                        $statusLabel = 'Failed';
                                    }
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (isset($attempt['score'])): ?>
                                    <span style="font-weight: bold; <?php echo ($attempt['score'] >= 75) ? 'color: #10B981;' : 'color: #EF4444;'; ?>">
                                        <?php echo number_format($attempt['score'], 1); ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">--</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if (!empty($attempt['duration_seconds'])) {
                                    $min = floor($attempt['duration_seconds'] / 60);
                                    $sec = $attempt['duration_seconds'] % 60;
                                    echo "{$min}m {$sec}s";
                                } else {
                                    echo '<span class="text-muted">--</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($attempt['status'] === 'completed'): ?>
                                    <a href="scormtesting/student_attempt_details.php?id=<?php echo $attempt['id']; ?>" 
                                       class="btn-action-gray" title="View Details">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="scorm_player.php?id=<?php echo $attempt['package_id']; ?>" 
                                   class="btn-action-gray" title="Retake">
                                    <i class="fas fa-redo"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
