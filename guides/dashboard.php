<?php
require_once "../includes/database.php";
require_once "../includes/notification_helper.php";


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
    $_SESSION['is_tourist_mode'] = !$isTourist;
    // redirect to avoid form resubmission
    header("Location: dashboard.php");
    exit;
}

// Prepare notifications only for guides (and not while in tourist mode)
$unreadCount = 0;
$notifications = [];
$showNotif = isset($_GET['show']) && $_GET['show'] === "notifications";

if ($role === 'guide' && !$isTourist) {
    $unreadCount = getUnreadNotificationCount($pdo, $user_id, 'guide');
    $notifications = getNotifications($pdo, $user_id, 'guide');
}

// KPI defaults
$totalBookings = $pendingBookings = $confirmedBookings = $cancelledBookings = $declinedBookings = 0;
$totalRevenue = 0.0;
$recentBookings = [];
$monthlyStats = [];
$guide_id = null;

if ($role === 'guide' && !$isTourist) {
    // Get guide ID
    $stmt = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $guideRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($guideRow) {
        $guide_id = (int)$guideRow['id'];

        // KPI Counts (single queries each - kept as original logic)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ?");
        $stmt->execute([$guide_id]);
        $totalBookings = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'pending'");
        $stmt->execute([$guide_id]);
        $pendingBookings = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'confirmed'");
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
            WHERE b.guide_id = ? AND b.status = 'confirmed'
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
                DATE_FORMAT(created_at, '%Y-%m') AS month,
                COUNT(*) AS total,
                SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
            FROM bookings
            WHERE guide_id = ?
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 6
        ");
        $stmt->execute([$guide_id]);
        $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Helper to safely echo values
function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
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
            <button type="submit" name="toggle_mode">
                <?= $isTourist ? "Switch to Guide Mode" : "Switch to Tourist Mode"; ?>
            </button>
        </form>

        <a href="../auth/logout.php">Logout</a>
    </div>
</header>

<div class="container">
<h2>Welcome, <?= e($_SESSION['name'] ?? '') ?>!</h2>

<?php if ($role === 'guide' && !$isTourist): ?>

    <?php if ($showNotif): ?>
        <div class="notification-dropdown">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <strong>Notifications</strong>
                <a href="dashboard.php" style="text-decoration:none; color:#444;">✖ Close</a>
            </div>

            <?php if ($unreadCount > 0): ?>
                <form method="POST" action="../mark_all_notification_read.php" style="margin:10px 0;">
                    <button class="small-btn">Mark all as read</button>
                </form>
            <?php endif; ?>

            <div style="max-height:260px; overflow-y:auto; margin-top:10px;">
                <?php if (empty($notifications)): ?>
                    <p style="text-align:center; color:#777;">📭 No notifications</p>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>" style="padding:10px; border-bottom:1px solid #eee;">
                            <strong><?= e(ucwords(str_replace('_',' ', $notif['type'] ?? 'Notification'))) ?></strong>
                            <p><?= e($notif['message'] ?? '') ?></p>
                            <small style="color:#666;"><?= e(date('M d, Y h:i A', strtotime($notif['created_at'] ?? 'now'))) ?></small>

                            <?php if (empty($notif['is_read'])): ?>
                                <form method="POST" action="../mark_notification_read.php" style="margin-top:5px;">
                                    <input type="hidden" name="notification_id" value="<?= e($notif['id']) ?>">
                                    <button class="small-btn">Mark read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <h3>📊 Key Performance Indicators (KPIs)</h3>
    <div class="kpi-grid">
        <div class="kpi-card"><h3><?= e($totalBookings) ?></h3><p>Total Bookings</p></div>
        <div class="kpi-card"><h3><?= e($pendingBookings) ?></h3><p>Pending Bookings</p></div>
        <div class="kpi-card"><h3><?= e($confirmedBookings) ?></h3><p>Confirmed Bookings</p></div>
        <div class="kpi-card"><h3><?= e($cancelledBookings) ?></h3><p>Cancelled Bookings</p></div>
        <div class="kpi-card"><h3><?= e($declinedBookings) ?></h3><p>Declined Bookings</p></div>
        <div class="kpi-card"><h3>₱<?= number_format((float)$totalRevenue, 2) ?></h3><p>Total Revenue</p></div>
    </div>

    <div class="report-section">
        <h3>📈 Monthly Statistics Report</h3>
        <button class="print-btn" onclick="window.print()">🖨️ Print Report</button>
        <a href="export_report.php" class="print-btn">📥 Export to CSV</a>

        <?php if (!empty($monthlyStats)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Month</th><th>Total</th><th>Confirmed</th><th>Pending</th><th>Cancelled</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyStats as $stat): ?>
                        <tr>
                            <td><?= e(date("F Y", strtotime($stat['month'] . "-01"))) ?></td>
                            <td><?= e($stat['total']) ?></td>
                            <td><?= e($stat['confirmed']) ?></td>
                            <td><?= e($stat['pending']) ?></td>
                            <td><?= e($stat['cancelled']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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