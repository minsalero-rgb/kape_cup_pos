<?php
declare(strict_types=1);

$topbarLabel = $role === 'admin' ? 'Administration' : 'Staff';
$initial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr((string) $user['full_name'], 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr((string) $user['full_name'], 0, 1));
?>
<header class="app-topbar">
    <div class="app-topbar-identity">
        <button class="app-menu-toggle" type="button" aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false" data-sidebar-toggle>
            <span class="material-symbols-outlined" aria-hidden="true">menu</span>
        </button>
        <span class="app-topbar-eyebrow"><?= escape_html($topbarLabel) ?></span>
    </div>
    <div class="app-topbar-user">
        <time datetime="<?= escape_html(date('Y-m-d')) ?>"><?= escape_html(date('D, M j')) ?></time>
        <span class="app-topbar-name"><?= escape_html($user['full_name']) ?></span>
        <span class="app-avatar app-avatar--top" aria-label="<?= escape_html($user['full_name']) ?>"><?= escape_html($initial) ?></span>
    </div>
</header>
