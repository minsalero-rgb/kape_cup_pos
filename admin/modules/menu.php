<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit; }
require_once __DIR__ . '/../../includes/menu_images.php';

$error = '';
$imageColumnStatement = $pdo->prepare(
    'SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table_name
       AND COLUMN_NAME = :column_name'
);
$imageColumnStatement->execute(['table_name' => 'menu_items', 'column_name' => 'image_filename']);
$menuHasImageColumn = (int) $imageColumnStatement->fetchColumn() > 0;
$menuImageUpload = null;
$oldMenuImageFilename = null;
$successKey = isset($_GET['success']) && is_string($_GET['success']) ? $_GET['success'] : '';
$success = $activeTab === 'menu' && $successKey === 'saved'
    ? 'Menu changes saved successfully.'
    : ($activeTab === 'menu' && $successKey === 'availability' ? 'Menu availability updated.' : '');
$form = [
    'item_id' => '',
    'category_id' => '',
    'name' => '',
    'description' => '',
    'base_price' => '',
    'image_filename' => '',
];

$requestedId = $activeTab === 'menu' && isset($_GET['edit']) && is_string($_GET['edit'])
    ? filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]
    )
    : null;

$menuAction = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['section']) &&
    is_string($_POST['section']) &&
    $_POST['section'] === 'menu' &&
    in_array($menuAction, ['toggle_active', 'toggle_sold_out'], true)
) {
    require_post_request();
    if (!csrf_token_is_valid(isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        http_response_code(403);
        $error = 'This form expired. Refresh the page and try again.';
    } else {
        $itemIdInput = isset($_POST['item_id']) && is_string($_POST['item_id']) ? $_POST['item_id'] : '';
        $itemId = filter_var($itemIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($itemId === false) {
            $error = 'Choose a valid menu item.';
        } else {
            $column = $menuAction === 'toggle_active' ? 'is_active' : 'is_sold_out';
            $currentStatement = $pdo->prepare(
                'SELECT is_active, is_sold_out FROM menu_items WHERE item_id = :item_id LIMIT 1'
            );
            $currentStatement->execute(['item_id' => (int) $itemId]);
            $current = $currentStatement->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                $error = 'That menu item could not be found.';
            } else {
                $nextValue = (int) $current[$column] === 1 ? 0 : 1;
                try {
                    $update = $pdo->prepare(
                        'UPDATE menu_items SET ' . $column . ' = :next_value WHERE item_id = :item_id'
                    );
                    $update->execute([
                        'next_value' => $nextValue,
                        'item_id' => (int) $itemId,
                    ]);
                    admin_redirect('menu', 'success=availability');
                } catch (PDOException $exception) {
                    error_log('[admin-menu] Menu availability update failed.');
                    http_response_code(500);
                    $error = 'Menu availability could not be changed. Please try again.';
                }
            }
        }
    }
}

if ($requestedId === false) {
    http_response_code(400);
    $error = 'Choose a valid menu item.';
} elseif ($requestedId !== null) {
    $itemStatement = $pdo->prepare(
        'SELECT item_id, category_id, name, description, base_price'
        . ($menuHasImageColumn ? ', image_filename' : '')
        . ' FROM menu_items WHERE item_id = :item_id LIMIT 1'
    );
    $itemStatement->execute(['item_id' => (int) $requestedId]);
    $item = $itemStatement->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        $error = 'That menu item could not be found.';
    } else {
        $form = [
            'item_id' => (string) $item['item_id'],
            'category_id' => (string) $item['category_id'],
            'name' => (string) $item['name'],
            'description' => $item['description'] === null ? '' : (string) $item['description'],
            'base_price' => (string) $item['base_price'],
            'image_filename' => $menuHasImageColumn && is_string($item['image_filename'])
                ? $item['image_filename']
                : '',
        ];
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['section']) &&
    is_string($_POST['section']) &&
    $_POST['section'] === 'menu' &&
    !in_array($menuAction, ['toggle_active', 'toggle_sold_out'], true)
) {
    require_post_request();

    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : null;

    if (!csrf_token_is_valid($token)) {
        http_response_code(403);
        $error = 'This form expired. Refresh the page and try again.';
    } else {
        $itemIdInput = isset($_POST['item_id']) && is_string($_POST['item_id']) ? $_POST['item_id'] : '';
        $itemId = $itemIdInput === ''
            ? null
            : filter_var($itemIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]);
        $categoryIdInput = isset($_POST['category_id']) && is_string($_POST['category_id']) ? $_POST['category_id'] : '';
        $categoryId = filter_var($categoryIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]);
        $name = isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) && is_string($_POST['description']) ? trim($_POST['description']) : '';
        $price = isset($_POST['base_price']) && is_string($_POST['base_price']) ? trim($_POST['base_price']) : '';
        $removeImage = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
        $nameLength = preg_match_all('/./us', $name, $matches);

        $form = [
            'item_id' => $itemId === null ? '' : (string) $itemIdInput,
            'category_id' => $categoryIdInput,
            'name' => $name,
            'description' => $description,
            'base_price' => $price,
            'image_filename' => '',
        ];

        if ($itemId === false) {
            $error = 'Choose a valid item to update.';
        } elseif ($categoryId === false) {
            $error = 'Choose a valid category.';
        } elseif ($name === '' || $nameLength === false || $nameLength > 150 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            $error = 'Item name is required and must be no more than 150 characters.';
        } elseif (strlen($description) > 60000) {
            $error = 'Description is too long.';
        } elseif (preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $price) !== 1 || order_money_to_cents($price) === null || order_money_to_cents($price) <= 0) {
            $error = 'Enter a positive price with up to two decimal places.';
        } else {
            $categoryCheck = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = :category_id LIMIT 1');
            $categoryCheck->execute(['category_id' => (int) $categoryId]);

            if ($categoryCheck->fetchColumn() === false) {
                $error = 'The selected category no longer exists.';
            } else {
                $existingItem = null;
                if ($itemId !== null) {
                    $existingStatement = $pdo->prepare(
                        'SELECT item_id'
                        . ($menuHasImageColumn ? ', image_filename' : '')
                        . ' FROM menu_items WHERE item_id = :item_id LIMIT 1'
                    );
                    $existingStatement->execute(['item_id' => (int) $itemId]);
                    $existingItem = $existingStatement->fetch(PDO::FETCH_ASSOC);
                    if (!$existingItem) {
                        $error = 'That menu item no longer exists.';
                    } elseif ($menuHasImageColumn && is_string($existingItem['image_filename'])) {
                        $oldMenuImageFilename = $existingItem['image_filename'];
                    }
                }

                $uploadedFile = isset($_FILES['image_file']) && is_array($_FILES['image_file'])
                    ? $_FILES['image_file']
                    : null;
                $hasImageUpload = $uploadedFile !== null
                    && (!isset($uploadedFile['error']) || $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE);
                if ($error === '' && $hasImageUpload && !$menuHasImageColumn) {
                    $error = 'Apply database migration 004 before uploading menu images.';
                } elseif ($error === '' && $removeImage && $hasImageUpload) {
                    $error = 'Choose either a replacement image or remove the current image.';
                }

                try {
                    if ($error === '') {
                        if ($menuHasImageColumn && $hasImageUpload) {
                            $menuImageUpload = save_menu_image_upload($uploadedFile);
                        }

                        $nextImageFilename = $menuImageUpload !== null
                            ? $menuImageUpload['filename']
                            : ($removeImage ? null : $oldMenuImageFilename);
                        if ($itemId === null) {
                            $save = $pdo->prepare(
                                'INSERT INTO menu_items (category_id, name, description, base_price'
                                . ($menuHasImageColumn ? ', image_filename' : '')
                                . ') VALUES (:category_id, :name, :description, :base_price'
                                . ($menuHasImageColumn ? ', :image_filename' : '')
                                . ')'
                            );
                            $parameters = [
                                'category_id' => (int) $categoryId,
                                'name' => $name,
                                'description' => $description === '' ? null : $description,
                                'base_price' => $price,
                            ];
                            if ($menuHasImageColumn) {
                                $parameters['image_filename'] = $nextImageFilename;
                            }
                            $save->execute($parameters);
                        } else {
                            $save = $pdo->prepare(
                                'UPDATE menu_items
                                 SET category_id = :category_id,
                                     name = :name,
                                     description = :description,
                                     base_price = :base_price'
                                     . ($menuHasImageColumn ? ', image_filename = :image_filename' : '')
                                     . '
                                 WHERE item_id = :item_id'
                            );
                            $parameters = [
                                'category_id' => (int) $categoryId,
                                'name' => $name,
                                'description' => $description === '' ? null : $description,
                                'base_price' => $price,
                                'item_id' => (int) $itemId,
                            ];
                            if ($menuHasImageColumn) {
                                $parameters['image_filename'] = $nextImageFilename;
                            }
                            $save->execute($parameters);
                        }

                        if ($menuHasImageColumn && $oldMenuImageFilename !== null && ($menuImageUpload !== null || $removeImage)) {
                            remove_menu_image($oldMenuImageFilename);
                        }
                        admin_redirect('menu', 'success=saved');
                        exit;
                    }
                } catch (MenuImageUploadException $exception) {
                    $error = $exception->getMessage();
                } catch (RuntimeException $exception) {
                    if ($menuImageUpload !== null) {
                        remove_menu_image($menuImageUpload['filename']);
                        $menuImageUpload = null;
                    }
                    error_log('[admin-menu-form] Menu image storage failed.');
                    $error = 'The menu image could not be saved. Check the upload directory permissions and try again.';
                } catch (PDOException $exception) {
                    if ($menuImageUpload !== null) {
                        remove_menu_image($menuImageUpload['filename']);
                        $menuImageUpload = null;
                    }
                    error_log('[admin-menu-form] Menu item save failed.');
                    http_response_code(500);
                    $error = 'The menu item could not be saved. Please try again.';
                }
            }
        }
    }
}

