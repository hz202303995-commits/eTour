<?php
session_start();
require_once "../includes/database.php";
require_once "../includes/notification_helper.php";

// Ensure CSRF token exists for forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if (
    !isset($_SESSION['user_id']) ||
    (
        $_SESSION['role'] !== 'user' &&
        !($_SESSION['role'] === 'guide' && !empty($_SESSION['is_tourist_mode']))
    )
) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";

// Confirmation page for cancellation (GET)
if (isset($_GET['cancel_id']) && !isset($_GET['confirm'])) {
    $cancel_id = (int)$_GET['cancel_id'];

    // Retrieve booking to confirm it belongs to current user
    $check = $pdo->prepare("SELECT b.id, b.guide_id, b.start_date, b.end_date, b.status, g.user_id AS guide_user_id, u.name AS guide_name, u.email AS guide_email FROM bookings b JOIN guides g ON b.guide_id = g.id JOIN users u ON g.user_id = u.id WHERE b.id = ? AND b.user_id = ? LIMIT 1");
    $check->execute([$cancel_id, $user_id]);
    $booking = $check->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header("Location: bookings.php");
        exit;
    }

    if ($booking['status'] !== 'pending') {
        header("Location: bookings.php");
        exit;
    }

    $dateStr = htmlspecialchars($booking['start_date']);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Confirm Cancellation</title>
        <link rel="stylesheet" href="../style.css">
    </head>
    <body>
        <div class="container">
            <h2>Confirm Cancellation</h2>
            <p>Are you sure you want to cancel the booking for <?= $dateStr ?> with <?= htmlspecialchars($booking['guide_name']) ?>?</p>
            <form method="POST" action="bookings.php">
                <input type="hidden" name="cancel_id" value="<?= (int)$cancel_id ?>">
                <input type="hidden" name="confirm" value="1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="btn btn-decline">Confirm Cancel</button>
                <a href="bookings.php" class="btn btn-cancel" style="margin-left:10px;">Back</a>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'], $_POST['confirm'])) {

    $cancel_id = (int)$_POST['cancel_id'];

    // Validate CSRF
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!(isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], (string)$posted_csrf))) {
        $message = 'Invalid request (CSRF).';
    } else {

    $check = $pdo->prepare("
        SELECT b.guide_id, b.start_date, b.end_date, b.status,
               g.user_id AS guide_user_id,
               u.name AS guide_name,
               u.email AS guide_email
        FROM bookings b
        JOIN guides g ON b.guide_id = g.id
        JOIN users u ON g.user_id = u.id
        WHERE b.id = ? AND b.user_id = ?
        LIMIT 1
    ");
    $check->execute([$cancel_id, $user_id]);
    $booking = $check->fetch(PDO::FETCH_ASSOC);

    if ($booking && $booking['status'] === 'pending') {

        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $upd->execute([$cancel_id]);

            $start = new DateTime($booking['start_date']);
            $end   = (new DateTime($booking['end_date']))->modify("+1 day");

            $period = new DatePeriod($start, new DateInterval("P1D"), $end);
            $checkAvail = $pdo->prepare("SELECT id FROM guide_availability WHERE guide_id = ? AND available_date = ? LIMIT 1");
            $ins = $pdo->prepare("INSERT INTO guide_availability (guide_id, available_date) VALUES (?, ?)");
            $checkBookingOnDate = $pdo->prepare("SELECT id FROM bookings WHERE guide_id = ? AND ? BETWEEN start_date AND end_date AND (status = 'pending' OR status = 'approved') LIMIT 1");

            foreach ($period as $dt) {
                    $dateStr = $dt->format("Y-m-d");
                    $checkAvail->execute([$booking['guide_id'], $dateStr]);
                    $checkBookingOnDate->execute([$booking['guide_id'], $dateStr]);
                    if (!$checkAvail->fetch() && !$checkBookingOnDate->fetch()) {
                        $ins->execute([$booking['guide_id'], $dateStr]);
                    }
            }

            $tourist_name = $_SESSION['name'];
            $notif_msg = "$tourist_name cancelled their booking for {$booking['start_date']}";

            // Use notification helper for consistency
            createNotification($pdo, $booking['guide_user_id'], 'guide', 'booking_cancelled', $notif_msg);

            if (function_exists("sendBookingNotificationEmail")) {
                $emailMessage =
                    "$tourist_name has cancelled their booking.\n\n" .
                    "Booking Details:\n" .
                    "Date: {$booking['start_date']} to {$booking['end_date']}\n\n" .
                    "The dates have been restored to your availability.";

                @sendBookingNotificationEmail(
                    $booking['guide_email'],
                    $booking['guide_name'],
                    $emailMessage,
                    "Booking Cancelled - eTOUR"
                );
            }

            $pdo->commit();
            $message = "Booking cancelled and dates restored.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error cancelling booking.";
        }

    } else {
        $message = "Unable to cancel this booking.";
    }
    }
}

$stmt = $pdo->prepare("
    SELECT b.*, g.id AS guide_table_id, u.name AS guide_name
    FROM bookings b
    JOIN guides g ON b.guide_id = g.id
    JOIN users u ON g.user_id = u.id
    WHERE b.user_id = ?
    ORDER BY b.id DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Bookings - eTOUR</title>
<link rel="stylesheet" href="../style.css">
</head>

<body>

<header class="navbar">
    <div class="logo">🌿 eTOUR | My Bookings</div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="search.php">Search Guides</a>
        <a href="bookings.php" class="active-link">My Bookings</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>

<div class="container">

    <h2>My Bookings</h2>

    <?php if ($message): ?>
        <p class="info" style="background: #d4edda; padding: 10px; border-radius: 5px;">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <?php if (!$bookings): ?>
        <p>No bookings found.</p>

    <?php else: ?>
        <div class="bookings-grid">
            <?php foreach ($bookings as $b): ?>
                <div class="card">
                <h3><?= htmlspecialchars($b['guide_name']) ?></h3>

                <p><strong>Start:</strong> <?= htmlspecialchars($b['start_date']) ?></p>
                <p><strong>End:</strong> <?= htmlspecialchars($b['end_date']) ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars(ucfirst($b['status'])) ?></p>

                <?php if ($b['status'] === 'pending'): ?>
                    <a href="?cancel_id=<?= (int)$b['id'] ?>" class="btn">Cancel</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div> <!-- .bookings-grid -->
    <?php endif; ?>

</div>

</body>
</html>
