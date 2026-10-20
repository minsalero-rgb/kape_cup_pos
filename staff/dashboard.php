<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/shift.php';

require_role('staff', $pdo);
$user = current_staff_user();
$shift = find_open_shift($pdo, $user['staff_id']);
$successMessage = get_flash_message('success');
$errorMessage = get_flash_message('error');

$statusCounts = [
    'Pending' => 0,
    'Paid' => 0,
    'Preparing' => 0,
];
$statusStatement = $pdo->query(
    "SELECT status, COUNT(*) AS order_count
     FROM orders
     WHERE status IN ('Pending', 'Paid', 'Preparing')
     GROUP BY status"
);
foreach ($statusStatement->fetchAll(PDO::FETCH_ASSOC) as $statusRow) {
    $statusCounts[(string) $statusRow['status']] = (int) $statusRow['order_count'];
}

$recentOrders = $pdo->query(
    'SELECT order_id, customer_name, order_type, table_label, status,
            total_amount, created_at
     FROM orders
     WHERE status IN (\'Pending\', \'Paid\', \'Preparing\')
     ORDER BY created_at ASC, order_id ASC
     LIMIT 8'
)->fetchAll(PDO::FETCH_ASSOC);

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
        'SELECT name, stock_quantity, low_stock_threshold
         FROM menu_items
         WHERE is_active = 1
           AND stock_quantity <= low_stock_threshold
         ORDER BY stock_quantity ASC, name ASC
         LIMIT 6'
    )->fetchAll(PDO::FETCH_ASSOC);
}

$role = 'staff';
$pageTitle = 'Staff Dashboard';
$pageEyebrow = 'Barista cockpit';
$activeNav = 'dashboard';
$pageDescription = 'Your shift, current order queue, and recorded stock alerts.';
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="staff-dashboard">
<?php if ($successMessage !== null): ?>
    <p class="alert alert--success" role="status"><?= escape_html($successMessage) ?></p>
<?php endif; ?>
<?php if ($errorMessage !== null): ?>
    <p class="alert alert--danger" role="alert"><?= escape_html($errorMessage) ?></p>
<?php endif; ?>

<section class="card staff-dashboard-shift" aria-labelledby="staff-shift-heading">
    <div class="staff-dashboard-shift-copy">
        <div class="staff-dashboard-shift-state">
            <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
            <span>Current shift</span>
            <span class="staff-dashboard-shift-badge<?= $shift === null ? ' is-off' : ' is-on' ?>">
                <?= $shift === null ? 'Off shift' : 'On shift' ?>
            </span>
        </div>
        <h2 id="staff-shift-heading"><?= $shift === null ? 'Ready to start your shift?' : 'Your shift is active' ?></h2>
        <?php if ($shift === null): ?>
            <p>Start your shift before processing POS sales or updating the order queue.</p>
        <?php else: ?>
            <p>Started <?= escape_html(date('M j, Y g:i A', strtotime((string) $shift['login_timestamp']))) ?>. Customer online ordering is enabled while an active Staff shift is open.</p>
        <?php endif; ?>
    </div>
    <div class="staff-dashboard-shift-action">
        <?php if ($shift === null): ?>
            <form method="post" action="start_shift.php">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <button class="btn-primary" type="submit">
                    <span class="material-symbols-outlined" aria-hidden="true">play_arrow</span>
                    Start shift
                </button>
            </form>
        <?php else: ?>
            <a class="btn-primary" href="offline_pos.php?page=pos">
                <span class="material-symbols-outlined" aria-hidden="true">point_of_sale</span>
                Open POS
            </a>
        <?php endif; ?>
    </div>
</section>

<section class="staff-dashboard-metrics" aria-label="Active order summary">
    <?php foreach ([
        'Pending' => ['Awaiting payment verification', 'receipt_long'],
        'Paid' => ['Awaiting preparation', 'payments'],
        'Preparing' => ['Being prepared', 'skillet'],
    ] as $status => $description): ?>
        <article class="card staff-dashboard-metric">
            <div class="staff-dashboard-metric-heading">
                <h2><?= escape_html($status) ?></h2>
                <span class="material-symbols-outlined" aria-hidden="true"><?= escape_html($description[1]) ?></span>
            </div>
            <p class="staff-dashboard-metric-value"><?= (int) $statusCounts[$status] ?></p>
            <p class="staff-dashboard-metric-detail"><?= escape_html($description[0]) ?></p>
        </article>
    <?php endforeach; ?>
    <article class="card staff-dashboard-metric">
        <div class="staff-dashboard-metric-heading">
            <h2>Low stock</h2>
            <span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>
        </div>
        <p class="staff-dashboard-metric-value"><?= $hasInventoryColumns ? count($lowStockItems) : '—' ?></p>
        <p class="staff-dashboard-metric-detail"><?= $hasInventoryColumns ? 'Items at or below threshold' : 'Inventory migration not installed' ?></p>
    </article>
