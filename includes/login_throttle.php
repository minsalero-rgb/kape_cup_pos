<?php
declare(strict_types=1);

define('LOGIN_WINDOW_MINUTES', 15);
define('LOGIN_MAX_FAILURES_PER_USERNAME', 5);
define('LOGIN_MAX_FAILURES_PER_IP', 20);

function login_username_key(string $username): string
{
    return hash('sha256', strtolower(trim($username)));
}

function login_ip_key(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function login_is_locked(PDO $pdo, string $username): bool
{
    $statement = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*)
             FROM login_attempts
             WHERE username_key = :username_key
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)
            ) AS username_failures,
            (SELECT COUNT(*)
             FROM login_attempts
             WHERE ip_key = :ip_key
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)
            ) AS ip_failures'
    );
    $statement->execute([
        'username_key' => login_username_key($username),
        'ip_key' => login_ip_key(),
    ]);
    $failures = $statement->fetch(PDO::FETCH_ASSOC);

    return (int) $failures['username_failures'] >= LOGIN_MAX_FAILURES_PER_USERNAME
        || (int) $failures['ip_failures'] >= LOGIN_MAX_FAILURES_PER_IP;
}

function login_record_failure(PDO $pdo, string $username): void
{
    $statement = $pdo->prepare(
        'INSERT INTO login_attempts (username_key, ip_key)
         VALUES (:username_key, :ip_key)'
    );
    $statement->execute([
        'username_key' => login_username_key($username),
        'ip_key' => login_ip_key(),
    ]);

    if (random_int(1, 20) === 1) {
        login_purge_old($pdo);
    }
}

function login_clear_failures(PDO $pdo, string $username): void
{
    $statement = $pdo->prepare(
        'DELETE FROM login_attempts
         WHERE username_key = :username_key'
    );
    $statement->execute([
        'username_key' => login_username_key($username),
    ]);
}

function login_purge_old(PDO $pdo): void
{
    $statement = $pdo->prepare(
        'DELETE FROM login_attempts
         WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
    $statement->execute();
}
