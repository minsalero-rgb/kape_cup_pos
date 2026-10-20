<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit; }

$error = '';
$success = $activeTab === 'modifiers' && isset($_GET['success']) && $_GET['success'] === 'saved' ? 'Modifier changes saved.' : '';
$formValues = [];

function modifier_post_string(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

function modifier_name_is_valid(string $name): bool
{
    $length = preg_match_all('/./us', $name, $matches);
    return $name !== '' &&
        $length !== false &&
        $length <= 100 &&
        preg_match('/[\x00-\x1F\x7F]/', $name) !== 1;
}

function modifier_key_is_valid(string $key): bool
{
    return preg_match('/^[a-z0-9][a-z0-9_-]{0,59}$/', $key) === 1;
}

function modifier_integer(string $value, int $minimum, int $maximum)
{
    if ($value === '' || preg_match('/^(0|[1-9][0-9]*)$/', $value) !== 1) {
        return false;
    }

    $integer = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $minimum, 'max_range' => $maximum],
    ]);
    return $integer === false ? false : (int) $integer;
}

function modifier_price_is_valid(string $value): bool
{
    return preg_match('/^-?(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/', $value) === 1;
}

function modifier_duplicate_error(PDOException $exception): bool
{
    return isset($exception->errorInfo[1]) && (int) $exception->errorInfo[1] === 1062;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section']) && is_string($_POST['section']) && $_POST['section'] === 'modifiers') {
    require_post_request();

    $token = modifier_post_string('csrf_token');
    if (!csrf_token_is_valid($token)) {
        $error = 'This form expired. Refresh the page and try again.';
    } else {
        $action = modifier_post_string('action');
        foreach ($_POST as $field => $value) {
            if (is_string($value)) {
                $formValues[$field] = trim($value);
            }
        }

        try {
            if ($action === 'add_group' || $action === 'update_group') {
                $name = modifier_post_string('name');
                $groupKey = modifier_post_string('group_key');
                $itemId = modifier_integer(modifier_post_string('item_id'), 1, PHP_INT_MAX);
                $requiredInput = modifier_post_string('is_required');
                $isRequired = $requiredInput === '1' ? 1 : ($requiredInput === '0' ? 0 : null);
                $minimum = modifier_integer(modifier_post_string('min_selections'), 0, 65535);
                $maximum = modifier_integer(modifier_post_string('max_selections'), 1, 65535);
                $sortOrder = modifier_integer(modifier_post_string('sort_order'), 0, PHP_INT_MAX);

                if (!modifier_name_is_valid($name)) {
                    $error = 'Group name is required and must be no more than 100 characters.';
                } elseif (!modifier_key_is_valid($groupKey)) {
                    $error = 'Group key must be 1–60 lowercase letters, numbers, hyphens, or underscores, starting with a letter or number.';
                } elseif ($itemId === false && $action === 'add_group') {
                    $error = 'Choose a valid menu item.';
                } elseif ($isRequired === null) {
                    $error = 'Choose whether this group is required or optional.';
                } elseif ($minimum === false || $maximum === false || $minimum > $maximum) {
                    $error = 'Selection limits must be valid, and the minimum cannot exceed the maximum.';
                } elseif (($isRequired === 1 && $minimum < 1) || ($isRequired === 0 && $minimum !== 0)) {
                    $error = 'Required groups need a minimum of at least 1; optional groups must have a minimum of 0.';
                } elseif ($sortOrder === false) {
                    $error = 'Sort order must be a whole number from 0 to ' . PHP_INT_MAX . '.';
                } else {
                    $groupId = null;
                    if ($action === 'update_group') {
                        $groupId = modifier_integer(modifier_post_string('modifier_group_id'), 1, PHP_INT_MAX);
                        $activeInput = modifier_post_string('is_active');
                        $isActive = $activeInput === '1' ? 1 : ($activeInput === '0' ? 0 : null);
                        if ($groupId === false) {
                            $error = 'Select a valid modifier group.';
                        } elseif ($isActive === null) {
                            $error = 'Choose whether this group is active or inactive.';
                        }
                    } else {
                        $isActive = 0;
                    }

                    if ($error === '') {
                        $pdo->beginTransaction();

                        if ($action === 'add_group') {
                            $itemStatement = $pdo->prepare(
                                'SELECT item_id FROM menu_items WHERE item_id = :item_id LIMIT 1'
                            );
                            $itemStatement->bindValue(':item_id', $itemId, PDO::PARAM_INT);
                            $itemStatement->execute();
                            if ($itemStatement->fetchColumn() === false) {
                                $error = 'The selected menu item no longer exists.';
                            } else {
                                $insert = $pdo->prepare(
                                    'INSERT INTO menu_modifier_groups
                                        (item_id, group_key, name, is_required, min_selections, max_selections, sort_order, is_active)
                                     VALUES
                                        (:item_id, :group_key, :name, :is_required, :min_selections, :max_selections, :sort_order, 0)'
                                );
                                $insert->execute([
                                    'item_id' => (int) $itemId,
                                    'group_key' => $groupKey,
                                    'name' => $name,
                                    'is_required' => $isRequired,
                                    'min_selections' => $minimum,
                                    'max_selections' => $maximum,
                                    'sort_order' => $sortOrder,
                                ]);
                            }
                        } else {
                            $groupStatement = $pdo->prepare(
                                'SELECT modifier_group_id
                                 FROM menu_modifier_groups
                                 WHERE modifier_group_id = :group_id
                                 FOR UPDATE'
                            );
                            $groupStatement->bindValue(':group_id', (int) $groupId, PDO::PARAM_INT);
                            $groupStatement->execute();

                            if ($groupStatement->fetchColumn() === false) {
                                $error = 'The selected modifier group no longer exists.';
                            } else {
                                $countStatement = $pdo->prepare(
                                    'SELECT COUNT(*)
                                     FROM menu_modifier_options
                                     WHERE modifier_group_id = :group_id
                                       AND is_active = 1'
                                );
                                $countStatement->execute(['group_id' => (int) $groupId]);
                                $activeOptionCount = (int) $countStatement->fetchColumn();

                                if ($isActive === 1 && $activeOptionCount < $maximum) {
                                    $error = 'Add enough active options to meet the group maximum before activating this group.';
                                } else {
                                    $update = $pdo->prepare(
                                        'UPDATE menu_modifier_groups
                                         SET group_key = :group_key,
                                             name = :name,
                                             is_required = :is_required,
                                             min_selections = :min_selections,
                                             max_selections = :max_selections,
                                             sort_order = :sort_order,
                                             is_active = :is_active
                                         WHERE modifier_group_id = :group_id'
                                    );
                                    $update->execute([
                                        'group_key' => $groupKey,
                                        'name' => $name,
                                        'is_required' => $isRequired,
                                        'min_selections' => $minimum,
                                        'max_selections' => $maximum,
                                        'sort_order' => $sortOrder,
                                        'is_active' => $isActive,
                                        'group_id' => (int) $groupId,
                                    ]);
                                }
                            }
                        }

                        if ($error === '') {
                            $pdo->commit();
                            admin_redirect('modifiers', 'success=saved');
                            exit;
                        }
                        $pdo->rollBack();
                    }
                }
            } elseif ($action === 'add_option' || $action === 'update_option') {
                $groupId = modifier_integer(modifier_post_string('modifier_group_id'), 1, PHP_INT_MAX);
                $optionKey = modifier_post_string('option_key');
                $name = modifier_post_string('name');
                $price = modifier_post_string('price_delta');
                $sortOrder = modifier_integer(modifier_post_string('sort_order'), 0, PHP_INT_MAX);

                if ($groupId === false) {
                    $error = 'Choose a valid modifier group.';
                } elseif (!modifier_key_is_valid($optionKey)) {
                    $error = 'Option key must be 1–60 lowercase letters, numbers, hyphens, or underscores, starting with a letter or number.';
                } elseif (!modifier_name_is_valid($name)) {
                    $error = 'Option name is required and must be no more than 100 characters.';
                } elseif (!modifier_price_is_valid($price)) {
                    $error = 'Enter a fixed price adjustment between -99,999,999.99 and 99,999,999.99 with up to 2 decimal places.';
                } elseif ($sortOrder === false) {
                    $error = 'Sort order must be a whole number from 0 to ' . PHP_INT_MAX . '.';
                } else {
                    $activeInput = $action === 'update_option'
                        ? modifier_post_string('is_active')
                        : '1';
                    $isActive = $activeInput === '1' ? 1 : ($activeInput === '0' ? 0 : null);
                    $optionId = $action === 'update_option'
                        ? modifier_integer(modifier_post_string('modifier_option_id'), 1, PHP_INT_MAX)
                        : null;

                    if ($isActive === null) {
                        $error = 'Choose whether this option is active or inactive.';
                    } elseif ($action === 'update_option' && $optionId === false) {
                        $error = 'Select a valid modifier option.';
                    }

                    if ($error === '') {
                        $pdo->beginTransaction();

                        $groupStatement = $pdo->prepare(
                            'SELECT modifier_group_id, is_active, max_selections
                             FROM menu_modifier_groups
                             WHERE modifier_group_id = :group_id
                             FOR UPDATE'
                        );
                        $groupStatement->bindValue(':group_id', (int) $groupId, PDO::PARAM_INT);
                        $groupStatement->execute();
                        $group = $groupStatement->fetch(PDO::FETCH_ASSOC);

                        if (!$group) {
                            $error = 'The selected modifier group no longer exists.';
                        } else {
                            $existingOption = null;
                            if ($action === 'update_option') {
                                $optionStatement = $pdo->prepare(
                                    'SELECT modifier_option_id, is_active
                                     FROM menu_modifier_options
                                     WHERE modifier_option_id = :option_id
                                       AND modifier_group_id = :group_id
                                     FOR UPDATE'
                                );
                                $optionStatement->execute([
                                    'option_id' => (int) $optionId,
                                    'group_id' => (int) $groupId,
                                ]);
                                $existingOption = $optionStatement->fetch(PDO::FETCH_ASSOC);
                                if (!$existingOption) {
                                    $error = 'The selected option no longer exists in this group.';
                                }
                            }

                            if ($error === '') {
                                $countStatement = $pdo->prepare(
                                    'SELECT COUNT(*)
                                     FROM menu_modifier_options
                                     WHERE modifier_group_id = :group_id
                                       AND is_active = 1'
                                );
                                $countStatement->execute(['group_id' => (int) $groupId]);
                                $activeOptionCount = (int) $countStatement->fetchColumn();
                                $resultingActiveCount = $activeOptionCount;
                                if ($action === 'add_option' && $isActive === 1) {
                                    $resultingActiveCount++;
                                } elseif ($action === 'update_option') {
                                    if ((int) $existingOption['is_active'] === 1 && $isActive === 0) {
                                        $resultingActiveCount--;
                                    } elseif ((int) $existingOption['is_active'] === 0 && $isActive === 1) {
                                        $resultingActiveCount++;
                                    }
                                }

                                if (
                                    (int) $group['is_active'] === 1 &&
                                    $resultingActiveCount < (int) $group['max_selections']
                                ) {
                                    $error = 'This option cannot be deactivated while its group is active. Deactivate the group or adjust its selection limits first.';
                                } elseif ($action === 'add_option') {
                                    $insert = $pdo->prepare(
                                        'INSERT INTO menu_modifier_options
                                            (modifier_group_id, option_key, name, price_delta, is_active, sort_order)
                                         VALUES
                                            (:group_id, :option_key, :name, :price_delta, :is_active, :sort_order)'
                                    );
                                    $insert->execute([
                                        'group_id' => (int) $groupId,
                                        'option_key' => $optionKey,
                                        'name' => $name,
                                        'price_delta' => $price,
                                        'is_active' => $isActive,
                                        'sort_order' => $sortOrder,
                                    ]);
                                } else {
                                    $update = $pdo->prepare(
                                        'UPDATE menu_modifier_options
                                         SET option_key = :option_key,
                                             name = :name,
                                             price_delta = :price_delta,
                                             is_active = :is_active,
                                             sort_order = :sort_order
                                         WHERE modifier_option_id = :option_id
                                           AND modifier_group_id = :group_id'
                                    );
                                    $update->execute([
                                        'option_key' => $optionKey,
                                        'name' => $name,
                                        'price_delta' => $price,
                                        'is_active' => $isActive,
                                        'sort_order' => $sortOrder,
                                        'option_id' => (int) $optionId,
                                        'group_id' => (int) $groupId,
                                    ]);
                                }
                            }
                        }

                        if ($error === '') {
                            $pdo->commit();
                            admin_redirect('modifiers', 'success=saved');
                            exit;
                        }
                        $pdo->rollBack();
                    }
                }
            } else {
                $error = 'Choose a valid modifier action.';
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (modifier_duplicate_error($exception)) {
                $error = 'That key is already in use for this menu item or modifier group.';
            } else {
                error_log('[admin-modifiers] Modifier save failed.');
                http_response_code(500);
                $error = 'The modifier changes could not be saved. Please try again.';
            }
        }
    }
}

try {
    $items = $pdo->query(
        'SELECT item_id, name, is_active
         FROM menu_items
         ORDER BY is_active DESC, name ASC, item_id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $groups = $pdo->query(
        'SELECT g.modifier_group_id, g.item_id, g.group_key, g.name,
                g.is_required, g.min_selections, g.max_selections,
                g.sort_order, g.is_active, m.name AS item_name, m.is_active AS item_is_active,
                (SELECT COUNT(*)
                 FROM menu_modifier_options o
                 WHERE o.modifier_group_id = g.modifier_group_id) AS option_count,
                (SELECT COUNT(*)
                 FROM menu_modifier_options o
                 WHERE o.modifier_group_id = g.modifier_group_id
                   AND o.is_active = 1) AS active_option_count
         FROM menu_modifier_groups g
         INNER JOIN menu_items m ON m.item_id = g.item_id
         ORDER BY m.name, g.sort_order, g.name, g.modifier_group_id'
    )->fetchAll(PDO::FETCH_ASSOC);

    $optionStatement = $pdo->prepare(
        'SELECT o.modifier_option_id, o.modifier_group_id, o.option_key, o.name,
                o.price_delta, o.is_active, o.sort_order,
                (SELECT COUNT(*)
                 FROM order_item_modifiers h
                 WHERE h.modifier_option_id = o.modifier_option_id) AS historical_use_count
         FROM menu_modifier_options o
         WHERE o.modifier_group_id = :group_id
         ORDER BY o.sort_order, o.name, o.modifier_option_id'
    );
} catch (PDOException $exception) {
    error_log('[admin-modifiers] Modifier list could not be loaded.');
    http_response_code(500);
    $items = [];
    $groups = [];
    $optionStatement = null;
    $error = 'Modifier data could not be loaded. Please try again later.';
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
        <h2 class="font-price-row text-price-row text-primary">Add a modifier group</h2>
        <p class="text-body-sm text-muted-text">
            Enter only owner-confirmed modifier names and rules. New groups are saved inactive;
            add their options, then activate the group after its selection limits are valid.
        </p>
        <?php if (empty($items)): ?>
            <p>No menu items exist yet. Add an owner-confirmed menu item before creating a modifier group.</p>
        <?php else: ?>
            <form method="post" action="dashboard.php?tab=modifiers">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="section" value="modifiers">
                <input type="hidden" name="action" value="add_group">
                <div class="grid gap-space-sm md:grid-cols-2">
                    <label class="block text-sm font-semibold">
                        Menu item
                        <select class="<?= ADMIN_INPUT_CLASS ?>" name="item_id" required>
                            <option value="">Choose a menu item</option>
                            <?php foreach ($items as $item): ?>
                                <option
                                    value="<?= (int) $item['item_id'] ?>"
                                    <?= ($formValues['action'] ?? '') === 'add_group' && isset($formValues['item_id']) && (string) $formValues['item_id'] === (string) $item['item_id'] ? 'selected' : '' ?>
                                >
                                    <?= escape_html($item['name']) ?><?= (int) $item['is_active'] === 1 ? '' : ' (inactive)' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">
                        Group name
                        <input class="<?= ADMIN_INPUT_CLASS ?>" name="name" type="text" maxlength="100" required value="<?= escape_html(($formValues['action'] ?? '') === 'add_group' ? ($formValues['name'] ?? '') : '') ?>">
                    </label>
                    <label class="block text-sm font-semibold">
                        Group key
                        <input class="<?= ADMIN_INPUT_CLASS ?>" name="group_key" type="text" maxlength="60" pattern="[a-z0-9][a-z0-9_-]{0,59}" required value="<?= escape_html(($formValues['action'] ?? '') === 'add_group' ? ($formValues['group_key'] ?? '') : '') ?>">
                    </label>
                    <label class="block text-sm font-semibold">
                        Selection rule
                        <select class="<?= ADMIN_INPUT_CLASS ?>" name="is_required" required>
                            <option value="">Choose required or optional</option>
                            <option value="1" <?= ($formValues['action'] ?? '') === 'add_group' && isset($formValues['is_required']) && (string) $formValues['is_required'] === '1' ? 'selected' : '' ?>>Required</option>
                            <option value="0" <?= ($formValues['action'] ?? '') === 'add_group' && isset($formValues['is_required']) && (string) $formValues['is_required'] === '0' ? 'selected' : '' ?>>Optional</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold">
                        Minimum selections
                        <input class="<?= ADMIN_INPUT_CLASS ?>" name="min_selections" type="number" min="0" max="65535" step="1" required value="<?= escape_html(($formValues['action'] ?? '') === 'add_group' ? ($formValues['min_selections'] ?? '') : '') ?>">
                    </label>
                    <label class="block text-sm font-semibold">
                        Maximum selections
                        <input class="<?= ADMIN_INPUT_CLASS ?>" name="max_selections" type="number" min="1" max="65535" step="1" required value="<?= escape_html(($formValues['action'] ?? '') === 'add_group' ? ($formValues['max_selections'] ?? '') : '') ?>">
                    </label>
                    <label class="block text-sm font-semibold">
                        Sort order
                        <input class="<?= ADMIN_INPUT_CLASS ?>" name="sort_order" type="number" min="0" max="<?= PHP_INT_MAX ?>" step="1" required value="<?= escape_html(($formValues['action'] ?? '') === 'add_group' ? ($formValues['sort_order'] ?? '0') : '0') ?>">
                    </label>
                </div>
                <button class="<?= ADMIN_PRIMARY_BUTTON_CLASS ?>" type="submit">Add modifier group</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="mt-space-md">
        <h2 class="mb-space-md font-price-row text-price-row text-primary">Existing modifier groups and options</h2>
        <?php if (empty($groups)): ?>
            <p class="text-body-sm text-muted-text">No modifier groups have been configured.</p>
        <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <?php
                $groupFormValues = ($formValues['action'] ?? '') === 'update_group' &&
                    ($formValues['modifier_group_id'] ?? '') === (string) $group['modifier_group_id']
                    ? $formValues
                    : [];
                $addOptionValues = ($formValues['action'] ?? '') === 'add_option' &&
                    ($formValues['modifier_group_id'] ?? '') === (string) $group['modifier_group_id']
                    ? $formValues
                    : [];
                ?>
                <article class="mb-space-md <?= ADMIN_PANEL_CLASS ?>">
                    <h3>
                        <?= escape_html($group['item_name']) ?> —
                        <?= escape_html($group['name']) ?>
                        <span class="font-semibold"><?= (int) $group['is_active'] === 1 ? '(Active)' : '(Inactive)' ?></span>
                    </h3>
                    <p class="text-body-sm text-muted-text">
                        Key: <?= escape_html($group['group_key']) ?> ·
                        <?= (int) $group['is_required'] === 1 ? 'Required' : 'Optional' ?> ·
                        Choose <?= (int) $group['min_selections'] ?>–<?= (int) $group['max_selections'] ?> ·
                        <?= (int) $group['active_option_count'] ?> active of <?= (int) $group['option_count'] ?> options
                        <?php if ((int) $group['item_is_active'] !== 1): ?> · Parent menu item is inactive<?php endif; ?>
                    </p>

                    <form method="post" action="dashboard.php?tab=modifiers">
                        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="section" value="modifiers">
                        <input type="hidden" name="action" value="update_group">
                        <input type="hidden" name="modifier_group_id" value="<?= (int) $group['modifier_group_id'] ?>">
                        <div class="grid gap-space-sm md:grid-cols-2">
                            <label class="block text-sm font-semibold">
                                Group name
                                <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="name" type="text" maxlength="100" required value="<?= escape_html($groupFormValues['name'] ?? $group['name']) ?>">
                            </label>
                            <label class="block text-sm font-semibold">
                                Group key
                                <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="group_key" type="text" maxlength="60" pattern="[a-z0-9][a-z0-9_-]{0,59}" required value="<?= escape_html($groupFormValues['group_key'] ?? $group['group_key']) ?>">
                            </label>
                            <label class="block text-sm font-semibold">
                                Selection rule
                                <select class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="is_required" required>
                                    <option value="1" <?= ($groupFormValues['is_required'] ?? (string) $group['is_required']) === '1' ? 'selected' : '' ?>>Required</option>
                                    <option value="0" <?= ($groupFormValues['is_required'] ?? (string) $group['is_required']) === '0' ? 'selected' : '' ?>>Optional</option>
                                </select>
                            </label>
                            <label class="block text-sm font-semibold">
                                Minimum selections
                                <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="min_selections" type="number" min="0" max="65535" step="1" required value="<?= escape_html($groupFormValues['min_selections'] ?? (string) $group['min_selections']) ?>">
                            </label>
                            <label class="block text-sm font-semibold">
                                Maximum selections
                                <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="max_selections" type="number" min="1" max="65535" step="1" required value="<?= escape_html($groupFormValues['max_selections'] ?? (string) $group['max_selections']) ?>">
                            </label>
                            <label class="block text-sm font-semibold">
                                Sort order
                                <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="sort_order" type="number" min="0" max="<?= PHP_INT_MAX ?>" step="1" required value="<?= escape_html($groupFormValues['sort_order'] ?? (string) $group['sort_order']) ?>">
                            </label>
                            <label class="block text-sm font-semibold">
                                Group status
                                <select class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="is_active" required>
                                    <option value="1" <?= ($groupFormValues['is_active'] ?? (string) $group['is_active']) === '1' ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= ($groupFormValues['is_active'] ?? (string) $group['is_active']) === '0' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </label>
                        </div>
                        <button class="<?= ADMIN_PRIMARY_BUTTON_CLASS ?>" type="submit">Save group</button>
                    </form>

                    <h3>Options</h3>
                    <?php if ($optionStatement !== null): ?>
                        <?php $optionStatement->execute(['group_id' => (int) $group['modifier_group_id']]); ?>
                        <?php $options = $optionStatement->fetchAll(PDO::FETCH_ASSOC); ?>
                        <?php if (empty($options)): ?>
                            <p class="text-body-sm text-muted-text">No options have been added.</p>
                        <?php else: ?>
                            <?php foreach ($options as $option): ?>
                                <?php
                                $optionFormValues = ($formValues['action'] ?? '') === 'update_option' &&
                                    ($formValues['modifier_group_id'] ?? '') === (string) $group['modifier_group_id'] &&
                                    ($formValues['modifier_option_id'] ?? '') === (string) $option['modifier_option_id']
                                    ? $formValues
                                    : [];
                                ?>
                                <form class="grid gap-space-sm md:grid-cols-2 lg:grid-cols-3" method="post" action="dashboard.php?tab=modifiers">
                                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="section" value="modifiers">
                                    <input type="hidden" name="action" value="update_option">
                                    <input type="hidden" name="modifier_group_id" value="<?= (int) $group['modifier_group_id'] ?>">
                                    <input type="hidden" name="modifier_option_id" value="<?= (int) $option['modifier_option_id'] ?>">
                                    <label class="block text-sm font-semibold">
                                        Option name
                                        <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="name" type="text" maxlength="100" required value="<?= escape_html($optionFormValues['name'] ?? $option['name']) ?>">
                                    </label>
                                    <label class="block text-sm font-semibold">
                                        Option key
                                        <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="option_key" type="text" maxlength="60" pattern="[a-z0-9][a-z0-9_-]{0,59}" required value="<?= escape_html($optionFormValues['option_key'] ?? $option['option_key']) ?>">
                                    </label>
                                    <label class="block text-sm font-semibold">
                                        Fixed price adjustment
                                        <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="price_delta" type="number" min="-99999999.99" max="99999999.99" step="0.01" required value="<?= escape_html($optionFormValues['price_delta'] ?? $option['price_delta']) ?>">
                                    </label>
                                    <label class="block text-sm font-semibold">
                                        Sort order
                                        <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="sort_order" type="number" min="0" max="<?= PHP_INT_MAX ?>" step="1" required value="<?= escape_html($optionFormValues['sort_order'] ?? (string) $option['sort_order']) ?>">
                                    </label>
                                    <label class="block text-sm font-semibold">
                                        Option status
                                        <select class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="is_active" required>
                                            <option value="1" <?= ($optionFormValues['is_active'] ?? (string) $option['is_active']) === '1' ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= ($optionFormValues['is_active'] ?? (string) $option['is_active']) === '0' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </label>
                                    <button class="rounded-lg bg-primary-container px-space-md py-space-sm font-label-button text-warm-white transition-colors hover:bg-primary" type="submit">Save Option</button>
                                    <?php if ((int) $option['historical_use_count'] > 0): ?>
                                        <p class="text-body-sm text-muted-text">
                                            Used in <?= (int) $option['historical_use_count'] ?> historical order line(s).
                                            Past order snapshots are unchanged; this option cannot be deleted.
                                        </p>
                                    <?php endif; ?>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>

                    <h3>Add an Option</h3>
                    <form class="grid gap-space-sm md:grid-cols-2 lg:grid-cols-3" method="post" action="dashboard.php?tab=modifiers">
                        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>"><input type="hidden" name="section" value="modifiers">
                        <input type="hidden" name="action" value="add_option">
                        <input type="hidden" name="modifier_group_id" value="<?= (int) $group['modifier_group_id'] ?>">
                        <label class="block text-sm font-semibold">
                            Option name
                            <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="name" type="text" maxlength="100" required value="<?= escape_html($addOptionValues['name'] ?? '') ?>">
                        </label>
                        <label class="block text-sm font-semibold">
                            Option key
                            <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="option_key" type="text" maxlength="60" pattern="[a-z0-9][a-z0-9_-]{0,59}" required value="<?= escape_html($addOptionValues['option_key'] ?? '') ?>">
                        </label>
                        <label class="block text-sm font-semibold">
                            Fixed price adjustment
                            <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="price_delta" type="number" min="-99999999.99" max="99999999.99" step="0.01" required value="<?= escape_html($addOptionValues['price_delta'] ?? '') ?>">
                        </label>
                        <label class="block text-sm font-semibold">
                            Sort order
                            <input class="mt-1 block w-full rounded-lg border border-border-neutral bg-warm-white px-space-sm py-space-xs" name="sort_order" type="number" min="0" max="<?= PHP_INT_MAX ?>" step="1" value="<?= escape_html($addOptionValues['sort_order'] ?? '0') ?>" required>
                        </label>
                        <span class="text-body-sm text-muted-text">New options are active.</span>
                        <button class="rounded-lg bg-primary-container px-space-md py-space-sm font-label-button text-warm-white transition-colors hover:bg-primary" type="submit">Add Option</button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <p><a href="dashboard.php?tab=menu">Back to menu management</a> · <a href="dashboard.php?tab=categories">Manage categories</a></p>
