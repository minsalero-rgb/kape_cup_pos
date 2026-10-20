<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';

require_role('staff', $pdo);
$user = current_staff_user();

$period = isset($_GET['period']) && is_string($_GET['period']) ? $_GET['period'] : 'today';
$periodDays = ['today' => 1, '7' => 7, '30' => 30];
if (!isset($periodDays[$period])) {
    $period = 'today';
}
$start = new DateTimeImmutable('today');
if ($periodDays[$period] > 1) {
    $start = $start->modify('-' . ($periodDays[$period] - 1) . ' days');
}
$startAt = $start->format('Y-m-d 00:00:00');
$endAt = (new DateTimeImmutable('tomorrow'))->format('Y-m-d 00:00:00');
$summaryStatement = $pdo->prepare(
    "SELECT COUNT(*) AS order_count,
            COALESCE(SUM(total_amount), 0.00) AS sales,
            COALESCE(AVG(total_amount), 0.00) AS average_order
     FROM orders
     WHERE staff_id = :staff_id
       AND status = 'Completed'
       AND completed_at >= :start_at
       AND completed_at < :end_at"
);
$summaryStatement->execute([
    'staff_id' => (int) $user['staff_id'],
    'start_at' => $startAt,
    'end_at' => $endAt,
]);
$summary = $summaryStatement->fetch(PDO::FETCH_ASSOC);
$paymentStatement = $pdo->prepare(
    "SELECT payment_method, COUNT(*) AS order_count,
            COALESCE(SUM(total_amount), 0.00) AS sales
     FROM orders
     WHERE staff_id = :staff_id
       AND status = 'Completed'
       AND completed_at >= :start_at
       AND completed_at < :end_at
     GROUP BY payment_method
     ORDER BY sales DESC, payment_method"
);
$paymentStatement->execute([
    'staff_id' => (int) $user['staff_id'],
    'start_at' => $startAt,
    'end_at' => $endAt,
]);
$payments = $paymentStatement->fetchAll(PDO::FETCH_ASSOC);
$role = 'staff';
$pageTitle = 'Sales & Reports';
$pageEyebrow = 'Barista cockpit';
$activeNav = 'reports';
$pageDescription = 'Completed sales attributed to your Staff account.';
require __DIR__ . '/../includes/layout/header.php';
?>
<form method="get" action="offline_pos.php" class="card report-filter">
    <input type="hidden" name="page" value="reports">
    <label>
        <span>Reporting period</span>
        <select name="period">
            <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="7" <?= $period === '7' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30" <?= $period === '30' ? 'selected' : '' ?>>Last 30 days</option>
        </select>
    </label>
    <button type="submit" class="btn-secondary">Update report</button>
</form>
<section class="app-auto-grid" aria-label="Sales summary">
    <article class="card stat"><p>Completed sales</p><p>₱<?= escape_html($summary['sales']) ?></p></article>
    <article class="card stat"><p>Completed orders</p><p><?= (int) $summary['order_count'] ?></p></article>
    <article class="card stat"><p>Average order</p><p>₱<?= escape_html($summary['average_order']) ?></p></article>
</section>
<section class="card mt-space-lg">
    <h2>Sales by payment method</h2>
    <div class="app-table-wrap">
        <table>
            <thead><tr><th>Payment</th><th>Completed orders</th><th>Sales</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr><td><?= escape_html($payment['payment_method']) ?></td><td><?= (int) $payment['order_count'] ?></td><td>₱<?= escape_html($payment['sales']) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($payments === []): ?><tr><td colspan="3">No completed orders in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
