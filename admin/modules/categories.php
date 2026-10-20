<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit; }

$error = '';
$successKey = isset($_GET['success']) && is_string($_GET['success']) ? $_GET['success'] : '';
$success = $activeTab === 'categories' && $successKey === 'saved'
    ? 'Category changes saved.'
    : ($activeTab === 'categories' && $successKey === 'deleted' ? 'Category deleted.' : '');
$newCategoryName = '';
$newSortOrder = '';
$submittedCategoryValues = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section']) && is_string($_POST['section']) && $_POST['section'] === 'categories') {
    require_post_request();

    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : null;

    if (!csrf_token_is_valid($token)) {
        $error = 'This form expired. Refresh the page and try again.';
    } else {
        $action = isset($_POST['action']) && is_string($_POST['action'])
            ? $_POST['action']
            : '';
        $name = isset($_POST['name']) && is_string($_POST['name'])
            ? trim($_POST['name'])
            : '';
        $sortOrderInput = isset($_POST['sort_order']) && is_string($_POST['sort_order'])
            ? trim($_POST['sort_order'])
            : '';
        $submittedCategoryId = isset($_POST['category_id']) && is_string($_POST['category_id'])
            ? $_POST['category_id']
            : '';
        if (
            $action === 'update' &&
            filter_var($submittedCategoryId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false
        ) {
            $submittedCategoryValues[$submittedCategoryId] = [
                'name' => isset($_POST['name']) && is_string($_POST['name']) ? trim($_POST['name']) : '',
                'sort_order' => $sortOrderInput,
            ];
        }
        $nameLength = preg_match_all('/./us', $name, $matches);
        $sortOrder = filter_var($sortOrderInput, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 0,
                'max_range' => PHP_INT_MAX,
            ],
        ]);

        if (!in_array($action, ['add', 'update', 'delete'], true)) {
            $error = 'Choose a valid category action.';
        } elseif (
            $action !== 'delete' &&
            $name === '' ||
            $action !== 'delete' &&
            $nameLength === false ||
            $action !== 'delete' &&
            $nameLength > 100 ||
            $action !== 'delete' &&
            preg_match('/[\x00-\x1F\x7F]/', $name) === 1
        ) {
            $error = 'Category name is required and must be no more than 100 characters.';
        } elseif ($action !== 'delete' && ($sortOrderInput === '' || $sortOrder === false)) {
            $error = 'Sort order must be a whole number between 0 and ' . PHP_INT_MAX . '.';
        } else {
            $categoryId = null;
            if (in_array($action, ['update', 'delete'], true)) {
                $categoryIdInput = isset($_POST['category_id']) && is_string($_POST['category_id'])
                    ? $_POST['category_id']
                    : '';
                $categoryId = filter_var($categoryIdInput, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);

                if ($categoryId === false) {
                    $error = 'Select a valid category to update.';
                }
            }

            if ($error === '') {
                try {
                    if ($action === 'delete') {
                        $pdo->beginTransaction();
                        $categoryExists = $pdo->prepare(
                            'SELECT category_id FROM categories WHERE category_id = :category_id FOR UPDATE'
                        );
                        $categoryExists->execute(['category_id' => (int) $categoryId]);
                        if ($categoryExists->fetchColumn() === false) {
                            $pdo->rollBack();
                            $error = 'That category no longer exists. Refresh the page and try again.';
                        } else {
                            $itemCount = $pdo->prepare(
                                'SELECT COUNT(*) FROM menu_items WHERE category_id = :category_id'
                            );
                            $itemCount->execute(['category_id' => (int) $categoryId]);
                            if ((int) $itemCount->fetchColumn() > 0) {
                                $pdo->rollBack();
                                $error = 'Reassign or deactivate all menu items before deleting this category.';
                            } else {
                                $delete = $pdo->prepare(
                                    'DELETE FROM categories WHERE category_id = :category_id'
                                );
                                $delete->execute(['category_id' => (int) $categoryId]);
                                $pdo->commit();
                                admin_redirect('categories', 'success=deleted');
                                exit;
                            }
                        }
                    } else {
                    $duplicateSql = 'SELECT category_id
                                     FROM categories
                                     WHERE name = :name';
                    if ($categoryId !== null) {
                        $duplicateSql .= ' AND category_id <> :category_id';
                    }
                    $duplicateSql .= ' LIMIT 1';
                    $duplicateStatement = $pdo->prepare($duplicateSql);
                    $duplicateStatement->bindValue(':name', $name, PDO::PARAM_STR);
                    if ($categoryId !== null) {
                        $duplicateStatement->bindValue(':category_id', (int) $categoryId, PDO::PARAM_INT);
                    }
                    $duplicateStatement->execute();

                    if ($duplicateStatement->fetchColumn() !== false) {
                        $error = 'A category with that name already exists.';
                    } elseif ($action === 'add') {
                        $insertStatement = $pdo->prepare(
                            'INSERT INTO categories (name, sort_order)
                             VALUES (:name, :sort_order)'
                        );
                        $insertStatement->bindValue(':name', $name, PDO::PARAM_STR);
                        $insertStatement->bindValue(':sort_order', (int) $sortOrder, PDO::PARAM_INT);
                        $insertStatement->execute();

                        admin_redirect('categories', 'success=saved');
                        exit;
                    } else {
                        $categoryExists = $pdo->prepare(
                            'SELECT category_id FROM categories WHERE category_id = :category_id LIMIT 1'
                        );
                        $categoryExists->bindValue(':category_id', (int) $categoryId, PDO::PARAM_INT);
                        $categoryExists->execute();

                        if ($categoryExists->fetchColumn() === false) {
                            $error = 'The selected category no longer exists. Refresh the page and try again.';
                        } else {
                            $updateStatement = $pdo->prepare(
                                'UPDATE categories
                                 SET name = :name,
                                     sort_order = :sort_order
                                 WHERE category_id = :category_id'
                            );
                            $updateStatement->bindValue(':name', $name, PDO::PARAM_STR);
                            $updateStatement->bindValue(':sort_order', (int) $sortOrder, PDO::PARAM_INT);
                            $updateStatement->bindValue(':category_id', (int) $categoryId, PDO::PARAM_INT);
                            $updateStatement->execute();

                            admin_redirect('categories', 'success=saved');
                            exit;
                        }
                    }
                    }
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $driverError = isset($exception->errorInfo[1])
                        ? (int) $exception->errorInfo[1]
                        : 0;
                    if ($driverError === 1062) {
                        $error = 'A category with that name already exists.';
                    } else {
                        error_log('[admin-categories] Category save failed.');
                        http_response_code(500);
                        $error = 'The category could not be saved. Please try again.';
                    }
                }
            }

            if ($action === 'add') {
                $newCategoryName = $name;
                $newSortOrder = $sortOrderInput;
            }
        }
    }
}

