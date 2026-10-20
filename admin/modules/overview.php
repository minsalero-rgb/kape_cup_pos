<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit; }

$today = new DateTimeImmutable('today');
$tomorrow = $today->modify('+1 day');
$startAt = $today->format('Y-m-d H:i:s');
$endAt = $tomorrow->format('Y-m-d H:i:s');

$completedStatement = $pdo->prepare(
    "SELECT COUNT(*) AS order_count,
            COALESCE(SUM(total_amount), 0.00) AS completed_sales
     FROM orders
     WHERE status = 'Completed'
       AND completed_at >= :start_at
       AND completed_at < :end_at"
);
$completedStatement->execute(['start_at' => $startAt, 'end_at' => $endAt]);
$completedSummary = $completedStatement->fetch(PDO::FETCH_ASSOC);

$pendingStatement = $pdo->prepare(
    "SELECT COUNT(*)
     FROM orders
     WHERE status = 'Pending'
       AND created_at >= :start_at
       AND created_at < :end_at"
);
$pendingStatement->execute(['start_at' => $startAt, 'end_at' => $endAt]);
$pendingCount = (int) $pendingStatement->fetchColumn();

$openSessionsCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM staff_shifts WHERE logout_timestamp IS NULL'
)->fetchColumn();
$activeStaffCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM staff WHERE is_active = 1'
)->fetchColumn();
$menuSummary = $pdo->query(
    'SELECT COUNT(*) AS active_count,
            COALESCE(SUM(is_sold_out = 1), 0) AS sold_out_count
     FROM menu_items
     WHERE is_active = 1'
)->fetch(PDO::FETCH_ASSOC);

$recentOrders = $pdo->query(
'SELECT order_id, customer_name, status, total_amount, payment_method, created_at
 FROM orders
 ORDER BY created_at DESC, order_id DESC
 LIMIT 6'
)->fetchAll(PDO::FETCH_ASSOC);

$popularStatement = $pdo->prepare(
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
 LIMIT 5"
);
$popularStatement->execute(['start_at' => $startAt, 'end_at' => $endAt]);
$popularItems = $popularStatement->fetchAll(PDO::FETCH_ASSOC);

$trendStart = $today->modify('-6 days')->format('Y-m-d 00:00:00');
$trendStatement = $pdo->prepare(
"SELECT DATE(completed_at) AS sales_date, SUM(total_amount) AS sales
 FROM orders
 WHERE status = 'Completed'
   AND completed_at >= :start_at
   AND completed_at < :end_at
 GROUP BY DATE(completed_at)
 ORDER BY sales_date ASC"
);
$trendStatement->execute(['start_at' => $trendStart, 'end_at' => $endAt]);
$trendByDate = [];
foreach ($trendStatement->fetchAll(PDO::FETCH_ASSOC) as $trendRow) {
$trendByDate[(string) $trendRow['sales_date']] = (float) $trendRow['sales'];
}
$salesTrend = [];
$maximumTrendSales = 0.0;
for ($day = $today->modify('-6 days'); $day <= $today; $day = $day->modify('+1 day')) {
$dateKey = $day->format('Y-m-d');
$salesTrend[] = [
    'date' => $dateKey,
    'label' => $day->format('D'),
    'sales' => isset($trendByDate[$dateKey]) ? $trendByDate[$dateKey] : 0.0,
];
$maximumTrendSales = max($maximumTrendSales, (float) end($salesTrend)['sales']);
}

$inventoryColumnsStatement = $pdo->query(
"SELECT COUNT(*)
 FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = 'menu_items'
   AND COLUMN_NAME IN ('stock_quantity', 'low_stock_threshold')"
);
$hasInventoryColumns = (int) $inventoryColumnsStatement->fetchColumn() === 2;
$lowStockItems = [];
if ($hasInventoryColumns) {
$lowStockItems = $pdo->query(
    'SELECT name, stock_quantity, low_stock_threshold, is_sold_out
     FROM menu_items
     WHERE is_active = 1
       AND stock_quantity <= low_stock_threshold
     ORDER BY stock_quantity ASC, name ASC
     LIMIT 8'
)->fetchAll(PDO::FETCH_ASSOC);
}

