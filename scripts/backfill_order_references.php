<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$arguments = array_slice($argv, 1);
$approvalPrefix = '--approve-order-ids=';
$approvalArguments = [];
$unknownArguments = [];
foreach ($arguments as $argument) {
    if (in_array($argument, ['--dry-run', '--apply'], true)) {
        continue;
    }
    if (strpos($argument, $approvalPrefix) === 0) {
        $approvalArguments[] = $argument;
        continue;
    }
    $unknownArguments[] = $argument;
}
$applyCount = count(array_filter($arguments, static function ($argument): bool {
    return $argument === '--apply';
}));
$dryRunCount = count(array_filter($arguments, static function ($argument): bool {
    return $argument === '--dry-run';
}));
if (
    $unknownArguments !== []
    || $applyCount > 1
    || $dryRunCount > 1
    || ($applyCount > 0 && $dryRunCount > 0)
    || count($approvalArguments) > 1
    || ($applyCount === 0 && $approvalArguments !== [])
    || ($applyCount === 1 && $approvalArguments === [])
) {
    fwrite(STDERR, "Usage: php scripts/backfill_order_references.php [--dry-run|--apply --approve-order-ids=ID[,ID...]]\n");
    exit(2);
}
$applyChanges = in_array('--apply', $arguments, true);
$approvedOrderIds = [];
if ($applyChanges) {
    $approvedIdsArgument = substr($approvalArguments[0], strlen($approvalPrefix));
    foreach (explode(',', $approvedIdsArgument) as $approvedId) {
        if (preg_match('/\A[1-9][0-9]*\z/', $approvedId) !== 1) {
            fwrite(STDERR, "Approved order IDs must be unique positive integers separated by commas.\n");
            exit(2);
        }
        $validatedId = filter_var($approvedId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);
        if ($validatedId === false || (string) $validatedId !== $approvedId) {
            fwrite(STDERR, "Approved order IDs must be unique positive integers separated by commas.\n");
            exit(2);
        }
        if (in_array((int) $validatedId, $approvedOrderIds, true)) {
            fwrite(STDERR, "Approved order IDs must be unique positive integers separated by commas.\n");
            exit(2);
        }
        $approvedOrderIds[] = (int) $validatedId;
    }
    sort($approvedOrderIds);
    if ($approvedOrderIds === []) {
        fwrite(STDERR, "At least one order ID must be explicitly approved for --apply.\n");
        exit(2);
    }
}

require_once __DIR__ . '/../includes/order_tracking.php';
require __DIR__ . '/../config/db.php';

function backfill_reference_is_missing($reference): bool
{
    return $reference === null || (is_string($reference) && trim($reference) === '');
}

function backfill_reference_key(string $reference): string
{
    return strtolower($reference);
}

function backfill_table_exists(PDO $pdo, string $tableName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);

    return (int) $statement->fetchColumn() === 1;
}

function backfill_has_unique_reference_index(PDO $pdo): bool
{
    $statement = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.STATISTICS AS reference_index
         WHERE reference_index.TABLE_SCHEMA = DATABASE()
           AND reference_index.TABLE_NAME = 'orders'
           AND reference_index.INDEX_NAME = 'uq_orders_order_reference'
           AND reference_index.COLUMN_NAME = 'order_reference'
           AND reference_index.SEQ_IN_INDEX = 1
           AND reference_index.SUB_PART IS NULL
           AND reference_index.NON_UNIQUE = 0
           AND NOT EXISTS (
               SELECT 1
               FROM information_schema.STATISTICS AS later_column
               WHERE later_column.TABLE_SCHEMA = reference_index.TABLE_SCHEMA
                 AND later_column.TABLE_NAME = 'orders'
                 AND later_column.INDEX_NAME = 'uq_orders_order_reference'
                 AND later_column.SEQ_IN_INDEX > 1
           )"
    );
    $statement->execute();

    return (int) $statement->fetchColumn() === 1;
}

