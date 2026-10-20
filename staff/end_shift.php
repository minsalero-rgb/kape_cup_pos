<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/shift.php';

require_role(['staff', 'admin'], $pdo);
require_post_request();

if (!csrf_token_is_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
    http_response_code(400);
    exit('This form expired. Return to the dashboard and try again.');
}

$user = current_staff_user();
$shift = find_open_shift($pdo, $user['staff_id']);

if ($shift === null) {
    unset($_SESSION['shift_id']);
    set_flash_message('error', 'You do not have an open shift to end.');
    redirect_to_staff('dashboard.php');
}

$statement = $pdo->prepare(
    'UPDATE staff_shifts
     SET logout_timestamp = CURRENT_TIMESTAMP
     WHERE shift_id = :shift_id
       AND staff_id = :staff_id
       AND logout_timestamp IS NULL'
);
$statement->execute([
    'shift_id' => (int) $shift['shift_id'],
    'staff_id' => $user['staff_id'],
]);

unset($_SESSION['shift_id']);

if ($statement->rowCount() === 1) {
    set_flash_message('success', 'Your shift has ended.');
} else {
    set_flash_message('error', 'Your shift could not be updated. Refresh the dashboard and check its status.');
}

redirect_to_staff('dashboard.php');