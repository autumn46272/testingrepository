<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

if ($_SESSION['role'] !== 'student') {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>My Training</h2>
</div>

<?php
// Get the student's numeric ID
$username = $_SESSION['username'];
$stmt_student = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
$stmt_student->execute([$username]);
$student_id_row = $stmt_student->fetch();

if ($student_id_row) {
    $student_id = $student_id_row['id'];

    // Fetch enrolled courses
    $stmt_courses = $pdo->prepare("
        SELECT c.*, sc.enrolled_at 
        FROM courses c
        JOIN student_courses sc ON c.id = sc.course_id
        WHERE sc.student_id = ?
        ORDER BY sc.enrolled_at DESC
    ");
    $stmt_courses->execute([$student_id]);
    $my_courses = $stmt_courses->fetchAll();
} else {
    $my_courses = [];
}
?>

<div class="card">
    <h3 style="margin-bottom: 20px; color: var(--secondary-color);">Assigned Training Modules</h3>

    <?php if (count($my_courses) > 0): ?>
        <div class="course-grid"
            style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ($my_courses as $course): ?>
                <div class="course-card"
                    style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; transition: transform 0.2s; background: white;">
                    <div style="padding: 20px;">
                        <h4 style="margin-top: 0; margin-bottom: 10px; color: var(--primary-color);">
                            <?php echo htmlspecialchars($course['course_name']); ?>
                        </h4>
                        <p
                            style="color: #6b7280; font-size: 14px; margin-bottom: 15px; line-height: 1.5; height: 63px; overflow: hidden;">
                            <?php echo htmlspecialchars(substr($course['description'] ?? '', 0, 100)) . (strlen($course['description'] ?? '') > 100 ? '...' : ''); ?>
                        </p>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                            <span style="font-size: 12px; color: #9ca3af;">
                                Enrolled: <?php echo date('M d, Y', strtotime($course['enrolled_at'])); ?>
                            </span>
                            <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-primary"
                                style="padding: 8px 16px; font-size: 14px;">
                                Start Training
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">You have no assigned training modules at the moment. Please check back later or contact your
            administrator.</p>
    <?php endif; ?>
</div>

<style>
    .course-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
</style>

<?php require_once 'includes/footer.php'; ?>