<?php
session_start();
require_once "../includes/database.php";

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

if (isset($_GET['cancel_id'])) {

    $cancel_id = (int)$_GET['cancel_id'];

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
            $ins = $pdo->prepare("
                INSERT IGNORE INTO guide_availability (guide_id, available_date)
                VALUES (?, ?)
            ");

            foreach ($period as $dt) {
                $ins->execute([$booking['guide_id'], $dt->format("Y-m-d")]);
            }

            $tourist_name = $_SESSION['name'];
            $notif_msg = "$tourist_name cancelled their booking for {$booking['start_date']}";

            $notif = $pdo->prepare("
                INSERT INTO notifications
                    (user_id, user_role, type, message, related_id, is_read, created_at)
                VALUES (?, 'guide', 'booking_cancelled', ?, ?, 0, NOW())
            ");
            $notif->execute([$booking['guide_user_id'], $notif_msg, $cancel_id]);

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
        <a href="bookings.php">My Bookings</a>
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
        <?php foreach ($bookings as $b): ?>
            <div class="card" style="margin-bottom: 15px;">
                <h3><?= htmlspecialchars($b['guide_name']) ?></h3>

                <p><strong>Start:</strong> <?= htmlspecialchars($b['start_date']) ?></p>
                <p><strong>End:</strong> <?= htmlspecialchars($b['end_date']) ?></p>
                <p><strong>Status:</strong> <?= htmlspecialchars(ucfirst($b['status'])) ?></p>

                <?php if ($b['status'] === 'pending'): ?>
                    <a href="?cancel_id=<?= (int)$b['id'] ?>" 
                       class="btn"
                       onclick="return confirm('Cancel this booking?')">
                       Cancel
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>
