<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Redirect students to their own dashboard
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("Location: student_dashboard.php");
    exit();
}


// Fetch Statistics
try {
    // Total Students
    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $total_students = $stmt->fetchColumn();

    // Active Students
    $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'");
    $active_students = $stmt->fetchColumn();

    // Average Score (across all academic records)
    $stmt = $pdo->query("SELECT AVG(score) FROM academic_records WHERE score IS NOT NULL");
    $avg_score = number_format($stmt->fetchColumn(), 1);

    // Recent Activity linked to Students
    $stmt = $pdo->query("
        SELECT ar.*, s.first_name, s.last_name 
        FROM academic_records ar 
        JOIN students s ON ar.student_id = s.id 
        ORDER BY ar.created_at DESC 
        LIMIT 5
    ");
    $recent_activities = $stmt->fetchAll();

} catch (PDOException $e) {
    // Handle error gracefully in production
    $total_students = 0;
    $active_students = 0;
    $avg_score = 0;
    $recent_activities = [];
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>Dashboard</h2>
</div>

<!-- Summary Cards -->
<div class="stat-grid">
    <div class="stat-card highlight">
        <div>
            <div class="stat-value">
                <?php echo $total_students; ?>
            </div>
            <div class="stat-label">Total Students</div>
        </div>
        <i class="fas fa-users fa-2x" style="color: var(--primary-color); opacity: 0.5;"></i>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value">
                <?php echo $active_students; ?>
            </div>
            <div class="stat-label">Active Students</div>
        </div>
        <i class="fas fa-user-check fa-2x" style="color: var(--secondary-color); opacity: 0.5;"></i>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value">
                <?php echo $avg_score; ?>%
            </div>
            <div class="stat-label">Avg. Academic Score</div>
        </div>
        <i class="fas fa-chart-line fa-2x" style="color: var(--secondary-color); opacity: 0.5;"></i>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-value">95%</div> <!-- Placeholder for complex calculation -->
            <div class="stat-label">Attendance Rate</div>
        </div>
        <i class="fas fa-calendar-check fa-2x" style="color: var(--secondary-color); opacity: 0.5;"></i>
    </div>
</div>

<!-- Recent Activity -->
<div class="card">
    <h3 style="margin-bottom: 20px; color: var(--secondary-color);">Recent Student Activity</h3>
    <?php if (count($recent_activities) > 0): ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Activity</th>
                        <th>Date</th>
                        <th>Score/Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activities as $activity): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                </strong>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($activity['activity_type'] . ' - ' . $activity['program']); ?>
                            </td>
                            <td>
                                <?php echo format_date($activity['activity_date']); ?>
                            </td>
                            <td>
                                <?php
                                if ($activity['score'] !== null)
                                    echo htmlspecialchars($activity['score']) . '%';
                                else
                                    echo htmlspecialchars($activity['attendance_status']);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No recent activity found.</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>