<?php
declare(strict_types=1);

function next_order_status(string $status): ?string
{
    $transitions = [
        'Pending' => 'Paid',
        'Paid' => 'Preparing',
        'Preparing' => 'Completed',
    ];

    return isset($transitions[$status]) ? $transitions[$status] : null;
}

function parse_order_advance_payload(string $rawBody): array
{
    if ($rawBody === '' || strlen($rawBody) > 4096) {
        return ['success' => false, 'code' => 'invalid_json'];
    }

    try {
        $payload = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        return ['success' => false, 'code' => 'invalid_json'];
    }
    if (!is_array($payload)) {
        return ['success' => false, 'code' => 'invalid_json'];
    }

    $rawOrderId = isset($payload['order_id']) &&
        (is_int($payload['order_id']) || is_string($payload['order_id']))
        ? (string) $payload['order_id']
        : '';
    $orderId = filter_var($rawOrderId, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
    ]);
    $expectedStatus = isset($payload['expected_status']) && is_string($payload['expected_status'])
        ? $payload['expected_status']
        : '';
    if (
        $orderId === false ||
        !in_array($expectedStatus, ['Pending', 'Paid', 'Preparing', 'Completed'], true)
    ) {
        return ['success' => false, 'code' => 'invalid_order_update'];
    }

    return [
        'success' => true,
        'order_id' => (int) $orderId,
        'expected_status' => $expectedStatus,
    ];
}

function order_queue_staff_user(PDO $pdo): ?array
{
    if (!staff_is_authenticated()) {
        return null;
    }

    $statement = $pdo->prepare(
        "SELECT staff_id, full_name, role
         FROM staff
         WHERE staff_id = :staff_id
           AND role = 'staff'
           AND is_active = 1
         LIMIT 1"
    );
    $statement->execute(['staff_id' => (int) $_SESSION['staff_id']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return null;
    }

    $_SESSION['staff_id'] = (int) $user['staff_id'];
    $_SESSION['staff_name'] = (string) $user['full_name'];
    $_SESSION['staff_role'] = 'staff';

    return [
        'staff_id' => (int) $user['staff_id'],
        'full_name' => (string) $user['full_name'],
        'role' => 'staff',
    ];
}

function order_queue_active_shift_ids(PDO $pdo, int $staffId, bool $lock = false): array
{
    $statement = $pdo->prepare(
        "SELECT shifts.shift_id
         FROM staff_shifts AS shifts
         INNER JOIN staff
             ON staff.staff_id = shifts.staff_id
         WHERE shifts.staff_id = :staff_id
           AND shifts.logout_timestamp IS NULL
           AND staff.role = 'staff'
           AND staff.is_active = 1" . ($lock ? ' FOR UPDATE' : '')
    );
    $statement->execute(['staff_id' => $staffId]);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

function fetch_active_order_queue(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT
            orders.order_id,
            orders.order_reference,
            orders.customer_name,
            orders.order_type,
            orders.table_label,
            orders.payment_method,
            orders.total_amount,
            orders.cash_tendered,
            orders.change_due,
            orders.status,
            orders.created_at,
            order_items.order_item_id,
            order_items.item_name_snapshot,
            order_items.quantity,
            order_items.unit_price_snapshot,
            order_items.line_total_snapshot,
            order_items.notes,
            order_item_modifiers.order_item_modifier_id,
            order_item_modifiers.group_name_snapshot,
            order_item_modifiers.option_name_snapshot,
            order_item_modifiers.price_delta_snapshot
         FROM orders
         LEFT JOIN order_items
             ON order_items.order_id = orders.order_id
         LEFT JOIN order_item_modifiers
             ON order_item_modifiers.order_item_id = order_items.order_item_id
         WHERE orders.status IN ('Pending', 'Paid', 'Preparing')
         ORDER BY orders.created_at ASC, orders.order_id ASC,
                  order_items.order_item_id ASC, order_item_modifiers.order_item_modifier_id ASC"
    );

    $orders = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $orderIdKey = (string) $row['order_id'];
        if (!isset($orders[$orderIdKey])) {
            $orders[$orderIdKey] = [
                'order_id' => (int) $row['order_id'],
                'order_reference' => $row['order_reference'] === null
                    ? null
                    : (string) $row['order_reference'],
                'customer_name' => (string) $row['customer_name'],
                'order_type' => (string) $row['order_type'],
                'table_label' => $row['table_label'],
                'payment_method' => (string) $row['payment_method'],
                'total_amount' => (string) $row['total_amount'],
                'cash_tendered' => $row['cash_tendered'] === null ? null : (string) $row['cash_tendered'],
                'change_due' => $row['change_due'] === null ? null : (string) $row['change_due'],
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
                'items' => [],
            ];
        }

        if ($row['order_item_id'] === null) {
            continue;
        }
        $itemIdKey = (string) $row['order_item_id'];
        if (!isset($orders[$orderIdKey]['items'][$itemIdKey])) {
            $orders[$orderIdKey]['items'][$itemIdKey] = [
                'name' => (string) $row['item_name_snapshot'],
                'quantity' => (int) $row['quantity'],
                'unit_price' => (string) $row['unit_price_snapshot'],
                'line_total' => (string) $row['line_total_snapshot'],
                'notes' => $row['notes'],
                'modifiers' => [],
            ];
        }
        if ($row['order_item_modifier_id'] !== null) {
            $orders[$orderIdKey]['items'][$itemIdKey]['modifiers'][] = [
                'group' => (string) $row['group_name_snapshot'],
                'name' => (string) $row['option_name_snapshot'],
                'price' => (string) $row['price_delta_snapshot'],
            ];
        }
    }

    foreach ($orders as &$order) {
        $order['items'] = array_values($order['items']);
    }
    unset($order);

    return array_values($orders);
}

