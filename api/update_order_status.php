<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/order_workflow.php';
require_once __DIR__ . '/../includes/order_api.php';

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    order_api_error(405, 'method_not_allowed', 'Only POST requests are allowed.');
}

try {
    $user = order_api_require_staff_or_admin($pdo);

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 4096) {
        order_api_error(413, 'request_too_large', 'The order update request is too large.');
    }
    $contentType = isset($_SERVER['CONTENT_TYPE'])
        ? strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0]))
        : '';
    if ($contentType !== 'application/json') {
        order_api_error(415, 'unsupported_media_type', 'Submit the order update as JSON.');
    }

    $csrfToken = isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])
        ? $_SERVER['HTTP_X_CSRF_TOKEN']
        : '';
    if (!csrf_token_is_valid($csrfToken)) {
        order_api_error(403, 'csrf_failed', 'Refresh the order page and try again.');
    }

    if ($user['role'] === 'staff') {
        order_api_require_one_active_shift($pdo, $user['staff_id']);
    }

    $rawBody = file_get_contents('php://input', false, null, 0, 4097);
    if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 4096) {
        order_api_error(400, 'invalid_json', 'The order update request is empty or too large.');
    }
    $input = parse_order_advance_payload($rawBody);
    if (!$input['success'] && $input['code'] === 'invalid_json') {
        order_api_error(400, 'invalid_json', 'The order update request must contain valid JSON.');
    }
    if (!$input['success']) {
        order_api_error(422, 'invalid_order_update', 'Provide a valid order ID and current status.');
    }

    $result = $user['role'] === 'admin'
        ? advance_order_status_as_admin($pdo, $input['order_id'], $input['expected_status'])
        : advance_order_status($pdo, $user['staff_id'], $input['order_id'], $input['expected_status']);
    if (!$result['success']) {
        $errors = [
            'invalid_transition' => [409, 'invalid_transition', 'Only the next forward status transition is allowed.'],
            'active_shift_required' => [409, 'active_shift_required', 'Exactly one active Staff shift is required for Staff order updates.'],
            'order_not_found' => [404, 'order_not_found', 'The order could not be found.'],
            'status_conflict' => [409, 'status_conflict', 'The order status changed. Refresh and try again.'],
        ];
        $error = isset($errors[$result['code']])
            ? $errors[$result['code']]
            : [409, 'invalid_transition', 'The order could not be advanced.'];
        order_api_error($error[0], $error[1], $error[2]);
    }

    order_api_response(200, [
        'success' => true,
        'data' => [
            'order_id' => $input['order_id'],
            'status' => $result['status'],
        ],
    ]);
} catch (PDOException $exception) {
    error_log('[order-status-update-api] Status update failed.');
    order_api_error(500, 'internal_error', 'The order could not be updated.');
} catch (Throwable $exception) {
    error_log('[order-status-update-api] Unexpected status update failure.');
    order_api_error(500, 'internal_error', 'The order could not be updated.');
}