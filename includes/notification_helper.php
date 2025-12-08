<?php
// Notification Helper Functions

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
        WHERE (user_id = ? OR user_role = ?)
    ");
    return $stmt->execute([$user_id, $user_role]);
}
?>