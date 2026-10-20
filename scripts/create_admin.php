<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../includes/password_rules.php';

function prompt_for_value(string $prompt): ?string
{
    fwrite(STDOUT, $prompt);
    $value = fgets(STDIN);

    return $value === false ? null : trim($value);
}

function unicode_character_count(string $value): ?int
{
    $matched = preg_match_all('/./us', $value, $matches);

    return $matched === false ? null : $matched;
}

$fullName = prompt_for_value('Admin full name: ');
$username = prompt_for_value('Admin username: ');
$password = getenv('INITIAL_ADMIN_PASSWORD');
putenv('INITIAL_ADMIN_PASSWORD');

if ($fullName === null || $username === null) {
    fwrite(STDERR, "Unable to read the Admin name and username from standard input.\n");
    exit(1);
}

$fullName = trim($fullName);
$username = trim($username);
$fullNameLength = unicode_character_count($fullName);
$usernameLength = unicode_character_count($username);

if (
    $fullName === '' ||
    $fullNameLength === null ||
    $fullNameLength > 120 ||
    preg_match('/[\x00-\x1F\x7F]/', $fullName) === 1
) {
    fwrite(STDERR, "The Admin full name is required and must be no more than 120 characters.\n");
    exit(1);
}

if (
    $username === '' ||
    $usernameLength === null ||
    $usernameLength > 80 ||
    preg_match('/[\x00-\x20\x7F]/', $username) === 1
) {
    fwrite(STDERR, "The Admin username is required, must be no more than 80 characters, and cannot contain whitespace or control characters.\n");
    exit(1);
}

if (!is_string($password) || validate_new_password($password) !== null) {
    if (is_string($password)) {
        unset($password);
    }
    putenv('INITIAL_ADMIN_PASSWORD');
    fwrite(STDERR, "INITIAL_ADMIN_PASSWORD must be valid and meet the password length requirements.\n");
    exit(1);
}

require __DIR__ . '/../config/db.php';

$lockName = 'kape_pos_initial_admin_' . substr(hash('sha256', (string) $dbname), 0, 32);
$lockAcquired = false;
$accountCreated = false;
$exitCode = 0;
$resultMessage = '';

try {
    $lockStatement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
    $lockStatement->bindValue(':lock_name', $lockName, PDO::PARAM_STR);
    $lockStatement->execute();

    if ((int) $lockStatement->fetchColumn() !== 1) {
        $exitCode = 1;
        $resultMessage = "Could not acquire the initial Admin setup lock. No account was created.\n";
    } else {
        $lockAcquired = true;

        $adminStatement = $pdo->prepare(
            'SELECT 1
             FROM staff
             WHERE role = :role
             LIMIT 1'
        );
        $adminStatement->bindValue(':role', 'admin', PDO::PARAM_STR);
        $adminStatement->execute();

        if ($adminStatement->fetchColumn() !== false) {
            $exitCode = 1;
            $resultMessage = "An Admin account already exists. No account was created.\n";
        } else {
            $usernameStatement = $pdo->prepare(
                'SELECT 1
                 FROM staff
                 WHERE username = :username
                 LIMIT 1'
            );
            $usernameStatement->bindValue(':username', $username, PDO::PARAM_STR);
            $usernameStatement->execute();

            if ($usernameStatement->fetchColumn() !== false) {
                $exitCode = 1;
                $resultMessage = "That username is already in use. No account was created.\n";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                unset($password);
                putenv('INITIAL_ADMIN_PASSWORD');

                if (!is_string($passwordHash)) {
                    $exitCode = 1;
                    $resultMessage = "Password hashing failed. No account was created.\n";
                } else {
                    $pdo->beginTransaction();
                    $insertStatement = $pdo->prepare(
                        'INSERT INTO staff (full_name, username, password_hash, role, is_active)
                         VALUES (:full_name, :username, :password_hash, :role, :is_active)'
                    );
                    $insertStatement->bindValue(':full_name', $fullName, PDO::PARAM_STR);
                    $insertStatement->bindValue(':username', $username, PDO::PARAM_STR);
                    $insertStatement->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
                    $insertStatement->bindValue(':role', 'admin', PDO::PARAM_STR);
                    $insertStatement->bindValue(':is_active', 1, PDO::PARAM_INT);
                    $insertStatement->execute();
                    unset($passwordHash);
                    $pdo->commit();

                    $accountCreated = true;
                    $resultMessage = "The initial Admin account was created successfully.\n";
                }
            }
        }
    }
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    unset($password, $passwordHash);
    putenv('INITIAL_ADMIN_PASSWORD');

    $errorInfo = $exception->errorInfo;
    if (isset($errorInfo[1]) && (int) $errorInfo[1] === 1062) {
        $resultMessage = "The username is already in use. No account was created.\n";
    } else {
        $resultMessage = "The initial Admin account could not be created. Check the database before retrying.\n";
    }
    $exitCode = 1;
} finally {
    if ($lockAcquired) {
        try {
            $releaseStatement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $releaseStatement->bindValue(':lock_name', $lockName, PDO::PARAM_STR);
            $releaseStatement->execute();

            if ((int) $releaseStatement->fetchColumn() !== 1) {
                $exitCode = 1;
                $resultMessage = $accountCreated
                    ? "The account may have been created, but the setup lock could not be released cleanly. Check the staff table before retrying.\n"
                    : "The setup lock could not be released cleanly. Check the database connection before retrying.\n";
            }
        } catch (PDOException $exception) {
            $exitCode = 1;
            $resultMessage = $accountCreated
                ? "The account may have been created, but the setup lock could not be released cleanly. Check the staff table before retrying.\n"
                : "The setup lock could not be released cleanly. Check the database connection before retrying.\n";
        }
    }
}

fwrite($exitCode === 0 ? STDOUT : STDERR, $resultMessage);
exit($exitCode);
