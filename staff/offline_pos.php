<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/order_submission.php';
require_once __DIR__ . '/../includes/order_idempotency.php';
require_once __DIR__ . '/../includes/order_workflow.php';
require_once __DIR__ . '/../includes/shift.php';

require_role('staff', $pdo);
$user = current_staff_user();
$basePath = app_base_path();

$inventoryColumnsStatement = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'menu_items'
       AND COLUMN_NAME IN ('stock_quantity', 'low_stock_threshold')"
);
$hasInventoryColumns = (int) $inventoryColumnsStatement->fetchColumn() === 2;
$inventoryLogTableStatement = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'inventory_stock_logs'"
);
$hasInventoryLogTable = (int) $inventoryLogTableStatement->fetchColumn() === 1;
if (!$hasInventoryColumns || !$hasInventoryLogTable) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Database Upgrade Required | M&amp;E Kape Cup</title>
        <link rel="stylesheet" href="<?= escape_html($basePath) ?>/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    </head>
    <body>
        <main class="app-main">
            <section class="card">
                <p class="app-eyebrow">Setup required</p>
                <h1>Database upgrade required</h1>
                <p>The POS inventory tables are not installed in the configured database.</p>
                <p>Ask a MySQL administrator to import <code>database/migrations/003_inventory.sql</code> into the configured application database. The application account cannot apply schema changes.</p>
                <p>After the migration is applied, reload this page and enter confirmed opening stock quantities in Stock &amp; Items.</p>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$page = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'pos';
$pageRoutes = [
    'queue' => 'orders.php',
    'reports' => 'reports.php',
    'stock' => 'stock.php',
];
if (isset($pageRoutes[$page])) {
    header('Location: ' . $basePath . '/staff/' . $pageRoutes[$page], true, 302);
    exit;
}
if ($page === 'customer') {
    header('Location: ' . $basePath . '/menu.php', true, 302);
    exit;
}
if ($page !== 'pos') {
    header('Location: ' . $basePath . '/staff/offline_pos.php?page=pos', true, 302);
    exit;
}

$activeShiftIds = order_queue_active_shift_ids($pdo, (int) $user['staff_id']);
$hasOneActiveShift = count($activeShiftIds) === 1;
$cart = isset($_SESSION['pos_cart']) && is_array($_SESSION['pos_cart'])
    ? $_SESSION['pos_cart']
    : [];
