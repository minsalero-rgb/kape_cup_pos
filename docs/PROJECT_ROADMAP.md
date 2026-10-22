# Development Roadmap

**Project:** Web-Based POS and Customer Self-Ordering System for M&E Kape Cup
**Stack (strict):** HTML5 + Tailwind CSS (CDN/output) + Vanilla JavaScript (`fetch()`) · Native PHP · MySQL 5.7.40 (InnoDB, `utf8mb4`) · Apache via WampServer · No frameworks or libraries (no Laravel, React, Vue, jQuery, etc.)

---

## Document Set and Precedence

| Priority | Document                         | Role                                                                  |
| -------- | -------------------------------- | --------------------------------------------------------------------- |
| 1        | `DECISIONS.md`                   | Approved decisions and open questions. **Wins on any business rule.** |
| 2        | `PROJECT_SPECIFICATION.md`       | **What** to build                                                     |
| 3        | `PROJECT_ROADMAP.md` (this file) | **In what order** to build it                                         |
| 4        | `STITCH_MASTER_PROMPT_FINAL.md`  | **How it looks**: Stitch design prompt (visual only)                  |

Reference only: `PROPOSED_DATABASE_SCHEMA.md` (proposed columns, for review; not approved SQL).

**Rules that apply to every phase**

- Do not invent requirements. Where `DECISIONS.md` marks an item unresolved, use a labeled placeholder.
- The nine-table direction is approved. Column-level design, final SQL, and database creation still need separate approval.
- Development and test data must be clearly labeled sample data with development-only accounts. Never place real secrets in source-controlled files.

---

## Current Status (per `DECISIONS.md`)

- **Phase 1 (Planning) is not complete.** Still needed: ERD, endpoint/folder plan, prioritized backlog, and the remaining owner decisions (see Phase 1.6).
- **Schema compatibility test:** On 2026-10-10 the owner reported importing the proposed `database/schema.sql` into the disposable database `kape_cup_pos_schema_test_20261010` on the real MySQL 5.7.40 server. All nine tables were created, the foreign keys and indexes were confirmed, and the generated-column rule that blocks a second open shift per staff member worked. This verified **only** the schema import and that uniqueness behavior. It does **not** verify application-level PHP rules, is **not** production database setup, and does **not** mark any phase complete.
- No Phase 3 environment completion is asserted.

---

## Roadmap at a Glance

| Phase | Focus               | Suggested Duration\* | Key Deliverable                                         |
| ----- | ------------------- | -------------------- | ------------------------------------------------------- |
| 1     | Planning            | 3–4 days             | Finalized scope, schema review, routes, status rules    |
| 2     | Interface Design    | 5–7 days             | Stitch designs + static HTML/Tailwind pages             |
| 3     | Environment Setup   | 1–2 days             | Working WampServer stack + skeleton + approved database |
| 4     | Backend Preparation | 10–14 days           | Auth, shift, menu/modifier, order, and report endpoints |
| 5     | Integration         | 7–10 days            | Pages wired to live APIs with polling                   |
| 6     | Testing             | 5–7 days             | Test report, fixed defects, security pass               |
| 7     | Finalization        | 3–5 days             | Deployed system, QR codes, documentation, handover      |

\*Assumes one developer. Adjust to your schedule.

```
Planning -> Interface Design -> Environment Setup -> Backend Preparation -> Integration -> Testing -> Finalization
```

---

## Phase 1: Planning

**Goal:** Lock down what will be built so later phases do not stall on open questions.

### 1.1 Requirements Confirmation

- [ ] Walk through the four core features with the owner and staff; record their confirmation (still open in `DECISIONS.md`).
- [ ] Roles: **Customer** (no login), **Staff** (login, shift-bound), **Admin** (login, full access including menu CRUD, shift resolution, and reports).
- [ ] The eight owner-confirmed categories, in order: Coffee Series, Matcha Series, Fruit Milk, Soda, Fruit Shake, Latte Series with Whipped Cream, Main Courses (chicken rice meals and beef, pork, chicken, and fish dishes), Snacks.
- [ ] Obtain the actual menu: product names, prices, modifier groups/options, fixed prices, applicability, and selection counts. **Do not invent these.**
- [ ] Obtain the permitted dine-in table labels and count for the configuration allowlist.

