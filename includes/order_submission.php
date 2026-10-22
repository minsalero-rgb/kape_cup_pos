<?php
declare(strict_types=1);

final class OrderSubmissionException extends RuntimeException
{
    private $errorCode;

    public function __construct(string $errorCode, string $message)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

function order_money_to_cents($value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $value = (string) $value;
    if (preg_match('/^(-?)(\d{1,8})(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
        return null;
    }

    $fraction = isset($matches[3]) ? str_pad($matches[3], 2, '0') : '00';
    $whole = (int) $matches[2];
    if ($whole > intdiv(PHP_INT_MAX, 100)) {
        return null;
    }

    $cents = $whole * 100;
    $fractionCents = (int) $fraction;
    if ($cents > PHP_INT_MAX - $fractionCents) {
        return null;
    }
    $cents += $fractionCents;

    return $matches[1] === '-' ? -$cents : $cents;
}

function order_money_from_cents(int $cents): string
{
    $sign = $cents < 0 ? '-' : '';
    $absolute = abs($cents);

    return $sign . intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
}

function prepare_order_cart(PDO $pdo, array $cart, bool $lockRows = false): array
{
    if ($cart === [] || count($cart) > 50) {
        throw new OrderSubmissionException('invalid_cart', 'Your cart is empty or contains too many items.');
    }

    $preparedItems = [];
    $totalCents = 0;
    $requestedQuantities = [];
    $itemSql = 'SELECT item_id, name, base_price, stock_quantity, is_active, is_sold_out
                FROM menu_items
                WHERE item_id = :item_id' . ($lockRows ? ' FOR UPDATE' : '');
    $itemStatement = $pdo->prepare($itemSql);

    foreach ($cart as $entry) {
        if (!is_array($entry)) {
            throw new OrderSubmissionException('invalid_cart', 'An item in your cart is invalid.');
        }

        $itemId = isset($entry['item_id']) && (is_int($entry['item_id']) || is_string($entry['item_id']))
            ? filter_var((string) $entry['item_id'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
            ])
            : false;
        $quantity = isset($entry['quantity']) && (is_int($entry['quantity']) || is_string($entry['quantity']))
            ? filter_var((string) $entry['quantity'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 99],
            ])
            : false;

        if ($itemId === false || $quantity === false) {
            throw new OrderSubmissionException('invalid_cart', 'An item or quantity in your cart is invalid.');
        }

        $rawOptionIds = isset($entry['modifier_option_ids']) ? $entry['modifier_option_ids'] : [];
        if (!is_array($rawOptionIds) || count($rawOptionIds) > 50) {
            throw new OrderSubmissionException('invalid_cart', 'A modifier selection in your cart is invalid.');
        }
        $optionIds = [];
        foreach ($rawOptionIds as $rawOptionId) {
            if (!is_int($rawOptionId) && !is_string($rawOptionId)) {
                throw new OrderSubmissionException('invalid_cart', 'A modifier selection in your cart is invalid.');
            }
            $optionId = filter_var((string) $rawOptionId, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
            ]);
            if ($optionId === false || in_array((int) $optionId, $optionIds, true)) {
                throw new OrderSubmissionException('invalid_cart', 'A modifier selection in your cart is invalid.');
            }
            $optionIds[] = (int) $optionId;
        }

        $notes = isset($entry['notes']) ? $entry['notes'] : null;
        if ($notes !== null && (!is_string($notes) || strlen($notes) > 500)) {
            throw new OrderSubmissionException('invalid_cart', 'An item note is too long or invalid.');
        }

        $itemStatement->execute(['item_id' => (int) $itemId]);
        $menuItem = $itemStatement->fetch(PDO::FETCH_ASSOC);
        if (
            !$menuItem
            || (int) $menuItem['is_active'] !== 1
            || (int) $menuItem['is_sold_out'] === 1
        ) {
            throw new OrderSubmissionException('item_unavailable', 'An item in your cart is no longer available.');
        }
        $itemIdValue = (int) $itemId;
        $requestedQuantities[$itemIdValue] = ($requestedQuantities[$itemIdValue] ?? 0) + (int) $quantity;
        if ((int) $menuItem['stock_quantity'] < $requestedQuantities[$itemIdValue]) {
            throw new OrderSubmissionException(
                'item_unavailable',
                'There is not enough stock for ' . (string) $menuItem['name'] . '.'
            );
        }

        $groupStatement = $pdo->prepare(
            'SELECT modifier_group_id, name, is_required, min_selections, max_selections
             FROM menu_modifier_groups
             WHERE item_id = :item_id
               AND is_active = 1
             ORDER BY sort_order ASC, modifier_group_id ASC' . ($lockRows ? ' FOR UPDATE' : '')
        );
        $groupStatement->execute(['item_id' => (int) $itemId]);
        $groups = [];
        foreach ($groupStatement->fetchAll(PDO::FETCH_ASSOC) as $group) {
            $groups[(int) $group['modifier_group_id']] = $group;
        }

        $selectedModifiers = [];
        $selectedPerGroup = [];
        if ($optionIds !== []) {
            $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
            $modifierSql =
                'SELECT options.modifier_option_id, options.modifier_group_id,
                        options.name AS option_name, options.price_delta,
                        mg.name AS group_name
                 FROM menu_modifier_options AS options
                 INNER JOIN menu_modifier_groups AS mg
                     ON mg.modifier_group_id = options.modifier_group_id
                 WHERE options.modifier_option_id IN (' . $placeholders . ')
                   AND options.is_active = 1
                   AND mg.item_id = ?
                   AND mg.is_active = 1' . ($lockRows ? ' FOR UPDATE' : '');
            $modifierStatement = $pdo->prepare($modifierSql);
            $modifierStatement->execute(array_merge($optionIds, [(int) $itemId]));
            $modifierRows = $modifierStatement->fetchAll(PDO::FETCH_ASSOC);

            if (count($modifierRows) !== count($optionIds)) {
                throw new OrderSubmissionException('invalid_modifier', 'A selected modifier is no longer available for this item.');
            }

            foreach ($modifierRows as $modifier) {
                $groupId = (int) $modifier['modifier_group_id'];
                if (!isset($groups[$groupId])) {
                    throw new OrderSubmissionException('invalid_modifier', 'A selected modifier does not belong to this item.');
                }
                $selectedPerGroup[$groupId] = isset($selectedPerGroup[$groupId])
                    ? $selectedPerGroup[$groupId] + 1
                    : 1;
                $selectedModifiers[] = [
                    'modifier_option_id' => (int) $modifier['modifier_option_id'],
                    'group_name_snapshot' => (string) $modifier['group_name'],
                    'option_name_snapshot' => (string) $modifier['option_name'],
                    'price_delta_snapshot' => (string) $modifier['price_delta'],
                ];
            }
        }

        foreach ($groups as $groupId => $group) {
            $selectionCount = isset($selectedPerGroup[$groupId]) ? $selectedPerGroup[$groupId] : 0;
            $minimum = (int) $group['min_selections'];
            if ((int) $group['is_required'] === 1) {
                $minimum = max(1, $minimum);
            }
            if ($selectionCount < $minimum || $selectionCount > (int) $group['max_selections']) {
                throw new OrderSubmissionException(
                    'invalid_modifier',
                    'Choose the required number of options for ' . (string) $group['name'] . '.'
                );
            }
        }

        $baseCents = order_money_to_cents((string) $menuItem['base_price']);
        if ($baseCents === null || $baseCents < 0) {
            throw new OrderSubmissionException('item_unavailable', 'An item in your cart has an invalid price.');
        }
        $unitCents = $baseCents;
        foreach ($selectedModifiers as $modifier) {
            $deltaCents = order_money_to_cents($modifier['price_delta_snapshot']);
            if ($deltaCents === null) {
                throw new OrderSubmissionException('invalid_modifier', 'A modifier has an invalid price.');
            }
            if ($deltaCents > 0 && $unitCents > PHP_INT_MAX - $deltaCents) {
                throw new OrderSubmissionException('invalid_cart', 'An item total is outside the supported range.');
            }
            if ($deltaCents < 0 && $unitCents < PHP_INT_MIN - $deltaCents) {
                throw new OrderSubmissionException('invalid_cart', 'An item total is outside the supported range.');
            }
            $unitCents += $deltaCents;
        }
        if ($unitCents < 0) {
            throw new OrderSubmissionException('invalid_cart', 'An item total is outside the supported range.');
        }

        if ($unitCents > intdiv(PHP_INT_MAX, (int) $quantity)) {
            throw new OrderSubmissionException('invalid_cart', 'An item total is outside the supported range.');
        }
        $lineCents = $unitCents * (int) $quantity;
        if ($totalCents > PHP_INT_MAX - $lineCents) {
            throw new OrderSubmissionException('invalid_cart', 'Your order total is outside the supported range.');
        }
        $totalCents += $lineCents;

        $preparedItems[] = [
            'item_id' => (int) $itemId,
            'item_name_snapshot' => (string) $menuItem['name'],
            'base_price_snapshot' => order_money_from_cents($baseCents),
            'quantity' => (int) $quantity,
            'unit_price_snapshot' => order_money_from_cents($unitCents),
            'line_total_snapshot' => order_money_from_cents($lineCents),
            'notes' => $notes,
            'modifiers' => $selectedModifiers,
        ];
    }

