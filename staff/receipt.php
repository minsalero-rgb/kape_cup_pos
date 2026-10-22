<?php
declare(strict_types=1);

header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_role(['staff', 'admin'], $pdo);
$user = current_staff_user();

$returnMode = isset($_GET['return']) && is_string($_GET['return']) && in_array($_GET['return'], ['orders', 'pos'], true)
    ? $_GET['return']
    : 'orders';
$returnUrl = $user['role'] === 'admin'
    ? '../admin/dashboard.php?tab=orders'
    : ($returnMode === 'pos' ? 'offline_pos.php?page=pos' : 'orders.php');

function receipt_error_page(int $statusCode, string $message, string $returnUrl): void
{
    http_response_code($statusCode);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Receipt unavailable | M&amp;E Kape Cup</title>
        <style>
            @font-face { font-family: Poppins; font-style: normal; font-weight: 400; src: url("../assets/fonts/poppins-regular.woff2") format("woff2"); }
            @font-face { font-family: Poppins; font-style: normal; font-weight: 700; src: url("../assets/fonts/poppins-bold.woff2") format("woff2"); }
            body { margin: 0; padding: 24px; background: #f4f1ed; color: #211a16; font: 16px/1.5 Poppins, sans-serif; }
            main { box-sizing: border-box; width: min(100%, 480px); margin: 10vh auto; padding: 24px; background: #fff; }
            a { color: #3b1f14; }
        </style>
    </head>
    <body>
        <main>
            <p><?= escape_html($message) ?></p>
            <a href="<?= escape_html($returnUrl) ?>">Back</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    receipt_error_page(405, 'Receipts can only be viewed with a GET request.', $returnUrl);
}

$rawOrderId = isset($_GET['order_id']) && is_string($_GET['order_id']) ? $_GET['order_id'] : '';
$orderId = filter_var($rawOrderId, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
]);
if ($orderId === false) {
    receipt_error_page(400, 'A valid order number is required.', $returnUrl);
}

try {
    $orderStatement = $pdo->prepare(
        'SELECT orders.order_id, orders.customer_name, orders.order_type, orders.table_label,
                orders.payment_method, orders.total_amount, orders.cash_tendered,
                orders.change_due, orders.status, orders.created_at, orders.paid_at,
                staff.full_name AS cashier_name
         FROM orders
         INNER JOIN staff ON staff.staff_id = orders.staff_id
         WHERE orders.order_id = :order_id
         LIMIT 1'
    );
    $orderStatement->execute(['order_id' => (int) $orderId]);
    $order = $orderStatement->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        receipt_error_page(404, 'That order could not be found.', $returnUrl);
    }
    if ($order['status'] === 'Pending') {
        receipt_error_page(409, 'A receipt is available only after payment is confirmed.', $returnUrl);
    }

    $itemStatement = $pdo->prepare(
        'SELECT order_items.order_item_id, order_items.item_name_snapshot,
                order_items.quantity, order_items.line_total_snapshot, order_items.notes,
                order_item_modifiers.group_name_snapshot,
                order_item_modifiers.option_name_snapshot,
                order_item_modifiers.price_delta_snapshot
         FROM order_items
         LEFT JOIN order_item_modifiers
            ON order_item_modifiers.order_item_id = order_items.order_item_id
         WHERE order_items.order_id = :order_id
         ORDER BY order_items.order_item_id ASC,
                  order_item_modifiers.order_item_modifier_id ASC'
    );
    $itemStatement->execute(['order_id' => (int) $orderId]);
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
} catch (Throwable $exception) {
    error_log('[staff-receipt] Receipt lookup failed.');
    receipt_error_page(500, 'The receipt could not be loaded. Please try again.', $returnUrl);
}

$receiptDate = $order['paid_at'] !== null ? $order['paid_at'] : $order['created_at'];
$receiptTimestamp = strtotime((string) $receiptDate);
$autoPrint = isset($_GET['auto']) && $_GET['auto'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #<?= escape_html((string) $order['order_id']) ?> | M&amp;E Kape Cup</title>
    <style>
        @font-face { font-family: Poppins; font-style: normal; font-weight: 400; src: url("../assets/fonts/poppins-regular.woff2") format("woff2"); }
        @font-face { font-family: Poppins; font-style: normal; font-weight: 700; src: url("../assets/fonts/poppins-bold.woff2") format("woff2"); }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px 12px; background: #f4f1ed; color: #211a16; font: 12px/1.45 Poppins, sans-serif; }
        .toolbar { display: flex; justify-content: center; align-items: center; gap: 12px; margin: 0 auto 16px; }
        .toolbar button, .toolbar a { min-height: 44px; padding: 10px 16px; border: 1px solid #3b1f14; border-radius: 4px; background: #3b1f14; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .toolbar a { background: #fff; color: #3b1f14; }
        .receipt { width: 72mm; max-width: calc(100vw - 24px); margin: 0 auto; padding: 6mm 5mm; background: #fff; box-shadow: 0 2px 14px rgb(0 0 0 / 12%); overflow-wrap: anywhere; }
        .center { text-align: center; }
        h1 { margin: 0; font-size: 16px; letter-spacing: 0.04em; }
        p { margin: 3px 0; }
        .rule { margin: 10px 0; border: 0; border-top: 1px dashed #211a16; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .row span:last-child { text-align: right; white-space: nowrap; }
        .item { margin: 8px 0; }
        .detail { padding-left: 12px; }
        .total { font-size: 14px; font-weight: 700; }
        .footer { margin-top: 14px; }
        @page { margin: 4mm; }
        @media print {
            body { padding: 0; background: #fff; }
            .toolbar { display: none; }
            .receipt { width: 72mm; max-width: none; margin: 0; padding: 0; box-shadow: none; background: #fff; }
        }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="Receipt actions">
        <button type="button" id="print-receipt">Print receipt</button>
        <a href="<?= escape_html($returnUrl) ?>">Back</a>
    </nav>
    <main class="receipt">
        <header class="center">
            <h1>M&amp;E KAPE CUP</h1>
            <p>Artisanal Brew &amp; Comforts</p>
        </header>
        <hr class="rule">
        <p>Order #<?= escape_html((string) $order['order_id']) ?></p>
        <p><?= escape_html($receiptTimestamp === false ? (string) $receiptDate : date('M j, Y g:i A', $receiptTimestamp)) ?></p>
        <p>Customer: <?= escape_html($order['customer_name']) ?></p>
        <p>
            <?= escape_html($order['order_type']) ?><?php if ($order['order_type'] === 'Dine-In' && $order['table_label'] !== null): ?> · Table <?= escape_html($order['table_label']) ?><?php endif; ?>
        </p>
        <p>Served by: <?= escape_html($order['cashier_name']) ?></p>
        <hr class="rule">
        <?php foreach ($itemsById as $item): ?>
            <section class="item">
                <div class="row">
                    <span><?= escape_html((string) $item['quantity']) ?> × <?= escape_html($item['name']) ?></span>
                    <span>₱<?= escape_html(number_format((float) $item['line_total'], 2)) ?></span>
                </div>
                <?php foreach ($item['modifiers'] as $modifier): ?>
                    <?php $modifierDelta = (float) $modifier['price_delta']; ?>
                    <p class="detail">+ <?= escape_html($modifier['group']) ?>: <?= escape_html($modifier['option']) ?> (<?= $modifierDelta < 0 ? '-' : '+' ?>₱<?= escape_html(number_format(abs($modifierDelta), 2)) ?>)</p>
                <?php endforeach; ?>
                <?php if ($item['notes'] !== null && $item['notes'] !== ''): ?>
                    <p class="detail">Note: <?= escape_html($item['notes']) ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
        <hr class="rule">
        <div class="row total">
            <span>TOTAL</span>
            <span>₱<?= escape_html(number_format((float) $order['total_amount'], 2)) ?></span>
        </div>
        <hr class="rule">
        <p>Payment: <?= escape_html($order['payment_method']) ?></p>
        <?php if ($order['payment_method'] === 'Cash'): ?>
            <p>Cash tendered: ₱<?= escape_html(number_format((float) $order['cash_tendered'], 2)) ?></p>
            <p>Change: ₱<?= escape_html(number_format((float) $order['change_due'], 2)) ?></p>
        <?php endif; ?>
        <p class="center footer">Thank you for your order!</p>
    </main>
    <script>
        (function () {
            var autoPrint = <?= $autoPrint ? 'true' : 'false' ?>;
            var returnUrl = <?= json_encode($returnUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
            document.getElementById('print-receipt').addEventListener('click', function () {
                window.print();
            });
            if (autoPrint) {
                window.addEventListener('afterprint', function () {
                    window.location.href = returnUrl;
                });
                window.addEventListener('load', function () {
                    window.setTimeout(function () {
                        window.print();
                    }, 250);
                });
            }
        }());
    </script>
</body>
</html>