$posCartFingerprint = hash('sha256', serialize($cart));
if (
    !isset($_SESSION['pos_submission_key'])
    || !is_string($_SESSION['pos_submission_key'])
    || preg_match('/\A[a-f0-9]{64}\z/', $_SESSION['pos_submission_key']) !== 1
    || !isset($_SESSION['pos_submission_cart_hash'])
    || !is_string($_SESSION['pos_submission_cart_hash'])
    || !hash_equals($_SESSION['pos_submission_cart_hash'], $posCartFingerprint)
) {
    $_SESSION['pos_submission_key'] = bin2hex(random_bytes(32));
    $_SESSION['pos_submission_cart_hash'] = $posCartFingerprint;
}
$posSubmissionKey = $_SESSION['pos_submission_key'];
$successMessage = get_flash_message('success');
$errorMessage = get_flash_message('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_request();
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Your request could not be verified. Refresh the page and try again.');
    }

    $action = isset($_POST['action']) && is_string($_POST['action'])
        ? $_POST['action']
        : '';
    try {
        if ($action === 'add') {
            if (!$hasOneActiveShift) {
                throw new OrderSubmissionException('active_shift_required', 'Start exactly one Staff shift before adding POS orders.');
            }
            $itemId = isset($_POST['item_id']) && is_string($_POST['item_id'])
                ? filter_var($_POST['item_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            $quantity = isset($_POST['quantity']) && is_string($_POST['quantity'])
                ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]])
                : false;
            $notes = isset($_POST['notes']) && is_string($_POST['notes'])
                ? trim($_POST['notes'])
                : '';
            $rawGroups = isset($_POST['modifier_groups']) && is_array($_POST['modifier_groups'])
                ? $_POST['modifier_groups']
                : [];
            if ($itemId === false || $quantity === false || strlen($notes) > 500 || count($rawGroups) > 50) {
                throw new OrderSubmissionException('invalid_cart', 'Choose a valid item, quantity, modifiers, and note.');
            }

            $modifierOptionIds = [];
            foreach ($rawGroups as $rawOptions) {
                if (!is_array($rawOptions) || count($rawOptions) > 50) {
                    throw new OrderSubmissionException('invalid_modifier', 'A modifier selection is invalid.');
                }
                foreach ($rawOptions as $rawOptionId) {
                    if (!is_string($rawOptionId) && !is_int($rawOptionId)) {
                        throw new OrderSubmissionException('invalid_modifier', 'A modifier selection is invalid.');
                    }
                    $optionId = filter_var((string) $rawOptionId, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1],
                    ]);
                    if ($optionId === false || in_array((int) $optionId, $modifierOptionIds, true)) {
                        throw new OrderSubmissionException('invalid_modifier', 'A modifier selection is invalid.');
                    }
                    $modifierOptionIds[] = (int) $optionId;
                }
            }
            sort($modifierOptionIds, SORT_NUMERIC);

            $itemStatement = $pdo->prepare(
                'SELECT item_id, name, stock_quantity
                 FROM menu_items
                 WHERE item_id = :item_id
                   AND is_active = 1
                   AND is_sold_out = 0
                   AND stock_quantity > 0
                 LIMIT 1'
            );
            $itemStatement->execute(['item_id' => (int) $itemId]);
            $menuItem = $itemStatement->fetch(PDO::FETCH_ASSOC);
            if (!$menuItem) {
                throw new OrderSubmissionException('item_unavailable', 'This item is unavailable or out of stock.');
            }

            $key = hash('sha256', serialize([(int) $itemId, $modifierOptionIds, $notes]));
            $currentQuantity = isset($cart[$key]['quantity']) ? (int) $cart[$key]['quantity'] : 0;
            if ($currentQuantity + (int) $quantity > 99
                || $currentQuantity + (int) $quantity > (int) $menuItem['stock_quantity']) {
                throw new OrderSubmissionException(
                    'item_unavailable',
                    'Only ' . (int) $menuItem['stock_quantity'] . ' ' . (string) $menuItem['name'] . ' available.'
                );
            }

            $cart[$key] = [
                'item_id' => (int) $itemId,
                'modifier_option_ids' => $modifierOptionIds,
                'quantity' => $currentQuantity + (int) $quantity,
                'notes' => $notes,
            ];
            $_SESSION['pos_cart'] = $cart;
            set_flash_message('success', (string) $menuItem['name'] . ' added to the current order.');
        } elseif ($action === 'update') {
            $key = isset($_POST['key']) && is_string($_POST['key']) ? $_POST['key'] : '';
            $quantity = isset($_POST['quantity']) && is_string($_POST['quantity'])
                ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]])
                : false;
            if (!isset($cart[$key]) || preg_match('/^[a-f0-9]{64}$/', $key) !== 1 || $quantity === false) {
                throw new OrderSubmissionException('invalid_cart', 'The cart quantity is invalid.');
            }
            if ($quantity === 0) {
                unset($cart[$key]);
            } else {
                $cart[$key]['quantity'] = (int) $quantity;
            }
            $_SESSION['pos_cart'] = $cart;
        } elseif ($action === 'remove') {
            $key = isset($_POST['key']) && is_string($_POST['key']) ? $_POST['key'] : '';
            if (!isset($cart[$key]) || preg_match('/^[a-f0-9]{64}$/', $key) !== 1) {
                throw new OrderSubmissionException('invalid_cart', 'The cart item could not be found.');
            }
            unset($cart[$key]);
            $_SESSION['pos_cart'] = $cart;
        } elseif ($action === 'clear') {
            $cart = [];
            $_SESSION['pos_cart'] = [];
        } elseif ($action === 'checkout') {
            $submissionKey = isset($_POST['submission_key']) && is_string($_POST['submission_key'])
                ? $_POST['submission_key']
                : '';
            if (preg_match('/\A[a-f0-9]{64}\z/', $submissionKey) !== 1) {
                throw new OrderSubmissionException('invalid_submission_key', 'Refresh the POS and try submitting this order again.');
            }
            $submissionSessionKey = hash('sha256', session_id());
            $existingOrder = find_order_submission($pdo, $submissionSessionKey, $submissionKey);
            if ($existingOrder !== null) {
                if (hash_equals((string) $existingOrder['cart_hash'], $posCartFingerprint)) {
                    $_SESSION['pos_cart'] = [];
                    $cart = [];
                    $_SESSION['pos_submission_key'] = bin2hex(random_bytes(32));
                    $_SESSION['pos_submission_cart_hash'] = hash('sha256', serialize([]));
                }
                set_flash_message(
                    'success',
                    'Order #' . (int) $existingOrder['order_id'] . ' was already completed. Total: ₱' . (string) $existingOrder['total_amount'] . '.'
                );
            } else {
            if (!$hasOneActiveShift || $cart === []) {
                throw new OrderSubmissionException('active_shift_required', 'An active Staff shift and a non-empty order are required.');
            }
            $paymentMethod = isset($_POST['payment_method']) && is_string($_POST['payment_method'])
                ? $_POST['payment_method']
                : '';
            if (!in_array($paymentMethod, ['Cash', 'GCash', 'Credit Card'], true)) {
                throw new OrderSubmissionException('invalid_payment_method', 'Choose a valid payment method.');
            }
            $cashTenderedCents = null;
            if ($paymentMethod === 'Cash') {
                $cashTenderedCents = isset($_POST['cash_tendered']) && is_string($_POST['cash_tendered'])
                    ? order_money_to_cents($_POST['cash_tendered'])
                    : null;
                if ($cashTenderedCents === null || $cashTenderedCents < 0) {
                    throw new OrderSubmissionException('invalid_cash_tendered', 'Enter a valid cash amount.');
                }
            }

            $pdo->beginTransaction();
            reserve_order_submission($pdo, $submissionSessionKey, $submissionKey, $posCartFingerprint);
            $lockedShiftIds = order_queue_active_shift_ids($pdo, (int) $user['staff_id'], true);
            if (count($lockedShiftIds) !== 1) {
                throw new OrderSubmissionException('active_shift_required', 'Exactly one active Staff shift is required to complete an order.');
            }
            $preparedCart = prepare_order_cart($pdo, array_values($cart), true);
            if ($paymentMethod === 'Cash' && $cashTenderedCents < $preparedCart['total_cents']) {
                throw new OrderSubmissionException('insufficient_cash', 'Cash received must cover the order total.');
            }

            $changeCents = $paymentMethod === 'Cash'
                ? $cashTenderedCents - $preparedCart['total_cents']
                : null;
            $orderStatement = $pdo->prepare(
                'INSERT INTO orders
                    (customer_name, order_type, table_label, payment_method, total_amount,
                     cash_tendered, change_due, status, staff_id, shift_id, paid_at)
                 VALUES
                    (:customer_name, \'Take-Out\', NULL, :payment_method, :total_amount,
                     :cash_tendered, :change_due, \'Paid\', :staff_id, :shift_id, CURRENT_TIMESTAMP)'
            );
            $orderStatement->execute([
                'customer_name' => 'Walk-in customer',
                'payment_method' => $paymentMethod,
                'total_amount' => $preparedCart['total_amount'],
                'cash_tendered' => $cashTenderedCents === null ? null : order_money_from_cents($cashTenderedCents),
                'change_due' => $changeCents === null ? null : order_money_from_cents($changeCents),
                'staff_id' => (int) $user['staff_id'],
                'shift_id' => (int) $lockedShiftIds[0],
            ]);
            $orderId = (int) $pdo->lastInsertId();
            insert_prepared_order_items($pdo, $orderId, $preparedCart);
            deduct_order_inventory($pdo, $preparedCart, (int) $user['staff_id'], $orderId);
            complete_order_submission($pdo, $submissionSessionKey, $submissionKey, $orderId);
            $pdo->commit();

            $_SESSION['pos_cart'] = [];
            $_SESSION['pos_submission_key'] = bin2hex(random_bytes(32));
            $_SESSION['pos_submission_cart_hash'] = hash('sha256', serialize([]));
            set_flash_message('success', 'Order #' . $orderId . ' completed. Total: ₱' . $preparedCart['total_amount'] . '.');
            }
        } else {
            throw new OrderSubmissionException('invalid_action', 'That POS action is not available.');
        }
    } catch (OrderSubmissionException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash_message('error', $exception->getMessage());
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorInfo = $exception->errorInfo;
        $existingOrder = null;
        if (is_array($errorInfo) && isset($errorInfo[1]) && (int) $errorInfo[1] === 1062
            && isset($submissionSessionKey, $submissionKey)) {
            $existingOrder = find_order_submission($pdo, $submissionSessionKey, $submissionKey);
        }
        if ($existingOrder !== null) {
            if (hash_equals((string) $existingOrder['cart_hash'], $posCartFingerprint)) {
                $_SESSION['pos_cart'] = [];
                $_SESSION['pos_submission_key'] = bin2hex(random_bytes(32));
                $_SESSION['pos_submission_cart_hash'] = hash('sha256', serialize([]));
            }
            set_flash_message(
                'success',
                'Order #' . (int) $existingOrder['order_id'] . ' was already completed. Total: ₱' . (string) $existingOrder['total_amount'] . '.'
            );
        } else {
            error_log('[staff-pos] POS database action failed.');
            set_flash_message('error', 'The POS action could not be completed. Refresh and try again.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[staff-pos] POS action failed.');
        set_flash_message('error', 'The POS action could not be completed. Refresh and try again.');
    }
    header('Location: ' . $basePath . '/staff/offline_pos.php?page=pos', true, 303);
    exit;
}

