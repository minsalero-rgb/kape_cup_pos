<?php
declare(strict_types=1);

function order_tracking_secret(): ?string
{
    $secret = getenv('ORDER_TRACKING_SECRET');
    if (!is_string($secret) || strlen($secret) < 32) {
        return null;
    }

    return $secret;
}

function order_tracking_token(int $orderId, string $secret): string
{
    return hash_hmac('sha256', 'mekape-order-status:v1:' . $orderId, $secret);
}

function order_tracking_reference(): string
{
    return str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
}

function normalize_order_tracking_reference(string $reference): ?string
{
    $reference = trim($reference);

    return preg_match('/\A(?:[0-9]{8}|[A-Za-z0-9_-]{27})\z/', $reference) === 1
        ? $reference
        : null;
}

function create_order_tracking_poll_token(string $reference): string
{
    $now = time();
    $tokens = isset($_SESSION['order_tracking_poll_tokens']) &&
        is_array($_SESSION['order_tracking_poll_tokens'])
        ? $_SESSION['order_tracking_poll_tokens']
        : [];

    foreach ($tokens as $tokenHash => $tokenData) {
        if (
            !is_array($tokenData)
            || !isset($tokenData['expires_at'])
            || !is_int($tokenData['expires_at'])
            || $tokenData['expires_at'] <= $now
        ) {
            unset($tokens[$tokenHash]);
        }
    }

    $token = bin2hex(random_bytes(32));
    $tokens[hash('sha256', $token)] = [
        'reference' => $reference,
        'expires_at' => $now + 1800,
        'last_polled_at' => 0.0,
    ];

    while (count($tokens) > 10) {
        array_shift($tokens);
    }

    $_SESSION['order_tracking_poll_tokens'] = $tokens;

    return $token;
}

function order_tracking_reference_for_poll_token(string $token): ?string
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
        return null;
    }

    if (
        !isset($_SESSION['order_tracking_poll_tokens'])
        || !is_array($_SESSION['order_tracking_poll_tokens'])
    ) {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    if (!isset($_SESSION['order_tracking_poll_tokens'][$tokenHash])) {
        return null;
    }

    $tokenData = $_SESSION['order_tracking_poll_tokens'][$tokenHash];
    if (
        !is_array($tokenData)
        || !isset($tokenData['reference'], $tokenData['expires_at'])
        || !is_string($tokenData['reference'])
        || !is_int($tokenData['expires_at'])
        || $tokenData['expires_at'] <= time()
    ) {
        unset($_SESSION['order_tracking_poll_tokens'][$tokenHash]);
        return null;
    }

    return normalize_order_tracking_reference($tokenData['reference']) === $tokenData['reference']
        ? $tokenData['reference']
        : null;
}

function order_tracking_poll_request_allowed(string $token): bool
{
    if (
        preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1
        || !isset($_SESSION['order_tracking_poll_tokens'])
        || !is_array($_SESSION['order_tracking_poll_tokens'])
    ) {
        return false;
    }

    $tokenHash = hash('sha256', $token);
    if (!isset($_SESSION['order_tracking_poll_tokens'][$tokenHash])) {
        return false;
    }

    $tokenData = $_SESSION['order_tracking_poll_tokens'][$tokenHash];
    if (
        !is_array($tokenData)
        || !isset($tokenData['expires_at'])
        || !is_int($tokenData['expires_at'])
        || $tokenData['expires_at'] <= time()
    ) {
        return false;
    }

    $lastPolledAt = isset($tokenData['last_polled_at'])
        ? $tokenData['last_polled_at']
        : 0.0;
    if (!is_int($lastPolledAt) && !is_float($lastPolledAt)) {
        return false;
    }

    $now = microtime(true);
    if ($lastPolledAt > 0 && $now - $lastPolledAt < 4.0) {
        return false;
    }

    $_SESSION['order_tracking_poll_tokens'][$tokenHash]['last_polled_at'] = $now;

    return true;
}

function insert_order_with_tracking_reference(PDO $pdo, string $sql, array $parameters): array
{
    $statement = $pdo->prepare($sql);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $reference = order_tracking_reference();
        $insertParameters = $parameters;
        $insertParameters['order_reference'] = $reference;

        try {
            $statement->execute($insertParameters);

            return [
                'order_id' => (int) $pdo->lastInsertId(),
                'order_reference' => $reference,
            ];
        } catch (PDOException $exception) {
            $errorInfo = $exception->errorInfo;
            if (!is_array($errorInfo) || !isset($errorInfo[1]) || (int) $errorInfo[1] !== 1062) {
                throw $exception;
            }

            $collisionStatement = $pdo->prepare(
                'SELECT 1
                 FROM orders
                 WHERE order_reference = :order_reference
                 LIMIT 1
                 FOR UPDATE'
            );
            $collisionStatement->execute(['order_reference' => $reference]);
            if (!$collisionStatement->fetchColumn()) {
                throw $exception;
            }
        }
    }

    throw new RuntimeException('A unique order tracking reference could not be generated.');
}
