<?php
declare(strict_types=1);

// Keep local WAMP credentials outside the public DocumentRoot.
$privateConfigPath = getenv('APP_PRIVATE_CONFIG');
if (!is_string($privateConfigPath) || $privateConfigPath === '') {
    $privateConfigPath = dirname(__DIR__, 3)
        . DIRECTORY_SEPARATOR . 'private'
        . DIRECTORY_SEPARATOR . 'kape_cup_pos.php';
}

$privateConfig = [];
if (is_file($privateConfigPath)) {
    try {
        $loadedConfig = require $privateConfigPath;
    } catch (Throwable $exception) {
        error_log('[database] Private configuration could not be loaded.');
        http_response_code(500);
        exit('Database configuration could not be loaded.');
    }

    if (!is_array($loadedConfig)) {
        error_log('[database] Private configuration must return an array.');
        http_response_code(500);
        exit('Database configuration is invalid.');
    }

    $privateConfig = $loadedConfig;
    foreach (['DB_HOST', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD', 'ORDER_TRACKING_SECRET', 'APP_TIMEZONE'] as $key) {
        if (isset($privateConfig[$key]) && is_string($privateConfig[$key])) {
            putenv($key . '=' . $privateConfig[$key]);
        }
    }
} elseif (file_exists($privateConfigPath)) {
    error_log('[database] Private configuration path is not a readable file.');
    http_response_code(500);
    exit('Database configuration is invalid.');
}

$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$appConfig = require __DIR__ . '/app.php';
$timezone = isset($appConfig['timezone']) && is_string($appConfig['timezone'])
    ? $appConfig['timezone']
    : '';
$timezone = in_array($timezone, timezone_identifiers_list(), true)
    ? $timezone
    : 'Asia/Manila';
date_default_timezone_set($timezone);
$tz = new DateTimeZone($timezone);
$seconds = $tz->getOffset(new DateTime('now', $tz));
$sign = $seconds < 0 ? '-' : '+';
$abs = abs($seconds);
$offset = sprintf('%s%02d:%02d', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60));

$host = is_string($host) && $host !== '' ? $host : 'localhost';
$dbname = is_string($dbname) && $dbname !== ''
    ? $dbname
    : 'db_kape_cup_pos';
$username = is_string($username) ? trim($username) : '';
$password = is_string($password) ? $password : '';

if ($username === '' || strcasecmp($username, 'root') === 0 || $password === '') {
    error_log('[database] A dedicated MySQL username and non-empty password must be configured.');
    http_response_code(500);
    exit('Database credentials are not configured. Please check the database setup instructions.');
}

if (!extension_loaded('pdo_mysql')) {
    error_log('[database] The PDO MySQL driver is not available.');
    http_response_code(500);
    exit('Database service is temporarily unavailable. Please contact support.');
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . $offset . "'",
        ]
    );
} catch (PDOException $exception) {
    $sqlState = (string) $exception->getCode();
    if (preg_match('/\A[A-Z0-9]{5}\z/', $sqlState) !== 1) {
        $sqlState = 'unknown';
    }
    $driverCode = is_array($exception->errorInfo) && isset($exception->errorInfo[1])
        && is_numeric($exception->errorInfo[1])
        ? (string) (int) $exception->errorInfo[1]
        : 'unknown';
    error_log('[database] Connection failed (SQLSTATE ' . $sqlState . ', driver code ' . $driverCode . ').');
    http_response_code(500);
    exit('Database connection failed. Please check the database configuration.');
}