<?php
/**
 * Attendance Sheet & Submission
 * Replaces old class_session_records logic with direct manual entry to academic_records
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Access Control
if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student') {
    redirect('student_dashboard.php');
}

$page_title = 'Attendance Sheet';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Messages
$success_msg = '';
$error_msg = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $date = clean_input($_POST['date']);
    $session = clean_input($_POST['session']);
    $topic = clean_input($_POST['topic']);
    $group_id = clean_input($_POST['group_id']);

    // Arrays from the table
    $student_ids = $_POST['student_ids'] ?? [];
    $absent_ids = $_POST['absent'] ?? [];
    $remarks_arr = $_POST['remarks'] ?? [];

    if (empty($date) || empty($group_id) || empty($topic)) {
        $error_msg = "Date, Group, and Topic are required.";
    } else {
        try {
            $pdo->beginTransaction();

            // Prepare Insert Statement
            $stmt = $pdo->prepare("
                INSERT INTO academic_records 
                (student_id, group_id, program, activity_type, activity_date, session, topic, attendance_status, remarks, created_at)
                VALUES 
                (:sid, :gid, :prog, 'Attendance', :date, :sess, :topic, :status, :remarks, NOW())
            ");

            // Get Group Name
            $stmt_g = $pdo->prepare("SELECT group_name FROM groups WHERE id = ?");
            $stmt_g->execute([$group_id]);
            $group_name = $stmt_g->fetchColumn();

            $count = 0;
            foreach ($student_ids as $sid) {
                // Checkbox Checked = Absent, Unchecked = Present
                $is_absent = isset($absent_ids[$sid]);
                $status = $is_absent ? 'Absent' : 'Present';

                $remark = isset($remarks_arr[$sid]) ? clean_input($remarks_arr[$sid]) : '';

                $stmt->execute([
                    ':sid' => $sid,
                    ':gid' => $group_id,
                    ':prog' => $group_name,
                    ':date' => $date,
                    ':sess' => $session,
                    ':topic' => $topic,
                    ':status' => $status,
                    ':remarks' => $remark
                ]);
                $count++;
            }

            $pdo->commit();
            $success_msg = "Attendance saved successfully for $count students!";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch Groups
$groups = $pdo->query("SELECT id, group_name FROM groups ORDER BY group_name ASC")->fetchAll();

// Get Selected Group to Load Students
$selected_group_id = isset($_GET['group_id']) ? clean_input($_GET['group_id']) : '';
$students = [];

if ($selected_group_id) {
    // Fetch students in this group
    $stmt_s = $pdo->prepare("
        SELECT s.id, s.student_id, s.first_name, s.last_name 
        FROM students s
        JOIN student_groups sg ON s.id = sg.student_id
        WHERE sg.group_id = ? AND s.status = 'Active'
        ORDER BY s.last_name ASC
    ");
    $stmt_s->execute([$selected_group_id]);
    $students = $stmt_s->fetchAll();
}
?>

<div class="page-header">
    <h2>Attendance Sheet</h2>
</div>

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if ($success_msg): ?>
    <script>
        Swal.fire({
            title: 'Success!',
            text: '<?php echo $success_msg; ?>',
            icon: 'success',
            confirmButtonColor: '#10B981'
        });
    </script>
<?php endif; ?>
<?php if ($error_msg): ?>
    <script>
        Swal.fire({
            title: 'Error!',
            text: '<?php echo addslashes($error_msg); ?>',
            icon: 'error',
            confirmButtonColor: '#EF4444'
        });
    </script>
<?php endif; ?>

<div class="card">
    <form method="POST" action="">
        <!-- Top Filters / Settings -->
        <!-- Note: We use GET to reload page with students when group changes, then POST to save -->
        <!-- To handle this, we can use a separate script or just a simple onchange for the group dropdown that submits to GET -->

        <div style="display: grid; grid-template-columns: 200px 200px 1fr; gap: 20px; margin-bottom: 24px;">
            <div class="form-group">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Session</label>
                <select name="session" class="form-control">
                    <option value="AM Session">AM Session</option>
                    <option value="PM Session">PM Session</option>
                    <option value="Whole Day">Whole Day</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Topic</label>
                <input type="text" name="topic" class="form-control" placeholder="e.g. Anatomy" required>
            </div>
        </div>

        <!-- Group Selector (Submits GET on change) -->
        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label">Batch / Group</label>
            <select name="group_id" id="group_id" class="form-control"
                onchange="window.location.href='attendance_sheet.php?group_id='+this.value">
                <option value="">-- Select Group --</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $selected_group_id == $g['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['group_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($selected_group_id && !empty($students)): ?>
            <!-- Student Table -->
            <div class="table-container" style="border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                        <tr>
                            <th
                                style="padding: 12px 16px; text-align: left; font-size: 12px; color: #6b7280; font-weight: 600;">
                                #</th>
                            <th
                                style="padding: 12px 16px; text-align: left; font-size: 12px; color: #6b7280; font-weight: 600;">
                                STUDENT NAME</th>
                            <th
                                style="padding: 12px 16px; text-align: center; font-size: 12px; color: #6b7280; font-weight: 600;">
                                ABSENT? <span style="font-size:11px; color: #166534; cursor: pointer; margin-left: 8px;"
                                    onclick="markAllPresent()">[Mark All Present]</span>
                            </th>
                            <th
                                style="padding: 12px 16px; text-align: left; font-size: 12px; color: #6b7280; font-weight: 600;">
                                REMARKS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $s): ?>
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 16px; color: #9ca3af;"><?php echo $index + 1; ?></td>
                                <td style="padding: 16px; font-weight: 600; color: #374151;">
                                    <?php echo htmlspecialchars($s['last_name'] . ', ' . $s['first_name']); ?>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <input type="hidden" name="student_ids[]" value="<?php echo $s['id']; ?>">
                                    <!-- Checkbox: Value Sent only if Checked -->
                                    <input type="checkbox" name="absent[<?php echo $s['id']; ?>]" value="1"
                                        class="status-checkbox" style="width: 20px; height: 20px; cursor: pointer;">
                                </td>
                                <td style="padding: 16px;">
                                    <input type="text" name="remarks[<?php echo $s['id']; ?>]" class="form-control"
                                        placeholder="Optional" style="width: 100%;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 24px; text-align: right;">
                <button type="submit" name="save_attendance" class="btn btn-primary"
                    style="background-color: #991b1b; border-color: #991b1b;">
                    <i class="fas fa-save" style="margin-right: 8px;"></i> Save Record
                </button>
            </div>
        <?php elseif ($selected_group_id): ?>
            <p style="text-align: center; color: #6b7280; padding: 40px;">No students found in this group.</p>
        <?php endif; ?>

    </form>
</div>

<script>
    function markAllPresent() {
        const checkboxes = document.querySelectorAll('.status-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = false; // Uncheck all to mark as Present
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>