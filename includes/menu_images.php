<?php
declare(strict_types=1);

final class MenuImageUploadException extends RuntimeException
{
}

function menu_image_directory(): string
{
    $config = require __DIR__ . '/../config/uploads.php';
    $directory = isset($config['menu_directory']) && is_string($config['menu_directory'])
        ? trim($config['menu_directory'])
        : '';
    if ($directory === '') {
        throw new RuntimeException('Menu image storage is not configured.');
    }
    $isAbsolutePath = DIRECTORY_SEPARATOR === '\\'
        ? preg_match('/\A(?:[A-Za-z]:[\/\\\\]|\\\\\\\\)/', $directory) === 1
        : substr($directory, 0, 1) === '/';
    if (!$isAbsolutePath) {
        throw new RuntimeException('Menu image storage must use an absolute path.');
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT'])
        : false;
    $resolvedDirectory = realpath($directory);
    $pathToCheck = $resolvedDirectory === false ? $directory : $resolvedDirectory;
    if ($documentRoot !== false) {
        $normalizedRoot = rtrim(str_replace(['/', '\\'], '/', $documentRoot), '/');
        $normalizedPath = rtrim(str_replace(['/', '\\'], '/', $pathToCheck), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $normalizedRoot = strtolower($normalizedRoot);
            $normalizedPath = strtolower($normalizedPath);
        }
        if ($normalizedPath === $normalizedRoot || strpos($normalizedPath, $normalizedRoot . '/') === 0) {
            throw new RuntimeException('Menu image storage must be outside the public document root.');
        }
    }

    return $directory;
}

function save_menu_image_upload($file): ?array
{
    if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
        throw new MenuImageUploadException('The selected image upload is invalid.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new MenuImageUploadException('The image upload failed. Choose a smaller image and try again.');
    }

    $temporaryPath = isset($file['tmp_name']) && is_string($file['tmp_name'])
        ? $file['tmp_name']
        : '';
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new MenuImageUploadException('The selected image upload could not be verified.');
    }

    $fileSize = filesize($temporaryPath);
    if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new MenuImageUploadException('Menu images must be no larger than 5 MB.');
    }

    $imageInfo = getimagesize($temporaryPath);
    if (!is_array($imageInfo) || !isset($imageInfo['mime'], $imageInfo[0], $imageInfo[1])) {
        throw new MenuImageUploadException('Choose a valid JPEG, PNG, or WebP image.');
    }

    $mimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mimeType = (string) $imageInfo['mime'];
    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if (!isset($mimeToExtension[$mimeType]) || $width < 1 || $height < 1 || $width > 6000 || $height > 6000) {
        throw new MenuImageUploadException('Choose a JPEG, PNG, or WebP image no larger than 6000 x 6000 pixels.');
    }

    $directory = menu_image_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Menu image storage could not be created.');
    }
    $directory = menu_image_directory();
    if (!is_writable($directory)) {
        throw new RuntimeException('Menu image storage is not writable by the web server.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $mimeToExtension[$mimeType];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('The uploaded menu image could not be stored.');
    }

    return [
        'filename' => $filename,
        'path' => $destination,
    ];
}

function remove_menu_image(string $filename): void
{
    if (preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename) !== 1) {
        error_log('[menu-images] An invalid stored menu image filename was ignored.');
        return;
    }

    $path = menu_image_directory() . DIRECTORY_SEPARATOR . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('[menu-images] An old menu image could not be removed.');
    }
}