### 1.2 Order Lifecycle and Business Rules (approved)

- [ ] Statuses: exactly `Pending`, `Paid`, `Preparing`, `Completed`. Allowed transitions: `Pending → Paid`, `Paid → Preparing`, `Preparing → Completed`. **No cancellation, reversal, or extra statuses.**
- [ ] Payment methods: Cash, GCash, Credit Card. Staff verify manually; no gateway.
- [ ] Cash: customer enters the tendered amount; the server calculates change and rejects insufficient cash.
- [ ] Order type: Dine-In requires a valid table number or QR table identifier (checked against the config allowlist); Take-Out needs no label.
- [ ] Ordering availability: only an active **Staff** shift enables ordering; an Admin-only shift does not. With no qualifying shift, show "Ordering currently closed" and reject submissions on the backend.
- [ ] Shift rules: staff resume only their own open shift; no new shift while an unresolved one exists; multiple open shifts require Admin resolution; **never auto-close stale shifts**.
- [ ] Attribution: when an order is accepted, record the qualifying active Staff shift and its staff member on the order; this never changes.
- [ ] Modifiers: predefined options with fixed prices; each price applies once per item unit; groups are required or optional with permitted selection counts; no advanced rules engine.
- [ ] Out of scope: discounts, taxes, refunds, inventory, suppliers, recipes, status-history table.

### 1.3 Database Design (nine-table direction approved; columns under review)

Target: MySQL 5.7.40, InnoDB, `utf8mb4`, `DECIMAL` for money.

| Table                   | Purpose                   | Key proposed fields                                                                                                                                                                                                            |
| ----------------------- | ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `staff`                 | Staff/admin accounts      | `staff_id` PK, `full_name`, unique `username`, `password_hash`, `role` (staff/admin), `is_active`                                                                                                                              |
| `staff_shifts`          | Shift history             | `shift_id` PK, `staff_id` FK, `login_timestamp`, nullable `logout_timestamp`; generated unique key to allow only one open shift per staff                                                                                      |
| `categories`            | Menu categories           | `category_id` PK, unique `name`, `sort_order`                                                                                                                                                                                  |
| `menu_items`            | Current menu              | `item_id` PK, `category_id` FK, `name`, `description`, `base_price`, `is_sold_out`, `is_active`                                                                                                                                |
| `menu_modifier_groups`  | Groups per item           | `modifier_group_id` PK, `item_id` FK, name/key, required or optional, min/max selections, sort order, active flag                                                                                                              |
| `menu_modifier_options` | Fixed-price choices       | `modifier_option_id` PK, `modifier_group_id` FK, name/key, `price_delta`, active flag                                                                                                                                          |
| `orders`                | Order header              | `order_id` PK, customer name, nullable table label, order type, payment method, `total_amount`, nullable cash tender/change, status, `created_at`, `updated_at`, `paid_at`, `completed_at`, required `staff_id` and `shift_id` |
| `order_items`           | Historical lines          | `order_item_id` PK, `order_id` FK, `item_id` FK, name and base-price snapshots, quantity, `unit_price_snapshot`, `line_total_snapshot`, notes                                                                                  |
| `order_item_modifiers`  | Historical modifier picks | `order_item_modifier_id` PK, `order_item_id` FK, `modifier_option_id` FK (`ON DELETE RESTRICT`), group/option name and price snapshots                                                                                         |

- [ ] Review `PROPOSED_DATABASE_SCHEMA.md` and approve or amend the columns before any SQL is finalized.
- [ ] `dining_tables` is excluded; use a config allowlist for table labels.
- [ ] Composite `(shift_id, staff_id)` foreign key (or transactional check) so an order's shift belongs to its assigned staff.
- [ ] `RESTRICT` deletes where history matters; deactivate menu items, options, and accounts instead of deleting them.
- [ ] Indexes: `orders(status, created_at)`, `orders(shift_id)`, `order_items(order_id)`, plus modifier lookups.
- [ ] No `CHECK` reliance (MySQL 5.7); enforce value rules in PHP.