    return [
        'items' => $preparedItems,
        'total_cents' => $totalCents,
        'total_amount' => order_money_from_cents($totalCents),
    ];
}

function deduct_order_inventory(PDO $pdo, array $preparedCart, int $staffId, int $orderId): void
{
    $updateStatement = $pdo->prepare(
        'UPDATE menu_items
         SET stock_quantity = stock_quantity - :deduct_quantity
         WHERE item_id = :item_id
           AND is_active = 1
           AND is_sold_out = 0
           AND stock_quantity >= :minimum_quantity'
    );
    $logStatement = $pdo->prepare(
        'INSERT INTO inventory_stock_logs
            (item_id, staff_id, order_id, quantity_change, reason)
         VALUES
            (:item_id, :staff_id, :order_id, :quantity_change, :reason)'
    );

    foreach ($preparedCart['items'] as $item) {
        $quantity = (int) $item['quantity'];
        $itemId = (int) $item['item_id'];
        $updateStatement->execute([
            'deduct_quantity' => $quantity,
            'item_id' => $itemId,
            'minimum_quantity' => $quantity,
        ]);
        if ($updateStatement->rowCount() !== 1) {
            throw new OrderSubmissionException(
                'item_unavailable',
                'An item in your cart no longer has enough stock.'
            );
        }

        $logStatement->execute([
            'item_id' => $itemId,
            'staff_id' => $staffId,
            'order_id' => $orderId,
            'quantity_change' => -$quantity,
            'reason' => 'Order #' . $orderId,
        ]);
    }
}

