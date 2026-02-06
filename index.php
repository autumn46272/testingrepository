<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        redirect('student_dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = clean_input($_POST['username']);
    $password = clean_input($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Fetch user with secret and email
        $stmt = $pdo->prepare("SELECT id, username, password_hash, first_name, last_name, role, user_type, email, two_factor_secret FROM users WHERE username = :username AND status = 'active'");
        $stmt->execute(['username' => $username]);

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();
            if (password_verify($password, $user['password_hash'])) {
                
                // 1. Check for TOTP (Google Authenticator)
                if (!empty($user['two_factor_secret'])) {
                    // Redirect to verification (TOTP mode)
                    $_SESSION['temp_user_id'] = $user['id'];
                    $_SESSION['2fa_mode'] = 'totp'; // Flag for verify page
                    redirect('verify_2fa.php');
                } 
                // 2. Fallback to Email 2FA
                elseif (!empty($user['email'])) {
                    // Generate Code
                    $code = rand(100000, 999999);
                    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                    // Store code
                    $update = $pdo->prepare("UPDATE users SET two_factor_code = :code, two_factor_expires_at = :expires WHERE id = :id");
                    $update->execute([
                        'code' => $code,
                        'expires' => $expires,
                        'id' => $user['id']
                    ]);

                    // Send Email
                    $to = $user['email'];
                    $subject = "Your Login Verification Code";
                    $message = "Your verification code is: " . $code . "\n\nThis code expires in 10 minutes.";
                    $headers = "From: no-reply@studentdb.com";

                    // Use @ to suppress errors if mail server not configured
                    @mail($to, $subject, $message, $headers);

                    // Redirect to verification (Email mode)
                    $_SESSION['temp_user_id'] = $user['id'];
                    $_SESSION['2fa_mode'] = 'email';
                    redirect('verify_2fa.php');

                } 
                // 3. No 2FA - Direct Login
                else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_type'] = $user['user_type'];

                    // Redirect based on role
                    if ($user['role'] === 'student') {
                        redirect('student_dashboard.php');
                    } else {
                        redirect('dashboard.php');
                    }
                }
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Student DB Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .login-card {
            background: white;
            padding: 40px;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .alert {
            padding: 10px;
            background-color: #fee2e2;
            color: #991b1b;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <h2>StudentDB Admin</h2>
            <p class="text-muted">Please sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required
                    value="<?php echo isset($username) ? $username : ''; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Sign In</button>
        </form>
    </div>
</body>

</html>