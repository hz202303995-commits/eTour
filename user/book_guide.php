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

// Fetch guide information
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

// Fetch available dates
$stmt = $pdo->prepare("
    SELECT id, available_date
    FROM guide_availability
    WHERE guide_id = ? AND available_date >= CURDATE()
    ORDER BY available_date ASC
");
$stmt->execute([$guide_id]);
$available_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_rate = $_GET["rate_type"] ?? null;
$error = null;

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// Handle booking confirmation
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "confirm_booking") {
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!(isset($_SESSION['csrf_token']) && hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$posted_csrf))) {
        $error = "Invalid request (CSRF).";
    } else {

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

                // Create notification
                $tourist_name = $_SESSION["name"];
                $notif_message = "New booking request from $tourist_name for $date";
                createNotification($pdo, $guide["guide_user_id"], 'guide', 'new_booking', $notif_message);

                // Remove availability after booking
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            <a href="../auth/logout.php">Logout</a>
        </div>
    </header>

    <div class="container">
        <h2>Book <?php echo htmlspecialchars($guide["guide_name"]); ?></h2>
        
        <?php if (!empty($error)): ?>
            <div class="error-notification">
                <span>⚠️ </span> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-notification">
                <p>✅ <?php echo htmlspecialchars($success); ?></p>
            </div>
            <a href="bookings.php" class="btn">View My Bookings</a>
        <?php endif; ?>

        <div class="confirmation-box">
            <h3>Guide Information</h3>
            <p class="info-row"><span class="label">📍 Location:</span><span class="value"><?php echo htmlspecialchars($guide["location"]); ?></span></p>
            <p class="info-row"><span class="label">🗣 Languages:</span><span class="value"><?php echo htmlspecialchars($guide["languages"]); ?></span></p>
            <p class="info-row"><span class="label">🏡 Accommodation:</span><span class="value"><?php echo nl2br(htmlspecialchars($guide["accommodation"])); ?></span></p>
            <p class="info-row"><span class="label">💰 Day Rate:</span><span class="value">₱<?php echo number_format($guide["rate_day"], 2); ?></span></p>
            <p class="info-row"><span class="label">💰 Hourly Rate:</span><span class="value">₱<?php echo number_format($guide["rate_hour"], 2); ?></span></p>
        </div>

        <?php if (!$selected_rate): ?>
            <div class="confirmation-box">
                <h3>Step 1: Select Rate Type</h3>

                <form method="GET">
                    <input type="hidden" name="guide_id" value="<?php echo $guide_id; ?>">
                    <?php if ($search_query): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>

                    <label for="rate_type">Choose your booking type:</label>
                    <select name="rate_type" id="rate_type" required>
                        <option value="">-- Select Rate --</option>
                        <option value="day">Day Rate (₱<?php echo number_format($guide["rate_day"], 2); ?>)</option>
                        <option value="hour">Hourly Rate (₱<?php echo number_format($guide["rate_hour"], 2); ?>)</option>
                    </select>

                    <br><br>
                    <button class="btn">Continue to Date Selection</button>
                </form>
            </div>

        <?php else: ?>

            <div class="confirmation-box">
                <h3>Step 2: Confirm Booking</h3>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="action" value="confirm_booking">
                    <input type="hidden" name="guide_id" value="<?php echo $guide_id; ?>">
                    <input type="hidden" name="rate_type" value="<?php echo htmlspecialchars($selected_rate); ?>">

                    <?php if ($search_query): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>

                    <p><strong>Selected Rate Type:</strong>
                        <?php echo $selected_rate === "day" ? "Day Rate" : "Hourly Rate"; ?>
                    </p>

                    <h4>Select Available Dates</h4>

                    <?php if (empty($available_dates)): ?>
                        <p class="no-dates">❌ No available dates.</p>
                    <?php else: ?>
                        <div class="date-selection-grid">
                            <?php foreach ($available_dates as $d): ?>
                                <div class="date-checkbox-item">
                                    <input type="checkbox" id="date_<?php echo $d["id"]; ?>"
                                           name="available_dates[]" value="<?php echo $d["id"]; ?>">
                                    <label for="date_<?php echo $d["id"]; ?>">
                                        <?php echo date("M d, Y", strtotime($d["available_date"])); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($selected_rate === "hour"): ?>
                        <br>
                        <div class="time-row">
                            <div class="time-col">
                                <label for="start_time">Start Time:</label>
                                <input id="start_time" type="time" name="start_time" required>
                            </div>
                            <div class="time-col">
                                <label for="end_time">End Time:</label>
                                <input id="end_time" type="time" name="end_time" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button class="btn btn-confirm">✅ Confirm Booking</button>
                </form>

                <br>

                <form method="GET">
                    <input type="hidden" name="guide_id" value="<?php echo $guide_id; ?>">
                    <?php if ($search_query): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>
                    <button class="btn btn-secondary">← Change Rate Type</button>
                </form>
            </div>

        <?php endif; ?>

    </div>
</body>
</html>