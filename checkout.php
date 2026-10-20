<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/customer_session.php';
initialize_customer_session();

require_once __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/shift.php';
require_once __DIR__ . '/includes/order_submission.php';

$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
$cartSummary = null;
$cartError = null;
if ($cart !== []) {
    try {
        $cartSummary = prepare_order_cart($pdo, $cart);
    } catch (OrderSubmissionException $exception) {
        $cartError = $exception->getMessage();
    }
}

$orderConfig = require __DIR__ . '/config/order.php';
$tableLabels = isset($orderConfig['dine_in_table_labels']) && is_array($orderConfig['dine_in_table_labels'])
    ? $orderConfig['dine_in_table_labels']
    : [];
$tableLabels = array_values(array_filter($tableLabels, static function ($label): bool {
    return is_string($label) && $label !== '';
}));
$orderingOpen = customer_ordering_is_enabled($pdo);
$csrfToken = csrf_token();
$cartFingerprint = hash('sha256', serialize($cart));
if (
    !isset($_SESSION['checkout_submission_key'])
    || !is_string($_SESSION['checkout_submission_key'])
    || preg_match('/\A[a-f0-9]{64}\z/', $_SESSION['checkout_submission_key']) !== 1
    || !isset($_SESSION['checkout_submission_cart_hash'])
    || !is_string($_SESSION['checkout_submission_cart_hash'])
    || !hash_equals($_SESSION['checkout_submission_cart_hash'], $cartFingerprint)
) {
    $_SESSION['checkout_submission_key'] = bin2hex(random_bytes(32));
    $_SESSION['checkout_submission_cart_hash'] = $cartFingerprint;
}
$submissionKey = $_SESSION['checkout_submission_key'];
$maxCashCents = PHP_INT_SIZE >= 8 ? 9999999999 : PHP_INT_MAX;
$maxCashAmount = order_money_from_cents($maxCashCents);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout | M&amp;E Kape Cup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Merriweather:wght@400;700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tokens.css"><link rel="stylesheet" href="assets/css/style.css?v=20261018-5">

</head>
<body class="customer-site bg-background font-body-md text-body-md text-on-surface antialiased">
<?php
$customerNavActive = '';
require __DIR__ . '/includes/customer_header.php';
?>

