<?php
declare(strict_types=1);

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/includes/customer_session.php';
initialize_customer_session();

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
        <?php if ($validOrderId === null || $trackingToken === null): ?>
            <h1 class="mt-3 font-headline text-2xl font-bold">Order reference unavailable</h1>
            <p class="mt-3 text-text-muted">This order can’t be verified in the current session. Return to checkout on the device used to place it, or ask staff at the counter for an update.</p>
        <?php else: ?>
            <h1 class="mt-3 font-headline text-2xl font-bold">Order #<?= htmlspecialchars((string) $validOrderId, ENT_QUOTES, 'UTF-8') ?></h1>
            <p id="tracking-message" class="mt-3 text-text-muted" role="status">Checking your order status…</p>
            <ol class="mt-8 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4" aria-label="Order progress">
                <?php foreach (['Pending', 'Paid', 'Preparing', 'Completed'] as $index => $status): ?>
                    <li id="status-step-<?= $index ?>" class="rounded-lg bg-stone-100 px-2 py-3 text-stone-500">
                        <span class="block font-bold"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="mt-6 text-sm text-text-muted">Payment is confirmed by café staff. This page refreshes the status automatically.</p>
            <script>
            (function () {
                var requestedOrderId = <?= json_encode($validOrderId, JSON_THROW_ON_ERROR) ?>;
                var trackingToken = <?= json_encode($trackingToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
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
                        var response = await fetch('api/ordering_status.php?id=' + encodeURIComponent(requestedOrderId), {
                            cache: 'no-store',
                            headers: {'X-Order-Tracking-Token': trackingToken}
                        });
                        if (!response.ok) throw new Error('Order status request failed.');
                        var result = await response.json();
                        if (!result || result.success !== true || !result.data ||
                            Number(result.data.order_id) !== requestedOrderId ||
                            statuses.indexOf(result.data.status) === -1) {
                            throw new Error('Order status response could not be verified.');
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
