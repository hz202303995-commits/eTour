<?php
require_once "../includes/database.php";

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

            // Redirect based on role
            if ($user['role'] === 'guide') {
                header("Location: ../guides/dashboard.php"); // or wherever guides should go
                exit;
            } elseif ($user['role'] === 'tourist' || $user['role'] === 'user') {
                header("Location: ../user/dashboard.php"); // or wherever tourists should go
                exit;
            } else {
                // Fallback for any other role
                header("Location: ../index.php");
                exit;
            }
        }
    }
}
?>
<link rel="stylesheet" href="../style.css">
<h2>Login</h2>

<?php if (!empty($error)): ?>
<p style="color:red;"><?= $error ?></p>
<?php endif; ?>

<form method="post">

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