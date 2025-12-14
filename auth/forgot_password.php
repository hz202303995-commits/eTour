<?php
session_start();
require_once "../includes/database.php";
require_once __DIR__ . '/../includes/email_config.php';

$message = "";
$error = "";
$step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;

// Password reset email handled by includes/email_config.php sendPasswordResetCode()

// Ensure csrf token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token for all POST actions
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Invalid request. Please try again.";
    } else if (isset($_POST['send_code'])) {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = "Please enter your email.";
        } else {
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "No account found with that email.";
            } else {
                $code = sprintf("%06d", mt_rand(100000, 999999));
                $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
                $upd->execute([$code, $expiry, $user['id']]);

                if (sendPasswordResetCode($email, $user['name'], $code)) {
                    $_SESSION['reset_step'] = 2;
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_id'] = $user['id'];
                    $step = 2;
                    $message = "Reset code sent to your email.";
                } else {
                    $error = "Failed to send reset code. Check server logs for details.";
                }
            }
        }
    } else if (isset($_POST['resend_code'])) {
        if (empty($_SESSION['reset_email']) || empty($_SESSION['reset_user_id'])) {
            $error = "No reset request found. Please start the reset process again.";
        } else {
            $email = $_SESSION['reset_email'];
            $userId = $_SESSION['reset_user_id'];
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user) {
                $code = sprintf("%06d", mt_rand(100000, 999999));
                $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?"); 
                $upd->execute([$code, $expiry, $user['id']]);

                if (sendPasswordResetCode($email, $user['name'], $code)) {
                    $message = "New reset code sent to your email.";
                } else {
                    $error = "Failed to resend reset code. Please try again later.";
                }
            } else {
                $error = "User not found for this reset request.";
            }
        }
    } else if (isset($_POST['reset_password'])) {
        $code = trim($_POST['code'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $email = $_SESSION['reset_email'] ?? '';

        if (empty($code) || empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in all fields.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif (empty($email)) {
            $error = "Reset session missing. Start the reset process again.";
        } else {
            $stmt = $pdo->prepare("SELECT id, reset_token, reset_token_expiry FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "User not found.";
            } elseif ($user['reset_token'] != $code) {
                $error = "Incorrect verification code.";
            } elseif (strtotime($user['reset_token_expiry']) < time()) {
                $error = "Reset code has expired. Please request a new code.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                $upd->execute([$hashed_password, $user['id']]);

                unset($_SESSION['reset_step']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);

                $message = "Password reset successful! You can now login.";
                $step = 3;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Forgot Password - eTour</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

    <div class="container">
        <div class="auth-box">
            <h2>Forgot Password</h2>

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if ($message): ?>
    <p class="success"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<?php if ($step == 1): ?>
    <form method="post">
        <label>Enter your email:</label><br>
        <input type="email" name="email" required><br><br>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" name="send_code">Send Reset Code</button>
    </form>

<?php elseif ($step == 2): ?>
    <p>A 6-digit code has been sent to <?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></p>
    <form method="post">
        <label>Enter Reset Code:</label><br>
        <input type="text" name="code" maxlength="6" required><br><br>

        <label>New Password:</label><br>
        <input type="password" name="new_password" required><br><br>

        <label>Confirm Password:</label><br>
        <input type="password" name="confirm_password" required><br><br>

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" name="reset_password">Reset Password</button>
    </form>

    <br>
    <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" name="resend_code">Resend Code</button>
    </form>

<?php elseif ($step == 3): ?>
    <p><a href="login.php">Click here to login</a></p>
<?php endif; ?>

            <p>
                Remembered your password? <a href="login.php">Login here</a><br>
                Don't have an account? <a href="register.php">Register here</a>
            </p>
        </div>
    </div>

</body>
</html>
