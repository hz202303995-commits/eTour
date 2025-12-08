<?php
session_start();
require_once "../includes/database.php";

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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
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
";

$params = [];

if ($search) {
    $sql .= " 
        WHERE 
            u.name LIKE :q OR 
            g.location LIKE :q OR 
            g.languages LIKE :q
    ";
    $params['q'] = "%$search%";
}

$sql .= " ORDER BY u.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$guides = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Search Guides - eTOUR</title>
<link rel="stylesheet" href="../style.css">

<style>
    .search-bar {
        margin: 20px 0;
        text-align: center;
    }
    .search-bar input {
        width: 50%;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 16px;
    }
    .search-bar button {
        padding: 10px 16px;
        font-size: 16px;
        border-radius: 8px;
        background: #2b7a78;
        color: #fff;
        border: none;
        cursor: pointer;
    }
    .search-bar button:hover {
        background: #205e5a;
    }
</style>
</head>

<body>

<header class="navbar">
    <div class="logo">🌿 eTOUR | Browse Guides</div>
    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="search.php" class="active-link">Search Guides</a>
        <a href="bookings.php">My Bookings</a>
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>


<div class="container">

    <h2>Search Guides</h2>

    <form class="search-bar" method="GET" action="">
        <input 
            type="text" 
            name="search" 
            placeholder="Search by name, language, or location..." 
            value="<?= htmlspecialchars($search) ?>"
        >
        <button type="submit">Search</button>
    </form>


    <?php if (!$guides): ?>
        <p>No guides found<?= $search ? " for '<strong>" . htmlspecialchars($search) . "</strong>'" : "" ?>.</p>

    <?php else: ?>
        <?php foreach ($guides as $g): ?>
            <div class="guide-card">

                <h3><?= htmlspecialchars($g['guide_name']); ?></h3>

                <p>📍 <strong>Location:</strong> <?= htmlspecialchars($g['location']); ?></p>
                <p>🗣 <strong>Languages:</strong> <?= htmlspecialchars($g['languages']); ?></p>
                <p>🏨 <strong>Accommodation:</strong> <?= nl2br(htmlspecialchars($g['accommodation'])); ?></p>
                <p>
                    💰 <strong>Day Rate:</strong> ₱<?= number_format($g['rate_day'], 2); ?><br>
                    💰 <strong>Hour Rate:</strong> ₱<?= number_format($g['rate_hour'], 2); ?>
                </p>

                <form method="GET" action="book_guide.php">
                    <input type="hidden" name="guide_id" value="<?= (int)$g['guide_id']; ?>">

                    <?php if ($search): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search); ?>">
                    <?php endif; ?>

                    <button type="submit" class="btn">Book / View</button>
                </form>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

</body>
</html>
