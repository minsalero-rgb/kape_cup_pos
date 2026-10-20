# MASTER GOOGLE STITCH PROMPT: M&E KAPE CUP WEB-BASED POS ORDERING SYSTEM (FULL SYSTEM, ALL SCREENS)

**Attach before running:** (1) the real M&E Kape Cup circular logo, (2) the promotional drinks photo (blue Soda, green Matcha, iced Coffee on a dark espresso background with the logo above). Use them as the visual references. Do not redraw, restyle, or replace the logo or mascot.

---

## 1. Project Overview and Source of Truth

Design a cohesive, desktop/laptop-first, fully responsive web-based Point-of-Sale (POS) Ordering System for **M&E Kape Cup**, covering **every screen of the system**: customer pages, the staff/admin login, the staff dashboard, the admin dashboard, and all admin management and report pages.

The system will be built by a beginner developer using Visual Studio Code, GitHub Copilot, Google Stitch, HTML, Tailwind CSS, JavaScript, PHP, Apache, and MySQL 5.7.40 on WampServer.

This task is **UI/UX design and visual prototyping only**. Do not implement backend logic, database operations, authentication, real payment processing, or any working server-side behavior inside Stitch.

**Authority rules**
- The approved project specification, roadmap, and DECISIONS.md are the authority for functionality and business rules. If any old design, prompt, or visual idea conflicts with them, the business rule wins.
- Do not invent business requirements. If something is unresolved, use a neutral placeholder, omit the feature, or label it **"Awaiting confirmation."**
- Never present invented products, prices, sizes, modifiers, taxes, fees, discounts, promotions, hours, addresses, sales figures, names, or order records as real. Any demonstration data must be visibly labeled **"Prototype data (fictional)."**

---

## 2. Screen Inventory (design ALL of these)

The system has four areas. Design every screen listed, using one shared design system.

### A. Customer-facing (public, no login)
1. **Home (landing page)**
2. **Digital Menu** (search, eight category filters, product grid)
3. **Product Details modal/panel** (size options + modifier groups)
4. **Cart drawer/panel** (with empty-cart state)
5. **Checkout / Order Review** (payment method choice, final review)
6. **Order Submitted / Order Status page** (shows the current status using the approved sequence)
7. **Ordering Unavailable state** (variant of the menu/cart/checkout when ordering is closed)

### B. Authentication (staff and admin only)
8. **Staff/Admin Login** (single login page for both roles, with error and disabled/loading states; the role determines which dashboard and navigation the user sees after login)

### C. Staff workspace
9. **Staff Dashboard: Order Queue & POS** (shift area, order queue, selected-order details, status actions)
10. **Staff Shift states** (no active shift / own open shift / shift requiring Admin resolution), shown inside the dashboard, not as separate invented features
11. **Confirmation dialogs** (start shift, end shift, Mark as Paid, Start Preparing, Mark as Completed)

### D. Admin workspace
12. **Admin Dashboard** (same layout family as the staff dashboard, with the full admin navigation)
13. **Order Oversight** (all orders, search/filter, order details; same status workflow rules)
14. **Menu Management: Categories** (list, add/edit, delete-blocked state)
15. **Menu Management: Menu Items** (list with search/filter, add/edit form with sizes and modifier groups, activate/deactivate)
16. **Menu Management: Modifier Groups & Options**
17. **Staff & Account Management** (staff list, add/edit, activate/deactivate)
18. **Duty Session Records** (shift history, stale/conflicting shift resolution) **ADMIN ONLY**
19. **Sales & Reports** (Completed orders only) **ADMIN ONLY**

### Access rules (must be visible in the navigation design)
| Page | Customer | Staff | Admin |
|---|---|---|---|
| Home, Menu, Cart, Checkout, Order Status | ✔ | n/a | n/a |
| Login | n/a | ✔ | ✔ |
| Dashboard (order queue and POS) | n/a | ✔ | ✔ |
| Order Oversight | n/a | n/a | ✔ |
| Menu Management (categories, items, modifiers) | n/a | n/a | ✔ |
| Staff & Account Management | n/a | n/a | ✔ |
| **Duty Session Records** | n/a | **✘** | **✔ only** |
| **Sales & Reports** | n/a | **✘** | **✔ only** |

- The staff sidebar must **not** show Duty Session Records or Sales & Reports.
- The admin sidebar shows everything.
- The customer interface never shows staff or admin links, apart from a discreet "Staff Login" text link in the footer.

---

## 3. Brand Identity (from the real logo and drinks photo)

