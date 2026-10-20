<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/order_workflow.php';

require_role('staff', $pdo);
$user = current_staff_user();
$activeStatuses = ['Pending', 'Paid', 'Preparing'];

function orders_redirect(string $messageKey, string $message): void
{
    set_flash_message($messageKey, $message);
    header('Location: orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_request();
    if (!csrf_token_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        exit('Your request could not be verified. Refresh the page and try again.');
    }

    $orderId = isset($_POST['order_id']) && is_string($_POST['order_id'])
        ? filter_var($_POST['order_id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ])
        : false;
    $expectedStatus = isset($_POST['expected_status']) && is_string($_POST['expected_status'])
        ? $_POST['expected_status']
        : '';

    if ($orderId === false || !in_array($expectedStatus, $activeStatuses, true)) {
        orders_redirect('error', 'That order action is invalid. Refresh the queue and try again.');
    }

    try {
        $result = advance_order_status($pdo, $user['staff_id'], (int) $orderId, $expectedStatus);
        if (!$result['success']) {
            $messages = [
                'invalid_transition' => 'Completed orders cannot be changed.',
                'active_shift_required' => 'You need exactly one active Staff shift to update orders.',
                'order_not_found' => 'That order is no longer available in the queue.',
                'status_conflict' => 'The order changed in the queue. Review its current status and try again.',
            ];
            $message = isset($messages[$result['code']])
                ? $messages[$result['code']]
                : 'The order could not be advanced. Refresh the queue and try again.';
            orders_redirect('error', $message);
        }

        orders_redirect('success', 'Order #' . (int) $orderId . ' advanced to ' . $result['status'] . '.');
    } catch (Throwable $exception) {
        error_log('[staff-orders] Order status update failed.');
        orders_redirect('error', 'The order could not be updated. Try again.');
    }
}

$shiftIds = order_queue_active_shift_ids($pdo, $user['staff_id']);
$hasOneActiveShift = count($shiftIds) === 1;
$successMessage = get_flash_message('success');
$errorMessage = get_flash_message('error');
$orders = $hasOneActiveShift ? fetch_active_order_queue($pdo) : [];
$role = 'staff';
$pageTitle = 'Kitchen Queue';
$pageEyebrow = 'Barista cockpit';
$activeNav = 'queue';
$pageDescription = 'The queue refreshes automatically when JavaScript is available; you can also refresh this page manually.';
require __DIR__ . '/../includes/layout/header.php';
?>

    <?php if ($successMessage !== null): ?>
        <p class="notice success" role="status"><?= escape_html($successMessage) ?></p>
    <?php endif; ?>
    <?php if ($errorMessage !== null): ?>
        <p class="notice" role="alert"><?= escape_html($errorMessage) ?></p>
    <?php endif; ?>
    <p id="queue-feedback" class="notice" role="status" hidden></p>

    <?php if (!$hasOneActiveShift): ?>
        <section class="notice" role="alert">Order access requires exactly one active Staff shift on your account.</section>
    <?php elseif ($orders === []): ?>
        <section class="order"><p>No active orders are waiting.</p></section>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <?php
            $nextStatus = next_order_status($order['status']);
            if ($order['status'] === 'Pending') {
                $confirmText = 'Confirm payment has been manually verified for order #' . $order['order_id'] .
                    ' (' . $order['payment_method'] . ', total ' . $order['total_amount'] . ').';
                if ($order['payment_method'] === 'Cash') {
                    $confirmText .= ' Cash tendered: ' . $order['cash_tendered'] .
                        '; change due: ' . $order['change_due'] . '.';
                }
            } else {
                $confirmText = 'Advance order #' . $order['order_id'] . ' from ' .
                    $order['status'] . ' to ' . $nextStatus . '?';
            }
            ?>
            <article class="card order">
                <div class="order-heading">
                    <h2>Order #<?= escape_html($order['order_id']) ?> — <?= escape_html($order['customer_name']) ?></h2>
                    <p class="status"><?= escape_html($order['status']) ?></p>
                </div>
                <p class="muted">
                    <?= escape_html($order['order_type']) ?>
                    <?php if ($order['order_type'] === 'Dine-In' && $order['table_label'] !== null): ?>
                        · Table <?= escape_html($order['table_label']) ?>
                    <?php endif; ?>
                    · <?= escape_html(date('M j, Y g:i A', strtotime($order['created_at']))) ?>
                </p>
                <div class="summary">
                    <span>Payment: <?= escape_html($order['payment_method']) ?></span>
                    <strong>Total: <?= escape_html($order['total_amount']) ?></strong>
                    <?php if ($order['payment_method'] === 'Cash'): ?>
                        <span>Tendered: <?= escape_html($order['cash_tendered']) ?></span>
                        <span>Change: <?= escape_html($order['change_due']) ?></span>
                    <?php endif; ?>
                </div>
                <h3>Items</h3>
                <?php foreach ($order['items'] as $item): ?>
                    <div class="item">
                        <strong><?= escape_html($item['quantity']) ?> × <?= escape_html($item['name']) ?></strong>
                        <span> — <?= escape_html($item['line_total']) ?></span>
                        <?php foreach ($item['modifiers'] as $modifier): ?>
                            <div class="muted"><?= escape_html($modifier['group']) ?>: <?= escape_html($modifier['name']) ?> (+<?= escape_html($modifier['price']) ?>)</div>
                        <?php endforeach; ?>
                        <?php if ($item['notes'] !== null && $item['notes'] !== ''): ?>
                            <div class="muted">Note: <?= escape_html($item['notes']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($nextStatus !== null): ?>
                    <form method="post" action="orders.php" data-confirm="<?= escape_html($confirmText) ?>" data-api-update="1">
                        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                        <input type="hidden" name="order_id" value="<?= escape_html($order['order_id']) ?>">
                        <input type="hidden" name="expected_status" value="<?= escape_html($order['status']) ?>">
                        <button type="submit">Advance to <?= escape_html($nextStatus) ?></button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
<script type="application/json" id="initial-order-queue"><?= json_encode(
    array_values($orders),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
) ?></script>
<script>
var queueFeedback = document.getElementById('queue-feedback');
var initialQueue = JSON.parse(document.getElementById('initial-order-queue').textContent);
var queueApiUrl = '../api/queue.php';
var advanceApiUrl = '../api/update_order_status.php';
var queueEnabled = <?= $hasOneActiveShift ? 'true' : 'false' ?>;

function showQueueFeedback(message) {
    queueFeedback.textContent = message;
    queueFeedback.hidden = false;
}

function queueSnapshot(orders) {
    return JSON.stringify(orders);
}

document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
            return;
        }

        if (!window.fetch || !form.hasAttribute('data-api-update')) {
            return;
        }

        event.preventDefault();
        var button = form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
        }
        fetch(advanceApiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': form.querySelector('[name="csrf_token"]').value
            },
            body: JSON.stringify({
                order_id: form.querySelector('[name="order_id"]').value,
                expected_status: form.querySelector('[name="expected_status"]').value
            })
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok || !body.success) {
                    throw new Error(body.error && body.error.message
                        ? body.error.message
                        : 'The order could not be updated.');
                }
                window.location.reload();
            });
        }).catch(function (error) {
            if (error instanceof TypeError || error instanceof SyntaxError) {
                form.removeAttribute('data-api-update');
                form.submit();
                return;
            }
            if (button) {
                button.disabled = false;
            }
            showQueueFeedback(error.message || 'The order update failed. Refresh and try again.');
        });
    });
});

if (window.fetch && queueEnabled) {
    window.setInterval(function () {
        fetch(queueApiUrl, {
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok || !body.success) {
                    throw new Error(body.error && body.error.message
                        ? body.error.message
                        : 'Queue refresh failed.');
                }
                if (queueSnapshot(body.data.orders) !== queueSnapshot(initialQueue)) {
                    window.location.reload();
                } else {
                    queueFeedback.hidden = true;
                }
            });
        }).catch(function () {
            showQueueFeedback('The queue could not refresh. Retrying automatically; you may also refresh this page.');
        });
    }, 5000);
}
</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
