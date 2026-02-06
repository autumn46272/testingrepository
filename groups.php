<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$error = '';
$success = '';

// Handle Program Management
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_program'])) {
        $new_prog = clean_input($_POST['program_name']);
        if (!empty($new_prog)) {
            try {
                $pdo->prepare("INSERT INTO programs (program_name) VALUES (?)")->execute([$new_prog]);
                $success = "Program added successfully!";
            } catch (PDOException $e) {
                $error = "Error adding program: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['edit_program'])) {
        $prog_id = clean_input($_POST['program_id']);
        $prog_name = clean_input($_POST['program_name']);
        if (!empty($prog_name)) {
            try {
                $pdo->prepare("UPDATE programs SET program_name = ? WHERE id = ?")->execute([$prog_name, $prog_id]);
                $success = "Program updated successfully!";
            } catch (PDOException $e) {
                $error = "Error updating program: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_program'])) {
        $prog_id = clean_input($_POST['program_id']);
        try {
            $pdo->prepare("DELETE FROM programs WHERE id = ?")->execute([$prog_id]);
            $success = "Program deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting program. It may be in use.";
        }
    }
}

// Status Automation Logic
try {
    $today = date('Y-m-d');
    // Ongoing (formerly Active): Within start and end date (inclusive)
    $pdo->exec("UPDATE groups SET status = 'Ongoing' WHERE program_start_date <= '$today' AND program_end_date >= '$today'");
    // Done (formerly Completed): Past end date
    $pdo->exec("UPDATE groups SET status = 'Done' WHERE program_end_date < '$today'");
    // Upcoming: Future start date
    $pdo->exec("UPDATE groups SET status = 'Upcoming' WHERE program_start_date > '$today'");
} catch (PDOException $e) {
    // Silent fail or log
}

// Handle Delete Group
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_group'])) {
    $group_id = clean_input($_POST['group_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM groups WHERE id = :id");
        $stmt->execute([':id' => $group_id]);
        $success = "Group deleted successfully!";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Cannot delete group: Students are assigned to this group.";
        } else {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Edit Group
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_group'])) {
    $id = clean_input($_POST['group_id']);
    $group_name = clean_input($_POST['group_name']);
    $program = clean_input($_POST['program']);
    $batch_year = clean_input($_POST['batch_year']);
    $program_start_date = clean_input($_POST['program_start_date']);
    $program_end_date = clean_input($_POST['program_end_date']);

    if (empty($group_name) || empty($program) || empty($batch_year)) {
        $error = "Group Name, Program, and Year are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE groups SET group_name = :name, program = :prog, batch_year = :year, program_start_date = :sdate, program_end_date = :edate WHERE id = :id");
            $stmt->execute([
                'name' => $group_name,
                'prog' => $program,
                'year' => $batch_year,
                'sdate' => $program_start_date ?: null,
                'edate' => $program_end_date ?: null,
                'id' => $id
            ]);
            $success = "Group updated successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Create Group
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_group'])) {
    $group_name = clean_input($_POST['group_name']);
    $program = clean_input($_POST['program']);
    $batch_year = clean_input($_POST['batch_year']);
    $batch_seq = clean_input($_POST['batch_seq']);
    $program_start_date = clean_input($_POST['program_start_date']);
    $program_end_date = clean_input($_POST['program_end_date']);

    if (empty($group_name) || empty($program) || empty($batch_year)) {
        $error = "All fields are required.";
    } else {
        // Auto-generate Course Code
        $course_code = generate_course_code($program, $batch_year, $batch_seq);

        try {
            // Default status
            $status = 'Upcoming';
            if ($program_start_date && $program_end_date) {
                if ($today >= $program_start_date && $today <= $program_end_date)
                    $status = 'Ongoing';
                elseif ($today > $program_end_date)
                    $status = 'Done';
            }

            $stmt = $pdo->prepare("INSERT INTO groups (group_name, program, batch_year, batch_sequence, course_code, program_start_date, program_end_date, status) VALUES (:name, :prog, :year, :seq, :code, :sdate, :edate, :status)");
            $stmt->execute([
                'name' => $group_name,
                'prog' => $program,
                'year' => $batch_year,
                'seq' => $batch_seq,
                'code' => $course_code,
                'sdate' => $program_start_date ?: null,
                'edate' => $program_end_date ?: null,
                'status' => $status
            ]);
            $success = "Group created successfully! Course Code: " . $course_code;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Error: Course Code '$course_code' already exists.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch All Groups
$stmt = $pdo->query("SELECT * FROM groups ORDER BY created_at DESC");
$groups = $stmt->fetchAll();

// Fetch Programs
$programs_list = $pdo->query("SELECT * FROM programs ORDER BY program_name ASC")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
    /* Modal Styling */
    .form-section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #6b7280;
        border-bottom: 2px solid #f3f4f6;
        padding-bottom: 8px;
        margin-bottom: 16px;
        margin-top: 10px;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-badge.ongoing {
        background: #DCFCE7;
        color: #166534;
    }

    .status-badge.upcoming {
        background: #FEF3C7;
        color: #92400E;
    }

    .status-badge.done {
        background: #DBEAFE;
        color: #1E40AF;
    }
</style>

<div class="page-header"
    style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>Groups Management</h2>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-secondary"
            onclick="document.getElementById('manageProgramsModal').style.display='block'">
            <i class="fas fa-list" style="margin-right: 8px;"></i> Manage Programs
        </button> <!-- NEW BUTTON -->
        <input type="text" id="searchInput" class="form-control" placeholder="Search..." onkeyup="filterTable()"
            style="width: 250px; height: 40px;">
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus" style="margin-right: 8px;"></i> Create Group
        </button>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table id="groupsTable">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Program</th>
                    <th>Batch Year</th>
                    <th>Group Name</th>
                    <th>Program Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td><span
                                style="font-weight: 600; color: var(--primary-color);"><?php echo htmlspecialchars($group['course_code']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($group['program']); ?></td>
                        <td><?php echo htmlspecialchars($group['batch_year']); ?></td>
                        <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                        <td>
                            <?php
                            if ($group['program_start_date'] && $group['program_end_date']) {
                                echo format_date($group['program_start_date']) . ' - ' . format_date($group['program_end_date']);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            $status_class = strtolower($group['status'] ?? 'upcoming');
                            // Map to classes
                            if ($status_class == 'ongoing' || $status_class == 'active') // Handle legacy Active just in case
                                echo '<span class="status-badge ongoing">Ongoing</span>';
                            elseif ($status_class == 'done' || $status_class == 'completed')
                                echo '<span class="status-badge done">Done</span>';
                            else
                                echo '<span class="status-badge upcoming">Upcoming</span>';
                            ?>
                        </td>
                        <td>
                            <div style="display: flex;">
                                <a href="group_view.php?id=<?php echo $group['id']; ?>" class="btn-action-gray"
                                    title="View Group">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn-action-gray" title="Edit"
                                    onclick="openEditGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Delete this group?');">
                                    <input type="hidden" name="delete_group" value="1">
                                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                    <button type="submit" class="btn-action-gray" title="Delete" style="color: #ef4444;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($groups) == 0): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #9ca3af; padding: 30px;">No groups found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Show Toasts if PHP set success/error (using JS on load)
if ($success || $error) {
    $msg = $success ?: $error;
    $type = $success ? 'success' : 'error';
    echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($msg) . "', '$type'); });</script>";
}
?>

<!-- Create Group Modal -->
<div id="createGroupModal" class="modal">
    <div class="modal-content" style="width: 550px;">
        <div
            style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; color: #111827;">Create New Group</h3>
            <span class="modal-close"
                onclick="document.getElementById('createGroupModal').style.display='none'">&times;</span>
        </div>

        <form method="post" style="padding: 24px;">
            <div
                style="background: #eff6ff; border: 1px dashed #3b82f6; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center;">
                <span style="font-size: 12px; color: #1d4ed8; text-transform: uppercase; font-weight: 600;">Course Code
                    will be generated</span><br>
                <div style="font-size: 24px; font-weight: 700; color: #2563eb; margin-top: 4px;">AUTO-GEN</div>
            </div>

            <div class="form-group">
                <label class="form-label">Group Name</label>
                <input type="text" name="group_name" class="form-control" placeholder="e.g. Batch Alpha" required>
            </div>

            <div class="form-group">
                <label class="form-label">Program</label>
                <select name="program" class="form-control" required>
                    <option value="">-- Select Program --</option>
                    <?php foreach ($programs_list as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['program_name']); ?>">
                            <?php echo htmlspecialchars($p['program_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Batch Year</label>
                    <input type="number" name="batch_year" class="form-control" value="<?php echo date('Y'); ?>"
                        required>
                </div>
                <div class="form-group">
                    <label class="form-label">Batch Sequence</label>
                    <input type="number" name="batch_seq" class="form-control" value="1" min="1" required>
                </div>
            </div>

            <div class="form-section-title">Program Duration</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="program_start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="program_end_date" class="form-control" required>
                </div>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn"
                    style="background: white; border: 1px solid #d1d5db; color: #374151; margin-right: 10px;"
                    onclick="document.getElementById('createGroupModal').style.display='none'">Cancel</button>
                <button type="submit" name="create_group" class="btn btn-primary">Create Group</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Group Modal -->
<div id="editGroupModal" class="modal">
    <div class="modal-content" style="width: 550px;">
        <div
            style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; color: #111827;">Edit Group</h3>
            <span class="modal-close"
                onclick="document.getElementById('editGroupModal').style.display='none'">&times;</span>
        </div>

        <form method="post" style="padding: 24px;">
            <input type="hidden" name="group_id" id="edit_group_id">

            <div class="form-group">
                <label class="form-label">Group Name</label>
                <input type="text" name="group_name" id="edit_group_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Program</label>
                <select name="program" id="edit_program" class="form-control" required>
                    <?php foreach ($programs_list as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['program_name']); ?>">
                            <?php echo htmlspecialchars($p['program_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Batch Year</label>
                <input type="number" name="batch_year" id="edit_batch_year" class="form-control" required>
            </div>

            <div class="form-section-title">Program Duration</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="program_start_date" id="edit_program_start_date" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="program_end_date" id="edit_program_end_date" class="form-control">
                </div>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn"
                    style="background: white; border: 1px solid #d1d5db; color: #374151; margin-right: 10px;"
                    onclick="document.getElementById('editGroupModal').style.display='none'">Cancel</button>
                <button type="submit" name="edit_group" class="btn btn-primary">Update Group</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() { document.getElementById('createGroupModal').style.display = 'block'; }

    function openEditGroupModal(group) {
        document.getElementById('edit_group_id').value = group.id;
        document.getElementById('edit_group_name').value = group.group_name;
        document.getElementById('edit_program').value = group.program;
        document.getElementById('edit_batch_year').value = group.batch_year;
        document.getElementById('edit_program_start_date').value = group.program_start_date || '';
        document.getElementById('edit_program_end_date').value = group.program_end_date || '';
        document.getElementById('editGroupModal').style.display = 'block';
    }

    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('groupsTable');
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
</script>

<!-- Manage Programs Modal -->
<div id="manageProgramsModal" class="modal">
    <div class="modal-content" style="width: 500px;">
        <div
            style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px;">Manage Programs</h3>
            <span class="modal-close" onclick="closeManageProgramsModal()">&times;</span>
        </div>
        <div style="padding: 24px;">
            <form method="POST" id="programForm" style="display: flex; gap: 10px; margin-bottom: 20px;">
                <input type="hidden" name="program_id" id="manage_program_id">
                <input type="text" name="program_name" id="manage_program_name" class="form-control"
                    placeholder="New Program Name" required>
                <div style="display: flex; gap: 5px;">
                    <button type="submit" name="add_program" id="btn_add_program" class="btn btn-primary">Add</button>
                    <button type="submit" name="edit_program" id="btn_edit_program" class="btn btn-success"
                        style="display:none;">Update</button>
                    <button type="button" id="btn_cancel_program" class="btn-action-gray" style="display:none;"
                        onclick="resetProgramForm()" title="Cancel Edit"><i class="fas fa-times"></i></button>
                </div>
            </form>
            <div style="border-top: 1px solid #e5e7eb; padding-top: 15px;">
                <label class="form-label">Existing Programs</label>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px;">
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($programs_list as $p): ?>
                            <li
                                style="padding: 10px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                                <span><?php echo htmlspecialchars($p['program_name']); ?></span>
                                <div style="display: flex; gap: 5px;">
                                    <button type="button" class="btn-action-gray" title="Edit"
                                        onclick='editProgram(<?php echo json_encode(["id" => $p["id"], "name" => $p["program_name"]]); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" onsubmit="return confirm('Delete program?');"
                                        style="margin: 0; display:inline;">
                                        <input type="hidden" name="program_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" name="delete_program" class="btn-action-gray delete"
                                            style="border: none; background: none; cursor: pointer;">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function editProgram(prog) {
        document.getElementById('manage_program_id').value = prog.id;
        document.getElementById('manage_program_name').value = prog.name;
        document.getElementById('btn_add_program').style.display = 'none';
        document.getElementById('btn_edit_program').style.display = 'inline-block';
        document.getElementById('btn_cancel_program').style.display = 'inline-block';
        document.getElementById('manage_program_name').focus();
    }

    function resetProgramForm() {
        document.getElementById('manage_program_id').value = '';
        document.getElementById('manage_program_name').value = '';
        document.getElementById('btn_add_program').style.display = 'inline-block';
        document.getElementById('btn_edit_program').style.display = 'none';
        document.getElementById('btn_cancel_program').style.display = 'none';
    }

    function closeManageProgramsModal() {
        document.getElementById('manageProgramsModal').style.display = 'none';
        resetProgramForm();
    }
</script>

<?php require_once 'includes/footer.php'; ?>