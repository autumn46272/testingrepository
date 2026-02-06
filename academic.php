<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$error = '';
$success = '';
$keep_open = false;
$last_student_id = '';
$last_group_id = '';

// Handle Topic Management
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_topic'])) {
        $new_topic = clean_input($_POST['new_topic']);
        if (!empty($new_topic)) {
            try {
                $pdo->prepare("INSERT INTO topics (topic_name) VALUES (?)")->execute([$new_topic]);
                $success = "Topic added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding topic: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_topic'])) {
        $topic_id = clean_input($_POST['topic_id']);
        try {
            $pdo->prepare("DELETE FROM topics WHERE id = ?")->execute([$topic_id]);
            $success = "Topic deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting topic.";
        }
    }
}

// Fetch Topics for Dropdown
$topics_db = $pdo->query("SELECT * FROM topics ORDER BY topic_name ASC")->fetchAll();
$topics = array_column($topics_db, 'topic_name');

// Handle Delete Record
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_record'])) {
    $record_id = clean_input($_POST['record_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM academic_records WHERE id = :id");
        $stmt->execute([':id' => $record_id]);
        $success = "Record deleted successfully!";
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Handle Add/Edit Record
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['save_record']) || isset($_POST['save_and_add']))) {
    $record_id = isset($_POST['record_id']) ? clean_input($_POST['record_id']) : '';
    $student_id = clean_input($_POST['student_id']);
    $group_id = clean_input($_POST['group_id']);
    $topic = clean_input($_POST['topic']);
    $activity_type = clean_input($_POST['activity_type']);
    $date = clean_input($_POST['date']);
    $score = !empty($_POST['score']) ? clean_input($_POST['score']) : null;
    $total_items = !empty($_POST['total_items']) ? clean_input($_POST['total_items']) : null;
    $attendance_status = !empty($_POST['attendance_status']) ? clean_input($_POST['attendance_status']) : null;
    $remarks = clean_input($_POST['remarks']);
    $session = !empty($_POST['session']) ? clean_input($_POST['session']) : null;

    if ($activity_type !== 'Attendance') {
        $attendance_status = null;
        $session = null;
    }

    if (empty($student_id) || empty($group_id) || empty($topic) || empty($activity_type) || empty($date)) {
        $error = "Required fields missing.";
    } else {
        try {
            $stmt_group = $pdo->prepare("SELECT group_name FROM groups WHERE id = :gid");
            $stmt_group->execute([':gid' => $group_id]);
            $program = $stmt_group->fetchColumn();

            if (!empty($record_id)) {
                // UPDATE
                $stmt = $pdo->prepare("UPDATE academic_records SET student_id=:sid, group_id=:gid, program=:prog, activity_type=:type, activity_date=:date, score=:score, total_items=:items, attendance_status=:status, session=:sess, remarks=:remarks, topic=:topic WHERE id=:rid");
                $stmt->execute([
                    'sid' => $student_id,
                    'gid' => $group_id,
                    'prog' => $program,
                    'type' => $activity_type,
                    'date' => $date,
                    'score' => $score,
                    'items' => $total_items,
                    'status' => $attendance_status,
                    'sess' => $session,
                    'remarks' => $remarks,
                    'topic' => $topic,
                    'rid' => $record_id
                ]);
                $success = "Record updated successfully!";
            } else {
                // INSERT
                $stmt = $pdo->prepare("INSERT INTO academic_records (student_id, group_id, program, activity_type, activity_date, score, total_items, attendance_status, session, remarks, topic) VALUES (:sid, :gid, :prog, :type, :date, :score, :items, :status, :sess, :remarks, :topic)");
                $stmt->execute([
                    'sid' => $student_id,
                    'gid' => $group_id,
                    'prog' => $program,
                    'type' => $activity_type,
                    'date' => $date,
                    'score' => $score,
                    'items' => $total_items,
                    'status' => $attendance_status,
                    'sess' => $session,
                    'remarks' => $remarks,
                    'topic' => $topic
                ]);
                $success = "Record added successfully!";

                // Logic for "Save & Add Activity"
                if (isset($_POST['save_and_add'])) {
                    $keep_open = true;
                    $last_student_id = $student_id;
                    $last_group_id = $group_id;
                    $success = "Record saved! Ready for next entry.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch Records
$search_keyword = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$activity_filter = isset($_GET['activity_type']) ? clean_input($_GET['activity_type']) : '';

$query = "SELECT ar.*, s.first_name, s.last_name, s.student_id as sid_code, s.group_id as student_group_id, g.group_name
    FROM academic_records ar 
    JOIN students s ON ar.student_id = s.id 
    LEFT JOIN groups g ON ar.group_id = g.id
    WHERE 1=1";
$params = [];

if (!empty($activity_filter)) {
    $query .= " AND ar.activity_type = :activity";
    $params[':activity'] = $activity_filter;
}
$query .= " ORDER BY ar.activity_date DESC, ar.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Fetch Students
$students = $pdo->query("SELECT s.id, s.first_name, s.last_name, s.student_id, GROUP_CONCAT(sg.group_id) as group_ids FROM students s LEFT JOIN student_groups sg ON s.id = sg.student_id WHERE s.status='Active' GROUP BY s.id ORDER BY s.last_name ASC")->fetchAll();
$groups_list = $pdo->query("SELECT id, group_name, course_code FROM groups ORDER BY group_name ASC")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header"
    style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>Academic Records</h2>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-secondary" onclick="document.getElementById('manageTopicsModal').style.display='block'">
            <i class="fas fa-list" style="margin-right: 8px;"></i> Manage Topics
        </button>
        <button class="btn btn-secondary" onclick="window.location.href='academic_batch_add.php'">
            <i class="fas fa-layer-group" style="margin-right: 8px;"></i> Add Batch Activity
        </button>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus" style="margin-right: 8px;"></i> Add Activity
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4" style="margin-bottom: 20px;">
    <div style="display: flex; gap: 12px; align-items: flex-end;">
        <div class="form-group" style="flex: 2; margin-bottom: 0;">
            <label class="form-label">Real-time Search</label>
            <input type="text" id="searchInput" class="form-control" placeholder="Search Student, Program, or Topic..."
                onkeyup="filterTable()" style="height: 38px;">
        </div>
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <form method="GET" action="academic.php" style="display: flex; gap: 10px; align-items: flex-end;">
                <div style="flex: 1;">
                    <label class="form-label">Activity Type</label>
                    <select name="activity_type" class="form-control" style="height: 38px; padding-top: 6px; padding-bottom: 6px;"
                        onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="Pre-Test" <?php echo $activity_filter == 'Pre-Test' ? 'selected' : ''; ?>>Pre-Test
                        </option>
                        <option value="Post-Test" <?php echo $activity_filter == 'Post-Test' ? 'selected' : ''; ?>>
                            Post-Test</option>
                        <option value="Qbank" <?php echo $activity_filter == 'Qbank' ? 'selected' : ''; ?>>Qbank</option>
                        <option value="Attendance" <?php echo $activity_filter == 'Attendance' ? 'selected' : ''; ?>>
                            Attendance</option>
                    </select>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Records Table -->
<div class="card">
    <div class="table-container">
        <table id="academicTable">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="selectAll" onclick="toggleAllRecords(this)">
                    </th>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Activity Type</th>
                    <th>Program / Group</th>
                    <th>Result / Status</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $rec): ?>
                    <tr>
                        <td><input type="checkbox" class="record-checkbox"></td>
                        <td>
                            <?php echo format_date($rec['activity_date']); ?>
                            <?php if (!empty($rec['session'])): ?>
                                <span class="badge"
                                    style="background-color: #f3f4f6; color: #374151; margin-left: 5px; font-size: 11px;"><?php echo htmlspecialchars($rec['session']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($rec['last_name'] . ', ' . $rec['first_name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($rec['sid_code']); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($rec['activity_type']); ?>
                            <?php if (!empty($rec['topic'])): ?>
                                <br><small class="text-muted"
                                    style="color: var(--secondary-color);"><?php echo htmlspecialchars($rec['topic']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($rec['group_name'] ?? $rec['program']); ?>
                        </td>
                        <td>
                            <?php
                            if ($rec['score'] !== null) {
                                $display_score = (float) $rec['score'] == (int) $rec['score'] ? (int) $rec['score'] : $rec['score'];
                                echo '<span style="font-weight:bold; color:var(--primary-color);">' . $display_score . '</span>';
                            }
                            $show_status = false;
                            if ($rec['attendance_status']) {
                                if ($rec['activity_type'] === 'Attendance')
                                    $show_status = true;
                                elseif ($rec['attendance_status'] !== 'Present')
                                    $show_status = true;
                            }
                            if ($rec['score'] !== null && $show_status)
                                echo '<br>';
                            if ($show_status)
                                echo '<span class="badge ' . ($rec['attendance_status'] == 'Present' ? 'badge-active' : 'badge-inactive') . '">' . $rec['attendance_status'] . '</span>';
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($rec['remarks']); ?></td>
                        <td>
                            <div style="display: flex; gap: 4px;">
                                <button class="btn-action-gray" title="Edit"
                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($rec)); ?>)"><i
                                        class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Delete this record?');">
                                    <input type="hidden" name="delete_record" value="1">
                                    <input type="hidden" name="record_id" value="<?php echo $rec['id']; ?>">
                                    <button type="submit" class="btn-action-gray delete" title="Delete"><i
                                            class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px;">No academic records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
if ($success || $error) {
    $msg = $success ?: $error;
    $type = $success ? 'success' : 'error';
    echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($msg) . "', '$type'); });</script>";
}
?>

<?php if ($keep_open && $last_student_id && $last_group_id): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Automatically open modal and pre-fill for Save & Add Flow
            setTimeout(function () {
                openAddModal();
                const groupSelect = document.getElementById('group_id');
                groupSelect.value = '<?php echo $last_group_id; ?>';
                filterStudentsByGroup(); // Update student dropdown based on group
                document.getElementById('student_id').value = '<?php echo $last_student_id; ?>';
                document.getElementById('modalTitle').innerText = 'Add Another Activity';
            }, 300); // Small delay to ensure DOM is ready and Toast doesn't conflict
        });
    </script>
<?php endif; ?>

<!-- Manage Topics Modal -->
<div id="manageTopicsModal" class="modal">
    <div class="modal-content" style="width: 500px;">
        <div
            style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px;">Manage Topics</h3>
            <span class="modal-close"
                onclick="document.getElementById('manageTopicsModal').style.display='none'">&times;</span>
        </div>
        <div style="padding: 24px;">
            <form method="POST" style="display: flex; gap: 10px; margin-bottom: 20px;">
                <input type="text" name="new_topic" class="form-control" placeholder="New Topic Name" required>
                <button type="submit" name="add_topic" class="btn btn-primary">Add</button>
            </form>
            <div style="border-top: 1px solid #e5e7eb; padding-top: 15px;">
                <label class="form-label">Existing Topics</label>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px;">
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($topics_db as $t): ?>
                            <li
                                style="padding: 10px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                                <span><?php echo htmlspecialchars($t['topic_name']); ?></span>
                                <form method="POST" onsubmit="return confirm('Delete topic?');" style="margin: 0;">
                                    <input type="hidden" name="topic_id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" name="delete_topic" class="btn-action-gray delete"
                                        style="border: none; background: none; cursor: pointer;"><i
                                            class="fas fa-trash-alt"></i></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit Record -->
<div id="addRecordModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div
            style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalTitle" style="margin: 0; font-size: 18px;">Add Academic Record</h3>
            <span class="modal-close"
                onclick="document.getElementById('addRecordModal').style.display='none'">&times;</span>
        </div>

        <form method="post" id="activityForm" style="padding: 24px;">
            <input type="hidden" name="record_id" id="record_id">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Batch / Group <span class="text-danger">*</span></label>
                    <select name="group_id" id="group_id" class="form-control" required
                        onchange="filterStudentsByGroup()">
                        <option value="">-- Select Batch/Group --</option>
                        <?php foreach ($groups_list as $g): ?>
                            <option value="<?php echo $g['id']; ?>">
                                <?php echo htmlspecialchars($g['group_name'] . ' (' . $g['course_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Student Name <span class="text-danger">*</span></label>
                    <select name="student_id" id="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-group="<?php echo $s['group_ids']; ?>">
                                <?php echo htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ' (' . $s['student_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Activity Type <span class="text-danger">*</span></label>
                    <select name="activity_type" id="activity_type" class="form-control" required
                        onchange="toggleFields()">
                        <option value="Pre-Test">Pre-Test</option>
                        <option value="Post-Test">Post-Test</option>
                        <option value="Qbank">Qbank</option>
                        <option value="Attendance">Attendance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Topic <span class="text-danger">*</span></label>
                    <select name="topic" id="topic" class="form-control" required>
                        <option value="">-- Select Topic --</option>
                        <?php foreach ($topics_db as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['topic_name']); ?>">
                                <?php echo htmlspecialchars($t['topic_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" name="date" id="date" class="form-control" style="max-width: 200px;"
                    value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div id="score_fields">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label class="form-label">Score</label><input type="number" name="score"
                            id="score" class="form-control" step="0.01" min="0" placeholder="e.g., 85"></div>
                    <div class="form-group"><label class="form-label">Total Items</label><input type="number"
                            name="total_items" id="total_items" class="form-control" step="1" min="1"
                            placeholder="e.g., 100"></div>
                </div>
            </div>

            <div id="attendance_fields" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Session</label>
                        <select name="session" id="session" class="form-control">
                            <option value="">-- Select Session --</option>
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="attendance_status" id="attendance_status" class="form-control">
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Late">Late</option>
                            <option value="Excused">Excused</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group"><label class="form-label">Remarks</label><textarea name="remarks" id="remarks"
                    class="form-control" rows="2"></textarea></div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn" style="background: #ccc; margin-right: 10px;"
                    onclick="document.getElementById('addRecordModal').style.display='none'">Cancel</button>
                <button type="submit" name="save_and_add" id="btnAddActivity" class="btn btn-secondary"
                    style="margin-right: 10px;">Save & Add Activity</button>
                <button type="submit" name="save_record" id="btnSave" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterStudentsByGroup() {
        const groupId = document.getElementById('group_id').value;
        const studentSelect = document.getElementById('student_id');
        const options = studentSelect.querySelectorAll('option');
        options.forEach(option => {
            if (option.value === '') { option.style.display = 'block'; return; }
            const studentGroups = option.getAttribute('data-group') ? option.getAttribute('data-group').split(',') : [];
            if (!groupId) { option.style.display = 'block'; }
            else { option.style.display = studentGroups.includes(groupId) ? 'block' : 'none'; }
        });
        if (studentSelect.value) {
            const selectedOption = studentSelect.querySelector(`option[value="${studentSelect.value}"]`);
            if (selectedOption && selectedOption.style.display === 'none') { studentSelect.value = ''; }
        }
    }

    function toggleFields() {
        const type = document.getElementById('activity_type').value;
        document.getElementById('score_fields').style.display = type === 'Attendance' ? 'none' : 'block';
        document.getElementById('attendance_fields').style.display = type === 'Attendance' ? 'block' : 'none';

        // Reset fields if hidden to ensure cleanliness (optional, but good practice)
        if (type !== 'Attendance') {
            document.getElementById('session').value = '';
        }
    }

    function openAddModal() {
        document.getElementById('activityForm').reset();
        document.getElementById('record_id').value = '';
        document.getElementById('modalTitle').innerText = 'Add Academic Record';
        document.getElementById('btnSave').style.display = 'inline-block';
        document.getElementById('btnAddActivity').style.display = 'inline-block';
        document.getElementById('btnSave').innerText = 'Save';
        document.getElementById('date').value = '<?php echo date('Y-m-d'); ?>';
        document.getElementById('activity_type').value = 'Pre-Test';
        toggleFields();
        document.getElementById('addRecordModal').style.display = 'block';
    }

    function openEditModal(record) {
        document.getElementById('activityForm').reset();
        document.getElementById('record_id').value = record.id;
        document.getElementById('modalTitle').innerText = 'Edit Academic Record';
        document.getElementById('btnSave').innerText = 'Update';
        document.getElementById('btnAddActivity').style.display = 'none'; // Hide "Add Another" in Edit mode
        document.getElementById('group_id').value = record.student_group_id || '';
        filterStudentsByGroup();
        document.getElementById('student_id').value = record.student_id;
        document.getElementById('activity_type').value = record.activity_type;
        document.getElementById('topic').value = record.topic;
        document.getElementById('date').value = record.activity_date;
        document.getElementById('score').value = record.score;
        document.getElementById('total_items').value = record.total_items;
        document.getElementById('attendance_status').value = record.attendance_status;
        document.getElementById('session').value = record.session || ''; // Populate Session
        document.getElementById('remarks').value = record.remarks;
        toggleFields();
        document.getElementById('addRecordModal').style.display = 'block';
    }

    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('academicTable');
        const tr = table.getElementsByTagName('tr');
        for (let i = 1; i < tr.length; i++) {
            let visible = false;
            const tds = tr[i].getElementsByTagName('td');
            for (let j = 0; j < tds.length; j++) {
                if (tds[j] && tds[j].innerText.toUpperCase().indexOf(filter) > -1) { visible = true; break; }
            }
            tr[i].style.display = visible ? "" : "none";
        }
    }

    function toggleAllRecords(source) {
        const checkboxes = document.querySelectorAll('.record-checkbox');
        checkboxes.forEach(cb => { if (cb.closest('tr').style.display !== 'none') cb.checked = source.checked; });
    }
</script>

<?php require_once 'includes/footer.php'; ?>