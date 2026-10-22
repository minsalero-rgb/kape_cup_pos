<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/receipt_records.php';

require_role('staff', $pdo);
$user = current_staff_user();

$normalized = receipt_records_normalize_filters($_GET);
$filters = $normalized['filters'];
$filterErrors = $normalized['errors'];
$actionError = '';
$successMessage = get_flash_message('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_request();
    if (!csrf_token_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        $actionError = 'This form expired. Refresh the page and try again.';
    } else {
        $receiptId = isset($_POST['receipt_id']) && is_string($_POST['receipt_id'])
            ? filter_var($_POST['receipt_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        $reason = isset($_POST['removal_reason']) && is_string($_POST['removal_reason'])
            ? trim($_POST['removal_reason'])
            : '';
        if ($receiptId === false) {
            $actionError = 'That receipt action is invalid.';
        } else {
            try {
                receipt_records_remove($pdo, (int) $receiptId, (int) $user['staff_id'], $reason);
                set_flash_message('success', 'The receipt was removed from the Staff view and retained in the Admin audit.');
                header('Location: receipts.php' . ($filters === [
                    'tracking' => '',
                    'date_from' => '',
                    'date_to' => '',
                    'item' => '',
                ] ? '' : '?' . http_build_query($filters)), true, 303);
                exit;
            } catch (InvalidArgumentException $exception) {
                $actionError = $exception->getMessage();
            } catch (DomainException $exception) {
                $actionError = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('[staff-receipts] Receipt removal failed.');
                $actionError = 'The receipt could not be removed. Please try again.';
            }
        }
    }
}

$receipts = [];
if ($filterErrors === []) {
    try {
        $receipts = receipt_records_fetch($pdo, $filters, false);
    } catch (Throwable $exception) {
        error_log('[staff-receipts] Receipt list could not be loaded.');
        $actionError = 'Receipt records could not be loaded. Please try again.';
    }
}

$role = 'staff';
$pageTitle = 'Receipts';
$pageEyebrow = 'Receipt management';
$activeNav = 'receipts';
$pageDescription = 'Find issued receipts, print an active receipt again, or remove it from the Staff view with an audit reason.';
require __DIR__ . '/../includes/layout/header.php';
?>
<?php if ($successMessage !== null): ?>
    <p class="notice success" role="status"><?= escape_html($successMessage) ?></p>
<?php endif; ?>
<?php if ($actionError !== ''): ?>
    <p class="notice" role="alert"><?= escape_html($actionError) ?></p>
<?php endif; ?>
<?php foreach ($filterErrors as $filterError): ?>
    <p class="notice" role="alert"><?= escape_html($filterError) ?></p>
<?php endforeach; ?>

<form method="get" action="receipts.php" class="card mb-4">
    <h2>Find receipts</h2>
    <div class="receipt-filter-grid">
        <label>Tracking number
            <input type="text" name="tracking" inputmode="text" maxlength="27" pattern="[A-Za-z0-9_\-]{1,27}" value="<?= escape_html($filters['tracking']) ?>">
        </label>
        <label>Issue date from
            <input type="date" name="date_from" value="<?= escape_html($filters['date_from']) ?>">
        </label>
        <label>Issue date to
            <input type="date" name="date_to" value="<?= escape_html($filters['date_to']) ?>">
        </label>
        <label>Ordered item
            <input type="search" name="item" maxlength="120" value="<?= escape_html($filters['item']) ?>">
        </label>
    </div>
    <div class="flex items-center gap-3">
        <button type="submit">Filter receipts</button>
        <a href="receipts.php">Clear</a>
    </div>
</form>

<section class="card">
    <h2>Active receipts</h2>
    <?php if ($receipts === []): ?>
        <p>No active receipts match those filters.</p>
    <?php else: ?>
        <?php foreach ($receipts as $receipt): ?>
            <?php
            $snapshotOrder = isset($receipt['snapshot']['order']) && is_array($receipt['snapshot']['order'])
                ? $receipt['snapshot']['order']
                : [];
            $snapshotItems = isset($receipt['snapshot']['items']) && is_array($receipt['snapshot']['items'])
                ? $receipt['snapshot']['items']
                : [];
            $itemNames = array_map(static function (array $item): string {
                return (string) ($item['name'] ?? '');
            }, $snapshotItems);
            ?>
            <article class="order">
                <h3>Order Tracking No.: <?= escape_html($receipt['order_reference']) ?></h3>
                <p>OR No.: <?= escape_html($receipt['or_number']) ?> · Issued <?= escape_html(date('M j, Y g:i A', strtotime($receipt['issued_at']))) ?></p>
                <p><?= escape_html(implode(', ', $itemNames)) ?></p>
                <p>Total: ₱<?= escape_html((string) ($snapshotOrder['total_amount'] ?? '')) ?></p>
                <p>Issued by: <?= escape_html($receipt['issued_by_name']) ?></p>
                <p>
                    <a href="receipt.php?order_id=<?= (int) $receipt['order_id'] ?>">View / print</a>
                </p>
                <form method="post" action="receipts.php<?= $filters === [
                    'tracking' => '',
                    'date_from' => '',
                    'date_to' => '',
                    'item' => '',
                ] ? '' : '?' . escape_html(http_build_query($filters)) ?>" data-confirm="Remove this receipt from the Staff view? Its snapshot will remain in the Admin audit.">
                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                    <input type="hidden" name="receipt_id" value="<?= (int) $receipt['receipt_id'] ?>">
                    <label>Reason for removal
                        <input type="text" name="removal_reason" maxlength="500" required>
                    </label>
                    <button type="submit">Remove receipt</button>
                </form>
            </article>
        <?php endforeach; ?>
        <p>Showing up to 100 matching receipts.</p>
    <?php endif; ?>
</section>
<script>
document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});
</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
