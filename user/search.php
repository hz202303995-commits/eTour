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

                <p class="info-row"><span class="label">📍 Location:</span><span class="value"><?= htmlspecialchars($g['location']); ?></span></p>
                <p class="info-row"><span class="label">🗣 Languages:</span><span class="value"><?= htmlspecialchars($g['languages']); ?></span></p>
                <?php
                $accom = trim($g['accommodation'] ?? '');
                $maxChars = 120;
                if (mb_strlen($accom) > $maxChars):
                    $preview = htmlspecialchars(mb_substr($accom, 0, $maxChars));
                ?>
                <p class="accommodation-preview" title="<?= htmlspecialchars($accom) ?>"><strong>🏨 Accommodation:</strong> <?= htmlspecialchars($preview) ?> <span class="see-more" aria-hidden="true">&hellip;</span></p>
                <?php else: ?>
                <p class="accommodation-preview"><strong>🏨 Accommodation:</strong> <?= nl2br(htmlspecialchars($accom)); ?></p>
                <?php endif; ?>
                <p class="info-row"><span class="label">💰 Day Rate:</span><span class="value">₱<?= number_format($g['rate_day'], 2); ?></span></p>
                <p class="info-row"><span class="label">💰 Hour Rate:</span><span class="value">₱<?= number_format($g['rate_hour'], 2); ?></span></p>

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
