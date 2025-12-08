<?php
session_start();
require_once "includes/database.php";

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// If guide is in tourist mode → show tourist notifications
$display_role = ($user_role === 'guide' && ($_SESSION['is_tourist_mode'] ?? false))
    ? 'user'
    : $user_role;

/* -----------------------------------------------------------
   HANDLE NOTIFICATION ACTIONS (mark, delete)
----------------------------------------------------------- */

function redirect_back() {
    header("Location: notifications.php");
    exit;
}

// Mark ONE as read
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];

    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND (user_id = ? OR user_role = ?)
    ");
    $stmt->execute([$notif_id, $user_id, $display_role]);

    redirect_back();
}

// Mark ALL as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE (user_id = ? OR user_role = ?)
    ");
    $stmt->execute([$user_id, $display_role]);

    redirect_back();
}

// Delete notification
if (isset($_GET['delete'])) {
    $notif_id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE id = ? AND (user_id = ? OR user_role = ?)
    ");
    $stmt->execute([$notif_id, $user_id, $display_role]);

    redirect_back();
}

/* -----------------------------------------------------------
   FETCH NOTIFICATIONS
----------------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT * 
    FROM notifications 
    WHERE (user_id = ? OR user_role = ?)
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id, $display_role]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE (user_id = ? OR user_role = ?) AND is_read = 0
");
$stmt->execute([$user_id, $display_role]);
$unread_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications - eTOUR</title>
<link rel="stylesheet" href="style.css">
<style>
    .notif-item {
        padding: 15px;
        margin: 10px 0;
        border: 1px solid #ddd;
        border-radius: 5px;
        background: #fff;
    }
    .notif-item.unread {
        background: #e8f5f5;
        border-left: 4px solid #2b7a78;
    }
    .notif-actions {
        margin-top: 10px;
    }
    .notif-actions a {
        margin-right: 10px;
        text-decoration: none;
        padding: 5px 10px;
        background: #2b7a78;
        color: white;
        border-radius: 3px;
        font-size: 12px;
    }
    .delete-btn {
        background: #f44336 !important;
    }
</style>
</head>
<body>

<header class="navbar">
    <div class="logo">🌿 eTOUR | Notifications</div>
    <div class="nav-links">

        <?php if ($display_role === 'guide'): ?>
            <a href="notifications.php">Notifications (<?= $unread_count ?>)</a>
            <a href="guides/dashboard.php">Dashboard</a>
            <a href="guides/availability.php">Availability</a>
            <a href="guides/manage_bookings.php">Manage Bookings</a>
            <a href="guides/profile.php">Profile</a>
        <?php else: ?>
            <a href="user/dashboard.php">Dashboard</a>
            <a href="user/search.php">Search Guides</a>
            <a href="user/bookings.php">My Bookings</a>
        <?php endif; ?>
        <a href="auth/logout.php">Logout</a>
    </div>
</header>

<div class="container">
    <h2>🔔 Notifications</h2>

    <?php if ($unread_count > 0): ?>
        <p>You have <strong><?= $unread_count ?></strong> unread notification(s).</p>
        <a href="?mark_all_read=1" 
           style="padding: 10px 15px; background: #2b7a78; color: white; text-decoration: none; border-radius: 5px;">
            Mark All as Read
        </a>
        <br><br>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
        <p>No notifications yet.</p>
    <?php else: ?>

        <?php foreach ($notifications as $notif): ?>
            <div class="notif-item <?= $notif['is_read'] ? '' : 'unread' ?>">

                <strong><?= ucwords(str_replace('_', ' ', $notif['type'])) ?></strong>
                <p><?= htmlspecialchars($notif['message']) ?></p>

                <small style="color: #999;">
                    <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                </small>

                <div class="notif-actions">
                    <?php if (!$notif['is_read']): ?>
                        <a href="?mark_read=<?= $notif['id'] ?>">Mark as Read</a>
                    <?php endif; ?>

                    <a href="?delete=<?= $notif['id'] ?>" 
                       class="delete-btn"
                       onclick="return confirm('Delete this notification?')">
                       Delete
                    </a>
                </div>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

</body>
</html>
