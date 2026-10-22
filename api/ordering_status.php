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

function order_status_respond_for_reference(PDO $pdo, string $reference, ?string $pollToken = null): void
{
    $referenceStatement = $pdo->prepare(
        'SELECT order_reference, status
         FROM orders
         WHERE order_reference = :order_reference
         LIMIT 1'
    );
    $referenceStatement->execute(['order_reference' => $reference]);
    $order = $referenceStatement->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        order_status_error(404, 'order_not_found', 'The order could not be found.');
    }

    if (!in_array($order['status'], ['Pending', 'Paid', 'Preparing', 'Completed'], true)) {
        error_log('[order-status] Stored order status is outside the approved workflow.');
        order_status_error(500, 'internal_error', 'Order status is temporarily unavailable.');
    }

    $data = [
        'order_reference' => (string) $order['order_reference'],
        'status' => (string) $order['status'],
    ];
    if ($pollToken !== null) {
        $data['polling_token'] = $pollToken;
    }

    order_status_json(200, [
        'success' => true,
        'data' => $data,
    ]);
}

require_once __DIR__ . '/../includes/order_tracking.php';

$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
if ($requestMethod !== 'GET') {
    header('Allow: GET');
    order_status_error(405, 'method_not_allowed', 'Only GET requests are allowed.');
}

$rawReference = isset($_GET['reference']) && is_string($_GET['reference'])
    ? $_GET['reference']
    : '';
$referenceProvided = array_key_exists('reference', $_GET);
$reference = normalize_order_tracking_reference($rawReference);
$pollToken = isset($_SERVER['HTTP_X_ORDER_TRACKING_POLL_TOKEN']) &&
    is_string($_SERVER['HTTP_X_ORDER_TRACKING_POLL_TOKEN'])
    ? $_SERVER['HTTP_X_ORDER_TRACKING_POLL_TOKEN']
    : '';

if ($pollToken !== '' && $referenceProvided) {
    order_status_error(400, 'invalid_tracking_request', 'The tracking request is invalid.');
}

if ($referenceProvided) {
    if ($reference === null) {
        order_status_error(422, 'invalid_reference', 'Enter a valid Order Tracking No.');
    }

    require_once __DIR__ . '/../includes/customer_session.php';
    initialize_customer_session();

    try {
        require __DIR__ . '/../config/db.php';
        $ipKey = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $sessionKey = hash('sha256', session_id());
        $attemptStatement = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*)
                 FROM order_submission_log
                 WHERE ip_key = :ip_key
                   AND event_type = 'tracking_lookup'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS ip_attempts,
                (SELECT COUNT(*)
                 FROM order_submission_log
                 WHERE session_key = :session_key
                   AND event_type = 'tracking_lookup'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS session_attempts"
        );
        $attemptStatement->execute([
            'ip_key' => $ipKey,
            'session_key' => $sessionKey,
        ]);
        $attempts = $attemptStatement->fetch(PDO::FETCH_ASSOC);
        if ((int) $attempts['ip_attempts'] >= 60 || (int) $attempts['session_attempts'] >= 10) {
            order_status_error(429, 'lookup_rate_limited', 'Too many tracking lookups. Please wait and try again.');
        }

        $logStatement = $pdo->prepare(
            "INSERT INTO order_submission_log (ip_key, session_key, event_type)
             VALUES (:ip_key, :session_key, 'tracking_lookup')"
        );
        $logStatement->execute([
            'ip_key' => $ipKey,
            'session_key' => $sessionKey,
        ]);

        if (random_int(1, 20) === 1) {
            try {
                $purgeStatement = $pdo->prepare(
                    "DELETE FROM order_submission_log
                     WHERE event_type = 'tracking_lookup'
                       AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
                );
                $purgeStatement->execute();
            } catch (PDOException $exception) {
                error_log('[order-status] Lookup log cleanup failed.');
            }
        }

        order_status_respond_for_reference(
            $pdo,
            $reference,
            create_order_tracking_poll_token($reference)
        );
    } catch (PDOException $exception) {
        error_log('[order-status] Reference lookup failed.');
        order_status_error(500, 'internal_error', 'Order status is temporarily unavailable.');
    } catch (Throwable $exception) {
        error_log('[order-status] Unexpected reference lookup failure.');
        order_status_error(500, 'internal_error', 'Order status is temporarily unavailable.');
    }
}

if ($pollToken !== '') {
    require_once __DIR__ . '/../includes/customer_session.php';
    initialize_customer_session();
    $pollReference = order_tracking_reference_for_poll_token($pollToken);
    if ($pollReference === null) {
        order_status_error(401, 'tracking_session_expired', 'Enter your Order Tracking No. again to continue.');
    }
    if (!order_tracking_poll_request_allowed($pollToken)) {
        header('Retry-After: 4');
        order_status_error(429, 'poll_rate_limited', 'Please wait before refreshing order status again.');
    }

    try {
        require __DIR__ . '/../config/db.php';
        order_status_respond_for_reference($pdo, $pollReference);
    } catch (PDOException $exception) {
        error_log('[order-status] Polling lookup failed.');
        order_status_error(500, 'internal_error', 'Order status is temporarily unavailable.');
    } catch (Throwable $exception) {
        error_log('[order-status] Unexpected polling lookup failure.');
        order_status_error(500, 'internal_error', 'Order status is temporarily unavailable.');
    }
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