### 1.4 Route and Endpoint Plan

**Customer pages:** `index.php` (home), `menu.php` (digital menu + cart, accepts `?table=`), `status.php` (order tracking)
**Staff/Admin pages:** `staff/login.php`, `staff/dashboard.php`, `admin/menu.php`, `admin/categories.php`, `admin/shifts.php`, `admin/reports.php`

| Endpoint                             | Method   | Role        | Purpose                                                                                                                                       |
| ------------------------------------ | -------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `api/menu.php`                       | GET      | Public      | Menu by category with modifiers and an `ordering_open` flag                                                                                   |
| `api/submit_order.php`               | POST     | Public      | Validate, price server-side, create the order, return the saved customer tracking reference                                                   |
| `api/ordering_status.php?reference=` | GET      | Public      | Minimal order status by random eight-digit customer reference with failed-lookup throttling; legacy ID + HMAC-token requests remain supported |
| `api/auth_login.php`                 | POST     | Staff/Admin | Login and open or resume own shift                                                                                                            |
| `api/auth_logout.php`                | POST     | Staff/Admin | Close shift and end the session                                                                                                               |
| `api/queue.php`                      | GET      | Staff/Admin | Live order queue                                                                                                                              |
| `api/update_order_status.php`        | POST     | Staff/Admin | Advance one allowed step                                                                                                                      |
| `api/admin_menu.php`                 | GET/POST | Admin       | Item CRUD, sold-out toggle, modifier groups/options                                                                                           |
| `api/admin_categories.php`           | GET/POST | Admin       | Category CRUD with deletion guard                                                                                                             |
| `api/admin_shifts.php`               | GET/POST | Admin       | List open/stale shifts; resolution action (procedure still to be defined)                                                                     |
| `api/reports.php`                    | GET      | Admin       | Aggregated analytics                                                                                                                          |

### 1.5 Report Definitions

- [ ] Revenue and best sellers: **Completed orders only** (approved).
- [ ] **Decide** status filters for payment-method breakdown, peak-hour metrics (grouped by order creation hour), and per-shift order volume/revenue.

### 1.6 Planning Deliverables and Open Owner Decisions

- [ ] ERD diagram
- [ ] Endpoint list and folder structure (above, once confirmed)
- [ ] Prioritized backlog (must-have vs. nice-to-have)
- [ ] Column-level schema review (`PROPOSED_DATABASE_SCHEMA.md`)
- [ ] Report status filters
- [ ] Production menu, modifiers, prices, and applicability
- [ ] Table labels and count
- [ ] Minimum Admin procedure for stale/conflicting shifts (no cash reconciliation)
- [ ] Development account provisioning, secret handling, and production seed separation
- [ ] Owner/staff confirmation of the four core features and roles

**Exit criteria:** Open decisions resolved or explicitly deferred with placeholders; schema columns approved; ERD, endpoint plan, and backlog written.

---

## Phase 2: Interface Design

**Goal:** Design and build every screen as static HTML + Tailwind before backend logic exists. Visual rules come from `STITCH_MASTER_PROMPT_FINAL.md`.

### 2.1 Design Principles

- Desktop/laptop-first (about 1440 × 900, also 1280 × 720), responsive down to tablet and phone. Customers use phones at tables, so the customer pages must work especially well on mobile.
- The **original M&E Kape Cup logo is locked**: use the supplied asset exactly; never redraw or alter it.
- Shared palette: Espresso Brown `#3B1F14`, Cream Beige `#F4E6C8`, Caramel Gold `#D9B77E`, Matcha Green `#78945B`, Warm White `#FFFCF6`, Dark Text `#29221D`, Muted Text `#74685E`, plus an accessible red for errors.
- Status is never shown by color alone; always pair it with a text label.
- Semantic HTML5, labeled inputs, visible keyboard focus.
- Use placeholders such as "[Product name]" and "[Price pending confirmation]" until real menu data exists.

