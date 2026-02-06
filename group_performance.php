<?php
/**
 * Group Performance Summary
 * Admin/Staff View
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Access Control
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student') {
    redirect('student_dashboard.php');
}

$page_title = 'Group Performance';
$selected_group_id = isset($_GET['group_id']) ? clean_input($_GET['group_id']) : '';

// 1. Fetch All Groups for Dropdown
try {
    $stmt = $pdo->query("SELECT id, group_name, program FROM groups ORDER BY created_at DESC");
    $groups = $stmt->fetchAll();
} catch (PDOException $e) {
    $groups = [];
    $error = "Error loading groups: " . $e->getMessage();
}

$students = [];
$performances = [];

// 2. If Group Selected, Fetch Students & Performance
if ($selected_group_id) {
    try {
        // Fetch Students in Group with User ID
        // Note: Joining with users table on username = student_id to get the user_id used in performance logs
        $stmt_students = $pdo->prepare("
            SELECT s.id, s.student_id, s.first_name, s.last_name, s.email, s.status, u.id as user_id
            FROM students s
            JOIN student_groups sg ON s.id = sg.student_id
            LEFT JOIN users u ON s.student_id = u.username
            WHERE sg.group_id = ?
            ORDER BY s.last_name ASC
        ");
        $stmt_students->execute([$selected_group_id]);
        $students = $stmt_students->fetchAll();

        if (!empty($students)) {
            // Get all user IDs to fetch performance in one query
            // Filter out null user_ids if any student doesn't have a user account yet
            $user_ids = array_filter(array_column($students, 'user_id'));
            
            if (!empty($user_ids)) {
                $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
                
                // Fetch Performance Summaries for these students
                $stmt_perf = $pdo->prepare("
                    SELECT 
                        sps.user_id,
                        sp.title,
                        sps.total_attempts,
                        sps.highest_score,
                        sps.average_score,
                        sps.total_time_spent_seconds
                    FROM student_performance_summary sps
                    JOIN scorm_packages sp ON sps.package_id = sp.id
                    WHERE sps.user_id IN ($placeholders)
                ");
                $stmt_perf->execute(array_values($user_ids));
                $all_perf = $stmt_perf->fetchAll();

                // Organize performance by User ID for easy access
                foreach ($all_perf as $perf) {
                    $performances[$perf['user_id']][] = $perf;
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Error loading data: " . $e->getMessage();
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>📈 Group Performance Summary</h2>
</div>

<!-- Filter Section -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: end;">
        <div style="flex: 1; max-width: 400px;">
            <label class="form-label">Select Group</label>
            <select name="group_id" class="form-control" onchange="this.form.submit()" required>
                <option value="">-- Choose a Group --</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $selected_group_id == $g['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['group_name']); ?> (<?php echo htmlspecialchars($g['program']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($selected_group_id): ?>
            <a href="group_performance.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Results Section -->
<?php if ($selected_group_id): ?>
    <div class="card">
        <?php if (empty($students)): ?>
            <p class="text-muted" style="text-align: center; padding: 20px;">No students found in this group.</p>
        <?php else: ?>
            <div class="table-container">
                <table style="width: 100%; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>Student Name</th>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Modules Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <?php 
                                // map using the user_id attached to the student record
                                $s_perf = isset($student['user_id']) ? ($performances[$student['user_id']] ?? []) : []; 
                                $modules_count = count($s_perf);
                                $has_data = $modules_count > 0;
                            ?>
                            <tr style="border-bottom: 1px solid #e5e7eb; cursor: pointer;" onclick="toggleDetails('<?php echo $student['id']; ?>')">
                                <td>
                                    <?php if ($has_data): ?>
                                    <button class="btn-action-gray toggle-btn" id="btn-<?php echo $student['id']; ?>" style="cursor: pointer;">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <?php else: ?>
                                        <span style="color: #d1d5db; padding-left: 10px;"><i class="fas fa-minus"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--primary-color);">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td>
                                    <span class="badge <?php echo ($student['status']=='Inactive'||$student['status']=='Failed')?'badge-inactive':'badge-active'; ?>">
                                        <?php echo htmlspecialchars($student['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $modules_count; ?> modules
                                </td>
                            </tr>
                            
                            <!-- Hidden Details Row -->
                            <tr id="details-<?php echo $student['id']; ?>" style="display: none; background-color: #f9fafb;">
                                <td colspan="5" style="padding: 0;">
                                    <div style="padding: 15px 15px 15px 65px; border-bottom: 1px solid #e5e7eb;">
                                        <h5 style="margin: 0 0 10px 0; color: var(--secondary-color);">Performance Summary</h5>
                                        <?php if ($has_data): ?>
                                            <table style="width: 100%; border: 1px solid #e5e7eb; background: white; border-radius: 6px; overflow: hidden;">
                                                <thead style="background: #f3f4f6;">
                                                    <tr>
                                                        <th style="font-size: 13px; padding: 8px;">Module</th>
                                                        <th style="font-size: 13px; padding: 8px; text-align: center;">Attempts</th>
                                                        <th style="font-size: 13px; padding: 8px; text-align: center;">High Score</th>
                                                        <th style="font-size: 13px; padding: 8px; text-align: center;">Avg Score</th>
                                                        <th style="font-size: 13px; padding: 8px; text-align: center;">Total Time</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($s_perf as $p): ?>
                                                        <tr>
                                                            <td style="padding: 8px;"><?php echo htmlspecialchars($p['title']); ?></td>
                                                            <td style="padding: 8px; text-align: center;"><?php echo $p['total_attempts']; ?></td>
                                                            <td style="padding: 8px; text-align: center;">
                                                                <span style="font-weight: 600; color: <?php echo $p['highest_score'] >= 75 ? '#10B981' : '#EF4444'; ?>">
                                                                    <?php echo round($p['highest_score'], 1); ?>%
                                                                </span>
                                                            </td>
                                                            <td style="padding: 8px; text-align: center;"><?php echo round($p['average_score'], 1); ?>%</td>
                                                            <td style="padding: 8px; text-align: center;">
                                                                <?php 
                                                                    $min = floor($p['total_time_spent_seconds'] / 60);
                                                                    echo "{$min}m"; 
                                                                ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php elseif(isset($_GET['group_id'])): ?>
    <!-- Initial State or No Group Selected -->
<?php else: ?>
    <div class="card" style="text-align: center; padding: 80px 20px;">
        <i class="fas fa-users" style="font-size: 64px; color: #e5e7eb; margin-bottom: 20px;"></i>
        <h3 style="color: var(--text-muted);">Select a group to view performance reports</h3>
    </div>
<?php endif; ?>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    const btn = document.getElementById('btn-' + id);
    
    // Safety check if row doesn't have details (no button)
    if (!btn) return;
    
    const icon = btn.querySelector('i');
    
    if (row.style.display === 'none') {
        row.style.display = 'table-row';
        icon.classList.remove('fa-chevron-right');
        icon.classList.add('fa-chevron-down');
    } else {
        row.style.display = 'none';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-right');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
