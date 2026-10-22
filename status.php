<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/includes/customer_session.php';
initialize_customer_session();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/order_tracking.php';

$rawOrderId = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
$orderId = filter_var($rawOrderId, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
]);
$validOrderId = $orderId === false ? null : (int) $orderId;
$trackingTokens = isset($_SESSION['order_tracking']) && is_array($_SESSION['order_tracking'])
    ? $_SESSION['order_tracking']
    : [];
$trackingToken = $validOrderId !== null
    && isset($trackingTokens[(string) $validOrderId])
    && is_string($trackingTokens[(string) $validOrderId])
    && preg_match('/^[a-f0-9]{64}$/', $trackingTokens[(string) $validOrderId]) === 1
        ? $trackingTokens[(string) $validOrderId]
        : null;
$rawReference = isset($_GET['reference']) && is_string($_GET['reference'])
    ? $_GET['reference']
    : '';
$reference = normalize_order_tracking_reference($rawReference);
$referenceProvided = array_key_exists('reference', $_GET);
$validReference = $reference !== null;
$invalidReference = $referenceProvided && !$validReference;
$canTrackLegacyOrder = $validOrderId !== null && $trackingToken !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Status | M&amp;E Kape Cup</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="customer-site">
<?php
$customerNavActive = 'order-status';
require __DIR__ . '/includes/customer_header.php';
?>
<main class="mx-auto flex max-w-xl items-center px-5 pb-12 pt-28 status-page-main">
    <section class="w-full rounded-2xl bg-white p-8 text-center shadow-lg">
        <p class="text-sm font-bold uppercase tracking-wider text-text-muted">Live order tracking</p>
        <h1 class="mt-3 font-headline text-2xl font-bold">Track Your Order</h1>
        <p class="mt-3 text-text-muted">Enter the Order Tracking No. printed on your receipt.</p>
        <form method="get" action="status.php" class="mt-6 text-left">
            <label for="order-reference" class="block text-sm font-bold">Order Tracking No.</label>
            <input id="order-reference" name="reference" type="text" maxlength="27" inputmode="text"
                   pattern="([0-9]{8}|[A-Za-z0-9_\-]{27})" value="<?= escape_html($validReference ? $reference : $rawReference) ?>"
                   placeholder="12345678" autocomplete="off" spellcheck="false" required
                   class="mt-2 w-full rounded-lg border border-border-neutral bg-[#FFFCF6] px-4 py-3 focus:outline-none focus:ring-2 focus:ring-caramel-gold">
            <button type="submit" class="mt-3 inline-flex rounded-lg bg-primary-container px-5 py-3 font-bold text-white">
                Track Order
            </button>
        </form>
        <?php if ($invalidReference): ?>
            <p class="mt-4 text-red-800" role="alert">Enter the Order Tracking No. printed on your receipt.</p>
        <?php endif; ?>
        <?php if ($validReference || $canTrackLegacyOrder): ?>
            <div class="mt-6 border-t border-border-neutral pt-6">
                <h2 class="font-headline text-lg font-bold">
                    <?php if ($validReference): ?>
                        Order Tracking No.: <?= escape_html($reference) ?>
                    <?php else: ?>
                        Order #<?= escape_html((string) $validOrderId) ?>
                    <?php endif; ?>
                </h2>
                <p id="tracking-message" class="mt-3 text-text-muted" role="status">Checking your order status…</p>
                <ol class="mt-8 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4" aria-label="Order progress">
                    <?php foreach (['Pending', 'Paid', 'Preparing', 'Completed'] as $index => $status): ?>
                        <li id="status-step-<?= $index ?>" class="rounded-lg bg-stone-100 px-2 py-3 text-stone-500">
                            <span class="block font-bold"><?= escape_html($status) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p class="mt-6 text-sm text-text-muted">Payment is confirmed by café staff. This page refreshes the status automatically.</p>
            </div>
            <script>
            (function () {
                var requestedReference = <?= json_encode($validReference ? $reference : null, JSON_THROW_ON_ERROR) ?>;
                var requestedOrderId = <?= json_encode($validReference ? null : $validOrderId, JSON_THROW_ON_ERROR) ?>;
                var trackingToken = <?= json_encode($trackingToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
                var pollingToken = null;
                var message = document.getElementById('tracking-message');
                var statuses = ['Pending', 'Paid', 'Preparing', 'Completed'];
                var requestInFlight = false;
                var pollingStopped = false;

                function stopPolling() {
                    pollingStopped = true;
                    window.clearInterval(intervalId);
                }

                function showFailure() {
                    message.textContent = 'Order status is temporarily unavailable. Retrying automatically…';
                    message.className = 'mt-3 text-red-800';
                }

                function stopWithMessage(text) {
                    stopPolling();
                    message.textContent = text;
                    message.className = 'mt-3 text-red-800';
                }

                function updateProgress(status) {
                    var currentIndex = statuses.indexOf(status);
                    statuses.forEach(function (step, index) {
                        var element = document.getElementById('status-step-' + index);
                        element.className = index <= currentIndex
                            ? 'rounded-lg bg-matcha-green px-2 py-3 text-white'
                            : 'rounded-lg bg-stone-100 px-2 py-3 text-stone-500';
                        if (index === currentIndex) {
                            element.setAttribute('aria-current', 'step');
                        } else {
                            element.removeAttribute('aria-current');
                        }
                    });
                    message.textContent = 'Current status: ' + status;
                    message.className = 'mt-3 font-bold text-primary-container';
                    if (status === 'Completed') stopPolling();
                }

                async function loadStatus() {
                    if (requestInFlight || pollingStopped) return;
                    requestInFlight = true;
                    try {
                        var endpoint = requestedReference !== null
                            ? (pollingToken === null
                                ? 'api/ordering_status.php?reference=' + encodeURIComponent(requestedReference)
                                : 'api/ordering_status.php')
                            : 'api/ordering_status.php?id=' + encodeURIComponent(requestedOrderId);
                        var options = {
                            cache: 'no-store',
                            headers: {}
                        };
                        if (requestedReference === null) {
                            options.headers['X-Order-Tracking-Token'] = trackingToken;
                        } else if (pollingToken !== null) {
                            options.headers['X-Order-Tracking-Poll-Token'] = pollingToken;
                        }
                        var response = await fetch(endpoint, options);
                        if (response.status === 401 && requestedReference !== null && pollingToken !== null) {
                            stopWithMessage('Your tracking session expired. Enter your Order Tracking No. again to continue.');
                            return;
                        }
                        if (response.status === 404 || response.status === 422) {
                            stopWithMessage('We could not find that order. Check the Order Tracking No. and try again.');
                            return;
                        }
                        if (response.status === 429) {
                            stopWithMessage('Too many tracking lookups. Please wait and try again.');
                            return;
                        }
                        if (!response.ok) throw new Error('Order status request failed.');
                        var result = await response.json();
                        if (!result || result.success !== true || !result.data ||
                            statuses.indexOf(result.data.status) === -1 ||
                            (requestedReference !== null && result.data.order_reference !== requestedReference) ||
                            (requestedReference === null && Number(result.data.order_id) !== requestedOrderId)) {
                            throw new Error('Order status response could not be verified.');
                        }
                        if (requestedReference !== null && pollingToken === null) {
                            if (!/^[a-f0-9]{64}$/.test(result.data.polling_token || '')) {
                                throw new Error('Order tracking could not be verified.');
                            }
                            pollingToken = result.data.polling_token;
                        }
                        updateProgress(result.data.status);
                    } catch (error) {
                        showFailure();
                    } finally {
                        requestInFlight = false;
                    }
                }

                var intervalId = window.setInterval(loadStatus, 5000);
                loadStatus();
            }());
            </script>
        <?php endif; ?>
        <a href="menu.php" class="mt-7 inline-flex rounded-lg bg-primary-container px-5 py-3 font-bold text-white">Back to menu</a>
    </section>
</main>
</body>
</html>
