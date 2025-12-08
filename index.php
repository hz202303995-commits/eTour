<?php
require_once "includes/database.php";

$stmt = $pdo->query("
    SELECT 
        g.id AS guide_id,
        u.name AS guide_name,
        g.location,
        g.languages,
        g.accommodation,
        g.rate_day,
        g.rate_hour
    FROM guides g
    JOIN users u ON g.user_id = u.id
    ORDER BY g.id DESC
    LIMIT 6
");
$featured = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>eTOUR | Explore Nature with Local Guides</title>
    <link rel="stylesheet" href="style.css">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: rgba(21, 97, 36, 0.9);
        }

        .hero {
            height: 80vh;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: rgba(18, 88, 47, 0.9);
            background: url('image/tour.jpg') no-repeat center center / cover;
        }

        .hero-content {
            background: rgba(235, 217, 217, 0.8);
            padding: 2rem;
            border-radius: 10px;
        }

        .container {
            background: rgba(21, 97, 36, 0.9);
            padding: 2rem;
            border-radius: 10px;
            margin-top: 2rem;
        }

        .footer {
            text-align: center;
            padding: 1rem;
            background-color: rgba(21, 97, 36, 0.9);
            color: white;
            margin-top: 2rem;
        }
    </style>
</head>

<body>

    <header class="navbar">
        <div class="logo">🌿 <span>Welcome to eTOUR</span></div>
        <nav class="nav-links">
            <a href="auth/register.php">Register</a>
            <a href="auth/login.php">Login</a>
        </nav>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Discover Nature. Connect With Your Guides.</h1>
            <p>Plan your next adventure and book a guide who knows the land best.</p><br>
            <a href="auth/register.php" class="btn">Start Exploring</a>
        </div>
    </section>

    <main class="container">
        <h2 class="section-title">🌲 Featured Guides</h2>

        <?php if (empty($featured)): ?>
            <p class="no-guides">No guides available yet. Please check back later!</p>

        <?php else: ?>
            <div class="guide-grid">
                <?php foreach ($featured as $g): ?>
                    <div class="guide-card">
                        <h3><?= htmlspecialchars($g['guide_name']); ?></h3>

                        <p><strong>📍 Location:</strong> <?= htmlspecialchars($g['location']); ?></p>
                        <p><strong>🗣️ Languages:</strong> <?= htmlspecialchars($g['languages']); ?></p>

                        <p><strong>💰 Day Rate:</strong> ₱<?= number_format($g['rate_day'], 2); ?>/day</p>
                        <p><strong>💰 Hourly Rate:</strong> ₱<?= number_format($g['rate_hour'], 2); ?>/hour</p>

                        <p><strong>🏡 Accommodation:</strong><br>
                            <?= nl2br(htmlspecialchars($g['accommodation'])); ?>
                        </p>

                        <form method="GET" action="user/book_guide.php">
                            <input type="hidden" name="guide_id" value="<?= (int)$g['guide_id']; ?>">
                            <button type="submit" class="btn">Book / View</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>🌿 eTOUR © <?= date("Y"); ?> | Explore. Experience. Enjoy Nature.</p>
    </footer>

</body>
</html>
