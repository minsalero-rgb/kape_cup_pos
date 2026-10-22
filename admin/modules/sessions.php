<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section']) && is_string($_POST['section']) && $_POST['section'] === 'sessions') {
    require_post_request();
    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : null;
    if (!csrf_token_is_valid($token)) {
        http_response_code(403);
        $error = 'This form expired. Refresh the page and try again.';
    } else {
        $shiftIdInput = isset($_POST['shift_id']) && is_string($_POST['shift_id']) ? $_POST['shift_id'] : '';
        $shiftId = filter_var($shiftIdInput, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);
        if ($shiftId === false) {
            $error = 'Choose a valid duty session.';
        } else {
            try {
                $close = $pdo->prepare(
                    'UPDATE staff_shifts
                     SET logout_timestamp = CURRENT_TIMESTAMP
                     WHERE shift_id = :shift_id
                       AND logout_timestamp IS NULL'
                );
                $close->execute(['shift_id' => (int) $shiftId]);
                if ($close->rowCount() !== 1) {
                    $error = 'That session is already closed or no longer exists. Refresh the list.';
                } else {
                    admin_redirect('sessions', 'success=closed');
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('[admin-duty-sessions] Manual shift resolution failed.');
                http_response_code(500);
                $error = 'The selected duty session could not be resolved. Please try again.';
            }
        }
    }
}

$success = $activeTab === 'sessions' && isset($_GET['success']) && $_GET['success'] === 'closed' ? 'The selected open session was manually closed.' : '';
$statement = $pdo->query(
    'SELECT
        shifts.shift_id,
        shifts.staff_id,
        shifts.login_timestamp,
        shifts.logout_timestamp,
        staff.full_name,
        staff.username,
        staff.role,
        staff.is_active,
        COUNT(orders.order_id) AS order_count
     FROM staff_shifts AS shifts
     INNER JOIN staff ON staff.staff_id = shifts.staff_id
     LEFT JOIN orders ON orders.shift_id = shifts.shift_id
                     AND orders.staff_id = shifts.staff_id
     GROUP BY shifts.shift_id, shifts.staff_id, shifts.login_timestamp,
              shifts.logout_timestamp, staff.full_name, staff.username,
              staff.role, staff.is_active
     ORDER BY (shifts.logout_timestamp IS NULL) DESC,
              shifts.login_timestamp DESC, shifts.shift_id DESC
     LIMIT 300'
);
$sessions = $statement->fetchAll(PDO::FETCH_ASSOC);
$openCount = 0;
foreach ($sessions as $session) {
    if ($session['logout_timestamp'] === null) {
        $openCount++;
    }
}
?>
<div><p class="mt-space-xs text-body-sm text-muted-text">Signed in as <?= escape_html($admin['full_name']) ?>. Open sessions are never closed automatically; inspect their start time and resolve them explicitly.</p></div>
    <div class="admin-session-summary grid gap-4 sm:grid-cols-2"><div class="rounded-xl border border-[#E8DEC9] bg-[#FFFCF6] p-5"><p class="text-sm text-[#74685E]">Open sessions in the latest 300 records</p><p class="mt-1 text-3xl font-bold text-[#3B1F14]"><?= $openCount ?></p></div><div class="rounded-xl border border-[#E8DEC9] bg-[#FFFCF6] p-5"><p class="text-sm text-[#74685E]">Resolution rule</p><p class="mt-1 font-semibold text-[#3B1F14]">Close only the session you reviewed</p></div></div>
    <?php if ($success !== ''): ?><p class="rounded-lg bg-green-50 p-4 text-green-900" role="status"><?= escape_html($success) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="rounded-lg bg-red-50 p-4 text-red-900" role="alert"><?= escape_html($error) ?></p><?php endif; ?>
    <section class="admin-session-history overflow-hidden rounded-2xl border border-[#E8DEC9] bg-[#FFFCF6] shadow-sm">
        <div class="border-b border-[#E8DEC9] p-5"><h2 class="text-xl font-bold text-[#3B1F14]">Shift history</h2><p class="mt-1 text-sm text-[#74685E]">Showing up to 300 sessions, newest first. Order records remain attached after resolution.</p></div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-[#E8DEC9] text-left text-sm">
            <thead class="bg-[#F6F3ED] text-[#74685E]"><tr><th class="px-4 py-3">Staff member</th><th class="px-4 py-3">Started</th><th class="px-4 py-3">Ended</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">State</th><th class="px-4 py-3">Manual action</th></tr></thead>
            <tbody class="divide-y divide-[#E8DEC9]">
            <?php foreach ($sessions as $session): ?>
                <?php
                $isOpen = $session['logout_timestamp'] === null;
                $startTimestamp = strtotime((string) $session['login_timestamp']);
                $elapsedHours = $isOpen && $startTimestamp !== false
                    ? (int) floor(max(0, time() - $startTimestamp) / 3600)
                    : null;
                ?>
                <tr>
                    <td class="px-4 py-4"><strong><?= escape_html($session['full_name']) ?></strong><span class="block text-xs text-[#74685E]"><?= escape_html($session['username']) ?> · <?= escape_html(ucfirst($session['role'])) ?><?= (int) $session['is_active'] === 1 ? '' : ' · Inactive account' ?></span><span class="block text-xs text-[#74685E]">Shift #<?= (int) $session['shift_id'] ?></span></td>
                    <td class="whitespace-nowrap px-4 py-4"><?= $startTimestamp === false ? 'Invalid timestamp' : escape_html(date('M j, Y g:i A', $startTimestamp)) ?></td>
                    <td class="whitespace-nowrap px-4 py-4"><?= $isOpen ? '—' : escape_html(date('M j, Y g:i A', strtotime((string) $session['logout_timestamp']))) ?></td>
                    <td class="px-4 py-4"><?= (int) $session['order_count'] ?></td>
                    <td class="px-4 py-4"><?= $isOpen ? 'Open · ' . (int) $elapsedHours . 'h elapsed' : 'Closed' ?></td>
                    <td class="px-4 py-4">
                        <?php if ($isOpen): ?><form method="post" action="dashboard.php?tab=sessions" data-confirm-shift="<?= (int) $session['shift_id'] ?>" data-staff-name="<?= escape_html($session['full_name']) ?>"><input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="section" value="sessions"><input type="hidden" name="shift_id" value="<?= (int) $session['shift_id'] ?>"><button class="rounded-lg border border-[#D9B77E] px-3 py-2 font-semibold text-[#3B1F14] hover:bg-[#EFE1C3]" type="submit">Resolve / close</button></form><?php else: ?><span class="text-xs text-[#74685E]">No action</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($sessions === []): ?><tr><td class="px-4 py-8 text-center text-[#74685E]" colspan="6">No duty sessions recorded.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