- **Logo:** a circular, vintage badge: espresso-brown disc, double cream ring, "M&E" arched on top and "KAPE CUP" arched below in a bold cream serif, small coffee beans left and right, and a friendly cartoon coffee-cup mascot (pointing both hands up, walking, white shoes). **Use the supplied logo as-is.** Do not recreate, simplify, recolor, or animate it.
- **Photography:** the drinks photo shows a blue Soda with lime and splash, an iced Matcha latte with foam, and an iced Coffee on a rustic light-wood table, against a deep espresso-brown backdrop. Use it as the hero image on Home and as the tone reference for imagery elsewhere. Do not invent new drink names from it.
- **Personality:** warm, welcoming, approachable café with vintage character and a clean modern web interface.
- **Mascot rule:** appears in the logo and at most in one or two friendly spots (for example the empty-cart or login illustration). Never on every card or button.
- No excessive decoration in ordering or staff areas.

---

## 4. Shared Visual Design System

### Color tokens (use consistently on every screen)
| Token | Hex | Use |
|---|---|---|
| Espresso Brown | #3B1F14 | Navigation, primary buttons, strong brand areas |
| Cream Beige | #F4E6C8 | Warm secondary surfaces, logo lettering color |
| Caramel Gold | #D9B77E | Restrained highlights, dividers, selected accents |
| Matcha Green | #78945B | Positive/success states, subtle accents |
| Warm White | #FFFCF6 | Page and card backgrounds |
| Dark Text | #29221D | Headings and body |
| Muted Text | #74685E | Secondary labels |

Add an accessible **red** for errors and destructive actions, an **amber** for warnings, and neutral grays for borders, dividers, disabled controls, and subtle backgrounds. Meet WCAG AA contrast.

### Typography
- **Bold friendly serif** for brand headings and selected promotional text only (echoing the logo lettering).
- **Clean sans-serif** for navigation, labels, descriptions, prices, forms, tables, buttons, and all operational (staff/admin) areas.
- Consistent hierarchy for: page titles, section headings, card headings, body, supporting text, form labels, prices/totals, table headers, status labels.

### Spacing, surfaces, icons
Consistent spacing scale, subtle borders, restrained shadows, moderately rounded cards, one icon style, clear alignment. Prefer simple, reusable layouts.

### Reusable components (define once, reuse everywhere)
- **Buttons:** primary, secondary, tertiary/text, destructive, disabled, loading, compact icon button with accessible label. Use descriptive verbs ("Order Now," "Add to Cart," "Save Changes," "Mark as Paid"). Avoid "Proceed."
- **Form controls:** text input, search field, select, checkbox, radio, quantity stepper, textarea, validation message, required-field marker, disabled and read-only fields. Visible labels, not placeholder-only.
- **Cards/panels:** product card, cart item, order queue card, order details panel, summary card, confirmation dialog, empty-state panel, alert/notification panel, data table, admin form panel.
- **Status badges:** consistent padding, border, and typography. **Always text label plus icon where useful, never color alone.**
- **Shared states shown as examples:** default, hover, keyboard focus, selected, disabled, loading, success, warning, error, empty results, unavailable item, confirmation required.

---

## 5. Responsive Rules (one web system, no native app)

Reference viewport **1440 × 900**; must also work at **1280 × 720** and wider.
- **Desktop/laptop:** multi-column layouts, top navigation for customer pages, structured left sidebar for staff/admin. Avoid oversized headers and wasted space.
- **Tablet:** reflowing grids, collapsible sidebar, readable order details, no hidden primary actions.
- **Mobile:** compact navigation (menu button), stacked content, touch-friendly controls, easy access to cart and prices, no unnecessary horizontal scrolling. Wide tables become stacked cards or scroll inside their own container.

---

## 6. Customer Screens

### 6.1 Home
Required: logo and clear customer navigation (Home, Menu, Cart); concise hero heading and supporting text (optional brand copy such as "Your Daily Cup of Happiness" may be used as a slogan, not as a factual claim); prominent **"Order Now"** button leading to the menu; the drinks photo; a brief intro to the café and ordering experience; a restrained **category-navigation section listing the eight approved categories** (no products, no prices); a clean footer with only confirmed business info (use "[Contact details pending confirmation]").

Do **not** invent: product names or prices, bestsellers, testimonials/ratings, operating hours, addresses, delivery or pickup, prep-time promises, promotions/discounts, customer or sales counts, loyalty programs.

### 6.2 Digital Menu
Include: search field, category filter bar, responsive product grid, and consistent product cards (image area, name, optional description, price, availability badge, one "Add to Cart" / "Select Options" action in the same position on every card), plus a clear **Unavailable** card treatment (dimmed, "Unavailable" badge, disabled action).

