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
$customerNavActiveClasses = 'px-space-md py-space-xs transition-colors bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm';
$customerNavIdleClasses = 'px-space-md py-space-xs font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors';
?>
<header class="fixed top-0 left-0 right-0 z-40 bg-surface/90 backdrop-blur-md shadow-[0_1px_8px_rgba(41,34,29,0.06)]">
    <div class="h-20 max-w-[1280px] mx-auto px-margin-desktop flex items-center justify-between gap-space-md">
        <div class="flex items-center gap-space-md">
            <a class="flex items-center gap-space-sm group" href="index.php">
                <img class="w-11 h-11 rounded-full object-cover" src="assets/img/me-kape-cup-logo.png" alt="">
                <div class="flex flex-col">
                    <span class="font-headline-sm text-headline-sm text-espresso-dark group-hover:text-primary transition-colors tracking-tight">M&amp;E Kape Cup</span>
                    <span class="font-label-sm text-label-sm text-text-muted tracking-wider uppercase">Artisanal Brew &amp; Comforts</span>
                </div>
            </a>
        </div>
        <nav class="hidden md:flex items-center gap-space-sm" aria-label="Main navigation">
            <?php foreach ($customerNavItems as $customerNavKey => $customerNavItem): ?>
                <a class="<?= $customerNavActive === $customerNavKey ? $customerNavActiveClasses : $customerNavIdleClasses ?>"
                   href="<?= htmlspecialchars($customerNavItem['href'], ENT_QUOTES, 'UTF-8') ?>"
                   <?= $customerNavActive === $customerNavKey ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($customerNavItem['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="flex items-center gap-space-md">
            <a class="relative flex items-center justify-center w-11 h-11 rounded-full bg-surface-container-high text-espresso-dark hover:bg-caramel-gold/20 hover:text-primary transition-colors" href="cart.php" aria-label="Open cart">
                <span class="material-symbols-outlined text-title-md" aria-hidden="true">shopping_bag</span>
                <?php if ($customerCartCount !== null): ?>
                    <span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-accessible-red text-on-error font-label-sm text-label-sm flex items-center justify-center font-bold"><?= (int) $customerCartCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</header>
