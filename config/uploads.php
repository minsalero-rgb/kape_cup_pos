<?php
declare(strict_types=1);

$configuredDirectory = getenv('APP_UPLOAD_DIR');
if (!is_string($configuredDirectory) || trim($configuredDirectory) === '') {
    $configuredDirectory = dirname(__DIR__, 3)
        . DIRECTORY_SEPARATOR . 'private'
        . DIRECTORY_SEPARATOR . 'kape_cup_pos_uploads'
        . DIRECTORY_SEPARATOR . 'menu';
}

return [
    'menu_directory' => $configuredDirectory,
];
