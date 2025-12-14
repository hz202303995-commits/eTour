<?php
session_start();
require_once "../includes/database.php";
require_once "../includes/notification_helper.php"; // Add this line at the top

// Ensure a CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

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

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

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

// GET confirmation pages for action links
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["action"], $_GET["booking_id"]) && !isset($_GET['confirm'])) {
    $action = $_GET['action'];
    $bookingId = (int)$_GET['booking_id'];

    // Retrieve booking to validate ownership
    $stmt = $pdo->prepare("SELECT b.*, u.id AS tourist_id, u.name AS tourist_name FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.guide_id = ? LIMIT 1");
    $stmt->execute([$bookingId, $guideId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header("Location: manage_bookings.php");
        exit;
    }

    $actionLabel = ucfirst(htmlspecialchars($action));
    $startDate = htmlspecialchars($booking['start_date']);
    $touristName = htmlspecialchars($booking['tourist_name']);

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Confirm "<?= htmlspecialchars($actionLabel) ?>" - eTOUR</title>
        <link rel="stylesheet" href="../style.css">
    </head>
    <body>
        <div class="container">
            <?php if (!empty($_GET['success'])): ?>
                <div class="success-notification" style="margin-bottom:10px;">✅ Booking declined and availability restored.</div>
            <?php endif; ?>
            <h2>Confirm <?= htmlspecialchars($actionLabel) ?></h2>
            <p>Are you sure you want to <?= htmlspecialchars(strtolower($actionLabel)) ?> the booking by <?= $touristName ?> for <?= $startDate ?>?</p>
            <form method="POST" action="manage_bookings.php">
                <input type="hidden" name="booking_id" value="<?= (int)$bookingId ?>">
                <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="btn btn-confirm"><?= $actionLabel ?></button>
                <a href="manage_bookings.php" class="btn btn-cancel" style="margin-left:10px;">Cancel</a>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// POST handler for actions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["booking_id"])) {
    // CSRF validation
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!(isset($_SESSION['csrf_token']) && hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$posted_csrf))) {
        // invalid csrf - redirect back
        header('Location: manage_bookings.php');
        exit;
    }

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
        $pdo->prepare("UPDATE bookings SET status = 'approved' WHERE id = ? AND guide_id = ?")
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

            $checkAvail = $pdo->prepare("SELECT id FROM guide_availability WHERE guide_id = ? AND available_date = ? LIMIT 1");
            $insAvail = $pdo->prepare("INSERT INTO guide_availability (guide_id, available_date) VALUES (?, ?)");
            $checkBookingOnDate = $pdo->prepare("SELECT id FROM bookings WHERE guide_id = ? AND ? BETWEEN start_date AND end_date AND (status = 'pending' OR status = 'approved') LIMIT 1");
            foreach ($period as $d) {
                $date_str = $d->format("Y-m-d");
                $checkAvail->execute([$guideId, $date_str]);
                // If a pending/approved booking exists on the date, skip restoring availability
                $checkBookingOnDate->execute([$guideId, $date_str]);
                if (!$checkAvail->fetch() && !$checkBookingOnDate->fetch()) {
                    $insAvail->execute([$guideId, $date_str]);
                }
            }

            // CREATE NOTIFICATION using helper function
            $msg = "Your booking with {$guideName} for {$startDate} has been declined.";
            createNotification($pdo, $touristId, 'user', 'booking_declined', $msg);

            $pdo->commit();
            // Redirect with success flag so guide can see availability restored
            header("Location: manage_bookings.php?success=1");
            exit;
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

    // Generic redirect if no earlier redirect
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

                            <a href="manage_bookings.php?action=confirm&booking_id=<?= (int)$b['id'] ?>" class="btn btn-confirm">Confirm</a>
                            <a href="manage_bookings.php?action=decline&booking_id=<?= (int)$b['id'] ?>" class="btn btn-decline">Decline</a>

                        <?php elseif ($status === "approved"): ?>

                            <a href="manage_bookings.php?action=cancel&booking_id=<?= (int)$b['id'] ?>" class="btn btn-cancel">Cancel</a>

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