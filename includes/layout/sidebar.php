<?php
declare(strict_types=1);

$navigationConfig = require dirname(__DIR__) . '/nav_config.php';
$navItems = array_values(array_filter(
    $navigationConfig,
    static function (array $item) use ($role): bool {
        return in_array($role, $item['roles'], true);
    }
));
$basePath = app_base_path();
$initial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr((string) $user['full_name'], 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr((string) $user['full_name'], 0, 1));
$statusText = $role === 'staff' ? 'Live POS' : 'Administrator access';
$statusDescription = 'Connected to the POS database';
$secondaryLabel = $role === 'staff' ? 'End shift' : 'Change password';
$secondaryUrl = $role === 'staff'
    ? $basePath . '/staff/end_shift.php'
    : $basePath . '/staff/change_password.php';
?>
<aside class="app-sidebar" id="app-sidebar" aria-label="Application sidebar">
    <a class="app-brand" href="<?= escape_html($role === 'admin' ? $basePath . '/admin/dashboard.php?tab=overview' : $basePath . '/staff/offline_pos.php?page=pos') ?>">
        <img src="<?= escape_html($basePath) ?>/assets/img/me-kape-cup-logo.png" alt="" width="40" height="40">
        <span class="app-brand-copy">
            <strong>M&amp;E Kape Cup</strong>
            <small><?= $role === 'admin' ? 'Admin Console' : 'Barista Cockpit' ?></small>
        </span>
    </a>
    <div class="app-context-card">
        <span><?= $role === 'admin' ? 'Account' : 'Station' ?></span>
        <strong><?= $role === 'admin' ? 'Administrator' : 'Terminal #01' ?></strong>
    </div>
    <nav class="app-nav" aria-label="<?= $role === 'admin' ? 'Admin' : 'Staff' ?> navigation">
        <?php foreach ($navItems as $item): ?>
            <?php $selected = $item['key'] === $activeNav; ?>
            <?php $isAdminDashboardTab = $role === 'admin' && strpos($item['url'], '/admin/dashboard.php?tab=') === 0; ?>
            <a
                class="app-nav-link<?= $selected ? ' is-active' : '' ?>"
                href="<?= escape_html($basePath . $item['url']) ?>"
                <?= $selected ? 'aria-current="page"' : '' ?>
                <?php if ($isAdminDashboardTab): ?>
                    id="nav-<?= escape_html($item['key']) ?>"
                    data-admin-tab="<?= escape_html($item['key']) ?>"
                    data-tab-label="<?= escape_html($item['label']) ?>"
                <?php endif; ?>
            >
                <span class="material-symbols-outlined" aria-hidden="true"><?= escape_html($item['icon']) ?></span>
                <span><?= escape_html($item['label']) ?></span>
                <?php if (isset($item['badge'])): ?>
                    <span class="app-nav-badge" data-badge="<?= escape_html($item['badge']) ?>" hidden></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <div class="app-nav-divider" role="separator"></div>
        <?php if ($role === 'staff'): ?>
            <form method="post" action="<?= escape_html($secondaryUrl) ?>" class="app-sidebar-action" data-confirm-shift="End your current staff shift?">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <button class="app-nav-link" type="submit">
                    <span class="material-symbols-outlined" aria-hidden="true">door_open</span>
                    <span><?= escape_html($secondaryLabel) ?></span>
                </button>
            </form>
        <?php else: ?>
            <a class="app-nav-link" href="<?= escape_html($secondaryUrl) ?>">
                <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                <span><?= escape_html($secondaryLabel) ?></span>
            </a>
        <?php endif; ?>
    </nav>
    <div class="app-sidebar-bottom">
        <div class="app-status-card">
            <span class="app-status-dot" aria-hidden="true"></span>
            <span><strong><?= escape_html($statusText) ?></strong><small><?= escape_html($statusDescription) ?></small></span>
        </div>
        <div class="app-user-row">
            <span class="app-avatar" aria-hidden="true"><?= escape_html($initial) ?></span>
            <span class="app-user-name"><?= escape_html($user['full_name']) ?></span>
            <form method="post" action="<?= escape_html($basePath) ?>/staff/logout.php">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <button class="app-logout-button" type="submit" aria-label="Log out">
                    <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>
<button class="app-sidebar-backdrop" type="button" aria-label="Close navigation" data-sidebar-close hidden></button>
