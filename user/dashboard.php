<?php
session_start();
require_once "../includes/database.php";

if (isset($_POST['return_to_guide'])) {
    $_SESSION['is_tourist_mode'] = false;
    header("Location: ../guides/dashboard.php");
    exit;
}

if (
    !isset($_SESSION['user_id']) ||
    (
        $_SESSION['role'] !== 'user' &&
        !($_SESSION['role'] === 'guide' && !empty($_SESSION['is_tourist_mode']))
    )
) {
    header("Location: ../auth/login.php");
    exit;
}

$stmt = $pdo->query("
    SELECT 
        g.id AS guide_id,
        u.name AS guide_name,
        g.location,
        g.languages,
        g.rate_day,
        g.rate_hour,
        g.accommodation
    FROM guides g
    JOIN users u ON g.user_id = u.id
    ORDER BY g.id DESC
    LIMIT 6
");
$featured_guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Dashboard - eTOUR</title>
<link rel="stylesheet" href="../style.css">
</head>

<body>

<header class="navbar">
    <div class="logo">🌿 eTOUR | User Dashboard</div>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="search.php">Search Guides</a>
        <a href="bookings.php">My Bookings</a>
        
        <?php if ($_SESSION['role'] === 'guide' && !empty($_SESSION['is_tourist_mode'])): ?>
            <form method="POST" style="display:inline; margin: 0;">
                <button type="submit" name="return_to_guide" style="margin: 0;">Switch to Guide Mode</button>
            </form>
        <?php endif; ?>
        
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>


<div class="container">

    <h2>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'User'); ?>!</h2>

    <?php if ($_SESSION['role'] === 'guide' && !empty($_SESSION['is_tourist_mode'])): ?>
        <p style="
            background: rgba(255, 243, 205, 0.9);
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid #f0ad4e;
            margin: 1rem 0;
        ">
            <strong>🔄 Tourist Mode Active</strong><br>
            You are browsing as a tourist. Switch back to guide mode to manage your bookings and availability.
        </p>
    <?php endif; ?>

    <p>Browse and book available tour guides below.</p>

    <a href="search.php" class="btn">🔍 Find Guides</a>

    <br><br>

    <h3>🌲 Featured Guides</h3>

    <?php if (!$featured_guides): ?>
        <p>No guides available at the moment. Please check back later.</p>

    <?php else: ?>
        <div class="guide-grid">
            <?php foreach ($featured_guides as $guide): ?>
                <div class="guide-card">

                    <h4><?= htmlspecialchars($guide['guide_name']); ?></h4>

                    <p><strong>📍 Location:</strong> 
                        <?= htmlspecialchars($guide['location']); ?></p>

                    <p><strong>🗣 Languages:</strong> 
                        <?= htmlspecialchars($guide['languages']); ?></p>

                    <p><strong>💰 Day Rate:</strong> 
                        ₱<?= number_format($guide['rate_day'], 2); ?></p>

                    <p><strong>💰 Hourly Rate:</strong> 
                        ₱<?= number_format($guide['rate_hour'], 2); ?></p>

                    <p><strong>🏨 Accommodation:</strong><br>
                        <?= nl2br(htmlspecialchars($guide['accommodation'])); ?>
                    </p>

                    <form method="GET" action="book_guide.php">
                        <input type="hidden" name="guide_id" 
                               value="<?= (int)$guide['guide_id']; ?>">
                        <button type="submit" class="btn">Book / View</button>
                    </form>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>