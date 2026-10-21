<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/customer_session.php';
initialize_customer_session();

require_once __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/shift.php';

$orderingOpen = customer_ordering_is_enabled($pdo);
$imageColumnStatement = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'menu_items'
       AND COLUMN_NAME = 'image_filename'"
);
$homeHasImageColumn = (int) $imageColumnStatement->fetchColumn() > 0;
$featuredItems = $pdo->query(
    'SELECT menu_items.item_id, menu_items.name, menu_items.description, menu_items.base_price,
            categories.name AS category_name'
            . ($homeHasImageColumn ? ', menu_items.image_filename' : '') . '
     FROM menu_items
     INNER JOIN categories ON categories.category_id = menu_items.category_id
     WHERE menu_items.is_active = 1
       AND menu_items.is_sold_out = 0
       AND menu_items.stock_quantity > 0
     ORDER BY menu_items.created_at DESC, menu_items.item_id DESC
     LIMIT 6'
)->fetchAll(PDO::FETCH_ASSOC);
$categoryImages = [
    'Coffee Series' => 'coffee.png',
    'Matcha Series' => 'matcha.png',
    'Fruit Milk' => 'fruit-milk.png',
    'Soda' => 'soda.png',
    'Fruit Shake' => 'fruit-shake.png',
    'Latte Series with Whipped Cream' => 'latte.png',
    'Main Courses' => 'main-courses.png',
    'Snacks' => 'snacks.png',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>M&amp;E Kape Cup</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="customer-site bg-background font-body-md text-body-md text-on-surface antialiased">
<?php
$customerNavActive = 'home';
require __DIR__ . '/includes/customer_header.php';
?>
<main class="store-home">
    <section class="store-hero" aria-labelledby="store-hero-title">
        <div class="store-hero-copy">
            <span class="store-kicker"><span aria-hidden="true"></span> Your neighborhood coffee stop</span>
            <h1 id="store-hero-title">A good day starts with <em>good coffee.</em></h1>
            <p>Find your usual, try something new, and make it yours. Freshly made favorites are just a few taps away.</p>
            <div class="store-hero-actions">
                <a class="store-button-primary" href="menu.php">Explore the menu <span aria-hidden="true">→</span></a>
                <a class="store-button-link" href="status.php">Track an order <span aria-hidden="true">↗</span></a>
            </div>
            <div class="store-order-status <?= $orderingOpen ? 'is-open' : 'is-paused' ?>" role="status">
                <span class="store-status-dot" aria-hidden="true"></span>
                <?= $orderingOpen ? 'Online ordering is open' : 'Online ordering is paused right now' ?>
            </div>
        </div>
        <div class="store-hero-art" aria-label="Coffee and café favorites">
            <div class="store-hero-photo store-hero-photo-main">
                <img src="assets/img/coffee.png" alt="Freshly made iced coffee" fetchpriority="high">
                <span class="store-photo-caption">Made for your moment</span>
            </div>
            <div class="store-hero-photo store-hero-photo-accent">
                <img src="assets/img/latte.png" alt="Creamy iced latte">
            </div>
            <span class="store-hero-stamp" aria-hidden="true">BREW<br>·<br>RELAX</span>
        </div>
        <div class="store-hero-bottom" aria-hidden="true">
            <span>GOOD COFFEE, GOOD COMPANY</span><span>EST. WITH LOVE</span>
        </div>
    </section>

    <section class="store-featured" aria-labelledby="featured-menu-heading">
        <div class="store-section-heading">
            <div>
                <span class="store-kicker">A little something for everyone</span>
                <h2 id="featured-menu-heading">Meet your next favorite</h2>
                <p>Real picks from the menu, ready when you are.</p>
            </div>
            <a class="store-view-menu" href="menu.php">View the full menu <span aria-hidden="true">→</span></a>
        </div>
        <?php if ($featuredItems === []): ?>
            <div class="store-empty-menu">
                <span class="material-symbols-outlined" aria-hidden="true">local_cafe</span>
                <p>Our menu is getting ready. Check back soon for today's favorites.</p>
            </div>
        <?php else: ?>
            <div class="store-product-grid">
                <?php foreach ($featuredItems as $item): ?>
                    <?php
                    $itemImage = $homeHasImageColumn && is_string($item['image_filename']) && $item['image_filename'] !== ''
                        ? 'api/menu_image.php?id=' . (int) $item['item_id']
                        : 'assets/img/' . ($categoryImages[$item['category_name']] ?? 'coffee.png');
                    ?>
                    <article class="store-product-card">
                        <a class="store-product-image" href="product_details.php?id=<?= (int) $item['item_id'] ?>" tabindex="-1" aria-hidden="true">
                            <img src="<?= escape_html($itemImage) ?>" alt="" loading="lazy" decoding="async">
                            <span class="store-product-category"><?= escape_html($item['category_name']) ?></span>
                        </a>
                        <div class="store-product-copy">
                            <h3><a href="product_details.php?id=<?= (int) $item['item_id'] ?>"><?= escape_html($item['name']) ?></a></h3>
                            <?php if ($item['description'] !== null && $item['description'] !== ''): ?>
                                <p><?= escape_html($item['description']) ?></p>
                            <?php else: ?>
                                <p>Made fresh to order at M&amp;E Kape Cup.</p>
                            <?php endif; ?>
                            <div class="store-product-bottom">
                                <strong>₱<?= escape_html(number_format((float) $item['base_price'], 2)) ?></strong>
                                <a href="product_details.php?id=<?= (int) $item['item_id'] ?>" aria-label="Customize <?= escape_html($item['name']) ?>">Make it yours <span aria-hidden="true">→</span></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="store-brand-note" aria-label="About Kape Cup">
        <span class="material-symbols-outlined" aria-hidden="true">coffee</span>
        <p><strong>Good things take a little care.</strong> Your favorites are prepared fresh, one cup at a time.</p>
        <a href="menu.php">Find yours <span aria-hidden="true">→</span></a>
    </section>
</main>
<footer class="home-footer">
    <div class="home-footer-inner">
        <div class="home-footer-brand">
            <a class="home-footer-name" href="index.php">M&amp;E Kape Cup</a>
            <span class="home-footer-tag">Cafe &amp; Roastery</span>
            <p>Warm roasts, timeless diner charm, and handcrafted coffee.</p>
        </div>
        <nav class="home-footer-links" aria-label="Staff and administrator login">
            <a href="staff/login.php">Staff login</a>
            <a href="admin/login.php">Admin login</a>
        </nav>
        <p class="home-footer-copyright">&copy; <?= date('Y') ?> M&amp;E Kape Cup. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
