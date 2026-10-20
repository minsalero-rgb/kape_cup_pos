<?php
declare(strict_types=1);

function find_order_submission(PDO $pdo, string $sessionKey, string $requestKey): ?array
{
    $statement = $pdo->prepare(
        'SELECT orders.order_id, orders.total_amount, requests.cart_hash
         FROM order_submission_requests AS requests
         INNER JOIN orders ON orders.order_id = requests.order_id
         WHERE requests.session_key = :session_key
           AND requests.request_key = :request_key
         LIMIT 1'
    );
    $statement->execute([
        'session_key' => $sessionKey,
        'request_key' => $requestKey,
    ]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);

    return $order ?: null;
}

function reserve_order_submission(PDO $pdo, string $sessionKey, string $requestKey, string $cartHash): void
{
    $statement = $pdo->prepare(
        'INSERT INTO order_submission_requests (session_key, request_key, cart_hash)
         VALUES (:session_key, :request_key, :cart_hash)'
    );
    $statement->execute([
        'session_key' => $sessionKey,
        'request_key' => $requestKey,
        'cart_hash' => $cartHash,
    ]);
}

function complete_order_submission(PDO $pdo, string $sessionKey, string $requestKey, int $orderId): void
{
    $statement = $pdo->prepare(
        'UPDATE order_submission_requests
         SET order_id = :order_id
         WHERE session_key = :session_key
           AND request_key = :request_key
           AND order_id IS NULL'
    );
    $statement->execute([
        'order_id' => $orderId,
        'session_key' => $sessionKey,
        'request_key' => $requestKey,
    ]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('The order submission key could not be finalized.');
    }
}