**Use exactly these eight categories, in this order:**
1. Coffee Series
2. Matcha Series
3. Fruit Milk
4. Soda
5. Fruit Shake
6. Latte Series with Whipped Cream
7. Main Courses (scope: chicken rice meals and beef, pork, chicken, and fish dishes)
8. Snacks

No generic substitutes ("Coffee," "Tea," "Cold Drinks," "Food") and no extra categories.

Menu data is not final. Use visibly labeled placeholder cards: **"[Product name]"**, **"[Price pending confirmation]"**, **"[Description if available]"**. Add a discreet note "Menu data pending owner confirmation." The beverage photo may be used as placeholder imagery with a neutral "[Product photo]" caption where needed.

### 6.3 Product Details modal/panel
Built to reflect the owner's menu data structure, shown with placeholders only:
- Product name, description (if available), base price (if applicable).
- **Size options** (only if the product has sizes): radio list of "[Size name]" with "[Price]" and an availability note. If a product has no sizes, show no size section.
- **Modifier groups** (only if the product has them): each group shows its **name**, a **Required/Optional** tag, the **selection limits** (minimum and maximum, for example "Choose up to [max]"), and its **options**, each with the fixed **additional price** (or none) and availability/notes. Use checkboxes or radios depending on the limits. Show validation messages when a required group is not satisfied or a maximum is exceeded.
- Additional prices apply **per ordered item unit** (initial project direction, pending owner confirmation).
- No default option is preselected unless data says so. Do not assume every product has sizes or modifiers. Do not invent modifier names or prices.
- Quantity stepper, live line-total preview labeled as a placeholder calculation, and an "Add to Cart" button.
- Selections apply to every unit in that order line; different customizations mean separate order lines.

### 6.4 Cart drawer/panel
Per line: item name, quantity stepper, selected size and modifiers, base price, modifier charges, line total, with **Edit** and **Remove** controls. Then order subtotal and final total (**no tax, fee, or discount lines**; label demo values as placeholders). Provide an **empty-cart state** with a "Browse the Menu" button. A "Review Order" button leads to checkout, disabled when the cart is empty or ordering is unavailable.

### 6.5 Checkout / Order Review
Simple, single-column or two-column layout: itemized review, payment method choice, total, and a **"Submit Order"** button.
- **Approved payment methods only: Cash, GCash, Credit Card.** No Maya, no other providers.
- Present payment as the customer's **chosen method for the order**, not a completed payment. No card-number form fields that imply real processing, no gateway logos or claims of online payment completion.
- No delivery or pickup options, no tax rate, no service/payment fee.
- Validation messages for an empty cart or missing payment method.

### 6.6 Order Submitted / Order Status
Show a placeholder order reference "[Order reference]" and the current status using the approved four-step tracker **Pending → Paid → Preparing → Completed** (text labels with icons). Brief explanation text per status. Do not promise preparation times. Make the "submitted" state clearly dependent on system confirmation (label as prototype).

### 6.7 Ordering Unavailable state
Shown when no qualifying active staff shift exists (an active Admin shift alone does **not** enable ordering). Display a friendly banner: **"Ordering is temporarily unavailable."** with a short explanation, **without exposing internal staff or shift details**. Browsing the menu stays possible; add-to-cart/submit actions are disabled or hidden. Never imply an order was accepted.

---

## 7. Authentication

### 7.1 Staff/Admin Login
- Logo, page title "Staff & Admin Login," username (or email) field, password field, show/hide password control, **"Log In"** button (default, loading, and disabled states), and an error message state ("Incorrect username or password") with icon and text.
- One login for both roles. After login, staff land on the Staff Dashboard and admins on the Admin Dashboard.
- A "Back to Home" link. No sign-up, social login, or invented "forgot password" flow (mark as "Awaiting confirmation" if shown at all).
- Visually calmer and more operational than the customer pages; a single mascot or logo illustration is acceptable.

---

## 8. Staff Workspace

### 8.1 Shared app shell (staff and admin)
- **Left sidebar** (collapsible on tablet, drawer on mobile) in Espresso Brown with the logo, navigation, and a logout button.
- **Top bar:** page title, signed-in user's name and role badge ("Staff" or "Admin") shown as **[Prototype user]**, and the current shift indicator.
- **Staff sidebar items:** Dashboard (Orders), My Shift.
- **Admin sidebar items:** Dashboard, Order Oversight, Menu Management (Categories, Menu Items, Modifier Groups), Staff & Accounts, **Duty Session Records**, **Sales & Reports**.
- Avoid deep nested navigation.

