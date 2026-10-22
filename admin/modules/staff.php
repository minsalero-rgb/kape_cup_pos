<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit; }

$error = '';
$formValues = ['full_name' => '', 'username' => '', 'role' => 'staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section']) && is_string($_POST['section']) && $_POST['section'] === 'staff') {
    require_post_request();
    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : null;
    if (!csrf_token_is_valid($token)) {
        http_response_code(403);
        $error = 'This form expired. Refresh the page and try again.';
    } else {
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'create') {
            $fullName = isset($_POST['full_name']) && is_string($_POST['full_name']) ? trim($_POST['full_name']) : '';
            $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
            $role = isset($_POST['role']) && is_string($_POST['role']) ? $_POST['role'] : '';
            $fullNameLength = preg_match_all('/./us', $fullName, $matches);
            $usernameLength = preg_match_all('/./us', $username, $matches);
            $formValues = ['full_name' => $fullName, 'username' => $username, 'role' => $role];

            if ($fullName === '' || $fullNameLength === false || $fullNameLength > 120 || preg_match('/[\x00-\x1F\x7F]/', $fullName) === 1) {
                $error = 'Enter a name of no more than 120 characters.';
            } elseif ($username === '' || $usernameLength === false || $usernameLength > 80 || preg_match('/[\x00-\x20\x7F]/', $username) === 1) {
                $error = 'Enter a username of no more than 80 characters without spaces.';
            } elseif (!in_array($role, ['staff', 'admin'], true)) {
                $error = 'Choose Staff or Admin as the account role.';
            } elseif (($passwordError = validate_new_password($password)) !== null) {
                $error = $passwordError;
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                unset($password);
                if (!is_string($passwordHash)) {
                    $error = 'The password could not be secured. Please try again.';
                } else {
                    try {
                        $insert = $pdo->prepare(
                            'INSERT INTO staff (full_name, username, password_hash, role, is_active)
                             VALUES (:full_name, :username, :password_hash, :role, 1)'
                        );
                        $insert->execute([
                            'full_name' => $fullName,
                            'username' => $username,
                            'password_hash' => $passwordHash,
                            'role' => $role,
                        ]);
                        unset($passwordHash);
                        admin_redirect('staff', 'success=created');
                        exit;
                    } catch (PDOException $exception) {
                        unset($passwordHash);
                        if (isset($exception->errorInfo[1]) && (int) $exception->errorInfo[1] === 1062) {
                            $error = 'That username is already in use.';
                        } else {
                            error_log('[admin-staff-accounts] Account creation failed.');
                            http_response_code(500);
                            $error = 'The account could not be created. Please try again.';
                        }
                    }
                }
            }
            unset($password);
        } elseif ($action === 'reset_password') {
            $staffIdInput = isset($_POST['staff_id']) && is_string($_POST['staff_id']) ? $_POST['staff_id'] : '';
            $staffId = filter_var($staffIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]);
            $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
            $passwordError = validate_new_password($password);

            if ($staffId === false) {
                $error = 'Choose a valid account.';
            } elseif ((int) $staffId === $admin['staff_id']) {
                $error = 'Use Change password to update your own password.';
            } elseif ($passwordError !== null) {
                $error = $passwordError;
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                unset($password);
                if (!is_string($passwordHash)) {
                    $error = 'The password could not be secured. Please try again.';
                } else {
                    try {
                        $resetStatement = $pdo->prepare(
                            'UPDATE staff
                             SET password_hash = :password_hash
                             WHERE staff_id = :staff_id'
                        );
                        $resetStatement->execute([
                            'password_hash' => $passwordHash,
                            'staff_id' => (int) $staffId,
                        ]);
                        if ($resetStatement->rowCount() !== 1) {
                            $error = 'That account no longer exists.';
                        } else {
                            admin_redirect('staff', 'success=password_reset');
                            exit;
                        }
                    } catch (PDOException $exception) {
                        error_log('[admin-staff-accounts] Password reset failed.');
                        http_response_code(500);
                        $error = 'The account password could not be reset. Please try again.';
                    } finally {
                        unset($passwordHash);
                    }
                }
            }
            unset($password);
        } elseif ($action === 'set_active') {
            $staffIdInput = isset($_POST['staff_id']) && is_string($_POST['staff_id']) ? $_POST['staff_id'] : '';
            $staffId = filter_var($staffIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]);
            $activeInput = isset($_POST['is_active']) && is_string($_POST['is_active']) ? $_POST['is_active'] : '';

            if ($staffId === false || !in_array($activeInput, ['0', '1'], true)) {
                $error = 'Choose a valid account and status.';
            } elseif ((int) $staffId === $admin['staff_id'] && $activeInput === '0') {
                $error = 'You cannot deactivate the account you are currently using.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $activeAdmins = $pdo->query(
                        "SELECT staff_id
                         FROM staff
                         WHERE role = 'admin'
                           AND is_active = 1
                         FOR UPDATE"
                    )->fetchAll(PDO::FETCH_COLUMN);
                    $targetStatement = $pdo->prepare('SELECT staff_id, role, is_active FROM staff WHERE staff_id = :staff_id FOR UPDATE');
                    $targetStatement->execute(['staff_id' => (int) $staffId]);
                    $target = $targetStatement->fetch(PDO::FETCH_ASSOC);

                    if (!$target) {
                        $pdo->rollBack();
                        $error = 'That account no longer exists.';
                    } elseif ($target['role'] === 'admin' && (int) $target['is_active'] === 1 && $activeInput === '0') {
                        if (count($activeAdmins) <= 1) {
                            $pdo->rollBack();
                            $error = 'The last active Admin account cannot be deactivated.';
                        }
                    }

                    if ($error === '') {
                        $update = $pdo->prepare('UPDATE staff SET is_active = :is_active WHERE staff_id = :staff_id');
                        $update->execute(['is_active' => (int) $activeInput, 'staff_id' => (int) $staffId]);
                        $pdo->commit();
                        admin_redirect('staff', 'success=' . ($activeInput === '1' ? 'reactivated' : 'deactivated'));
                        exit;
                    }
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('[admin-staff-accounts] Account status update failed.');
                    http_response_code(500);
                    $error = 'The account status could not be changed. Please try again.';
                }
            }
        } else {
            $error = 'Choose a valid account action.';
        }
    }
}

