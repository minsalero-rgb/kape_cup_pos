<?php
declare(strict_types=1);

/**
 * Shared customer-facing header.
 *
 * Usage: set $customerNavActive to one of 'home', 'menu', 'order-status'
 * (or leave it unset / '' for pages such as checkout), then include this file
 * from a path relative to the page, e.g.:
 *     require __DIR__ . '/includes/customer_header.php';
 *
 * The markup and classes match the header on menu.php, cart.php and
 * product_details.php so every customer page looks identical.
 */

$customerNavActive = isset($customerNavActive) && is_string($customerNavActive) ? $customerNavActive : '';

$customerCartCount = null;
if (session_status() === PHP_SESSION_ACTIVE) {
    $customerCartCount = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $customerCartEntry) {
            if (is_array($customerCartEntry) && isset($customerCartEntry['quantity'])) {
                $customerCartCount += (int) $customerCartEntry['quantity'];
            }
        }
    }
}

$customerNavItems = [
    'home' => ['label' => 'Home', 'href' => 'index.php'],
    'menu' => ['label' => 'Menu', 'href' => 'menu.php'],
    'order-status' => ['label' => 'Order Status', 'href' => 'status.php'],
];
?>
<header class="customer-header">
    <div class="customer-header__inner">
        <a class="customer-brand" data-path="home" href="index.php">
            <img src="assets/img/me-kape-cup-logo.png" alt="" width="40" height="40">
            <span class="customer-brand__copy">
                <span class="customer-brand__name">M&amp;E Kape Cup</span>
                <span class="customer-brand__tagline">Artisanal Brew &amp; Comforts</span>
            </span>
        </a>
        <nav class="customer-nav" aria-label="Main navigation" data-active-classes="bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm">
            <?php foreach ($customerNavItems as $customerNavKey => $customerNavItem): ?>
                <a class="customer-nav__link"
                   data-path="<?= escape_html($customerNavKey) ?>"
                   href="<?= escape_html($customerNavItem['href']) ?>"
                   <?= $customerNavActive === $customerNavKey ? 'aria-current="page"' : '' ?>><?= escape_html($customerNavItem['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <a class="customer-cart-link" data-path="cart-drawer" href="cart.php" aria-label="Open cart">
            <span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>
            <?php if ($customerCartCount !== null): ?>
                <span class="customer-cart-count" aria-label="<?= escape_html((string) $customerCartCount) ?> items"><?= (int) $customerCartCount ?></span>
            <?php endif; ?>
        </a>
    </div>
</header>
<?php if ($customerNavActive === 'menu' && $customerCartCount !== null && $customerCartCount > 0): ?>
    <a class="customer-mobile-cart-bar" href="cart.php" aria-label="View cart with <?= escape_html((string) $customerCartCount) ?> items">
        <span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span>
        <span>View cart</span>
        <strong><?= escape_html((string) $customerCartCount) ?> <?= $customerCartCount === 1 ? 'item' : 'items' ?></strong>
        <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
    </a>
<?php endif; ?>