### 2.2 Screens

**Designed in the current Stitch task (three screens):**

- [ ] Home
- [ ] Digital Menu (search, eight category filters, product grid, customization modal, cart, closed-ordering state, checkout presentation)
- [ ] Staff Order Dashboard & POS (queue, order details, status action, shift states)

**Still to design (follow-up Stitch prompts):**

- [ ] Customer checkout confirmation and **order status tracking** (Pending → Paid → Preparing → Completed)
- [ ] Staff login and shift-start/resume/end states
- [ ] Admin: menu management with modifier groups and options, category management, shift oversight and resolution, sales reports

### 2.3 Required UI Details (from `DECISIONS.md`)

- [ ] Checkout: order type (Dine-In/Take-Out), table label (Dine-In only), customer name, notes, payment method (Cash/GCash/Credit Card), cash tendered with calculated change.
- [ ] Modifier modal: required vs. optional groups, selection-count rules, fixed prices shown, and a validation message.
- [ ] Cart line total shown as (base price + selected modifiers) × quantity.
- [ ] Closed state text: **"Ordering currently closed"**; the submit action is disabled.
- [ ] Staff queue shows order reference, time, status, order type and table label, customer name, item summary, total, and payment method; details show modifiers, notes, and cash change.
- [ ] Only the next permitted status action is shown; confirmation dialogs guard each change; a Completed order shows no action.
- [ ] Shift states: no active shift, own open shift, and a conflict needing Admin resolution.
- [ ] Not included: Maya, loyalty, kitchen-slip printing, delivery/pickup, cancellation, discounts, taxes.

### 2.4 Static Prototypes

- [ ] Export or rebuild each screen as static `.html` with Tailwind classes and clearly labeled fictional prototype data.
- [ ] Use `<template>` elements for repeating items (menu cards, queue cards, cart rows, modifier options).
- [ ] Include states: empty cart, loading, error, sold-out, closed ordering, empty queue, no active shift.
- [ ] Review with the owner and one staff member; once approved, designs are **locked**.

**Exit criteria:** All screens exist as responsive static pages and are approved.

---

## Phase 3: Environment Setup

**Goal:** A reproducible local environment and a clean project skeleton. Database creation requires separate approval of the schema.

### 3.1 Local Stack

- [ ] WampServer with PHP 8.x, Apache, and MySQL **5.7.40**; enable `pdo_mysql`, `mbstring`, and sessions. No Composer or npm packages.
- [ ] Git repository with a `.gitignore` for config and secrets.
- [ ] Set the HMAC secret as a **private Apache/PHP environment variable** (at least 32 bytes). Never put it in project files or the database.

### 3.2 Project Skeleton

```
mekapecup/
├── index.php  menu.php  status.php
├── staff/    login.php  dashboard.php
├── admin/    menu.php  categories.php  shifts.php  reports.php
├── api/      (JSON endpoints, see Phase 1.4)
├── includes/ config.php  db.php  auth.php  helpers.php  layout/
├── assets/   js/  css/  img/   (original logo asset, unmodified)
├── database/ schema.sql  seed.sql
├── docs/     DECISIONS.md and related documents
└── README.md
```

`config.php` holds the table-label allowlist and database settings but **no** secrets committed to source control.

### 3.3 Database Initialization

- [ ] Only after the schema columns and SQL are approved, create the project database (distinct from the disposable test database).
- [ ] `schema.sql`: InnoDB, `utf8mb4`, all keys and constraints, the generated-column rule for one open shift per staff.
- [ ] `seed.sql`: **development-only** labeled sample categories and menu items and development-only accounts (hashed with `password_hash()`). Real credentials and production menu data are supplied and handled separately.
- [ ] Dedicated MySQL user with limited privileges, not `root`.
- [ ] PDO with exceptions enabled and emulated prepares off.

### 3.4 Smoke Test