?>
<div>
    <div class="flex flex-wrap items-center justify-between gap-space-sm">
        <div>
            <p class="mt-space-xs text-body-sm text-muted-text">Welcome, <?= escape_html($admin['full_name']) ?>. Today's operations at a glance.</p>
        </div>
        <span class="inline-flex items-center gap-space-xs rounded-lg bg-surface-container-low px-space-sm py-space-2xs font-status-badge text-status-badge text-muted-text"><span class="h-2.5 w-2.5 rounded-full bg-matcha-green"></span>Administrator access</span>
    </div>

    <section class="mt-space-md app-auto-grid" aria-label="Today's summary">
        <article class="card stat"><p>Completed sales today</p><p>₱<?= escape_html($completedSummary['completed_sales']) ?></p></article>
        <article class="card stat"><p>Completed orders today</p><p><?= (int) $completedSummary['order_count'] ?></p></article>
        <article class="card stat"><p>Pending orders today</p><p><?= $pendingCount ?></p></article>
        <article class="card stat"><p>Open duty sessions</p><p><?= $openSessionsCount ?></p></article>
        <article class="card stat"><p>Active staff</p><p><?= $activeStaffCount ?></p></article>
        <article class="card stat"><p>Active menu items / sold out</p><p><?= (int) $menuSummary['active_count'] ?> <span class="stat-detail">/ <?= (int) $menuSummary['sold_out_count'] ?> sold out</span></p></article>
    </section>

    <section class="mt-space-lg grid gap-space-md lg:grid-cols-2" aria-label="Recent activity and sales">
        <article class="card">
            <div class="app-section-heading">
                <div>
                    <p class="app-eyebrow">Latest activity</p>
                    <h2>Recent transactions</h2>
                </div>
                <a href="dashboard.php?tab=orders">All orders</a>
            </div>
            <?php if ($recentOrders === []): ?>
                <p class="text-body-sm text-muted-text">No orders have been recorded yet.</p>
            <?php else: ?>
                <div class="app-table-wrap">
                    <table>
                        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?= (int) $order['order_id'] ?><span class="block text-xs text-muted-text"><?= escape_html(date('M j, g:i A', strtotime((string) $order['created_at']))) ?></span></td>
                                <td><?= escape_html($order['customer_name']) ?></td>
                                <td><?= escape_html($order['status']) ?></td>
                                <td>₱<?= escape_html($order['total_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <article class="card">
            <div class="app-section-heading">
                <div>
                    <p class="app-eyebrow">Completed orders</p>
                    <h2>Sales trend · last 7 days</h2>
                </div>
                <span class="status-badge">Completed only</span>
            </div>
            <div class="admin-sales-trend" role="img" aria-label="Completed sales by day for the last seven days">
                <?php foreach ($salesTrend as $day): ?>
                    <?php $barHeight = $maximumTrendSales > 0 ? max(4, (int) round($day['sales'] / $maximumTrendSales * 100)) : 0; ?>
                    <div class="admin-sales-trend__day" title="<?= escape_html($day['date']) ?>: ₱<?= escape_html(number_format($day['sales'], 2)) ?>">
                        <span class="admin-sales-trend__amount">₱<?= escape_html(number_format($day['sales'], 0)) ?></span>
                        <span class="admin-sales-trend__bar" style="height: <?= $barHeight ?>%"></span>
                        <span class="admin-sales-trend__label"><?= escape_html($day['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-space-sm text-body-sm text-muted-text">Values use recorded Completed orders; an empty day represents no completed sales recorded for that date.</p>
        </article>
    </section>

    <section class="mt-space-lg grid gap-space-md lg:grid-cols-2" aria-label="Popular products and stock alerts">
        <article class="card">
            <div class="app-section-heading">
                <div><p class="app-eyebrow">Today</p><h2>Popular products</h2></div>
                <span class="status-badge">Completed only</span>
            </div>
            <?php if ($popularItems === []): ?>
                <p class="text-body-sm text-muted-text">No completed product sales have been recorded today.</p>
            <?php else: ?>
                <div class="app-table-wrap">
                    <table>
                        <thead><tr><th>Product</th><th>Units</th><th>Sales</th></tr></thead>
                        <tbody>
                        <?php foreach ($popularItems as $item): ?>
                            <tr><td><?= escape_html($item['item_name_snapshot']) ?></td><td><?= (int) $item['units_sold'] ?></td><td>₱<?= escape_html($item['sales']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <article class="card">
            <div class="app-section-heading">
                <div><p class="app-eyebrow">Inventory</p><h2>Low-stock alerts</h2></div>
                <?php if ($hasInventoryColumns): ?><a href="<?= escape_html(app_base_path()) ?>/staff/stock.php">Manage stock</a><?php endif; ?>
            </div>
            <?php if (!$hasInventoryColumns): ?>
                <p class="text-body-sm text-muted-text">Inventory is unavailable until migration 003 is installed.</p>
            <?php elseif ($lowStockItems === []): ?>
                <p class="text-body-sm text-muted-text">No active menu items are at or below their recorded low-stock threshold.</p>
            <?php else: ?>
                <ul class="admin-alert-list">
                    <?php foreach ($lowStockItems as $item): ?>
                        <li>
                            <strong><?= escape_html($item['name']) ?></strong>
                            <span><?= (int) $item['stock_quantity'] ?> on hand · alert at <?= (int) $item['low_stock_threshold'] ?><?= (int) $item['is_sold_out'] === 1 ? ' · marked sold out' : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </section>

</div>
