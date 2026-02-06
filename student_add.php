<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$error = '';
$success = '';

// Fetch Groups for Dropdown
try {
    $stmt = $pdo->query("SELECT id, group_name FROM groups ORDER BY group_name ASC");
    $groups = $stmt->fetchAll();
} catch (PDOException $e) {
    $groups = [];
    $error = "Error fetching groups: " . $e->getMessage();
}

// Fetch Branches for Dropdown
try {
    $stmt = $pdo->query("SELECT branch_name FROM branch_counters ORDER BY branch_name ASC");
    $branches = $stmt->fetchAll();
} catch (PDOException $e) {
    $branches = [];
    // If table doesn't exist, use default branches
    if (strpos($e->getMessage(), 'branch_counters') !== false) {
        $error = "Warning: Please run add_branch_system.sql to create branch_counters table.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect Inputs
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $gender = clean_input($_POST['gender']);
    $group_id = clean_input($_POST['group_id']);
    $status = clean_input($_POST['status']);
    $branch = clean_input($_POST['branch']);

    // Optional Fields
    $email = clean_input($_POST['email']);
    $contact_number = clean_input($_POST['contact_number']);
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL;
    $city = clean_input($_POST['city']);
    $country = clean_input($_POST['country']);
    
    // Auto-generate Student ID based on branch
    $student_id = generate_branch_student_id($pdo, $branch);

    // Image Upload Handling
    $profile_image = NULL;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_image']['name'];
        $filetype = $_FILES['profile_image']['type'];
        $filesize = $_FILES['profile_image']['size'];
        $temp_name = $_FILES['profile_image']['tmp_name'];

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            // Create uploads directory if not exists
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }

            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/' . $new_filename;

            if (move_uploaded_file($temp_name, $upload_path)) {
                $profile_image = $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        }
    }

    if (empty($first_name) || empty($last_name) || empty($gender) || empty($group_id) || empty($branch)) {
        $error = "Please fill in all required fields.";
    } elseif (empty($error)) {
        try {
            $sql = "INSERT INTO students (student_id, first_name, last_name, gender, group_id, status, email, contact_number, birthdate, city, country, branch, profile_image) 
                    VALUES (:student_id, :first_name, :last_name, :gender, :group_id, :status, :email, :contact_number, :birthdate, :city, :country, :branch, :profile_image)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':student_id' => $student_id,
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':gender' => $gender,
                ':group_id' => $group_id,
                ':status' => $status,
                ':email' => $email,
                ':contact_number' => $contact_number,
                ':birthdate' => $birthdate,
                ':city' => $city,
                ':country' => $country,
                ':branch' => $branch,
                ':profile_image' => $profile_image
            ]);

            // Create user account for the student
            $user_result = create_user_for_student($pdo, $student_id, $first_name, $last_name);
            
            // Prepare success message with credentials
            if ($user_result['success']) {
                $success_msg = "Candidate created successfully! User account created with Username: {$user_result['username']} | Password: {$user_result['password']}";
            } else {
                $success_msg = "Candidate created successfully! {$user_result['message']}";
            }

            // Redirect to students page with success
            redirect('students.php?success=created&msg=' . urlencode($success_msg));

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Error: Student ID '$student_id' already exists.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>Add New Candidate</h2>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"
        style="margin-bottom: 20px; padding: 15px; background: #fee2e2; color: #991b1b; border-radius: 6px;">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" enctype="multipart/form-data">
        <h4
            style="color: var(--secondary-color); margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Personal Information</h4>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" name="first_name" class="form-control" required
                    value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="last_name" class="form-control" required
                    value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label">Gender <span class="text-danger">*</span></label>
                <select name="gender" class="form-control" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Birthdate</label>
                <input type="date" name="birthdate" class="form-control"
                    value="<?php echo isset($_POST['birthdate']) ? $_POST['birthdate'] : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Branch <span class="text-danger">*</span></label>
                <select name="branch" class="form-control" required>
                    <option value="">Select Branch</option>
                    <?php if (!empty($branches)): ?>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch['branch_name']); ?>" 
                                <?php echo (isset($_POST['branch']) && $_POST['branch'] == $branch['branch_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="Philippines" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'Philippines') ? 'selected' : ''; ?>>Philippines</option>
                        <option value="US" <?php echo (isset($_POST['branch']) && $_POST['branch'] == 'US') ? 'selected' : ''; ?>>US</option>
                    <?php endif; ?>
                </select>
                <small class="text-muted">Student ID will be auto-generated based on branch</small>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control"
                    value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control"
                    value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Country</label>
                <input type="text" name="country" class="form-control"
                    value="<?php echo isset($_POST['country']) ? htmlspecialchars($_POST['country']) : ''; ?>">
            </div>
        </div>



        <h4
            style="color: var(--secondary-color); margin: 30px 0 20px 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Academic Info</h4>

        <div class="form-group">
            <label class="form-label">Group <span class="text-danger">*</span></label>
            <select name="group_id" class="form-control" required>
                <option value="">Select Group</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?php echo $group['id']; ?>" <?php echo (isset($_POST['group_id']) && $_POST['group_id'] == $group['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($group['group_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="text-muted">Student ID will be automatically assigned when you save</small>
        </div>

        <div class="form-group">
            <label class="form-label">Status <span class="text-danger">*</span></label>
            <select name="status" class="form-control" required>
                <option value="Active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                <option value="Graduated" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Graduated') ? 'selected' : ''; ?>>Graduated</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Profile Image</label>
            <input type="file" name="profile_image" class="form-control" accept="image/*">
            <small class="text-muted">Allowed: JPG, PNG, GIF</small>
        </div>

        <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 10px;">
            <a href="students.php" class="btn btn-secondary"
                style="background-color: #ccc; border: 1px solid #bbb; color: #333;">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Candidate</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>