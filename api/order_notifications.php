<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo '{"success":false,"error":{"code":"method_not_allowed","message":"Only GET requests are allowed."}}';
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/order_workflow.php';
require_once __DIR__ . '/../includes/order_api.php';

if (!staff_is_authenticated()) {
    order_api_error(401, 'authentication_required', 'Sign in to access order notifications.');
}

try {
    $user = order_api_require_staff_or_admin($pdo);
    if ($user['role'] === 'staff') {
        order_api_require_one_active_shift($pdo, $user['staff_id']);
    }

    $afterOrderId = isset($_GET['after']) && is_string($_GET['after'])
        ? $_GET['after']
        : '';
    if (preg_match('/^(0|[1-9][0-9]{0,19})$/D', $afterOrderId) !== 1
        || (strlen($afterOrderId) === 20
            && strcmp($afterOrderId, '18446744073709551615') > 0)) {
        order_api_error(422, 'invalid_cursor', 'Provide a valid order notification cursor.');
    }

    $statement = $pdo->prepare(
        "SELECT order_id
         FROM orders
         WHERE order_id > :after_order_id
           AND status IN ('Pending', 'Paid', 'Preparing')
         ORDER BY order_id ASC
         LIMIT 25"
    );
    $statement->bindValue(':after_order_id', $afterOrderId, PDO::PARAM_STR);
    $statement->execute();
    $orderIds = array_map(
        static function ($orderId): string {
            return (string) $orderId;
        },
        $statement->fetchAll(PDO::FETCH_COLUMN)
    );
    $nextCursor = $orderIds === []
        ? $afterOrderId
        : $orderIds[count($orderIds) - 1];

    order_api_response(200, [
        'success' => true,
        'data' => [
            'order_ids' => $orderIds,
            'next_cursor' => $nextCursor,
        ],
    ]);
} catch (PDOException $exception) {
    error_log('[staff-order-notifications-api] Order notification query failed.');
    order_api_error(500, 'internal_error', 'Order notifications are temporarily unavailable.');
} catch (Throwable $exception) {
    error_log('[staff-order-notifications-api] Unexpected order notification failure.');
    order_api_error(500, 'internal_error', 'Order notifications are temporarily unavailable.');
}