<main class="mx-auto w-full max-w-5xl px-5 pb-10 pt-28">
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-wider text-text-muted">Review &amp; settle</p>
        <h1 class="mt-2 font-headline text-3xl font-bold text-espresso-dark">Order Checkout</h1>
        <p class="mt-2 text-text-muted">Your order total is calculated from the current menu and configured modifiers.</p>
    </div>

    <?php if (!$orderingOpen): ?>
        <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-950" role="status">
            Ordering currently closed. Online ordering opens when an active Staff shift is available.
        </div>
    <?php endif; ?>
    <?php if ($cartError !== null): ?>
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900" role="alert">
            <?= escape_html($cartError) ?> <a class="font-bold underline" href="cart.php">Review your cart</a>.
        </div>
    <?php endif; ?>
    <div id="checkout-message" class="mb-6 hidden rounded-xl p-4" role="alert"></div>
    <section id="checkout-success" class="mb-6 hidden rounded-xl border border-matcha-green bg-green-50 p-5 text-green-950" role="status" aria-live="polite">
        <h2 id="checkout-success-order" class="font-headline text-2xl font-bold"></h2>
        <p class="mt-2 font-semibold">Keep this number. If you lose this page, show it at the counter.</p>
        <a id="checkout-track-order" class="mt-4 inline-flex rounded-lg bg-primary-container px-5 py-3 font-bold text-white" href="status.php">Track order status</a>
    </section>

    <?php if ($cart === []): ?>
        <section class="rounded-2xl bg-white p-8 text-center shadow-sm">
            <h2 class="font-headline text-xl font-bold">Your cart is empty</h2>
            <p class="mt-2 text-text-muted">Choose something from the menu to get started.</p>
            <a href="menu.php" class="mt-5 inline-flex rounded-lg bg-primary-container px-5 py-3 font-bold text-white">Browse menu</a>
        </section>
    <?php else: ?>
        <form id="checkout-form" class="grid gap-7 lg:grid-cols-5">
            <section class="space-y-6 lg:col-span-3">
                <div class="rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="font-headline text-xl font-bold">Customer details</h2>
                    <label for="customer-name" class="mt-5 block text-sm font-bold">Name for your order</label>
                    <input id="customer-name" name="customer_name" type="text" maxlength="120" autocomplete="name" required
                           class="mt-2 w-full rounded-lg border border-border-neutral bg-[#FFFCF6] px-4 py-3 focus:outline-none focus:ring-2 focus:ring-caramel-gold">

                    <label for="order-type" class="mt-5 block text-sm font-bold">Order type</label>
                    <select id="order-type" name="order_type" class="mt-2 w-full rounded-lg border border-border-neutral bg-[#FFFCF6] px-4 py-3">
                        <option value="Take-Out">Take-Out</option>
                        <?php if ($tableLabels !== []): ?>
                            <option value="Dine-In">Dine-In</option>
                        <?php endif; ?>
                    </select>
                    <?php if ($tableLabels !== []): ?>
                        <div id="table-label-field" class="mt-4 hidden">
                            <label for="table-label" class="block text-sm font-bold">Table</label>
                            <select id="table-label" name="table_label" class="mt-2 w-full rounded-lg border border-border-neutral bg-[#FFFCF6] px-4 py-3">
                                <option value="">Choose your table</option>
                                <?php foreach ($tableLabels as $tableLabel): ?>
                                    <?php if (is_string($tableLabel) && $tableLabel !== ''): ?>
                                        <option value="<?= escape_html($tableLabel) ?>"><?= escape_html($tableLabel) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <p class="mt-3 text-sm text-text-muted">Dine-In ordering is not enabled because no table labels have been configured.</p>
                    <?php endif; ?>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm">
                    <h2 class="font-headline text-xl font-bold">Payment method</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <?php foreach (['Cash', 'GCash', 'Credit Card'] as $method): ?>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-border-neutral p-3">
                                <input type="radio" name="payment_method" value="<?= escape_html($method) ?>" <?= $method === 'Cash' ? 'checked' : '' ?>>
                                <span class="font-semibold"><?= escape_html($method) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="cash-tendered-field" class="mt-5">
                        <label for="cash-tendered" class="block text-sm font-bold">Cash tendered (PHP)</label>
                        <input id="cash-tendered" name="cash_tendered" type="number" min="0" max="<?= escape_html($maxCashAmount) ?>" step="0.01" inputmode="decimal"
                               class="mt-2 w-full rounded-lg border border-border-neutral bg-[#FFFCF6] px-4 py-3">
                        <p class="mt-2 text-sm text-text-muted">Change is calculated from the final server-verified total.</p>
                    </div>
                </div>
            </section>

            <aside class="h-fit rounded-2xl bg-white p-6 shadow-sm lg:col-span-2">
                <h2 class="font-headline text-xl font-bold">Order summary</h2>
                <?php if ($cartSummary !== null): ?>
                    <ul class="mt-5 divide-y divide-border-neutral">
                        <?php foreach ($cartSummary['items'] as $item): ?>
                            <li class="py-4 first:pt-0">
                                <div class="flex justify-between gap-4">
                                    <div>
                                        <p class="font-bold"><?= escape_html($item['quantity']) ?> × <?= escape_html($item['item_name_snapshot']) ?></p>
                                        <?php foreach ($item['modifiers'] as $modifier): ?>
                                            <p class="mt-1 text-sm text-text-muted">
                                                <?= escape_html($modifier['group_name_snapshot']) ?>:
                                                <?= escape_html($modifier['option_name_snapshot']) ?>
                                                (<?= $modifier['price_delta_snapshot'][0] === '-' ? '' : '+' ?>₱<?= escape_html(number_format((float) $modifier['price_delta_snapshot'], 2)) ?>)
                                            </p>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="whitespace-nowrap font-bold">₱<?= escape_html(number_format((float) $item['line_total_snapshot'], 2)) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="mt-4 flex justify-between border-t border-border-neutral pt-4 text-lg font-bold">
                        <span>Total</span>
                        <span>₱<?= escape_html(number_format((float) $cartSummary['total_amount'], 2)) ?></span>
                    </div>
                <?php endif; ?>
                <button id="submit-order-button" type="submit" <?= (!$orderingOpen || $cartError !== null || $cartSummary === null) ? 'disabled' : '' ?>
                        class="mt-6 w-full rounded-lg bg-primary-container px-5 py-4 font-bold text-white transition hover:bg-espresso-dark disabled:cursor-not-allowed disabled:opacity-50">
                    Place order
                </button>
                <a href="cart.php" class="mt-4 block text-center text-sm font-bold text-espresso-dark underline">Back to cart</a>
                <p class="mt-4 text-center text-xs text-text-muted">Payment is verified manually by café staff. No online payment is collected.</p>
            </aside>
        </form>
    <?php endif; ?>
