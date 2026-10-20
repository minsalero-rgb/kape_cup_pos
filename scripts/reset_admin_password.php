<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../includes/password_rules.php';

$username = isset($argv[1]) && is_string($argv[1]) ? trim($argv[1]) : '';
if ($username === '') {
    fwrite(STDOUT, 'Admin username to reset: ');
    $usernameInput = fgets(STDIN);
    $username = $usernameInput === false ? '' : trim($usernameInput);
}
$password = getenv('ADMIN_RESET_PASSWORD');
putenv('ADMIN_RESET_PASSWORD');

$usernameLength = preg_match_all('/./us', $username);
if (
    $username === ''
    || $usernameLength === false
    || $usernameLength > 80
    || preg_match('/[\x00-\x20\x7F]/', $username) === 1
) {
    unset($password);
    fwrite(STDERR, "Enter a valid Admin username with no whitespace or control characters.\n");
    exit(1);
}

if (!is_string($password) || validate_new_password($password) !== null) {
    unset($password);
    fwrite(STDERR, "ADMIN_RESET_PASSWORD must be set and meet the password length requirements.\n");
    exit(1);
}

require __DIR__ . '/../config/db.php';

$lockName = 'kape_pos_admin_password_reset_' . substr(
    hash('sha256', (string) $dbname . ':' . $username),
    0,
    32
);
$lockAcquired = false;
$exitCode = 0;
$resultMessage = '';

try {
    $lockStatement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
    $lockStatement->execute(['lock_name' => $lockName]);
    if ((int) $lockStatement->fetchColumn() !== 1) {
        throw new RuntimeException('Could not acquire the password reset lock.');
    }
    $lockAcquired = true;

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    unset($password);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Password hashing failed.');
    }

    $pdo->beginTransaction();
    $updateStatement = $pdo->prepare(
        'UPDATE staff
         SET password_hash = :password_hash
         WHERE username = :username
           AND role = :role
           AND is_active = 1'
    );
    $updateStatement->execute([
        'password_hash' => $passwordHash,
        'username' => $username,
        'role' => 'admin',
    ]);
    unset($passwordHash);

    if ($updateStatement->rowCount() !== 1) {
        $pdo->rollBack();
        $exitCode = 1;
        $resultMessage = "No active Admin account matched that username. No password was changed.\n";
    } else {
        $pdo->commit();
        $resultMessage = "The Admin password was reset successfully. Sign in with the new password.\n";
    }
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    unset($password, $passwordHash);
    putenv('ADMIN_RESET_PASSWORD');
    error_log('[admin-password-reset] Password reset database operation failed.');
    $exitCode = 1;
    $resultMessage = "The Admin password could not be reset. Check the database connection before retrying.\n";
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    unset($password, $passwordHash);
    putenv('ADMIN_RESET_PASSWORD');
    $exitCode = 1;
    $resultMessage = $exception->getMessage() . " No password was changed.\n";
} finally {
    if ($lockAcquired) {
        try {
            $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $releaseStatement->execute(['lock_name' => $lockName]);
            if ((int) $releaseStatement->fetchColumn() !== 1) {
                error_log('[admin-password-reset] Password reset lock release failed.');
                $exitCode = 1;
                $resultMessage = "The password reset may have completed, but the database lock could not be released cleanly. Check the Admin login before retrying.\n";
            }
        } catch (PDOException $exception) {
            error_log('[admin-password-reset] Password reset lock release failed.');
            $exitCode = 1;
            $resultMessage = "The password reset may have completed, but the database lock could not be released cleanly. Check the Admin login before retrying.\n";
        }
    }
}

fwrite($exitCode === 0 ? STDOUT : STDERR, $resultMessage);
exit($exitCode);