$categories = $pdo->query('SELECT category_id, name FROM categories ORDER BY sort_order, name')->fetchAll(PDO::FETCH_ASSOC);
$items = $pdo->query(
    'SELECT menu_items.item_id, menu_items.name, menu_items.base_price'
    . ($menuHasImageColumn ? ', menu_items.image_filename' : '')
    . ',
            menu_items.is_active, menu_items.is_sold_out, menu_items.stock_quantity,
            categories.name AS category_name
     FROM menu_items
     INNER JOIN categories ON categories.category_id = menu_items.category_id
     ORDER BY categories.sort_order, categories.name, menu_items.name, menu_items.item_id'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<div>
            <h2 class="mt-space-xs font-headline-sm text-headline-sm text-primary"><?= $form['item_id'] === '' ? 'Add Menu Item' : 'Edit Menu Item' ?></h2>
            <p class="mt-space-xs text-body-sm text-muted-text">Signed in as <?= escape_html($admin['full_name']) ?>. Use this shared form for both creating and updating menu items.</p>
        </div>

        <?php if ($success !== ''): ?>
            <p class="rounded-lg bg-green-50 p-4 text-green-900" role="status"><?= escape_html($success) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="rounded-lg bg-red-50 p-4 text-red-900" role="alert"><?= escape_html($error) ?></p>
        <?php endif; ?>

        <section class="admin-menu-form-panel rounded-2xl border border-[#E8DEC9] bg-[#FFFCF6] p-6 shadow-sm">
            <h2 class="text-xl font-bold text-[#3B1F14]"><?= $form['item_id'] === '' ? 'Create menu item' : 'Update menu item #' . escape_html($form['item_id']) ?></h2>
            <form method="post" action="dashboard.php?tab=menu" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="section" value="menu"><input type="hidden" name="action" value="save">
                <input type="hidden" name="item_id" value="<?= escape_html($form['item_id']) ?>">

                <label class="text-sm font-semibold">
                    Category
                    <select class="mt-1 block w-full rounded-lg border border-[#E5D8C3] bg-white px-3 py-2" name="category_id" required>
                        <option value="">Choose a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= $form['category_id'] === (string) $category['category_id'] ? 'selected' : '' ?>><?= escape_html($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="text-sm font-semibold">
                    Item name
                    <input class="mt-1 block w-full rounded-lg border border-[#E5D8C3] bg-white px-3 py-2" name="name" maxlength="150" required value="<?= escape_html($form['name']) ?>">
                </label>

                <label class="text-sm font-semibold">
                    Base price (PHP)
                    <input class="mt-1 block w-full rounded-lg border border-[#E5D8C3] bg-white px-3 py-2" name="base_price" inputmode="decimal" pattern="[0-9]{1,8}(\.[0-9]{1,2})?" required value="<?= escape_html($form['base_price']) ?>">
                </label>

                <label class="text-sm font-semibold md:col-span-2">
                    Description
                    <textarea class="mt-1 block w-full rounded-lg border border-[#E5D8C3] bg-white px-3 py-2" name="description" rows="3" maxlength="60000"><?= escape_html($form['description']) ?></textarea>
                </label>

                <?php if ($menuHasImageColumn): ?>
                    <label class="text-sm font-semibold md:col-span-2">
                        Product image (JPEG, PNG, or WebP; up to 5 MB)
                        <input class="mt-1 block w-full rounded-lg border border-[#E5D8C3] bg-white px-3 py-2" type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <?php if ($form['image_filename'] !== ''): ?>
                        <div class="flex items-center gap-4 md:col-span-2">
                            <img class="h-20 w-20 rounded-xl object-cover" src="../api/menu_image.php?id=<?= (int) $form['item_id'] ?>" alt="Current image for <?= escape_html($form['name']) ?>">
                            <label class="flex items-center gap-2 text-sm font-semibold">
                                <input type="checkbox" name="remove_image" value="1">
                                Remove current image
                            </label>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-sm text-amber-900 md:col-span-2">Menu images are disabled until database migration 004 is applied.</p>
                <?php endif; ?>

                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <button class="rounded-lg bg-[#3B1F14] px-5 py-3 font-semibold text-white hover:bg-[#220B03]" type="submit">Save item</button>
                    <a class="rounded-lg border border-[#D9B77E] px-5 py-3 font-semibold text-[#3B1F14]" href="dashboard.php?tab=menu">Cancel</a>
                    <?php if ($form['item_id'] !== ''): ?>
                        <a class="rounded-lg border border-[#D9B77E] px-5 py-3 font-semibold text-[#3B1F14]" href="dashboard.php?tab=menu">Create another</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="admin-menu-list-panel overflow-hidden rounded-2xl border border-[#E8DEC9] bg-[#FFFCF6] shadow-sm">
            <div class="border-b border-[#E8DEC9] p-5">
                <h2 class="text-xl font-bold text-[#3B1F14]">Current menu items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[#E8DEC9] text-left text-sm">
                    <thead class="bg-[#F6F3ED] text-[#74685E]">
                        <tr>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Base price</th>
                            <th class="px-4 py-3">Availability</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E8DEC9]">
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <?php if ($menuHasImageColumn && is_string($item['image_filename']) && $item['image_filename'] !== ''): ?>
                                        <img class="mb-2 h-14 w-14 rounded-lg object-cover" src="../api/menu_image.php?id=<?= (int) $item['item_id'] ?>" alt="">
                                    <?php endif; ?>
                                    <?= escape_html($item['name']) ?>
                                </td>
                                <td class="px-4 py-3"><?= escape_html($item['category_name']) ?></td>
                                <td class="px-4 py-3">₱<?= escape_html($item['base_price']) ?></td>
                                <td class="px-4 py-3">
                                    <?php if ((int) $item['is_active'] !== 1): ?>
                                        Inactive
                                    <?php elseif ((int) $item['is_sold_out'] === 1): ?>
                                        Sold out · <?= (int) $item['stock_quantity'] ?> in stock
                                    <?php elseif ((int) $item['stock_quantity'] < 1): ?>
                                        Out of stock · 0 in stock
                                    <?php else: ?>
                                        Available · <?= (int) $item['stock_quantity'] ?> in stock
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <a class="font-semibold text-[#3B1F14] underline" href="dashboard.php?tab=menu&amp;edit=<?= (int) $item['item_id'] ?>">Edit</a>
                                    <form class="mt-2" method="post" action="dashboard.php?tab=menu" data-confirm="<?= (int) $item['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this item? Historical orders will remain unchanged.">
                                        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                        <input type="hidden" name="section" value="menu">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                        <button class="rounded-lg border border-[#D9B77E] px-3 py-2 font-semibold text-[#3B1F14]" type="submit"><?= (int) $item['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                    </form>
                                    <?php if ((int) $item['is_active'] === 1): ?>
                                        <form class="mt-2" method="post" action="dashboard.php?tab=menu">
                                            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                            <input type="hidden" name="section" value="menu">
                                            <input type="hidden" name="action" value="toggle_sold_out">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                            <button class="rounded-lg border border-[#D9B77E] px-3 py-2 font-semibold text-[#3B1F14]" type="submit"><?= (int) $item['is_sold_out'] === 1 ? 'Mark available' : 'Mark sold out' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
