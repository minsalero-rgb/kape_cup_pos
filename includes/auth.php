<?php
declare(strict_types=1);

function initialize_staff_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_name('mekape_staff_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

initialize_staff_session();

function staff_is_authenticated(): bool
{
    if (
        !isset($_SESSION['staff_id']) ||
        filter_var($_SESSION['staff_id'], FILTER_VALIDATE_INT) === false ||
        (int) $_SESSION['staff_id'] < 1
    ) {
        return false;
    }

    $now = time();
    $lastActivity = isset($_SESSION['staff_last_activity'])
        ? filter_var($_SESSION['staff_last_activity'], FILTER_VALIDATE_INT)
        : false;

    if (
        $lastActivity === false ||
        $lastActivity > $now ||
        $now - $lastActivity > 1800
    ) {
        unset(
            $_SESSION['staff_id'],
            $_SESSION['staff_name'],
            $_SESSION['staff_role'],
            $_SESSION['shift_id'],
            $_SESSION['staff_last_activity']
        );
        session_regenerate_id(true);
        return false;
    }

    $_SESSION['staff_last_activity'] = $now;
    return true;
}

function app_base_path(): string
{
    $scriptDirectory = str_replace(
        '\\',
        '/',
        dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')
    );

    if (preg_match('~/(staff|admin|api)$~', $scriptDirectory) === 1) {
        $scriptDirectory = dirname($scriptDirectory);
    }

    if ($scriptDirectory === '.' || $scriptDirectory === '/') {
        return '';
    }

    return rtrim($scriptDirectory, '/');
}

function redirect_to_staff(string $page): void
{
    header('Location: ' . app_base_path() . '/staff/' . ltrim($page, '/'));
    exit;
}

function redirect_to_login(): void
{
    $scriptName = isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])
        ? $_SERVER['SCRIPT_NAME']
        : '';

    if (strpos($scriptName, '/admin/') !== false) {
        header('Location: ' . app_base_path() . '/admin/login.php');
        exit;
    }

    redirect_to_staff('login.php');
}

function set_flash_message(string $key, string $message): void
{
    $_SESSION['flash_messages'][$key] = $message;
}

function get_flash_message(string $key): ?string
{
    if (!isset($_SESSION['flash_messages'][$key])) {
        return null;
    }

    $message = (string) $_SESSION['flash_messages'][$key];
    unset($_SESSION['flash_messages'][$key]);

    if (empty($_SESSION['flash_messages'])) {
        unset($_SESSION['flash_messages']);
    }

    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_token_is_valid($token): bool
{
    return is_string($token) &&
        isset($_SESSION['csrf_token']) &&
        is_string($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token);
}

function require_post_request(): void
{
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('This action requires a POST request.');
    }
}

function require_login(PDO $pdo): void
{
    if (!staff_is_authenticated()) {
        redirect_to_login();
    }

    $statement = $pdo->prepare(
        'SELECT staff_id, full_name, role
         FROM staff
         WHERE staff_id = :staff_id
           AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['staff_id' => (int) $_SESSION['staff_id']]);
    $staff = $statement->fetch();

    if (!$staff) {
        unset(
            $_SESSION['staff_id'],
            $_SESSION['staff_name'],
            $_SESSION['staff_role'],
            $_SESSION['shift_id'],
            $_SESSION['staff_last_activity']
        );
        session_regenerate_id(true);
        set_flash_message('error', 'Your account is inactive or your session has expired. Please sign in again.');
        redirect_to_login();
    }

    $_SESSION['staff_id'] = (int) $staff['staff_id'];
    $_SESSION['staff_name'] = (string) $staff['full_name'];
    $_SESSION['staff_role'] = (string) $staff['role'];
}

function require_role($roles, PDO $pdo): void
{
    require_login($pdo);

    $allowedRoles = is_array($roles) ? $roles : [$roles];
    $allowedRoles = array_values(array_filter($allowedRoles, 'is_string'));

    if (!in_array($_SESSION['staff_role'], $allowedRoles, true)) {
        http_response_code(403);
        exit('You are not authorized to access this page.');
    }
}

function current_staff_user(): array
{
    return [
        'staff_id' => (int) $_SESSION['staff_id'],
        'full_name' => (string) $_SESSION['staff_name'],
        'role' => (string) $_SESSION['staff_role'],
    ];
}

function escape_html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}