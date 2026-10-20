<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

ob_start(static function ($output) {
    if (http_response_code() >= 500) {
        json_decode($output);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $output;
        }

        return '{"success":false,"error":{"code":"internal_error","message":"Order status is temporarily unavailable."}}';
    }

    return $output;
});

function order_status_json(int $statusCode, array $body): void
{
    http_response_code($statusCode);
    try {
        echo json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        http_response_code(500);
        echo '{"success":false,"error":{"code":"internal_error","message":"Order status is temporarily unavailable."}}';
    }
    exit;
}

function order_status_error(int $statusCode, string $code, string $message): void
{
    order_status_json($statusCode, [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ]);
}

$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
if ($requestMethod !== 'GET') {
    header('Allow: GET');
    order_status_error(405, 'method_not_allowed', 'Only GET requests are allowed.');
}

$rawOrderId = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
$orderId = filter_var($rawOrderId, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
]);
$trackingToken = isset($_SERVER['HTTP_X_ORDER_TRACKING_TOKEN']) &&
    is_string($_SERVER['HTTP_X_ORDER_TRACKING_TOKEN'])
    ? $_SERVER['HTTP_X_ORDER_TRACKING_TOKEN']
    : '';

if ($orderId === false || preg_match('/^[a-f0-9]{64}$/', $trackingToken) !== 1) {
    order_status_error(404, 'order_not_found', 'The order could not be found.');
}

require __DIR__ . '/../includes/order_tracking.php';
$secret = order_tracking_secret();
if ($secret === null) {
    error_log('[order-status] Tracking secret is not configured.');
    order_status_error(503, 'tracking_unavailable', 'Order status is temporarily unavailable.');
}

$expectedToken = order_tracking_token((int) $orderId, $secret);
if (!hash_equals($expectedToken, $trackingToken)) {
    order_status_error(404, 'order_not_found', 'The order could not be found.');
}

try {
    require __DIR__ . '/../config/db.php';

    $statement = $pdo->prepare(
        'SELECT order_id, status, created_at
         FROM orders
         WHERE order_id = :order_id
         LIMIT 1'
    );
    $statement->bindValue(':order_id', (int) $orderId, PDO::PARAM_INT);
    $statement->execute();
    $order = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        order_status_error(404, 'order_not_found', 'The order could not be found.');
    }

    if (!in_array($order['status'], ['Pending', 'Paid', 'Preparing', 'Completed'], true)) {
        error_log('[order-status] Stored order status is outside the approved workflow.');
        order_status_error(500, 'internal_error', 'Order status is temporarily unavailable.');
    }

    order_status_json(200, [
        'success' => true,
        'data' => [
            'order_id' => (int) $order['order_id'],
            'status' => (string) $order['status'],
            'created_at' => (string) $order['created_at'],
        ],
    ]);
} catch (PDOException $exception) {
    error_log('[order-status] Status lookup failed.');
    order_status_error(500, 'internal_error', 'Order status is temporarily unavailable.');
} catch (Throwable $exception) {
    error_log('[order-status] Unexpected status lookup failure.');
    order_status_error(500, 'internal_error', 'Order status is temporarily unavailable.');
}