$categories = $pdo->query(
    'SELECT category_id, name
     FROM categories
     ORDER BY sort_order ASC, name ASC, category_id ASC'
)->fetchAll(PDO::FETCH_ASSOC);
$items = $pdo->query(
    'SELECT menu_items.item_id, menu_items.name, menu_items.description,
            menu_items.base_price, menu_items.stock_quantity,
            menu_items.low_stock_threshold, menu_items.is_sold_out,
            categories.name AS category_name
     FROM menu_items
     INNER JOIN categories ON categories.category_id = menu_items.category_id
     WHERE menu_items.is_active = 1
     ORDER BY categories.sort_order ASC, categories.name ASC, menu_items.name ASC'
)->fetchAll(PDO::FETCH_ASSOC);
$modifierStatement = $pdo->query(
    'SELECT groups.item_id, groups.modifier_group_id, groups.name AS group_name,
            groups.is_required, groups.min_selections, groups.max_selections,
            options.modifier_option_id, options.name AS option_name, options.price_delta
     FROM menu_modifier_groups AS groups
     LEFT JOIN menu_modifier_options AS options
       ON options.modifier_group_id = groups.modifier_group_id
      AND options.is_active = 1
     WHERE groups.is_active = 1
     ORDER BY groups.item_id, groups.sort_order, groups.modifier_group_id,
              options.sort_order, options.modifier_option_id'
);
$modifierGroups = [];
foreach ($modifierStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $itemKey = (string) $row['item_id'];
    $groupKey = (string) $row['modifier_group_id'];
    if (!isset($modifierGroups[$itemKey][$groupKey])) {
        $modifierGroups[$itemKey][$groupKey] = [
            'id' => (int) $row['modifier_group_id'],
            'name' => (string) $row['group_name'],
            'is_required' => (int) $row['is_required'] === 1,
            'min_selections' => (int) $row['min_selections'],
            'max_selections' => (int) $row['max_selections'],
            'options' => [],
        ];
    }
    if ($row['modifier_option_id'] !== null) {
        $modifierGroups[$itemKey][$groupKey]['options'][] = [
            'id' => (int) $row['modifier_option_id'],
            'name' => (string) $row['option_name'],
            'price_delta' => (string) $row['price_delta'],
        ];
    }
}

