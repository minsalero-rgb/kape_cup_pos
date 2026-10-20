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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape_html($pageTitle) ?> | M&amp;E Kape Cup</title>
    <link rel="stylesheet" href="<?= escape_html(app_base_path()) ?>/assets/css/tokens.css">
    <link rel="stylesheet" href="<?= escape_html(app_base_path()) ?>/assets/css/style.css?v=20261018-7">
    <link rel="stylesheet" href="<?= escape_html(app_base_path()) ?>/assets/css/app.css?v=20261020-1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Merriweather:wght@400;700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
</head>
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
