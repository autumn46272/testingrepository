<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$error = '';
$success = '';

// Handle Create User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_user'])) {
    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']);
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $email = clean_input($_POST['email']);
    $user_type = clean_input($_POST['user_type']);

    if (empty($username) || empty($password) || empty($first_name) || empty($user_type)) {
        $error = "All fields are required.";
    } else {
        // Hash password
        $pass_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Always set role to 'student' (user_type still differentiates permissions)
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, first_name, last_name, email, role, user_type) VALUES (:user, :pass, :fname, :lname, :email, 'student', :type)");
            $stmt->execute([
                'user' => $username,
                'pass' => $pass_hash,
                'fname' => $first_name,
                'lname' => $last_name,
                'email' => $email,
                'type' => $user_type
            ]);
            $success = "User created successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Username '$username' already exists.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $uid = clean_input($_POST['user_id']);
    // Prevent self-deletion
    if ($uid == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $uid]);
            $success = "User deleted successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Edit User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_user'])) {
    $uid = clean_input($_POST['user_id']);
    $user_type = clean_input($_POST['user_type']);
    $email = clean_input($_POST['email']);
    $password = clean_input($_POST['password']);

    if (empty($user_type)) {
        $error = "User Type is required.";
    } else {
        try {
            if (!empty($password)) {
                $pass_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET user_type = :type, email = :email, password_hash = :pass WHERE id = :id");
                $stmt->execute(['type' => $user_type, 'email' => $email, 'pass' => $pass_hash, 'id' => $uid]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET user_type = :type, email = :email WHERE id = :id");
                $stmt->execute(['type' => $user_type, 'email' => $email, 'id' => $uid]);
            }
            $success = "User updated successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Status Toggle (Deactivate)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $uid = clean_input($_GET['id']);
    // Prevent self-deactivation
    if ($uid == $_SESSION['user_id']) {
        $error = "You cannot deactivate your own account.";
    } else {
        $current_status = $pdo->query("SELECT status FROM users WHERE id=$uid")->fetchColumn();
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';

        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $uid]);
        redirect('users.php');
    }
}

// Fetch Users with Search/Filter
$search_keyword = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search_keyword)) {
    $query .= " AND (username LIKE :keyword OR first_name LIKE :keyword OR last_name LIKE :keyword)";
    $params[':keyword'] = "%$search_keyword%";
}

if (!empty($status_filter)) {
    $query .= " AND status = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// User Types Array
$user_types = [
    "Student",
    "Admin",
    "Super Admin",
    "Student Assistant",
    "Academic Assistant",
    "Academic Coach"
];

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header"
    style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>System Users</h2>
    <div style="display:flex; gap: 10px;">
        <button class="btn btn-primary" onclick="document.getElementById('createUserModal').style.display='block'">
            <i class="fas fa-user-plus" style="margin-right: 8px;"></i> Add User
        </button>
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

<!-- Filters -->
<div class="card mb-4" style="margin-bottom: 20px;">
    <form method="GET" action="users.php" style="display: flex; gap: 12px; align-items: flex-end;">
        <div class="form-group" style="flex: 2; margin-bottom: 0;">
            <label class="form-label" style="font-size: 13px; margin-bottom: 6px;">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Username or name..."
                value="<?php echo htmlspecialchars($search_keyword); ?>" style="height: 38px;">
        </div>
        <div class="form-group" style="flex: 1; margin-bottom: 0;">
            <label class="form-label" style="font-size: 13px; margin-bottom: 6px;">Status</label>
            <select name="status" class="form-control" style="height: 38px; padding-top: 6px; padding-bottom: 6px;">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div style="display: flex; gap: 8px; margin-bottom: 0;">
            <button type="submit" class="btn btn-secondary" style="height: 38px; padding: 0 20px;">Filter</button>
            <?php if (!empty($search_keyword) || !empty($status_filter)): ?>
                <a href="users.php" class="btn"
                    style="height: 38px; padding: 0 20px; background: #e5e7eb; color: #374151; text-decoration: none; display: flex; align-items: center;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>User Type</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="font-weight: bold;">
                            <?php echo htmlspecialchars($u['username']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($u['email']); ?>
                        </td>
                        <td>
                            <span class="badge" style="background: #f3f4f6; color: #4b5563;">
                                <?php echo htmlspecialchars($u['user_type'] ?? 'Admin'); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $u['status'] == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                                <?php echo ucfirst($u['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo format_date($u['created_at']); ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 5px; align-items: center;">
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-action-gray" title="Edit"
                                        onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($u)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <a href="users.php?toggle=1&id=<?php echo $u['id']; ?>" class="btn-action-gray"
                                        style="color: <?php echo $u['status'] == 'active' ? '#ef4444' : '#10b981'; ?>;"
                                        title="<?php echo $u['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>"
                                        onclick="return confirm('Change status for this user?')">
                                        <i class="fas fa-power-off"></i>
                                    </a>

                                    <form method="POST" style="display:inline;"
                                        onsubmit="return confirm('Permanently delete this user?');">
                                        <input type="hidden" name="delete_user" value="1">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn-action-gray delete" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Bigger Current User Icon -->
                                    <div class="btn-action-gray" style="cursor: default; background: #e5e7eb; color: #6b7280;"
                                        title="Current User">
                                        <i class="fas fa-user-check"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Modal -->
<div id="createUserModal" class="modal"
    style="display:none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content"
        style="background-color: #fefefe; margin: 5% auto; padding: 25px; border: 1px solid #888; width: 500px; border-radius: 8px;">
        <span onclick="document.getElementById('createUserModal').style.display='none'"
            style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        <h3 style="margin-bottom: 20px; color: var(--secondary-color);">Create User</h3>

        <form method="post">
            <div class="form-group">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="stat-grid" style="grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">User Type <span class="text-danger">*</span></label>
                <select name="user_type" class="form-control" required>
                    <?php foreach ($user_types as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn" style="background: #ccc; margin-right: 10px;"
                    onclick="document.getElementById('createUserModal').style.display='none'">Cancel</button>
                <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editUserModal" class="modal"
    style="display:none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content"
        style="background-color: #fefefe; margin: 10% auto; padding: 25px; border: 1px solid #888; width: 500px; border-radius: 8px;">
        <span onclick="document.getElementById('editUserModal').style.display='none'"
            style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        <h3 style="margin-bottom: 20px; color: var(--secondary-color);">Edit User</h3>

        <form method="post">
            <input type="hidden" name="user_id" id="edit_user_id">

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" id="edit_username" class="form-control" disabled style="background: #f3f4f6;">
            </div>
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" id="edit_email" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">User Type <span class="text-danger">*</span></label>
                <select name="user_type" id="edit_user_type" class="form-control" required>
                    <?php foreach ($user_types as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control"
                    placeholder="Leave blank to keep current password">
                <small class="text-muted">Only enter if you want to change the password.</small>
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn" style="background: #ccc; margin-right: 10px;"
                    onclick="document.getElementById('editUserModal').style.display='none'">Cancel</button>
                <button type="submit" name="edit_user" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditUserModal(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_user_type').value = user.user_type || 'Admin';
        document.getElementById('editUserModal').style.display = 'block';
    }
</script>

<?php require_once 'includes/footer.php'; ?>