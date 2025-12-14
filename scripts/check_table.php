<?php
require_once __DIR__ . '/../includes/database.php';
$stmt = $pdo->query("SHOW TABLES LIKE 'guide_monthly_stats'");
$exists = (bool)$stmt->fetch();
var_export($exists);
