<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$error = '';
$success = '';

// Handle success message from URL (e.g., after redirect from student_add.php)
if (isset($_GET['success']) && $_GET['success'] == 'created' && isset($_GET['msg'])) {
    $success = urldecode($_GET['msg']);
}


// US States List
$us_states = [
    "Alabama", "Alaska", "American Samoa", "Arizona", "Arkansas", "Australia", "California", "Canada", "Colorado", 
    "Connecticut", "Delaware", "Florida", "Georgia", "Guam", "Hawaii", "Idaho", "Illinois", "Indiana", "Iowa", 
    "Kansas", "Kentucky", "Louisiana", "Maine", "Maryland", "Massachusetts", "Michigan", "Minnesota", "Mississippi", 
    "Missouri", "Montana", "Nebraska", "Nevada", "New Hampshire", "New Jersey", "New Mexico", "New York", 
    "North Carolina", "North Dakota", "North Mariana Islands", "Ohio", "Oklahoma", "Oregon", "Pennsylvania", 
    "Puerto Rico", "Rhode Island", "Saipan or NMI", "South Carolina", "South Dakota", "Tennessee", "Texas", 
    "Utah", "Vermont", "U.S. Virgin Islands", "Virginia", "Washington State", "Washington D.C.", "West Virginia", 
    "Wisconsin", "Wyoming"
];

// Handle Delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_student'])) {
    $id = clean_input($_POST['student_id']);
    try {
        $pdo->prepare("DELETE FROM student_groups WHERE student_id = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $success = "Student deleted successfully!";
    } catch (PDOException $e) {
        $error = "Error deleting student: " . $e->getMessage();
    }
}

// Handle Add Candidate
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_student'])) {
    // ID generation moved down
    
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $email = clean_input($_POST['email']);
    $gender = clean_input($_POST['gender']);
    $group_ids = isset($_POST['group_ids']) ? $_POST['group_ids'] : [];
    
    $bon_country = clean_input($_POST['bon_country']);
    $work_status = clean_input($_POST['work_status']);
    $rfid = clean_input($_POST['rfid']);
    $school = clean_input($_POST['school']);
    $exam_type = clean_input($_POST['exam_type']);
    $exam_takes = clean_input($_POST['exam_takes']);
    $emergency_name = clean_input($_POST['emergency_name']);
    $emergency_number = clean_input($_POST['emergency_number']);
    $status = clean_input($_POST['status']);
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL;
    $contact_number = clean_input($_POST['contact_number']);
    $prev_review_center = clean_input($_POST['prev_review_center']);
    
    // New Fields
    $city = isset($_POST['city']) ? clean_input($_POST['city']) : null;
    $branch = isset($_POST['branch']) ? clean_input($_POST['branch']) : null;
    
    // Generate ID after getting branch - USE branch-based ID generation
    $student_id = generate_branch_student_id($pdo, $branch);
    
    $application_status = clean_input($_POST['application_status']);
    $exam_status = clean_input($_POST['exam_status']);
    $exam_date = (!empty($_POST['exam_date']) && $exam_status == 'Scheduled') ? $_POST['exam_date'] : NULL;

    // Use bon_country field for BON/State (no schema change needed for column name)
    // Legacy 'country' column is ignored/unused based on current logic, using bon_country instead.

    $profile_image = NULL;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if (!file_exists('uploads')) mkdir('uploads', 0777, true);
            $new_filename = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['profile_image']['tmp_name'], 'uploads/' . $new_filename);
            $profile_image = $new_filename;
        }
    }

    try {
        $pdo->beginTransaction();
        $sql = "INSERT INTO students (student_id, first_name, last_name, email, gender, branch, bon_country, work_status, rfid, school, prev_review_center, exam_type, exam_takes, emergency_name, emergency_number, status, birthdate, contact_number, city, application_status, exam_status, exam_date, profile_image) VALUES (:sid, :fn, :ln, :email, :gender, :branch, :bon, :ws, :rfid, :sch, :prc, :et, :takes, :en, :enum, :stat, :bd, :cn, :city, :app_stat, :ex_stat, :ex_date, :img)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'sid' => $student_id, 'fn' => $first_name, 'ln' => $last_name, 'email' => $email, 'gender' => $gender, 'branch' => $branch,
            'bon' => $bon_country, 'ws' => $work_status, 'rfid' => $rfid, 'sch' => $school, 'prc' => $prev_review_center, 'et' => $exam_type, 'takes' => $exam_takes,
            'en' => $emergency_name, 'enum' => $emergency_number, 'stat' => $status, 'bd' => $birthdate, 'cn' => $contact_number, 
            'city' => $city, 'app_stat' => $application_status, 'ex_stat' => $exam_status, 'ex_date' => $exam_date, 'img' => $profile_image
        ]);
        $new_id = $pdo->lastInsertId();

        if (!empty($group_ids)) {
            $stmt_g = $pdo->prepare("INSERT INTO student_groups (student_id, group_id) VALUES (?, ?)");
            foreach ($group_ids as $gid) $stmt_g->execute([$new_id, $gid]);
        }

        $pdo->commit();
        
        // Create user account for the student
        $user_result = create_user_for_student($pdo, $student_id, $first_name, $last_name);
        
        // Prepare success message with credentials
        if ($user_result['success']) {
            $success = "Candidate added successfully! ID: " . $student_id . " | User account created - Username: {$user_result['username']} | Password: {$user_result['password']}";
        } else {
            $success = "Candidate added successfully! ID: " . $student_id . " | {$user_result['message']}";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Database Error: " . $e->getMessage();
    }
}

