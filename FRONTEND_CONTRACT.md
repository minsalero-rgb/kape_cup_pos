# Frontend contract inventory (pre-change)

Generated from the untouched `baseline` commit before frontend edits. PHP source and API contracts are recorded as-is.

| Page | CSS / JS assets | Forms (`action`, `method`, `name`, `value`) | IDs / data hooks | Fetch URLs |
|---|---|---|---|---|
| `admin/categories.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/dashboard.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/duty_sessions.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/login.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/menu.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/menu_form.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/modifiers.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/modules/categories.php` | CSS: ?<br>JS: ? | form action='dashboard.php?tab=categories' method='post'<br>name='csrf_token'<br>name='section'<br>name='action'<br>name='category_id' | data-confirm='Delete this category? This is allowed only when no menu items are assigned.' | ? |
| `admin/modules/menu.php` | CSS: ?<br>JS: ? | form action='dashboard.php?tab=menu' method='post'<br>name='csrf_token'<br>name='section'<br>name='action'<br>name='item_id'<br>name='category_id'<br>name='name'<br>name='base_price'<br>name='description'<br>name='image_file'<br>name='remove_image' | data-confirm='<?= (int) $item[' | ? |
| `admin/modules/modifiers.php` | CSS: ?<br>JS: ? | form action='dashboard.php?tab=modifiers' method='post'<br>name='csrf_token'<br>name='section'<br>name='action'<br>name='modifier_group_id'<br>name='name'<br>name='group_key'<br>name='is_required'<br>name='min_selections'<br>name='max_selections'<br>name='sort_order'<br>name='is_active'<br>name='modifier_option_id'<br>name='option_key'<br>name='price_delta' | ? | ? |
| `admin/modules/orders.php` | CSS: ?<br>JS: ? | form action='dashboard.php' method='get'<br>name='tab'<br>name='status'<br>form action='dashboard.php?tab=orders' method='post'<br>name='csrf_token'<br>name='section'<br>name='order_id'<br>name='expected_status' | data-confirm='Advance order #<?= (int) $order[' | ? |
| `admin/modules/overview.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/modules/reports.php` | CSS: ?<br>JS: ? | form action='dashboard.php' method='get'<br>name='tab'<br>name='date' | ? | ? |
| `admin/modules/sessions.php` | CSS: ?<br>JS: ? | form action='dashboard.php?tab=sessions' method='post'<br>name='csrf_token'<br>name='section'<br>name='shift_id' | data-confirm-shift='<?= (int) $session[' | ? |
| `admin/modules/staff.php` | CSS: ?<br>JS: ? | form action='dashboard.php?tab=staff' method='post'<br>name='csrf_token'<br>name='section'<br>name='action'<br>name='full_name'<br>name='username'<br>name='password'<br>name='role'<br>name='staff_id'<br>name='is_active' | id='create-account-heading'<br>id='staff-accounts-heading'<br>id='staff-password-<?= (int) $account['<br>data-confirm='<?= $accountIsActive ? ' | ? |
| `admin/orders.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/reports.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `admin/staff_accounts.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `cart.php` | CSS: assets/css/style.css?v=20261018-7, assets/css/tokens.css<br>JS: ? | form action='cart.php' method='post'<br>name='csrf_token'<br>name='action'<br>name='key'<br>name='quantity' | data-path='home'<br>data-active-classes='bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm'<br>data-path='menu'<br>data-path='order-status'<br>data-path='cart-drawer'<br>data-alt='Steaming Spanish Latte in an artisanal ceramic tumbler, textured crema with rich cinnamon dusting on a dark rustic wooden cafe table with scattered roasted beans, golden morning sunlight.'<br>data-alt='Freshly baked flaky golden pain au chocolat beside a petite double espresso cup, vintage bistro napkin, ambient warm diner illumination.'<br>data-alt='Ceremonial grade iced matcha latte with visible emerald green and cream milk swirl in clear tall fluted glass on marble counter.'<br>id='cart-drawer-scrim'<br>id='cart-drawer-heading'<br>id='summary-subtotal'<br>id='summary-total' | ? |
| `checkout.php` | CSS: assets/css/style.css?v=20261018-5, assets/css/tokens.css<br>JS: ? | form action='(implicit)' method='(implicit)'<br>name='customer_name'<br>name='order_type'<br>name='table_label'<br>name='payment_method'<br>name='cash_tendered' | id='checkout-message'<br>id='checkout-success'<br>id='checkout-success-order'<br>id='checkout-success-reference'<br>id='checkout-success-payment'<br>id='checkout-success-summary'<br>id='checkout-track-order'<br>id='checkout-form'<br>id='customer-name'<br>id='order-type'<br>id='table-label-field'<br>id='table-label'<br>id='cash-tendered-field'<br>id='cash-tendered'<br>id='submit-order-button' | api/submit_order.php returns the saved order reference, total, and payment method |
| `includes/auth.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/customer_header.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/customer_session.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/helpers.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/layout/footer.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/layout/header.php` | CSS: <?= escape_html(app_base_path()) ?>/assets/css/app.css?v=20261020-1, <?= escape_html(app_base_path()) ?>/assets/css/style.css?v=20261018-7, <?= escape_html(app_base_path()) ?>/assets/css/tokens.css<br>JS: ? | ? | ? | ? |
| `includes/layout/sidebar.php` | CSS: ?<br>JS: ? | form action='<?= escape_html($secondaryUrl) ?>' method='post'<br>name='csrf_token'<br>form action='<?= escape_html($basePath) ?>' method='post' | id='app-sidebar'<br>data-badge='<?= escape_html($item[' | ? |
| `includes/layout/topbar.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/login_page.php` | CSS: ../assets/css/style.css?v=20261018-7, ../assets/css/tokens.css<br>JS: ? | form action='<?= escape_html($formAction) ?>' method='post'<br>name='csrf_token'<br>name='username'<br>name='password' | id='username'<br>id='password' | ? |
| `includes/login_throttle.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/menu_images.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/nav_config.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/order_api.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/order_idempotency.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/order_submission.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/order_tracking.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/order_workflow.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/password_rules.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `includes/shift.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `index.php` | CSS: assets/css/style.css?v=20261020-1, assets/css/tokens.css<br>JS: ? | ? | id='store-hero-title'<br>id='featured-menu-heading' | ? |
| `menu.php` | CSS: assets/css/style.css?v=20261020-3, assets/css/tokens.css<br>JS: ? | ? | data-path='home'<br>data-active-classes='bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm'<br>data-path='menu'<br>data-path='order-status'<br>data-path='cart-drawer'<br>id='menu-page-title'<br>id='menu-search'<br>id='category-filter-bar'<br>data-category='all'<br>id='active-category-heading'<br>id='active-category-count'<br>id='menu-grid'<br>id='empty-menu-message'<br>id='option-modal'<br>id='option-modal-box'<br>id='modal-product-title'<br>data-size='Regular'<br>data-size='Large'<br>data-sweetness='0%'<br>data-sweetness='50%'<br>data-sweetness='100%'<br>data-sweetness='Extra' | ? |
| `product_details.php` | CSS: assets/css/style.css?v=20261018-7, assets/css/tokens.css<br>JS: ? | form action='cart.php' method='post'<br>name='csrf_token'<br>name='action'<br>name='item_id'<br>name='quantity' | data-path='home'<br>data-active-classes='bg-secondary-container text-on-secondary-container font-bold rounded-lg shadow-sm'<br>data-path='menu'<br>data-path='order-status'<br>data-path='cart-drawer'<br>id='modal-container'<br>id='product-name'<br>id='product-description'<br>id='base-price-display'<br>id='special-instructions'<br>id='qty-decrease'<br>id='qty-value'<br>id='qty-increase'<br>id='line-total-display'<br>id='modal-cancel-btn'<br>id='modal-add-cart-btn'<br>id='cart-btn-price'<br>id='add-to-cart-form'<br>id='cart-quantity' | ? |
| `staff/change_password.php` | CSS: ?<br>JS: ? | form action='change_password.php' method='post'<br>name='csrf_token'<br>name='current_password'<br>name='new_password'<br>name='confirm_password' | ? | ? |
| `staff/dashboard.php` | CSS: ?<br>JS: ? | form action='start_shift.php' method='post'<br>name='csrf_token' | id='staff-shift-heading' | ? |
| `staff/end_shift.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `staff/login.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `staff/logout.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `staff/offline_pos.php` | CSS: <?= escape_html($basePath) ?>/assets/css/app.css, <?= escape_html($basePath) ?>/assets/css/tokens.css<br>JS: ? | form action='offline_pos.php?page=pos' method='post'<br>name='csrf_token'<br>name='action'<br>name='item_id'<br>name='modifier_groups[<?= $group['<br>name='notes'<br>name='quantity'<br>name='key'<br>name='submission_key'<br>name='payment_method'<br>name='cash_tendered' | id='pos-menu-heading'<br>id='pos-product-search'<br>id='pos-category-filter'<br>data-pos-category-section='<?= (int) $category['<br>id='pos-category-<?= (int) $category['<br>data-pos-category='<?= (int) $category['<br>id='pos-filter-empty'<br>id='pos-cart-heading'<br>id='pos-payment-method'<br>id='pos-cash-field' | ? |
| `staff/orders.php` | CSS: ?<br>JS: ? | form action='orders.php' method='post'<br>name='csrf_token'<br>name='order_id'<br>name='expected_status' | id='queue-feedback'<br>id='initial-order-queue' | ? |
| `staff/reports.php` | CSS: ?<br>JS: ? | form action='offline_pos.php' method='get'<br>name='page'<br>name='period' | ? | ? |
| `staff/start_shift.php` | CSS: ?<br>JS: ? | ? | ? | ? |
| `staff/stock.php` | CSS: ?<br>JS: ? | form action='offline_pos.php?page=stock' method='post'<br>name='csrf_token'<br>name='item_id'<br>name='quantity_change'<br>name='reason' | ? | ? |
| `status.php` | CSS: assets/css/style.css?v=20261018-7, assets/css/tokens.css<br>JS: ? | GET form `name='reference'` (8 digits)<br>action='status.php' | id='order-reference'<br>id='tracking-message' | api/ordering_status.php?reference=; legacy `?id=` remains protected by `X-Order-Tracking-Token` |
