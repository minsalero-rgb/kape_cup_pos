<?php
declare(strict_types=1);

if (!function_exists('asset_url')) {
    function asset_url(string $asset): string
    {
        $relativePath = ltrim($asset, '/\\');
        $filePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $relativePath
        );
        $modifiedAt = is_file($filePath) ? filemtime($filePath) : false;
        if ($modifiedAt === false) {
            throw new RuntimeException('Unable to determine asset version.');
        }

        return app_base_path() . '/assets/' . str_replace('\\', '/', $relativePath) . '?v=' . $modifiedAt;
    }
}

$pageTitle = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'M&E Kape Cup';
$pageCss = ['css/style.css'];
$pageJs = isset($pageJs) && is_array($pageJs) ? $pageJs : ['js/ui.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape_html($pageTitle) ?> | M&amp;E Kape Cup</title>
    <link rel="stylesheet" href="<?= escape_html(asset_url($pageCss[0])) ?>">
    <?php foreach ($pageJs as $script): ?>
        <script src="<?= escape_html(asset_url($script)) ?>" defer></script>
    <?php endforeach; ?>
</head>
