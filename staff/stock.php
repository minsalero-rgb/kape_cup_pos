<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/shift.php';

require_role(['staff', 'admin'], $pdo);
$user = current_staff_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_request();
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Your request could not be verified. Refresh the page and try again.');
    }

    $itemId = isset($_POST['item_id']) && is_string($_POST['item_id'])
        ? filter_var($_POST['item_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $change = isset($_POST['quantity_change']) && is_string($_POST['quantity_change'])
        ? filter_var($_POST['quantity_change'], FILTER_VALIDATE_INT, ['options' => ['min_range' => -1000000, 'max_range' => 1000000]])
        : false;
    $reason = isset($_POST['reason']) && is_string($_POST['reason'])
        ? trim($_POST['reason'])
        : '';

    if ($itemId === false || $change === false || $change === 0 || $reason === '' || strlen($reason) > 255) {
        set_flash_message('error', 'Choose an item, enter a non-zero quantity adjustment, and provide a reason.');
    } else {
        try {
            $pdo->beginTransaction();
            $itemStatement = $pdo->prepare(
                'SELECT stock_quantity
                 FROM menu_items
                 WHERE item_id = :item_id
                   AND is_active = 1
                 FOR UPDATE'
            );
            $itemStatement->execute(['item_id' => (int) $itemId]);
            $currentQuantity = $itemStatement->fetchColumn();
            if ($currentQuantity === false) {
                throw new RuntimeException('That active menu item could not be found.');
            }
            $updatedQuantity = (int) $currentQuantity + (int) $change;
            if ($updatedQuantity < 0) {
                throw new RuntimeException('Stock cannot be reduced below zero.');
            }

            $updateStatement = $pdo->prepare(
                'UPDATE menu_items SET stock_quantity = :stock_quantity WHERE item_id = :item_id'
            );
            $updateStatement->execute([
                'stock_quantity' => $updatedQuantity,
                'item_id' => (int) $itemId,
            ]);
            $logStatement = $pdo->prepare(
                'INSERT INTO inventory_stock_logs
                    (item_id, staff_id, order_id, quantity_change, reason)
                 VALUES
                    (:item_id, :staff_id, NULL, :quantity_change, :reason)'
            );
            $logStatement->execute([
                'item_id' => (int) $itemId,
                'staff_id' => (int) $user['staff_id'],
                'quantity_change' => (int) $change,
                'reason' => $reason,
            ]);
            $pdo->commit();
            set_flash_message('success', 'Inventory updated and recorded.');
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash_message('error', $exception->getMessage());
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[staff-stock] Inventory adjustment failed.');
            set_flash_message('error', 'Inventory could not be updated. Try again.');
        }
    }

    header('Location: stock.php', true, 303);
    exit;
}

$successMessage = get_flash_message('success');
$errorMessage = get_flash_message('error');
$items = $pdo->query(
    'SELECT menu_items.item_id, menu_items.name, menu_items.stock_quantity,
            menu_items.low_stock_threshold, menu_items.is_sold_out,
            menu_items.is_active, categories.name AS category_name
     FROM menu_items
     INNER JOIN categories ON categories.category_id = menu_items.category_id
     ORDER BY categories.sort_order, categories.name, menu_items.name'
)->fetchAll(PDO::FETCH_ASSOC);
$logs = $pdo->query(
    'SELECT inventory_stock_logs.quantity_change, inventory_stock_logs.reason,
            inventory_stock_logs.created_at, menu_items.name AS item_name,
            staff.full_name
     FROM inventory_stock_logs
     INNER JOIN menu_items ON menu_items.item_id = inventory_stock_logs.item_id
     INNER JOIN staff ON staff.staff_id = inventory_stock_logs.staff_id
     ORDER BY inventory_stock_logs.created_at DESC, inventory_stock_logs.log_id DESC
     LIMIT 25'
)->fetchAll(PDO::FETCH_ASSOC);
$role = $user['role'];
$pageTitle = 'Stock & Items';
$pageEyebrow = $role === 'admin' ? 'Administration' : 'Barista cockpit';
$activeNav = 'stock';
$pageDescription = 'On-hand quantities and every adjustment are recorded in the POS database.';
require __DIR__ . '/../includes/layout/header.php';
?>
<?php if ($successMessage !== null): ?><p class="alert alert--success" role="status"><?= escape_html($successMessage) ?></p><?php endif; ?>
<?php if ($errorMessage !== null): ?><p class="alert alert--danger" role="alert"><?= escape_html($errorMessage) ?></p><?php endif; ?>
<section class="card">
    <div class="app-section-heading">
        <div>
            <p class="app-eyebrow">Inventory</p>
            <h2>On-hand stock</h2>
        </div>
        <span class="status-badge"><?= count($items) ?> items</span>
    </div>
    <div class="app-table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Category</th><th>On hand</th><th>Low at</th><th>Availability</th><th>Adjust stock</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= escape_html($item['name']) ?></td>
                    <td><?= escape_html($item['category_name']) ?></td>
                    <td><?= (int) $item['stock_quantity'] ?></td>
                    <td><?= (int) $item['low_stock_threshold'] ?></td>
                    <td>
                        <?php if ((int) $item['is_active'] !== 1): ?>
                            <span class="status-badge">Inactive</span>
                        <?php elseif ((int) $item['is_sold_out'] === 1 || (int) $item['stock_quantity'] === 0): ?>
                            <span class="status-badge is-danger">Unavailable</span>
                        <?php elseif ((int) $item['stock_quantity'] <= (int) $item['low_stock_threshold']): ?>
                            <span class="status-badge is-warning">Low stock</span>
                        <?php else: ?>
                            <span class="status-badge is-success">In stock</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $item['is_active'] === 1): ?>
                            <form method="post" action="stock.php" class="stock-adjust-form">
                                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                <label>
                                    <span class="sr-only">Quantity change for <?= escape_html($item['name']) ?></span>
                                    <input type="number" name="quantity_change" min="-1000000" max="1000000" step="1" required>
                                </label>
                                <label>
                                    <span class="sr-only">Reason for <?= escape_html($item['name']) ?></span>
                                    <input type="text" name="reason" maxlength="255" placeholder="Reason" required>
                                </label>
                                <button type="submit" class="btn-secondary">Save</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">Activate item to adjust stock.</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?><tr><td colspan="6">No menu items are configured.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="card mt-space-lg">
    <h2>Recent stock activity</h2>
    <div class="app-table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Item</th><th>Change</th><th>Reason</th><th>Recorded by</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= escape_html(date('M j, Y g:i A', strtotime((string) $log['created_at']))) ?></td>
                    <td><?= escape_html($log['item_name']) ?></td>
                    <td><?= (int) $log['quantity_change'] > 0 ? '+' : '' ?><?= (int) $log['quantity_change'] ?></td>
                    <td><?= escape_html($log['reason']) ?></td>
                    <td><?= escape_html($log['full_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($logs === []): ?><tr><td colspan="5">No stock adjustments recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