try {
    $categories = $pdo->query(
        'SELECT category_id, name, sort_order
         FROM categories
         ORDER BY sort_order ASC, name ASC, category_id ASC'
    )->fetchAll();

    if ($newSortOrder === '') {
        $maximumSortOrder = 0;
        foreach ($categories as $category) {
            $maximumSortOrder = max($maximumSortOrder, (int) $category['sort_order']);
        }
        $newSortOrder = $maximumSortOrder < PHP_INT_MAX
            ? (string) ($maximumSortOrder + 1)
            : '0';
    }
} catch (PDOException $exception) {
    error_log('[admin-categories] Category list could not be loaded.');
    http_response_code(500);
    $categories = [];
    $error = 'Categories could not be loaded. Please try again later.';
}
?>
<div class="mb-space-md">
</div>

    <?php if ($error !== ''): ?>
        <p class="rounded-lg bg-red-50 p-4 text-red-900" role="alert"><?= escape_html($error) ?></p>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <p class="rounded-lg bg-green-50 p-4 text-green-900" role="status"><?= escape_html($success) ?></p>
    <?php endif; ?>

    <section class="<?= ADMIN_PANEL_CLASS ?>">
        <h2 class="font-price-row text-price-row text-primary">Add a category</h2>
        <form method="post" action="dashboard.php?tab=categories">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="section" value="categories">
            <input type="hidden" name="action" value="add">

            <label class="mt-space-sm block text-body-sm font-semibold" for="new-name">Category name</label>
            <input class="<?= ADMIN_INPUT_CLASS ?>"
                id="new-name"
                name="name"
                type="text"
                maxlength="100"
                required
                value="<?= escape_html($newCategoryName) ?>"
            >

            <label class="mt-space-sm block text-body-sm font-semibold" for="new-sort-order">Sort order</label>
            <input class="<?= ADMIN_INPUT_CLASS ?>"
                id="new-sort-order"
                name="sort_order"
                type="number"
                min="0"
                max="<?= PHP_INT_MAX ?>"
                step="1"
                required
                value="<?= escape_html($newSortOrder) ?>"
            >

            <button class="<?= ADMIN_PRIMARY_BUTTON_CLASS ?> mt-space-sm" type="submit">Add category</button>
        </form>
    </section>

    <section class="<?= ADMIN_PANEL_CLASS ?> mt-space-md">
        <h2 class="font-price-row text-price-row text-primary">Existing categories</h2>
        <?php if (empty($categories)): ?>
            <p class="text-body-sm text-muted-text">No categories could be displayed.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="<?= ADMIN_TABLE_CLASS ?>">
                    <thead class="bg-surface-container-low text-muted-text">
                        <tr>
                            <th class="px-space-sm py-space-xs" scope="col">Category ID</th>
                            <th class="px-space-sm py-space-xs" scope="col">Name and sort order</th>
                            <th class="px-space-sm py-space-xs" scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-neutral">
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td class="px-space-sm py-space-xs"><?= (int) $category['category_id'] ?></td>
                            <td class="px-space-sm py-space-xs">
                                <form class="grid gap-space-sm md:grid-cols-2" method="post" action="dashboard.php?tab=categories">
                                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="section" value="categories">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>">

                                    <label class="block text-sm font-semibold">
                                        Category name
                                        <input class="<?= ADMIN_INPUT_CLASS ?>"
                                            name="name"
                                            type="text"
                                            maxlength="100"
                                            required
                                            value="<?= escape_html($submittedCategoryValues[(string) $category['category_id']]['name'] ?? $category['name']) ?>"
                                        >
                                    </label>

                                    <label class="block text-sm font-semibold">
                                        Sort order
                                        <input class="<?= ADMIN_INPUT_CLASS ?>"
                                            name="sort_order"
                                            type="number"
                                            min="0"
                                            max="<?= PHP_INT_MAX ?>"
                                            step="1"
                                            required
                                            value="<?= escape_html($submittedCategoryValues[(string) $category['category_id']]['sort_order'] ?? (string) $category['sort_order']) ?>"
                                        >
                                    </label>

                                    <button class="<?= ADMIN_PRIMARY_BUTTON_CLASS ?>" type="submit">Save</button>
                                </form>
                            </td>
                            <td class="px-space-sm py-space-xs">
                                <form method="post" action="dashboard.php?tab=categories" data-confirm="Delete this category? This is allowed only when no menu items are assigned.">
                                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                    <input type="hidden" name="section" value="categories">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= (int) $category['category_id'] ?>">
                                    <button class="btn-secondary" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <p><a href="dashboard.php?tab=menu">Back to menu management</a> · <a href="../menu.php">View customer menu</a></p>