$successMessages = [
    'created' => 'Account created.',
    'password_reset' => 'Account password reset.',
    'deactivated' => 'Account deactivated. Its history is retained.',
    'reactivated' => 'Account reactivated.',
];
$successKey = isset($_GET['success']) && is_string($_GET['success']) ? $_GET['success'] : '';
$success = $activeTab === 'staff' && isset($successMessages[$successKey]) ? $successMessages[$successKey] : '';
$accounts = $pdo->query(
    'SELECT staff_id, full_name, username, role, is_active, created_at
     FROM staff
     ORDER BY role ASC, full_name ASC, staff_id ASC'
)->fetchAll(PDO::FETCH_ASSOC);
$activeAccountCount = count(array_filter(
    $accounts,
    static fn (array $account): bool => (int) $account['is_active'] === 1
));
$inactiveAccountCount = count($accounts) - $activeAccountCount;
?>
<div class="admin-staff-page">
    <div class="admin-staff-intro">
        <p>Signed in as <strong><?= escape_html($admin['full_name']) ?></strong>. Account records are retained; deactivate access instead of deleting users.</p>
        <div class="admin-staff-summary" aria-label="Account totals">
            <div><strong><?= count($accounts) ?></strong><span>Total accounts</span></div>
            <div><strong><?= $activeAccountCount ?></strong><span>Active</span></div>
            <div><strong><?= $inactiveAccountCount ?></strong><span>Inactive</span></div>
        </div>
    </div>
    <?php if ($success !== ''): ?><p class="admin-staff-alert is-success" role="status"><?= escape_html($success) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="admin-staff-alert is-error" role="alert"><?= escape_html($error) ?></p><?php endif; ?>
    <section class="admin-staff-card" aria-labelledby="create-account-heading">
        <div class="admin-staff-card-heading">
            <div><span>TEAM ACCESS</span><h2 id="create-account-heading">Create account</h2></div>
            <span class="material-symbols-outlined" aria-hidden="true">person_add</span>
        </div>
        <form method="post" action="dashboard.php?tab=staff" class="admin-staff-create-form">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <input type="hidden" name="section" value="staff">
            <input type="hidden" name="action" value="create">
            <label>Full name<input name="full_name" maxlength="120" required value="<?= escape_html($formValues['full_name']) ?>"></label>
            <label>Username<input name="username" maxlength="80" autocomplete="off" required value="<?= escape_html($formValues['username']) ?>"></label>
            <label>Initial password<input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required><small>At least 12 characters; maximum 72 UTF-8 bytes.</small></label>
            <label>Role<select name="role"><option value="staff" <?= $formValues['role'] === 'staff' ? 'selected' : '' ?>>Staff</option><option value="admin" <?= $formValues['role'] === 'admin' ? 'selected' : '' ?>>Admin</option></select></label>
            <div class="admin-staff-form-submit"><button type="submit"><span class="material-symbols-outlined" aria-hidden="true">add</span>Create account</button></div>
        </form>
    </section>
    <section class="admin-staff-card" aria-labelledby="staff-accounts-heading">
        <div class="admin-staff-card-heading admin-staff-list-heading">
            <div><span>PEOPLE &amp; PERMISSIONS</span><h2 id="staff-accounts-heading">Accounts</h2></div>
            <span class="admin-staff-list-total"><?= count($accounts) ?> <?= count($accounts) === 1 ? 'account' : 'accounts' ?></span>
        </div>
        <div class="admin-staff-table-wrap">
            <table class="admin-staff-table">
                <thead><tr><th scope="col">Name / username</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Created</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <?php $accountIsActive = (int) $account['is_active'] === 1; ?>
                    <tr>
                        <td class="admin-staff-identity"><strong><?= escape_html($account['full_name']) ?></strong><span><?= escape_html($account['username']) ?></span></td>
                        <td><span class="admin-staff-role"><?= escape_html(ucfirst($account['role'])) ?></span></td>
                        <td><span class="admin-staff-status<?= $accountIsActive ? ' is-active' : ' is-inactive' ?>"><span></span><?= $accountIsActive ? 'Active' : 'Inactive' ?></span></td>
                        <td class="admin-staff-created"><?= escape_html(date('M j, Y', strtotime($account['created_at']))) ?></td>
                        <td class="admin-staff-actions">
                            <?php if ((int) $account['staff_id'] !== (int) $admin['staff_id']): ?>
                                <form method="post" action="dashboard.php?tab=staff" class="admin-staff-reset-form">
                                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                    <input type="hidden" name="section" value="staff">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="staff_id" value="<?= (int) $account['staff_id'] ?>">
                                    <label class="admin-staff-password-label" for="staff-password-<?= (int) $account['staff_id'] ?>">New password</label>
                                    <div class="admin-staff-reset-controls">
                                        <input id="staff-password-<?= (int) $account['staff_id'] ?>" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" placeholder="12+ characters" required>
                                        <button class="admin-staff-button is-secondary" type="submit">Reset</button>
                                    </div>
                                </form>
                                <form method="post" action="dashboard.php?tab=staff" data-confirm="<?= $accountIsActive ? 'Deactivate access for ' . escape_html($account['full_name']) . '? The account and its history will be retained.' : 'Reactivate access for ' . escape_html($account['full_name']) . '?' ?>">
                                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                    <input type="hidden" name="section" value="staff">
                                    <input type="hidden" name="action" value="set_active">
                                    <input type="hidden" name="staff_id" value="<?= (int) $account['staff_id'] ?>">
                                    <input type="hidden" name="is_active" value="<?= $accountIsActive ? '0' : '1' ?>">
                                    <button class="admin-staff-button <?= $accountIsActive ? 'is-danger' : 'is-secondary' ?>" type="submit"><?= $accountIsActive ? 'Deactivate' : 'Reactivate' ?></button>
                                </form>
                            <?php else: ?><span class="admin-staff-current">Current account</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($accounts === []): ?>
                    <tr><td class="admin-staff-empty" colspan="5"><span class="material-symbols-outlined" aria-hidden="true">group</span><strong>No accounts yet</strong><span>Create an account above to get started.</span></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