$productsById = [];
foreach ($items as $item) {
    $productsById[(string) $item['item_id']] = $item;
}
$cartLines = [];
$cartTotalCents = 0;
$cartValid = $cart !== [];
foreach ($cart as $key => $entry) {
    $itemId = isset($entry['item_id']) ? (string) $entry['item_id'] : '';
    if (!isset($productsById[$itemId])) {
        $cartLines[] = ['key' => (string) $key, 'name' => 'Unavailable item', 'error' => 'This menu item is no longer active.'];
        $cartValid = false;
        continue;
    }
    try {
        $prepared = prepare_order_cart($pdo, [$entry]);
        $line = $prepared['items'][0];
        $cartTotalCents += (int) $prepared['total_cents'];
        $cartLines[] = [
            'key' => (string) $key,
            'name' => $line['item_name_snapshot'],
            'quantity' => (int) $line['quantity'],
            'unit_price' => $line['unit_price_snapshot'],
            'line_total' => $line['line_total_snapshot'],
            'notes' => (string) ($line['notes'] ?? ''),
            'modifiers' => $line['modifiers'],
            'error' => null,
        ];
    } catch (OrderSubmissionException $exception) {
        $cartValid = false;
        $cartLines[] = [
            'key' => (string) $key,
            'name' => (string) $productsById[$itemId]['name'],
            'quantity' => isset($entry['quantity']) ? (int) $entry['quantity'] : 0,
            'error' => $exception->getMessage(),
        ];
    }
}
$cartTotal = order_money_from_cents($cartTotalCents);
$role = 'staff';
$pageTitle = 'POS Register';
$pageEyebrow = 'Barista cockpit';
$activeNav = 'pos';
$pageDescription = 'Live menu, order totals, payments, and stock are read from and saved to the POS database.';
require __DIR__ . '/../includes/layout/header.php';
?>
<?php if ($successMessage !== null): ?>
    <p class="alert alert--success" role="status"><?= escape_html($successMessage) ?></p>