// Handle Edit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_student'])) {
    $id = clean_input($_POST['student_id_db']);
    $first_name = clean_input($_POST['edit_first_name']);
    $last_name = clean_input($_POST['edit_last_name']);
    $email = clean_input($_POST['edit_email']);
    $gender = clean_input($_POST['edit_gender']);
    $group_ids = isset($_POST['edit_group_ids']) ? $_POST['edit_group_ids'] : [];
    
    $bon_country = clean_input($_POST['edit_bon_country']);
    $work_status = clean_input($_POST['edit_work_status']);
    $rfid = clean_input($_POST['edit_rfid']);
    $school = clean_input($_POST['edit_school']);
    $prev_review_center = clean_input($_POST['edit_prev_review_center']);
    $exam_type = clean_input($_POST['edit_exam_type']);
    $exam_takes = clean_input($_POST['edit_exam_takes']);
    $emergency_name = clean_input($_POST['edit_emergency_name']);
    $emergency_number = clean_input($_POST['edit_emergency_number']);
    $status = clean_input($_POST['edit_status']);
    $contact_number = clean_input($_POST['edit_contact_number']);
    $birthdate = !empty($_POST['edit_birthdate']) ? $_POST['edit_birthdate'] : NULL;
    
    // New Fields
    $city = isset($_POST['edit_city']) ? clean_input($_POST['edit_city']) : null;
    $branch = isset($_POST['edit_branch']) ? clean_input($_POST['edit_branch']) : null;
    $application_status = clean_input($_POST['edit_application_status']);
    $exam_status = clean_input($_POST['edit_exam_status']);
    $exam_status = clean_input($_POST['edit_exam_status']);
    $exam_date = (!empty($_POST['edit_exam_date']) && $exam_status == 'Scheduled') ? $_POST['edit_exam_date'] : NULL;

    // Handle Profile Image Upload
    $profile_image_update = "";
    $params = [
        'fn' => $first_name, 'ln' => $last_name, 'email' => $email, 'gender' => $gender, 'branch' => $branch, 'bon' => $bon_country, 'ws' => $work_status, 
        'rfid' => $rfid, 'sch' => $school, 'prc' => $prev_review_center, 'et' => $exam_type, 'takes' => $exam_takes, 'en' => $emergency_name, 'enum' => $emergency_number, 
        'stat' => $status, 'cn' => $contact_number, 'bd' => $birthdate, 'city' => $city, 
        'app_stat' => $application_status, 'ex_stat' => $exam_status, 'ex_date' => $exam_date, 'id' => $id
    ];

    if (isset($_FILES['edit_profile_image']) && $_FILES['edit_profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['edit_profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if (!file_exists('uploads')) mkdir('uploads', 0777, true);
            $new_filename = uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['edit_profile_image']['tmp_name'], 'uploads/' . $new_filename);
            
            $profile_image_update = ", profile_image=:img";
            $params['img'] = $new_filename;
        }
    }

    try {
        $pdo->beginTransaction();
        $sql = "UPDATE students SET first_name=:fn, last_name=:ln, email=:email, gender=:gender, branch=:branch, bon_country=:bon, work_status=:ws, rfid=:rfid, school=:sch, prev_review_center=:prc, exam_type=:et, exam_takes=:takes, emergency_name=:en, emergency_number=:enum, status=:stat, contact_number=:cn, birthdate=:bd, city=:city, application_status=:app_stat, exam_status=:ex_stat, exam_date=:ex_date $profile_image_update WHERE id=:id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $pdo->prepare("DELETE FROM student_groups WHERE student_id = ?")->execute([$id]);
        if (!empty($group_ids)) {
            $stmt_g = $pdo->prepare("INSERT INTO student_groups (student_id, group_id) VALUES (?, ?)");
            foreach ($group_ids as $gid) $stmt_g->execute([$id, $gid]);
        }

        $pdo->commit();
        $success = "Student updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Update Error: " . $e->getMessage();
    }
}

