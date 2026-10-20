<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/../includes/customer_session.php';
initialize_customer_session();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_workflow.php';
require_once __DIR__ . '/../includes/order_api.php';
require_once __DIR__ . '/../includes/order_submission.php';
require_once __DIR__ . '/../includes/order_idempotency.php';
require_once __DIR__ . '/../includes/shift.php';
require_once __DIR__ . '/../includes/order_tracking.php';

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    order_api_error(405, 'method_not_allowed', 'Only POST requests are allowed.');
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($contentLength > 8192) {
    order_api_error(413, 'request_too_large', 'The order request is too large.');
}

$contentType = isset($_SERVER['CONTENT_TYPE'])
    ? strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0]))
    : '';
if ($contentType !== 'application/json') {
    order_api_error(415, 'unsupported_media_type', 'Submit the order as JSON.');
}

$csrfToken = isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])
    ? $_SERVER['HTTP_X_CSRF_TOKEN']
    : '';
if (!csrf_token_is_valid($csrfToken)) {
    order_api_error(403, 'csrf_failed', 'Your checkout session expired. Refresh checkout and try again.');
}

$rawBody = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 8192) {
    order_api_error(400, 'invalid_json', 'The order request is empty or too large.');
}

try {
    $input = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    order_api_error(400, 'invalid_json', 'The order request must contain valid JSON.');
}
if (!is_array($input)) {
    order_api_error(400, 'invalid_json', 'The order request must contain a JSON object.');
}

$customerName = isset($input['customer_name']) && is_string($input['customer_name'])
    ? trim($input['customer_name'])
    : '';
if ($customerName === '' || preg_match('/\A.{1,120}\z/us', $customerName) !== 1) {
    order_api_error(422, 'invalid_customer_name', 'Enter a customer name of no more than 120 characters.');
}

$orderType = isset($input['order_type']) && is_string($input['order_type'])
    ? $input['order_type']
    : '';
if (!in_array($orderType, ['Dine-In', 'Take-Out'], true)) {
    order_api_error(422, 'invalid_order_type', 'Choose Dine-In or Take-Out.');
}

$paymentMethod = isset($input['payment_method']) && is_string($input['payment_method'])
    ? $input['payment_method']
    : '';
if (!in_array($paymentMethod, ['Cash', 'GCash', 'Credit Card'], true)) {
    order_api_error(422, 'invalid_payment_method', 'Choose Cash, GCash, or Credit Card.');
}

$submissionKey = isset($input['submission_key']) && is_string($input['submission_key'])
    ? $input['submission_key']
    : '';
if (preg_match('/\A[a-f0-9]{64}\z/', $submissionKey) !== 1) {
    order_api_error(422, 'invalid_submission_key', 'Refresh checkout and try submitting your order again.');
}
$submissionSessionKey = hash('sha256', session_id());

$orderConfig = require __DIR__ . '/../config/order.php';
$tableLabels = isset($orderConfig['dine_in_table_labels']) && is_array($orderConfig['dine_in_table_labels'])
    ? $orderConfig['dine_in_table_labels']
    : [];
$tableLabels = array_values(array_filter($tableLabels, static function ($label): bool {
    return is_string($label) && $label !== '';
}));
$tableLabel = null;
if ($orderType === 'Dine-In') {
    $tableLabel = isset($input['table_label']) && is_string($input['table_label'])
        ? $input['table_label']
        : '';
    if ($tableLabels === [] || !in_array($tableLabel, $tableLabels, true)) {
        order_api_error(422, 'invalid_table_label', 'Choose a configured dine-in table label.');
    }
} elseif (isset($input['table_label']) && $input['table_label'] !== null && $input['table_label'] !== '') {
    order_api_error(422, 'invalid_table_label', 'Take-Out orders cannot include a table label.');
}

$cashTenderedCents = null;
if ($paymentMethod === 'Cash') {
    $cashTenderedCents = isset($input['cash_tendered'])
        ? order_money_to_cents($input['cash_tendered'])
        : null;
    if ($cashTenderedCents === null || $cashTenderedCents < 0) {
        order_api_error(422, 'invalid_cash_tendered', 'Enter a valid cash amount.');
    }
} elseif (isset($input['cash_tendered']) && $input['cash_tendered'] !== '') {
    order_api_error(422, 'invalid_cash_tendered', 'Cash tendered is only used for Cash payments.');
}