- [ ] Use the existing application or a protected local/staging test harness with a disposable database to verify the browser-to-PHP-to-MySQL round trip. Do not create temporary public PHP pages or endpoints, and never run smoke tests against production data.

**Exit criteria:** Stack runs, approved schema loads cleanly, and the browser-to-PHP-to-MySQL round trip works.

---

## Phase 4: Backend Preparation

**Goal:** Build and verify every endpoint and business rule before touching the UI.

### 4.1 Core Utilities

- [ ] `db.php` (PDO), `helpers.php` (`json_response`, validators, error handling that hides internals), `auth.php` (`require_login`, `require_role`, shift lookups).
- [ ] Session hardening (`session_regenerate_id(true)`, `HttpOnly`, `SameSite`, idle timeout) and CSRF tokens for staff/admin POSTs.
- [ ] Preserve the HMAC helper for legacy status URLs; compare tokens with `hash_equals()`. New customer tracking uses a persisted random reference.

### 4.2 Authentication and Shifts

- [ ] Login with `password_verify()`; start the session.
- [ ] Shift logic at login:
  - No open shift: create one (`login_timestamp`).
  - Own open shift: **resume** it.
  - Another user's open shift exists, or the user already has an unresolved or multiple open shifts: block normal resumption and require **Admin resolution**.
  - **Never auto-close** stale shifts.
- [ ] Logout sets `logout_timestamp` and destroys the session.
- [ ] Role guards: Staff use the queue; Admin also manages menu, shifts, and reports.
- [ ] `admin_shifts.php`: list open/stale shifts; implement only the resolution procedure approved in Phase 1 (no cash reconciliation).

### 4.3 Menu, Category, and Modifier CRUD (Admin)

- [ ] Public `menu.php`: categories (in `sort_order`), active items, sold-out flags, active modifier groups/options, and `ordering_open`.
- [ ] Admin CRUD for categories, menu items, modifier groups, and options; instant sold-out toggle.
- [ ] Deactivate (not delete) items and options referenced by history.
- [ ] **Block category deletion while any item, active or deactivated, remains assigned.**
- [ ] Validate positive prices, required names, valid IDs, and group min/max rules.

### 4.4 Order Creation

- [ ] `submit_order.php` accepts customer name, order type, table label (Dine-In), payment method, cash tendered, and line items (item ID, quantity, selected modifier option IDs, notes).
- [ ] In **one transaction**: find and **lock** the qualifying active Staff shift (reject with "Ordering currently closed" if none); validate the Dine-In table label against the config allowlist; reload prices from the database; validate modifier applicability and group selection rules; compute `unit_price_snapshot` = base + modifiers, `line_total_snapshot` = unit × quantity, and the order total; check cash sufficiency and compute change; insert `orders`, `order_items`, and `order_item_modifiers` with snapshots; record `shift_id` and `staff_id`.
- [ ] Ignore all browser-supplied prices and totals.
- [ ] Return the saved customer tracking reference, verified total, and payment method; keep internal IDs private to staff/internal operations. Preserve idempotency and submission limits.

### 4.5 Order Status and Queue

- [ ] `ordering_status.php`: lookup by customer reference with bounded failure rate and minimal status data; preserve protected order-ID/HMAC compatibility.
- [ ] `queue.php`: active orders, oldest first, for Staff and Admin; supports a `since` parameter.
- [ ] `update_order_status.php`: allowed transitions only (`Pending → Paid → Preparing → Completed`), via conditional `UPDATE ... WHERE status = :expected`; Staff require exactly one active Staff shift; Admin updates preserve original order attribution; no cancel or reverse.

### 4.6 Analytics (SQL aggregates)

- [ ] Completed-sales revenue and best sellers from **Completed** orders only.
- [ ] Payment-method breakdown, peak hours (`GROUP BY HOUR(created_at)`), and per-shift order volume: apply the status filters decided in Phase 1.
- [ ] `reports.php` with a date parameter (default today).

### 4.7 Verification

- [ ] Test every endpoint with success and failure cases (browser, `curl`, or a temporary test script) before moving on.

**Exit criteria:** All endpoints return correct JSON, enforce roles and rules, and persist data correctly.

