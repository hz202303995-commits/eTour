<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
?>
<link rel="stylesheet" href="../assets/styles.css">
<h2>Reset Password</h2>

<?php if ($error): ?>
<p style="color:red;"><?= $error ?></p>
<?php endif; ?>

<?php if ($message): ?>
<p style="color:green;"><?= $message ?></p>
<?php else: ?>

<form method="post">
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