</section>

<section class="staff-dashboard-workspace" aria-label="Staff work queue and stock alerts">
    <article class="card staff-dashboard-panel staff-dashboard-orders">
        <div class="app-section-heading">
            <div>
                <p class="app-eyebrow">Oldest first</p>
                <h2>Orders needing attention</h2>
            </div>
            <a class="staff-dashboard-text-link" href="orders.php">Open queue</a>
        </div>
        <?php if ($recentOrders === []): ?>
            <div class="staff-dashboard-empty">
                <span class="material-symbols-outlined" aria-hidden="true">task_alt</span>
                <p>There are no active orders right now.</p>
            </div>
        <?php else: ?>
            <div class="app-table-wrap">
                <table class="staff-dashboard-order-table">
                    <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><strong>#<?= (int) $order['order_id'] ?></strong><span class="staff-dashboard-cell-detail"><?= escape_html(date('g:i A', strtotime((string) $order['created_at']))) ?></span></td>
                            <td><?= escape_html($order['customer_name']) ?><span class="staff-dashboard-cell-detail"><?= escape_html($order['order_type']) ?><?= $order['table_label'] !== null ? ' · ' . escape_html($order['table_label']) : '' ?></span></td>
                            <td><span class="status-badge"><?= escape_html($order['status']) ?></span></td>
                            <td class="staff-dashboard-order-total">₱<?= escape_html($order['total_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="card staff-dashboard-panel staff-dashboard-stock">
        <div class="app-section-heading">
            <div>
                <p class="app-eyebrow">Recorded stock</p>
                <h2>Inventory alerts</h2>
            </div>
            <?php if ($hasInventoryColumns): ?><a class="staff-dashboard-text-link" href="stock.php">Manage stock</a><?php endif; ?>
        </div>
        <?php if (!$hasInventoryColumns): ?>
            <div class="staff-dashboard-empty">
                <span class="material-symbols-outlined" aria-hidden="true">info</span>
                <p>Stock alerts are unavailable until migration 003 is installed.</p>
            </div>
        <?php elseif ($lowStockItems === []): ?>
            <div class="staff-dashboard-empty">
                <span class="material-symbols-outlined" aria-hidden="true">inventory</span>
                <p>No active items are at or below their recorded low-stock threshold.</p>
            </div>
        <?php else: ?>
            <ul class="staff-dashboard-stock-list">
                <?php foreach ($lowStockItems as $item): ?>
                    <li>
                        <span class="staff-dashboard-stock-name"><?= escape_html($item['name']) ?></span>
                        <span class="staff-dashboard-stock-count"><?= (int) $item['stock_quantity'] ?> on hand</span>
                        <span class="staff-dashboard-stock-threshold">Alert at <?= (int) $item['low_stock_threshold'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>

<nav class="staff-dashboard-tools" aria-label="Staff tools">
    <h2>Quick access</h2>
    <div class="staff-dashboard-tool-grid">
        <a class="card staff-dashboard-tool" href="offline_pos.php?page=pos">
            <span class="staff-dashboard-tool-icon material-symbols-outlined" aria-hidden="true">point_of_sale</span>
            <span class="card-link-copy"><span>POS / New Order</span><span>Build a counter sale from the active database menu.</span></span>
            <span class="material-symbols-outlined staff-dashboard-tool-arrow" aria-hidden="true">arrow_forward</span>
        </a>
        <a class="card staff-dashboard-tool" href="orders.php">
            <span class="staff-dashboard-tool-icon material-symbols-outlined" aria-hidden="true">receipt_long</span>
            <span class="card-link-copy"><span>Manage Orders</span><span>Review details and advance valid order statuses.</span></span>
            <span class="material-symbols-outlined staff-dashboard-tool-arrow" aria-hidden="true">arrow_forward</span>
        </a>
        <a class="card staff-dashboard-tool" href="reports.php">
            <span class="staff-dashboard-tool-icon material-symbols-outlined" aria-hidden="true">monitoring</span>
            <span class="card-link-copy"><span>My Sales</span><span>View completed sales attributed to your account.</span></span>
            <span class="material-symbols-outlined staff-dashboard-tool-arrow" aria-hidden="true">arrow_forward</span>
        </a>
        <a class="card staff-dashboard-tool" href="change_password.php">
            <span class="staff-dashboard-tool-icon material-symbols-outlined" aria-hidden="true">lock</span>
            <span class="card-link-copy"><span>Change Password</span><span>Update your account password securely.</span></span>
            <span class="material-symbols-outlined staff-dashboard-tool-arrow" aria-hidden="true">arrow_forward</span>
        </a>
    </div>
</nav>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
