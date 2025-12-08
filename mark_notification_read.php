<?php
session_start();
require_once 'includes/database.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $ref = $_SERVER['HTTP_REFERER'] ?? 'user/dashboard.php';
    header("Location: $ref");
    exit;
}

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    $ref = $_SERVER['HTTP_REFERER'] ?? 'user/dashboard.php';
    header("Location: $ref");
    exit;
}

$notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if ($notificationId > 0) {

    $userId   = $_SESSION['user_id'];
    $userRole = $_SESSION['role'];
    
    // If guide is in tourist mode, treat as user
    if ($userRole === 'guide' && !empty($_SESSION['is_tourist_mode'])) {
        $userRole = 'user';
    }

    // Check if the notification is intended for this user or their role
    $stmtCheck = $pdo->prepare("
        SELECT id 
        FROM notifications 
        WHERE id = ? 
          AND (user_role = ? OR user_id = ?)
    ");
    $stmtCheck->execute([$notificationId, $userRole, $userId]);

    if ($stmtCheck->fetch()) {
        // Mark as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$notificationId]);
    }
}

// Get the redirect destination from POST or use referer
$redirectTo = $_POST['redirect_to'] ?? '';

if ($redirectTo) {
    // If specific redirect is provided, use it
    $basePath = ($userRole === 'guide' && empty($_SESSION['is_tourist_mode'])) ? 'guides/' : 'user/';
    header("Location: " . $basePath . $redirectTo);
} else {
    // Otherwise use referer but remove the ?show=notifications parameter
    $ref = $_SERVER['HTTP_REFERER'] ?? 'user/dashboard.php';
    $ref = preg_replace('/[?&]show=notifications/', '', $ref);
    header("Location: $ref");
}
exit;