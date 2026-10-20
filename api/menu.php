<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ob_start(static function ($output) {
    if (http_response_code() >= 500) {
        return '{"success":false,"error":{"code":"internal_error","message":"Menu is temporarily unavailable."}}';
    }

    return $output;
});

function send_menu_json(int $statusCode, array $body): void
{
    try {
        $json = json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    } catch (JsonException $exception) {
        http_response_code(500);
        echo '{"success":false,"error":{"code":"internal_error","message":"Menu is temporarily unavailable."}}';
        return;
    }

    http_response_code($statusCode);
    echo $json;
}

$requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

if ($requestMethod !== 'GET') {
    header('Allow: GET');
    send_menu_json(405, [
        'success' => false,
        'error' => [
            'code' => 'method_not_allowed',
            'message' => 'Only GET requests are allowed.',
        ],
    ]);
    exit;
}

try {
    require __DIR__ . '/../config/db.php';

    $imageColumnStatement = $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'menu_items'
           AND COLUMN_NAME = 'image_filename'"
    );
    $menuHasImageColumn = (int) $imageColumnStatement->fetchColumn() > 0;
    $statement = $pdo->query(
        'SELECT
            categories.category_id,
            categories.name AS category_name,
            categories.sort_order AS category_sort_order,
            menu_items.item_id,
            menu_items.name AS item_name,
            menu_items.description,
            ' . ($menuHasImageColumn ? 'menu_items.image_filename,' : '') . '
            menu_items.base_price,
            menu_items.stock_quantity,
            menu_items.is_sold_out
         FROM categories
         LEFT JOIN menu_items
            ON menu_items.category_id = categories.category_id
           AND menu_items.is_active = 1
         ORDER BY
            categories.sort_order ASC,
            categories.name ASC,
            menu_items.name ASC,
            menu_items.item_id ASC'
    );

    $categories = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $categoryId = (int) $row['category_id'];

        if (!isset($categories[$categoryId])) {
            $categories[$categoryId] = [
                'id' => $categoryId,
                'name' => (string) $row['category_name'],
                'items' => [],
            ];
        }

        if ($row['item_id'] === null) {
            continue;
        }

        $categories[$categoryId]['items'][] = [
            'id' => (int) $row['item_id'],
            'name' => (string) $row['item_name'],
            'description' => $row['description'] === null
                ? null
                : (string) $row['description'],
            'image_url' => $menuHasImageColumn && is_string($row['image_filename']) && $row['image_filename'] !== ''
                ? 'api/menu_image.php?id=' . (int) $row['item_id']
                : null,
            'price' => (string) $row['base_price'],
            'availability' => (int) $row['is_sold_out'] === 1 || (int) $row['stock_quantity'] < 1
                ? 'sold_out'
                : 'available',
            'stock_quantity' => (int) $row['stock_quantity'],
        ];
    }

    send_menu_json(200, [
        'success' => true,
        'data' => [
            'categories' => array_values($categories),
        ],
    ]);
} catch (Throwable $exception) {
    error_log('[menu-api] Request failed while loading menu data.');
    send_menu_json(500, [
        'success' => false,
        'error' => [
            'code' => 'internal_error',
            'message' => 'Menu is temporarily unavailable.',
        ],
    ]);
}
