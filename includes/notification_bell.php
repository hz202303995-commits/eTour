<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'database.php';

if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? '';
$unread = 0;
$notifications = [];

// If guide is in tourist mode, treat as user
if ($userRole === 'guide' && !empty($_SESSION['is_tourist_mode'])) {
    $userRole = 'user';
}

if ($userId) {
    // Count unread notifications for both user_id and user_role
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM notifications WHERE (user_id = ? OR user_role = ?) AND is_read = 0");
    $stmt->execute([$userId, $userRole]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread = (int)($row['c'] ?? 0);
    
    // Check if user wants to see notifications
    $showNotifications = isset($_GET['show_notifications']);
    
    if ($showNotifications) {
        // Fetch recent notifications for both user_id and user_role
        $stmt = $pdo->prepare("SELECT id, message, created_at, is_read FROM notifications WHERE (user_id = ? OR user_role = ?) ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$userId, $userRole]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$currentPage = $_SERVER['PHP_SELF'];
$showNotifications = isset($_GET['show_notifications']);
?>
<div style="position:relative; display:inline-block;">
    <a href="<?= $currentPage ?>?show_notifications=1" style="text-decoration:none;">
        🔔 <?php if ($unread > 0): ?><span style="font-weight:700; margin-left:6px;"><?= e($unread) ?></span><?php endif; ?>
    </a>
    
    <?php if ($showNotifications): ?>
    <div style="position:absolute; right:0; background:white; border:1px solid #ccc; padding:10px; min-width:250px; box-shadow:0 2px 8px rgba(0,0,0,0.1); z-index:1000; margin-top:5px;">
        <div style="margin-bottom:10px;">
            <a href="mark_all_read.php" style="font-size:12px;">Mark all as read</a>
            <a href="<?= $currentPage ?>" style="font-size:12px; float:right;">Close</a>
        </div>
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notif): ?>
                <div style="padding:8px; border-bottom:1px solid #eee; <?= $notif['is_read'] ? '' : 'background:#f0f8ff;' ?>">
                    <div><?= e($notif['message']) ?></div>
                    <small style="color:#666;"><?= e($notif['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="margin:0;">No notifications</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>