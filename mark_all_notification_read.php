<?php
session_start();
require_once "includes/database.php";
require_once "includes/notification_helper.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// If guide is in tourist mode, treat as user
if ($user_role === 'guide' && !empty($_SESSION['is_tourist_mode'])) {
    $user_role = 'user';
}

markAllNotificationsAsRead($pdo, $user_id, $user_role);

// Redirect back
$referer = $_SERVER['HTTP_REFERER'] ?? 'user/dashboard.php';
header("Location: $referer");
exit;
?>