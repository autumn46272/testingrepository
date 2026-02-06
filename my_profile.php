<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'admin';
$username = $_SESSION['username']; // This is student_id for students

$profile_data = [];
$is_student = ($role == 'student');

if ($is_student) {
    // Fetch from students table
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = :sid");
    $stmt->execute(['sid' => $username]);
    $student = $stmt->fetch();

    if ($student) {
        $profile_data = [
            'Name' => $student['first_name'] . ' ' . $student['last_name'],
            'Student ID' => $student['student_id'],
            'Email' => $student['email'],
            'Branch' => $student['branch'],
            'Status' => $student['status'],
        ];
    }
} else {
    // Fetch from users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :uid");
    $stmt->execute(['uid' => $user_id]);
    $user = $stmt->fetch();

    if ($user) {
        $profile_data = [
            'Name' => $user['first_name'] . ' ' . $user['last_name'],
            'Username' => $user['username'],
            'Role' => $user['role'],
            'User Type' => $user['user_type'],
            'Status' => $user['status'],
        ];
    }
}
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>My Profile</h2>
</div>

<div class="card">
    <h3 style="margin-bottom: 20px; color: var(--secondary-color);">Personal Information</h3>

    <div style="display: grid; grid-template-columns: 1fr; gap: 20px; max-width: 600px;">
        <?php foreach ($profile_data as $label => $value): ?>
            <div style="display: flex; border-bottom: 1px solid #eee; padding: 10px 0;">
                <strong style="width: 150px; color: #555;">
                    <?php echo htmlspecialchars($label); ?>:
                </strong>
                <span class="text-muted">
                    <?php echo htmlspecialchars($value); ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>