function backfill_receipt_reference_data(PDO $pdo, bool $lock = false): array
{
    if (!backfill_table_exists($pdo, 'receipt_records')) {
        return [false, [], [], []];
    }

    $engineStatement = $pdo->prepare(
        "SELECT ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'receipt_records'
         LIMIT 1"
    );
    $engineStatement->execute();
    if (strcasecmp((string) $engineStatement->fetchColumn(), 'InnoDB') !== 0) {
        throw new RuntimeException('The receipt_records table must use InnoDB for safe reference checks.');
    }

    $requiredColumns = [
        'receipt_id',
        'order_id',
        'order_reference',
        'receipt_snapshot',
    ];
    $columnStatement = $pdo->query(
        "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'receipt_records'"
    );
    $receiptColumns = $columnStatement->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($requiredColumns, $receiptColumns) !== []) {
        throw new RuntimeException('The receipt_records table is missing required reference or snapshot columns.');
    }

    $receiptStatement = $pdo->query(
        'SELECT receipt_records.receipt_id,
                receipt_records.order_id,
                receipt_records.order_reference,
                receipt_records.receipt_snapshot,
                orders.order_id AS matched_order_id,
                orders.order_reference AS current_order_reference
         FROM receipt_records
         LEFT JOIN orders ON orders.order_id = receipt_records.order_id
         ORDER BY receipt_records.order_id, receipt_records.receipt_id' . ($lock ? ' FOR UPDATE' : '')
    );
    $receiptsByOrder = [];
    $inconsistentReceiptIds = [];
    $inconsistentOrderIds = [];
    $referenceOwners = [];

    foreach ($receiptStatement->fetchAll(PDO::FETCH_ASSOC) as $receipt) {
        $receiptId = (int) $receipt['receipt_id'];
        $orderId = (int) $receipt['order_id'];
        $reference = (string) $receipt['order_reference'];
        $snapshot = json_decode((string) $receipt['receipt_snapshot'], true);
        $snapshotReference = isset($snapshot['order']['order_reference']) &&
            is_string($snapshot['order']['order_reference'])
            ? $snapshot['order']['order_reference']
            : null;

        if (
            $receipt['matched_order_id'] === null
            || normalize_order_tracking_reference($reference) !== $reference
            || $snapshotReference !== $reference
        ) {
            $inconsistentReceiptIds[] = $receiptId;
            $inconsistentOrderIds[] = $orderId;
            continue;
        }

        $referenceKey = backfill_reference_key($reference);
        if (!isset($referenceOwners[$referenceKey])) {
            $referenceOwners[$referenceKey] = [
                'order_ids' => [],
                'receipt_ids' => [],
            ];
        }
        $referenceOwners[$referenceKey]['order_ids'][$orderId] = true;
        $referenceOwners[$referenceKey]['receipt_ids'][] = $receiptId;

        if (backfill_reference_is_missing($receipt['current_order_reference'])) {
            $receiptsByOrder[$orderId][] = [
                'receipt_id' => $receiptId,
                'order_reference' => $reference,
            ];
        } elseif ((string) $receipt['current_order_reference'] !== $reference) {
            $inconsistentReceiptIds[] = $receiptId;
            $inconsistentOrderIds[] = $orderId;
        }
    }

    foreach ($referenceOwners as $owner) {
        if (count($owner['order_ids']) > 1) {
            $inconsistentReceiptIds = array_merge($inconsistentReceiptIds, $owner['receipt_ids']);
            $inconsistentOrderIds = array_merge($inconsistentOrderIds, array_keys($owner['order_ids']));
        }
    }

    return [
        true,
        $receiptsByOrder,
        array_values(array_unique($inconsistentReceiptIds)),
        array_values(array_unique($inconsistentOrderIds)),
    ];
}

