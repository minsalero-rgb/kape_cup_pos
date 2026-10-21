<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/customer_session.php';
initialize_customer_session();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/order_submission.php';
require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$csrfToken = csrf_token();

$error = '';

// Handle cart actions submitted by the customer.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';
    if (!csrf_token_is_valid($token)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';


if ($action === 'add') {
    $itemId = filter_var(
        $_POST['item_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $rawNotes = $_POST['notes'] ?? '';
    $notes = is_string($rawNotes) ? trim($rawNotes) : '';
    $rawModifierOptionIds = $_POST['modifier_option_ids'] ?? [];
    $addQuantity = filter_var(
        $_POST['quantity'] ?? 1,
        FILTER_VALIDATE_INT
    );
    $modifierOptionIds = [];

    if (is_array($rawModifierOptionIds) && count($rawModifierOptionIds) <= 50) {
        foreach ($rawModifierOptionIds as $rawModifierOptionId) {
            if (!is_int($rawModifierOptionId) && !is_string($rawModifierOptionId)) {
                $modifierOptionIds = [];
                $error = 'Invalid modifier selection.';
                break;
            }
            $modifierOptionId = filter_var(
                $rawModifierOptionId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($modifierOptionId === false || in_array($modifierOptionId, $modifierOptionIds, true)) {
                $modifierOptionIds = [];
                $error = 'Invalid modifier selection.';
                break;
            }
            $modifierOptionIds[] = (int) $modifierOptionId;
        }
    } elseif ($rawModifierOptionIds !== []) {
        $error = 'Invalid modifier selection.';
    }
    sort($modifierOptionIds, SORT_NUMERIC);

    if (
        !$itemId || $itemId < 1 ||
        !is_string($rawNotes) ||
        strlen($notes) > 500 ||
        $addQuantity === false ||
        $addQuantity < 1 ||
        $addQuantity > 20 ||
        $error !== ''
    ) {
        if ($error === '') {
            $error = 'Invalid product selection or quantity.';
        }
    } else {
        $stmt = $pdo->prepare(
            'SELECT item_id
             FROM menu_items
             WHERE item_id = ?
               AND is_active = 1
               AND is_sold_out = 0
               AND stock_quantity > 0'
        );
        $stmt->execute([$itemId]);

        if (!$stmt->fetch()) {
            $error = 'This product is unavailable.';
        } else {
            $key = hash('sha256', serialize([$itemId, $modifierOptionIds, $notes]));

            $currentQty = (int) (
                $_SESSION['cart'][$key]['quantity'] ?? 0
            );

            if ($currentQty + $addQuantity > 99) {
                $error = 'Maximum quantity is 99 per selection.';
            } else {
                $_SESSION['cart'][$key] = [
                    'item_id' => $itemId,
                    'modifier_option_ids' => $modifierOptionIds,
                    'quantity' => $currentQty + $addQuantity,
                    'notes' => $notes,
                ];
            }
        }
    }

        } elseif ($action === 'update') {
            $key = $_POST['key'] ?? '';
            $quantity = filter_var(
                $_POST['quantity'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (
                !is_string($key) ||
                !isset($_SESSION['cart'][$key]) ||
                $quantity === false ||
                $quantity < 0 ||
                $quantity > 99
            ) {
                $error = 'Invalid cart quantity.';
            } elseif ($quantity === 0) {
                unset($_SESSION['cart'][$key]);
            } else {
                $_SESSION['cart'][$key]['quantity'] = $quantity;
            }
        } elseif ($action === 'remove') {
            $key = $_POST['key'] ?? '';

            if (is_string($key) && isset($_SESSION['cart'][$key])) {
                unset($_SESSION['cart'][$key]);
            }
        } else {
            $error = 'Invalid cart action.';
        }
    }

    if ($error === '') {
        header('Location: cart.php');
        exit;
    }
}

// Load actual product names and prices from the database.
$cartItems = [];
$cartCount = 0;
$cartTotalCents = 0;
$cartIssues = [];
$hasUnavailableItems = false;

$validCart = [];

foreach ($_SESSION['cart'] as $key => $entry) {
    if (
        is_array($entry) &&
        isset($entry['item_id'], $entry['quantity']) &&
        (is_int($entry['item_id']) || is_string($entry['item_id'])) &&
        (is_int($entry['quantity']) || is_string($entry['quantity'])) &&
        filter_var((string) $entry['item_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false &&
        filter_var((string) $entry['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]) !== false
    ) {
        unset($entry['size'], $entry['sweetness']);
        $validCart[$key] = $entry;
    } else {
        $cartIssues[] = 'An invalid cart entry was removed. Add that item again before checkout.';
    }
}

$_SESSION['cart'] = $validCart;

if (!empty($_SESSION['cart'])) {
    $ids = array_values(array_unique(array_map(
        static function ($entry): int {
            return (int) $entry['item_id'];
        },
        $_SESSION['cart']
    )));

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT item_id, name, is_active, is_sold_out, stock_quantity
         FROM menu_items
         WHERE item_id IN ($placeholders)"
    );
    $stmt->execute($ids);

    $products = [];

    foreach ($stmt->fetchAll() as $product) {
        $products[(int) $product['item_id']] = $product;
    }

    foreach ($_SESSION['cart'] as $key => $entry) {
        $id = (int) $entry['item_id'];
        $product = isset($products[$id])
            ? $products[$id]
            : ['item_id' => $id, 'name' => 'Unavailable item', 'is_active' => 0, 'is_sold_out' => 1, 'stock_quantity' => 0];
        $quantity = (int) $entry['quantity'];
        $preparedLine = null;
        $lineError = null;
        if (
            (int) $product['is_active'] === 1
            && (int) $product['is_sold_out'] === 0
            && (int) $product['stock_quantity'] > 0
        ) {
            try {
                $prepared = prepare_order_cart($pdo, [$entry]);
                $preparedLine = $prepared['items'][0];
            } catch (OrderSubmissionException $exception) {
                $lineError = $exception->getMessage();
            }
        } else {
            $lineError = 'This item is currently unavailable.';
        }

        $lineTotalCents = $preparedLine === null
            ? null
            : order_money_to_cents($preparedLine['line_total_snapshot']);
        if (
            $lineTotalCents === null ||
            $cartTotalCents > PHP_INT_MAX - $lineTotalCents
        ) {
            $lineError = $lineError !== null
                ? $lineError
                : 'The cart total is outside the supported range.';
            $hasUnavailableItems = true;
        } else {
            $cartTotalCents += $lineTotalCents;
        }

        $cartItems[] = [
            'key' => (string) $key,
            'product' => $product,
            'quantity' => $quantity,
            'unit_price' => $preparedLine === null ? null : $preparedLine['unit_price_snapshot'],
            'subtotal' => $preparedLine === null ? null : $preparedLine['line_total_snapshot'],
            'base_price' => $preparedLine === null ? null : $preparedLine['base_price_snapshot'],
            'modifiers' => $preparedLine === null ? [] : $preparedLine['modifiers'],
            'notes' => isset($entry['notes']) && is_string($entry['notes']) ? $entry['notes'] : '',
            'available' => (int) $product['is_active'] === 1
                && (int) $product['is_sold_out'] === 0
                && (int) $product['stock_quantity'] > 0
                && $lineError === null,
            'error' => $lineError,
        ];

        $cartCount += $quantity;
    }
}

$hasUnavailableItems = $hasUnavailableItems || $cartIssues !== [];

foreach ($cartItems as $item) {
    if (!$item['available']) {
        $hasUnavailableItems = true;
        break;
    }
}
$cartTotal = $hasUnavailableItems ? null : order_money_from_cents($cartTotalCents);
$cartTotalDisplay = $cartTotal === null
    ? '—'
    : '₱' . number_format((float) $cartTotal, 2);
$csrfToken = csrf_token();
?>
<!DOCTYPE html>

<html lang="en"><head><meta charset="utf-8"/><meta content="width=device-width, initial-scale=1.0" name="viewport"/><meta content="web_standard" name="shell-type"/>
<title>Your Cart | M&amp;E Kape Cup</title><link rel="stylesheet" href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>"></head><body class="customer-site"><?php
$customerNavActive = '';
require __DIR__ . '/includes/customer_header.php';
?><main class="w-full pt-20 bg-background min-h-screen"><div class="flex flex-col w-full relative">
<!-- Backdrop Menu Floor (Simulated Active Menu View Behind Open Drawer) -->
<div class="w-full max-w-[1280px] mx-auto px-margin md:px-margin-desktop py-space-lg opacity-40 pointer-events-none select-none blur-[1px]">
<div class="flex flex-col md:flex-row md:items-end justify-between gap-space-md pb-space-lg">
<div class="flex flex-col gap-space-xs">
<span class="font-label-sm text-label-sm text-caramel-gold uppercase tracking-wider">Artisanal Selection</span>
<h1 class="font-headline-lg text-headline-lg text-espresso-dark">Our Seasonal Roasts &amp; Confections</h1>
</div>
<div class="flex items-center gap-space-sm">
<span class="px-space-md py-space-xs rounded-full bg-secondary-container text-on-secondary-container font-label-md text-label-md">Espresso Bar</span>
<span class="px-space-md py-space-xs rounded-full bg-surface-container-high text-on-surface-variant font-label-md text-label-md">Pour Overs</span>
<span class="px-space-md py-space-xs rounded-full bg-surface-container-high text-on-surface-variant font-label-md text-label-md">Fresh Pastries</span>
</div>
</div>
<!-- Background Menu Grid Preview -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-gutter-desktop mt-space-md">
<div class="bg-surface-container-lowest rounded-xl p-space-md shadow-sm flex flex-col gap-space-sm">
<div class="w-full h-44 rounded-lg bg-surface-container-high overflow-hidden">
<img class="w-full h-full object-cover" data-alt="Steaming Spanish Latte in an artisanal ceramic tumbler, textured crema with rich cinnamon dusting on a dark rustic wooden cafe table with scattered roasted beans, golden morning sunlight." src="assets/img/latte.png"/>
</div>
<div class="flex justify-between items-start">
<div>
<h4 class="font-title-sm text-title-sm text-espresso-dark">Barista's Spanish Latte</h4>
<p class="font-body-sm text-body-sm text-text-muted">Signature condensed milk &amp; double shot</p>
</div>
<span class="font-label-lg text-label-lg text-espresso-dark">₱185.00</span>
</div>
</div>
<div class="bg-surface-container-lowest rounded-xl p-space-md shadow-sm flex flex-col gap-space-sm">
<div class="w-full h-44 rounded-lg bg-surface-container-high overflow-hidden">
<img class="w-full h-full object-cover" data-alt="Freshly baked flaky golden pain au chocolat beside a petite double espresso cup, vintage bistro napkin, ambient warm diner illumination." src="assets/img/snacks.png"/>
</div>
<div class="flex justify-between items-start">
<div>
<h4 class="font-title-sm text-title-sm text-espresso-dark">Ube Glazed Kouign-Amann</h4>
<p class="font-body-sm text-body-sm text-text-muted">Caramelized layers with native taro reduction</p>
</div>
<span class="font-label-lg text-label-lg text-espresso-dark">₱160.00</span>
</div>
</div>
<div class="bg-surface-container-lowest rounded-xl p-space-md shadow-sm flex flex-col gap-space-sm">
<div class="w-full h-44 rounded-lg bg-surface-container-high overflow-hidden">
<img class="w-full h-full object-cover" data-alt="Ceremonial grade iced matcha latte with visible emerald green and cream milk swirl in clear tall fluted glass on marble counter." src="assets/img/matcha.png"/>
</div>
<div class="flex justify-between items-start">
<div>
<h4 class="font-title-sm text-title-sm text-espresso-dark">Kyoto Cloud Cold Brew</h4>
<p class="font-body-sm text-body-sm text-text-muted">16-hour slow drip over sea-salt cold foam</p>
</div>
<span class="font-label-lg text-label-lg text-espresso-dark">₱195.00</span>
</div>
</div>
</div>
</div>
<!-- Slide-out Cart Drawer Overlay -->
<div class="fixed inset-0 z-50 bg-espresso-dark/40 backdrop-blur-[2px] flex justify-end" id="cart-drawer-scrim">
<!-- Active Slide Panel Container -->
<aside class="w-full max-w-[520px] h-full bg-surface-container-lowest shadow-2xl flex flex-col justify-between overflow-hidden relative">
<!-- Drawer Header Bar -->
<div class="p-space-lg bg-surface-container-lowest flex items-center justify-between gap-space-md shadow-sm z-10">
<div class="flex flex-col">
<div class="flex items-center gap-space-xs">
<span class="material-symbols-outlined icon-filled text-title-md text-espresso-dark">shopping_bag</span>
<h2 class="font-headline-sm text-headline-sm text-espresso-dark tracking-tight" id="cart-drawer-heading">
  Your Cart (<?= $cartCount ?> Items)
</h2>
</div>
<span class="font-label-sm text-label-sm text-text-muted mt-0.5">Your selected items</span>
</div>
<div class="flex items-center gap-space-xs">
<!-- Toggle button between Populated & Empty States for prototype preview -->
<a aria-label="Close cart" class="w-9 h-9 rounded-full bg-surface-container flex items-center justify-center text-espresso-dark hover:bg-surface-container-high transition-colors" data-path="menu" href="menu.php">
<span class="material-symbols-outlined text-title-sm">close</span>
</a>
</div>
</div>
<!-- Populated Cart Content State -->

<!-- Populated Cart Content State -->
<?php if ($cartIssues !== []): ?>
  <div class="mb-4 rounded-lg bg-error-container p-space-md text-on-error-container" role="alert">
    <?php foreach (array_unique($cartIssues) as $issue): ?>
      <p><?= escape_html($issue) ?></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<div
  class="flex-1 overflow-y-auto px-space-lg py-space-md flex flex-col gap-space-md <?php echo empty($cartItems) ? 'hidden' : ''; ?>"
  id="cart-populated-state"
>
  <?php if ($error !== ''): ?>
    <div class="p-space-sm rounded-lg bg-error-container text-on-error-container">
      <?= escape_html($error) ?>
    </div>
  <?php endif; ?>

  <!-- Status Notification Pill -->
  <div class="p-space-sm rounded-lg bg-surface-container-low flex items-center justify-between gap-space-xs">
    <div class="flex items-center gap-space-xs">
      <span class="material-symbols-outlined text-title-sm text-matcha-green">schedule</span>
      <span class="font-label-sm text-label-sm text-espresso-dark">
        Standard counter pickup
      </span>
    </div>
    <span class="font-label-sm text-label-sm text-text-muted">
      M&amp;E Kape Cup
    </span>
  </div>

  <?php foreach ($cartItems as $item): ?>
    <article class="bg-surface-container-lowest rounded-xl p-space-md shadow-sm hover:shadow-md transition-shadow flex flex-col gap-space-sm relative">

      <div class="flex gap-space-md items-start">
        <div class="w-20 h-20 rounded-lg overflow-hidden flex-shrink-0 bg-surface-container">
          <div class="w-full h-full flex items-center justify-center">
            <span class="material-symbols-outlined text-3xl text-text-muted">coffee</span>
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-space-xs">
            <div class="min-w-0">
              <h3 class="font-title-sm text-title-sm text-espresso-dark">
                <?= escape_html($item['product']['name']) ?>
              </h3>
              <div class="font-body-sm text-body-sm text-text-muted mt-1">
                Base price: <?= $item['base_price'] === null ? '—' : '₱' . number_format((float) $item['base_price'], 2) ?>
              </div>
            </div>

            <div class="text-right">
              <span class="font-currency-display text-currency-display text-espresso-dark font-bold">
                <?= $item['subtotal'] === null ? '—' : '₱' . number_format((float) $item['subtotal'], 2) ?>
              </span>
            </div>
          </div>

          <div class="mt-space-xs p-space-xs rounded bg-surface-container-low flex items-center justify-between gap-space-xs">
            <span class="font-body-sm text-body-sm text-text-muted">
              <?= $item['unit_price'] === null ? 'Price unavailable' : '₱' . number_format((float) $item['unit_price'], 2) . ' each' ?>
            </span>
            <span class="font-label-sm text-label-sm text-matcha-green">
              <?= $item['modifiers'] === [] ? 'No selected options' : count($item['modifiers']) . ' selected option(s)' ?>
            </span>
          </div>

          <?php if ($item['modifiers'] !== []): ?>
            <ul class="mt-2 space-y-1 text-sm text-text-muted">
              <?php foreach ($item['modifiers'] as $modifier): ?>
                <li>
                  <?= escape_html($modifier['group_name_snapshot']) ?>:
                  <?= escape_html($modifier['option_name_snapshot']) ?>
                  (<?= (float) $modifier['price_delta_snapshot'] > 0 ? '+' : '' ?>₱<?= number_format((float) $modifier['price_delta_snapshot'], 2) ?>)
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($item['notes'] !== ''): ?>
            <p class="mt-2 text-sm text-text-muted">Note: <?= escape_html($item['notes']) ?></p>
          <?php endif; ?>

          <?php if (!$item['available']): ?>
            <p class="text-accessible-red font-body-sm mt-2">
              <?= escape_html($item['error'] ?? 'This item is currently unavailable.') ?>
            </p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Working Quantity Controls and Remove Button -->
      <div class="flex items-center justify-between pt-space-xs mt-space-xs">
        <form method="post" action="cart.php">
          <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">
          <input type="hidden" name="action" value="remove">
          <input type="hidden" name="key" value="<?= escape_html($item['key']) ?>">

          <button
            type="submit"
            class="font-label-sm text-label-sm text-accessible-red hover:underline transition-colors flex items-center gap-0.5"
          >
            <span class="material-symbols-outlined text-[15px]">delete_outline</span>
            <span>Remove</span>
          </button>
        </form>

        <div class="flex items-center rounded-full bg-surface-container p-0.5">
          <form method="post" action="cart.php">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="key" value="<?= escape_html($item['key']) ?>">
            <input type="hidden" name="quantity" value="<?= max(0, $item['quantity'] - 1) ?>">

            <button
              type="submit"
              aria-label="Decrease quantity"
              class="w-7 h-7 rounded-full bg-surface-container-lowest flex items-center justify-center text-espresso-dark hover:bg-primary hover:text-on-primary transition-colors shadow-sm"
            >
              <span class="material-symbols-outlined text-[14px]">remove</span>
            </button>
          </form>

          <span class="w-8 text-center font-title-sm text-title-sm text-espresso-dark font-bold tabular-nums">
            <?= $item['quantity'] ?>
          </span>

          <form method="post" action="cart.php">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="key" value="<?= escape_html($item['key']) ?>">
            <input type="hidden" name="quantity" value="<?= min(99, $item['quantity'] + 1) ?>">

            <button
              type="submit"
              aria-label="Increase quantity"
              class="w-7 h-7 rounded-full bg-surface-container-lowest flex items-center justify-center text-espresso-dark hover:bg-primary hover:text-on-primary transition-colors shadow-sm"
            >
              <span class="material-symbols-outlined text-[14px]">add</span>
            </button>
          </form>
        </div>
      </div>
    </article>
  <?php endforeach; ?>

</div>
<!-- Empty Cart State (Toggleable) -->
<div class="<?= empty($cartItems) ? '' : 'hidden' ?> flex-1 px-space-lg py-space-xl flex-col items-center justify-center text-center" id="cart-empty-state">
<!-- Friendly Rubber-Hose Style Mascot Badge / Illustration -->
<div class="relative w-36 h-36 mb-space-lg">
<div class="absolute inset-0 rounded-full bg-caramel-gold/20 animate-pulse"></div>
<div class="relative w-full h-full rounded-full bg-secondary-container flex flex-col items-center justify-center p-space-md shadow-inner">
<svg class="w-20 h-20 text-espresso-dark" fill="currentColor" viewbox="0 0 100 100">
<!-- Vintage Cup Mascot Outline -->
<path d="M22 36C22 28 32 24 50 24C68 24 78 28 78 36V62C78 74 65 82 50 82C35 82 22 74 22 62V36Z" fill="#3B1F14"></path>
<ellipse cx="50" cy="35" fill="#D9B77E" rx="27" ry="9"></ellipse>
<ellipse cx="50" cy="35" fill="#29221D" rx="22" ry="6"></ellipse>
<!-- Cup Handle -->
<path d="M78 40C86 40 92 46 92 54C92 62 86 68 78 68V61C82 61 85 58 85 54C85 50 82 47 78 47V40Z" fill="#3B1F14"></path>
<!-- Cartoon Eyes (Rubber-hose pie eyes) -->
<circle cx="42" cy="52" fill="#FFFCF6" r="5"></circle>
<circle cx="43" cy="52" fill="#29221D" r="2.5"></circle>
<circle cx="58" cy="52" fill="#FFFCF6" r="5"></circle>
<circle cx="59" cy="52" fill="#29221D" r="2.5"></circle>
<!-- Wavy Smile -->
<path d="M43 64C47 68 53 68 57 64" fill="none" stroke="#FFFCF6" stroke-linecap="round" stroke-width="2.5"></path>
<!-- Steam accents -->
<path d="M38 18C36 14 42 10 40 6" fill="none" stroke="#D9B77E" stroke-linecap="round" stroke-width="2"></path>
<path d="M50 20C48 15 54 11 52 7" fill="none" stroke="#D9B77E" stroke-linecap="round" stroke-width="2"></path>
<path d="M62 18C60 14 66 10 64 6" fill="none" stroke="#D9B77E" stroke-linecap="round" stroke-width="2"></path>
</svg>
</div>
<span class="absolute -bottom-2 -right-2 px-space-xs py-0.5 rounded bg-espresso-dark text-on-primary font-label-sm text-label-sm shadow-md">Fresh Pot!</span>
</div>
<h3 class="font-headline-sm text-headline-sm text-espresso-dark mb-space-xs">Your cart is currently empty</h3>
<p class="font-body-md text-body-md text-text-muted max-w-[280px] mb-space-lg">
          Looks like you haven't picked your favorite roast yet. Indulge in our slow-dripped brew or fresh morning pastry.
        </p>
<a class="w-full max-w-[260px] py-space-sm px-space-lg rounded-lg bg-primary text-on-primary font-label-lg text-label-lg text-center shadow-md hover:bg-espresso-dark transition-colors flex items-center justify-center gap-space-xs" data-path="menu" href="menu.php">
        <span>Browse the Menu</span>
<span class="material-symbols-outlined text-title-sm">arrow_forward</span>
</a>
</div>
<!-- Populated Cart Subtotal & Checkout Section -->
<div class="bg-surface-container-lowest p-space-lg shadow-[0_-4px_16px_rgba(41,34,29,0.06)] flex flex-col gap-space-md z-10 <?= empty($cartItems) ? 'hidden' : '' ?>" id="cart-footer-summary">
<!-- Breakdown: Subtotal & Total strictly without tax, delivery, or service fees -->
<div class="flex flex-col gap-space-xs">
<div class="flex justify-between items-center text-text-muted">
<span class="font-body-md text-body-md">Order Subtotal</span>
<span class="font-title-md text-title-md text-espresso-dark font-semibold" id="summary-subtotal"><?= escape_html($cartTotalDisplay) ?></span>
</div>
<div class="flex justify-between items-baseline pt-space-xs">
<div class="flex flex-col">
<span class="font-title-md text-title-md text-espresso-dark font-bold">Total</span>
<span class="font-label-sm text-label-sm text-text-muted">Database prices • Selected options included</span>
</div>
<div class="text-right">
<span class="font-currency-display text-currency-display text-espresso-dark font-bold" id="summary-total"><?= escape_html($cartTotalDisplay) ?></span>
</div>
</div>
</div>
<!-- Checkout Primary Action -->
<?php if (!$hasUnavailableItems && $cartItems !== []): ?>
  <a class="w-full py-space-md px-space-lg rounded-lg bg-primary text-on-primary font-label-lg text-label-lg font-bold text-center shadow-md hover:bg-espresso-dark active:scale-[0.99] transition-all flex items-center justify-center gap-space-sm group" href="checkout.php">
    <span>Review Order</span>
    <span class="material-symbols-outlined text-title-md group-hover:translate-x-1 transition-transform">arrow_forward</span>
  </a>
<?php else: ?>
  <p class="rounded-lg bg-amber-50 p-space-sm text-center text-sm text-amber-900">
    Remove unavailable or invalid items before checkout.
  </p>
<?php endif; ?>
<!-- Additional Safety / Note -->
<div class="flex items-center justify-center gap-space-xs text-text-muted">
<span class="material-symbols-outlined text-[16px] text-caramel-gold">lock</span>
<span class="font-label-sm text-label-sm">Standard counter pickup • Instant ticket confirmation</span>
</div>
</div>
</aside>
</div>
</div></main><footer class="w-full bg-surface-container-low mt-space-xl shadow-[0_-1px_6px_rgba(41,34,29,0.03)]"><div class="max-w-[1280px] mx-auto px-margin-desktop py-space-xl flex flex-col md:flex-row items-center justify-between gap-space-md"><div class="flex flex-col items-center md:items-start gap-space-xs"><div class="flex items-center gap-space-xs"><span class="font-headline-sm text-headline-sm text-espresso-dark">M&amp;E Kape Cup</span><span class="px-space-xs py-0.5 rounded bg-secondary-container text-on-secondary-container font-label-sm text-label-sm">Cafe &amp; Roastery</span></div><p class="font-body-sm text-body-sm text-text-muted">Warm roasts, timeless diner charm &amp; handcrafted coffee.</p></div><div class="flex flex-col md:flex-row items-center gap-space-lg"><div class="font-label-sm text-label-sm text-text-muted text-center md:text-right"><p class="mt-1">© 2024 M&amp;E Kape Cup. All rights reserved.</p></div></div></div></footer></body></html>