<?php
session_start();
require_once "../includes/database.php";

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Invalid request. Please try again.";
    } else {

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = "Email not found.";
        } elseif (!$user['is_verified']) {
            $error = "Please verify your email first.";
        } elseif (!password_verify($password, $user['password'])) {
            $error = "Incorrect password.";
        } else {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            if ($user['role'] === 'guide') {
                header("Location: ../guides/dashboard.php");
                exit;
            } elseif ($user['role'] === 'tourist' || $user['role'] === 'user') {
                header("Location: ../user/dashboard.php");
                exit;
            } else {
                header("Location: ../index.php");
                exit;
            }
        }
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - eTOUR</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<div class="container">
    <div class="auth-box">
        <h2>Login</h2>

    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <label>Email</label><br>
        <input type="email" name="email" required><br>

        <label>Password</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Login</button>
        </form>

        <p>
        Don't have an account? <a href="register.php">Register here</a><br>
        Forgot your password? <a href="forgot_password.php">Reset it here</a>
    </p>
    </div>
</div>

</body>
</html>