<?php
session_start();
require_once "../includes/database.php";

/* =====================================================
   ACCESS VALIDATION
   Allowed:
   - normal users
   - guides IN tourist mode
   ===================================================== */
if (
    !isset($_SESSION["user_id"]) ||
    (
        $_SESSION["role"] !== "user" &&
        !($_SESSION["role"] === "guide" && !empty($_SESSION["is_tourist_mode"]))
    )
) {
    header("Location: ../auth/login.php");
    exit;
}

/* =====================================================
   GET GUIDE ID
   ===================================================== */
$guide_id = isset($_GET["guide_id"]) ? (int)$_GET["guide_id"] : 0;
if ($guide_id <= 0) {
    exit("Invalid guide ID.");
}

/* =====================================================
   PROCESS BOOKING REQUEST
   ===================================================== */
$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $rate_type  = $_POST["rate_type"] ?? "day";
    $start_date = $_POST["start_date"] ?? "";
    $end_date   = $_POST["end_date"] ?? "";

    // Basic validation
    if (!$start_date || !$end_date) {
        $errors[] = "Select start and end dates.";
    } elseif ($start_date > $end_date) {
        $errors[] = "Start date must be before end date.";
    }

    /* =====================================================
       Validate date availability (DAY RATE ONLY)
       ===================================================== */
    $dates = [];

    if (!$errors && $rate_type === "day") {
        $start = new DateTime($start_date);
        $end   = (new DateTime($end_date))->modify("+1 day");

        $period = new DatePeriod($start, new DateInterval("P1D"), $end);
        foreach ($period as $d) {
            $dates[] = $d->format("Y-m-d");
        }

        if (!empty($dates)) {
            $placeholders = implode(",", array_fill(0, count($dates), "?"));
            $params = array_merge([$guide_id], $dates);

            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM guide_availability 
                WHERE guide_id = ? 
                  AND available_date IN ($placeholders)
            ");
            $stmt->execute($params);
            $availableCount = $stmt->fetchColumn();

            if ($availableCount != count($dates)) {
                $errors[] = "One or more selected dates are not available.";
            }
        }
    }

    /* =====================================================
       INSERT BOOKING
       ===================================================== */
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert booking (pending)
            $ins = $pdo->prepare("
                INSERT INTO bookings
                    (user_id, guide_id, rate_type, start_date, end_date, status)
                VALUES
                    (?, ?, ?, ?, ?, 'pending')
            ");
            $ins->execute([
                $_SESSION["user_id"],
                $guide_id,
                $rate_type,
                $start_date,
                $end_date
            ]);

            // Remove each booked day from availability (DAY RATE ONLY)
            if ($rate_type === "day") {
                $del = $pdo->prepare("
                    DELETE FROM guide_availability
                    WHERE guide_id = ? AND available_date = ?
                ");

                foreach ($dates as $d) {
                    $del->execute([$guide_id, $d]);
                }
            }

            $pdo->commit();
            $success = "Booking successfully requested!";

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Booking failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Booking Result - eTOUR</title>
<link rel="stylesheet" href="../style.css">
</head>

<body>

<header class="navbar">
    <div class="logo">🌿 eTOUR | Booking Result</div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="bookings.php">My Bookings</a>
        <a href="search.php">Search Guides</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>

<div class="container">

    <h2>Booking Result</h2>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <p class="success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <a href="bookings.php" class="btn">Go to My Bookings</a>

</div>

</body>
</html>
