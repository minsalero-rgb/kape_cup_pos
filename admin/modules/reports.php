<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit; }

$today = date('Y-m-d');
$dateInput = $activeTab === 'reports' && isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : $today;
$date = DateTime::createFromFormat('!Y-m-d', $dateInput);
$dateErrors = DateTime::getLastErrors();
if (
    $date === false ||
    ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) ||
    $date->format('Y-m-d') !== $dateInput
) {
    $date = new DateTime($today);
    $dateInput = $today;
}
$startAt = $date->format('Y-m-d 00:00:00');
$endDate = clone $date;
$endDate->modify('+1 day');
$endAt = $endDate->format('Y-m-d 00:00:00');

$summaryStatement = $pdo->prepare(
    "SELECT COUNT(*) AS order_count,
            COALESCE(SUM(total_amount), 0.00) AS completed_sales,
            COALESCE(AVG(total_amount), 0.00) AS average_order
     FROM orders
     WHERE status = 'Completed'
       AND completed_at >= :start_at
       AND completed_at < :end_at"
);
$summaryStatement->execute(['start_at' => $startAt, 'end_at' => $endAt]);
$summary = $summaryStatement->fetch(PDO::FETCH_ASSOC);

$paymentStatement = $pdo->prepare(
    "SELECT payment_method, COUNT(*) AS order_count,
            COALESCE(SUM(total_amount), 0.00) AS sales
     FROM orders
     WHERE status = 'Completed'
       AND completed_at >= :start_at
       AND completed_at < :end_at
     GROUP BY payment_method
     ORDER BY sales DESC, payment_method ASC"
);
$paymentStatement->execute(['start_at' => $startAt, 'end_at' => $endAt]);
$paymentRows = $paymentStatement->fetchAll(PDO::FETCH_ASSOC);

$hourStatement = $pdo->prepare(
    "SELECT HOUR(created_at) AS order_hour, COUNT(*) AS order_count,
            COALESCE(SUM(total_amount), 0.00) AS sales
     FROM orders
     WHERE status = 'Completed'
       AND created_at >= :start_at
       AND created_at < :end_at
     GROUP BY HOUR(created_at)
     ORDER BY order_hour ASC"
);
$hourStatement->execute(['start_at' => $startAt, 'end_at' => $endAt]);
$hourRows = $hourStatement->fetchAll(PDO::FETCH_ASSOC);

$itemStatement = $pdo->prepare(
    "SELECT order_items.item_name_snapshot,
            SUM(order_items.quantity) AS units_sold,
            SUM(order_items.line_total_snapshot) AS sales
     FROM order_items
     INNER JOIN orders ON orders.order_id = order_items.order_id
     WHERE orders.status = 'Completed'
       AND orders.completed_at >= :start_at
       AND orders.completed_at < :end_at
     GROUP BY order_items.item_name_snapshot
     ORDER BY units_sold DESC, sales DESC, order_items.item_name_snapshot ASC
     LIMIT 10"
);
$itemStatement->execute(['start_at' => $startAt, 'end_at' => $endAt]);
$itemRows = $itemStatement->fetchAll(PDO::FETCH_ASSOC);

