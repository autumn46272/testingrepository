<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/GoogleAuthenticator.php';

// Ensure user is partially logged in (has user_id but not completed 2FA)
if (!isset($_SESSION['temp_user_id'])) {
    redirect('index.php');
}

$error = '';
$mode = isset($_SESSION['2fa_mode']) ? $_SESSION['2fa_mode'] : 'email'; // Default to email if not set

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code = clean_input($_POST['code']);

    if (empty($code)) {
        $error = "Please enter the verification code.";
    } else {
        $verified = false;
        $user_id = $_SESSION['temp_user_id'];

        if ($mode === 'totp') {
            // --- TOTP Verification ---
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute(['id' => $user_id]);
            $user = $stmt->fetch();

            if ($user && !empty($user['two_factor_secret'])) {
                $ga = new PHPGangsta_GoogleAuthenticator();
                $checkResult = $ga->verifyCode($user['two_factor_secret'], $code, 2); // 2 = 1 minute tolerance
                if ($checkResult) {
                    $verified = true;
                }
            }
        } else {
            // --- Email Verification ---
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND two_factor_code = :code AND two_factor_expires_at > NOW()");
            $stmt->execute([
                'id' => $user_id,
                'code' => $code
            ]);

            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                $verified = true;
                
                // Clear Used Code
                $update = $pdo->prepare("UPDATE users SET two_factor_code = NULL, two_factor_expires_at = NULL WHERE id = :id");
                $update->execute(['id' => $user['id']]);
            }
        }

        if ($verified && isset($user)) {
            // Set session variables (Login confirmed)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_type'] = $user['user_type'];

            // Clear temp session
            unset($_SESSION['temp_user_id']);
            unset($_SESSION['2fa_mode']);

            // Redirect based on role
            if ($user['role'] === 'student') {
                redirect('student_dashboard.php');
            } else {
                redirect('dashboard.php');
            }
        } else {
            $error = "Invalid verification code.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login - Student DB Admin</title>
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
            text-align: center;
        }

        .login-header {
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
        }
        
        .code-input {
            letter-spacing: 5px;
            font-size: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h2>Two-Factor Authentication</h2>
            <p class="text-muted">
                <?php if ($mode === 'totp'): ?>
                    Please enter the code from your Authenticator app.
                <?php else: ?>
                    Please enter the 6-digit code sent to your email.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <input type="text" name="code" class="form-control code-input" maxlength="6" required placeholder="000000" autocomplete="off" inputmode="numeric" pattern="[0-9]*">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Verify</button>
        </form>
        <p style="margin-top: 20px; font-size: 0.9rem;">
            <a href="index.php">Back to Login</a>
        </p>
    </div>
</body>
</html>
