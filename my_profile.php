<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'admin';
$username = $_SESSION['username']; // This is student_id for students

$profile_data = [];
$is_student = ($role == 'student');

if ($is_student) {
    // Fetch from students table
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = :sid");
    $stmt->execute(['sid' => $username]);
    $student = $stmt->fetch();

    if ($student) {
        $profile_data = [
            'Name' => $student['first_name'] . ' ' . $student['last_name'],
            'Student ID' => $student['student_id'],
            'Email' => $student['email'],
            'Branch' => $student['branch'],
            'Status' => $student['status'],
        ];
    }
} else {
    // Fetch from users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :uid");
    $stmt->execute(['uid' => $user_id]);
    $user = $stmt->fetch();

    if ($user) {
        $profile_data = [
            'Name' => $user['first_name'] . ' ' . $user['last_name'],
            'Username' => $user['username'],
            'Role' => $user['role'],
            'User Type' => $user['user_type'],
            'Status' => $user['status'],
        ];
    }
}
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>My Profile</h2>
</div>

<?php
require_once 'includes/GoogleAuthenticator.php';

$ga = new PHPGangsta_GoogleAuthenticator();

// Handle tab selection
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'personal';

// Messages
$error = isset($_GET['error']) ? urldecode($_GET['error']) : '';
$success = isset($_GET['success']) ? urldecode($_GET['success']) : '';

// 2FA Logic for Security Tab
$two_factor_enabled = false;
$user_secret = null;

// Check existing secret
$stmt_sec = $pdo->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
$stmt_sec->execute([$user_id]);
$u_sec = $stmt_sec->fetch();
if ($u_sec && !empty($u_sec['two_factor_secret'])) {
    $two_factor_enabled = true;
} else {
    // Generate a new secret for display (not saved until confirmed)
    $secret = $ga->createSecret();
    $qrCodeUrl = $ga->getQRCodeGoogleUrl($username, $secret, 'StudentDBSystem');
}
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>My Profile</h2>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:6px; margin-bottom:20px;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success" style="background:#dcfce7; color:#166534; padding:15px; border-radius:6px; margin-bottom:20px;">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="tabs" style="border-bottom: 1px solid #e5e7eb; margin-bottom: 20px;">
        <button onclick="window.location.href='?tab=personal'" 
                style="background:none; border:none; padding: 10px 20px; cursor:pointer; font-weight:600; 
                border-bottom: 2px solid <?php echo $active_tab == 'personal' ? 'var(--primary-color)' : 'transparent'; ?>; 
                color: <?php echo $active_tab == 'personal' ? 'var(--primary-color)' : '#6b7280'; ?>;">
            Personal Information
        </button>
        <button onclick="window.location.href='?tab=security'" 
                style="background:none; border:none; padding: 10px 20px; cursor:pointer; font-weight:600; 
                border-bottom: 2px solid <?php echo $active_tab == 'security' ? 'var(--primary-color)' : 'transparent'; ?>; 
                color: <?php echo $active_tab == 'security' ? 'var(--primary-color)' : '#6b7280'; ?>;">
            Security Settings
        </button>
    </div>

    <?php if ($active_tab == 'personal'): ?>
        <h3 style="margin-bottom: 20px; color: var(--secondary-color);">Personal Information</h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 20px; max-width: 600px;">
            <?php foreach ($profile_data as $label => $value): ?>
                <div style="display: flex; border-bottom: 1px solid #eee; padding: 10px 0;">
                    <strong style="width: 150px; color: #555;">
                        <?php echo htmlspecialchars($label); ?>:
                    </strong>
                    <span class="text-muted">
                        <?php echo htmlspecialchars($value); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif ($active_tab == 'security'): ?>
        
        <!-- CHANGE PASSWORD SECTION -->
        <div style="margin-bottom: 40px;">
            <h3 style="margin-bottom: 15px; color: var(--secondary-color); font-size: 16px;">Change Password</h3>
            <form action="profile_action.php" method="POST" style="max-width: 400px; background: #f9fafb; padding: 20px; border-radius: 8px;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; font-size:13px; font-weight:600;">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; font-size:13px; font-weight:600;">New Password</label>
                    <input type="password" name="new_password" class="form-control" required style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display:block; margin-bottom:5px; font-size:13px; font-weight:600;">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:4px;">
                </div>
                <button type="submit" name="change_password" class="btn btn-primary" style="width:100%;">Update Password</button>
            </form>
        </div>

        <!-- 2FA SECTION -->
        <div>
            <h3 style="margin-bottom: 15px; color: var(--secondary-color); font-size: 16px;">Two-Factor Authentication (2FA)</h3>
            
            <?php if ($two_factor_enabled): ?>
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 20px; border-radius: 8px;">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px;">
                        <i class="fas fa-check-circle" style="color: #10b981; font-size: 24px;"></i>
                        <div>
                            <strong style="color: #065f46;">2FA is currently ENABLED</strong>
                            <p style="margin:0; font-size:13px; color:#064e3b;">Your account is secured with Google Authenticator.</p>
                        </div>
                    </div>
                    <form action="profile_action.php" method="POST" onsubmit="return confirm('Are you sure you want to disable 2FA? Your account will be less secure.');">
                        <button type="submit" name="disable_2fa" class="btn" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;">Disable 2FA</button>
                    </form>
                </div>
            <?php else: ?>
                <div style="background: #fff; border: 1px solid #e5e7eb; padding: 20px; border-radius: 8px;">
                    <p style="margin-bottom: 20px; color: #4b5563;">Scan this QR code with your Google Authenticator or Microsoft Authenticator app:</p>
                    
                    <div style="display: flex; gap: 30px; align-items: flex-start;">
                        <div style="background: white; padding: 10px; border: 1px solid #eee; display: inline-block;">
                            <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" />
                        </div>
                        <div style="flex: 1;">
                            <form action="profile_action.php" method="POST">
                                <input type="hidden" name="secret" value="<?php echo $secret; ?>">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="display:block; margin-bottom:5px; font-size:13px; font-weight:600;">Enter 6-digit Code from App</label>
                                    <input type="text" name="code" class="form-control" placeholder="000000" required inputmode="numeric" pattern="[0-9]*" maxlength="6" style="width:200px; padding:8px; border:1px solid #d1d5db; border-radius:4px; font-size:18px; letter-spacing: 2px; text-align: center;">
                                </div>
                                <button type="submit" name="enable_2fa" class="btn btn-primary">Verify & Enable 2FA</button>
                            </form>
                            <div style="margin-top: 20px; font-size: 13px; color: #6b7280;">
                                <p><strong>Manual Entry:</strong> If you can't scan the code, enter this secret key manually:</p>
                                <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 14px;"><?php echo $secret; ?></code>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>