<?php endif; ?>
<?php if ($errorMessage !== null): ?>
    <p class="alert alert--danger" role="alert"><?= escape_html($errorMessage) ?></p>
<?php endif; ?>
<?php if (!$hasOneActiveShift): ?>
    <p class="alert alert--warning" role="alert">Start exactly one Staff shift before placing a POS order.</p>
<?php endif; ?>
<div class="pos-workspace">
    <section aria-labelledby="pos-menu-heading">
        <div class="app-section-heading">
            <div>
                <p class="app-eyebrow">Live menu</p>
                <h2 id="pos-menu-heading">Choose an item</h2>
            </div>
            <span class="status-badge"><?= count($items) ?> active items</span>
        </div>
        <?php if ($items !== []): ?>
            <div class="pos-menu-filters">
                <label>
                    <span>Search products</span>
                    <input id="pos-product-search" type="search" placeholder="Search name or description" autocomplete="off">
                </label>
                <label>
                    <span>Category</span>
                    <select id="pos-category-filter">
                        <option value="all">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>"><?= escape_html($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        <?php endif; ?>
        <?php if ($items === []): ?>
            <p class="card">There are no active menu items. Add items through Menu Management first.</p>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <?php
                $categoryItems = array_values(array_filter(
                    $items,
                    static function (array $item) use ($category): bool {
                        return (string) $item['category_name'] === (string) $category['name'];
                    }
                ));
                if ($categoryItems === []) {
                    continue;
                }
                ?>
                <section class="pos-category" data-pos-category-section="<?= (int) $category['category_id'] ?>" aria-labelledby="pos-category-<?= (int) $category['category_id'] ?>">
                    <h3 id="pos-category-<?= (int) $category['category_id'] ?>"><?= escape_html($category['name']) ?></h3>
                    <div class="app-auto-grid">
                        <?php foreach ($categoryItems as $item): ?>
                            <?php $soldOut = (int) $item['is_sold_out'] === 1 || (int) $item['stock_quantity'] < 1; ?>
                            <article class="card pos-item-card<?= $soldOut ? ' is-sold-out' : '' ?><?= (int) $item['stock_quantity'] <= (int) $item['low_stock_threshold'] ? ' is-low-stock' : '' ?>" data-pos-item data-pos-category="<?= (int) $category['category_id'] ?>">
                                <div class="pos-item-heading">
                                    <h4><?= escape_html($item['name']) ?></h4>
                                    <strong>₱<?= escape_html($item['base_price']) ?></strong>
                                </div>
                                <?php if ($item['description'] !== null && $item['description'] !== ''): ?>
                                    <p class="pos-item-description"><?= escape_html($item['description']) ?></p>
                                <?php endif; ?>
                                <p class="pos-stock<?= $soldOut ? ' is-out' : ((int) $item['stock_quantity'] <= (int) $item['low_stock_threshold'] ? ' is-low' : '') ?>">
                                    <?= $soldOut ? 'Out of stock' : (int) $item['stock_quantity'] . ' in stock' ?>
                                </p>
                                <?php if (!$soldOut && $hasOneActiveShift): ?>
                                    <form method="post" action="offline_pos.php?page=pos" class="pos-add-form">
                                        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                        <?php if (isset($modifierGroups[(string) $item['item_id']])): ?>
                                            <details class="pos-options">
                                                <summary>Options</summary>
                                                <?php foreach ($modifierGroups[(string) $item['item_id']] as $group): ?>
                                                    <fieldset>
                                                        <legend><?= escape_html($group['name']) ?><?= $group['is_required'] ? ' (required)' : '' ?></legend>
                                                        <small>Choose <?= $group['min_selections'] ?>–<?= $group['max_selections'] ?></small>
                                                        <?php foreach ($group['options'] as $option): ?>
                                                            <label>
                                                                <input type="checkbox" name="modifier_groups[<?= $group['id'] ?>][]" value="<?= $option['id'] ?>">
                                                                <span><?= escape_html($option['name']) ?></span>
                                                                <?php if ((float) $option['price_delta'] !== 0.0): ?>
                                                                    <small><?= (float) $option['price_delta'] > 0 ? '+' : '' ?>₱<?= escape_html($option['price_delta']) ?></small>
                                                                <?php endif; ?>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </fieldset>
                                                <?php endforeach; ?>
                                            </details>
                                        <?php endif; ?>
                                        <label class="pos-note-label">
                                            <span>Item note</span>
                                            <input type="text" name="notes" maxlength="500" placeholder="Optional">
                                        </label>
                                        <div class="pos-add-controls">
                                            <label>
                                                <span class="sr-only">Quantity for <?= escape_html($item['name']) ?></span>
                                                <input type="number" name="quantity" min="1" max="99" value="1" required>
                                            </label>
                                            <button type="submit" class="btn-primary">Add</button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <button class="btn-secondary" type="button" disabled><?= $soldOut ? 'Unavailable' : 'Start shift to order' ?></button>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            <p id="pos-filter-empty" class="pos-empty-cart" hidden>No products match these filters.</p>
        <?php endif; ?>
    </section>

    <aside class="card pos-cart" aria-labelledby="pos-cart-heading">
        <div class="app-section-heading">
            <div>
                <p class="app-eyebrow">Current order</p>
                <h2 id="pos-cart-heading">Walk-in sale</h2>
            </div>
            <span class="status-badge"><?= count($cartLines) ?> lines</span>
        </div>
        <?php if ($cartLines === []): ?>
            <p class="pos-empty-cart">Choose items from the live menu to start an order.</p>
        <?php else: ?>
            <div class="pos-cart-lines">
                <?php foreach ($cartLines as $line): ?>
                    <article class="pos-cart-line">
                        <div class="pos-item-heading">
                            <strong><?= escape_html($line['name']) ?></strong>
                            <?php if (isset($line['line_total'])): ?><span>₱<?= escape_html($line['line_total']) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($line['error'])): ?>
                            <p class="pos-line-error"><?= escape_html($line['error']) ?></p>
                        <?php else: ?>
                            <p class="pos-line-detail">₱<?= escape_html($line['unit_price']) ?> each</p>
                            <?php foreach ($line['modifiers'] as $modifier): ?>
                                <p class="pos-line-detail"><?= escape_html($modifier['group_name_snapshot']) ?>: <?= escape_html($modifier['option_name_snapshot']) ?></p>
                            <?php endforeach; ?>
                            <?php if ($line['notes'] !== ''): ?><p class="pos-line-detail">Note: <?= escape_html($line['notes']) ?></p><?php endif; ?>
                        <?php endif; ?>
                        <div class="pos-cart-controls">
                            <form method="post" action="offline_pos.php?page=pos">
                                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="key" value="<?= escape_html($line['key']) ?>">
                                <label>
                                    <span>Qty</span>
                                    <input type="number" name="quantity" min="1" max="99" value="<?= (int) ($line['quantity'] ?? 0) ?>">
                                </label>
                                <button type="submit" class="btn-secondary">Update</button>
                            </form>
                            <form method="post" action="offline_pos.php?page=pos" class="pos-remove-form" data-confirm="Remove this item from the order?">
                                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="key" value="<?= escape_html($line['key']) ?>">
                                <button type="submit" class="btn-ghost">Remove</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="pos-total-row"><span>Total</span><strong>₱<?= escape_html($cartTotal) ?></strong></div>
            <?php if (!$cartValid): ?>
                <p class="alert alert--warning" role="alert">Review the cart items and required options before checkout.</p>
            <?php endif; ?>
            <form method="post" action="offline_pos.php?page=pos" class="pos-checkout-form">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="submission_key" value="<?= escape_html($posSubmissionKey) ?>">
                <label>
                    <span>Payment method</span>
                    <select name="payment_method" id="pos-payment-method">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Credit Card">Credit Card</option>
                    </select>
                </label>
                <label id="pos-cash-field">
                    <span>Cash received (PHP)</span>
                    <input type="number" name="cash_tendered" min="0" step="0.01" inputmode="decimal">
                </label>
                <button type="submit" class="btn-primary" <?= !$cartValid || !$hasOneActiveShift ? 'disabled' : '' ?>>Complete sale</button>
            </form>
            <form method="post" action="offline_pos.php?page=pos" class="pos-clear-form" data-confirm="Clear every item from this order?">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn-ghost">Clear order</button>
            </form>
        <?php endif; ?>
    </aside>
