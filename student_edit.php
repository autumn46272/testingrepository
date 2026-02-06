<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

if (!isset($_GET['id'])) {
    redirect('students.php');
}

$id = clean_input($_GET['id']);
$student = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$student->execute([$id]);
$student = $student->fetch();

if (!$student) {
    echo "Student not found.";
    exit();
}

$error = '';
$success = '';

// Predefined Programs
$available_programs = [
    "25 Day Course",
    "Final Coaching",
    "Intensive Review",
    "Refresher Course"
];

// Fetch Groups
$groups = $pdo->query("SELECT id, course_code, group_name FROM groups ORDER BY created_at DESC")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Required Fields (some can be readonly)
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $gender = clean_input($_POST['gender']);

    // Optional Fields
    $email = clean_input($_POST['email']);
    $enrollment_date = clean_input($_POST['enrollment_date']);
    $group_id = clean_input($_POST['group_id']);
    $programs = isset($_POST['programs']) ? $_POST['programs'] : [];

    // Image Upload (Only update if new image selected)
    $profile_image = $student['profile_image']; // Default to existing
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = array('jpg', 'jpeg', 'png', 'gif');
        $filename = $_FILES['profile_image']['name'];
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed)) {
            $new_filename = uniqid() . '.' . $file_ext;
            $destination = 'assets/uploads/' . $new_filename;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                $profile_image = $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, GIF are allowed.";
        }
    }

    if (empty($error)) {
        try {
            $sql = "UPDATE students SET 
                profile_image = :profile_image, 
                first_name = :first_name, 
                last_name = :last_name, 
                gender = :gender, 
                birthdate = :birthdate, 
                email = :email, 
                contact_number = :contact, 
                city = :city, 
                country = :country, 
                school = :school, 
                work_status = :work, 
                emergency_contact_name = :ec_name, 
                emergency_contact_number = :ec_number, 
                programs = :programs, 
                group_id = :group_id, 
                exam_type = :exam_type, 
                exam_date = :exam_date, 
                exam_status = :exam_status, 
                exam_takes = :exam_takes, 
                enrollment_date = :enrollment_date, 
                status = :status, 
                remarks = :remarks
                WHERE id = :id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'profile_image' => $profile_image,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'gender' => $gender,
                'birthdate' => clean_input($_POST['birthdate']),
                'email' => $email,
                'contact' => clean_input($_POST['contact_number']),
                'city' => clean_input($_POST['city']),
                'country' => clean_input($_POST['country']),
                'school' => clean_input($_POST['school']),
                'work' => clean_input($_POST['work_status']),
                'ec_name' => clean_input($_POST['emergency_contact_name']),
                'ec_number' => clean_input($_POST['emergency_contact_number']),
                'programs' => json_encode($programs),
                'group_id' => $group_id ? $group_id : null,
                'exam_type' => clean_input($_POST['exam_type']),
                'exam_date' => clean_input($_POST['exam_date']) ?: null,
                'exam_status' => clean_input($_POST['exam_status']),
                'exam_takes' => clean_input($_POST['exam_takes']) ?: 0,
                'enrollment_date' => $enrollment_date,
                'status' => clean_input($_POST['status']),
                'remarks' => clean_input($_POST['remarks']),
                'id' => $id
            ]);

            $success = "Student updated successfully.";
            // Refresh student data
            $student = $pdo->query("SELECT * FROM students WHERE id=$id")->fetch();

        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Decode programs
$current_programs = json_decode($student['programs'], true) ?? [];

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>Edit Student:
        <?php echo htmlspecialchars($student['first_name']); ?>
    </h2>
    <a href="students.php" class="btn" style="color: #666;"><i class="fas fa-arrow-left"></i> Back to List</a>
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

<form method="post" enctype="multipart/form-data">
    <div class="card">
        <h3
            style="margin-bottom: 20px; color: var(--secondary-color); border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Basic Information</h3>
        <div class="stat-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="form-group">
                <label class="form-label">Student ID (Read Only)</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['student_id']); ?>"
                    readonly style="background: #f9f9f9;">
            </div>
            <div class="form-group">
                <label class="form-label">Enrollment Date</label>
                <input type="date" name="enrollment_date" class="form-control"
                    value="<?php echo $student['enrollment_date']; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" name="first_name" class="form-control"
                    value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" name="last_name" class="form-control"
                    value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Gender *</label>
                <select name="gender" class="form-control" required>
                    <option value="Male" <?php echo $student['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $student['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $student['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Birthdate</label>
                <input type="date" name="birthdate" class="form-control" value="<?php echo $student['birthdate']; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                    value="<?php echo htmlspecialchars($student['email']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control"
                    value="<?php echo htmlspecialchars($student['contact_number']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Profile Image (Leave empty to keep current)</label>
                <input type="file" name="profile_image" class="form-control" accept="image/*">
                <?php if ($student['profile_image']): ?>
                    <small>Current:
                        <?php echo htmlspecialchars($student['profile_image']); ?>
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <h3 style="margin: 20px 0; color: var(--secondary-color); border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Additional Details</h3>
        <div class="stat-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control"
                    value="<?php echo htmlspecialchars($student['city']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Country / BON</label>
                <input type="text" name="country" class="form-control"
                    value="<?php echo htmlspecialchars($student['country']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">School</label>
                <input type="text" name="school" class="form-control"
                    value="<?php echo htmlspecialchars($student['school']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Work Status</label>
                <input type="text" name="work_status" class="form-control"
                    value="<?php echo htmlspecialchars($student['work_status']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name" class="form-control"
                    value="<?php echo htmlspecialchars($student['emergency_contact_name']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Emergency Contact Number</label>
                <input type="text" name="emergency_contact_number" class="form-control"
                    value="<?php echo htmlspecialchars($student['emergency_contact_number']); ?>">
            </div>
        </div>

        <h3 style="margin: 20px 0; color: var(--secondary-color); border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Academic & Program</h3>
        <div class="stat-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="form-group">
                <label class="form-label">Group / Batch</label>
                <select name="group_id" class="form-control">
                    <option value="">-- Select Group --</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo $g['id']; ?>" <?php echo $student['group_id'] == $g['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['course_code'] . ' - ' . $g['group_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Programs / Course Attended</label>
                <select name="programs[]" class="form-control" multiple style="height: 100px;">
                    <?php foreach ($available_programs as $prog): ?>
                        <option value="<?php echo $prog; ?>" <?php echo in_array($prog, $current_programs) ? 'selected' : ''; ?>
                            >
                            <?php echo $prog; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Exam Type</label>
                <input type="text" name="exam_type" class="form-control"
                    value="<?php echo htmlspecialchars($student['exam_type']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Exam Date</label>
                <input type="date" name="exam_date" class="form-control" value="<?php echo $student['exam_date']; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Exam Status</label>
                <select name="exam_status" class="form-control">
                    <?php foreach (['No Exam Yet', 'Scheduled', 'Passed', 'Failed'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $student['exam_status'] == $s ? 'selected' : ''; ?>>
                            <?php echo $s; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <?php foreach (['Active', 'Inactive', 'Graduated'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $student['status'] == $s ? 'selected' : ''; ?>>
                            <?php echo $s; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" class="form-control"
                rows="3"><?php echo htmlspecialchars($student['remarks']); ?></textarea>
        </div>

        <div style="margin-top: 20px; text-align: right;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 30px;">Update Student</button>
        </div>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>