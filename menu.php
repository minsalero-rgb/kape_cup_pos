<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/customer_session.php';
initialize_customer_session();

require_once __DIR__ . '/config/db.php';

$showAdminMenuLink = false;
if (isset($_COOKIE['mekape_staff_session']) && is_string($_COOKIE['mekape_staff_session'])) {
    $customerSessionName = session_name();
    $customerSessionId = session_id();
    $customerSessionCookieParams = session_get_cookie_params();

    session_write_close();
    require_once __DIR__ . '/includes/auth.php';
    $showAdminMenuLink = staff_is_authenticated()
        && isset($_SESSION['staff_role'])
        && $_SESSION['staff_role'] === 'admin';
    session_write_close();

    session_name($customerSessionName);
    session_set_cookie_params($customerSessionCookieParams);
    session_id($customerSessionId);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load categories from the database.
try {
    $categoryStatement = $pdo->query(
        'SELECT category_id, name, sort_order
         FROM categories
         ORDER BY sort_order ASC, name ASC, category_id ASC'
    );

    $categories = $categoryStatement->fetchAll();

} catch (PDOException $exception) {
    error_log('[customer-menu] Category loading failed.');
    http_response_code(500);
    exit('The menu is temporarily unavailable. Please try again later.');
}

// Load active products and their categories.
try {
    $imageColumnStatement = $pdo->query(
        "SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'menu_items'
           AND COLUMN_NAME = 'image_filename'"
    );
    $menuHasImageColumn = (int) $imageColumnStatement->fetchColumn() > 0;
    $productStatement = $pdo->query(
        'SELECT
            mi.item_id,
            mi.category_id,
            mi.name,
            mi.description,
            ' . ($menuHasImageColumn ? 'mi.image_filename,' : '') . '
            mi.base_price,
            mi.stock_quantity,
            mi.is_sold_out,
            c.name AS category_name
         FROM menu_items AS mi
         INNER JOIN categories AS c
            ON c.category_id = mi.category_id
         WHERE mi.is_active = 1
         ORDER BY c.sort_order ASC, mi.name ASC, mi.item_id ASC'
    );

    $menuItems = $productStatement->fetchAll();

} catch (PDOException $exception) {
    error_log('[customer-menu] Product loading failed.');
    http_response_code(500);
    exit('The menu is temporarily unavailable. Please try again later.');
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta content="width=device-width, initial-scale=1.0" name="viewport"><meta content="web_standard" name="shell-type">
<title>Menu | M&amp;E Kape Cup</title><link rel="stylesheet" href="assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>"></head><body class="customer-site bg-background font-body-md text-body-md text-on-surface antialiased"><header class="fixed top-0 left-0 right-0 z-40 bg-surface/90 backdrop-blur-md shadow-[0_1px_8px_rgba(41,34,29,0.06)]"><div class="h-20 max-w-[1280px] mx-auto px-margin-desktop flex items-center justify-between gap-space-md"><div class="flex items-center gap-space-md"><a class="flex items-center gap-space-sm group" data-path="home" href="index.php"><img class="w-11 h-11 rounded-full object-cover" src="assets/img/me-kape-cup-logo.png" alt=""><div class="flex flex-col"><span class="font-headline-sm text-headline-sm text-espresso-dark group-hover:text-primary transition-colors tracking-tight">M&amp;E Kape Cup</span><span class="font-label-sm text-label-sm text-text-muted tracking-wider uppercase">Artisanal Brew &amp; Comforts</span></div></a></div><nav class="hidden md:flex items-center gap-space-sm" data-active-classes="bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm"><a class="px-space-md py-space-xs font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors" data-path="home" href="index.php">Home</a><a aria-current="page" class="px-space-md py-space-xs transition-colors bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm" data-path="menu" href="menu.php">Menu</a><a class="px-space-md py-space-xs font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors" data-path="order-status" href="status.php">Order Status</a></nav><div class="flex items-center gap-space-md"><a class="relative flex items-center justify-center w-11 h-11 rounded-full bg-surface-container-high text-espresso-dark hover:bg-caramel-gold/20 hover:text-primary transition-colors" data-path="cart-drawer" href="cart.php"><span class="material-symbols-outlined text-title-md">shopping_bag</span><span class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-accessible-red text-on-error font-label-sm text-label-sm flex items-center justify-center font-bold"><?= (int) array_sum(array_map(static function ($e) { return is_array($e) && isset($e['quantity']) ? (int) $e['quantity'] : 0; }, isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [])) ?></span></a></div></div></header><main class="w-full pt-20 bg-background min-h-screen"><div class="flex flex-col w-full">
<div class="w-full max-w-[1280px] mx-auto px-margin md:px-margin-desktop py-space-lg flex flex-col gap-space-lg">
<?php if ($showAdminMenuLink): ?>
<div class="flex justify-end">
<a class="inline-flex items-center rounded-lg bg-primary-container px-space-md py-space-sm font-label-lg text-label-lg text-on-primary shadow-sm transition-colors hover:bg-primary" href="/kape_cup_pos/admin/dashboard.php?tab=menu">Manage Menu</a>
</div>
<?php endif; ?>

<!-- Database-driven Categories Navigation Bar -->
<?php
$categoryIcons = [
    'Coffee Series' => 'local_cafe',
    'Matcha Series' => 'spa',
    'Fruit Milk' => 'nutrition',
    'Soda' => 'bubble_chart',
    'Fruit Shake' => 'blender',
    'Latte Series with Whipped Cream' => 'icecream',
    'Main Courses' => 'dinner_dining',
    'Snacks' => 'bakery_dining',
];
$categoryImages = [
    'Coffee Series' => 'coffee.png',
    'Matcha Series' => 'matcha.png',
    'Fruit Milk' => 'fruit-milk.png',
    'Soda' => 'soda.png',
    'Fruit Shake' => 'fruit-shake.png',
    'Latte Series with Whipped Cream' => 'latte.png',
    'Main Courses' => 'main-courses.png',
    'Snacks' => 'snacks.png',
];
$menuItemCount = count($menuItems);
?>

<section class="menu-page-intro" aria-labelledby="menu-page-title">
    <div class="menu-intro-copy">
        <span class="menu-eyebrow"><span aria-hidden="true"></span> Made fresh at Kape Cup</span>
        <h1 id="menu-page-title">Find your <em>feel-good</em> favorite.</h1>
        <p>From coffee and matcha to cozy café bites, explore the menu and make your next break a good one.</p>
        <div class="menu-intro-meta">
            <span class="menu-item-count"><strong><?= $menuItemCount ?></strong> <?= $menuItemCount === 1 ? 'item' : 'items' ?> on the menu</span>
            <span class="menu-meta-divider" aria-hidden="true"></span>
            <span class="menu-fresh-note"><span class="material-symbols-outlined" aria-hidden="true">eco</span> Prepared with care</span>
        </div>
    </div>
    <div class="menu-intro-art" aria-label="Coffee, matcha, and café favorites">
        <img class="menu-intro-photo menu-intro-photo-primary" src="assets/img/coffee.png" alt="Freshly made iced coffee">
        <img class="menu-intro-photo menu-intro-photo-secondary" src="assets/img/matcha.png" alt="Iced matcha latte">
        <span class="menu-intro-seal" aria-hidden="true">YOUR<br>DAILY<br>HAPPY</span>
    </div>
</section>

<section class="menu-browser" aria-label="Browse the menu">
    <div class="menu-browser-heading">
        <div>
            <span class="menu-browser-eyebrow">Take your pick</span>
            <h2>Something delicious awaits</h2>
        </div>
        <label class="menu-search-box" for="menu-search">
            <span class="material-symbols-outlined" aria-hidden="true">search</span>
            <input id="menu-search" placeholder="Search drinks and bites..." type="search" autocomplete="off">
        </label>
    </div>

<div class="menu-category-scroll">
    <nav
        aria-label="Menu Categories"
        class="menu-category-list"
        id="category-filter-bar"
    >
        <button
            class="category-btn active"
            data-category="all"
            type="button"
            aria-pressed="true"
        >
            <span class="material-symbols-outlined" aria-hidden="true">apps</span>
            <span>All</span>
            <span class="category-count"><?= $menuItemCount ?></span>
        </button>

        <?php foreach ($categories as $category): ?>
            <?php
            $categoryId = (int) $category['category_id'];
            $categoryName = $category['name'];
            $icon = $categoryIcons[$categoryName] ?? 'restaurant';
            ?>

            <button
                class="category-btn"
                data-category="<?= $categoryId ?>"
                type="button"
                aria-pressed="false"
            >
                <span class="material-symbols-outlined" aria-hidden="true"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="category-label"><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="category-count"><?= count(array_filter($menuItems, static function ($item) use ($categoryId): bool {
                    return (int) $item['category_id'] === $categoryId;
                })) ?></span>
            </button>
        <?php endforeach; ?>
    </nav>
</div>

<div class="menu-results-heading">
    <div>
        <h2 id="active-category-heading">All menu favorites</h2>
        <span id="active-category-count"><?= $menuItemCount ?> <?= $menuItemCount === 1 ? 'item' : 'items' ?> to explore</span>
    </div>
    <span class="menu-results-note"><span class="material-symbols-outlined" aria-hidden="true">local_cafe</span> Your next favorite is waiting</span>
</div>

<div
    class="menu-product-grid"
    id="menu-grid"
>
    <?php if (empty($menuItems)): ?>
        <div class="menu-empty-state" id="empty-menu-message">
            <span class="material-symbols-outlined" aria-hidden="true">coffee</span>
            <div>
                <h3>Good things are brewing.</h3>
                <p>We're preparing the menu. Please check back soon for your café favorites.</p>
            </div>
            <a href="index.php">Back to home <span aria-hidden="true">→</span></a>
        </div>
    <?php else: ?>
        <?php foreach ($menuItems as $menuItem): ?>
            <?php
            $itemId = (int) $menuItem['item_id'];
            $categoryId = (int) $menuItem['category_id'];
            $itemName = htmlspecialchars(
                $menuItem['name'],
                ENT_QUOTES,
                'UTF-8'
            );
            $description = htmlspecialchars(
                $menuItem['description'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            );
            $isSoldOut = (int) $menuItem['is_sold_out'] === 1
                || (int) $menuItem['stock_quantity'] < 1;
            $itemImage = $menuHasImageColumn && is_string($menuItem['image_filename']) && $menuItem['image_filename'] !== ''
                ? 'api/menu_image.php?id=' . $itemId
                : 'assets/img/' . ($categoryImages[$menuItem['category_name']] ?? 'coffee.png');
            ?>

            <article
                class="menu-item-card"
                data-category="<?= $categoryId ?>"
                data-name="<?= htmlspecialchars($menuItem['name'], ENT_QUOTES, 'UTF-8') ?>"
                data-description="<?= $description ?>"
            >
                <a class="menu-product-image" href="product_details.php?id=<?= $itemId ?>" tabindex="-1" aria-hidden="true">
                    <img src="<?= htmlspecialchars($itemImage, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" decoding="async">
                    <span class="menu-product-category"><?= htmlspecialchars($menuItem['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($isSoldOut): ?>
                        <span class="menu-sold-out-badge">Sold out</span>
                    <?php endif; ?>
                </a>
                <div class="menu-product-copy">
                    <h3><a href="product_details.php?id=<?= $itemId ?>"><?= $itemName ?></a></h3>
                    <p><?= $description !== '' ? $description : 'Made fresh to order at M&E Kape Cup.' ?></p>
                    <div class="menu-product-footer">
                        <strong>₱<?= number_format((float) $menuItem['base_price'], 2) ?></strong>
                        <?php if ($isSoldOut): ?>
                            <span class="menu-unavailable">Currently unavailable</span>
                        <?php else: ?>
                            <a class="menu-select-button" href="product_details.php?id=<?= $itemId ?>">Customize <span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</section>
<!-- Interactive Customizer Modal Drawer (Simulated Selection Flow) -->

<!-- Product Customizer Modal -->
<div
  id="option-modal"
  class="fixed inset-0 z-50 bg-espresso-dark/60 backdrop-blur-xs flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-200"
>
  <div
    id="option-modal-box"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-product-title"
    class="w-full max-w-md max-h-[90vh] overflow-y-auto bg-surface-container-lowest rounded-xl p-space-lg shadow-xl flex flex-col gap-space-md transform scale-95 transition-transform duration-200"
  >
    <!-- Modal Header -->
    <div class="flex items-start justify-between gap-3">
      <div class="flex flex-col min-w-0">
        <span class="font-label-sm text-label-sm text-caramel-gold uppercase tracking-wider font-bold">
          M&amp;E Kape Cup Bar
        </span>
        <h4
          id="modal-product-title"
          class="font-headline-sm text-headline-sm text-espresso-dark break-words"
        >
          Product Options
        </h4>
      </div>

      <button
        type="button"
        onclick="closeModal()"
        aria-label="Close product options"
        class="shrink-0 w-8 h-8 rounded-full bg-surface-container flex items-center justify-center text-text-muted hover:text-espresso-dark"
      >
        <span class="material-symbols-outlined text-title-sm">close</span>
      </button>
    </div>

    <!-- Cup Size -->
    <div class="flex flex-col gap-space-xs">
      <label class="font-label-lg text-label-lg text-espresso-dark font-semibold">
        Cup Size
      </label>

      <div class="grid grid-cols-2 gap-space-xs">
        <button
          type="button"
          class="cup-size-btn py-2 px-space-sm rounded-lg bg-secondary-container font-label-md text-label-md text-on-secondary-container font-bold flex items-center justify-center gap-1 shadow-xs"
          data-size="Regular"
          aria-pressed="true"
        >
          <span class="material-symbols-outlined text-label-md">coffee</span>
          Regular (16oz)
        </button>

        <button
          type="button"
          class="cup-size-btn py-2 px-space-sm rounded-lg bg-surface-container hover:bg-surface-container-high font-label-md text-label-md text-text-muted flex items-center justify-center gap-1"
          data-size="Large"
          aria-pressed="false"
        >
          <span class="material-symbols-outlined text-label-md">coffee</span>
          Large (22oz)
        </button>
      </div>
    </div>

    <!-- Sweetness Level -->
    <div class="flex flex-col gap-space-xs">
      <label class="font-label-lg text-label-lg text-espresso-dark font-semibold">
        Sweetness Level
      </label>

      <div class="grid grid-cols-4 gap-1 text-center font-label-sm text-label-sm">
        <button
          type="button"
          class="sweetness-btn py-2 rounded bg-surface-container text-text-muted hover:bg-surface-container-high"
          data-sweetness="0%"
          aria-pressed="false"
        >0%</button>

        <button
          type="button"
          class="sweetness-btn py-2 rounded bg-surface-container text-text-muted hover:bg-surface-container-high"
          data-sweetness="50%"
          aria-pressed="false"
        >50%</button>

        <button
          type="button"
          class="sweetness-btn py-2 rounded bg-secondary-container text-on-secondary-container font-bold shadow-xs"
          data-sweetness="100%"
          aria-pressed="true"
        >100%</button>

        <button
          type="button"
          class="sweetness-btn py-2 rounded bg-surface-container text-text-muted hover:bg-surface-container-high"
          data-sweetness="Extra"
          aria-pressed="false"
        >Extra</button>
      </div>
    </div>

    <!-- Station Notice: contained within the modal -->
    <div class="bg-surface-container-low rounded-lg p-space-sm flex items-start gap-space-sm">
      <span class="material-symbols-outlined text-espresso-dark">storefront</span>
      <div class="flex flex-col min-w-0">
        <span class="font-label-sm text-label-sm text-espresso-dark font-bold">
          Counter &amp; Takeaway Orders
        </span>
        <span class="font-body-sm text-body-sm text-text-muted">
          Item prices and availability are updated regularly by the cafe shift supervisor.
        </span>
      </div>
    </div>

    <!-- Modal Footer: buttons stay inside the card -->
    <div class="grid grid-cols-2 gap-space-sm pt-space-xs">
      <button
        type="button"
        onclick="closeModal()"
        class="w-full min-h-11 px-3 py-3 rounded-lg bg-surface-container text-espresso-dark font-semibold hover:bg-surface-container-high"
      >
        Cancel
      </button>

      <button
        type="button"
        onclick="confirmAddToCart()"
        class="w-full min-h-11 px-3 py-3 rounded-lg bg-espresso-dark text-white font-semibold hover:opacity-90"
      >
        Done
      </button>
    </div>
  </div>
</div>

<script>
  // Category filtering and product search
  const categoryButtons = document.querySelectorAll('.category-btn');
  const heading = document.getElementById('active-category-heading');
  const countLabel = document.getElementById('active-category-count');
  const searchInput = document.getElementById('menu-search');
  const productCards = document.querySelectorAll('#menu-grid article[data-category]');

  let activeCategory = document.querySelector('.category-btn.active')
    ?.getAttribute('data-category') || null;

  function updateMenu() {
    const query = searchInput
      ? searchInput.value.trim().toLowerCase()
      : '';

    let visibleCount = 0;

    productCards.forEach(card => {
      const cardCategory = card.getAttribute('data-category');
      const cardName = card.getAttribute('data-name') || '';
      const cardDescription = card.getAttribute('data-description') || '';

      const matchesCategory =
        activeCategory === null ||
        activeCategory === 'all' ||
        cardCategory === activeCategory;

      const matchesSearch =
        cardName.toLowerCase().includes(query) ||
        cardDescription.toLowerCase().includes(query);

      const isVisible = matchesCategory && matchesSearch;

      card.style.display = isVisible ? '' : 'none';

      if (isVisible) {
        visibleCount++;
      }
    });

    if (countLabel) {
      countLabel.textContent =
        `${visibleCount} ${visibleCount === 1 ? 'item' : 'items'} to explore`;
    }
  }

  // Handle category button clicks
  categoryButtons.forEach(button => {
    button.addEventListener('click', () => {
      activeCategory = button.getAttribute('data-category');

      categoryButtons.forEach(btn => {
        const isActive = btn === button;

        btn.classList.toggle('active', isActive);
        btn.setAttribute('aria-pressed', String(isActive));
      });

      const categoryLabel = button.querySelector('.category-label');

      if (heading && categoryLabel) {
        heading.textContent =
          button.getAttribute('data-category') === 'all'
            ? 'All menu favorites'
            : categoryLabel.textContent.trim();
      } else if (heading) {
        heading.textContent = 'All menu favorites';
      }

      updateMenu();
    });
  });

  // Handle product search
  if (searchInput) {
    searchInput.addEventListener('input', updateMenu);
  }

  // Show the correct initial product count
  updateMenu();

  // Modal interactions
  const modal = document.getElementById('option-modal');
  const modalBox = document.getElementById('option-modal-box');
  const modalTitle = document.getElementById('modal-product-title');

  // Stores the product currently being customized.
  let selectedProduct = null;


function handleSelectOptions(productId, productName, productPrice) {
  if (!modal || !modalBox) return;

  selectedProduct = {
    item_id: Number(productId),
    name: productName,
    base_price: Number(productPrice)
  };

  selectedSize = 'Regular';
  selectedSweetness = '100%';

  // Reset Cup Size button highlights.
  document.querySelectorAll('.cup-size-btn').forEach(btn => {
    const active = btn.dataset.size === selectedSize;

    btn.classList.toggle('bg-secondary-container', active);
    btn.classList.toggle('text-on-secondary-container', active);
    btn.classList.toggle('font-bold', active);
    btn.classList.toggle('shadow-xs', active);
    btn.classList.toggle('bg-surface-container', !active);
    btn.classList.toggle('text-text-muted', !active);
    btn.setAttribute('aria-pressed', String(active));
  });

  // Reset Sweetness button highlights.
  document.querySelectorAll('.sweetness-btn').forEach(btn => {
    const active = btn.dataset.sweetness === selectedSweetness;

    btn.classList.toggle('bg-secondary-container', active);
    btn.classList.toggle('text-on-secondary-container', active);
    btn.classList.toggle('font-bold', active);
    btn.classList.toggle('shadow-xs', active);
    btn.classList.toggle('bg-surface-container', !active);
    btn.classList.toggle('text-text-muted', !active);
    btn.setAttribute('aria-pressed', String(active));
  });

  if (modalTitle) {
    modalTitle.textContent = productName;
  }

  modal.classList.remove('hidden');

  setTimeout(() => {
    modal.classList.remove('opacity-0');
    modalBox.classList.remove('scale-95');
    modalBox.classList.add('scale-100');
  }, 10);
}
  function closeModal() {
    if (!modal || !modalBox) return;

    modal.classList.add('opacity-0');
    modalBox.classList.remove('scale-100');
    modalBox.classList.add('scale-95');

    setTimeout(() => {
      modal.classList.add('hidden');
    }, 200);
  }

  // Default product options
  let selectedSize = 'Regular';
  let selectedSweetness = '100%';

  // Cup size selection
  document.querySelectorAll('.cup-size-btn').forEach(button => {
    button.addEventListener('click', () => {
      selectedSize = button.dataset.size;

      document.querySelectorAll('.cup-size-btn').forEach(btn => {
        const active = btn === button;

        btn.classList.toggle('bg-secondary-container', active);
        btn.classList.toggle('text-on-secondary-container', active);
        btn.classList.toggle('font-bold', active);
        btn.classList.toggle('shadow-xs', active);
        btn.classList.toggle('bg-surface-container', !active);
        btn.classList.toggle('text-text-muted', !active);
        btn.setAttribute('aria-pressed', String(active));
      });
    });
  });

  // Sweetness selection
  document.querySelectorAll('.sweetness-btn').forEach(button => {
    button.addEventListener('click', () => {
      selectedSweetness = button.dataset.sweetness;

      document.querySelectorAll('.sweetness-btn').forEach(btn => {
        const active = btn === button;

        btn.classList.toggle('bg-secondary-container', active);
        btn.classList.toggle('text-on-secondary-container', active);
        btn.classList.toggle('font-bold', active);
        btn.classList.toggle('shadow-xs', active);
        btn.classList.toggle('bg-surface-container', !active);
        btn.classList.toggle('text-text-muted', !active);
        btn.setAttribute('aria-pressed', String(active));
      });
    });
  });

 function confirmAddToCart() {
  if (!selectedProduct) {
    alert('Please select a product first.');
    return;
  }

  window.location.href = 'product_details.php?id='
    + encodeURIComponent(String(selectedProduct.item_id));
}
</script>
</main><footer class="w-full bg-surface-container-low mt-space-xl shadow-[0_-1px_6px_rgba(41,34,29,0.03)]"><div class="max-w-[1280px] mx-auto px-margin-desktop py-space-xl flex flex-col md:flex-row items-center justify-between gap-space-md"><div class="flex flex-col items-center md:items-start gap-space-xs"><div class="flex items-center gap-space-xs"><span class="font-headline-sm text-headline-sm text-espresso-dark">M&amp;E Kape Cup</span><span class="px-space-xs py-0.5 rounded bg-secondary-container text-on-secondary-container font-label-sm text-label-sm">Cafe &amp; Roastery</span></div><p class="font-body-sm text-body-sm text-text-muted">Warm roasts, timeless diner charm &amp; handcrafted coffee.</p></div><div class="flex flex-col md:flex-row items-center gap-space-lg"><div class="font-label-sm text-label-sm text-text-muted text-center md:text-right"><p class="mt-1">© 2024 M&amp;E Kape Cup. All rights reserved.</p></div></div></div></footer></body></html>