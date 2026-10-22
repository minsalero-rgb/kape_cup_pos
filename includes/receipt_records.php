<?php
declare(strict_types=1);

function receipt_records_normalize_filters(array $input): array
{
    $filters = [
        'tracking' => '',
        'date_from' => '',
        'date_to' => '',
        'item' => '',
    ];
    $errors = [];

    $tracking = isset($input['tracking']) && is_string($input['tracking'])
        ? trim($input['tracking'])
        : '';
    if ($tracking !== '' && preg_match('/\A[A-Za-z0-9_-]{1,27}\z/', $tracking) !== 1) {
        $errors[] = 'Tracking number must contain up to 27 letters, digits, underscores, or hyphens.';
    } else {
        $filters['tracking'] = $tracking;
    }

    foreach (['date_from', 'date_to'] as $dateField) {
        $date = isset($input[$dateField]) && is_string($input[$dateField])
            ? trim($input[$dateField])
            : '';
        if ($date !== '' && !receipt_records_is_date($date)) {
            $errors[] = 'Enter valid dates for the receipt issue-date range.';
        } else {
            $filters[$dateField] = $date;
        }
    }

    if (
        $filters['date_from'] !== ''
        && $filters['date_to'] !== ''
        && $filters['date_from'] > $filters['date_to']
    ) {
        $errors[] = 'The start date must not be after the end date.';
    }

    $item = isset($input['item']) && is_string($input['item'])
        ? trim($input['item'])
        : '';
    if (strlen($item) > 120) {
        $errors[] = 'Item search must be 120 characters or fewer.';
    } else {
        $filters['item'] = $item;
    }

    return ['filters' => $filters, 'errors' => array_values(array_unique($errors))];
}

function receipt_records_is_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function receipt_records_fetch(PDO $pdo, array $filters, bool $includeRemoved): array
{
    $conditions = [];
    $parameters = [];

    if (!$includeRemoved) {
        $conditions[] = "receipt_records.status = 'Active'";
    }
    if ($filters['tracking'] !== '') {
        $conditions[] = "receipt_records.order_reference LIKE :tracking ESCAPE '!'";
        $tracking = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['tracking']);
        $parameters['tracking'] = '%' . $tracking . '%';
    }
    if ($filters['date_from'] !== '') {
        $conditions[] = 'receipt_records.issued_at >= :date_from';
        $parameters['date_from'] = $filters['date_from'] . ' 00:00:00';
    }
    if ($filters['date_to'] !== '') {
        $conditions[] = 'receipt_records.issued_at < DATE_ADD(:date_to, INTERVAL 1 DAY)';
        $parameters['date_to'] = $filters['date_to'] . ' 00:00:00';
    }
    if ($filters['item'] !== '') {
        $conditions[] = 'EXISTS (
            SELECT 1
            FROM order_items
            WHERE order_items.order_id = receipt_records.order_id
              AND order_items.item_name_snapshot LIKE :item
        )';
        $parameters['item'] = '%' . $filters['item'] . '%';
    }

    $sql = 'SELECT receipt_records.receipt_id, receipt_records.order_id,
                   receipt_records.order_reference, receipt_records.or_number,
                   receipt_records.issued_at, receipt_records.status,
                   receipt_records.receipt_snapshot, receipt_records.removed_at,
                   receipt_records.removal_reason,
                   issued_by.full_name AS issued_by_name,
                   removed_by.full_name AS removed_by_name
            FROM receipt_records
            INNER JOIN staff AS issued_by
                ON issued_by.staff_id = receipt_records.created_by_staff_id
            LEFT JOIN staff AS removed_by
                ON removed_by.staff_id = receipt_records.removed_by_staff_id';
    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY receipt_records.issued_at DESC, receipt_records.receipt_id DESC LIMIT 100';

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $receipts = $statement->fetchAll(PDO::FETCH_ASSOC);
    foreach ($receipts as &$receipt) {
        $snapshot = json_decode((string) $receipt['receipt_snapshot'], true);
        if (!is_array($snapshot)) {
            throw new UnexpectedValueException('A stored receipt snapshot is invalid.');
        }
        $receipt['snapshot'] = $snapshot;
    }
    unset($receipt);

    return $receipts;
}