### 8.2 Staff Dashboard: Order Queue & POS
Operational interface: clarity, speed, accuracy, safe actions first.

**Layout (desktop):** left sidebar; main area split into an **order queue column** and a **selected-order details panel**; a **shift action area** at the top.

**Shift states (design all three):**
1. **No active shift:** neutral card with a primary **"Start Shift"** action and a note that customer ordering is closed.
2. **Own open shift:** shows the shift is open, with an **"End Shift"** action (confirmation dialog).
3. **Shift requires Admin resolution** (stale or conflicting): warning panel with a text label and icon, saying an administrator must resolve it; **no staff action to resume or close it**.

Rules: staff can resume **only their own** open shifts and never another staff member's. An active Admin shift alone does not enable customer ordering. Ordering closes when there is no qualifying active staff shift. **No automatic closing of stale shifts.** No scheduling, attendance analytics, assignments, or automatic clock-out.

**Order queue card fields:** order reference, order time, status badge, concise item summary or item count, total (₱), payment method and payment state (visually distinct). Search/filter by status and reference. The selected order is clearly highlighted. Empty-queue and no-results states included.

**Order details panel:** reference and time, itemized lines with quantity, size, selected modifiers, per-line total, order total; **payment method** (Cash / GCash / Credit Card) separate from **payment status**; status tracker; **one next-action button only**. Long product names and modifier lists wrap or truncate cleanly without breaking the layout.

**Exact status workflow (forward-only):** **Pending → Paid → Preparing → Completed.**
- Pending: show **"Mark as Paid"** (after payment verification).
- Paid: show **"Start Preparing."**
- Preparing: show **"Mark as Completed."**
- Completed: **no further actions.**
- No skipping, no moving backward. No "Confirmed," "In Kitchen," "Ready," "Ready for Pickup/Dispatch," "Pending Verification," or similar statuses.
- Every status change uses a **confirmation dialog** with clear Cancel and Confirm buttons.

Selecting a payment method is not proof of payment. No invented transaction IDs or payment gateway claims.

**Excluded from staff screens:** kitchen-slip/ticket printing, dispatch or delivery controls, "ready for pickup" actions, loyalty/reward controls, customer-management features, unsupported payment-verification states, invented analytics or metrics, and any control that appears functional but is outside approved scope.

---

## 9. Admin Workspace (same design system, same shell as staff)

Admin screens use the same sidebar and top bar, same buttons, cards, tables, badges, and dialogs. Admin pages may be slightly denser (tables and forms) but stay beginner-friendly.

### 9.1 Admin Dashboard
Same layout family as the staff dashboard (order queue and selected-order details) with the admin sidebar. Include the shift area for the admin's own shift and a clear note that an Admin shift alone does not open customer ordering. Include a compact **"Shifts needing resolution"** alert panel linking to Duty Session Records when applicable. No invented KPI tiles or charts.

### 9.2 Order Oversight
Searchable and filterable table of all orders (reference, time, status badge, items, total, payment method, payment status) with a details panel. Same forward-only status rules and confirmation dialogs.

### 9.3 Menu Management: Categories
List showing exactly the eight approved categories in the approved order, with an item count per category (**[count]** placeholder), add/edit form, and a **delete-blocked state**: a category cannot be deleted while any menu items, including deactivated ones, remain assigned to it. The message explains that items must first be reassigned or removed.

### 9.4 Menu Management: Menu Items
- Table with search, category filter, status filter (Active / Inactive), and row actions (Edit, Deactivate/Activate).
- **Items are deactivated, not destructively removed, when historical records must be preserved.** Show a deactivate confirmation dialog explaining that past orders keep their original names and prices.
- **Add/Edit Menu Item form**, mirroring the owner's menu data fields (all placeholders):
  - Category (select from the eight categories)
  - Product name
  - Description (optional)
  - Base price (if applicable)
  - Availability (for example currently offered, temporarily unavailable, seasonal; plus an availability notes/schedule field)
  - **Size options** table: Size name, Price, Availability/notes; shows **"None"** when the product has no sizes
  - **Modifier groups** attached to the product (or **"None"**)
  - Support a "Pending" marker for details not yet confirmed
- No invented products or prices.

### 9.5 Menu Management: Modifier Groups & Options
Group form: group name, **Required / Optional**, **minimum selections**, **maximum selections**, **additional price applies per** (per ordered item unit, pending confirmation). Options table: option name, **fixed additional price (₱)**, availability/notes, with add/edit/deactivate. Do not assume a default option or selection count. Show which products use each group.

