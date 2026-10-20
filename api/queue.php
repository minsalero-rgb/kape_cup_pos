<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo '{"success":false,"error":{"code":"method_not_allowed","message":"Only GET requests are allowed."}}';
    exit;
}

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/order_workflow.php';
require_once __DIR__ . '/../includes/order_api.php';

try {
    $user = order_api_require_staff_or_admin($pdo);
    if ($user['role'] === 'staff') {
        order_api_require_one_active_shift($pdo, (int) $user['staff_id']);
    }

    order_api_response(200, [
        'success' => true,
        'data' => [
            'orders' => fetch_active_order_queue($pdo),
        ],
    ]);
} catch (PDOException $exception) {
    error_log('[staff-order-queue-api] Queue query failed.');
    order_api_error(500, 'internal_error', 'The order queue is temporarily unavailable.');
} catch (Throwable $exception) {
    error_log('[staff-order-queue-api] Unexpected queue failure.');
    order_api_error(500, 'internal_error', 'The order queue is temporarily unavailable.');
}
