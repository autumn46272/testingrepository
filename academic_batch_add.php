<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$group_id = isset($_GET['group_id']) ? clean_input($_GET['group_id']) : '';

// Handle Save Batch
$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_batch'])) {
    if (empty($group_id)) {
        $error = "Group is missing.";
    } else {
        try {
            $pdo->beginTransaction();

            // Common Data
            $activity_type = clean_input($_POST['activity_type']);
            $date = clean_input($_POST['date']);
            $topic = clean_input($_POST['topic']);
            $total_items = clean_input($_POST['total_items']);
            $scores = $_POST['scores'] ?? [];
            $remarks_list = $_POST['remarks'] ?? [];

            // Get group program name
            $stmt_group = $pdo->prepare("SELECT group_name, program FROM groups WHERE id = :gid");
            $stmt_group->execute([':gid' => $group_id]);
            $group_info = $stmt_group->fetch();
            $program = $group_info ? $group_info['program'] : 'Unknown';

            // Prepare Insert
            $stmt = $pdo->prepare("INSERT INTO academic_records 
                (student_id, group_id, program, activity_type, activity_date, topic, total_items, score, remarks, created_at) 
                VALUES (:sid, :gid, :prog, :type, :date, :topic, :items, :score, :rem, NOW())");

            $count = 0;
            foreach ($scores as $student_id => $score_val) {
                // If score is empty, skip or save as null? 
                // Assumption: Save record if score is entered (even 0), skip if empty string?
                // Or user might want to record 'Absent' which technically has no score but might need a record.
                // Let's save if it's set. If empty string, treat as NULL or 0? 
                // Let's treat empty string as "No Entry" and SKIP to avoid cluttering DB with empty records,
                // UNLESS remarks are present.

                $remark = isset($remarks_list[$student_id]) ? clean_input($remarks_list[$student_id]) : '';

                if ($score_val === '' && $remark === '') {
                    continue; // Skip completely empty entries
                }

                $final_score = ($score_val === '') ? null : $score_val;

                $stmt->execute([
                    ':sid' => $student_id,
                    ':gid' => $group_id,
                    ':prog' => $program,
                    ':type' => $activity_type,
                    ':date' => $date,
                    ':topic' => $topic,
                    ':items' => $total_items,
                    ':score' => $final_score,
                    ':rem' => $remark
                ]);
                $count++;
            }

            $pdo->commit();
            $success = "Batch activity saved successfully for $count records!";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch Groups
$groups = $pdo->query("SELECT id, group_name FROM groups ORDER BY group_name ASC")->fetchAll();

// Fetch Topics
$topics_db = $pdo->query("SELECT * FROM topics ORDER BY topic_name ASC")->fetchAll();
$topics = array_column($topics_db, 'topic_name');

// Fetch Students
$students = [];
if (!empty($group_id)) {
    // UPDATED: M2M Fetch
    $stmt = $pdo->prepare("SELECT s.id, s.first_name, s.last_name FROM students s 
                           JOIN student_groups sg ON s.id = sg.student_id 
                           WHERE sg.group_id = :gid AND s.status = 'Active' 
                           ORDER BY s.last_name ASC");
    $stmt->execute([':gid' => $group_id]);
    $students = $stmt->fetchAll();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header"
    style="margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center;">
        <a href="academic.php" style="margin-right: 15px; color: #6b7280; font-size: 1.2rem;"><i
                class="fas fa-arrow-left"></i></a>
        <h2>Add Batch Activity</h2>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert"
        style="background-color: #dcfce7; color: #166534; padding: 15px; margin-bottom: 20px; border-radius: 6px;">
        <?php echo $success; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert"
        style="background-color: #fee2e2; color: #991b1b; padding: 15px; margin-bottom: 20px; border-radius: 6px;">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<!-- Step 1: Select Group -->
<div class="card mb-4" style="margin-bottom: 24px;">
    <!-- Custom Searchable Dropdown CSS -->
    <style>
        .custom-dropdown {
            position: relative;
        }

        .dropdown-search {
            width: 100%;
            padding: 10px 35px 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            outline: none;
            background: #fff;
        }

        .dropdown-search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }

        .dropdown-list {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #3b82f6;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 50;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .dropdown-list.show {
            display: block;
        }

        .dropdown-item {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
            transition: background 0.1s;
        }

        .dropdown-item:hover {
            background-color: #eff6ff;
            color: #1e40af;
        }

        .dropdown-item.selected {
            background-color: #eff6ff;
            color: #1e40af;
            font-weight: 600;
        }
    </style>

    <form method="GET" id="groupSelectForm"
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; align-items: end;">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Step 1: Select Group</label>
            <div class="custom-dropdown">
                <input type="hidden" name="group_id" id="selected_group_id" value="<?php echo $group_id; ?>">

                <?php
                $current_group_name = '';
                if ($group_id) {
                    foreach ($groups as $g) {
                        if ($g['id'] == $group_id) {
                            $current_group_name = $g['group_name'];
                            break;
                        }
                    }
                }
                ?>

                <div style="position: relative;">
                    <input type="text" class="dropdown-search" id="groupSearchInput"
                        placeholder="Search or Select Group..."
                        value="<?php echo htmlspecialchars($current_group_name); ?>" autocomplete="off"
                        onfocus="showDropdown()" onblur="hideDropdown()" onkeyup="filterDropdown()">
                    <i class="fas fa-search dropdown-search-icon"></i>
                </div>

                <div class="dropdown-list" id="groupDropdownList">
                    <?php foreach ($groups as $g): ?>
                        <div class="dropdown-item <?php echo $group_id == $g['id'] ? 'selected' : ''; ?>"
                            onclick="selectGroup('<?php echo $g['id']; ?>', '<?php echo htmlspecialchars(addslashes($g['group_name'])); ?>')">
                            <?php echo htmlspecialchars($g['group_name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function showDropdown() {
        document.getElementById('groupDropdownList').classList.add('show');
    }

    function hideDropdown() {
        setTimeout(() => {
            document.getElementById('groupDropdownList').classList.remove('show');
        }, 200);
    }

    function filterDropdown() {
        const input = document.getElementById('groupSearchInput');
        const filter = input.value.toUpperCase();
        const list = document.getElementById('groupDropdownList');
        const items = list.getElementsByClassName('dropdown-item');

        showDropdown(); // Ensure visible when typing

        for (let i = 0; i < items.length; i++) {
            const txtValue = items[i].innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                items[i].style.display = "";
            } else {
                items[i].style.display = "none";
            }
        }
    }

    function selectGroup(id, name) {
        document.getElementById('selected_group_id').value = id;
        document.getElementById('groupSearchInput').value = name;
        document.getElementById('groupSelectForm').submit();
    }
</script>

<?php if ($group_id && !empty($students)): ?>
    <div class="card">
        <form method="POST">
            <input type="hidden" name="save_batch" value="1">

            <!-- Step 2: Activity Details -->
            <div style="padding: 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; border-radius: 8px 8px 0 0;">
                <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--primary-color);">Step 2: Activity Details</h4>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Activity Type</label>
                        <select name="activity_type" class="form-control" required>
                            <option value="Pre-Test">Pre-Test</option>
                            <option value="Post-Test">Post-Test</option>
                            <option value="Qbank">Qbank</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Topic</label>
                        <select name="topic" class="form-control" required>
                            <option value="">-- Select Topic --</option>
                            <?php foreach ($topics as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>">
                                    <?php echo htmlspecialchars($t); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Items</label>
                        <input type="number" name="total_items" class="form-control" min="1" required>
                    </div>
                </div>
            </div>

            <!-- Step 3: Student List -->
            <div style="padding: 20px;">
                <h4 style="margin: 0 0 15px 0; font-size: 16px; color: var(--primary-color);">Step 3: Enter Scores</h4>
                <div class="table-container">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f3f4f6; text-align: left;">
                                <th style="padding: 12px; border-bottom: 2px solid #e5e7eb;">Student Name</th>
                                <th style="padding: 12px; border-bottom: 2px solid #e5e7eb; width: 150px;">Score</th>
                                <th style="padding: 12px; border-bottom: 2px solid #e5e7eb;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $s): ?>
                                <tr>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <strong>
                                            <?php echo htmlspecialchars($s['last_name'] . ', ' . $s['first_name']); ?>
                                        </strong>
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <input type="number" name="scores[<?php echo $s['id']; ?>]" class="form-control"
                                            placeholder="0" min="0">
                                    </td>
                                    <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                        <input type="text" name="remarks[<?php echo $s['id']; ?>]" class="form-control"
                                            placeholder="Optional">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                style="padding: 20px; background: #f9fafb; border-top: 1px solid #e5e7eb; text-align: right; border-radius: 0 0 8px 8px;">
                <a href="academic.php" class="btn"
                    style="background: #ccc; margin-right: 10px; text-decoration: none; display: inline-block;">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 10px 30px;">Save Batch Activity</button>
            </div>
        </form>
    </div>
<?php elseif ($group_id): ?>
    <div class="alert" style="background-color: #f3f4f6; padding: 20px; text-align: center; color: #666;">
        No active students found in this group.
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>