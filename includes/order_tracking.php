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