### 9.6 Staff & Account Management
Staff list (name, role, account status Active/Inactive) with add/edit and activate/deactivate. All names are **[Prototype user]** placeholders. No invented permissions beyond Staff vs Admin.

### 9.7 Duty Session Records (ADMIN ONLY)
Table of shift records: staff name, role, shift start, shift end, shift status (Open / Closed / **Requires resolution**). Filter by status and search by name. For stale or conflicting shifts, an **"Resolve Shift"** action opens a confirmation dialog stating the Admin's resolution. **Stale shifts are never closed automatically.** Mark all rows as prototype data. No invented attendance analytics or scheduling.

### 9.8 Sales & Reports (ADMIN ONLY)
- Revenue and best-seller reports are based **only on Completed orders**. State this on the page. Pending, Paid, and Preparing orders never count as completed sales.
- Simple date-range selector, summary cards (Completed-order revenue, number of Completed orders), a revenue table, and a best-sellers table. At most one simple chart, labeled **"Prototype data, not real records."**
- Peso formatting (₱). No tax or fee rules, no invented filters, no claim that figures come from real database records.

---

## 10. Money, Data, and Content Conventions
- Use ₱ formatting for actual or explicitly labeled prototype values.
- Historical order item and modifier names and prices must stay understandable in order details even if the menu item was later changed or deactivated (show them as recorded on the order).
- Never display fabricated customer names, phone numbers, staff identities, order references, transaction records, timestamps, or sales totals as real.
- No Maya, loyalty/rewards, delivery/pickup, promotions, tax, or service fees anywhere.

---

## 11. Beginner-Friendly and Implementation-Friendly Constraints
- Familiar navigation, descriptive button labels, consistent form layouts, readable tables and cards, simple dialogs with clear Cancel/Confirm, helpful validation, clear empty/unavailable states, predictable button placement.
- Avoid nested navigation, crowded dashboards, unnecessary charts, heavy animation, and decorative controls with no purpose.
- Must be feasible with standard HTML and Tailwind utility classes (grids, flex, cards, tables, forms, dialogs), with no paid UI framework or proprietary frontend architecture.
- Ensure text contrast, visible keyboard focus, clear labels, usable control sizes, and no color-only status or error indication.
- Do not generate backend logic or database operations. Do not assume any control works with PHP or MySQL.

---

## 12. Quality Checklist (verify before finishing)
- The real logo and mascot are preserved and recognizable, and the supplied drinks photo is used as the hero reference.
- All screens share the same color tokens, typography, spacing, buttons, cards, tables, dialogs, and status badges.
- Customer, staff, and admin areas look distinct yet clearly belong to one system.
- The digital menu and category management show exactly the eight approved categories in the approved order.
- No invented menu items, prices, sizes, modifiers, taxes, promotions, hours, addresses, or sales figures are shown as real.
- Payment methods are exactly Cash, GCash, Credit Card, with no implied payment processing.
- Order status is exactly Pending → Paid → Preparing → Completed, forward-only, with one next action and a confirmation dialog.
- Staff shift states reflect own-shift ownership and Admin resolution; ordering-unavailable state is present.
- **Staff cannot see or reach Sales & Reports or Duty Session Records; only Admin can.**
- Sales and best-seller reporting is explicitly based on Completed orders only.
- Kitchen-slip printing, dispatch/delivery, loyalty, and Maya are absent.
- Layouts work at 1440 × 900 and 1280 × 720, and adapt to tablet and mobile.

---

## 13. Execution and Reporting Instructions
1. Generate or update all screens in the Stitch project **ME Kape Cup UI**. Improve the existing Home, Digital Menu, and Staff Order Dashboard & POS screens instead of duplicating them, then add the remaining screens from the inventory in Section 2.
2. Establish the shared design system first (tokens, typography, components), then build customer screens, then login, then the staff and admin app shell and pages.
3. Do not modify local files, export files, the database, or MCP configuration.
4. If a screen cannot be retrieved or an edit is unavailable, stop that operation and report the limitation accurately. Do not claim a change unless it was confirmed.
5. Afterward, report **for each screen**: whether it was created or edited successfully; the main corrections or design decisions; unresolved content that needs real menu or business data; requirements Stitch could not implement; and whether the final screen was verified.

**Final priority:** approved project specification and roadmap first, approved decisions second, brand identity and design consistency throughout. When a visual idea conflicts with an approved business rule, the business rule wins.
