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
$existingShift = find_open_shift($pdo, $user['staff_id']);

if ($existingShift !== null) {
    $_SESSION['shift_id'] = (int) $existingShift['shift_id'];
    set_flash_message('success', 'Your existing open shift is ready to resume.');
    redirect_to_staff('dashboard.php');
}

try {
    $pdo->beginTransaction();

    $existingShift = find_open_shift($pdo, $user['staff_id']);

    if ($existingShift !== null) {
        $pdo->commit();
        $_SESSION['shift_id'] = (int) $existingShift['shift_id'];
        set_flash_message('success', 'Your existing open shift is ready to resume.');
        redirect_to_staff('dashboard.php');
    }

    $statement = $pdo->prepare(
        'INSERT INTO staff_shifts (staff_id, login_timestamp, logout_timestamp)
         VALUES (:staff_id, CURRENT_TIMESTAMP, NULL)'
    );
    $statement->execute(['staff_id' => $user['staff_id']]);
    $shiftId = (int) $pdo->lastInsertId();
    $pdo->commit();

    $_SESSION['shift_id'] = $shiftId;
    set_flash_message('success', 'Your shift has started.');
    redirect_to_staff('dashboard.php');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        $exception->getCode() === '23000' &&
        strpos($exception->getMessage(), 'uq_staff_shifts_open_staff_id') !== false
    ) {
        $existingShift = find_open_shift($pdo, $user['staff_id']);

        if ($existingShift !== null) {
            $_SESSION['shift_id'] = (int) $existingShift['shift_id'];
            set_flash_message('success', 'Your existing open shift is ready to resume.');
        } else {
            error_log('Open shift uniqueness conflict for staff ID ' . $user['staff_id'] . '.');
            set_flash_message(
                'error',
                'An active-shift conflict was detected. No shift was changed. Ask an administrator to review it.'
            );
        }

        redirect_to_staff('dashboard.php');
    }

    error_log('Unable to start staff shift: ' . $exception->getMessage());
    set_flash_message('error', 'Unable to start your shift. No existing shift was closed or changed.');
    redirect_to_staff('dashboard.php');
}