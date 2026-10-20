<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit; }
require_once __DIR__ . '/../../includes/order_workflow.php';

$allowedStatuses = ['Pending', 'Paid', 'Preparing', 'Completed'];
$actionError = '';
$actionSuccess = isset($_GET['success']) && $_GET['success'] === 'status'
    ? 'Order status updated.'
    : '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['section']) &&
    is_string($_POST['section']) &&
    $_POST['section'] === 'orders'
) {
    require_post_request();
    if (!csrf_token_is_valid(isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        $actionError = 'This form expired. Refresh the page and try again.';
    } else {
        $orderId = isset($_POST['order_id']) && is_string($_POST['order_id'])
            ? filter_var($_POST['order_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $expectedStatus = isset($_POST['expected_status']) && is_string($_POST['expected_status'])
            ? $_POST['expected_status']
            : '';
        if ($orderId === false || !in_array($expectedStatus, ['Pending', 'Paid', 'Preparing'], true)) {
            $actionError = 'That order action is invalid. Refresh the order list and try again.';
        } else {
            try {
                $result = advance_order_status_as_admin($pdo, (int) $orderId, $expectedStatus);
                if ($result['success']) {
                    admin_redirect('orders', 'success=status');
                }
                $actionError = $result['code'] === 'status_conflict'
                    ? 'The order changed before this update. Refresh and review the latest status.'
                    : 'The order could not be advanced through the approved workflow.';
            } catch (Throwable $exception) {
                error_log('[admin-orders] Order status update failed.');
                http_response_code(500);
                $actionError = 'The order could not be updated. Please try again.';
            }
        }
    }
}

$statusFilter = $activeTab === 'orders' && isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : 'All';
if ($statusFilter !== 'All' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}

$sql = 'SELECT orders.order_id, orders.customer_name, orders.order_type,
               orders.table_label, orders.payment_method, orders.total_amount,
               orders.cash_tendered, orders.change_due, orders.status,
               orders.created_at, orders.paid_at, orders.preparing_at,
               orders.completed_at, staff.full_name AS staff_name,
               shifts.shift_id
        FROM orders
        INNER JOIN staff ON staff.staff_id = orders.staff_id
        INNER JOIN staff_shifts AS shifts
            ON shifts.shift_id = orders.shift_id
           AND shifts.staff_id = orders.staff_id';
if ($statusFilter !== 'All') {
    $sql .= ' WHERE orders.status = :status';
}
$sql .= ' ORDER BY orders.created_at DESC, orders.order_id DESC LIMIT 100';
$orderStatement = $pdo->prepare($sql);
if ($statusFilter !== 'All') {
    $orderStatement->bindValue(':status', $statusFilter, PDO::PARAM_STR);
}
$orderStatement->execute();
$orders = $orderStatement->fetchAll(PDO::FETCH_ASSOC);

$orderIds = array_map(static function (array $order): int {
    return (int) $order['order_id'];
}, $orders);
$itemsByOrder = [];
if ($orderIds !== []) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStatement = $pdo->prepare(
        'SELECT order_items.order_id, order_items.order_item_id,
                order_items.item_name_snapshot, order_items.base_price_snapshot,
                order_items.quantity, order_items.unit_price_snapshot,
                order_items.line_total_snapshot, order_items.notes,
                order_item_modifiers.group_name_snapshot,
                order_item_modifiers.option_name_snapshot,
                order_item_modifiers.price_delta_snapshot
         FROM order_items
         LEFT JOIN order_item_modifiers
            ON order_item_modifiers.order_item_id = order_items.order_item_id
         WHERE order_items.order_id IN (' . $placeholders . ')
         ORDER BY order_items.order_id, order_items.order_item_id,
                  order_item_modifiers.order_item_modifier_id'
    );
    $itemStatement->execute($orderIds);
    while ($row = $itemStatement->fetch(PDO::FETCH_ASSOC)) {
        $orderKey = (string) $row['order_id'];
        $itemKey = (string) $row['order_item_id'];
        if (!isset($itemsByOrder[$orderKey][$itemKey])) {
            $itemsByOrder[$orderKey][$itemKey] = [
                'name' => (string) $row['item_name_snapshot'],
                'base_price' => (string) $row['base_price_snapshot'],
                'quantity' => (int) $row['quantity'],
                'unit_price' => (string) $row['unit_price_snapshot'],
                'line_total' => (string) $row['line_total_snapshot'],
                'notes' => $row['notes'],
                'modifiers' => [],
            ];
        }
        if ($row['group_name_snapshot'] !== null) {
            $itemsByOrder[$orderKey][$itemKey]['modifiers'][] = [
                'group' => (string) $row['group_name_snapshot'],
                'name' => (string) $row['option_name_snapshot'],
                'price' => (string) $row['price_delta_snapshot'],
            ];
        }
    }
}
?>
<div><p class="mt-space-xs text-body-sm text-muted-text">Signed in as <?= escape_html($admin['full_name']) ?>. Status can move forward only through Pending → Paid → Preparing → Completed; payment is verified manually.</p></div>
    <?php if ($actionError !== ''): ?><p class="rounded-lg bg-red-50 p-4 text-red-900" role="alert"><?= escape_html($actionError) ?></p><?php endif; ?>
    <?php if ($actionSuccess !== ''): ?><p class="rounded-lg bg-green-50 p-4 text-green-900" role="status"><?= escape_html($actionSuccess) ?></p><?php endif; ?>
    <form method="get" action="dashboard.php" class="flex flex-wrap items-end gap-3 rounded-xl border border-[#E8DEC9] bg-[#FFFCF6] p-4">
        <input type="hidden" name="tab" value="orders"><label class="text-sm font-semibold">Status
            <select class="mt-1 block rounded-lg border border-[#E5D8C3] bg-white px-3 py-2" name="status">
                <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All statuses</option>
                <?php foreach ($allowedStatuses as $status): ?><option value="<?= escape_html($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= escape_html($status) ?></option><?php endforeach; ?>
            </select>
        </label>
        <button class="rounded-lg bg-[#3B1F14] px-4 py-2 font-semibold text-white" type="submit">Filter</button>
        <span class="text-sm text-[#74685E]">Latest 100 matching orders</span>
    </form>
    <?php foreach ($orders as $order): ?>
        <?php $orderKey = (string) $order['order_id']; ?>
        <article class="rounded-2xl border border-[#E8DEC9] bg-[#FFFCF6] p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#E8DEC9] pb-4">
                <div><h2 class="text-xl font-bold text-[#3B1F14]">Order #<?= (int) $order['order_id'] ?> · <?= escape_html($order['customer_name']) ?></h2><p class="mt-1 text-sm text-[#74685E]"><?= escape_html(date('M j, Y g:i A', strtotime($order['created_at']))) ?> · <?= escape_html($order['order_type']) ?><?= $order['table_label'] !== null ? ' · Table ' . escape_html($order['table_label']) : '' ?></p></div>
                <span class="rounded-full bg-[#EFE1C3] px-3 py-1 text-sm font-bold text-[#3B1F14]"><?= escape_html($order['status']) ?></span>
            </div>
            <div class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <p>Payment: <strong><?= escape_html($order['payment_method']) ?></strong></p>
                <p>Total: <strong>₱<?= escape_html($order['total_amount']) ?></strong></p>
                <?php if ($order['cash_tendered'] !== null): ?><p>Cash tendered: ₱<?= escape_html($order['cash_tendered']) ?></p><p>Change due: ₱<?= escape_html($order['change_due']) ?></p><?php endif; ?>
                <p>Accepted by: <?= escape_html($order['staff_name']) ?></p><p>Shift: #<?= (int) $order['shift_id'] ?></p>
            </div>
            <h3 class="mt-5 font-bold text-[#3B1F14]">Items</h3>
            <div class="mt-2 divide-y divide-[#E8DEC9]">
                <?php foreach (isset($itemsByOrder[$orderKey]) ? $itemsByOrder[$orderKey] : [] as $item): ?>
                    <div class="py-3">
                        <div class="flex flex-wrap justify-between gap-2"><strong><?= (int) $item['quantity'] ?> × <?= escape_html($item['name']) ?></strong><span>₱<?= escape_html($item['line_total']) ?></span></div>
                        <p class="mt-1 text-xs text-[#74685E]">Base ₱<?= escape_html($item['base_price']) ?> · Unit ₱<?= escape_html($item['unit_price']) ?></p>
                        <?php foreach ($item['modifiers'] as $modifier): ?><p class="text-sm text-[#74685E]"><?= escape_html($modifier['group']) ?>: <?= escape_html($modifier['name']) ?> (<?= (float) $modifier['price'] > 0 ? '+' : '' ?>₱<?= escape_html($modifier['price']) ?>)</p><?php endforeach; ?>
                        <?php if ($item['notes'] !== null && $item['notes'] !== ''): ?><p class="text-sm text-[#74685E]">Note: <?= escape_html($item['notes']) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($itemsByOrder[$orderKey])): ?><p class="py-3 text-sm text-[#74685E]">No item lines were recorded.</p><?php endif; ?>
            </div>
            <div class="mt-4 flex flex-wrap gap-4 border-t border-[#E8DEC9] pt-3 text-xs text-[#74685E]">
                <?php foreach (['paid_at' => 'Paid', 'preparing_at' => 'Preparing', 'completed_at' => 'Completed'] as $field => $label): ?>
                    <?php if ($order[$field] !== null): ?><span><?= escape_html($label) ?>: <?= escape_html(date('M j, Y g:i A', strtotime($order[$field]))) ?></span><?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php $nextStatus = next_order_status((string) $order['status']); ?>
            <?php if ($nextStatus !== null): ?>
                <form class="mt-4" method="post" action="dashboard.php?tab=orders" data-confirm="Advance order #<?= (int) $order['order_id'] ?> from <?= escape_html($order['status']) ?> to <?= escape_html($nextStatus) ?>?">
                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                    <input type="hidden" name="section" value="orders">
                    <input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
                    <input type="hidden" name="expected_status" value="<?= escape_html($order['status']) ?>">
                    <button class="rounded-lg bg-[#3B1F14] px-4 py-2 font-semibold text-white" type="submit">Advance to <?= escape_html($nextStatus) ?></button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php if ($orders === []): ?><p class="rounded-xl border border-[#E8DEC9] bg-[#FFFCF6] p-8 text-center text-[#74685E]">No matching orders were found.</p><?php endif; ?>
