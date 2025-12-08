<?php
session_start();
require_once "../includes/database.php";
require_once "../includes/notification_helper.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

$guide_id = isset($_POST["guide_id"])
    ? (int)$_POST["guide_id"]
    : (isset($_GET["guide_id"]) ? (int)$_GET["guide_id"] : 0);

$search_query = isset($_POST["search"])
    ? trim($_POST["search"])
    : (isset($_GET["search"]) ? trim($_GET["search"]) : "");

$stmt = $pdo->prepare("
    SELECT 
        u.id AS guide_user_id,
        u.name AS guide_name,
        g.location, g.languages, g.accommodation,
        g.rate_day, g.rate_hour
    FROM guides g
    JOIN users u ON g.user_id = u.id
    WHERE g.id = ?
");
$stmt->execute([$guide_id]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    exit("Guide not found.");
}

$success = isset($_GET["success"])
    ? "Booking successful! The guide has been notified."
    : null;

$stmt = $pdo->prepare("
    SELECT id, available_date
    FROM guide_availability
    WHERE guide_id = ? AND available_date >= CURDATE()
    ORDER BY available_date ASC
");

$stmt = $pdo->prepare("SELECT user_id, name FROM guides g JOIN users u ON g.user_id = u.id WHERE g.id = ?");
$stmt->execute([$guide_id]);
$guide_data = $stmt->fetch();

if ($guide_data) {
    // Notify the guide about new booking
    $tourist_name = $_SESSION['name'];
    createNotification(
        $pdo,
        $guide_data['user_id'],
        'guide',
        'new_booking',
        "New booking request from {$tourist_name} for {$start_date} to {$end_date}"
    );
}
$stmt->execute([$guide_id]);
$available_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_rate = $_GET["rate_type"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "confirm_booking") {

    $rate_type      = $_POST["rate_type"] ?? "";
    $selected_dates = $_POST["available_dates"] ?? [];
    $user_id        = $_SESSION["user_id"];
    $start_time     = $_POST["start_time"] ?? null;
    $end_time       = $_POST["end_time"] ?? null;

    if (!$rate_type) {
        $error = "Please select a rate type.";
    } elseif (empty($selected_dates)) {
        $error = "Please select at least one available date.";
    } elseif ($rate_type === "hour" && (!$start_time || !$end_time)) {
        $error = "Start and end time are required for hourly bookings.";
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($selected_dates as $date_id) {

                $check = $pdo->prepare("
                    SELECT available_date
                    FROM guide_availability
                    WHERE id = ? AND guide_id = ?
                ");
                $check->execute([$date_id, $guide_id]);
                $avail = $check->fetch(PDO::FETCH_ASSOC);

                if (!$avail) continue;

                $date = $avail["available_date"];

                if ($rate_type === "day") {
                    $ins = $pdo->prepare("
                        INSERT INTO bookings
                        (user_id, guide_id, start_date, end_date, booking_date, rate_type, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $ins->execute([$user_id, $guide_id, $date, $date, $date, $rate_type]);

                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO bookings
                        (user_id, guide_id, start_date, end_date, booking_date, rate_type, start_time, end_time, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $ins->execute([
                        $user_id, $guide_id, $date, $date, 
                        $date, $rate_type,
                        $start_time, $end_time
                    ]);
                }

                $booking_id = $pdo->lastInsertId();

                // CREATE NOTIFICATION using helper function
                $tourist_name = $_SESSION["name"];
                $notif_message = "New booking request from $tourist_name for $date";
                createNotification($pdo, $guide["guide_user_id"], 'guide', 'new_booking', $notif_message);

                $pdo->prepare("
                    DELETE FROM guide_availability
                    WHERE id = ? AND guide_id = ?
                ")->execute([$date_id, $guide_id]);
            }

            $pdo->commit();

            header(
                "Location: book_guide.php?guide_id={$guide_id}"
                . "&search=" . urlencode($search_query)
                . "&success=1"
                . "&rate_type=" . urlencode($rate_type)
            );
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Booking failed: " . $e->getMessage();
        }
    }

    $selected_rate = $rate_type;
}
?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book Guide - eTOUR</title>
<link rel="stylesheet" href="../style.css">
</head>

<body>
    <header class="navbar">
        <div class="logo">🌿 eTOUR | Book Guide</div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="search.php">Search Guides</a>
            <a href="bookings.php">My Bookings</a>
            <a href="view_notifications.php">Notifications</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </header>

<div class="container">
    <h2>Book <?= htmlspecialchars($guide["guide_name"]) ?></h2>
    <?php if (!empty($error)): ?>
        <div class="error-notification">
            <span>⚠️ </span> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-notification">
            <p>✅ <?= htmlspecialchars($success) ?></p>
        </div>
        <a href="bookings.php" class="btn">View My Bookings</a>
    <?php endif; ?>

    <div class="confirmation-box" style="margin-top: 2rem;">
        <h3>Guide Information</h3>
        <p><strong>📍 Location:</strong> <?= htmlspecialchars($guide["location"]) ?></p>
        <p><strong>🗣 Languages:</strong> <?= htmlspecialchars($guide["languages"]) ?></p>
        <p><strong>🏡 Accommodation:</strong> <?= htmlspecialchars($guide["accommodation"]) ?></p>
        <p><strong>💰 Day Rate:</strong> ₱<?= number_format($guide["rate_day"], 2) ?></p>
        <p><strong>💰 Hourly Rate:</strong> ₱<?= number_format($guide["rate_hour"], 2) ?></p>
    </div>

    <?php if (!$selected_rate): ?>
        <div class="confirmation-box" style="margin-top: 2rem;">
            <h3>Step 1: Select Rate Type</h3>

            <form method="GET">
                <input type="hidden" name="guide_id" value="<?= $guide_id ?>">
                <?php if ($search_query): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                <?php endif; ?>

                <label for="rate_type">Choose your booking type:</label>
                <select name="rate_type" id="rate_type" required>
                    <option value="">-- Select Rate --</option>
                    <option value="day">Day Rate (₱<?= number_format($guide["rate_day"], 2) ?>)</option>
                    <option value="hour">Hourly Rate (₱<?= number_format($guide["rate_hour"], 2) ?>)</option>
                </select>

                <br><br>
                <button class="btn">Continue to Date Selection</button>
            </form>
        </div>

    <?php else: ?>

    <div class="confirmation-box" style="margin-top: 2rem;">
        <h3>Step 2: Confirm Booking</h3>

        <form method="POST">
            <input type="hidden" name="action" value="confirm_booking">
            <input type="hidden" name="guide_id" value="<?= $guide_id ?>">
            <input type="hidden" name="rate_type" value="<?= htmlspecialchars($selected_rate) ?>">

            <?php if ($search_query): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
            <?php endif; ?>

            <p><strong>Selected Rate Type:</strong>
                <?= $selected_rate === "day" ? "Day Rate" : "Hourly Rate" ?>
            </p>

            <h4>Select Available Dates</h4>

            <?php if (empty($available_dates)): ?>
                <p style="color:#d9534f;font-weight:600;">❌ No available dates.</p>
            <?php else: ?>
                <div class="date-selection-grid">
                    <?php foreach ($available_dates as $d): ?>
                        <div class="date-checkbox-item">
                            <input type="checkbox" id="date_<?= $d["id"] ?>"
                                   name="available_dates[]" value="<?= $d["id"] ?>">
                            <label for="date_<?= $d["id"] ?>">
                                <?= date("M d, Y", strtotime($d["available_date"])) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($selected_rate === "hour"): ?>
                <br>
                <label>Start Time:</label>
                <input type="time" name="start_time" required>

                <label>End Time:</label>
                <input type="time" name="end_time" required>
            <?php endif; ?>

            <button class="btn" style="margin-top: 1.5rem;">✅ Confirm Booking</button>
        </form>

        <br>

        <form method="GET">
            <input type="hidden" name="guide_id" value="<?= $guide_id ?>">
            <?php if ($search_query): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
            <?php endif; ?>
            <button class="btn" style="background:#6c757d">← Change Rate Type</button>
        </form>
    </div>

    <?php endif; ?>

</div>
</body>
</html>
