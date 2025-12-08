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

// Fetch guide ID
$stmtGuide = $pdo->prepare("SELECT id FROM guides WHERE user_id = ?");
$stmtGuide->execute([$userId]);
$guide = $stmtGuide->fetch(PDO::FETCH_ASSOC);

if (!$guide) {
    exit("Guide profile not found.");
}

$guideId = (int)$guide['id'];

// Fetch all bookings for export
$stmt = $pdo->prepare("
    SELECT 
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

// ---------------------------------------
// CSV DOWNLOAD HEADERS
// ---------------------------------------
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=bookings_report_" . date("Y-m-d") . ".csv");

// Output stream
$output = fopen("php://output", "w");

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header row
fputcsv($output, [
    "Booking ID",
    "Tourist Name",
    "Tourist Email",
    "Start Date",
    "End Date",
    "Rate Type",
    "Start Time",
    "End Time",
    "Days",
    "Status",
    "Revenue",
    "Booking Date"
]);

// ---------------------------------------
// Write Data Rows
// ---------------------------------------
foreach ($bookings as $b) {
    $revenue = 0;

    if ($b["status"] === "confirmed") {
        if ($b["rate_type"] === "day") {
            $revenue = $b["rate_day"] * $b["days"];
        } else {
            if (!empty($b["start_time"]) && !empty($b["end_time"])) {
                $start = new DateTime($b["start_time"]);
                $end   = new DateTime($b["end_time"]);
                $hours = max(0, ($end->getTimestamp() - $start->getTimestamp()) / 3600);
                $revenue = $b["rate_hour"] * $hours * $b["days"];
            }
        }
    }

    fputcsv($output, [
        $b["id"],
        $b["tourist_name"],
        $b["tourist_email"],
        $b["start_date"],
        $b["end_date"],
        ucfirst($b["rate_type"]),
        $b["start_time"] ?: "N/A",
        $b["end_time"] ?: "N/A",
        $b["days"],
        ucfirst($b["status"]),
        number_format($revenue, 2),
        date("Y-m-d H:i:s", strtotime($b["created_at"]))
    ]);
}

fclose($output);
exit;
?>
