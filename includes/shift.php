<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function find_open_shift(PDO $pdo, int $staffId): ?array
{
    $statement = $pdo->prepare(
        'SELECT shift_id, login_timestamp
         FROM staff_shifts
         WHERE staff_id = :staff_id
           AND logout_timestamp IS NULL
         LIMIT 1'
    );
    $statement->execute(['staff_id' => $staffId]);
    $shift = $statement->fetch();

    return $shift ? $shift : null;
}

function customer_ordering_is_enabled(PDO $pdo): bool
{
    $statement = $pdo->prepare(
        "SELECT 1
         FROM staff_shifts AS shifts
         INNER JOIN staff
             ON staff.staff_id = shifts.staff_id
         WHERE shifts.logout_timestamp IS NULL
           AND staff.role = 'staff'
           AND staff.is_active = 1
         LIMIT 1"
    );
    $statement->execute();

    return (bool) $statement->fetchColumn();
}