function receipt_records_issue(PDO $pdo, int $orderId, int $staffId, string $orNumber): int
{
    $orNumber = trim($orNumber);
    if (
        $orNumber === ''
        || strlen($orNumber) > 50
        || preg_match('/[\x00-\x1F\x7F]/', $orNumber) === 1
    ) {
        throw new InvalidArgumentException('Enter an issued OR number of 1 to 50 printable characters.');
    }

    if ($pdo->inTransaction()) {
        throw new LogicException('Receipt issuance requires its own transaction.');
    }

    $pdo->beginTransaction();
    try {
        $orderStatement = $pdo->prepare(
            'SELECT orders.order_id, orders.order_reference, orders.customer_name,
                    orders.order_type, orders.table_label, orders.payment_method,
                    orders.total_amount, orders.cash_tendered, orders.change_due,
                    orders.status, orders.created_at, orders.paid_at,
                    staff.full_name AS cashier_name
             FROM orders
             INNER JOIN staff ON staff.staff_id = orders.staff_id
             WHERE orders.order_id = :order_id
             LIMIT 1
             FOR UPDATE'
        );
        $orderStatement->execute(['order_id' => $orderId]);
        $order = $orderStatement->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new DomainException('That order is no longer available.');
        }
        if ($order['status'] === 'Pending') {
            throw new DomainException('A receipt can be issued only after payment is confirmed.');
        }

        $activeStatement = $pdo->prepare(
            "SELECT receipt_id
             FROM receipt_records
             WHERE order_id = :order_id AND status = 'Active'
             LIMIT 1"
        );
        $activeStatement->execute(['order_id' => $orderId]);
        if ($activeStatement->fetchColumn() !== false) {
            throw new DomainException('An active receipt already exists for this order.');
        }

        $itemStatement = $pdo->prepare(
            'SELECT order_items.order_item_id, order_items.item_name_snapshot,
                    order_items.quantity, order_items.line_total_snapshot,
                    order_items.notes, order_item_modifiers.group_name_snapshot,
                    order_item_modifiers.option_name_snapshot,
                    order_item_modifiers.price_delta_snapshot
             FROM order_items
             LEFT JOIN order_item_modifiers
                ON order_item_modifiers.order_item_id = order_items.order_item_id
             WHERE order_items.order_id = :order_id
             ORDER BY order_items.order_item_id,
                      order_item_modifiers.order_item_modifier_id'
        );
        $itemStatement->execute(['order_id' => $orderId]);
        $itemsById = [];
        foreach ($itemStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $itemId = (int) $row['order_item_id'];
            if (!isset($itemsById[$itemId])) {
                $itemsById[$itemId] = [
                    'name' => (string) $row['item_name_snapshot'],
                    'quantity' => (int) $row['quantity'],
                    'line_total' => (string) $row['line_total_snapshot'],
                    'notes' => $row['notes'] === null ? null : (string) $row['notes'],
                    'modifiers' => [],
                ];
            }
            if ($row['group_name_snapshot'] !== null) {
                $itemsById[$itemId]['modifiers'][] = [
                    'group' => (string) $row['group_name_snapshot'],
                    'option' => (string) $row['option_name_snapshot'],
                    'price_delta' => (string) $row['price_delta_snapshot'],
                ];
            }
        }

        $insertStatement = $pdo->prepare(
            "INSERT INTO receipt_records
                (order_id, order_reference, or_number, status, receipt_snapshot,
                 created_by_staff_id)
             VALUES
                (:order_id, :order_reference, :or_number, 'Active', '{}',
                 :created_by_staff_id)"
        );
        $insertStatement->execute([
            'order_id' => $orderId,
            'order_reference' => (string) $order['order_reference'],
            'or_number' => $orNumber,
            'created_by_staff_id' => $staffId,
        ]);
        $receiptId = (int) $pdo->lastInsertId();

        $issuedAtStatement = $pdo->prepare(
            'SELECT issued_at FROM receipt_records WHERE receipt_id = :receipt_id'
        );
        $issuedAtStatement->execute(['receipt_id' => $receiptId]);
        $issuedAt = (string) $issuedAtStatement->fetchColumn();
        $issuerStatement = $pdo->prepare(
            'SELECT full_name FROM staff WHERE staff_id = :staff_id LIMIT 1'
        );
        $issuerStatement->execute(['staff_id' => $staffId]);
        $issuerName = (string) $issuerStatement->fetchColumn();
        $snapshot = [
            'order' => $order,
            'items' => array_values($itemsById),
            'or_number' => $orNumber,
            'issued_at' => $issuedAt,
            'issued_by_name' => $issuerName,
        ];
        $snapshotJson = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
        $snapshotStatement = $pdo->prepare(
            'UPDATE receipt_records
             SET receipt_snapshot = :receipt_snapshot
             WHERE receipt_id = :receipt_id'
        );
        $snapshotStatement->execute([
            'receipt_snapshot' => $snapshotJson,
            'receipt_id' => $receiptId,
        ]);

        $pdo->commit();
        return $receiptId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function receipt_records_remove(PDO $pdo, int $receiptId, int $staffId, string $reason): void
{
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 500) {
        throw new InvalidArgumentException('Enter a removal reason of 1 to 500 characters.');
    }

    $statement = $pdo->prepare(
        "UPDATE receipt_records
         SET status = 'Removed',
             removed_at = CURRENT_TIMESTAMP,
             removed_by_staff_id = :staff_id,
             removal_reason = :removal_reason
         WHERE receipt_id = :receipt_id AND status = 'Active'"
    );
    $statement->execute([
        'staff_id' => $staffId,
        'removal_reason' => $reason,
        'receipt_id' => $receiptId,
    ]);
    if ($statement->rowCount() !== 1) {
        throw new DomainException('That receipt is no longer active.');
    }
}
