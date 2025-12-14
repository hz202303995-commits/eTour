<?php
require_once "../includes/database.php";
require_once "../includes/notification_helper.php";
// Note: `guide_monthly_stats` table must be created via migrations/001_create_guide_monthly_stats.sql

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}


// Access Validation
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$isTourist = $_SESSION['is_tourist_mode'] ?? false;

// Toggle Tourist Mode
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_mode'])) {
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$posted_csrf)) {
        // invalid CSRF, redirect safely
        header("Location: dashboard.php");
        exit;
    }
    $_SESSION['is_tourist_mode'] = !$isTourist;
    // redirect to avoid form resubmission
    header("Location: dashboard.php");
    exit;
}

// Save a month snapshot to monthly history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_month'])) {
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)$posted_csrf)) {
        header("Location: dashboard.php");
        exit;
    }
    if (!($role === 'guide' && !$isTourist)) {
        header("Location: dashboard.php");
        exit;
    }

    $saveMonth = $_POST['month'] ?? '';
    // ensure $guide_id is determined for the POST handler
    if (empty($guide_id)) {
        $stmt = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $gRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($gRow) $guide_id = (int)$gRow['id'];
    }
    if (empty($guide_id)) {
        header("Location: dashboard.php?error=no_guide");
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $saveMonth)) {
        header("Location: dashboard.php?error=invalid_month");
        exit;
    }

    // compute and persist
    $ok = compute_and_save_month_stats($pdo, $guide_id, $saveMonth);
    if ($ok) {
        header("Location: dashboard.php?saved_month=".urlencode($saveMonth));
        exit;
    } else {
        header("Location: dashboard.php?error=save_failed");
        exit;
    }
}

// Prepare notifications only for guides (and not while in tourist mode)
$unreadCount = 0;
$notifications = [];
$showNotif = isset($_GET['show']) && $_GET['show'] === "notifications";

if ($role === 'guide' && !$isTourist) {
    // Only show pending booking requests (new_booking) in the bell
    $unreadCount = getUnreadNotificationCount($pdo, $user_id, 'guide', 'new_booking');
    $notifications = getNotifications($pdo, $user_id, 'guide', 'new_booking');
}

// KPI defaults
$pendingBookings = $confirmedBookings = $cancelledBookings = $declinedBookings = 0;
$totalRevenue = 0.0;
$recentBookings = [];
$monthlyStats = [];
$guide_id = null;
$monthlyHistory = [];
$historyDetail = [];