function backfill_find_duplicate_order_ids(PDO $pdo): array
{
    $statement = $pdo->query(
        "SELECT order_id
         FROM orders AS current_order
         WHERE current_order.order_reference IS NOT NULL
           AND TRIM(current_order.order_reference) <> ''
           AND EXISTS (
               SELECT 1
               FROM orders AS other_order
               WHERE other_order.order_reference = current_order.order_reference
                 AND other_order.order_id <> current_order.order_id
           )
         ORDER BY order_id"
    );

    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function backfill_print_summary(array $summary, bool $applyChanges): void
{
    fwrite(STDOUT, $applyChanges ? "Backfill mode: APPLY\n" : "Backfill mode: DRY RUN (no database writes)\n");
    fwrite(STDOUT, 'Examined: ' . $summary['examined'] . "\n");
    fwrite(STDOUT, 'Valid eight-digit references preserved: ' . $summary['valid_eight_digit'] . "\n");
    fwrite(STDOUT, 'Valid legacy references preserved: ' . $summary['valid_legacy'] . "\n");
    fwrite(STDOUT, 'Malformed references: ' . $summary['malformed'] . "\n");
    fwrite(STDOUT, 'Orders in duplicate-reference groups: ' . $summary['duplicate'] . "\n");
    fwrite(STDOUT, 'Receipt conflicts: ' . $summary['receipt_conflicts'] . "\n");
    fwrite(STDOUT, 'Missing references: ' . $summary['missing'] . "\n");
    fwrite(STDOUT, 'Would update / updated: ' . $summary['updated'] . "\n");
    fwrite(STDOUT, 'Restored from consistent receipt records: ' . $summary['restored_from_receipt'] . "\n");
    fwrite(STDOUT, 'Failed / conflicting records: ' . $summary['failed'] . "\n");
    if ($summary['approved_order_ids'] !== []) {
        fwrite(STDOUT, 'Explicitly approved order IDs: ' . implode(', ', $summary['approved_order_ids']) . "\n");
    }
    foreach ($summary['planned_changes'] as $orderId => $change) {
        fwrite(
            STDOUT,
            'Planned order #' . $orderId . ': reference ' . $change['reference'] .
                ' (' . $change['source'] . ')' . "\n"
        );
    }
    if ($summary['problem_order_ids'] !== []) {
        fwrite(STDOUT, 'Affected order IDs: ' . implode(', ', $summary['problem_order_ids']) . "\n");
    }
    if ($summary['problem_receipt_ids'] !== []) {
        fwrite(STDOUT, 'Affected receipt IDs: ' . implode(', ', $summary['problem_receipt_ids']) . "\n");
    }
}

$summary = [
    'examined' => 0,
    'valid_eight_digit' => 0,
    'valid_legacy' => 0,
    'malformed' => 0,
    'duplicate' => 0,
    'receipt_conflicts' => 0,
    'missing' => 0,
    'updated' => 0,
    'restored_from_receipt' => 0,
    'failed' => 0,
    'approved_order_ids' => $approvedOrderIds,
    'planned_changes' => [],
    'problem_order_ids' => [],
    'problem_receipt_ids' => [],
];

try {
    $columnStatement = $pdo->prepare(
        "SELECT CHARACTER_MAXIMUM_LENGTH
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'orders'
           AND COLUMN_NAME = 'order_reference'"
    );
    $columnStatement->execute();
    $referenceColumnLength = $columnStatement->fetchColumn();
    if ($referenceColumnLength === false) {
        throw new RuntimeException('The orders.order_reference column is missing; apply and verify its migration first.');
    }
    if ((int) $referenceColumnLength < 8) {
        throw new RuntimeException('The orders.order_reference column must hold at least eight characters.');
    }

    if ($applyChanges && !backfill_has_unique_reference_index($pdo)) {
        throw new RuntimeException('The unique uq_orders_order_reference index is required before backfill.');
    }

    $engineStatement = $pdo->prepare(
        "SELECT ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'orders'
         LIMIT 1"
    );
    $engineStatement->execute();
    if (strcasecmp((string) $engineStatement->fetchColumn(), 'InnoDB') !== 0) {
        throw new RuntimeException('The orders table must use InnoDB for transactional backfill.');
    }

    $orderRows = $pdo->query(
        'SELECT order_id, order_reference
         FROM orders
         ORDER BY order_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    $summary['examined'] = count($orderRows);
    $missingOrderIds = [];
    $malformedOrderIds = [];

    foreach ($orderRows as $order) {
        $orderId = (int) $order['order_id'];
        $reference = $order['order_reference'];

        if (backfill_reference_is_missing($reference)) {
            $missingOrderIds[] = $orderId;
            continue;
        }

        $reference = (string) $reference;
        if (normalize_order_tracking_reference($reference) !== $reference) {
            $malformedOrderIds[] = $orderId;
            $summary['malformed']++;
            continue;
        }

        if (preg_match('/\A[0-9]{8}\z/', $reference) === 1) {
            $summary['valid_eight_digit']++;
        } else {
            $summary['valid_legacy']++;
        }
    }

    $summary['missing'] = count($missingOrderIds);
    $summary['problem_order_ids'] = array_values(array_unique($malformedOrderIds));
    $expectedOrderIds = $missingOrderIds;
    sort($expectedOrderIds);
    if ($applyChanges && $approvedOrderIds !== $expectedOrderIds) {
        $summary['problem_order_ids'] = array_values(array_unique(array_merge(
            $summary['problem_order_ids'],
            array_diff($approvedOrderIds, $expectedOrderIds),
            array_diff($expectedOrderIds, $approvedOrderIds)
        )));
        $summary['failed'] = max(1, count($summary['problem_order_ids']));
        throw new RuntimeException(
            'Explicitly approved order IDs must exactly match the current missing-reference orders; review a new dry run.'
        );
    }

    $duplicateOrderIds = backfill_find_duplicate_order_ids($pdo);
    $summary['duplicate'] = count($duplicateOrderIds);
    if ($duplicateOrderIds !== []) {
        $summary['problem_order_ids'] = array_values(array_unique(array_merge(
            $summary['problem_order_ids'],
            $duplicateOrderIds
        )));
    }

    [$receiptTableExists, $receiptsByOrder, $inconsistentReceiptIds, $inconsistentOrderIds] =
        backfill_receipt_reference_data($pdo);
    $summary['problem_receipt_ids'] = $inconsistentReceiptIds;
    $summary['receipt_conflicts'] = count($inconsistentReceiptIds);
    $summary['problem_order_ids'] = array_values(array_unique(array_merge(
        $summary['problem_order_ids'],
        $inconsistentOrderIds
    )));

    if ($receiptTableExists) {
        foreach ($missingOrderIds as $orderId) {
            $receiptRows = isset($receiptsByOrder[$orderId]) ? $receiptsByOrder[$orderId] : [];
            if ($receiptRows === []) {
                continue;
            }

            $receiptReferences = array_values(array_unique(array_column($receiptRows, 'order_reference')));
            if (count($receiptReferences) !== 1) {
                $summary['problem_order_ids'][] = $orderId;
                foreach ($receiptRows as $receiptRow) {
                    $summary['problem_receipt_ids'][] = $receiptRow['receipt_id'];
                }
                continue;
            }
        }
    }

    $summary['problem_order_ids'] = array_values(array_unique($summary['problem_order_ids']));
    $summary['problem_receipt_ids'] = array_values(array_unique($summary['problem_receipt_ids']));
    $summary['receipt_conflicts'] = count($summary['problem_receipt_ids']);
    if (
        $summary['problem_order_ids'] !== []
        || $summary['problem_receipt_ids'] !== []
    ) {
        $summary['failed'] = count($summary['problem_order_ids']) + count($summary['problem_receipt_ids']);
        throw new RuntimeException('Malformed, duplicate, or inconsistent historical references were found; no rows were changed.');
    }

    $occupiedReferences = [];
    foreach ($orderRows as $order) {
        if (!backfill_reference_is_missing($order['order_reference'])) {
            $occupiedReferences[backfill_reference_key((string) $order['order_reference'])] = true;
        }
    }

    $plannedReferences = [];
    $plannedSources = [];
    foreach ($missingOrderIds as $orderId) {
        $receiptRows = isset($receiptsByOrder[$orderId]) ? $receiptsByOrder[$orderId] : [];
        if ($receiptRows !== []) {
            $reference = (string) $receiptRows[0]['order_reference'];
            $referenceStatement = $pdo->prepare(
                'SELECT 1
                 FROM orders
                 WHERE order_reference = :order_reference
                 LIMIT 1'
            );
            $referenceStatement->execute(['order_reference' => $reference]);
            $referenceKey = backfill_reference_key($reference);
            if ($referenceStatement->fetchColumn() || isset($occupiedReferences[$referenceKey])) {
                $summary['problem_order_ids'][] = $orderId;
                foreach ($receiptRows as $receiptRow) {
                    $summary['problem_receipt_ids'][] = $receiptRow['receipt_id'];
                }
                continue;
            }

            $plannedReferences[$orderId] = $reference;
            $plannedSources[$orderId] = 'restored from consistent receipt';
            $occupiedReferences[$referenceKey] = true;
            $summary['restored_from_receipt']++;
            continue;
        }

        $reference = null;
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = order_tracking_reference();
            $candidateKey = backfill_reference_key($candidate);
            if (isset($occupiedReferences[$candidateKey])) {
                continue;
            }

            $referenceStatement = $pdo->prepare(
                'SELECT 1
                 FROM orders
                 WHERE order_reference = :order_reference
                 LIMIT 1'
            );
            $referenceStatement->execute(['order_reference' => $candidate]);
            if ($referenceStatement->fetchColumn()) {
                $occupiedReferences[$candidateKey] = true;
                continue;
            }

            $reference = $candidate;
            $occupiedReferences[$candidateKey] = true;
            break;
        }

        if ($reference === null) {
            $summary['problem_order_ids'][] = $orderId;
            continue;
        }
        $plannedReferences[$orderId] = $reference;
        $plannedSources[$orderId] = 'newly generated';
    }

    $summary['problem_order_ids'] = array_values(array_unique($summary['problem_order_ids']));
    $summary['problem_receipt_ids'] = array_values(array_unique($summary['problem_receipt_ids']));
    if ($summary['problem_order_ids'] !== [] || $summary['problem_receipt_ids'] !== []) {
        $summary['failed'] = count($summary['problem_order_ids']) + count($summary['problem_receipt_ids']);
        throw new RuntimeException('A safe unique reference could not be planned; no rows were changed.');
    }

    foreach ($plannedReferences as $orderId => $reference) {
        $summary['planned_changes'][$orderId] = [
            'reference' => $reference,
            'source' => $plannedSources[$orderId],
        ];
    }

    if (!$applyChanges) {
        $summary['updated'] = count($plannedReferences);
        backfill_print_summary($summary, false);
        exit(0);
    }

    $pdo->beginTransaction();
    $lockedRows = $pdo->query(
        "SELECT order_id
         FROM orders
         WHERE order_reference IS NULL
            OR TRIM(order_reference) = ''
         ORDER BY order_id
         FOR UPDATE"
    )->fetchAll(PDO::FETCH_COLUMN);
    $lockedOrderIds = array_map('intval', $lockedRows);
    sort($lockedOrderIds);
    $expectedOrderIds = $missingOrderIds;
    sort($expectedOrderIds);
    if ($lockedOrderIds !== $expectedOrderIds) {
        throw new RuntimeException('The set of missing-reference orders changed after preview; retry a dry run.');
    }
    [$lockedReceiptTableExists, $lockedReceiptsByOrder, $lockedReceiptIds, $lockedReceiptOrderIds] =
        backfill_receipt_reference_data($pdo, true);
    if (
        $lockedReceiptTableExists !== $receiptTableExists
        || $lockedReceiptIds !== []
        || $lockedReceiptOrderIds !== []
    ) {
        throw new RuntimeException('Receipt references changed or became inconsistent after preview; retry a dry run.');
    }
    foreach ($missingOrderIds as $orderId) {
        $plannedReceiptRows = isset($receiptsByOrder[$orderId]) ? $receiptsByOrder[$orderId] : [];
        $lockedReceiptRows = isset($lockedReceiptsByOrder[$orderId]) ? $lockedReceiptsByOrder[$orderId] : [];
        if ($plannedReceiptRows !== $lockedReceiptRows) {
            throw new RuntimeException('Receipt records changed after preview; retry a dry run.');
        }
    }

    $updateStatement = $pdo->prepare(
        "UPDATE orders
         SET order_reference = :order_reference
         WHERE order_id = :order_id
           AND (order_reference IS NULL OR TRIM(order_reference) = '')"
    );
    foreach ($plannedReferences as $orderId => $reference) {
        $updateStatement->execute([
            'order_reference' => $reference,
            'order_id' => $orderId,
        ]);
        if ($updateStatement->rowCount() !== 1) {
            throw new RuntimeException('An order changed during backfill; the transaction will be rolled back.');
        }
    }

    $postRows = $pdo->query(
        'SELECT order_id, order_reference
         FROM orders
         ORDER BY order_id'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($postRows as $order) {
        $reference = $order['order_reference'];
        if (
            !is_string($reference)
            || normalize_order_tracking_reference($reference) !== $reference
        ) {
            throw new RuntimeException('Backfill validation failed; the transaction will be rolled back.');
        }
    }
    if (backfill_find_duplicate_order_ids($pdo) !== []) {
        throw new RuntimeException('Duplicate references were detected after backfill; the transaction will be rolled back.');
    }

    $pdo->commit();
    $summary['updated'] = count($plannedReferences);
    backfill_print_summary($summary, true);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $summary['failed'] = max($summary['failed'], $summary['missing']);
    backfill_print_summary($summary, $applyChanges);
    fwrite(STDERR, "Reference backfill failed during a database operation; any active transaction was rolled back. Review protected server logs.\n");
    exit(1);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($summary['failed'] === 0) {
        $summary['failed'] = $summary['missing'];
    }
    backfill_print_summary($summary, $applyChanges);
    fwrite(STDERR, 'Reference backfill stopped safely: ' . $exception->getMessage() . "\n");
    exit(1);
}
