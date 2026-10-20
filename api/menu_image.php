<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
require_once __DIR__ . '/../includes/menu_images.php';

$itemId = isset($_GET['id']) && is_string($_GET['id'])
    ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($itemId === false) {
    http_response_code(404);
    exit;
}

try {
    require __DIR__ . '/../config/db.php';
    $statement = $pdo->prepare(
        'SELECT image_filename
         FROM menu_items
         WHERE item_id = :item_id
           AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['item_id' => (int) $itemId]);
    $filename = $statement->fetchColumn();
    if (!is_string($filename) || preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $filename, $matches) !== 1) {
        http_response_code(404);
        exit;
    }

    $directory = realpath(menu_image_directory());
    $path = $directory === false ? false : realpath($directory . DIRECTORY_SEPARATOR . $filename);
    if ($directory === false || $path === false || dirname($path) !== $directory || !is_file($path) || !is_readable($path)) {
        http_response_code(404);
        exit;
    }

    $fileSize = filesize($path);
    if ($fileSize === false) {
        http_response_code(404);
        exit;
    }

    $contentTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];
    header('Content-Type: ' . $contentTypes[$matches[1]]);
    header('Content-Length: ' . (string) $fileSize);
    readfile($path);
} catch (PDOException $exception) {
    error_log('[menu-image] Image lookup failed.');
    http_response_code(404);
} catch (RuntimeException $exception) {
    error_log('[menu-image] Image storage configuration is unavailable.');
    http_response_code(404);
}
