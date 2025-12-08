<?php
session_start();
require_once "../includes/database.php";
require_once "../includes/notification_helper.php"; // Add this line at the top

/* =====================================================
   ACCESS CHECK — only guides & not in tourist mode
   ===================================================== */
if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "guide" ||
    !empty($_SESSION["is_tourist_mode"])
) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];
$guideName = $_SESSION["name"] ?? "";

/* =====================================================
   GET GUIDE ID
   ===================================================== */
$stmtGuide = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
$stmtGuide->execute([$userId]);
$guide = $stmtGuide->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    exit("Error: Guide profile not found.");
}

$guideId = (int)$guide["id"];
$message = "";

/* =====================================================
   HANDLE BOOKING ACTIONS (POST)
   ===================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["booking_id"])) {

    $action = $_POST["action"];
    $bookingId = (int)$_POST["booking_id"];

    // Retrieve booking
    $stmt = $pdo->prepare("
        SELECT b.*, u.id AS tourist_id, u.name AS tourist_name
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ? AND b.guide_id = ?
        LIMIT 1
    ");
    $stmt->execute([$bookingId, $guideId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header("Location: manage_bookings.php");
        exit;
    }

    $touristId = (int)$booking["tourist_id"];
    $startDate = $booking["start_date"];

    /* ==========================================
       CONFIRM BOOKING
       ========================================== */
    if ($action === "confirm") {
        $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND guide_id = ?")
            ->execute([$bookingId, $guideId]);

        // CREATE NOTIFICATION using helper function
        $msg = "Your booking with {$guideName} for {$startDate} has been confirmed!";
        createNotification($pdo, $touristId, 'user', 'booking_confirmed', $msg);
    }

    /* ==========================================
       DECLINE BOOKING (RESTORE AVAILABILITY)
       ========================================== */
    elseif ($action === "decline") {

        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE bookings SET status = 'declined' WHERE id = ? AND guide_id = ?")
                ->execute([$bookingId, $guideId]);

            // Restore each day of the booking
            $start = new DateTime($booking['start_date']);
            $end   = (new DateTime($booking['end_date']))->modify('+1 day');
            $period = new DatePeriod($start, new DateInterval("P1D"), $end);

            $insert = $pdo->prepare("INSERT IGNORE INTO guide_availability (guide_id, available_date) VALUES (?, ?)");
            foreach ($period as $d) {
                $insert->execute([$guideId, $d->format("Y-m-d")]);
            }

            // CREATE NOTIFICATION using helper function
            $msg = "Your booking with {$guideName} for {$startDate} has been declined.";
            createNotification($pdo, $touristId, 'user', 'booking_declined', $msg);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }

    /* ==========================================
       CANCEL BOOKING
       ========================================== */
    elseif ($action === "cancel") {
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND guide_id = ?")
            ->execute([$bookingId, $guideId]);

        // CREATE NOTIFICATION using helper function
        $msg = "Your booking with {$guideName} for {$startDate} has been cancelled.";
        createNotification($pdo, $touristId, 'user', 'booking_cancelled', $msg);
    }

    header("Location: manage_bookings.php");
    exit;
}

/* =====================================================
   FETCH BOOKINGS FOR LISTING
   ===================================================== */
$stmt = $pdo->prepare("
    SELECT 
        b.id, b.start_date, b.end_date, b.status,
        b.rate_type, b.start_time, b.end_time,
        u.name AS tourist_name, u.email AS tourist_email
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.guide_id = ?
    ORDER BY b.start_date DESC
");
$stmt->execute([$guideId]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Bookings - eTOUR</title>
<link rel="stylesheet" href="../style.css">

<style>
    .container { max-width:1100px; margin:30px auto; padding:0 15px; }
    table { width:100%; border-collapse:collapse; margin-top:15px; }
    th,td { padding:10px; border:1px solid #ddd; }
    th { background:#f7f7f7; }
    .btn { padding:7px 12px; border-radius:5px; border:0; cursor:pointer; }
    .btn-confirm { background:#28a745; color:#fff; }
    .btn-decline { background:#dc3545; color:#fff; }
    .btn-cancel { background:#f0ad4e; color:#fff; }
    .status { padding:4px 8px; border-radius:4px; font-weight:600; }
    .status-pending { background:#fff3cd; color:#856404; }
    .status-confirmed { background:#d1ecf1; color:#0c5460; }
    .status-declined { background:#f8d7da; color:#721c24; }
    .status-cancelled { background:#f5c6cb; color:#721c24; }
    form.inline { display:inline; }
</style>
</head>

<body>
<header class="navbar">
    <div class="logo">🌿 eTOUR | Manage Bookings</div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="availability.php">Availability</a>
        <a href="manage_bookings.php" class="active-link">Manage Bookings</a>
        <a href="profile.php">Profile</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <h2>Manage Bookings</h2>

    <?php if (empty($bookings)): ?>
        <p>No bookings yet.</p>

    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tourist</th>
                    <th>Email</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Type</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th style="width:220px;">Action</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach ($bookings as $b): ?>
                <tr>
                    <td><?= (int)$b["id"] ?></td>
                    <td><?= htmlspecialchars($b["tourist_name"]) ?></td>
                    <td><?= htmlspecialchars($b["tourist_email"]) ?></td>
                    <td><?= htmlspecialchars($b["start_date"]) ?></td>
                    <td><?= htmlspecialchars($b["end_date"]) ?></td>
                    <td><?= ucfirst(htmlspecialchars($b["rate_type"])) ?></td>

                    <td>
                        <?php if ($b["rate_type"] === "hour" && $b["start_time"] && $b["end_time"]): ?>
                            <?= htmlspecialchars($b["start_time"]) ?> - <?= htmlspecialchars($b["end_time"]) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php
                            $status = $b["status"];
                            $class = "status-" . $status;
                        ?>
                        <span class="status <?= $class ?>"><?= ucfirst($status) ?></span>
                    </td>

                    <td>
                        <?php if ($status === "pending"): ?>

                            <form method="POST" class="inline" onsubmit="return confirm('Confirm this booking?');">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button class="btn btn-confirm">Confirm</button>
                            </form>

                            <form method="POST" class="inline" onsubmit="return confirm('Decline this booking?');">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                <input type="hidden" name="action" value="decline">
                                <button class="btn btn-decline">Decline</button>
                            </form>

                        <?php elseif ($status === "confirmed"): ?>

                            <form method="POST" class="inline" onsubmit="return confirm('Cancel this booking?');">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <button class="btn btn-cancel">Cancel</button>
                            </form>

                        <?php else: ?>
                            <em>No actions</em>
                        <?php endif; ?>
                    </td>

                </tr>
            <?php endforeach; ?>

            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>