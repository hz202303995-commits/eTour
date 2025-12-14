<?php
session_start();
require_once "../includes/database.php";

$errors = [];

if (!isset($_SESSION['pending_email'])) {
    die("No verification request found.");
}

$email = $_SESSION['pending_email'];

// Ensure a CSRF token exists for this session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_code = trim($_POST['code'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        // fetch id, verification_code, role and name in one query
        $stmt = $pdo->prepare("SELECT id, verification_code, role, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['verification_code'] == $input_code) {
            // single update only
            $upd = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
            $upd->execute([$user['id']]);

            // AUTO LOGIN user
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            unset($_SESSION['pending_email']);

            // Redirect to correct dashboard
            if ($user['role'] === 'guide') {
                header("Location: ../guides/dashboard.php");
            } else {
                header("Location: ../user/dashboard.php");
            }
            exit;
        } else {
            $errors[] = "Incorrect verification code.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification - eTOUR</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<div class="container">
    <div class="auth-box">
        <h2>Email Verification</h2>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $e): ?>
                <p class="error"><?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post">
            <label>Enter the 6-digit verification code sent to your email</label>
            <input type="text" name="code" maxlength="6" required>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <br>
            <button type="submit" class="btn">Verify</button>
        </form>

        <p><a href="login.php">Back to login</a></p>
    </div>
</div>

</body>
</html>
