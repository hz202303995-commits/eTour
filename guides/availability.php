<?php
session_start();
require_once "../includes/database.php";

// Access control: only guides
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guide') {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

// Fetch or auto-create guide profile
$stmt = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
$stmt->execute([$userId]);
$guide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    $pdo->prepare("
        INSERT INTO guides (user_id, contact, location, languages, accommodation, rate_day, rate_hour)     
        VALUES (?, '', '', '', '', 0, 0)
    ")->execute([$userId]);

    $stmt->execute([$userId]);
    $guide = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$guide || !isset($guide['id'])) {
    die("Guide profile missing.");
}

$guideId = (int)$guide['id'];
$success = '';
$error = '';

function is_valid_date($d) {
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// Handle POST actions (add/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string)$_SESSION['csrf_token'], (string)$posted_csrf)) {
        $error = "Invalid request (CSRF).";
    } else {
        if (isset($_POST['delete_id'])) {
            $deleteId = (int)$_POST['delete_id'];
            $stmt = $pdo->prepare("DELETE FROM guide_availability WHERE id = ? AND guide_id = ?");
            $stmt->execute([$deleteId, $guideId]);
            header("Location: availability.php");
            exit;
        }

        if (isset($_POST['add_date'])) {
            $addDate = trim($_POST['add_date']);

            if (!is_valid_date($addDate)) {
                $error = "Invalid date format.";
            } else {
                $today = date('Y-m-d');

                if ($addDate < $today) {
                    $error = "Cannot add past dates.";
                } else {
                    $checkBooking = $pdo->prepare("
                        SELECT id FROM bookings
                        WHERE guide_id = ?
                          AND ? BETWEEN start_date AND end_date
                          AND (status = 'pending' OR status = 'confirmed')
                        LIMIT 1
                    ");
                    $checkBooking->execute([$guideId, $addDate]);

                    if ($checkBooking->fetch()) {
                        $error = "This date already has a pending or confirmed booking.";
                    } else {
                        $stmt = $pdo->prepare("
                            SELECT id FROM guide_availability
                            WHERE guide_id = ? AND available_date = ? LIMIT 1
                        ");
                        $stmt->execute([$guideId, $addDate]);

                        if ($stmt->fetch()) {
                            $error = "This date is already added as available.";
                        } else {
                            $ins = $pdo->prepare("
                                INSERT INTO guide_availability (guide_id, available_date)
                                VALUES (?, ?)
                            ");
                            $ins->execute([$guideId, $addDate]);

                            $formattedDate = date("F j, Y", strtotime($addDate));
                            $success = "Availability successfully added for $formattedDate";

                            $year  = (int)date("Y", strtotime($addDate));
                            $month = (int)date("n", strtotime($addDate));

                            header("Location: availability.php?year=$year&month=$month&success=" . urlencode($success));
                            exit;
                        }
                    }
                }
            }
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Determine calendar month/year
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

if ($month < 1)  $month = 1;
if ($month > 12) $month = 12;

// Fetch availability
$stmt = $pdo->prepare("SELECT id, available_date FROM guide_availability WHERE guide_id = ?");
$stmt->execute([$guideId]);
$availRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$available_map = [];
foreach ($availRows as $r) {
    $available_map[$r['available_date']] = $r['id'];
}

// Fetch confirmed bookings
$stmt = $pdo->prepare("SELECT start_date, end_date FROM bookings WHERE guide_id = ? AND status = 'confirmed'");
$stmt->execute([$guideId]);
$confirmedBookingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$confirmed_dates = [];
foreach ($confirmedBookingRows as $b) {
    $start = new DateTime($b['start_date']);
    $end   = new DateTime($b['end_date']);
    $endPlus = clone $end;
    $endPlus->modify('+1 day');

    foreach (new DatePeriod($start, new DateInterval('P1D'), $endPlus) as $d) {
        $confirmed_dates[$d->format('Y-m-d')] = true;
    }
}

// Fetch pending bookings
$stmt = $pdo->prepare("SELECT start_date, end_date FROM bookings WHERE guide_id = ? AND status = 'pending'");
$stmt->execute([$guideId]);
$pendingBookingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pending_dates = [];
foreach ($pendingBookingRows as $b) {
    $start = new DateTime($b['start_date']);
    $end   = new DateTime($b['end_date']);
    $endPlus = clone $end;
    $endPlus->modify('+1 day');

    foreach (new DatePeriod($start, new DateInterval('P1D'), $endPlus) as $d) {
        $pending_dates[$d->format('Y-m-d')] = true;
    }
}

// Calendar calculations
$first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = (int)date('t', $first_day_of_month);
$day_of_week = (int)date('w', $first_day_of_month);

$today = date('Y-m-d');

$prevMonth = $month - 1;
$prevYear  = $year;
if ($prevMonth < 1) { 
    $prevMonth = 12; 
    $prevYear--; 
}

$nextMonth = $month + 1;
$nextYear  = $year;
if ($nextMonth > 12) { 
    $nextMonth = 1; 
    $nextYear++; 
}

function url_with_params($params = []) {
    return basename($_SERVER['PHP_SELF']) . '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide Availability - eTOUR</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

<header class="navbar">
    <div class="logo">eTOUR | Guide Availability</div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="availability.php" class="active-link">Availability</a>
        <a href="manage_bookings.php">Manage Bookings</a>
        <a href="profile.php">Profile</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <h2><?php echo htmlspecialchars(date("F Y", strtotime("$year-$month-01"))); ?></h2>

    <?php if ($success): ?>
        <div class="notice"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div>
        <a href="<?php echo url_with_params(['year' => $prevYear, 'month' => $prevMonth]); ?>">&laquo; Prev</a>
        &nbsp;&nbsp;
        <a href="<?php echo url_with_params(['year' => $nextYear, 'month' => $nextMonth]); ?>">Next &raquo;</a>
    </div>

    <table class="calendar" border="1" cellpadding="6">
        <thead>
            <tr>
                <th>Sun</th>
                <th>Mon</th>
                <th>Tue</th>
                <th>Wed</th>
                <th>Thu</th>
                <th>Fri</th>
                <th>Sat</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $week_day = 0;
            echo "<tr>";

            for ($i = 0; $i < $day_of_week; $i++) {
                echo "<td></td>";
                $week_day++;
            }

            for ($day = 1; $day <= $days_in_month; $day++) {
                $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
                $content = "<strong>$day</strong><br>";

                if (isset($confirmed_dates[$dateStr])) {
                    echo "<td class='scheduled'>$content Confirmed</td>";
                } elseif (isset($pending_dates[$dateStr])) {
                    echo "<td class='booked-pending'>$content Pending</td>";
                } elseif (isset($available_map[$dateStr])) {
                    $id = $available_map[$dateStr];
                    echo "<td class='available'>$content Available
                        <form method='POST' class='delete-form'>
                            <input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf, ENT_QUOTES) . "'>
                            <input type='hidden' name='delete_id' value='" . htmlspecialchars($id, ENT_QUOTES) . "'>
                            <button type='submit' onclick=\"return confirm('Delete this availability?')\">Delete</button>
                        </form>
                    </td>";
                } elseif ($dateStr < $today) {
                    echo "<td class='past'>$content</td>";
                } else {
                    echo "<td class='clickable'>
                        <form method='POST' class='add-form'>
                            <input type='hidden' name='csrf_token' value='" . htmlspecialchars($csrf, ENT_QUOTES) . "'>
                            <input type='hidden' name='add_date' value='" . htmlspecialchars($dateStr, ENT_QUOTES) . "'>
                            <button type='submit' class='add-link'>$content Add</button>
                        </form>
                    </td>";
                }

                $week_day++;

                if ($week_day == 7) {
                    echo "</tr><tr>";
                    $week_day = 0;
                }
            }

            while ($week_day > 0 && $week_day < 7) {
                echo "<td></td>";
                $week_day++;
            }
            echo "</tr>";
            ?>
        </tbody>
    </table>

</div>

</body>
</html>