# M&E Kape Cup POS — frontend audit and validation

## Confirmed issues

| Severity | Confirmed finding and evidence | Affected files | Status / reproducible verification |
|---|---|---|---|
| Medium | Live pages previously loaded `style.css`, `tokens.css`, and `app.css` in inconsistent combinations; Google Fonts were linked per page. The standalone POS setup page did not load `style.css`. | [includes/layout/header.php](./includes/layout/header.php), [includes/login_page.php](./includes/login_page.php), [staff/offline_pos.php](./staff/offline_pos.php), [index.php](./index.php), [menu.php](./menu.php), [cart.php](./cart.php), [product_details.php](./product_details.php), [checkout.php](./checkout.php), [status.php](./status.php) | Fixed. Inspect each HTML page's `link[rel=stylesheet]`; browser smoke tests confirmed one unified CSS link on all tested customer and login pages. |
| Medium | Customer pages depended on Google Fonts. The initial self-hosted icon font rendered names as text because ligatures were not enabled; data-sourced `spa` and `restaurant` icons were missing from its first subset. | [assets/css/style.css](./assets/css/style.css), [assets/fonts/material-symbols.woff2](./assets/fonts/material-symbols.woff2), customer PHP views | Fixed. Browser menu rendered the icons; font checks passed and its loaded resource list contained no external URLs. |
| Low | Menu search matched card UI text. On the live menu, searching `sold out` incorrectly displayed all three products. | [menu.php](./menu.php) | Fixed. Type `sold out` (0 cards) and `cappuccino` (1 card) in menu search. |
| Low | `menu.php`, `cart.php`, and `product_details.php` had no document title; the live menu's `document.title` was empty. | [menu.php](./menu.php), [cart.php](./cart.php), [product_details.php](./product_details.php) | Fixed. Browser smoke tests verified the three page titles. |
| Low | Stylesheet cache-busting values differed across pages and could leave a stale CSS version after deployment. | [includes/partials/head.php](./includes/partials/head.php), customer PHP views, [includes/login_page.php](./includes/login_page.php), [staff/offline_pos.php](./staff/offline_pos.php) | Fixed. Rendered HTML uses the `style.css` modification time as `?v=`; browser checks verified the query on shared-head pages. |

## Application map

- **Customer entry points:** `index.php`, `menu.php`, `product_details.php`, `cart.php`, `checkout.php`, and `status.php`. Customer pages use the customer session and render menu/cart/order status.
- **Staff routes:** `staff/login.php`, `change_password.php`, `dashboard.php`, `offline_pos.php`, `orders.php`, `stock.php`, `reports.php`, `start_shift.php`, `end_shift.php`, and `logout.php`.
- **Admin routes:** login/dashboard plus thin page wrappers for categories, duty sessions, menu, modifiers, orders, reports, and staff accounts. `admin/dashboard.php` dispatches the `admin/modules/` views.
- **JSON APIs:** `api/menu.php`, `menu_image.php`, `ordering_status.php`, `order_notifications.php`, `queue.php`, `submit_order.php`, and `update_order_status.php`.
- **Shared PHP:** `includes/auth.php`, `customer_session.php`, `login_page.php`, `layout/`, `nav_config.php`, and the order submission, workflow, tracking, inventory, and image helpers.
- **Database:** the fresh-install schema defines `staff`, `staff_shifts`, `categories`, `menu_items`, modifier groups/options, `orders`, order items/modifiers, inventory logs, login attempts, order submission logs, and idempotency requests. Existing-database migrations 001–005 in `database/migrations/` match the documented order in `docs/DATABASE_SETUP.md`; the full schema includes those migration results.
- **Contracts:** HTML form actions/methods/names, CSRF fields, API routes/headers, and order/session logic were not changed. The `data-description` menu-search attribute is additive. `FRONTEND_CONTRACT.md` is retained as the pre-change snapshot, so it intentionally mentions old stylesheet URLs.

## Security-sensitive review

Static inspection covered staff/customer session setup and regeneration, role checks, login password verification/rehashing and throttling, CSRF checks on state-changing forms/APIs, prepared SQL, order idempotency and stock locks, tracking HMAC validation, upload type/size/path checks, and generic database error messages. **No confirmed security defect was established in this review.** This is not a penetration test or a replacement for authenticated, database-backed concurrency and authorization tests. No backend file, schema, route, credential, or business rule was changed.

## Changes

- Merged the current tokens, local font faces, base/accessibility rules, shared components, existing Tailwind v3.4.17 output, customer styling, admin/staff styling, and layout/responsive rules into [assets/css/style.css](./assets/css/style.css). Existing utility and app rules were retained in cascade order; the live Material Symbols ligature and menu-filter rules were repaired.
- Updated the customer pages, login partial, shared application head, and POS database-upgrade page to load the unified stylesheet. Added missing customer page titles and consistent filemtime cache keys.
- Removed the superseded stylesheets: `assets/css/app.css`, `base.css`, `components.css`, `fonts.css`, `layout-app.css`, `layout-customer.css`, and `tokens.css`. The only CSS file remaining under `assets/css/` is `style.css`.
- Updated `docs/README.md` to describe the unified stylesheet and local fonts.
- Added a follow-up semantic component layer for buttons, fields, status badges, notices, tables, tabs, empty/loading states, and pagination. Refined the shared tokens and used them for the new app/customer refinements; customer views now use shared semantic header/login markup instead of page-specific utility styling.
- Added the mobile customer cart bar and improved POS sold-out/low-stock affordances. Updated supported icon names and order-state badges in the inspected views.
- Removed the remaining native confirmation calls from the staff queue and admin close-shift forms so the shared dialog is the single confirmation path. The shared dialog now composes close-shift copy, preserves keyboard focus behavior, and runs before staff queue submit handlers.

