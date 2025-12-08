<?php
session_start();
require_once "../includes/database.php";
require_once "../includes/email_config.php";

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user';

    if (!$name || !$email || !$password) {
        $errors[] = "All fields are required.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $errors[] = "Email already registered.";
        } else {
            $verification_code = random_int(100000, 999999);

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare("
                INSERT INTO users (name, email, password, role, verification_code, is_verified)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $insert->execute([$name, $email, $hashed_password, $role, $verification_code]);

            if (sendVerificationEmail($email, $name, $verification_code)) {
                $_SESSION['pending_email'] = $email;
                header("Location: verify_email.php");
                exit;
            } else {
                $errors[] = "Failed to send verification email.";
            }
        }
    }
}
?>

<link rel="stylesheet" href="../style.css">
<h2>Register</h2>

<?php if (!empty($errors)): ?>
    <?php foreach ($errors as $e): ?>
        <p style="color:red;"><?= $e ?></p>
    <?php endforeach; ?>
<?php endif; ?>

<form method="post">

    <label>Name</label><br>
    <input type="text" name="name" required><br>

    <label>Email</label><br>
    <input type="email" name="email" required><br>

    <label>Password</label><br>
    <input type="password" name="password" required><br>

    <label>Role</label><br>
    <select name="role">
        <option value="user">Tourist</option>
        <option value="guide">Guide</option>
    </select>
    <br><br>

    <button type="submit">Register</button>
</form>
<p>
    Already have an account? <a href="login.php">Login here</a><br>
    Forgot your password? <a href="forgot_password.php">Reset it here</a>
</p>