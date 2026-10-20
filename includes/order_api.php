<?php
declare(strict_types=1);

function order_api_response(int $statusCode, array $body): void
{
    http_response_code($statusCode);
    try {
        echo json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        http_response_code(500);
        echo '{"success":false,"error":{"code":"internal_error","message":"The order request could not be completed."}}';
    }
    exit;
}

function order_api_error(int $statusCode, string $code, string $message): void
{
    order_api_response($statusCode, [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ]);
}

function order_api_require_staff(PDO $pdo): array
{
    $user = order_queue_staff_user($pdo);
    if ($user !== null) {
        return $user;
    }

    if (staff_is_authenticated()) {
        order_api_error(403, 'staff_access_required', 'Active Staff access is required.');
    }

    order_api_error(401, 'authentication_required', 'Sign in to access the order queue.');
}

function order_api_require_staff_or_admin(PDO $pdo): array
{
    if (!staff_is_authenticated()) {
        order_api_error(401, 'authentication_required', 'Sign in to update order status.');
    }

    $statement = $pdo->prepare(
        "SELECT staff_id, full_name, role
         FROM staff
         WHERE staff_id = :staff_id
           AND is_active = 1
           AND role IN ('staff', 'admin')
         LIMIT 1"
    );
    $statement->execute(['staff_id' => (int) $_SESSION['staff_id']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        order_api_error(403, 'staff_access_required', 'An active Staff or Admin account is required.');
    }

    $_SESSION['staff_id'] = (int) $user['staff_id'];
    $_SESSION['staff_name'] = (string) $user['full_name'];
    $_SESSION['staff_role'] = (string) $user['role'];

    return [
        'staff_id' => (int) $user['staff_id'],
        'full_name' => (string) $user['full_name'],
        'role' => (string) $user['role'],
    ];
}

function order_api_require_one_active_shift(PDO $pdo, int $staffId): void
{
    if (count(order_queue_active_shift_ids($pdo, $staffId)) !== 1) {
        order_api_error(409, 'active_shift_required', 'Exactly one active Staff shift is required.');
    }
}
