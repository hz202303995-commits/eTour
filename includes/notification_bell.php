<?php
require_once "database.php";
require_once "notification_helper.php";

// =========================================
// FETCH NOTIFICATIONS FOR THE NAVBAR BELL
// =========================================

// Wrapper functions to maintain backward compatibility with existing code
if (!function_exists('getNotifications')) {
    function getNotifications($pdo, $user_id, $role, $limit = 15)
    {
        $limit = (int)$limit; // Cast to integer
        $stmt = $pdo->prepare("
            SELECT * FROM notifications
            WHERE user_id = ? OR user_role = ?
            ORDER BY created_at DESC
            LIMIT " . $limit
        );
        $stmt->execute([$user_id, $role]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount($pdo, $user_id, $role)
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS unread
            FROM notifications
            WHERE (user_id = ? OR user_role = ?)
              AND is_read = 0
        ");
        $stmt->execute([$user_id, $role]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['unread'] : 0;
    }
}
?>