</main>

<script>
(function () {
    var form = document.getElementById('checkout-form');
    if (!form) {
        return;
    }

    var orderType = document.getElementById('order-type');
    var tableField = document.getElementById('table-label-field');
    var tableSelect = document.getElementById('table-label');
    var cashField = document.getElementById('cash-tendered-field');
    var cashInput = document.getElementById('cash-tendered');
    var message = document.getElementById('checkout-message');
    var success = document.getElementById('checkout-success');
    var successOrder = document.getElementById('checkout-success-order');
    var trackOrder = document.getElementById('checkout-track-order');
    var button = document.getElementById('submit-order-button');
    var originalButtonText = button.textContent;

    function updateConditionalFields() {
        var isDineIn = orderType.value === 'Dine-In';
        if (tableField) {
            tableField.classList.toggle('hidden', !isDineIn);
            tableSelect.required = isDineIn;
        }

        var cashSelected = form.querySelector('input[name="payment_method"]:checked').value === 'Cash';
        cashField.classList.toggle('hidden', !cashSelected);
        cashInput.required = cashSelected;
    }

    orderType.addEventListener('change', updateConditionalFields);
    form.querySelectorAll('input[name="payment_method"]').forEach(function (input) {
        input.addEventListener('change', updateConditionalFields);
    });
    updateConditionalFields();

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        message.classList.add('hidden');
        button.disabled = true;
        button.textContent = 'Submitting order…';

        var selectedPayment = form.querySelector('input[name="payment_method"]:checked');
        var payload = {
            customer_name: form.elements.customer_name.value.trim(),
            order_type: orderType.value,
            payment_method: selectedPayment ? selectedPayment.value : '',
            submission_key: <?= json_encode($submissionKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
        };
        if (orderType.value === 'Dine-In' && tableSelect) {
            payload.table_label = tableSelect.value;
        }
        if (payload.payment_method === 'Cash') {
            payload.cash_tendered = cashInput.value;
        }

        try {
            var response = await fetch('api/submit_order.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
                },
                body: JSON.stringify(payload)
            });
            var result = await response.json();
            if (!response.ok || !result.success) {
                var errors = {
                    ordering_closed: 'Ordering currently closed. Please try again when a Staff shift is open.',
                    rate_limited: 'Too many orders are being placed right now. Please wait a few minutes or ask the counter for help.',
                    item_unavailable: 'An item in your cart is no longer available. Review your cart.',
                    invalid_modifier: result.error && result.error.message,
                    insufficient_cash: 'Cash tendered must be at least the final order total.',
                    tracking_unavailable: 'Order tracking is not configured on the server, so the order cannot be submitted yet.'
                };
                throw new Error(errors[result.error && result.error.code] || (result.error && result.error.message) || 'Your order could not be submitted.');
            }

            if (!result.data || !result.data.order_id) {
                throw new Error('The order was accepted, but its reference was missing. Do not submit again; contact the café.');
            }

            form.classList.add('hidden');
            successOrder.textContent = 'Order #' + result.data.order_id;
            trackOrder.href = 'status.php?id=' + encodeURIComponent(String(result.data.order_id));
            success.classList.remove('hidden');
        } catch (error) {
            message.textContent = error.message || 'We could not confirm whether your order was submitted. Do not submit again until you have checked with the café.';
            message.className = 'mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-900';
            button.disabled = false;
            button.textContent = originalButtonText;
        }
    });
}());
</script>
</body>
</html>
