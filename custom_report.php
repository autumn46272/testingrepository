<?php
/**
 * Custom Report: Class List Attendance
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Access Control - Admin/Staff Only
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student') {
    redirect('student_dashboard.php');
}

$page_title = 'Class Attendance';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Get Filters
$selected_group_id = isset($_GET['group_id']) ? clean_input($_GET['group_id']) : '';
$selected_date = isset($_GET['date']) ? clean_input($_GET['date']) : date('Y-m-d');

// 1. Fetch Groups for Dropdown
try {
    $stmt = $pdo->query("SELECT id, group_name FROM groups ORDER BY group_name ASC");
    $groups = $stmt->fetchAll();
} catch (PDOException $e) {
    $groups = [];
    $error = "Error loading groups: " . $e->getMessage();
}

$students = [];

// 2. Fetch Data if Group Selected
if ($selected_group_id) {
    try {
        // Base: All students in the group
        // Left Join: Attendance for the SPECIFIC DATE
        // Note: We use LEFT JOIN so we still get students who have NO attendance (Absent)
        $sql = "
            SELECT 
                s.first_name,
                s.last_name,
                s.student_id as student_code,
                s.rfid,
                p.entry_date,
                TIME(p.entry_date) as time_in
            FROM students s
            JOIN student_groups sg ON s.id = sg.student_id
            LEFT JOIN people p ON s.rfid = p.name AND DATE(p.entry_date) = :selected_date
            WHERE sg.group_id = :group_id
            ORDER BY s.last_name ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':group_id' => $selected_group_id,
            ':selected_date' => $selected_date
        ]);
        $students = $stmt->fetchAll();

    } catch (PDOException $e) {
        $error = "Error fetching data: " . $e->getMessage();
    }
}
?>

<div class="page-header">
    <h2>📋 Class List Attendance</h2>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <label class="form-label">Select Group</label>
            <select name="group_id" class="form-control" required>
                <option value="">-- Choose a Group --</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $selected_group_id == $g['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['group_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 0 0 200px;">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?php echo $selected_date; ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($selected_group_id): ?>
            <a href="custom_report.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($selected_group_id): ?>
    <div class="card">
        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Time In</th>
                        <th>RFID Tag</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No students found in this group.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $row): ?>
                            <?php 
                                $is_present = !empty($row['entry_date']);
                                $status_class = $is_present ? 'badge-active' : 'badge-inactive'; // Use existing badge styles
                                $status_text = $is_present ? 'Present' : 'Absent';
                            ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>" 
                                          style="background-color: <?php echo $is_present ? '#10B981' : '#EF4444'; ?>; color: white;">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: var(--primary-color);">
                                        <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['student_code']); ?></td>
                                <td>
                                    <?php if ($is_present): ?>
                                        <?php echo date('h:i A', strtotime($row['time_in'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo htmlspecialchars($row['rfid'] ?? 'N/A'); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php elseif(!isset($error)): ?>
    <div class="card text-center" style="padding: 40px;">
        <p class="text-muted">Please select a Group and Date to view attendance.</p>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