try {
    require __DIR__ . '/../config/db.php';

    $trackingSecret = order_tracking_secret();
    if ($trackingSecret === null) {
        error_log('[order-submit-api] Tracking secret is not configured.');
        order_api_error(503, 'tracking_unavailable', 'Order submission is temporarily unavailable because tracking is not configured.');
    }
    $existingOrder = find_order_submission($pdo, $submissionSessionKey, $submissionKey);
    if ($existingOrder !== null) {
        $orderId = (int) $existingOrder['order_id'];
        $currentCartHash = hash(
            'sha256',
            serialize(isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [])
        );
        if (hash_equals((string) $existingOrder['cart_hash'], $currentCartHash)) {
            $_SESSION['cart'] = [];
            $_SESSION['checkout_submission_cart_hash'] = hash('sha256', serialize([]));
            $_SESSION['checkout_submission_key'] = bin2hex(random_bytes(32));
        }
        $_SESSION['order_tracking'] = isset($_SESSION['order_tracking']) && is_array($_SESSION['order_tracking'])
            ? $_SESSION['order_tracking']
            : [];
        $_SESSION['order_tracking'][(string) $orderId] = order_tracking_token($orderId, $trackingSecret);
        order_api_response(200, [
            'success' => true,
            'data' => [
                'order_id' => $orderId,
                'total_amount' => (string) $existingOrder['total_amount'],
            ],
        ]);
    }
    if (!customer_ordering_is_enabled($pdo)) {
        order_api_error(409, 'ordering_closed', 'Ordering currently closed.');
    }
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart']) || $_SESSION['cart'] === []) {
        order_api_error(422, 'invalid_cart', 'Your cart is empty. Add an item before checkout.');
    }
    $customerCartHash = hash('sha256', serialize($_SESSION['cart']));

    $pdo->beginTransaction();
    reserve_order_submission($pdo, $submissionSessionKey, $submissionKey, $customerCartHash);
    $shiftStatement = $pdo->query(
        "SELECT shifts.shift_id, shifts.staff_id
         FROM staff_shifts AS shifts
         INNER JOIN staff
             ON staff.staff_id = shifts.staff_id
         WHERE shifts.logout_timestamp IS NULL
           AND staff.role = 'staff'
           AND staff.is_active = 1
         ORDER BY shifts.login_timestamp ASC, shifts.shift_id ASC
         LIMIT 1
         FOR UPDATE"
    );
    $shift = $shiftStatement->fetch(PDO::FETCH_ASSOC);
    if (!$shift) {
        $pdo->rollBack();
        order_api_error(409, 'ordering_closed', 'Ordering currently closed.');
    }

    $submissionIpKey = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $rateLimitStatement = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*)
             FROM orders
             WHERE status = :pending_status) AS pending_orders,
            (SELECT COUNT(*)
             FROM order_submission_log
             WHERE session_key = :session_key
               AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS session_orders,
            (SELECT COUNT(*)
             FROM order_submission_log
             WHERE ip_key = :ip_key
               AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS ip_orders'
    );
    $rateLimitStatement->execute([
        'pending_status' => 'Pending',
        'session_key' => $submissionSessionKey,
        'ip_key' => $submissionIpKey,
    ]);
    $rateLimits = $rateLimitStatement->fetch(PDO::FETCH_ASSOC);
    if ((int) $rateLimits['pending_orders'] >= $orderConfig['max_open_pending_orders']
        || (int) $rateLimits['session_orders'] >= $orderConfig['max_orders_per_session_per_10_min']
        || (int) $rateLimits['ip_orders'] >= $orderConfig['max_orders_per_ip_per_10_min']) {
        $pdo->rollBack();
        order_api_error(
            429,
            'rate_limited',
            'Too many orders are being placed right now. Please wait a few minutes or ask the counter for help.'
        );
    }

    $preparedCart = prepare_order_cart($pdo, $_SESSION['cart'], true);
    if ($paymentMethod === 'Cash' && $cashTenderedCents < $preparedCart['total_cents']) {
        $pdo->rollBack();
        order_api_error(422, 'insufficient_cash', 'Cash tendered must be at least the final order total.');
    }

    $changeCents = $paymentMethod === 'Cash'
        ? $cashTenderedCents - $preparedCart['total_cents']
        : null;
    $orderStatement = $pdo->prepare(
        'INSERT INTO orders
            (customer_name, order_type, table_label, payment_method, total_amount,
             cash_tendered, change_due, status, staff_id, shift_id)
         VALUES
            (:customer_name, :order_type, :table_label, :payment_method, :total_amount,
             :cash_tendered, :change_due, \'Pending\', :staff_id, :shift_id)'
    );
    $orderStatement->execute([
        'customer_name' => $customerName,
        'order_type' => $orderType,
        'table_label' => $tableLabel,
        'payment_method' => $paymentMethod,
        'total_amount' => $preparedCart['total_amount'],
        'cash_tendered' => $cashTenderedCents === null ? null : order_money_from_cents($cashTenderedCents),
        'change_due' => $changeCents === null ? null : order_money_from_cents($changeCents),
        'staff_id' => (int) $shift['staff_id'],
        'shift_id' => (int) $shift['shift_id'],
    ]    );
    $orderId = (int) $pdo->lastInsertId();

    insert_prepared_order_items($pdo, $orderId, $preparedCart);
    deduct_order_inventory($pdo, $preparedCart, (int) $shift['staff_id'], $orderId);
    complete_order_submission($pdo, $submissionSessionKey, $submissionKey, $orderId);
    $logStatement = $pdo->prepare(
        'INSERT INTO order_submission_log (ip_key, session_key)
         VALUES (:ip_key, :session_key)'
    );
    $logStatement->execute([
        'ip_key' => $submissionIpKey,
        'session_key' => $submissionSessionKey,
    ]);
    $pdo->commit();
    $_SESSION['cart'] = [];
    $_SESSION['checkout_submission_cart_hash'] = hash('sha256', serialize([]));
    $_SESSION['order_tracking'] = isset($_SESSION['order_tracking']) && is_array($_SESSION['order_tracking'])
        ? $_SESSION['order_tracking']
        : [];
    $_SESSION['order_tracking'][(string) $orderId] = order_tracking_token($orderId, $trackingSecret);
    $_SESSION['checkout_submission_key'] = bin2hex(random_bytes(32));

    if (random_int(1, 20) === 1) {
        try {
            $purgeStatement = $pdo->prepare(
                'DELETE FROM order_submission_log
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)'
            );
            $purgeStatement->execute();
        } catch (PDOException $exception) {
            error_log('[order-submit-api] Rate-limit log cleanup failed.');
        }
    }

    order_api_response(201, [
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'total_amount' => $preparedCart['total_amount'],
        ],
    ]);
} catch (OrderSubmissionException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $statusCode = in_array($exception->errorCode(), ['invalid_cart', 'invalid_modifier', 'invalid_table_label'], true)
        ? 422
        : 409;
    order_api_error($statusCode, $exception->errorCode(), $exception->getMessage());
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorInfo = $exception->errorInfo;
    if (is_array($errorInfo) && isset($errorInfo[1]) && (int) $errorInfo[1] === 1062) {
        $existingOrder = find_order_submission($pdo, $submissionSessionKey, $submissionKey);
        if ($existingOrder !== null && isset($trackingSecret)) {
            $orderId = (int) $existingOrder['order_id'];
            $currentCartHash = hash(
                'sha256',
                serialize(isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [])
            );
            if (hash_equals((string) $existingOrder['cart_hash'], $currentCartHash)) {
                $_SESSION['cart'] = [];
                $_SESSION['checkout_submission_cart_hash'] = hash('sha256', serialize([]));
                $_SESSION['checkout_submission_key'] = bin2hex(random_bytes(32));
            }
            $_SESSION['order_tracking'] = isset($_SESSION['order_tracking']) && is_array($_SESSION['order_tracking'])
                ? $_SESSION['order_tracking']
                : [];
            $_SESSION['order_tracking'][(string) $orderId] = order_tracking_token($orderId, $trackingSecret);
            order_api_response(200, [
                'success' => true,
                'data' => [
                    'order_id' => $orderId,
                    'total_amount' => (string) $existingOrder['total_amount'],
                ],
            ]);
        }
    }
    error_log('[order-submit-api] Database operation failed.');
    order_api_error(500, 'internal_error', 'The order could not be submitted. Please try again.');
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[order-submit-api] Unexpected order submission failure.');
    order_api_error(500, 'internal_error', 'The order could not be submitted. Please try again.');
}