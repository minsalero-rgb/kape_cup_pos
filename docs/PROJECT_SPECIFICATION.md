# Project Title

**Web-Based POS and Customer Self-Ordering System for M&E Kape Cup**

---

## Document Set and Precedence

This specification is one of four connected project documents. When they differ, follow this order:

| Priority | Document | Role |
|---|---|---|
| 1 | `DECISIONS.md` | Approved decisions, owner-approved schema direction, and unresolved questions. **Wins on any business rule.** |
| 2 | `PROJECT_SPECIFICATION.md` (this file) | **What** the system does: features and constraints |
| 3 | `PROJECT_ROADMAP.md` | **In what order** it is built: phases, milestones, exit criteria |
| 4 | `STITCH_MASTER_PROMPT_FINAL.md` | **How the interface looks**: design prompt for Google Stitch (visual design only) |

Also referenced: `PROPOSED_DATABASE_SCHEMA.md` (proposed column-level design, **for review only**; not an approved SQL script and not authorization to create a database).

**Do not invent requirements.** Where `DECISIONS.md` lists an item as unresolved (actual menu items, prices, modifier groups, table labels, some report filters), use clearly labeled placeholders until it is resolved.

---

## Problem / Use Case

Customers at M&E Kape Cup face slow-moving queues during peak hours because a single staff member handles both line-taking and manual logbook recording. Because the shop is located along a high-noise highway corridor and spans a large seating area, calling out customer names for order pickups causes frequent noise disruption, misunderstandings, and delayed handoffs.

This system enables seated customers to open the web-based POS ordering website via a provided link or table QR code, browse the menu, and submit orders directly from their devices, while a real-time staff dashboard manages verification, payment, and status progression without vocal shouting.

---

## Technical Architecture & Core Specifications

### Tech Stack Constraints

- **Frontend:** HTML5 (semantic structure) + Tailwind CSS (via CDN/output) + Vanilla JavaScript (AJAX/Fetch API).
- **Backend:** Native PHP (procedural or OOP) handling session management, routing, and logic.
- **Persistence:** MySQL relational database targeting **MySQL 5.7.40** with **InnoDB**, `utf8mb4`, and foreign-key constraints. The initial order model uses nine normalized tables; receipt issue/removal auditing adds `receipt_records` without changing order or payment history.
- **Local environment:** Apache, PHP, and MySQL through WampServer.
- **Framework Constraint:** Strictly native tools. No external PHP or JavaScript frameworks (no Laravel, React, Vue, jQuery, etc.).
- **No payment gateway.** Staff verify payment manually.

### Approved Data Model Direction

The owner approved the table-structure direction only. The column-level design, final SQL, and database creation still require review and separate approval.

| # | Table | Purpose |
|---|---|---|
| 1 | `staff` | Staff and admin accounts |
| 2 | `staff_shifts` | Shift history (`login_timestamp`, nullable `logout_timestamp`) |
| 3 | `categories` | Configurable menu categories |
| 4 | `menu_items` | Current menu and base prices |
| 5 | `menu_modifier_groups` | Modifier groups attached to a menu item |
| 6 | `menu_modifier_options` | Fixed-price modifier choices |
| 7 | `orders` | Order header |
| 8 | `order_items` | Historical order lines with name/price snapshots |
| 9 | `order_item_modifiers` | Historical modifier selections with name/price snapshots |
| 10 | `receipt_records` | Issued OR-numbered receipt snapshots and Staff removal audit |

- No fixed table count overrides data integrity or required functionality.
- A `dining_tables` table is **excluded**. Dine-in table labels are validated against a **configuration allowlist**.
- JSON is not used for authoritative modifier configuration or order history.

---

## Core Specifications & Feature Breakdown

### Core Feature 1: Customer Self-Ordering & Real-Time Tracking

- **Table/Location Anchor:** Customers open the provided web link or scan a table QR code. **Dine-In** orders require a valid table number or QR table identifier (checked against the configured allowlist). **Take-Out** orders do not require a table label.
- **Menu Browsing & Customization:** Items are organized in the eight owner-confirmed categories, in this order:
  1. Coffee Series
  2. Matcha Series
  3. Fruit Milk
  4. Soda
  5. Fruit Shake
  6. Latte Series with Whipped Cream
  7. Main Courses (chicken rice meals and beef, pork, chicken, and fish dishes)
  8. Snacks

  Items support **predefined modifier options with fixed additional prices**, plus custom notes. Each modifier group is either required or optional and defines its permitted selection count. Each selected modifier's fixed price applies **per unit** of the ordered item. There is no advanced modifier rules engine. *Product names, prices, sizes, modifier groups, option labels, prices, and item applicability are pending until the actual menu is obtained.*
