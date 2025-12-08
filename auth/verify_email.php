<?php
session_start();
require_once "../includes/database.php";

$errors = [];
$success = "";

if (!isset($_SESSION['pending_email'])) {
    die("No verification request found.");
}

$email = $_SESSION['pending_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_code = trim($_POST['code']);

    $stmt = $pdo->prepare("SELECT id, verification_code FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['verification_code'] == $input_code) {

        $upd = $pdo->prepare("
            UPDATE users SET is_verified = 1, verification_code = NULL
            WHERE id = ?
        ");
        $upd->execute([$user['id']]);

        // Mark email verified
        $upd = $pdo->prepare("
            UPDATE users SET is_verified = 1, verification_code = NULL
            WHERE id = ?
        ");
        $upd->execute([$user['id']]);

        // AUTO LOGIN user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user_role = $pdo->query("SELECT role FROM users WHERE id = {$user['id']}")->fetchColumn();
        $_SESSION['name'] = $pdo->query("SELECT name FROM users WHERE id = {$user['id']}")->fetchColumn();

        unset($_SESSION['pending_email']);

        // Redirect to correct dashboard
        if ($user_role === 'guide') {
            header("Location: ../guides/dashboard.php");
        } else {
            header("Location: ../user/dashboard.php");
        }
        exit;


    } else {
        $errors[] = "Incorrect verification code.";
    }
}
?>

<h2>Email Verification</h2>

<?php foreach ($errors as $e): ?>
    <p style="color:red;"><?= $e ?></p>
<?php endforeach; ?>

<?php if ($success): ?>
    <p style="color:green;"><?= $success ?></p>
    <a href="login.php">Go to Login</a>
<?php else: ?>
    <form method="post">
        <label>Enter the 6-digit verification code sent to your email</label><br>
        <input type="text" name="code" maxlength="6" required><br><br>
        <button type="submit">Verify</button>
    </form>
<?php endif; ?>

<p><a href="login.php">Back to login</a></p>
