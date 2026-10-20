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
    <link rel="stylesheet" href="../assets/css/tokens.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=20261018-7">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Merriweather:wght@400;700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
</head>
<body class="min-h-screen bg-[#FFF8F5] text-[#221A13]">
<main class="min-h-screen flex items-center justify-center px-4 py-10">
    <section class="w-full max-w-md rounded-2xl bg-[#FFFCF6] p-8 shadow-xl">
        <div class="mb-8 text-center">
            <a href="../index.php" class="mx-auto mb-4 block h-16 w-16 overflow-hidden rounded-full" aria-label="M&E Kape Cup home"><img class="h-full w-full object-cover" src="../assets/img/me-kape-cup-logo.png" alt=""></a>
            <h1 class="text-2xl font-bold"><?= escape_html($loginTitle) ?></h1>
            <p class="mt-2 text-sm text-[#74685E]">
                Sign in to access the <?= $loginRole === 'admin' ? 'management dashboard' : 'POS terminal' ?>.
            </p>
        </div>
        <?php if ($signedOut): ?>
            <p class="mb-5 rounded-lg bg-[#EAF2E3] p-3 text-sm" role="status">You have been signed out.</p>
        <?php endif; ?>
        <?php if ($errorMessage !== null): ?>
            <p class="mb-5 rounded-lg bg-[#FDE5E1] p-3 text-sm text-[#8B2E24]" role="alert"><?= escape_html($errorMessage) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= escape_html($formAction) ?>" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <label class="block text-sm font-semibold" for="username">Username
                <input id="username" name="username" type="text" autocomplete="username" required maxlength="80" value="<?= escape_html(isset($username) ? $username : '') ?>" class="mt-2 w-full rounded-lg border border-[#E8DEC9] bg-[#FFFCF6] px-4 py-3 outline-none focus:ring-2 focus:ring-[#D9B77E]">
            </label>
            <label class="block text-sm font-semibold" for="password">Password
                <input id="password" name="password" type="password" autocomplete="current-password" required class="mt-2 w-full rounded-lg border border-[#E8DEC9] bg-[#FFFCF6] px-4 py-3 outline-none focus:ring-2 focus:ring-[#D9B77E]">
            </label>
            <button type="submit" class="w-full rounded-lg bg-[#3B1F14] px-4 py-3 font-semibold text-white transition hover:bg-[#220B03]">Log In</button>
        </form>
        <p class="mt-6 text-center text-sm text-[#74685E]">
            <a href="../index.php" class="underline underline-offset-4 hover:text-[#3B1F14]">Back to home</a>
        </p>
    </section>
</main>
</body>
</html>
