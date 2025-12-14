<?php
session_start();
require_once 'includes/database.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

// If guide is in tourist mode, treat as user
if ($userRole === 'guide' && !empty($_SESSION['is_tourist_mode'])) {
    $userRole = 'user';
}

if ($notificationId > 0) {
    // Verify notification belongs to this user (by user_id or user_role)
    $stmt = $pdo->prepare("SELECT id FROM notifications WHERE id = ? AND (user_id = ? OR user_role = ?)");
    $stmt->execute([$notificationId, $userId, $userRole]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        // Mark as read
        $stmtUpdate = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmtUpdate->execute([$notificationId]);
    }
}

// Redirect to the appropriate bookings page depending on role
if ($userRole === 'guide') {
    header('Location: guides/manage_bookings.php');
} else {
    header('Location: user/bookings.php');
}
exit;
