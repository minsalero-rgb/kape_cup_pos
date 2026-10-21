<?php
declare(strict_types=1);

if (!isset($loginRole) || !in_array($loginRole, ['staff', 'admin'], true)) {
    http_response_code(500);
    exit('Login page configuration is invalid.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/login_throttle.php';
require __DIR__ . '/../config/db.php';

const LOGIN_DUMMY_HASH = '$2y$10$6QtDhpSvYpwtev3Y.Ney5uU8IrEqbtDnsNfesX0.OaQBvm2sAe8G6';

$errorMessage = null;
$signedOut = isset($_GET['message']) && $_GET['message'] === 'signed-out';
$loginTitle = $loginRole === 'admin' ? 'Admin Login' : 'Staff Login';
$dashboardPath = $loginRole === 'admin'
    ? '/admin/dashboard.php'
    : '/staff/offline_pos.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) && is_string($_POST['username'])
        ? trim($_POST['username'])
        : '';
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'This login form expired. Refresh the page and try again.';
    } elseif (login_is_locked($pdo, $username)) {
        http_response_code(429);
        $errorMessage = 'Too many sign-in attempts. Please wait a few minutes and try again.';
    } elseif ($username === '' || $password === '') {
        password_verify($password, LOGIN_DUMMY_HASH);
        login_record_failure($pdo, $username);
        $errorMessage = 'Incorrect username or password. Please verify your credentials or contact an administrator.';
    } else {
        $statement = $pdo->prepare(
            'SELECT staff_id, full_name, password_hash, role, is_active
             FROM staff
             WHERE username = :username
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $staff = $statement->fetch();

        $passwordIsValid = false;
        if ($staff && (int) $staff['is_active'] === 1) {
            $passwordIsValid = password_verify($password, (string) $staff['password_hash']);
        } else {
            password_verify($password, LOGIN_DUMMY_HASH);
        }

        if (
            !$passwordIsValid
            || !$staff
            || (string) $staff['role'] !== $loginRole
        ) {
            login_record_failure($pdo, $username);
            $errorMessage = 'Incorrect username or password. Please verify your credentials or contact an administrator.';
        } else {
            login_clear_failures($pdo, $username);

            if (password_needs_rehash((string) $staff['password_hash'], PASSWORD_DEFAULT)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $rehashStatement = $pdo->prepare(
                    'UPDATE staff
                     SET password_hash = :password_hash
                     WHERE staff_id = :staff_id'
                );
                $rehashStatement->execute([
                    'password_hash' => $passwordHash,
                    'staff_id' => (int) $staff['staff_id'],
                ]);
            }

            session_regenerate_id(true);
            $_SESSION['staff_id'] = (int) $staff['staff_id'];
            $_SESSION['staff_name'] = (string) $staff['full_name'];
            $_SESSION['staff_role'] = (string) $staff['role'];
            $_SESSION['staff_last_activity'] = time();
            unset($_SESSION['shift_id']);

            header('Location: ' . app_base_path() . $dashboardPath);
            exit;
        }
    }
}

$formAction = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escape_html($loginTitle) ?> | M&amp;E Kape Cup</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/css/style.css') ?>">
</head>
<body class="login-page">
<main class="login-page__main">
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-card__brand">
            <a href="../index.php" class="login-card__logo" aria-label="M&E Kape Cup home"><img src="../assets/img/me-kape-cup-logo.png" alt=""></a>
            <h1 class="login-card__title" id="login-title"><?= escape_html($loginTitle) ?></h1>
            <p class="login-card__intro">
                Sign in to access the <?= $loginRole === 'admin' ? 'management dashboard' : 'POS terminal' ?>.
            </p>
        </div>
        <?php if ($signedOut): ?>
            <p class="ui-alert ui-alert--success login-card__notice" role="status">You have been signed out.</p>
        <?php endif; ?>
        <?php if ($errorMessage !== null): ?>
            <p class="ui-alert login-card__notice" role="alert"><?= escape_html($errorMessage) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= escape_html($formAction) ?>" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <label class="ui-field" for="username">Username
                <input class="ui-input" id="username" name="username" type="text" autocomplete="username" required maxlength="80" value="<?= escape_html(isset($username) ? $username : '') ?>">
            </label>
            <label class="ui-field" for="password">Password
                <input class="ui-input" id="password" name="password" type="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="ui-button ui-button--primary ui-button--large login-form__submit">Log In</button>
        </form>
        <p class="login-card__back">
            <a href="../index.php">Back to home</a>
        </p>
    </section>
</main>
</body>
</html>
