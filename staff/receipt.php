<?php
declare(strict_types=1);

header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/receipt_records.php';
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

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    receipt_error_page(405, 'This receipt page accepts GET and POST requests.', $returnUrl);
}

$rawOrderId = isset($_GET['order_id']) && is_string($_GET['order_id']) ? $_GET['order_id'] : '';
$orderId = filter_var($rawOrderId, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
]);
if ($orderId === false) {
    receipt_error_page(400, 'A valid order number is required.', $returnUrl);
}

$receiptActionError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_request();
    if (!csrf_token_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        receipt_error_page(403, 'This form expired. Refresh the page and try again.', $returnUrl);
    }
    $action = isset($_POST['action']) && is_string($_POST['action'])
        ? $_POST['action']
        : '';
    $orNumber = isset($_POST['or_number']) && is_string($_POST['or_number'])
        ? $_POST['or_number']
        : '';
    if ($action !== 'issue_receipt') {
        $receiptActionError = 'That receipt action is invalid.';
    } else {
        try {
            receipt_records_issue($pdo, (int) $orderId, (int) $user['staff_id'], $orNumber);
            header(
                'Location: receipt.php?order_id=' . (int) $orderId .
                '&auto=1&return=' . rawurlencode($returnMode) . '&issued=1',
                true,
                303
            );
            exit;
        } catch (InvalidArgumentException $exception) {
            $receiptActionError = $exception->getMessage();
        } catch (DomainException $exception) {
            $receiptActionError = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('[staff-receipt] Receipt issuance failed.');
            $receiptActionError = 'The receipt could not be issued. Check the OR number and try again.';
        }
    }
}

try {
    $orderStatement = $pdo->prepare(
        'SELECT orders.order_id, orders.order_reference, orders.customer_name,
                orders.order_type, orders.table_label,
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

    $activeReceiptStatement = $pdo->prepare(
        "SELECT receipt_id, or_number, issued_at, receipt_snapshot
         FROM receipt_records
         WHERE order_id = :order_id AND status = 'Active'
         LIMIT 1"
    );
    $activeReceiptStatement->execute(['order_id' => (int) $orderId]);
    $receiptRecord = $activeReceiptStatement->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($receiptRecord !== null) {
        $snapshot = json_decode((string) $receiptRecord['receipt_snapshot'], true, 512, JSON_THROW_ON_ERROR);
        if (
            !isset($snapshot['order'], $snapshot['items'])
            || !is_array($snapshot['order'])
            || !is_array($snapshot['items'])
        ) {
            throw new UnexpectedValueException('The stored receipt snapshot is incomplete.');
        }
        $order = $snapshot['order'];
        $itemsById = $snapshot['items'];
    }
} catch (Throwable $exception) {
    error_log('[staff-receipt] Receipt lookup failed.');
    receipt_error_page(500, 'The receipt could not be loaded. Please try again.', $returnUrl);
}

$receiptDate = $receiptRecord !== null
    ? $receiptRecord['issued_at']
    : ($order['paid_at'] !== null ? $order['paid_at'] : $order['created_at']);
$receiptTimestamp = strtotime((string) $receiptDate);
$autoPrint = $receiptRecord !== null && isset($_GET['auto']) && $_GET['auto'] === '1';
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
        .issue-panel { width: min(100%, 560px); margin: 24px auto; padding: 24px; background: #fff; }
        .issue-panel label { display: block; margin: 12px 0; }
        .issue-panel input { display: block; width: 100%; min-height: 44px; margin-top: 4px; padding: 8px; }
        .issue-panel button { min-height: 44px; padding: 10px 16px; border: 0; border-radius: 4px; background: #3b1f14; color: #fff; font: inherit; cursor: pointer; }
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
        <?php if ($receiptRecord !== null): ?>
            <button type="button" id="print-receipt">Print receipt</button>
        <?php endif; ?>
        <a href="<?= escape_html($returnUrl) ?>">Back</a>
    </nav>
    <?php if ($receiptRecord === null): ?>
        <main class="issue-panel">
            <h1>Issue a receipt</h1>
            <?php if ($receiptActionError !== ''): ?>
                <p role="alert"><?= escape_html($receiptActionError) ?></p>
            <?php endif; ?>
            <p>Enter an OR number that has already been issued through your authorized process.</p>
            <p>Order Tracking No.: <?= escape_html((string) $order['order_reference']) ?></p>
            <p>Order #<?= escape_html((string) $order['order_id']) ?> · Total ₱<?= escape_html((string) $order['total_amount']) ?></p>
            <form method="post" action="receipt.php?order_id=<?= (int) $orderId ?>&return=<?= escape_html($returnMode) ?>">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <input type="hidden" name="action" value="issue_receipt">
                <label>Issued OR number
                    <input type="text" name="or_number" maxlength="50" required autocomplete="off">
                </label>
                <button type="submit">Save OR number and print</button>
            </form>
            <p>This receipt layout does not itself certify legal or BIR compliance.</p>
        </main>
    <?php else: ?>
    <main class="receipt">
        <header class="center">
            <h1>M&amp;E KAPE CUP</h1>
            <p>Artisanal Brew &amp; Comforts</p>
        </header>
        <hr class="rule">
        <p>Order #<?= escape_html((string) $order['order_id']) ?></p>
        <p>OR No.: <?= escape_html((string) $receiptRecord['or_number']) ?></p>
        <p>Order Tracking No.: <?= escape_html((string) $order['order_reference']) ?></p>
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
    <?php endif; ?>
    <script>
        (function () {
            var autoPrint = <?= $autoPrint ? 'true' : 'false' ?>;
            var returnUrl = <?= json_encode($returnUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
            var printButton = document.getElementById('print-receipt');
            if (printButton) {
                printButton.addEventListener('click', function () {
                    window.print();
                });
            }
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
