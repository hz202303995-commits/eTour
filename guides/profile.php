<?php
session_start();
require_once "../includes/database.php";

/* =======================================================
   ACCESS CHECK — only guides not in tourist mode
   ======================================================= */
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "guide" ||
    !empty($_SESSION["is_tourist_mode"])
) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];
$message = "";

/* =======================================================
   HANDLE PROFILE UPDATE
   ======================================================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {

    $contact       = trim($_POST["contact"]);
    $location      = trim($_POST["location"]);
    $languages     = trim($_POST["languages"]);
    $accommodation = trim($_POST["accommodation"]);
    $rateDay       = isset($_POST["rate_day"]) ? floatval($_POST["rate_day"]) : 0;
    $rateHour      = isset($_POST["rate_hour"]) ? floatval($_POST["rate_hour"]) : 0;

    // Check if guide profile exists
    $stmt = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
    $stmt->execute([$userId]);
    $guideExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($guideExists) {
        // Update existing profile
        $update = $pdo->prepare("
            UPDATE guides
            SET contact = ?, location = ?, languages = ?, accommodation = ?, rate_day = ?, rate_hour = ?
            WHERE user_id = ?
        ");
        $update->execute([$contact, $location, $languages, $accommodation, $rateDay, $rateHour, $userId]);

    } else {
        // Insert new guide profile
        $insert = $pdo->prepare("
            INSERT INTO guides (user_id, contact, location, languages, accommodation, rate_day, rate_hour)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$userId, $contact, $location, $languages, $accommodation, $rateDay, $rateHour]);
    }

    $message = "Profile updated successfully!";
}

/* =======================================================
   FETCH PROFILE INFO
   ======================================================= */
$stmt = $pdo->prepare("SELECT * FROM guides WHERE user_id = ?");
$stmt->execute([$userId]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch name & email from users table
$userStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Guide Profile - eTOUR</title>
<link rel="stylesheet" href="../style.css">

<style>
    .container { max-width:900px; margin:30px auto; }
    form label { font-weight:600; margin-top:12px; display:block; }
    input, textarea { width:100%; padding:8px; margin-top:4px; }
    textarea { height:80px; }
    .btn { margin-top:15px; padding:10px 16px; }
    .info-message { background:#e9f9ee; border-left:4px solid #2b7a78; padding:10px; border-radius:6px; }
</style>
</head>

<body>

<header class="navbar">
    <div class="logo">🌿 eTOUR | Guide Profile</div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="availability.php">Availability</a>
        <a href="manage_bookings.php">Manage Bookings</a>
        <a href="profile.php" class="active-link">Profile</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <h2>Guide Profile</h2>

    <?php if ($message): ?>
        <p class="info-message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Name:</label>
        <input type="text" value="<?= htmlspecialchars($user["name"] ?? "") ?>" disabled>

        <label>Email:</label>
        <input type="email" value="<?= htmlspecialchars($user["email"] ?? "") ?>" disabled>

        <label>Contact:</label>
        <input type="text" name="contact" required
               value="<?= htmlspecialchars($guide["contact"] ?? "") ?>"
               placeholder="Enter your contact number">

        <label>Location:</label>
        <input type="text" name="location" required
               value="<?= htmlspecialchars($guide["location"] ?? "") ?>"
               placeholder="Enter your location">

        <label>Languages:</label>
        <input type="text" name="languages" required
               value="<?= htmlspecialchars($guide["languages"] ?? "") ?>"
               placeholder="e.g., English, Filipino">

        <label>Accommodation:</label>
        <textarea name="accommodation" required><?= htmlspecialchars($guide["accommodation"] ?? "") ?></textarea>

        <label>Rate per Day:</label>
        <input type="number" step="0.01" name="rate_day" required
               value="<?= htmlspecialchars($guide["rate_day"] ?? "") ?>">

        <label>Rate per Hour:</label>
        <input type="number" step="0.01" name="rate_hour" required
               value="<?= htmlspecialchars($guide["rate_hour"] ?? "") ?>">

        <button type="submit" name="update_profile" class="btn">Update Profile</button>
    </form>
</div>

</body>
</html>