function insert_prepared_order_items(PDO $pdo, int $orderId, array $preparedCart): void
{
    $itemStatement = $pdo->prepare(
        'INSERT INTO order_items
            (order_id, item_id, item_name_snapshot, base_price_snapshot,
             quantity, unit_price_snapshot, line_total_snapshot, notes)
         VALUES
            (:order_id, :item_id, :item_name_snapshot, :base_price_snapshot,
             :quantity, :unit_price_snapshot, :line_total_snapshot, :notes)'
    );
    $modifierStatement = $pdo->prepare(
        'INSERT INTO order_item_modifiers
            (order_item_id, modifier_option_id, group_name_snapshot,
             option_name_snapshot, price_delta_snapshot)
         VALUES
            (:order_item_id, :modifier_option_id, :group_name_snapshot,
             :option_name_snapshot, :price_delta_snapshot)'
    );

    foreach ($preparedCart['items'] as $item) {
        $itemStatement->execute([
            'order_id' => $orderId,
            'item_id' => $item['item_id'],
            'item_name_snapshot' => $item['item_name_snapshot'],
            'base_price_snapshot' => $item['base_price_snapshot'],
            'quantity' => $item['quantity'],
            'unit_price_snapshot' => $item['unit_price_snapshot'],
            'line_total_snapshot' => $item['line_total_snapshot'],
            'notes' => $item['notes'],
        ]);
        $orderItemId = (int) $pdo->lastInsertId();

        foreach ($item['modifiers'] as $modifier) {
            $modifierStatement->execute([
                'order_item_id' => $orderItemId,
                'modifier_option_id' => $modifier['modifier_option_id'],
                'group_name_snapshot' => $modifier['group_name_snapshot'],
                'option_name_snapshot' => $modifier['option_name_snapshot'],
                'price_delta_snapshot' => $modifier['price_delta_snapshot'],
            ]);
        }
    }
}
