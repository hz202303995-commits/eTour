<?php

function createNotification($pdo, $user_id, $user_role, $type, $message) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, user_role, type, message, is_read, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        return $stmt->execute([$user_id, $user_role, $type, $message]);
    } catch (PDOException $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

function markNotificationAsRead($pdo, $notification_id, $user_id) {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$notification_id, $user_id]);
}

function markAllNotificationsAsRead($pdo, $user_id, $user_role) {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? AND user_role = ?
    ");
    return $stmt->execute([$user_id, $user_role]);
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount(PDO $pdo, int $userId): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('getNotifications')) {
    function getNotifications(PDO $pdo, int $userId, string $userRole = null, int $limit = 50): array {
        $sql = "SELECT id, type, message, is_read, created_at FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($userRole) {
            $sql .= " AND user_role = ?";
            $params[] = $userRole;
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('fetchNotifications')) {
    function fetchNotifications(PDO $pdo, int $userId, int $limit = 50): array {
        $sql = "SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>