---

## Phase 5: Integration

**Goal:** Connect the approved Phase 2 pages to the Phase 4 endpoints with Vanilla JS, without altering the approved designs.

### 5.1 Shared Foundation

- [ ] `fetchJSON()` wrapper (errors, non-200, timeouts, redirect to login on 401).
- [ ] Safe rendering (`textContent`, never raw `innerHTML` for user text).

### 5.2 Customer Ordering (`customer.js`)

- [ ] Read `?table=`; load the menu and `ordering_open`; show "Ordering currently closed" and disable submission when closed.
- [ ] Render categories, items, and modifier groups from `<template>`s; enforce required/optional rules in the UI (the server remains the authority).
- [ ] Cart with live totals; checkout with order type, table label (Dine-In), name, notes, payment method, and cash change.
- [ ] Submit via `fetch()`; display the saved tracking reference and a status link; do not rely on transient browser storage as the recovery path.

### 5.3 Customer Tracking (`status.js`)

- [ ] Let customers enter a reference from any session, then poll `ordering_status.php` every five seconds; retain token headers for legacy ID links.
- [ ] Update the status stepper live; pause when the tab is hidden and refresh on return; stop at Completed; clear `localStorage` afterward.
- [ ] Handle "no active order" and invalid-token states.

### 5.4 Staff Dashboard (`dashboard.js`)

- [ ] Poll `queue.php` every 3–5 seconds; highlight new Pending orders.
- [ ] Show only the next permitted action with a confirmation dialog; display payment method, total, and cash change.
- [ ] Show shift state (none / own open / needs Admin resolution) and a Logout control; show a "connection lost, retrying" banner.

### 5.5 Admin Module (`admin.js`)

- [ ] Menu, modifier, and category management with the sold-out toggle and deletion guard messages.
- [ ] Shift oversight and resolution (per the approved procedure).
- [ ] Reports with summary cards and plain HTML/CSS or inline-SVG bars (no chart library).

### 5.6 End-to-End Check

- [ ] Customer orders → staff sees it within about 5 seconds → staff advances it → customer screen updates within about 5 seconds, on two real devices.

**Exit criteria:** The full workflow runs across two devices with no manual reloads.

---

## Phase 6: Testing

**Goal:** Prove correctness, security, and reliability under café conditions.

### 6.1 Functional Tests

| Area         | Test                                                                                                                                      |
| ------------ | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Menu         | Eight categories appear in order; sold-out items cannot be ordered                                                                        |
| Modifiers    | Required/optional rules enforced; fixed prices applied per unit; snapshots saved                                                          |
| Cart         | Line totals = (base + modifiers) × quantity; order total correct                                                                          |
| Order type   | Dine-In without a valid table label rejected; Take-Out accepted without one                                                               |
| Cash         | Change correct; insufficient cash rejected                                                                                                |
| Availability | With no Staff shift (including an Admin-only shift), UI shows "Ordering currently closed" and the backend rejects submission              |
| Status flow  | Only the three allowed transitions work; skips, reversals, and cancellation rejected                                                      |
| Tracking     | Status lookup fails without a valid token; token travels in the header; rotating the secret invalidates old tokens                        |
| Shifts       | Resume only own shift; no second shift; multiple open shifts require Admin; no auto-close; orders carry the correct `shift_id`/`staff_id` |
| RBAC         | Staff cannot reach admin pages or APIs                                                                                                    |
| Menu CRUD    | Deactivation preserves history; category deletion blocked while any item remains                                                          |
| History      | Editing a price or name does not change past orders                                                                                       |
| Reports      | Revenue and best sellers count Completed orders only; other metrics match the decided filters                                             |

### 6.2 Real-Time Behavior

- [ ] Measure polling latency (target 5 seconds or less); test several customer devices plus two staff devices; test Wi-Fi drop and phone sleep/wake; test two staff advancing the same order simultaneously.
- [ ] Test the race between a shift closing and an order being submitted (the shift lock must hold).

### 6.3 Security Checklist