function advance_order_status_internal(PDO $pdo, ?int $staffId, int $orderId, string $expectedStatus): array
{
    $nextStatus = next_order_status($expectedStatus);
    if ($nextStatus === null) {
        return ['success' => false, 'code' => 'invalid_transition'];
    }

    $pdo->beginTransaction();
    try {
        if ($staffId !== null) {
            $shiftIds = order_queue_active_shift_ids($pdo, $staffId, true);
            if (count($shiftIds) !== 1) {
                $pdo->rollBack();
                return ['success' => false, 'code' => 'active_shift_required'];
            }
        }

        $orderStatement = $pdo->prepare(
            'SELECT status
             FROM orders
             WHERE order_id = :order_id
             FOR UPDATE'
        );
        $orderStatement->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $orderStatement->execute();
        $currentStatus = $orderStatement->fetchColumn();

        if ($currentStatus === false) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 'order_not_found'];
        }
        if ($currentStatus !== $expectedStatus) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 'status_conflict'];
        }

        $timestampColumn = [
            'Paid' => 'paid_at',
            'Preparing' => 'preparing_at',
            'Completed' => 'completed_at',
        ][$nextStatus];
        $updateStatement = $pdo->prepare(
            'UPDATE orders
             SET status = :next_status, ' . $timestampColumn . ' = CURRENT_TIMESTAMP
             WHERE order_id = :order_id
               AND status = :expected_status'
        );
        $updateStatement->execute([
            'next_status' => $nextStatus,
            'order_id' => $orderId,
            'expected_status' => $expectedStatus,
        ]);

        if ($updateStatement->rowCount() !== 1) {
            $pdo->rollBack();
            return ['success' => false, 'code' => 'status_conflict'];
        }

        $pdo->commit();
        return ['success' => true, 'status' => $nextStatus];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function advance_order_status(PDO $pdo, int $staffId, int $orderId, string $expectedStatus): array
{
    return advance_order_status_internal($pdo, $staffId, $orderId, $expectedStatus);
}

function advance_order_status_as_admin(PDO $pdo, int $orderId, string $expectedStatus): array
{
    return advance_order_status_internal($pdo, null, $orderId, $expectedStatus);
}