</div>
<script>
(function () {
    var search = document.getElementById('pos-product-search');
    var category = document.getElementById('pos-category-filter');
    var emptyMessage = document.getElementById('pos-filter-empty');
    if (search && category && emptyMessage) {
        var itemCards = Array.prototype.slice.call(document.querySelectorAll('[data-pos-item]'));
        var categorySections = Array.prototype.slice.call(document.querySelectorAll('[data-pos-category-section]'));
        function filterProducts() {
            var term = search.value.trim().toLocaleLowerCase();
            var selectedCategory = category.value;
            var visibleCount = 0;
            itemCards.forEach(function (card) {
                var matchesCategory = selectedCategory === 'all' || card.getAttribute('data-pos-category') === selectedCategory;
                var matchesSearch = term === '' || card.textContent.toLocaleLowerCase().indexOf(term) !== -1;
                card.hidden = !(matchesCategory && matchesSearch);
                if (!card.hidden) visibleCount += 1;
            });
            categorySections.forEach(function (section) {
                section.hidden = !section.querySelector('[data-pos-item]:not([hidden])');
            });
            emptyMessage.hidden = visibleCount !== 0;
        }
        search.addEventListener('input', filterProducts);
        category.addEventListener('change', filterProducts);
    }

    var payment = document.getElementById('pos-payment-method');
    var cashField = document.getElementById('pos-cash-field');
    var cashInput = cashField ? cashField.querySelector('input') : null;
    if (!payment || !cashField || !cashInput) return;
    function updateCashField() {
        var isCash = payment.value === 'Cash';
        cashField.hidden = !isCash;
        cashInput.required = isCash;
    }
    payment.addEventListener('change', updateCashField);
    updateCashField();

    var checkoutForm = document.querySelector('.pos-checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function () {
            var submitButton = checkoutForm.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Completing sale…';
            }
        });
    }
}());
</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
