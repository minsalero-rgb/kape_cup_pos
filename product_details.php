<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/customer_session.php';
initialize_customer_session();

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/order_submission.php';
require_once __DIR__ . '/config/db.php';

$imageColumnStatement = $pdo->query(
    "SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'menu_items'
       AND COLUMN_NAME = 'image_filename'"
);
$menuHasImageColumn = (int) $imageColumnStatement->fetchColumn() > 0;
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    http_response_code(404);
    exit('Invalid product. Please return to the menu and select a product.');
}

$productStatement = $pdo->prepare(
    'SELECT item_id, name, description'
    . ($menuHasImageColumn ? ', image_filename' : '')
    . ', base_price, stock_quantity, is_sold_out
     FROM menu_items
     WHERE item_id = :item_id
       AND is_active = 1
     LIMIT 1'
);

$productStatement->execute(['item_id' => $productId]);
$product = $productStatement->fetch();

if (!$product) {
    http_response_code(404);
    exit('Product not found. Please return to the menu.');
}

$groupStatement = $pdo->prepare(
    'SELECT
        mg.modifier_group_id,
        mg.name AS group_name,
        mg.is_required,
        mg.min_selections,
        mg.max_selections,
        options.modifier_option_id,
        options.name AS option_name,
        options.price_delta
     FROM menu_modifier_groups AS mg
     LEFT JOIN menu_modifier_options AS options
        ON options.modifier_group_id = mg.modifier_group_id
       AND options.is_active = 1
     WHERE mg.item_id = :item_id
       AND mg.is_active = 1
     ORDER BY mg.sort_order ASC, mg.modifier_group_id ASC,
              options.sort_order ASC, options.modifier_option_id ASC'
);
$groupStatement->execute(['item_id' => $productId]);
$modifierGroups = [];
foreach ($groupStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $groupId = (int) $row['modifier_group_id'];
    if (!isset($modifierGroups[$groupId])) {
        $modifierGroups[$groupId] = [
            'name' => (string) $row['group_name'],
            'is_required' => (int) $row['is_required'] === 1,
            'min_selections' => (int) $row['min_selections'],
            'max_selections' => (int) $row['max_selections'],
            'options' => [],
        ];
    }
    if ($row['modifier_option_id'] !== null) {
        $modifierGroups[$groupId]['options'][] = [
            'id' => (int) $row['modifier_option_id'],
            'name' => (string) $row['option_name'],
            'price_delta' => (string) $row['price_delta'],
        ];
    }
}

