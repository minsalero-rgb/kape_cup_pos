<?php
declare(strict_types=1);

if (!isset($role, $pageTitle, $activeNav, $user) || !in_array($role, ['admin', 'staff'], true)) {
    http_response_code(500);
    exit('Application layout configuration is invalid.');
}

$pageEyebrow = isset($pageEyebrow) && is_string($pageEyebrow)
    ? $pageEyebrow
    : ($role === 'admin' ? 'Administration' : 'Barista cockpit');
$pageDescription = isset($pageDescription) && is_string($pageDescription)
    ? $pageDescription
    : '';
?>
<?php
$pageCss = ['css/style.css'];
$pageJs = ['js/ui.js'];
require dirname(__DIR__) . '/partials/head.php';
?>
<body class="app-body app-body--<?= escape_html($role) ?>">
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/topbar.php'; ?>
        <main class="app-content">
            <div class="app-page-heading">
                <div>
                    <p class="app-eyebrow"><?= escape_html($pageEyebrow) ?></p>
                    <h1 data-app-page-title><?= escape_html($pageTitle) ?></h1>
                    <?php if ($pageDescription !== ''): ?>
                        <p class="app-page-description"><?= escape_html($pageDescription) ?></p>
                    <?php endif; ?>
                </div>
            </div>