- [ ] Prepared statements everywhere (injection strings tested).
- [ ] XSS payloads in customer name and notes are escaped.
- [ ] Price tampering is ignored (server-side calculation).
- [ ] Password hashing, session fixation, idle timeout, and CSRF checked.
- [ ] Direct access to protected pages/APIs returns 401/403.
- [ ] Secrets are absent from files and the database; errors never leak to the browser.

### 6.4 Compatibility and Usability

- [ ] Android browsers, iOS Safari, desktop Chrome/Edge/Firefox; slow mobile data.
- [ ] Usability session with real customers and staff, including a mock rush.

### 6.5 Defect Handling

- [ ] Log, prioritize (critical/major/minor), fix, and retest. Re-run the full order loop after each critical fix.

**Exit criteria:** No critical or major defects open; security checklist passed; mock rush completed.

---

## Phase 7: Finalization

**Goal:** Deploy, hand over, and prepare for real use.

### 7.1 Deployment

- [ ] Choose hosting (PHP + MySQL) or an on-site server; set production config: errors hidden and logged, HTTPS, secure cookie flags.
- [ ] Set the production HMAC secret as a private environment variable.
- [ ] Load the **production** menu and accounts, supplied separately from development seed data; replace all development passwords.
- [ ] Set up daily database backups and test a restore.

### 7.2 QR Codes and Table Setup

- [ ] Generate one QR code per permitted table label pointing to `https://<domain>/menu.php?table=<label>` (a one-time offline task, so no library is added). Labels must match the config allowlist.
- [ ] Print table tents and a counter sign with the shared link.

### 7.3 Documentation

- [ ] `README.md` (setup, configuration, deployment); one-page staff guide; one-page admin guide (including shift resolution); maintenance notes (backup/restore, password reset, adding a table label, rotating the secret and its effect on tracking tokens).

### 7.4 Training and Soft Launch

- [ ] Train staff and admin; soft launch in a quiet period with the logbook as fallback; gather feedback for 1–2 weeks; full launch.

### 7.5 Post-Launch Backlog (not approved; needs owner decision)

Items such as order cancellation, receipts, CSV export, item photos, or inventory are **not approved** and are listed only as possible future requests.

**Exit criteria:** System live, staff trained, documentation delivered, backups running.

---

## Risk Register

| Risk                                     | Impact                                 | Mitigation                                                                 |
| ---------------------------------------- | -------------------------------------- | -------------------------------------------------------------------------- |
| Unstable café Wi-Fi/mobile data          | Customers can't order or track         | Lightweight pages; retry logic; logbook fallback                           |
| Polling load on shared hosting           | Slow responses at peak                 | Indexed queries; `since` parameter; 4–5 second interval; pause hidden tabs |
| Fake or prank orders (no customer login) | Queue clutter                          | Staff verify before Paid; rate limiting; table-label validation            |
| Staff forget to log out                  | Stale open shift blocks ordering logic | Idle timeout; **Admin resolves**; never auto-close                         |
| `localStorage` cleared                   | Customer loses tracking                | Show the order reference on confirmation; token held with the order        |
| Tracking secret rotated                  | Old tokens stop working                | Rotate only deliberately; document the effect                              |
| Menu data not yet supplied               | Cannot finalize seed data              | Labeled placeholders; development-only sample data                         |
| Scope creep                              | Delays                                 | Only approved features; unapproved ideas stay in the backlog               |

---

## Milestone Checklist

- [ ] **M1:** Open decisions resolved or deferred; schema columns approved; ERD, endpoint plan, and backlog done (end of Phase 1)
- [ ] **M2:** All screens designed, built as static pages, and approved (end of Phase 2)
- [ ] **M3:** Environment and approved database running (end of Phase 3)
- [ ] **M4:** All backend endpoints verified (end of Phase 4)
- [ ] **M5:** End-to-end order loop works across devices (end of Phase 5)
- [ ] **M6:** Testing and security pass complete (end of Phase 6)
- [ ] **M7:** Live deployment and staff trained (end of Phase 7)