// Fetch Students
try {
    $search_keyword = isset($_GET['search']) ? clean_input($_GET['search']) : '';
    $group_filter = isset($_GET['group_id']) ? clean_input($_GET['group_id']) : '';

    $query = "SELECT s.*, GROUP_CONCAT(g.group_name SEPARATOR ', ') as group_names, GROUP_CONCAT(g.id) as group_ids_str FROM students s LEFT JOIN student_groups sg ON s.id = sg.student_id LEFT JOIN groups g ON sg.group_id = g.id WHERE 1=1";
    $params = [];

    if (!empty($group_filter)) {
        $query .= " AND EXISTS (SELECT 1 FROM student_groups sg2 WHERE sg2.student_id = s.id AND sg2.group_id = :gid)";
        $params[':gid'] = $group_filter;
    }

    $query .= " GROUP BY s.id ORDER BY s.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    $groups = $pdo->query("SELECT id, group_name FROM groups ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) { die("DB Error: " . $e->getMessage()); }

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
    .form-section-header { font-size: 13px; font-weight: 700; color: var(--primary-color); text-transform: uppercase; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin: 20px 0 15px 0; }
    
    /* Custom Group Select Styles */
    .group-select-container {
        position: relative; /* Context for absolute list */
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #fff;
    }
    .group-search-box {
        position: relative;
    }
    .group-search-box input {
        width: 100%;
        border: none;
        padding: 10px 35px 10px 12px;
        font-size: 13px;
        outline: none;
        background: transparent;
    }
    .group-search-box i {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        pointer-events: none;
    }
    .group-list {
        display: none; /* Hidden by default */
        position: absolute;
        top: 100%;
        left: -1px; /* Align borders */
        right: -1px;
        background: #fff;
        border: 1px solid #3b82f6; /* Highlight border when open */
        border-top: none;
        border-radius: 0 0 6px 6px;
        max-height: 200px;
        overflow-y: auto;
        padding: 0;
        margin: 0;
        list-style: none;
        z-index: 50; /* Above other content */
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .group-list.show {
        display: block;
    }
    .group-item {
        display: block;
        padding: 8px 12px;
        font-size: 13px;
        color: #374151;
        cursor: pointer;
        border-bottom: 1px solid #f3f4f6;
        transition: background 0.1s;
        margin: 0;
    }
    .group-item:last-child { border-bottom: none; }
    .group-item:hover { background-color: #f9fafb; }
    
    /* Hidden checkbox, visual state handled by parent class */
    .group-item input[type="checkbox"] {
        display: none; 
    }
    
    /* Selected State */
    .group-item.selected {
        background-color: #3b82f6; /* Blue highlight */
        color: white;
    }

    /* Selected Groups Tags */
    .group-tags-container {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
        min-height: 28px;
    }
    .group-tag-item {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
        padding: 2px 8px;
        font-size: 12px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .group-tag-item i {
        cursor: pointer;
        opacity: 0.6;
    }
    .group-tag-item i:hover {
        opacity: 1;
        color: #ef4444;
    }
</style>

<div class="page-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>Candidates Management</h2>
    <div style="display: flex; gap: 10px;">
        <input type="text" id="searchInput" class="form-control" placeholder="Search candidates..." onkeyup="filterTable()" style="width: 250px; height: 40px;">
        <button class="btn btn-primary" onclick="openCreateModal()" style="height: 40px;">
            <i class="fas fa-plus" style="margin-right: 8px;"></i> Add Candidate
        </button>
    </div>
</div>

<?php 
if ($success || $error) {
    $msg = $success ?: $error;
    $type = $success ? 'success' : 'error';
    echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('".addslashes($msg)."', '$type'); });</script>";
}
?>

<div class="card mb-4" style="margin-bottom: 20px;">
    <form method="GET" action="students.php" style="display: flex; gap: 12px; align-items: flex-end;">
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label class="form-label" style="font-size: 13px; margin-bottom: 6px;">Filter by Group</label>
            <select name="group_id" class="form-control" style="height: 38px; padding-top: 6px; padding-bottom: 6px;">
                <option value="">All Groups</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?php echo $group['id']; ?>" <?php echo $group_filter == $group['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($group['group_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-bottom: 0;">
            <button type="submit" class="btn btn-secondary" style="height: 38px; padding: 0 20px;">Filter</button>
             <?php if (!empty($group_filter)): ?>
                <a href="students.php" class="btn" style="height: 38px; padding: 0 20px; background: #e5e7eb; color: #374151; text-decoration: none; display: inline-flex; align-items: center;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-container">
        <table id="studentsTable">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Groups / Programs</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><input type="checkbox" class="student-checkbox"></td>
                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                        <td>
                            <?php 
                            if (!empty($student['group_names'])) {
                                $g_names = explode(',', $student['group_names']);
                                foreach($g_names as $gn) echo '<span class="badge" style="background:#e5e7eb; color:#374151; margin-right:4px;">'.htmlspecialchars(trim($gn)).'</span>';
                            } else { echo '<span class="text-muted">No Group</span>'; }
                            ?>
                        </td>
                        <td><span class="badge <?php echo ($student['status']=='Inactive'||$student['status']=='Failed')?'badge-inactive':'badge-active'; ?>"><?php echo htmlspecialchars($student['status']); ?></span></td>
                        <td>
                            <div style="display: flex;">
                                <a href="student_view.php?id=<?php echo $student['id']; ?>" class="btn-action-gray" title="View"><i class="fas fa-eye"></i></a>
                                <button class="btn-action-gray" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($student)); ?>)"><i class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this student?');">
                                    <input type="hidden" name="delete_student" value="1">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <button type="submit" class="btn-action-gray delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($students) === 0): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No candidates found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- CREATE MODAL -->
