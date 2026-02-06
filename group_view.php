<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$group_id = isset($_GET['id']) ? clean_input($_GET['id']) : null;

if (!$group_id) {
    redirect('groups.php');
}

$error = '';
$success = '';

// Handle Add Course to Group
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_course'])) {
    $course_id = clean_input($_POST['course_id']);

    if (empty($course_id)) {
        $error = "Please select a course.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO group_courses (group_id, course_id) VALUES (:gid, :cid)");
            $stmt->execute(['gid' => $group_id, 'cid' => $course_id]);
            $success = "Course added to group!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "This course is already assigned to this group.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Remove Course from Group
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_course'])) {
    $course_id = clean_input($_POST['course_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM group_courses WHERE group_id = :gid AND course_id = :cid");
        $stmt->execute(['gid' => $group_id, 'cid' => $course_id]);
        $success = "Course removed from group!";
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Handle Mass Enroll / Unenroll
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mass_action'])) {
    $action = $_POST['mass_action'];
    $selected_students = $_POST['selected_students'] ?? [];
    
    if (empty($selected_students)) {
        $error = "No students selected.";
    } else {
        try {
            // Get all courses assigned to this group
            $stmt_courses = $pdo->prepare("SELECT course_id FROM group_courses WHERE group_id = ?");
            $stmt_courses->execute([$group_id]);
            $group_course_ids = $stmt_courses->fetchAll(PDO::FETCH_COLUMN);

            if (empty($group_course_ids)) {
                $error = "No courses assigned to this group to enroll/unenroll.";
            } else {
                if ($action == 'enroll') {
                    $insert_stmt = $pdo->prepare("INSERT IGNORE INTO student_courses (student_id, course_id) VALUES (?, ?)");
                    $count = 0;
                    foreach ($selected_students as $sid) {
                        foreach ($group_course_ids as $cid) {
                            $insert_stmt->execute([$sid, $cid]);
                            if ($insert_stmt->rowCount() > 0) $count++;
                        }
                    }
                    $skipped = (count($selected_students) * count($group_course_ids)) - $count;
                    $success = "Processed enrollment for " . count($selected_students) . " students. ($count new, $skipped already enrolled)";
                } elseif ($action == 'unenroll') {
                    $delete_stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ? AND course_id = ?");
                    $count = 0;
                    foreach ($selected_students as $sid) {
                        foreach ($group_course_ids as $cid) {
                            $delete_stmt->execute([$sid, $cid]);
                            if ($delete_stmt->rowCount() > 0) $count++;
                        }
                    }
                    $success = "Processed unenrollment for " . count($selected_students) . " students. ($count removed)";
                }
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch Group Details
try {
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch();

    if (!$group) {
        die("Group not found.");
    }

    // Fetch Students in Group
    $stmt_students = $pdo->prepare("
        SELECT s.*, GROUP_CONCAT(g.group_name SEPARATOR ', ') as group_names 
        FROM students s
        JOIN student_groups sg ON s.id = sg.student_id
        LEFT JOIN student_groups sg2 ON s.id = sg2.student_id
        LEFT JOIN groups g ON sg2.group_id = g.id
        WHERE sg.group_id = ?
        GROUP BY s.id
        ORDER BY s.last_name ASC
    ");
    $stmt_students->execute([$group_id]);
    $students = $stmt_students->fetchAll();

    // Fetch Assigned Courses
    $stmt_assigned = $pdo->prepare("
        SELECT c.*, gc.assigned_at 
        FROM courses c
        JOIN group_courses gc ON c.id = gc.course_id
        WHERE gc.group_id = ?
        ORDER BY gc.assigned_at DESC
    ");
    $stmt_assigned->execute([$group_id]);
    $assigned_courses = $stmt_assigned->fetchAll();

    // Fetch Available Courses (Not yet assigned)
    $stmt_available = $pdo->prepare("
        SELECT * FROM courses 
        WHERE id NOT IN (SELECT course_id FROM group_courses WHERE group_id = ?)
        ORDER BY course_name ASC
    ");
    $stmt_available->execute([$group_id]);
    $available_courses = $stmt_available->fetchAll();

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <a href="groups.php" style="text-decoration: none; color: #6b7280; font-size: 14px; display: flex; align-items: center; margin-bottom: 8px;">
            <i class="fas fa-arrow-left" style="margin-right: 5px;"></i> Back to Groups
        </a>
        <h2 style="margin: 0; color: var(--primary-color);">
            <i class="fas fa-users" style="margin-right: 10px;"></i><?php echo htmlspecialchars($group['group_name']); ?>
        </h2>
        <div style="font-size: 14px; color: #6b7280; margin-top: 5px;">
            <span style="font-weight: 600;"><?php echo htmlspecialchars($group['program']); ?></span>
            <span style="margin: 0 10px;">|</span>
            <?php echo format_date($group['program_start_date']); ?> - <?php echo format_date($group['program_end_date']); ?>
        </div>
    </div>
</div>

<style>
    .tabs {
        display: flex;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 20px;
    }
    .tab-btn {
        padding: 10px 20px;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        color: #6b7280;
    }
    .tab-btn.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    .mass-actions-bar {
        background: #f9fafb;
        padding: 10px 15px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .mass-action-select {
        padding: 6px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 14px;
    }
    .btn-apply {
        padding: 6px 12px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }
    .btn-apply:hover {
        opacity: 0.9;
    }
</style>

<div class="tabs">
    <button class="tab-btn active" onclick="openTab(event, 'candidates')">Enrolled Candidates (<?php echo count($students); ?>)</button>
    <button class="tab-btn" onclick="openTab(event, 'courses')">Courses (<?php echo count($assigned_courses); ?>)</button>
</div>

<!-- Enrolled Candidates Tab -->
<div id="candidates" class="tab-content active">
    <!-- Mass Action Form Wrapper around Table -->
    <form method="POST" id="massActionForm" onsubmit="return confirm('Are you sure you want to perform this action on selected students?');">
        <div class="card" style="overflow: hidden; padding: 0;">
            <div class="mass-actions-bar">
                <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                <label for="selectAll" style="font-size: 14px; color: #4b5563; cursor: pointer;">Select All</label>
                
                <div style="flex: 1;"></div>
                
                <span style="font-size: 14px; color: #6b7280;">Mass Actions:</span>
                <select name="mass_action" class="mass-action-select" required>
                    <option value="" disabled selected>-- Select Action --</option>
                    <option value="enroll">Enroll users in group courses</option>
                    <option value="unenroll">Unenroll users from group courses</option>
                </select>
                <button type="submit" class="btn-apply">Apply</button>
            </div>
            
            <div class="table-container" style="border: none;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"></th> <!-- Checkbox column -->
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>All Groups</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="selected_students[]" value="<?php echo $student['id']; ?>" class="student-checkbox">
                                </td>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <?php 
                                    if (!empty($student['group_names'])) {
                                        $g_names = explode(',', $student['group_names']);
                                        foreach($g_names as $gn) echo '<span class="badge" style="background:#e5e7eb; color:#374151; margin-right:4px;">'.htmlspecialchars(trim($gn)).'</span>';
                                    }
                                    ?>
                                </td>
                                <td><span class="badge <?php echo ($student['status']=='Inactive'||$student['status']=='Failed')?'badge-inactive':'badge-active'; ?>"><?php echo htmlspecialchars($student['status']); ?></span></td>
                                <td>
                                    <div style="display: flex;">
                                        <a href="student_view.php?id=<?php echo $student['id']; ?>" class="btn-action-gray" title="View Profile"><i class="fas fa-eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($students) === 0): ?>
                            <tr><td colspan="7" style="text-align: center; padding: 20px;">No candidates enrolled in this group.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<!-- Courses Tab -->
<div id="courses" class="tab-content">
    <div class="card">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
            <button class="btn btn-primary" onclick="openAddCourseModal()">
                <i class="fas fa-plus"></i> Add Course
            </button>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Course Name</th>
                        <th>Description</th>
                        <th>Assigned At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_courses as $course): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($course['course_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($course['description']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($course['assigned_at'])); ?></td>
                            <td>
                                <div style="display: flex;">
                                    <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn-action-gray" title="View Course"><i class="fas fa-eye"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this course from the group?');">
                                        <input type="hidden" name="remove_course" value="1">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="btn-action-gray" title="Remove" style="color: #ef4444;">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($assigned_courses) === 0): ?>
                        <tr><td colspan="4" style="text-align: center; padding: 20px;">No courses assigned to this group.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
if ($success || $error) {
    $msg = $success ?: $error;
    $type = $success ? 'success' : 'error';
    echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($msg) . "', '$type'); });</script>";
}
?>

<!-- Add Course Modal -->
<div id="addCourseModal" class="modal">
    <div class="modal-content" style="width: 500px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; color: #111827;">Assign Course to Group</h3>
            <span class="modal-close" onclick="document.getElementById('addCourseModal').style.display='none'">&times;</span>
        </div>

        <form method="post" style="padding: 24px;">
            <div class="form-group">
                <label class="form-label">Select Course</label>
                
                <!-- Searchable Dropdown Container -->
                <div class="searchable-dropdown" style="position: relative;">
                    <div class="dropdown-selected" onclick="toggleDropdown()" style="padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer; background: white; display: flex; justify-content: space-between; align-items: center;">
                        <span id="selectedCourseText" style="color: #6b7280;">-- Choose a course --</span>
                        <i class="fas fa-chevron-down" style="font-size: 12px; color: #9ca3af;"></i>
                    </div>
                    <input type="hidden" name="course_id" id="selectedCourseId" required>

                    <div id="dropdownList" class="dropdown-list" style="display: none; position: absolute; width: 100%; max-height: 250px; overflow-y: auto; background: white; border: 1px solid #d1d5db; border-radius: 6px; margin-top: 5px; z-index: 1000; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                        <div style="padding: 8px; border-bottom: 1px solid #f3f4f6; position: sticky; top: 0; background: white;">
                            <input type="text" id="courseSearchInput" placeholder="Search course..." onkeyup="filterCourses()" style="width: 100%; padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div id="courseItemsContainer">
                            <?php foreach ($available_courses as $c): ?>
                                <div class="dropdown-item" onclick="selectCourse('<?php echo $c['id']; ?>', '<?php echo addslashes($c['course_name']); ?>')" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #f9fafb; font-size: 14px;">
                                    <?php echo htmlspecialchars($c['course_name']); ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($available_courses) == 0): ?>
                                <div style="padding: 10px; color: #9ca3af; text-align: center; font-size: 14px;">No available courses found.</div>
                            <?php endif; ?>
                            <div id="noResults" style="display: none; padding: 10px; color: #9ca3af; text-align: center; font-size: 14px;">No courses found.</div>
                        </div>
                    </div>
                </div>

                <?php if (count($available_courses) == 0): ?>
                    <small style="color: orange; display: block; margin-top: 5px;">No available courses found.</small>
                <?php endif; ?>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn" style="background: white; border: 1px solid #d1d5db; color: #374151; margin-right: 10px;" onclick="document.getElementById('addCourseModal').style.display='none'">Cancel</button>
                <button type="submit" name="add_course" class="btn btn-primary" <?php echo count($available_courses) == 0 ? 'disabled' : ''; ?>>Add to Group</button>
            </div>
        </form>
    </div>
</div>

<style>
    .dropdown-item:hover {
        background-color: #f3f4f6;
    }
</style>

<script>
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(tabName).style.display = "block";
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
        
        // Save active tab to localStorage
        localStorage.setItem('activeGroupTab', tabName);
    }

    function openAddCourseModal() {
        document.getElementById('addCourseModal').style.display = 'block';
    }
    
    function toggleSelectAll(selectAllCheckbox) {
        const checkboxes = document.querySelectorAll('.student-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
    }

    // Dropdown Logic
    function toggleDropdown() {
        const list = document.getElementById('dropdownList');
        if (list.style.display === 'none') {
            list.style.display = 'block';
            document.getElementById('courseSearchInput').focus();
        } else {
            list.style.display = 'none';
        }
    }

    function filterCourses() {
        const input = document.getElementById('courseSearchInput');
        const filter = input.value.toUpperCase();
        const container = document.getElementById('courseItemsContainer');
        const items = container.getElementsByClassName('dropdown-item');
        let visibleCount = 0;

        for (let i = 0; i < items.length; i++) {
            const txtValue = items[i].textContent || items[i].innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                items[i].style.display = "";
                visibleCount++;
            } else {
                items[i].style.display = "none";
            }
        }
        
        const noResults = document.getElementById('noResults');
        if (visibleCount === 0 && items.length > 0) {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }

    function selectCourse(id, name) {
        document.getElementById('selectedCourseId').value = id;
        document.getElementById('selectedCourseText').innerText = name;
        document.getElementById('selectedCourseText').style.color = '#111827';
        document.getElementById('dropdownList').style.display = 'none';
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.searchable-dropdown');
        const list = document.getElementById('dropdownList');
        if (dropdown && !dropdown.contains(event.target)) {
            list.style.display = 'none';
        }
    });

    // Check local storage for active tab on load
    document.addEventListener('DOMContentLoaded', function() {
        const activeTab = localStorage.getItem('activeGroupTab');
        if (activeTab) {
            const tabBtn = document.querySelector(`.tab-btn[onclick*="'${activeTab}'"]`);
            if (tabBtn) {
                tabBtn.click();
            }
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>
