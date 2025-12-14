<?php
session_start();
require_once "../includes/database.php";

// Access check
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'guide' ||
    !empty($_SESSION['is_tourist_mode'])
) {
    header("Location: ../auth/login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Fetch guide id
$stmtGuide = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
$stmtGuide->execute([$userId]);
$guide = $stmtGuide->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    exit("Guide profile not found.");
}

$guideId = (int)$guide['id'];

// Monthly stats table should be created via migration SQL: migrations/001_create_guide_monthly_stats.sql

// Helper: Shorten currency for large figures to fit card (e.g., 1.2M)
function formatMoneyShort($amount) {
    $amount = (float)$amount;
    $clean = function($val) {
        $asStr = number_format($val, 2, '.', '');
        $asStr = rtrim(rtrim($asStr, '0'), '.');
        return $asStr;
    };
    if ($amount >= 1000000000) return $clean($amount/1000000000) . 'B';
    if ($amount >= 1000000) return $clean($amount/1000000) . 'M';
    if ($amount >= 1000) return $clean($amount/1000) . 'K';
    return number_format($amount, 0);
}

// Fetch all bookings for the guide (include rate info)
$stmt = $pdo->prepare("\n    SELECT 
        b.id,
        u.name AS tourist_name,
        u.email AS tourist_email,
        b.start_date,
        b.end_date,
        b.rate_type,
        b.start_time,
        b.end_time,
        b.status,
        b.created_at,
        g.rate_day,
        g.rate_hour,
        DATEDIFF(b.end_date, b.start_date) + 1 AS days
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN guides g ON b.guide_id = g.id
    WHERE b.guide_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$guideId]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly stats
$stmt = $pdo->prepare("\n    SELECT 
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
    LIMIT 12
");
$stmt->execute([$guideId]);
$monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If printing a specific historical month, prefer stored monthly stats and bookings for that month
$historyMonth = $_GET['history_month'] ?? null;
if ($historyMonth) {
    $stmtHist = $pdo->prepare("SELECT * FROM guide_monthly_stats WHERE guide_id = ? AND month = ? LIMIT 1");
    $stmtHist->execute([$guideId, $historyMonth]);
    $hist = $stmtHist->fetch(PDO::FETCH_ASSOC);
    if ($hist) {
        $monthlyStats = [$hist];
        // get bookings created in that month
        $start = $historyMonth . '-01 00:00:00';
        $end = date('Y-m-t 23:59:59', strtotime($start));
        $stmtB = $pdo->prepare("SELECT b.*, u.name AS tourist_name, u.email AS tourist_email, g.rate_day, g.rate_hour, DATEDIFF(b.end_date, b.start_date) + 1 AS days FROM bookings b JOIN users u ON b.user_id = u.id JOIN guides g ON b.guide_id = g.id WHERE b.guide_id = ? AND b.created_at BETWEEN ? AND ? ORDER BY b.created_at DESC");
        $stmtB->execute([$guideId, $start, $end]);
        $bookings = $stmtB->fetchAll(PDO::FETCH_ASSOC);
    }
}

// KPIs: counts by status for print report
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'pending'");
$stmt->execute([$guideId]);
$pendingBookings = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'approved'");
$stmt->execute([$guideId]);
$confirmedBookings = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'cancelled'");
$stmt->execute([$guideId]);
$cancelledBookings = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE guide_id = ? AND status = 'declined'");
$stmt->execute([$guideId]);
$declinedBookings = (int)$stmt->fetchColumn();

// Revenue calculation (approved bookings)
$totalRevenue = 0.0;
foreach ($bookings as $b) {
    if (($b['status'] ?? '') !== 'approved') continue;
    $days = max(1, (int)($b['days'] ?? 1));
    if (($b['rate_type'] ?? '') === 'day') {
        $totalRevenue += (float)$b['rate_day'] * $days;
    } else {
        if (!empty($b['start_time']) && !empty($b['end_time'])) {
            $start = new DateTime($b['start_time']);
            $end = new DateTime($b['end_time']);
            $intervalSeconds = $end->getTimestamp() - $start->getTimestamp();
            if ($intervalSeconds > 0) {
                $hours = $intervalSeconds / 3600.0;
                $totalRevenue += (float)$b['rate_hour'] * $hours * $days;
            }
        }
    }
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printable Report - eTOUR</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        /* Minimal print-friendly styles */
        @media print {
            .no-print { display: none; }
            body { background: white; color: #000; }
        }
        .report-header { display:flex; justify-content:space-between; align-items:center; }
        .report-meta { text-align:right; }
        table { width:100%; border-collapse: collapse; }
        th, td { border:1px solid #ddd; padding:8px; }
        th { background:#f6f6f6; }
    </style>
</head>
<body>
    <header class="navbar no-print">
        <div class="logo">🌿 eTOUR | Printable Report</div>
        <div class="nav-links">
            <a href="dashboard.php">Back to Dashboard</a>
            <a href="export_report.php">Export CSV</a>
        </div>
    </header>

    <div class="container">
        <div class="report-header">
            <div>
                <h2>Guide Report</h2>
                <p>Guide ID: <?= e($guideId) ?></p>
            </div>
            <div class="report-meta">
                <p>Generated: <?= e(date('Y-m-d H:i:s')) ?></p>
                <p>Total Revenue: ₱<?= number_format((float)$totalRevenue, 2) ?></p>
            </div>
        </div>

        <h3>Key Performance Indicators (KPIs)</h3>
        <div class="kpi-grid" style="margin-bottom:12px;">
            <div class="kpi-card"><h3><span class="kpi-number"><?= e($pendingBookings) ?></span></h3><p>Pending</p></div>
            <div class="kpi-card"><h3><span class="kpi-number"><?= e($confirmedBookings) ?></span></h3><p>Confirmed</p></div>
            <div class="kpi-card"><h3><span class="kpi-number"><?= e($cancelledBookings) ?></span></h3><p>Cancelled</p></div>
            <div class="kpi-card"><h3><span class="kpi-number"><?= e($declinedBookings) ?></span></h3><p>Declined</p></div>
            <div class="kpi-card kpi-revenue" title="₱<?= number_format((float)$totalRevenue, 2) ?>"><h3><span class="kpi-number"><span class="currency">₱</span><?= e(formatMoneyShort($totalRevenue)) ?></span></h3><p>Total Revenue</p></div>
        </div>

        <h3>Monthly Statistics (last 12 months)</h3>
        <?php if (!empty($monthlyStats)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Month</th><th>Confirmed</th><th>Pending</th><th>Cancelled</th><th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlyStats as $stat): ?>
                        <tr>
                            <td><?= e(date('F Y', strtotime($stat['month'] . '-01'))) ?></td>
                            <td><?= e($stat['confirmed']) ?></td>
                            <td><?= e($stat['pending']) ?></td>
                            <td><?= e($stat['cancelled']) ?></td>
                            <td>₱<?= number_format((float)($stat['revenue'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th><?= e(array_sum(array_column($monthlyStats, 'confirmed'))) ?></th>
                        <th><?= e(array_sum(array_column($monthlyStats, 'pending'))) ?></th>
                        <th><?= e(array_sum(array_column($monthlyStats, 'cancelled'))) ?></th>
                        <th>₱<?= number_format((float)$totalRevenue, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <p>No monthly statistics available.</p>
        <?php endif; ?>

        <h3 style="margin-top:20px;">Bookings</h3>
        <?php if (!empty($bookings)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Name</th><th>Email</th><th>Start</th><th>End</th><th>Type</th><th>Status</th><th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><?= e($b['id']) ?></td>
                            <td><?= e($b['tourist_name']) ?></td>
                            <td><?= e($b['tourist_email']) ?></td>
                            <td><?= e($b['start_date']) ?></td>
                            <td><?= e($b['end_date']) ?></td>
                            <td><?= e(ucfirst($b['rate_type'] ?? '')) ?></td>
                            <td><?= e(ucfirst($b['status'] ?? '')) ?></td>
                            <td>
                                <?php
                                    $rev = 0;
                                    if (($b['status'] ?? '') === 'approved') {
                                        $d = max(1, (int)($b['days'] ?? 1));
                                        if (($b['rate_type'] ?? '') === 'day') {
                                            $rev = (float)$b['rate_day'] * $d;
                                        } else {
                                            if (!empty($b['start_time']) && !empty($b['end_time'])) {
                                                $st = new DateTime($b['start_time']);
                                                $en = new DateTime($b['end_time']);
                                                $s = $en->getTimestamp() - $st->getTimestamp();
                                                if ($s > 0) $rev = (float)$b['rate_hour'] * ($s / 3600.0) * $d;
                                            }
                                        }
                                    }
                                    echo '₱' . number_format($rev, 2);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No bookings to display.</p>
        <?php endif; ?>

        <p class="no-print" style="margin-top:20px;">Tip: use your browser's Print command to print this report.</p>
    </div>
</body>
</html>