<div id="createStudentModal" class="modal">
    <div class="modal-content" style="background-color: #fff; margin: 2% auto; padding: 0; border: none; width: 1000px; max-width: 95%; border-radius: 12px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between;">
            <h3 style="margin: 0; font-size: 18px;">Add New Candidate</h3>
            <span class="modal-close" onclick="document.getElementById('createStudentModal').style.display='none'">&times;</span>
        </div>
        <form method="post" enctype="multipart/form-data" id="createForm" style="padding: 24px;">
            <div style="display: grid; grid-template-columns: 250px 1fr; gap: 24px;">
                <!-- Left Column: Image & Status -->
                <div>
                    <div style="text-align: center; margin-bottom: 20px; background: #eff6ff; padding: 15px; border-radius: 8px;">
                        <span style="font-size: 11px; color: #1e40af; font-weight: 600; letter-spacing: 0.5px;">STUDENT ID (AUTO)</span><br>
                        <strong style="font-size: 16px; color: #1d4ed8;">RAPH-XXX / RAUS-XXX</strong>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Profile Image</label>
                        <input type="file" name="profile_image" class="form-control" onchange="previewImage(this, 'create_preview')">
                        <div id="create_preview" style="margin-top: 10px; width: 100%; height: 180px; background: #f9fafb; border: 2px dashed #e5e7eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            <span class="text-muted" style="font-size: 12px;">No Image Selected</span>
                        </div>
                    </div>

                    <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                </div>

                <!-- Right Column: Main Info -->
                <div>
                    <div class="form-section-header" style="margin-top: 0;">Personal Information</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Gender</label><select name="gender" class="form-control" required><option value="Male">Male</option><option value="Female">Female</option></select></div>
                        
                        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Contact No</label><input type="text" name="contact_number" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Birthdate</label><input type="date" name="birthdate" class="form-control"></div>
                        
                        <div class="form-group"><label class="form-label">Branch</label>
                            <select name="branch" class="form-control">
                                <option value="">Select Branch</option>
                                <option value="US">US</option>
                                <option value="Philippines">Philippines</option>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">City</label><input type="text" name="city" class="form-control"></div>
                        <div class="form-group"><label class="form-label">BON/State</label>
                            <select name="bon_country" class="form-control">
                                <option value="">Select State</option>
                                <?php foreach($us_states as $s): echo "<option value='$s'>$s</option>"; endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Work Status</label><select name="work_status" class="form-control"><option value="Unemployed">Unemployed</option><option value="Employed">Employed</option><option value="Student">Student</option></select></div>
                        <div class="form-group"><label class="form-label">RFID</label><input type="text" name="rfid" class="form-control"></div>
                    </div>
                
                    <div class="form-section-header">Academic Information</div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Batch / Groups (Select Multiple)</label>
                            <div class="group-select-container">
                                <div class="group-search-box">
                                    <input type="text" placeholder="Search groups..." onkeyup="filterGroups(this, 'create_groups_list')"
                                        onfocus="showGroupList('create_groups_list')" onblur="hideGroupList('create_groups_list')">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="group-list" id="create_groups_list">
                                    <?php foreach($groups as $g): ?>
                                    <label class="group-item" data-id="<?php echo $g['id']; ?>" data-name="<?php echo htmlspecialchars($g['group_name']); ?>" onclick="toggleGroupSelection(this, 'create_tags')">
                                        <input type="checkbox" name="group_ids[]" value="<?php echo $g['id']; ?>"> 
                                        <?php echo htmlspecialchars($g['group_name']); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div id="create_tags" class="group-tags-container"></div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                            <div class="form-group"><label class="form-label">School</label><input type="text" name="school" class="form-control"></div>
                            <div class="form-group"><label class="form-label">Previous Review Center</label><input type="text" name="prev_review_center" class="form-control"></div>
                            <div class="form-group"><label class="form-label">Application Status</label>
                                <select name="application_status" class="form-control">
                                    <option value="No NCLEX Application">No NCLEX Application</option>
                                    <option value="CGFNS">CGFNS</option>
                                    <option value="BON">BON</option>
                                    <option value="PEARSON VUE">PEARSON VUE</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <div class="form-group"><label class="form-label">Exam Status</label>
                             <select name="exam_status" class="form-control" onchange="toggleExamDate(this, 'create_exam_date_div')">
                                 <option value="No Exam Schedule">No Schedule</option>
                                 <option value="Scheduled">Scheduled</option>
                                 <option value="Passed">Passed</option>
                                 <option value="Failed">Failed</option>
                             </select>
                        </div>
                        <div class="form-group" id="create_exam_date_div" style="display: none;">
                             <label class="form-label">Exam Date</label>
                             <input type="date" name="exam_date" class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">Exam Takes</label><input type="number" name="exam_takes" class="form-control" value="1"></div>
                        <div class="form-group"><label class="form-label">Exam Type</label><select name="exam_type" class="form-control"><option value="NCLEX-RN">NCLEX-RN</option><option value="NCLEX-PN">NCLEX-PN</option></select></div>
                    </div>

                    <div class="form-section-header">Emergency Contact</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="emergency_name" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Contact Number</label><input type="text" name="emergency_number" class="form-control"></div>
                    </div>
                </div>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn" style="background: #ccc;" onclick="document.getElementById('createStudentModal').style.display='none'">Cancel</button>
                <button type="submit" name="create_student" class="btn btn-primary">Save Candidate</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editStudentModal" class="modal">
    <div class="modal-content" style="background-color: #fff; margin: 2% auto; padding: 0; border: none; width: 1000px; max-width: 95%; border-radius: 12px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between;">
            <h3 style="margin: 0; font-size: 18px;">Edit Candidate</h3>
            <span class="modal-close" onclick="document.getElementById('editStudentModal').style.display='none'">&times;</span>
        </div>
        <form method="post" enctype="multipart/form-data" style="padding: 24px;">
            <input type="hidden" name="student_id_db" id="edit_id_db">
            
            <div style="display: grid; grid-template-columns: 250px 1fr; gap: 24px;">
                <!-- Left Column: Image & Status -->
                <div>
                     <div style="text-align: center; margin-bottom: 20px;">
                        <span style="font-size: 11px; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">CURRENT PROFILE IMAGE</span>
                        <div id="edit_image_preview" style="margin-top: 10px; width: 100%; height: 180px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            <!-- Image injected via JS -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Change Image</label>
                        <input type="file" name="edit_profile_image" class="form-control" onchange="previewImage(this, 'edit_image_preview')">
                    </div>

                    <div class="form-group"><label class="form-label">Status</label><select name="edit_status" id="edit_status" class="form-control"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                </div>

                <!-- Right Column: Main Info -->
                <div>
                    <div class="form-section-header" style="margin-top: 0;">Personal Information</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label class="form-label">First Name</label><input type="text" name="edit_first_name" id="edit_first_name" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Last Name</label><input type="text" name="edit_last_name" id="edit_last_name" class="form-control" required></div>
                        <div class="form-group"><label class="form-label">Gender</label><select name="edit_gender" id="edit_gender" class="form-control"><option value="Male">Male</option><option value="Female">Female</option></select></div>
                        
                        <div class="form-group"><label class="form-label">Email</label><input type="email" name="edit_email" id="edit_email" class="form-control"></div>
                         <div class="form-group"><label class="form-label">Contact</label><input type="text" name="edit_contact_number" id="edit_contact_number" class="form-control"></div>
                         <div class="form-group"><label class="form-label">Birthdate</label><input type="date" name="edit_birthdate" id="edit_birthdate" class="form-control"></div>
                        
                        <div class="form-group"><label class="form-label">Branch</label>
                            <select name="edit_branch" id="edit_branch" class="form-control">
                                <option value="">Select Branch</option>
                                <option value="US">US</option>
                                <option value="Philippines">Philippines</option>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">City</label><input type="text" name="edit_city" id="edit_city" class="form-control"></div>
                         <div class="form-group"><label class="form-label">BON/State</label>
                             <select name="edit_bon_country" id="edit_bon_country" class="form-control">
                                <option value="">Select State</option>
                                <?php foreach($us_states as $s): echo "<option value='$s'>$s</option>"; endforeach; ?>
                            </select>
                        </div>
                         <div class="form-group"><label class="form-label">Work Status</label><select name="edit_work_status" id="edit_work_status" class="form-control"><option value="Unemployed">Unemployed</option><option value="Employed">Employed</option><option value="Student">Student</option></select></div>
                         <div class="form-group"><label class="form-label">RFID</label><input type="text" name="edit_rfid" id="edit_rfid" class="form-control"></div>
                    </div>

                    <div class="form-section-header">Academic Information</div>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label class="form-label">Batch / Groups (Select Multiple)</label>
                            <div class="group-select-container">
                                <div class="group-search-box">
                                    <input type="text" placeholder="Search groups..." onkeyup="filterGroups(this, 'edit_groups_list')"
                                        onfocus="showGroupList('edit_groups_list')" onblur="hideGroupList('edit_groups_list')">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="group-list" id="edit_groups_list">
                                    <?php foreach($groups as $g): ?>
                                    <label class="group-item" id="edit_group_<?php echo $g['id']; ?>" data-id="<?php echo $g['id']; ?>" data-name="<?php echo htmlspecialchars($g['group_name']); ?>" onclick="toggleGroupSelection(this, 'edit_tags')">
                                        <input type="checkbox" name="edit_group_ids[]" value="<?php echo $g['id']; ?>"> 
                                        <?php echo htmlspecialchars($g['group_name']); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div id="edit_tags" class="group-tags-container"></div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                            <div class="form-group"><label class="form-label">School</label><input type="text" name="edit_school" id="edit_school" class="form-control"></div>
                            <div class="form-group"><label class="form-label">Previous Review Center</label><input type="text" name="edit_prev_review_center" id="edit_prev_review_center" class="form-control"></div>
                            <div class="form-group"><label class="form-label">App Status</label>
                                <select name="edit_application_status" id="edit_application_status" class="form-control">
                                    <option value="No NCLEX Application">No NCLEX Application</option><option value="CGFNS">CGFNS</option><option value="BON">BON</option><option value="PEARSON VUE">PEARSON VUE</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-top: 15px;">
                        <div class="form-group"><label class="form-label">Exam Status</label>
                             <select name="edit_exam_status" id="edit_exam_status" class="form-control" onchange="toggleExamDate(this, 'edit_exam_date_div')">
                                <option value="No Exam Schedule">No Schedule</option><option value="Scheduled">Scheduled</option><option value="Passed">Passed</option><option value="Failed">Failed</option>
                             </select>
                        </div>
                        <div class="form-group" id="edit_exam_date_div" style="display: none;">
                            <label class="form-label">Exam Date</label>
                            <input type="date" name="edit_exam_date" id="edit_exam_date" class="form-control">
                        </div>
                        <div class="form-group"><label class="form-label">Takes</label><input type="number" name="edit_exam_takes" id="edit_exam_takes" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Exam Type</label><select name="edit_exam_type" id="edit_exam_type" class="form-control"><option value="NCLEX-RN">NCLEX-RN</option><option value="NCLEX-PN">NCLEX-PN</option></select></div>
                    </div>

                     <div class="form-section-header">Emergency Contact</div>
                     <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label class="form-label">Name</label><input type="text" name="edit_emergency_name" id="edit_emergency_name" class="form-control"></div>
                        <div class="form-group"><label class="form-label">Number</label><input type="text" name="edit_emergency_number" id="edit_emergency_number" class="form-control"></div>
                    </div>
                </div>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn" style="background: #ccc;" onclick="document.getElementById('editStudentModal').style.display='none'">Cancel</button>
                <button type="submit" name="edit_student" class="btn btn-primary">Update Candidate</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleExamDate(selectInfo, divId) {
        const div = document.getElementById(divId);
        if (selectInfo.value === 'Scheduled') {
            div.style.display = 'block';
        } else {
            div.style.display = 'none';
            // Optional: clear date value if hidden
            // const dateInput = div.querySelector('input');
            // if(dateInput) dateInput.value = ''; 
        }
    }

    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">`;
            }
            reader.readAsDataURL(input.files[0]);
        } else {
             preview.innerHTML = '<span class="text-muted" style="font-size: 12px;">No Image Selected</span>';
        }
    }

    function toggleGroupSelection(label, tagContainerId) {
        const checkbox = label.querySelector('input');
        // Delay to allow checkbox state to update
        setTimeout(() => {
            if (checkbox.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
            if (tagContainerId) updateGroupTags(tagContainerId, label.closest('.group-list'));
        }, 0);
    }

    function updateGroupTags(containerId, listElement) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        const selectedLabels = listElement.querySelectorAll('.group-item.selected');
        selectedLabels.forEach(lbl => {
            const name = lbl.getAttribute('data-name');
            const id = lbl.getAttribute('data-id');
            const tag = document.createElement('div');
            tag.className = 'group-tag-item';
            tag.innerHTML = `${name} <i class="fas fa-times" onclick="removeGroupTag('${listElement.id}', '${id}', '${containerId}')"></i>`;
            container.appendChild(tag);
        });
    }

    function removeGroupTag(listId, groupId, containerId) {
        const list = document.getElementById(listId);
        // Find the input with this value
        const checkbox = list.querySelector(`input[value="${groupId}"]`);
        if (checkbox) {
            checkbox.checked = false;
            checkbox.closest('label').classList.remove('selected');
            updateGroupTags(containerId, list);
        }
    }

    function openCreateModal() { 
        document.getElementById('createForm').reset();
        document.getElementById('create_tags').innerHTML = ''; // Clear tags
        document.getElementById('create_preview').innerHTML = '<span class="text-muted" style="font-size: 12px;">No Image Selected</span>'; // Clear preview
        // Clear selected classes
        document.querySelectorAll('#create_groups_list .group-item').forEach(l => {
             l.classList.remove('selected');
             l.querySelector('input').checked = false;
        });
        
        toggleExamDate(document.querySelector('select[name="exam_status"]'), 'create_exam_date_div');
        document.getElementById('createStudentModal').style.display = 'block'; 
    }

    function openEditModal(s) {
        document.getElementById('edit_id_db').value = s.id;
        document.getElementById('edit_first_name').value = s.first_name;
        document.getElementById('edit_last_name').value = s.last_name;
        document.getElementById('edit_gender').value = s.gender;
        document.getElementById('edit_email').value = s.email;
        document.getElementById('edit_contact_number').value = s.contact_number;
        document.getElementById('edit_birthdate').value = s.birthdate;
        document.getElementById('edit_branch').value = s.branch || '';
        document.getElementById('edit_city').value = s.city || '';
        document.getElementById('edit_bon_country').value = s.bon_country;
        
        document.getElementById('edit_work_status').value = s.work_status;
        document.getElementById('edit_rfid').value = s.rfid;
        
        document.getElementById('edit_school').value = s.school;
        document.getElementById('edit_prev_review_center').value = s.prev_review_center || '';
        document.getElementById('edit_application_status').value = s.application_status || 'No NCLEX Application';
        document.getElementById('edit_exam_status').value = s.exam_status || 'No Exam Schedule';
        
        const dateDiv = document.getElementById('edit_exam_date_div');
        if (s.exam_status === 'Scheduled') {
            dateDiv.style.display = 'block';
            document.getElementById('edit_exam_date').value = s.exam_date;
        } else {
            dateDiv.style.display = 'none';
             document.getElementById('edit_exam_date').value = '';
        }

        document.getElementById('edit_exam_type').value = s.exam_type;
        document.getElementById('edit_exam_takes').value = s.exam_takes;
        
        document.getElementById('edit_status').value = s.status;
        document.getElementById('edit_emergency_name').value = s.emergency_name;
        document.getElementById('edit_emergency_number').value = s.emergency_number;
        
        // Image Preview
        const editPreview = document.getElementById('edit_image_preview');
        if (s.profile_image) {
            editPreview.innerHTML = `<img src="uploads/${s.profile_image}" style="width:100%; height:100%; object-fit:cover;">`;
        } else {
            editPreview.innerHTML = `<div style="font-size:36px; color:#d1d5db;">${s.first_name.charAt(0)}</div>`;
        }

        const checkboxes = document.querySelectorAll('#edit_groups_list input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.checked = false;
            cb.parentElement.classList.remove('selected');
        });
        
        if (s.group_ids_str) {
            const ids = s.group_ids_str.split(',');
            ids.forEach(id => {
                const label = document.getElementById('edit_group_' + id.trim());
                if(label) {
                    const cb = label.querySelector('input');
                    cb.checked = true;
                    label.classList.add('selected');
                }
            });
        }
        updateGroupTags('edit_tags', document.getElementById('edit_groups_list'));
        
        document.getElementById('editStudentModal').style.display = 'block';
    }

    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('studentsTable');
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

    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.student-checkbox');
        checkboxes.forEach(cb => {
             if(cb.closest('tr').style.display !== 'none') cb.checked = source.checked;
        });
    }

    function filterGroups(input, listId) {
        // First ensure list is visible if user is typing
        showGroupList(listId);
        
        const filter = input.value.toUpperCase();
        const list = document.getElementById(listId);
        const items = list.getElementsByTagName('label');
        for (let i = 0; i < items.length; i++) {
            const txtValue = items[i].innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                items[i].style.display = "";
            } else {
                items[i].style.display = "none";
            }
        }
    }
    
    function showGroupList(listId) {
        document.getElementById(listId).classList.add('show');
    }
    
    function hideGroupList(listId) {
        // Delay to allow click event on items to register
        setTimeout(() => {
            document.getElementById(listId).classList.remove('show');
        }, 200);
    }

    function toggleGroupSelection(label) {
        // Prevent double toggle if clicking directly on checkbox (handled by browser default? No, input is hidden)
        // Since input is inside label, clicking label toggles input automatically.
        // We just need to sync the class.
        
        // Wait for the change to propagate? Or just check current state.
        // Actually, better to listen to the checkbox change event?
        // But the input is hidden. Let's doing it manually.
        
        const checkbox = label.querySelector('input');
        // If the click target was the label, the checkbox state toggles automatically.
        // We just toggle the class based on the NEW state.
        
        // Slight delay to allow checkbox state to update
        setTimeout(() => {
            if (checkbox.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
        }, 0);
    }
</script>

<?php require_once 'includes/footer.php'; ?>