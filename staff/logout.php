<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

require_login($pdo);
require_post_request();

if (!csrf_token_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    http_response_code(400);
    exit('This sign-out form expired. Return to the dashboard and try again.');
}

$loginPath = isset($_SESSION['staff_role']) && $_SESSION['staff_role'] === 'admin'
    ? '/admin/login.php'
    : '/staff/login.php';

$_SESSION = [];
$cookieParameters = session_get_cookie_params();

setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $cookieParameters['path'],
    'domain' => $cookieParameters['domain'],
    'secure' => $cookieParameters['secure'],
    'httponly' => $cookieParameters['httponly'],
    'samesite' => isset($cookieParameters['samesite']) ? $cookieParameters['samesite'] : 'Lax',
]);

session_destroy();
header('Location: ' . app_base_path() . $loginPath . '?message=signed-out');
exit;
