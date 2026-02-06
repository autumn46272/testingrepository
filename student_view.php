<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Get student ID
$student_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($student_id == 0)
    redirect('students.php');

// Fetch Student Data
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch();

    if (!$student)
        redirect('students.php');

    // Fetch Assigned Groups
    $stmt_g = $pdo->prepare("SELECT g.id, g.group_name, g.course_code FROM groups g 
                             JOIN student_groups sg ON g.id = sg.group_id 
                             WHERE sg.student_id = :id 
                             ORDER BY g.created_at DESC");
    $stmt_g->execute([':id' => $student_id]);
    $assigned_groups = $stmt_g->fetchAll();

    // Filter Logic
    $group_filter_id = isset($_GET['group_id']) ? clean_input($_GET['group_id']) : '';

    // Fetch SCORM Attempts joined with Packages
    $query = "SELECT sa.*, sp.title as package_name, sp.id as package_id, 
                     sa.score as score, sa.total_questions as total_items,
                     sa.status as status, sa.started_at as activity_date
              FROM scorm_attempts sa 
              JOIN scorm_packages sp ON sa.package_id = sp.id
              WHERE sa.user_id = :id 
              ORDER BY sa.started_at DESC";

    $stmt_u = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt_u->execute([':username' => $student['student_id']]);
    $user_record = $stmt_u->fetch();
    $user_id = $user_record ? $user_record['id'] : 0;

    $stmt_records = $pdo->prepare($query);
    $stmt_records->execute([':id' => $user_id]);
    $scorm_records = $stmt_records->fetchAll();

    // Custom Sorting: Pre-Test > Activity > Post-Test
    // Logic: Assign weight. Pre=1, Other=2, Post=3. Then Date DESC.
    usort($scorm_records, function ($a, $b) {
        $typeA = stripos($a['package_name'], 'Pre-Test') !== false ? 1 : (stripos($a['package_name'], 'Post-Test') !== false ? 3 : 2);
        $typeB = stripos($b['package_name'], 'Pre-Test') !== false ? 1 : (stripos($b['package_name'], 'Post-Test') !== false ? 3 : 2);

        if ($typeA !== $typeB) {
            return $typeA - $typeB;
        }

        // Secondary sort by Date DESC
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });

    // Live Tab includes everything now
    $live = $scorm_records;

    // Attendance remains separate (populated later)
    $attendance = [];

    // --- FETCH ACADEMIC RECORDS (ATTENDANCE) ---
    // Fetch from academic_records where activity_type = 'Attendance'
    $stmt_att = $pdo->prepare("
        SELECT ar.*, g.group_name, g.course_code 
        FROM academic_records ar
        LEFT JOIN groups g ON ar.group_id = g.id
        WHERE ar.student_id = :id 
          AND ar.activity_type = 'Attendance'
        ORDER BY ar.activity_date DESC
    ");
    $stmt_att->execute([':id' => $student_id]);
    $attendance_records = $stmt_att->fetchAll();

    // Map to structure expected by render function (or just use directly if we update render function)
    foreach ($attendance_records as $ar) {
        $attendance[] = [
            'activity_date' => $ar['activity_date'],
            'group_name' => $ar['group_name'],
            'course_code' => $ar['course_code'],
            'topic' => $ar['topic'],
            'attendance_status' => $ar['attendance_status'],
            'session' => $ar['session'],
            'remarks' => $ar['remarks'],
            'time_in' => null // Not available in academic_records
        ];
    }

    // Re-sort Attendance by Date DESC
    usort($attendance, function ($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
    .student-view-container {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    .student-info-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        height: fit-content;
    }

    .student-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 20px;
        display: block;
        border: 4px solid #f3f4f6;
    }

    .student-avatar-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        margin: 0 auto 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: white;
        font-weight: bold;
    }

    .student-name {
        text-align: center;
        font-size: 24px;
        font-weight: 600;
        color: var(--secondary-color);
        margin-bottom: 8px;
    }

    .student-id-badge {
        text-align: center;
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 24px;
    }

    .info-group {
        margin-bottom: 20px;
    }

    .info-label {
        font-size: 12px;
        font-weight: 600;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .info-value {
        font-size: 15px;
        color: #374151;
        font-weight: 500;
    }

    .group-tag {
        display: inline-block;
        background: #f3f4f6;
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid #e5e7eb;
        font-size: 13px;
        margin-bottom: 4px;
        margin-right: 4px;
    }

    .tab-container {
        background: white;
        border-radius: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .tab-nav {
        display: flex;
        border-bottom: 2px solid #e5e7eb;
        background: #f9fafb;
    }

    .tab-btn {
        flex: 1;
        padding: 16px 20px;
        background: none;
        border: none;
        font-size: 14px;
        font-weight: 600;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.2s;
        border-bottom: 3px solid transparent;
    }

    .tab-btn:hover {
        background: #f3f4f6;
        color: var(--secondary-color);
    }

    .tab-btn.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
        background: white;
    }

    .tab-content {
        display: none;
        padding: 24px;
    }

    .tab-content.active {
        display: block;
    }

    .record-table {
        width: 100%;
        border-collapse: collapse;
    }

    .record-table th {
        background: #f9fafb;
        padding: 12px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        border-bottom: 2px solid #e5e7eb;
    }

    .record-table td {
        padding: 14px 12px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
    }

    .record-table tr:hover {
        background: #f9fafb;
    }

    .score-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
    }

    .score-excellent {
        background: #dcfce7;
        color: #166534;
    }

    .score-good {
        background: #dbeafe;
        color: #1e40af;
    }

    .score-average {
        background: #fef3c7;
        color: #92400e;
    }

    .score-poor {
        background: #fee2e2;
        color: #991b1b;
    }

    @media (max-width: 1024px) {
        .student-view-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header"
    style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>Student Profile</h2>
    <a href="students.php" class="btn btn-secondary"><i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Back
        to List</a>
</div>

<div class="student-view-container">
    <!-- Left Column: Student Info -->
    <div class="student-info-card">
        <?php if (!empty($student['profile_image'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($student['profile_image']); ?>" alt="Profile"
                class="student-avatar">
        <?php else: ?>
            <div class="student-avatar-placeholder"><?php echo strtoupper(substr($student['first_name'], 0, 1)); ?></div>
        <?php endif; ?>

        <div class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
        </div>
        <div class="student-id-badge">ID: <?php echo htmlspecialchars($student['student_id']); ?></div>

        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px;">
            <div class="info-group">
                <div class="info-label">Gender</div>
                <div class="info-value"><?php echo htmlspecialchars($student['gender']); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Birthdate</div>
                <div class="info-value">
                    <?php echo $student['birthdate'] ? format_date($student['birthdate']) : 'N/A'; ?>
                </div>
            </div>
            <div class="info-group">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo htmlspecialchars($student['email'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Contact</div>
                <div class="info-value"><?php echo htmlspecialchars($student['contact_number'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Branch</div>
                <div class="info-value"><?php echo htmlspecialchars($student['branch'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">City</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($student['city'] ?: 'N/A'); ?>
                </div>
            </div>
            <div class="info-group">
                <div class="info-label">BON / State</div>
                <div class="info-value"><?php echo htmlspecialchars($student['bon_country'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Application Status</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($student['application_status'] ?: 'No NCLEX Application'); ?>
                </div>
            </div>
            <div class="info-group">
                <div class="info-label">Work Status</div>
                <div class="info-value"><?php echo htmlspecialchars($student['work_status'] ?: 'N/A'); ?></div>
            </div>
        </div>

        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 20px;">
            <h4
                style="font-size: 13px; font-weight: 700; color: #4b5563; margin-bottom: 15px; text-transform: uppercase;">
                Academic Info</h4>
            <div class="info-group">
                <div class="info-label">Groups / Programs</div>
                <div class="info-value">
                    <?php if (count($assigned_groups) > 0): ?>
                        <?php foreach ($assigned_groups as $ag): ?>
                            <!-- CHANGED: Use course_code instead of group_name -->
                            <span class="group-tag"><?php echo htmlspecialchars($ag['course_code']); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="text-muted">No Groups Assigned</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-group">
                <div class="info-label">School</div>
                <div class="info-value"><?php echo htmlspecialchars($student['school'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Previous Review Center</div>
                <div class="info-value"><?php echo htmlspecialchars($student['prev_review_center'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Exam Type (Takes)</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($student['exam_type'] ?: 'N/A'); ?>
                    <?php echo $student['exam_takes'] ? '(' . $student['exam_takes'] . ')' : ''; ?>
                </div>
            </div>
            <div class="info-group">
                <div class="info-label">Exam Status</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($student['exam_status'] ?: 'No Exam Schedule'); ?>
                </div>
            </div>
            <?php if ($student['exam_status'] == 'Scheduled'): ?>
                <div class="info-group">
                    <div class="info-label">Exam Date</div>
                    <div class="info-value">
                        <?php echo $student['exam_date'] ? format_date($student['exam_date']) : 'N/A'; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="info-group">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <span
                        class="badge <?php echo ($student['status'] == 'Inactive' || $student['status'] == 'Failed') ? 'badge-inactive' : 'badge-active'; ?>">
                        <?php echo htmlspecialchars($student['status']); ?>
                    </span>
                </div>
            </div>
            <div class="info-group">
                <div class="info-label">Date Added</div>
                <div class="info-value"><?php echo format_date($student['created_at']); ?></div>
            </div>
        </div>

        <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 20px;">
            <h4
                style="font-size: 13px; font-weight: 700; color: #4b5563; margin-bottom: 15px; text-transform: uppercase;">
                Emergency Contact</h4>
            <div class="info-group">
                <div class="info-label">Name</div>
                <div class="info-value"><?php echo htmlspecialchars($student['emergency_name'] ?: 'N/A'); ?></div>
            </div>
            <div class="info-group">
                <div class="info-label">Number</div>
                <div class="info-value"><?php echo htmlspecialchars($student['emergency_number'] ?: 'N/A'); ?></div>
            </div>
        </div>
    </div>

    <!-- Right Column: Academic History -->
    <div class="tab-container">
        <!-- Search and Filter Bar -->
        <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #fff;">
            <form method="GET" style="display: flex; gap: 12px; align-items: flex-end;">
                <input type="hidden" name="id" value="<?php echo $student_id; ?>">

                <div style="flex: 1;">
                    <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Search Groups</label>
                    <input type="text" id="groupSearchInput" class="form-control" placeholder="Type to find group..."
                        style="height: 38px; font-size: 13px; margin-bottom: 5px;" onkeyup="filterGroupOptions()">
                    <select name="group_id" id="groupSelect" class="form-control"
                        style="height: 38px; font-size: 13px; padding-top: 6px; padding-bottom: 6px;"
                        onchange="this.form.submit()">
                        <option value="">All Batches/Groups</option>
                        <?php foreach ($assigned_groups as $ag): ?>
                            <option value="<?php echo $ag['id']; ?>" <?php echo $group_filter_id == $ag['id'] ? 'selected' : ''; ?>>
                                <!-- CHANGED: Group Name + (Course Code) -->
                                <?php echo htmlspecialchars($ag['group_name'] . ' (' . $ag['course_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1;">
                    <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Real-time Search</label>
                    <div style="position: relative;">
                        <input type="text" id="recordSearchInput" class="form-control"
                            placeholder="Search topic, remarks..."
                            style="height: 38px; font-size: 13px; padding-right: 30px;" onkeyup="filterRecords()">
                        <div style="position: absolute; right: 10px; top: 10px; color: #9ca3af;"><i
                                class="fas fa-search"></i></div>
                    </div>
                </div>
            </form>
        </div>

        <div class="tab-nav">
            <button class="tab-btn" onclick="switchTab(event, 'pre_live')"><i class="fas fa-file-alt"
                    style="margin-right: 6px;"></i> Pre-Live</button>
            <button class="tab-btn active" onclick="switchTab(event, 'live')"><i class="fas fa-play-circle"
                    style="margin-right: 6px;"></i> Live</button>
            <button class="tab-btn" onclick="switchTab(event, 'post_live')"><i class="fas fa-file-check"
                    style="margin-right: 6px;"></i> Post-Live</button>
            <button class="tab-btn" onclick="switchTab(event, 'attendance')"><i class="fas fa-calendar-check"
                    style="margin-right: 6px;"></i> Attendance</button>
        </div>

        <!-- Pre-Live Tab (Blank) -->
        <div id="pre_live" class="tab-content">
            <p style="text-align: center; color: #9ca3af; padding: 40px;">No records yet.</p>
        </div>

        <!-- Live Tab -->
        <div id="live" class="tab-content active"><?php renderTable($live, $assigned_groups); ?></div>

        <!-- Post-Live Tab (Blank) -->
        <div id="post_live" class="tab-content">
            <p style="text-align: center; color: #9ca3af; padding: 40px;">No records yet.</p>
        </div>

        <div id="attendance" class="tab-content"><?php renderAttendanceTable($attendance); ?></div>
    </div>
</div>

<script>
    function switchTab(event, tabId) {
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
        // Re-run filter on switch to ensure search applies to new tab
        filterRecords();
    }

    function filterRecords() {
        const input = document.getElementById('recordSearchInput');
        const filter = input.value.toUpperCase();
        // Filter all tables in all tabs (or just active one, but filtering all feels smoother when switching)
        const tables = document.querySelectorAll('.record-table');

        tables.forEach(table => {
            const tr = table.getElementsByTagName('tr');
            for (let i = 1; i < tr.length; i++) { // Skip header
                let visible = false;
                const tds = tr[i].getElementsByTagName('td');
                for (let j = 0; j < tds.length; j++) {
                    if (tds[j] && tds[j].innerText.toUpperCase().indexOf(filter) > -1) {
                        visible = true;
                        break;
                    }
                }
                tr[i].style.display = visible ? "" : "none";
            }
        });
    }

    function filterGroupOptions() {
        const input = document.getElementById('groupSearchInput');
        const filter = input.value.toUpperCase();
        const select = document.getElementById('groupSelect');
        const options = select.getElementsByTagName('option');

        for (let i = 1; i < options.length; i++) { // Skip "All Batches"
            const txt = options[i].text;
            if (txt.toUpperCase().indexOf(filter) > -1) {
                options[i].style.display = "";
            } else {
                options[i].style.display = "none";
            }
        }
    }
</script>

<?php
function renderTable($records, $assigned_groups = [])
{
    // Flatten assigned groups into a string for display
    $group_str = '-';
    if (!empty($assigned_groups)) {
        $codes = array_map(function ($g) {
            return $g['course_code'];
        }, $assigned_groups);
        $group_str = implode(', ', $codes);
    }

    if (count($records) > 0) {
        echo '<table class="record-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 15%;">Activity Type</th>
                    <th style="width: 15%;">Batch/Group</th>
                    <th style="width: 30%;">Topic</th>
                    <th style="width: 10%;">Score / Max</th>
                    <th style="width: 15%;">Status</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($records as $record) {
            // Determine Activity Type
            $activity_type = 'Activity';
            if (stripos($record['package_name'], 'Pre-Test') !== false) {
                $activity_type = 'Pre-Test';
            } elseif (stripos($record['package_name'], 'Post-Test') !== false) {
                $activity_type = 'Post-Test';
            }

            echo '<tr>';
            echo '<td>' . format_date($record['activity_date']) . '</td>';
            echo '<td><span class="badge" style="background:#f3f4f6; color:#4b5563;">' . $activity_type . '</span></td>';
            echo '<td>' . htmlspecialchars($group_str) . '</td>';
            echo '<td>' . htmlspecialchars($record['package_name']) . '</td>';
            echo '<td>';
            // Score Logic
            $score = $record['score'] ?? 0;
            // SCORM score is already 0-100 based on handler logic
            $percentage = $score;
            $class = $percentage >= 90 ? 'score-excellent' : ($percentage >= 75 ? 'score-good' : ($percentage >= 60 ? 'score-average' : 'score-poor'));

            echo '<span class="score-badge ' . $class . '" style="font-size: 13px;">' . number_format($score, 0) . '%</span>';

            if (!empty($record['total_items'])) {
                echo ' <span class="text-muted" style="font-size: 11px; margin-left: 4px;">(' . $record['total_items'] . ' items)</span>';
            }

            echo '</td>';
            echo '<td>' . htmlspecialchars($record['status'] ?: '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p style="text-align: center; color: #9ca3af; padding: 40px;">No records found.</p>';
    }
}

function renderAttendanceTable($records)
{
    if (count($records) > 0) {
        echo '<table class="record-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Date</th>
                    <th style="width: 25%;">Batch/Group</th>
                    <th style="width: 25%;">Topic</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 15%;">Remarks</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($records as $record) {
            $date_display = format_date($record['activity_date']);
            if (!empty($record['session'])) {
                // Parse session (Remove "Session" word)
                $session_label = str_ireplace(' Session', '', $record['session']);
                // Add badge
                $date_display .= ' <span class="badge" style="background-color: #f3f4f6; color: #374151; font-size: 11px; margin-left: 6px;">' . htmlspecialchars($session_label) . '</span>';
            } elseif (!empty($record['time_in'])) {
                // Fallback if no session but time exists (unlikely given new source, but safe)
                $date_display .= ' ' . $record['time_in'];
            }

            echo '<tr>';
            echo '<td>' . $date_display . '</td>';
            echo '<td>' . htmlspecialchars($record['group_name'] . ' (' . $record['course_code'] . ')') . '</td>';
            echo '<td>' . htmlspecialchars($record['topic'] ?? '-') . '</td>';
            echo '<td>';
            $bg = '#dcfce7';
            $col = '#166534';
            if ($record['attendance_status'] == 'Absent') {
                $bg = '#fee2e2';
                $col = '#991b1b';
            } elseif ($record['attendance_status'] == 'Late') {
                $bg = '#fef3c7';
                $col = '#92400e';
            } elseif ($record['attendance_status'] == 'Excused') {
                $bg = '#dbeafe';
                $col = '#1e40af';
            }
            echo '<span class="badge" style="background-color: ' . $bg . '; color: ' . $col . ';">' . htmlspecialchars($record['attendance_status']) . '</span>';
            echo '</td>';
            echo '<td>' . htmlspecialchars($record['remarks'] ?: '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p style="text-align: center; color: #9ca3af; padding: 40px;">No attendance records found.</p>';
    }
}
require_once 'includes/footer.php';
?>