$modifierConfigurationError = false;
foreach ($modifierGroups as $group) {
    $minimum = $group['is_required'] ? max(1, $group['min_selections']) : $group['min_selections'];
    if (
        $group['max_selections'] < $minimum ||
        $group['max_selections'] > 50 ||
        count($group['options']) < $minimum
    ) {
        $modifierConfigurationError = true;
        break;
    }
}
$modifierGroupRules = [];
foreach ($modifierGroups as $groupId => $group) {
    $modifierGroupRules[] = [
        'id' => (int) $groupId,
        'min' => $group['is_required'] ? max(1, $group['min_selections']) : $group['min_selections'],
        'max' => $group['max_selections'],
    ];
    foreach ($group['options'] as $option) {
        if (order_money_to_cents($option['price_delta']) === null) {
            $modifierConfigurationError = true;
        }
    }
}
$basePriceCents = order_money_to_cents((string) $product['base_price']);
if ($basePriceCents === null || $basePriceCents < 0) {
    $modifierConfigurationError = true;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = csrf_token();
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta content="width=device-width, initial-scale=1.0" name="viewport"><meta content="web_standard" name="shell-type"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Merriweather:wght@400;700&amp;display=swap" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/css/tokens.css"><link rel="stylesheet" href="assets/css/style.css?v=20261018-7"></head><body class="customer-site bg-background font-body-md text-body-md text-on-surface antialiased"><header class="fixed top-0 left-0 right-0 z-40 bg-surface/90 backdrop-blur-md shadow-[0_1px_8px_rgba(41,34,29,0.06)]"><div class="h-20 max-w-[1280px] mx-auto px-margin-desktop flex items-center justify-between gap-space-md"><div class="flex items-center gap-space-md"><a class="flex items-center gap-space-sm group" data-path="home" href="index.php"><img class="w-11 h-11 rounded-full object-cover" src="assets/img/me-kape-cup-logo.png" alt=""><div class="flex flex-col"><span class="font-headline-sm text-headline-sm text-espresso-dark group-hover:text-primary transition-colors tracking-tight">M&amp;E Kape Cup</span><span class="font-label-sm text-label-sm text-text-muted tracking-wider uppercase">Artisanal Brew &amp; Comforts</span></div></a></div><nav class="hidden md:flex items-center gap-space-sm" data-active-classes="bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm"><a class="px-space-md py-space-xs font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors" data-path="home" href="index.php">Home</a><a aria-current="page" class="px-space-md py-space-xs transition-colors bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm" data-path="menu" href="menu.php">Menu</a><a class="px-space-md py-space-xs font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors" data-path="order-status" href="status.php">Order Status</a></nav><div class="flex items-center gap-space-md"><a class="relative flex items-center justify-center w-11 h-11 rounded-full bg-surface-container-high text-espresso-dark hover:bg-caramel-gold/20 hover:text-primary transition-colors" data-path="cart-drawer" href="cart.php"><span class="material-symbols-outlined text-title-md">shopping_bag</span><span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-accessible-red text-on-error font-label-sm text-label-sm flex items-center justify-center font-bold"><?= (int) array_sum(array_map(static function ($e) { return is_array($e) && isset($e['quantity']) ? (int) $e['quantity'] : 0; }, isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [])) ?></span></a></div></div></header><main class="w-full pt-20 bg-background min-h-screen"><div class="flex flex-col w-full relative">

<!-- Modal Backdrop Overlay (Laptop & Desktop Ergonomic Workspace) -->
<div class="fixed inset-0 z-50 flex items-center justify-center p-space-sm md:p-space-lg bg-espresso-dark/60 backdrop-blur-sm transition-opacity duration-300" id="modal-container">
<!-- Center Stage Modal Dialog Container -->
<div class="w-full max-w-[820px] max-h-[942px] bg-surface-container-lowest rounded-xl shadow-2xl flex flex-col overflow-hidden transform transition-all duration-300">
<!-- Top Modal Header Bar with Retro Badge & Metadata -->
<div class="w-full bg-surface-container-low px-space-lg py-space-md flex items-center justify-between gap-space-md">
<div class="flex items-center gap-space-sm">
<div class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center text-caramel-gold shadow-sm">
<span class="material-symbols-outlined text-title-sm">local_cafe</span>
</div>
<div class="flex flex-col">
<span class="font-headline-sm text-headline-sm text-espresso-dark tracking-tight">M&amp;E Kape Cup • Options &amp; Modifiers</span>
<span class="font-label-sm text-label-sm text-caramel-gold">
    Product ID: #<?= (int) $product['item_id'] ?>
</span>
</div>
</div>
</div>
<!-- Scrollable Main Content Frame (Product Header + Modifiers) -->
<div class="w-full flex-1 overflow-y-auto px-space-lg py-space-lg flex flex-col gap-space-lg">
<!-- Product Profile Card: Layout inspired by the diner badge & artisan trio -->
<div class="bg-surface-container rounded-xl p-space-md md:p-space-lg flex flex-col sm:flex-row gap-space-lg items-center sm:items-start shadow-sm">
<div class="w-full sm:w-44 h-44 rounded-lg overflow-hidden bg-primary-container relative flex-shrink-0 shadow-inner">
<?php if ($menuHasImageColumn && is_string($product['image_filename']) && $product['image_filename'] !== ''): ?>
    <img class="w-full h-full object-cover" src="api/menu_image.php?id=<?= (int) $product['item_id'] ?>" alt="<?= escape_html($product['name']) ?>">
<?php else: ?>
    <img class="w-full h-full object-cover" src="assets/img/me-kape-cup-logo.png" alt="M&amp;E Kape Cup">
<?php endif; ?>
<div class="absolute bottom-2 left-2 px-space-xs py-0.5 rounded bg-espresso-dark/80 text-surface font-label-sm text-label-sm backdrop-blur-xs">
              M&amp;E Roastery
            </div>
</div>
<div class="flex flex-col flex-1 gap-space-xs w-full">
<div class="flex flex-wrap items-center justify-between gap-space-xs">
<div class="flex items-center gap-space-xs text-text-muted">
<span class="material-symbols-outlined text-[15px] text-caramel-gold">star</span>
</div>
</div>

<h2 class="font-headline-md text-headline-md text-espresso-dark" id="product-name">
    <?= escape_html($product['name']) ?>
</h2>

<p class="font-body-md text-body-md text-text-muted" id="product-description">
    <?= escape_html($product['description'] ?: 'A freshly prepared drink from M&E Kape Cup.') ?>
</p>
<div class="mt-space-sm pt-space-xs flex flex-wrap items-baseline gap-space-md">
<div class="flex items-baseline gap-1">
<span class="font-body-sm text-body-sm text-text-muted">Base Price:</span>

<span class="font-currency-display text-currency-display text-espresso-dark" id="base-price-display">
    ₱<?= number_format((float) $product['base_price'], 2) ?>
</span>
</div>
</div>
</div>
</div>
<!-- Global Prototype Notice Strip -->
<div class="flex items-center gap-space-sm px-space-md py-space-xs rounded-lg bg-surface-container-high text-on-surface-variant">
<span class="material-symbols-outlined text-[18px] text-warning-amber">database</span>
<span class="font-body-sm text-body-sm">
            Product details and base prices are loaded from the menu database. Available options may vary by product.
</span>
</div>

<div class="flex items-center justify-between rounded-xl bg-surface-container-low p-space-md">
    <span class="font-title-sm text-title-sm text-espresso-dark">Base price</span>
    <span class="font-title-sm text-title-sm text-espresso-dark">₱<?= number_format((float) $product['base_price'], 2) ?></span>
</div>

<?php foreach ($modifierGroups as $groupId => $group): ?>
    <?php
    $minimum = $group['is_required'] ? max(1, $group['min_selections']) : $group['min_selections'];
    $singleChoice = $group['max_selections'] === 1;
    $inputType = $singleChoice ? 'radio' : 'checkbox';
    $inputName = 'modifier-group-' . (int) $groupId;
    ?>
    <section class="flex flex-col gap-space-sm rounded-xl bg-surface-container-low p-space-lg">
        <div class="flex flex-col gap-space-xs sm:flex-row sm:items-center sm:justify-between">
            <h3 class="font-title-sm text-title-sm text-espresso-dark"><?= escape_html($group['name']) ?></h3>
            <span class="rounded bg-secondary-container px-space-xs py-0.5 font-label-sm text-on-secondary-container">
                <?= $minimum > 0 ? 'Required' : 'Optional' ?> · Choose <?= (int) $minimum ?> to <?= (int) $group['max_selections'] ?>
            </span>
        </div>
        <div class="grid grid-cols-1 gap-space-sm sm:grid-cols-2">
            <?php if ($singleChoice && $minimum === 0): ?>
                <label class="flex cursor-pointer items-center gap-space-sm rounded-lg bg-surface-container-lowest p-space-md">
                    <input class="modifier-option-input accent-primary" type="radio" name="<?= escape_html($inputName) ?>" value="" data-group-id="<?= (int) $groupId ?>" data-option-id="" data-price-cents="0" checked>
                    <span class="font-body-md text-espresso-dark">No selection</span>
                </label>
            <?php endif; ?>
            <?php foreach ($group['options'] as $option): ?>
                <?php $optionCents = order_money_to_cents($option['price_delta']); ?>
                <label class="flex cursor-pointer items-center justify-between gap-space-sm rounded-lg bg-surface-container-lowest p-space-md hover:bg-surface-container">
                    <span class="flex items-center gap-space-sm">
                        <input
                            class="modifier-option-input accent-primary"
                            type="<?= $inputType ?>"
                            name="<?= escape_html($inputName) ?>"
                            value="<?= (int) $option['id'] ?>"
                            data-group-id="<?= (int) $groupId ?>"
                            data-option-id="<?= (int) $option['id'] ?>"
                            data-price-cents="<?= $optionCents === null ? '0' : (int) $optionCents ?>"
                        >
                        <span class="font-body-md text-espresso-dark"><?= escape_html($option['name']) ?></span>
                    </span>
                    <span class="whitespace-nowrap font-label-sm text-text-muted">
                        <?= $optionCents !== null && $optionCents > 0 ? '+' : '' ?>₱<?= $optionCents === null ? '—' : number_format($optionCents / 100, 2) ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="hidden rounded bg-error-container px-space-sm py-1 text-sm text-on-error-container" data-group-feedback="<?= (int) $groupId ?>" role="alert"></p>
    </section>
<?php endforeach; ?>

<?php if ($modifierConfigurationError): ?>
    <p class="rounded-lg bg-error-container p-space-md text-on-error-container" role="alert">
        Product options are temporarily unavailable. Please choose another item.
    </p>
<?php endif; ?>

<!-- Special Instructions / Notes -->
<div class="flex flex-col gap-space-xs">
<label class="font-label-md text-label-md text-on-surface-variant flex items-center gap-1" for="special-instructions">
<span class="material-symbols-outlined text-[16px]">notes</span>
            Barista Notes &amp; Dietary Requests (Optional)
          </label>
<input class="w-full bg-surface-container-lowest px-space-md py-space-sm rounded-lg font-body-md text-body-md text-on-surface outline-none focus:bg-surface-bright shadow-sm" id="special-instructions" maxlength="500" placeholder="Add an optional request..." type="text">
</div>
</div>
<!-- Anchored Modal Footer / Execution Dashboard -->
<div class="w-full bg-surface-container px-space-lg py-space-md shadow-lg flex flex-col sm:flex-row items-center justify-between gap-space-md">
<!-- Left: Quantity Stepper & Pricing Calculation Preview -->
<div class="flex items-center gap-space-lg w-full sm:w-auto justify-between sm:justify-start">
<!-- Tactile Quantity Stepper module -->
<div class="flex items-center bg-surface-container-lowest rounded-full p-1 shadow-sm">
<button aria-label="Decrease quantity" class="w-9 h-9 rounded-full bg-surface-container hover:bg-surface-container-high flex items-center justify-center text-espresso-dark transition-colors active:scale-95" id="qty-decrease" type="button">
<span class="material-symbols-outlined text-title-sm">remove</span>
</button>
<span class="w-10 text-center font-title-sm text-title-sm text-espresso-dark select-none" id="qty-value">1</span>
<button aria-label="Increase quantity" class="w-9 h-9 rounded-full bg-surface-container hover:bg-surface-container-high flex items-center justify-center text-espresso-dark transition-colors active:scale-95" id="qty-increase" type="button">
<span class="material-symbols-outlined text-title-sm">add</span>
</button>
</div>
<!-- Live Line-Total Preview clearly labeled -->
<div class="flex flex-col text-right sm:text-left">
<span class="font-label-sm text-label-sm text-text-muted">Order Subtotal:</span>
<div class="flex items-baseline gap-1">
<span class="font-headline-md text-headline-md text-espresso-dark" id="line-total-display">₱120.00</span>
<span class="font-label-sm text-label-sm text-text-muted">Total</span>
</div>
</div>
</div>
<!-- Right: Action Triggers (Cancel secondary and Add to Cart primary Espresso Brown) -->
<div class="flex items-center gap-space-sm w-full sm:w-auto">
<!-- Secondary Action: Cancel -->
<button class="flex-1 sm:flex-none px-space-lg py-space-sm rounded-lg bg-surface-container-lowest hover:bg-surface-container-high font-label-lg text-label-lg text-espresso-dark transition-colors shadow-sm" id="modal-cancel-btn" type="button">
            Cancel
          </button>
<!-- Primary Action: Espresso Brown "Add to Cart" -->
<button class="flex-1 sm:flex-none px-space-xl py-space-sm rounded-lg bg-primary-container hover:bg-espresso-dark text-on-primary font-label-lg text-label-lg transition-all flex items-center justify-center gap-space-xs shadow-md active:scale-[0.98]" id="modal-add-cart-btn" type="button" <?= ((int) $product['is_sold_out'] === 1 || (int) $product['stock_quantity'] < 1 || $modifierConfigurationError) ? 'disabled' : '' ?>>
<span class="material-symbols-outlined text-title-sm">shopping_bag</span>
<span>Add to Cart • <span id="cart-btn-price">₱120.00</span></span>
</button>
</div>
</div>
</div>
</div>

<!-- Actual form that sends the product to cart.php -->
<form id="add-to-cart-form" action="cart.php" method="post" hidden>
    <input
        type="hidden"
        name="csrf_token"
        value="<?= escape_html($_SESSION['csrf_token']) ?>"
    >

    <input type="hidden" name="action" value="add">

    <input
        type="hidden"
        name="item_id"
        value="<?= (int) $product['item_id'] ?>"
    >

    <input type="hidden" name="quantity" id="cart-quantity" value="1">
</form>
<!-- Micro-interaction & State Management Logic -->
<script>
    (function () {
      const BASE_PRICE_CENTS = <?= json_encode($basePriceCents === null ? 0 : $basePriceCents) ?>;
      const GROUP_RULES = <?= json_encode($modifierGroupRules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      let quantity = 1;

      const modifierInputs = document.querySelectorAll('.modifier-option-input');
      const qtyValueEl = document.getElementById('qty-value');
      const qtyDecreaseBtn = document.getElementById('qty-decrease');
      const qtyIncreaseBtn = document.getElementById('qty-increase');
      const lineTotalEl = document.getElementById('line-total-display');
      const cartBtnPriceEl = document.getElementById('cart-btn-price');
      const cancelBtn = document.getElementById('modal-cancel-btn');
      const addCartBtn = document.getElementById('modal-add-cart-btn');
      const cartForm = document.getElementById('add-to-cart-form');

      function calculateTotal() {
        let unitCents = BASE_PRICE_CENTS;
        modifierInputs.forEach(input => {
          if (input.checked && input.dataset.optionId) {
            unitCents += Number(input.dataset.priceCents || 0);
          }
        });

        const formattedTotal = '₱' + (unitCents * quantity / 100).toLocaleString('en-PH', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
        lineTotalEl.textContent = formattedTotal;
        cartBtnPriceEl.textContent = formattedTotal;
      }

      qtyIncreaseBtn.addEventListener('click', () => {
        if (quantity < 20) {
          quantity++;
          qtyValueEl.textContent = quantity;
          calculateTotal();
        }
      });

      qtyDecreaseBtn.addEventListener('click', () => {
        if (quantity > 1) {
          quantity--;
          qtyValueEl.textContent = quantity;
          calculateTotal();
        }
      });

      modifierInputs.forEach(input => {
        input.addEventListener('change', () => {
          const groupId = input.dataset.groupId;
          const rule = GROUP_RULES.find(group => String(group.id) === groupId);
          const feedback = document.querySelector('[data-group-feedback="' + groupId + '"]');
          const selected = Array.from(modifierInputs).filter(option =>
            option.dataset.groupId === groupId &&
            option.checked &&
            option.dataset.optionId
          );

          if (rule && selected.length > rule.max && input.type === 'checkbox') {
            input.checked = false;
            if (feedback) {
              feedback.textContent = 'Choose no more than ' + rule.max + ' option(s) for this group.';
              feedback.classList.remove('hidden');
              setTimeout(() => feedback.classList.add('hidden'), 3000);
            }
          }
          calculateTotal();
        });
      });

      addCartBtn.addEventListener('click', () => {
        let valid = true;
        GROUP_RULES.forEach(rule => {
          const selected = Array.from(modifierInputs).filter(option =>
            String(option.dataset.groupId) === String(rule.id) &&
            option.checked &&
            option.dataset.optionId
          );
          const feedback = document.querySelector('[data-group-feedback="' + rule.id + '"]');
          if (selected.length < rule.min || selected.length > rule.max) {
            valid = false;
            if (feedback) {
              feedback.textContent = 'Choose between ' + rule.min + ' and ' + rule.max + ' option(s) for this group.';
              feedback.classList.remove('hidden');
              feedback.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          } else if (feedback) {
            feedback.classList.add('hidden');
          }
        });
        if (!valid) {
          return;
        }

        modifierInputs.forEach(input => {
          if (input.checked && input.dataset.optionId) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'modifier_option_ids[]';
            hiddenInput.value = input.dataset.optionId;
            cartForm.appendChild(hiddenInput);
          }
        });
        document.getElementById('cart-quantity').value = String(quantity);
        const notesInput = document.getElementById('special-instructions');
        const notesField = document.createElement('input');
        notesField.type = 'hidden';
        notesField.name = 'notes';
        notesField.value = notesInput.value.trim();
        cartForm.appendChild(notesField);
        cartForm.submit();
      });

      cancelBtn.addEventListener('click', () => {
        window.location.href = 'menu.php';
      });

      calculateTotal();
    })();
  </script>
</div></main><footer class="w-full bg-surface-container-low mt-space-xl shadow-[0_-1px_6px_rgba(41,34,29,0.03)]"><div class="max-w-[1280px] mx-auto px-margin-desktop py-space-xl flex flex-col md:flex-row items-center justify-between gap-space-md"><div class="flex flex-col items-center md:items-start gap-space-xs"><div class="flex items-center gap-space-xs"><span class="font-headline-sm text-headline-sm text-espresso-dark">M&amp;E Kape Cup</span><span class="px-space-xs py-0.5 rounded bg-secondary-container text-on-secondary-container font-label-sm text-label-sm">Cafe &amp; Roastery</span></div><p class="font-body-sm text-body-sm text-text-muted">Warm roasts, timeless diner charm &amp; handcrafted coffee.</p></div><div class="flex flex-col md:flex-row items-center gap-space-lg"><div class="font-label-sm text-label-sm text-text-muted text-center md:text-right"><p class="mt-1">© 2024 M&amp;E Kape Cup. All rights reserved.</p></div></div></div></footer></body></html>