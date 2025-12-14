<?php
session_start();
require_once "../includes/database.php";

$error = "";
$message = "";

// If token present
if (!isset($_GET['token'])) {
    die("<p>Invalid password reset link.</p>");
}

$token = $_GET['token'];

// Check token in DB
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("<p>Invalid or expired reset token.</p>");
}

$user_id = $user['id'];

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Invalid request. Please try again.";
    } else {

    $new_password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (empty($new_password) || empty($confirm)) {
        $error = "Please fill out all fields.";
    } elseif ($new_password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Hash new password
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        // Update DB
        $upd = $pdo->prepare("
            UPDATE users 
            SET password = ?, reset_token = NULL 
            WHERE id = ?
        ");
        $upd->execute([$hashed, $user_id]);

        $message = "Password successfully reset. <a href='login.php'>Login here</a>";
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - eTOUR</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<div class="container">
    <div class="auth-box">
        <h2>Reset Password</h2>

<?php if ($error): ?>
<p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($message): ?>
<p class="success"><?= $message ?></p>
<?php else: ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <label>New Password:</label><br>
    <input type="password" name="password" required><br>

    <label>Confirm Password:</label><br>
    <input type="password" name="confirm" required><br><br>

    <button type="submit">Reset Password</button>
</form>

<?php endif; ?>
    <p>
    Remembered your password? <a href="login.php">Login here</a><br>
    Don't have an account? <a href="register.php">Register here</a>

        </p>
    </div>
</div>

</body>
</html>