### Modified files

`admin/dashboard.php`; `admin/modules/orders.php`; `admin/modules/overview.php`; `assets/css/style.css`; `assets/fonts/material-symbols.woff2`; `assets/js/ui.js`; `cart.php`; `checkout.php`; `docs/README.md`; `includes/customer_header.php`; `includes/layout/header.php`; `includes/login_page.php`; `includes/partials/head.php`; `index.php`; `menu.php`; `product_details.php`; `staff/dashboard.php`; `staff/offline_pos.php`; `staff/orders.php`; `status.php`.

### Deleted files

`assets/css/app.css`; `assets/css/base.css`; `assets/css/components.css`; `assets/css/fonts.css`; `assets/css/layout-app.css`; `assets/css/layout-customer.css`; `assets/css/tokens.css`.

## Validation results

- PHP lint using WAMP PHP 8.0.26: **all 16 changed PHP files, 0 failures**.
- PHP lint using PHP 8.2.0: **all 16 changed PHP files, 0 failures**.
- WAMP smoke test: Apache on port 80 and MySQL on port 3306 responded. Browser GET smoke tests for home, menu, product details, cart, checkout, invalid order status, staff login, and admin login all returned HTTP 200; each HTML page exposed one stylesheet URL.
- Browser checks on customer routes (including `product_details.php?id=2`) and both login pages at 360, 768, 1024, and 1280 CSS px: no document horizontal overflow, console errors on valid routes, broken images, or third-party requests. The product page without an ID correctly returned 404. Keyboard focus showed a visible outline. Local Inter and icon fonts loaded.
- Browser search regression test: `sold out` returned zero cards; `cappuccino` returned one.
- Sampled foreground/background contrast ratios passed AA: body text/paper 16.74:1, muted text/paper 5.28:1, primary/paper 14.73:1, border/paper 3.33:1, focus/paper 14.73:1, and semantic alert pairs 6.21–9.15:1.
- Synthetic confirmation test verified exactly one shared dialog, composed close-shift text, cancellation without submission, accepted submission once, and queue API handling only after acceptance.
- Static reference scan: no live PHP link to removed CSS files and no live Google Fonts/Tailwind CDN CSS requests. `assets/css/` contains only `style.css`.
- CSS source markers: 44 media queries, 126 `!important` declarations, and 270 hex-color occurrences remain in the merged stylesheet. New/refined app rules use tokens, but compiled utility and legacy cascade colors have not been fully normalized; bulk rewriting generated rules risks changing existing utility behavior.
- `git diff --check`: passed. No changes under `api/`, `config/`, `database/`, `includes/auth.php`, or `includes/order_*.php`. No Node.js executable was available for `node --check`; the retired offline POS build was not run.

## Remaining WAMP checks and limits

The live database returned three menu items, all sold out, so it was not appropriate to create a customer order or change stock as part of this frontend pass. No staff/admin credentials were supplied, and no write operations were performed. Therefore checkout retry/idempotency, payment verification, order tracking with a valid token, shift processing, stock deduction, admin mutations, reports, and authenticated tablet layouts remain unverified. The authenticated keyboard-only walkthrough for POS order, queue advance, and close-shift remains outstanding; only the shared confirmation path was tested synthetically. Repeat those flows in a disposable or backed-up WAMP database with an authorized test account and an intentionally stocked test item. Test with the network disabled. No Lighthouse audit was run.

Prioritize these remaining checks:

1. **Database/order safety:** in a disposable database, submit simultaneous duplicate checkout requests, verify one order is created, confirm unavailable/low-stock items cannot be sold, and test all legal/illegal status transitions. Check inventory audit rows and cash/change calculations without implying electronic payment is automatically verified.
2. **Authentication/security:** verify unauthenticated and wrong-role requests to every Staff/Admin route/API, CSRF rejection, login throttling, session regeneration/cookie flags under HTTPS, tracking-token privacy and invalid-token responses, and malformed/oversized upload rejection. Confirm uploads remain outside `DocumentRoot`.
3. **Accessibility:** audit label/error associations, keyboard order/focus, Escape behavior for dialog/drawer, contrast, icon accessible names, reduced motion, and zoom at 200%; run Lighthouse/axe on customer and authenticated shell pages.
4. **Visual/offline:** repeat 360/768/1280 px screenshots for home, cart, checkout, status, POS, queue, stock, admin tables, and reports; disable network and verify all fonts, icons, and images that are expected to be local.