if ($role === 'guide' && !$isTourist) {
    // Get guide ID
    $stmt = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $guideRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($guideRow) {
        $guide_id = (int)$guideRow['id'];

        // KPI Counts (single queries each - kept as original logic)

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'pending'");
        $stmt->execute([$guide_id]);
        $pendingBookings = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'approved'");
        $stmt->execute([$guide_id]);
        $confirmedBookings = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'cancelled'");
        $stmt->execute([$guide_id]);
        $cancelledBookings = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'declined'");
        $stmt->execute([$guide_id]);
        $declinedBookings = (int)$stmt->fetchColumn();

        // Revenue Calculation - iterate confirmed bookings and compute revenue
        $stmt = $pdo->prepare("
            SELECT b.*, g.rate_day, g.rate_hour, 
                   DATEDIFF(b.end_date, b.start_date) + 1 AS days
            FROM bookings b
            JOIN guides g ON b.guide_id = g.id
            WHERE b.guide_id = ? AND b.status = 'approved'
        ");
        $stmt->execute([$guide_id]);
        $confirmedBookingsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($confirmedBookingsList as $booking) {
            $days = max(1, (int)$booking['days']);
            if (($booking['rate_type'] ?? '') === 'day') {
                $totalRevenue += (float)$booking['rate_day'] * $days;
            } else {
                // hourly booking: require start_time and end_time
                if (!empty($booking['start_time']) && !empty($booking['end_time'])) {
                    $start = new DateTime($booking['start_time']);
                    $end = new DateTime($booking['end_time']);
                    $intervalSeconds = $end->getTimestamp() - $start->getTimestamp();
                    if ($intervalSeconds > 0) {
                        $hours = $intervalSeconds / 3600.0;
                        $totalRevenue += (float)$booking['rate_hour'] * $hours * $days;
                    }
                }
            }
        }

        // Recent Bookings
        $stmt = $pdo->prepare("
            SELECT b.*, u.name AS tourist_name, u.email AS tourist_email
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            WHERE b.guide_id = ?
            ORDER BY b.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$guide_id]);
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Monthly Stats (last 6 months groups by YYYY-MM)
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(b.created_at, '%Y-%m') AS month,
                    SUM(CASE WHEN b.status='approved' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN b.status='pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN b.status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(
                        CASE WHEN b.status = 'approved' AND b.rate_type = 'day' THEN (DATEDIFF(b.end_date, b.start_date) + 1) * g.rate_day
                             WHEN b.status = 'approved' AND b.rate_type = 'hour' AND b.start_time IS NOT NULL AND b.end_time IS NOT NULL THEN (TIMESTAMPDIFF(SECOND, b.start_time, b.end_time) / 3600.0) * g.rate_hour * (DATEDIFF(b.end_date, b.start_date) + 1)
                             ELSE 0 END
                    ) AS revenue
                FROM bookings b
                JOIN guides g ON b.guide_id = g.id
                WHERE b.guide_id = ?
                GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
                ORDER BY month DESC
                LIMIT 6
            ");
        $stmt->execute([$guide_id]);
        $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Load persisted monthly history (last 12 saved months)
        $stmt = $pdo->prepare("SELECT month, confirmed, pending, cancelled, revenue FROM guide_monthly_stats WHERE guide_id = ? ORDER BY month DESC LIMIT 12");
        $stmt->execute([$guide_id]);
        $monthlyHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $savedMonths = array_column($monthlyHistory, 'month');

        // If a specific history month is requested, load detail
        if (!empty($_GET['history_month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['history_month'])) {
            $historyMonth = $_GET['history_month'];
            $stmt = $pdo->prepare("SELECT * FROM guide_monthly_stats WHERE guide_id = ? AND month = ? LIMIT 1");
            $stmt->execute([$guide_id, $historyMonth]);
            $historyDetail = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // (monthly history storage removed for now)

        // Helper: compute and persist monthly stats for a guide for a specific month (YYYY-MM)
                /* moved function definition to top for parsing safety */
    }
}

// Helper: Shorten currency for large figures to fit card (e.g., 1.2M)
function formatMoneyShort($amount) {
    $amount = (float)$amount;
    $clean = function($val) {
        // Remove unnecessary trailing zeros (e.g. "1.00" => "1")
        $asStr = number_format($val, 2, '.', '');
        $asStr = rtrim(rtrim($asStr, '0'), '.');
        return $asStr;
    };
    if ($amount >= 1000000000) return $clean($amount/1000000000) . 'B';
    if ($amount >= 1000000) return $clean($amount/1000000) . 'M';
    if ($amount >= 1000) return $clean($amount/1000) . 'K';
    return number_format($amount, 0); // integer display for amounts < 1000
}

// Helper to safely echo values
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Compute monthly aggregates and persist to guide_monthly_stats table
function compute_and_save_month_stats(PDO $pdo, int $guide_id, string $month): bool {
    // Validate month format
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) return false;

    // Compute aggregates for the given month
    $stmt = $pdo->prepare("SELECT
            SUM(CASE WHEN b.status='approved' THEN 1 ELSE 0 END) AS confirmed,
            SUM(CASE WHEN b.status='pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN b.status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(
                CASE WHEN b.status = 'approved' AND b.rate_type = 'day' THEN (DATEDIFF(b.end_date, b.start_date) + 1) * g.rate_day
                     WHEN b.status = 'approved' AND b.rate_type = 'hour' AND b.start_time IS NOT NULL AND b.end_time IS NOT NULL THEN (TIMESTAMPDIFF(SECOND, b.start_time, b.end_time) / 3600.0) * g.rate_hour * (DATEDIFF(b.end_date, b.start_date) + 1)
                     ELSE 0 END
            ) AS revenue
        FROM bookings b
        JOIN guides g ON b.guide_id = g.id
        WHERE b.guide_id = ? AND DATE_FORMAT(b.created_at, '%Y-%m') = ?");
    $stmt->execute([$guide_id, $month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        // no bookings -> zero values
        $confirmed = $pending = $cancelled = 0;
        $revenue = 0.0;
    } else {
        $confirmed = (int)($row['confirmed'] ?? 0);
        $pending = (int)($row['pending'] ?? 0);
        $cancelled = (int)($row['cancelled'] ?? 0);
        $revenue = (float)($row['revenue'] ?? 0);
    }

    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare("INSERT INTO guide_monthly_stats (guide_id, month, confirmed, pending, cancelled, revenue)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE confirmed = VALUES(confirmed), pending = VALUES(pending), cancelled = VALUES(cancelled), revenue = VALUES(revenue), updated_at = CURRENT_TIMESTAMP");
        $ins->execute([$guide_id, $month, $confirmed, $pending, $cancelled, number_format((float)$revenue, 2, '.', '')]);
        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Failed to save month stats: ' . $ex->getMessage());
        return false;
    }
}

// compute_and_save_month_stats: compute monthly aggregates and persist
/* monthly stat helper removed */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - eTOUR</title>
<link rel="stylesheet" href="../style.css">
</head>
<body>

<header class="navbar">
    <div class="logo">🌿 eTOUR | Dashboard</div>
    <div class="nav-links">
        <?php if ($role === 'guide' && !$isTourist): ?>
            <a href="dashboard.php?show=notifications" class="notification-bell" title="Notifications">🔔
                <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount > 99 ? '99+' : e($unreadCount); ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        
        <a href="dashboard.php" class="active-link">Dashboard</a>

        <?php if ($role === 'guide'): ?>
            <?php if (!$isTourist): ?>
                <a href="availability.php">Availability</a>
                <a href="manage_bookings.php">Manage Bookings</a>
                <a href="profile.php">Profile</a>
            <?php else: ?>
                <a href="../user/search.php">Search Guides</a>
                <a href="../user/bookings.php">My Bookings</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="search.php">Search Guides</a>
            <a href="bookings.php">My Bookings</a>
        <?php endif; ?>

        <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" name="toggle_mode">
                <?= $isTourist ? "Switch to Guide Mode" : "Switch to Tourist Mode"; ?>
            </button>
        </form>

        <a href="../auth/logout.php">Logout</a>
    </div>
</header>

    <div class="container">
    <h2>Welcome, <?= e($_SESSION['name'] ?? '') ?>!</h2>
        <?php if (!empty($_GET['saved_month'])): ?>
            <p style="color:green;">Saved monthly snapshot for <?= e($_GET['saved_month']) ?>.</p>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <p style="color:red;">Error: <?= e($_GET['error']) ?></p>
        <?php endif; ?>

        <?php if ($role === 'guide' && !$isTourist): ?>

            <?php if ($showNotif): ?>
                <div class="notification-dropdown">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <strong>Notifications</strong>
                        <a href="dashboard.php" style="text-decoration:none; color:#444;">✖ Close</a>
                    </div>

                    <?php if ($unreadCount > 0): ?>
                    <form method="POST" action="../mark_all_notification_read.php" style="margin:10px 0;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                        <input type="hidden" name="type" value="new_booking">
                        <button class="small-btn">Mark all booking requests read</button>
                    </form>
                    <?php endif; ?>

                    <div style="max-height:260px; overflow-y:auto; margin-top:10px;">
                        <?php if (empty($notifications)): ?>
                            <p style="text-align:center; color:#777;">📭 No notifications</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <a href="../notification_redirect.php?id=<?= (int)$notif['id'] ?>" class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>" style="display:block;padding:10px; border-bottom:1px solid #eee; text-decoration:none; color:inherit;">
                                    <strong><?= e(ucwords(str_replace('_',' ', $notif['type'] ?? 'Notification'))) ?></strong>
                                    <p><?= e($notif['message'] ?? '') ?></p>
                                    <small style="color:#666;"><?= e(date('M d, Y h:i A', strtotime($notif['created_at'] ?? 'now'))) ?></small>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h3>📊 Key Performance Indicators (KPIs)</h3>
            <div class="kpi-grid">
                <div class="kpi-card"><h3><span class="kpi-number"><?= e($pendingBookings) ?></span></h3><p>Pending Bookings</p></div>
                <div class="kpi-card"><h3><span class="kpi-number"><?= e($confirmedBookings) ?></span></h3><p>Confirmed Bookings</p></div>
                <div class="kpi-card"><h3><span class="kpi-number"><?= e($cancelledBookings) ?></span></h3><p>Cancelled Bookings</p></div>
                <div class="kpi-card"><h3><span class="kpi-number"><?= e($declinedBookings) ?></span></h3><p>Declined Bookings</p></div>
                <div class="kpi-card kpi-revenue" title="₱<?= number_format((float)$totalRevenue, 2) ?>"><h3><span class="kpi-number"><span class="currency">₱</span><?= e(formatMoneyShort($totalRevenue)) ?></span></h3><p>Total Revenue</p></div>
            </div>

            <div class="report-section">
                <h3>📈 Monthly Statistics Report</h3>
                <a href="print_report.php" class="print-btn" target="_blank">🖨️ Print Report</a>
                <a href="export_report.php" class="print-btn">📥 Export to CSV</a>

                <?php if (!empty($monthlyStats)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Month</th><th>Confirmed</th><th>Pending</th><th>Cancelled</th><th>Revenue</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monthlyStats as $stat): ?>
                                <tr>
                                    <td><?= e(date("F Y", strtotime($stat['month'] . "-01"))) ?></td>
                                    <td><?= e($stat['confirmed']) ?></td>
                                    <td><?= e($stat['pending']) ?></td>
                                    <td><?= e($stat['cancelled']) ?></td>
                                    <td>₱<?= number_format((float)($stat['revenue'] ?? 0), 2) ?></td>
                                    <td>
                                        <?php if (!empty($savedMonths) && in_array($stat['month'], $savedMonths)): ?>
                                            <span class="small-pill">Saved</span>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                <input type="hidden" name="month" value="<?= e($stat['month']) ?>">
                                                <button type="submit" name="save_month" class="small-btn">Save</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Totals</th>
                                <th><?= e(array_sum(array_column($monthlyStats, 'confirmed'))) ?></th>
                                <th><?= e(array_sum(array_column($monthlyStats, 'pending'))) ?></th>
                                <th><?= e(array_sum(array_column($monthlyStats, 'cancelled'))) ?></th>
                                <th>₱<?= number_format((float)$totalRevenue, 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="report-section">
                        <h3>📚 Monthly History</h3>
                        <?php if (!empty($monthlyHistory)): ?>
                            <table>
                                <thead>
                                    <tr><th>Month</th><th>Confirmed</th><th>Pending</th><th>Cancelled</th><th>Revenue</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($monthlyHistory as $h): ?>
                                    <tr>
                                        <td><?= e(date('F Y', strtotime($h['month'] . '-01'))) ?></td>
                                        <td><?= e($h['confirmed']) ?></td>
                                        <td><?= e($h['pending']) ?></td>
                                        <td><?= e($h['cancelled']) ?></td>
                                        <td>₱<?= number_format((float)$h['revenue'], 2) ?></td>
                                        <td>
                                            <a href="dashboard.php?history_month=<?= e($h['month']) ?>" class="btn btn-secondary">View</a>
                                            <a href="print_report.php?history_month=<?= e($h['month']) ?>" target="_blank" class="btn">Print</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No monthly history saved yet.</p>
                        <?php endif; ?>

                        <?php if (!empty($historyDetail)): ?>
                            <div class="kpi-card" style="margin-top:12px;">
                                <h3><?= e(date('F Y', strtotime($historyDetail['month'].'-01'))) ?></h3>
                                <p><strong>Confirmed:</strong> <?= e($historyDetail['confirmed']) ?> &nbsp; <strong>Pending:</strong> <?= e($historyDetail['pending']) ?> &nbsp; <strong>Cancelled:</strong> <?= e($historyDetail['cancelled']) ?></p>
                                <p><strong>Revenue:</strong> ₱<?= number_format((float)$historyDetail['revenue'], 2) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>No data available.</p>
                <?php endif; ?>
            </div>

            <div class="report-section">
                <h3>📋 Recent Bookings</h3>
                <?php if (!empty($recentBookings)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Email</th><th>Start</th><th>End</th><th>Type</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentBookings as $booking): ?>
                                <tr>
                                    <td><?= e($booking['id']) ?></td>
                                    <td><?= e($booking['tourist_name']) ?></td>
                                    <td><?= e($booking['tourist_email']) ?></td>
                                    <td><?= e($booking['start_date']) ?></td>
                                    <td><?= e($booking['end_date']) ?></td>
                                    <td><?= e(ucfirst($booking['rate_type'] ?? '')) ?></td>
                                    <td><?= e(ucfirst($booking['status'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No bookings yet.</p>
                <?php endif; ?>
            </div>

            <a href="manage_bookings.php" class="btn">📋 Manage All Bookings</a>

        <?php else: ?>

            <h3>Tourist Mode</h3>
            <p>You can now browse guides and make bookings.</p>
            <a href="../user/search.php" class="btn">🔍 Find Guides</a>

        <?php endif; ?>

    </div>
</body>
</html>