- **Cart & Order Summary:** Interactive cart showing itemized costs (base price, modifier charges, line totals), order type (Dine-In or Take-Out), customer name, total payment due, calculated change for cash payments, and selected payment method (**Cash, GCash, or Credit Card**). No discounts, taxes, or fees are included.
- **Ordering Availability:** Customer ordering is open only while a **Staff** account has an active shift. An Admin-only shift does not enable ordering. When no qualifying shift exists, the site shows **"Ordering currently closed"** and the backend rejects order submissions.
- **Persistent Status Tracking:** Each order has a saved, random eight-digit customer-facing tracking reference separate from its internal `order_id`. Customers can enter the reference after returning to the menu, refreshing, or opening a new session/device. The status screen (`Pending` → `Paid` → `Preparing` → `Completed`) polls the saved order status automatically.
- **Tracking Lookup:** References are generated server-side with cryptographic randomness, uniquely indexed, format-validated, and rate-limited. Eight digits are less resistant to guessing than a long bearer token; therefore public lookup returns only the status and reference. Existing internal-ID lookups continue to require the signed **HMAC tracking token** in `X-Order-Tracking-Token`; its private server secret is optional for new reference-based tracking.
- **Receipt issuance and audit:** Staff enter OR numbers already issued through the business's authorized process. The system does not generate OR numbers or claim legal/BIR compliance. Receipt issue time and an order/item snapshot are saved. Staff can remove a receipt from the Staff view with a required reason; the Admin audit retains the snapshot. Staff and Admin can filter receipts by tracking number, receipt issue date, and ordered item.

### Core Feature 2: Staff & Admin Live Queue & Order Verification

- **Real-Time Staff Dashboard:** Staff or Admin view a live order queue powered by Vanilla JavaScript `fetch()` polling every 3–5 seconds.
- **Order Review & Verification:** Staff review incoming `Pending` orders, manually confirm payment details with the customer according to the chosen method (Cash, GCash, or Credit Card), and advance the order. **The only permitted transitions** are `Pending → Paid`, `Paid → Preparing`, and `Preparing → Completed`. No cancellation, reversal, skipped steps, or additional statuses are approved.
- **Visual Status Handoff:** Updating the status on the staff dashboard immediately reflects on the customer's device, eliminating the need to shout across the highway-adjacent seating area.

### Core Feature 3: Staff Shift Management & Menu Management (CRUD)

- **Duty Session Audit:** Staff log in at the start of their shift and log out at the end. The system records `login_timestamp` and `logout_timestamp` in `staff_shifts`. When the system **accepts an order**, it associates the order with the qualifying active Staff shift and that shift's staff member (`shift_id`, `staff_id`), and preserves that attribution for the order's lifetime.
- **Shift Rules:**
  - Staff may resume **only their own** open shift.
  - A staff member cannot open another shift while an unresolved open shift exists.
  - If multiple open shifts exist, an **Admin must resolve** them before normal resumption.
  - Stale or abandoned shifts are **never closed automatically** at login. An Admin resolves exceptions.
  - Detailed resolution permissions and cash reconciliation are out of scope unless separately approved.
- **Admin Role-Based Access Control (RBAC):** A dedicated Admin module with full CRUD for categories, menu items, pricing, modifier groups and options (managed within the menu-management workflow), and instant "Out of Stock / Sold Out" toggles.
  - Menu items and modifier options referenced by history are **deactivated, not physically deleted**.
  - A category **cannot be deleted while any menu item remains assigned** to it, including deactivated items. Items must first be reassigned or removed.

### Core Feature 4: Daily Sales & Shift Analytics (Admin Module)

- **Shift & Daily Summary:** Native PHP/SQL aggregate reporting (`SUM()`, `COUNT()`, `GROUP BY`) showing:
  - Daily gross revenue
  - Breakdown by payment method (Cash vs. GCash vs. Credit Card)
  - Peak sales hours (grouped by order creation hour)
  - Best-selling menu items
  - Order volume processed per staff duty session
- **Completed-only rule:** Completed-sales revenue and best-seller rankings count **Completed orders only**. Pending and other unfinished orders are excluded from those reports.
- **Still to be decided:** the status filters for the payment-method breakdown, peak-hour metrics, and per-shift order volume/revenue. Report behavior for these is not final.

---

## Order Data Integrity Rules

- Historical orders preserve order-time item names, base prices, selected modifier names and prices, quantities, and line totals, so later menu changes never rewrite history.
- The server ignores browser-supplied prices and totals. It reloads current prices and validates all submitted IDs and selections.
- `unit_price_snapshot` = item base price + selected modifier prices. `line_total_snapshot` = unit price × quantity. Order total = sum of line totals.
- The order and all of its line and modifier snapshots are inserted in **one transaction**. Status changes use a conditional update to prevent duplicate or skipped transitions.
- The qualifying Staff shift is validated and locked inside the order-creation transaction, so it cannot close between the availability check and order insertion.
- MySQL 5.7 does not reliably enforce `CHECK` constraints, so positive quantity and price, valid order/payment/status values, modifier applicability, Dine-In table requirements, and cash sufficiency are enforced in PHP.
- Out of scope: discounts, taxes, refunds, inventory, suppliers, recipes, a separate status-transition history table, and any unapproved feature.

---

## Open Items (from `DECISIONS.md`)

- Review the proposed column-level schema before SQL is drafted.
- Define status filters for payment-method, peak-hour, and per-shift reports.
- Supply the production menu: product names, prices, modifier groups/options, prices, applicability, and selection counts.
- Supply the permitted table labels and count for the configuration allowlist.
- Define the minimum Admin procedure for resolving stale or conflicting shifts.
- Define development account provisioning, secret handling, and production seed-data separation.
- Record owner/staff confirmation of the four core features and roles.
