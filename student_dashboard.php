<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

//Redirect if not student (optional, but good practice)
if ($_SESSION['role'] !== 'student') {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>Student Dashboard</h2>
    <p class="text-muted">Welcome back,
        <?php echo htmlspecialchars($_SESSION['first_name']); ?>!
    </p>
</div>

<!-- Summary Cards for Student -->
<div class="stat-grid">
    <div class="stat-card highlight">
        <div>
            <div class="stat-value">0</div>
            <div class="stat-label">Assigned Courses</div>
        </div>
        <i class="fas fa-book-reader fa-2x" style="color: var(--primary-color); opacity: 0.5;"></i>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value">0%</div>
            <div class="stat-label">Average Score</div>
        </div>
        <i class="fas fa-chart-line fa-2x" style="color: var(--secondary-color); opacity: 0.5;"></i>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value">Active</div>
            <div class="stat-label">My Status</div>
        </div>
        <i class="fas fa-user-check fa-2x" style="color: var(--secondary-color); opacity: 0.5;"></i>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="color: var(--secondary-color); margin: 0;">My Recent Activity</h3>
        <a href="scorm_history.php" class="btn btn-secondary" style="font-size: 0.9rem; padding: 6px 12px;">View All</a>
    </div>

    <?php
    // Get recent attempts
    $recent_attempts = [];
    if (isset($_SESSION['user_id'])) {
        try {
            $sql = "SELECT a.*, p.title as package_title 
                    FROM scorm_attempts a 
                    JOIN scorm_packages p ON a.package_id = p.id 
                    WHERE a.user_id = ? 
                    ORDER BY a.started_at DESC 
                    LIMIT 5";
            $recent_attempts = db_fetch_all($sql, [$_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Silently fail or log error
        }
    }
    ?>

    <?php if (empty($recent_attempts)): ?>
        <p class="text-muted">No recent activity found.</p>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Test Name</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_attempts as $attempt): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($attempt['started_at'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($attempt['package_title']); ?></strong></td>
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
                                $statusClass = ($attempt['status'] === 'completed') 
                                    ? (($attempt['score'] ?? 0) >= 75 ? 'badge-active' : 'badge-inactive') 
                                    : 'badge-inactive';
                                $statusLabel = ($attempt['status'] === 'completed') 
                                    ? (($attempt['score'] ?? 0) >= 75 ? 'Passed' : 'Failed') 
                                    : 'In Progress';
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusLabel; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($attempt['status'] === 'completed'): ?>
                                    <a href="scormtesting/student_attempt_details.php?id=<?php echo $attempt['id']; ?>" 
                                       class="btn-action-gray" title="View Report">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>