$shiftStatement = $pdo->prepare(
    "SELECT shifts.shift_id, staff.full_name, COUNT(orders.order_id) AS order_count,
            COALESCE(SUM(orders.total_amount), 0.00) AS sales
     FROM orders
     INNER JOIN staff_shifts AS shifts
        ON shifts.shift_id = orders.shift_id
       AND shifts.staff_id = orders.staff_id
     INNER JOIN staff ON staff.staff_id = shifts.staff_id
     WHERE orders.status = 'Completed'
       AND orders.completed_at >= :start_at
       AND orders.completed_at < :end_at
     GROUP BY shifts.shift_id, staff.full_name
     ORDER BY sales DESC, shifts.shift_id ASC"
);
$shiftStatement->execute(['start_at' => $startAt, 'end_at' => $endAt]);
$shiftRows = $shiftStatement->fetchAll(PDO::FETCH_ASSOC);
?>
<div><p class="mt-space-xs text-body-sm text-muted-text">Signed in as <?= escape_html($admin['full_name']) ?>. Revenue and related breakdowns include Completed orders only.</p></div>
    <form method="get" action="dashboard.php" class="flex flex-wrap items-end gap-3 rounded-xl border border-[#E8DEC9] bg-[#FFFCF6] p-4">
        <input type="hidden" name="tab" value="reports"><label class="text-sm font-semibold">Completion date
            <input class="mt-1 block rounded-lg border border-[#E5D8C3] bg-white px-3 py-2" type="date" name="date" value="<?= escape_html($dateInput) ?>" required>
        </label>
        <button class="rounded-lg bg-[#3B1F14] px-4 py-2 font-semibold text-white" type="submit">View report</button>
        <span class="text-sm text-[#74685E]">Based on orders completed on this date, <?= escape_html($date->format('M j, Y')) ?>.</span>
    </form>
    <section class="grid gap-4 sm:grid-cols-3">
        <article class="rounded-xl border border-[#E8DEC9] bg-[#FFFCF6] p-5"><p class="text-sm text-[#74685E]">Completed sales</p><p class="mt-2 text-3xl font-bold text-[#3B1F14]">₱<?= escape_html(number_format((float) $summary['completed_sales'], 2)) ?></p></article>
        <article class="rounded-xl border border-[#E8DEC9] bg-[#FFFCF6] p-5"><p class="text-sm text-[#74685E]">Completed orders</p><p class="mt-2 text-3xl font-bold text-[#3B1F14]"><?= (int) $summary['order_count'] ?></p></article>
        <article class="rounded-xl border border-[#E8DEC9] bg-[#FFFCF6] p-5"><p class="text-sm text-[#74685E]">Average completed order</p><p class="mt-2 text-3xl font-bold text-[#3B1F14]">₱<?= escape_html(number_format((float) $summary['average_order'], 2)) ?></p></article>
    </section>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-[#E8DEC9] bg-[#FFFCF6] shadow-sm"><div class="border-b border-[#E8DEC9] p-5"><h2 class="text-lg font-bold text-[#3B1F14]">Payment method</h2><p class="text-xs text-[#74685E]">Completed orders only</p></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-[#F6F3ED]"><tr><th class="px-4 py-3">Method</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">Sales</th></tr></thead><tbody class="divide-y divide-[#E8DEC9]"><?php foreach ($paymentRows as $row): ?><tr><td class="px-4 py-3"><?= escape_html($row['payment_method']) ?></td><td class="px-4 py-3"><?= (int) $row['order_count'] ?></td><td class="px-4 py-3">₱<?= escape_html($row['sales']) ?></td></tr><?php endforeach; ?><?php if ($paymentRows === []): ?><tr><td class="px-4 py-4 text-[#74685E]" colspan="3">No completed orders for this date.</td></tr><?php endif; ?></tbody></table></div></section>
        <section class="overflow-hidden rounded-2xl border border-[#E8DEC9] bg-[#FFFCF6] shadow-sm"><div class="border-b border-[#E8DEC9] p-5"><h2 class="text-lg font-bold text-[#3B1F14]">Top items</h2><p class="text-xs text-[#74685E]">Ranked by units sold; Completed orders only</p></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-[#F6F3ED]"><tr><th class="px-4 py-3">Item snapshot</th><th class="px-4 py-3">Units</th><th class="px-4 py-3">Sales</th></tr></thead><tbody class="divide-y divide-[#E8DEC9]"><?php foreach ($itemRows as $row): ?><tr><td class="px-4 py-3"><?= escape_html($row['item_name_snapshot']) ?></td><td class="px-4 py-3"><?= (int) $row['units_sold'] ?></td><td class="px-4 py-3">₱<?= escape_html($row['sales']) ?></td></tr><?php endforeach; ?><?php if ($itemRows === []): ?><tr><td class="px-4 py-4 text-[#74685E]" colspan="3">No completed item sales for this date.</td></tr><?php endif; ?></tbody></table></div></section>
        <section class="overflow-hidden rounded-2xl border border-[#E8DEC9] bg-[#FFFCF6] shadow-sm"><div class="border-b border-[#E8DEC9] p-5"><h2 class="text-lg font-bold text-[#3B1F14]">Orders by hour</h2><p class="text-xs text-[#74685E]">Grouped by order creation hour; Completed orders only</p></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-[#F6F3ED]"><tr><th class="px-4 py-3">Hour</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">Sales</th></tr></thead><tbody class="divide-y divide-[#E8DEC9]"><?php foreach ($hourRows as $row): ?><tr><td class="px-4 py-3"><?= escape_html(gmdate('g A', (int) $row['order_hour'] * 3600)) ?></td><td class="px-4 py-3"><?= (int) $row['order_count'] ?></td><td class="px-4 py-3">₱<?= escape_html($row['sales']) ?></td></tr><?php endforeach; ?><?php if ($hourRows === []): ?><tr><td class="px-4 py-4 text-[#74685E]" colspan="3">No completed orders for this date.</td><?php endif; ?></tbody></table></div></section>
        <section class="overflow-hidden rounded-2xl border border-[#E8DEC9] bg-[#FFFCF6] shadow-sm"><div class="border-b border-[#E8DEC9] p-5"><h2 class="text-lg font-bold text-[#3B1F14]">Sales by Staff shift</h2><p class="text-xs text-[#74685E]">Order attribution preserved from acceptance; Completed orders only</p></div><div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-[#F6F3ED]"><tr><th class="px-4 py-3">Staff / shift</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">Sales</th></tr></thead><tbody class="divide-y divide-[#E8DEC9]"><?php foreach ($shiftRows as $row): ?><tr><td class="px-4 py-3"><?= escape_html($row['full_name']) ?> · #<?= (int) $row['shift_id'] ?></td><td class="px-4 py-3"><?= (int) $row['order_count'] ?></td><td class="px-4 py-3">₱<?= escape_html($row['sales']) ?></td></tr><?php endforeach; ?><?php if ($shiftRows === []): ?><tr><td class="px-4 py-4 text-[#74685E]" colspan="3">No completed orders for this date.</td></tr><?php endif; ?></tbody></table></div></section>
    </div>
