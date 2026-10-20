<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/password_rules.php';
require_once __DIR__ . '/../includes/login_throttle.php';
require __DIR__ . '/../config/db.php';

require_role(['staff', 'admin'], $pdo);
$user = current_staff_user();
$role = $user['role'];
$pageTitle = 'Change password';
$pageEyebrow = 'Account settings';
$activeNav = '';
$pageDescription = 'Choose at least 12 characters. Passwords may be up to 72 bytes.';
$error = '';
$success = get_flash_message('success');

$accountStatement = $pdo->prepare(
    'SELECT username, password_hash
     FROM staff
     WHERE staff_id = :staff_id
       AND is_active = 1
     LIMIT 1'
);
$accountStatement->execute(['staff_id' => $user['staff_id']]);
$account = $accountStatement->fetch(PDO::FETCH_ASSOC);
if (!$account) {
    http_response_code(403);
    exit('Your account is not available.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_request();
    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : null;

    if (!csrf_token_is_valid($token)) {
        http_response_code(403);
        $error = 'This form expired. Refresh the page and try again.';
    } elseif (login_is_locked($pdo, (string) $account['username'])) {
        http_response_code(429);
        $error = 'Too many password attempts. Please wait a few minutes and try again.';
    } else {
        $currentPassword = isset($_POST['current_password']) && is_string($_POST['current_password'])
            ? $_POST['current_password']
            : '';
        $newPassword = isset($_POST['new_password']) && is_string($_POST['new_password'])
            ? $_POST['new_password']
            : '';
        $confirmPassword = isset($_POST['confirm_password']) && is_string($_POST['confirm_password'])
            ? $_POST['confirm_password']
            : '';

        if (!password_verify($currentPassword, (string) $account['password_hash'])) {
            login_record_failure($pdo, (string) $account['username']);
            $error = 'The current password is incorrect.';
        } else {
            login_clear_failures($pdo, (string) $account['username']);
            $passwordError = validate_new_password($newPassword);
            if ($passwordError !== null) {
                $error = $passwordError;
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'The new password and confirmation do not match.';
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if (!is_string($passwordHash)) {
                    $error = 'The password could not be secured. Please try again.';
                } else {
                    try {
                        $updateStatement = $pdo->prepare(
                            'UPDATE staff
                             SET password_hash = :password_hash
                             WHERE staff_id = :staff_id
                               AND is_active = 1'
                        );
                        $updateStatement->execute([
                            'password_hash' => $passwordHash,
                            'staff_id' => $user['staff_id'],
                        ]);
                        if ($updateStatement->rowCount() !== 1) {
                            http_response_code(409);
                            $error = 'Your account changed. Refresh the page and try again.';
                        } else {
                            session_regenerate_id(true);
                            set_flash_message('success', 'Your password has been changed.');
                            header('Location: dashboard.php');
                            exit;
                        }
                    } catch (PDOException $exception) {
                        error_log('[staff-change-password] Password update failed.');
                        http_response_code(500);
                        $error = 'The password could not be changed. Please try again.';
                    }
                }
            }
        }
    }
}
require __DIR__ . '/../includes/layout/header.php';
?>
    <section class="card password-card">
        <?php if ($success !== null): ?><p class="mt-5 rounded-lg bg-green-50 p-3 text-sm text-green-900" role="status"><?= escape_html($success) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="mt-5 rounded-lg bg-red-50 p-3 text-sm text-red-900" role="alert"><?= escape_html($error) ?></p><?php endif; ?>
        <form method="post" action="change_password.php" class="mt-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <label class="block text-sm font-semibold">Current password
                <input class="mt-1 w-full rounded-lg border border-[#E5D8C3] bg-white px-3 py-3" type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <label class="block text-sm font-semibold">New password
                <input class="mt-1 w-full rounded-lg border border-[#E5D8C3] bg-white px-3 py-3" type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required>
            </label>
            <label class="block text-sm font-semibold">Confirm new password
                <input class="mt-1 w-full rounded-lg border border-[#E5D8C3] bg-white px-3 py-3" type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required>
            </label>
            <div class="flex flex-wrap gap-3 pt-2">
                <button class="btn-primary" type="submit">Change password</button>
                <a class="btn-secondary" href="<?= escape_html(app_base_path()) ?>/<?= $role === 'admin' ? 'admin/dashboard.php?tab=overview' : 'staff/offline_pos.php' ?>">Cancel</a>
            </div>
        </form>
    </section>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
