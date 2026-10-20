# Project Decisions and Open Questions

**Project:** Web-Based POS and Customer Self-Ordering System for M&E Kape Cup

This document separates approved requirements from the owner-approved schema direction, proposed column-level design, and unresolved details. The roadmap remains the implementation sequence. The table-structure direction is approved; the proposed column-level schema is for review and is not an approved SQL script or authorization to create a database.

## Approved decisions

### Database and platform

- Target MySQL 5.7.40 with InnoDB, `utf8mb4`, and relational foreign keys.
- Preserve configurable categories and separate order headers from historical order-item lines.
- The owner has approved the nine-table relational design direction: `staff`, `staff_shifts`, `categories`, `menu_items`, `menu_modifier_groups`, `menu_modifier_options`, `orders`, `order_items`, and `order_item_modifiers`. The optional `dining_tables` table is excluded; validate dine-in table labels using a configuration allowlist.
- Approval covers the table-structure direction only. It does not approve the final column-level design or SQL script, and does not authorize database creation or initialization. Review the proposed detail in `PROPOSED_DATABASE_SCHEMA.md` before SQL is prepared.
- No fixed table count overrides data integrity or required functionality. The current nine-table proposal must support the approved features without adding unnecessary tables.

### Ordering and status

- Dine-in orders require a valid table number or QR table identifier; take-out orders do not require a table label.
- When no active shift qualifies under the owner-approved availability policy, display **“Ordering currently closed”** and reject order submissions on the backend.
- The exact order statuses are `Pending`, `Paid`, `Preparing`, and `Completed`. Permitted transitions are only `Pending` → `Paid`, `Paid` → `Preparing`, and `Preparing` → `Completed`; no cancellation, reversal, or additional statuses are approved.
- Public order-status lookup requires the order ID and a signed HMAC tracking token returned when the order is created. Send the token in the `X-Order-Tracking-Token` header, not in the URL; the server secret must be a private Apache/PHP environment variable of at least 32 bytes and must never be stored in project files or the database. Rotating the secret invalidates previously issued tracking tokens.
- Staff may resume only their own open shift. Do not automatically close stale or abandoned shifts at login. An Admin resolves exceptions.
- Staff manually verify payment. No payment gateway is included.

### Menu, modifiers, and history

- The owner confirmed these menu categories: Coffee Series, Matcha Series, Fruit Milk, Soda, Fruit Shake, Latte Series with Whipped Cream, Main Courses, and Snacks. Main Courses includes chicken rice meals and beef, pork, chicken, and fish dishes. Product names, prices, sizes, and modifier details remain pending until the actual menu is obtained.
- Support full category management and prevent category deletion while menu items remain assigned; affected items must first be reassigned or removed.
- Deactivate menu items rather than destructively deleting records referenced by historical orders.
- Use predefined modifier options with fixed additional prices. Exact groups, option labels, prices, and applicability have not been finalized.
- Preserve order-time item names, base prices, selected modifier names and prices, quantities, and line totals so later menu changes do not rewrite order history.
- Do not add discounts, taxes, refunds, inventory, suppliers, recipes, or other unapproved features.

### Development data

- Development and test data may use clearly labeled sample menu items and development-only accounts.
- Do not treat sample data or credentials as production data. Keep development seed data and credentials isolated from production; never put real secrets in source-controlled seed files.

## Owner-approved initial-design decisions — final SQL and database approval pending

The owner has adopted the following rules for the initial design. The schema direction is approved, but its detailed columns, final SQL, and database creation remain subject to review and separate approval. The four statuses, no-cancellation rule, MySQL target, and dine-in-only table-label requirement remain unchanged.

### Modifier behavior

- Apply each selected modifier's fixed additional price per unit of the ordered menu item.
- Support simple required or optional selection rules with predefined groups and options. Do not build an advanced modifier rules engine.
- Manage modifier groups and options within the existing menu-management workflow.
- Do not assume actual modifier names, prices, or menu applicability. Preserve the selected modifier name and price on each historical order item.
- Each group must define whether selection is required or optional and its permitted selection count. Exact group configuration, option names, prices, and item applicability remain unfinalized.

### Ordering availability and shifts

- Only an active shift belonging to a Staff account enables customer ordering; an Admin-only shift does not.
- Do not allow a staff member to open another shift while an unresolved open shift exists. If multiple open shifts already exist, require Admin resolution before normal resumption.
- Admin review and resolution are required for stale or conflicting shifts. Detailed resolution permissions and cash reconciliation procedures remain out of scope unless separately approved.
- At the moment the system accepts an order, associate it with the qualifying active Staff shift and that shift's staff member; preserve that attribution for the order's lifetime.

### Table labels, reports, and history

- Initially validate dine-in table labels against a configuration-based allowlist. Do not add a `dining_tables` registry or table-registry management features unless later requirements justify them. Take-out orders do not require a label.
- Count completed-sales revenue and best-seller rankings from `Completed` orders only; exclude Pending and all other unfinished orders from those reports.
- Exclude Pending and other unfinished orders from any report explicitly defined as completed sales. This does not establish status filters for every required report.
- Initially retain current order status and workflow/report timestamps; do not add a separate status-transition history table unless an approved audit requirement justifies it.
- A deactivated item that remains assigned to a category continues to block category deletion. Reassign it before deleting the category; preserve historical item references and snapshots.

### Remaining reporting clarification

- The required reports include payment-method breakdown, peak sales hours grouped by order creation hour, and order volume per staff shift. The adopted Completed-only rule applies to completed-sales revenue and best sellers; the status filters for payment-method breakdown, peak-hour metrics, and per-shift order volume/revenue still need to be stated before report behavior is finalized.

## Proposed column-level relational schema

The nine-table direction is owner-approved; the detailed column-level design is proposed, not final. It uses MySQL 5.7.40-compatible types and features. All tables use InnoDB and `utf8mb4`; monetary values use `DECIMAL`, not floating-point types. See `PROPOSED_DATABASE_SCHEMA.md` for columns, nullability, keys, indexes, defaults, relationships, and integrity notes. Final SQL and database creation require separate approval.

| Table | Purpose and proposed important fields |
|---|---|
| `staff` | Staff/admin accounts: `staff_id BIGINT UNSIGNED` PK, `full_name`, unique `username`, `password_hash`, `role` (`staff`/`admin`), and `is_active`. Deactivate rather than remove accounts needed by history. |
| `staff_shifts` | Shift history: `shift_id BIGINT UNSIGNED` PK, `staff_id` FK, `login_timestamp`, nullable `logout_timestamp`. Resume only a shift owned by the authenticated staff member. A one-open-shift-per-staff integrity rule is recommended. |
| `categories` | Configurable menu categories: `category_id` PK, unique `name`, and `sort_order`. |
| `menu_items` | Current menu: `item_id` PK, `category_id` FK, `name`, `description`, `base_price DECIMAL(10,2)`, `is_sold_out`, and `is_active`. Deactivation preserves historical links. |
| `menu_modifier_groups` | Modifier groups attached to a menu item: `modifier_group_id` PK, `item_id` FK, group key/name, required/optional setting, permitted minimum/maximum selection counts, sort order, and active flag. Exact groups and count values remain unresolved; no actual modifier names or applicability are assumed. |
| `menu_modifier_options` | Fixed-price choices: `modifier_option_id` PK, `modifier_group_id` FK, option key/name, `price_delta DECIMAL(10,2)`, and active flag. |
| `orders` | Order header: `order_id` PK, customer name, nullable table label for take-out, order type, payment method, `total_amount DECIMAL(10,2)`, nullable cash tender/change, four-state status, `created_at`, `updated_at`, `paid_at`, `completed_at`, and required `staff_id`/`shift_id` FKs recorded when the system accepts the order under an active Staff shift. |
| `order_items` | Historical line: `order_item_id` PK, `order_id` FK, `item_id` FK, item-name and base-price snapshots, quantity, `unit_price_snapshot DECIMAL(10,2)`, `line_total_snapshot DECIMAL(10,2)`, and notes. |
| `order_item_modifiers` | Historical modifier selections: `order_item_modifier_id` PK, `order_item_id` FK, required `modifier_option_id` FK with `ON DELETE RESTRICT`, plus group/option name and fixed-price snapshots. Deactivate referenced options rather than physically deleting them; snapshots remain authoritative for historical receipts and reports. |

This core proposal has **nine tables**: the original six plus three normalized modifier tables. The modifier group belongs to a menu item, avoiding a separate many-to-many mapping table while allowing each item to have its own modifier applicability. Each option belongs to one group. Each order-item modifier row preserves the selected label and price independently of later menu changes.

## Disposable MySQL 5.7.40 schema verification — completed

On 2026-10-10, the owner reported completing a controlled compatibility test of the proposed `database/schema.sql` on the actual MySQL 5.7.40 server through WampServer/phpMyAdmin. The script was imported only into the newly created disposable database `kape_cup_pos_schema_test_20261010`; no production or project database was selected.

- The import succeeded and all nine expected tables were present.
- Read-only metadata checks found nine foreign-key constraints (ten column mappings, including the composite order-to-shift-owner FK), with `RESTRICT` delete and update rules. Index checks confirmed the generated open-shift unique key, its `(shift_id, staff_id)` referenced unique key, the matching `orders` child index, and the unique modifier-selection key.
- `order_item_modifiers.modifier_option_id` was reported as `BIGINT UNSIGNED`, non-nullable, and protected by `ON DELETE RESTRICT`.
- The `staff_shifts.open_staff_id` column was reported as a stored generated column. A second simultaneous open shift for the same staff member was rejected with duplicate-key error `#1062` on `uq_staff_shifts_open_staff_id`. After closing the first shift, two additional closed shifts were accepted; counts showed zero open and three closed shifts.
- The temporary test shifts and dummy staff row were deleted. A final read-only check returned no row for the test username.

These results verify the reported schema import and the tested generated-column uniqueness behavior on that MySQL 5.7.40 server. They do not verify application-level PHP rules or complete project functionality, do not constitute production database setup, and do not mark any roadmap phase complete. The disposable test database remains separate from the project database.

Do not use JSON as the authoritative modifier configuration or order history. Although MySQL 5.7.40 supports JSON, JSON does not provide ordinary foreign keys for its internal values and makes relational validation/reporting less direct. JSON is unnecessary for this simple predefined-options requirement.

### Table-label approach

The current proposal uses a configuration-based allowlist for dine-in labels and does not include `dining_tables`. This avoids another table and an Admin table-registry workflow. It requires the permitted labels to be maintained in configuration. No table count or actual label is assumed. Revisit a registry only if an approved requirement calls for runtime table-label management or database-enforced references.

### Integrity and calculation recommendations

- Restrict deletion of categories with assigned menu items and of menu items/options referenced by order history; reassign or deactivate them instead. A menu item with order history must not be physically deleted.
- Add indexes for queue retrieval (`orders(status, created_at)`), shift reports (`orders(shift_id)`), order-line lookups (`order_items(order_id)`), and modifier lookups by item/group/order line.
- Use unique usernames, unique category names, and unique option names/keys within a modifier group as appropriate.
- Enforce positive quantity and price, valid order/payment/status values, modifier applicability, dine-in table requirements, and cash sufficiency in PHP. MySQL 5.7 does not reliably enforce `CHECK` constraints.
- The server must reload current item/option prices and validate submitted IDs/selections. Calculate `unit_price_snapshot` as the item base-price snapshot plus the selected modifier price snapshots; each modifier price applies once per ordered item unit. Calculate `line_total_snapshot` as unit price times quantity, then the order total as the sum of line totals. Ignore browser-supplied prices and totals. No discount or tax calculation is included.
- Insert the order and all line/modifier snapshots in one transaction. Use a conditional status update to prevent duplicate or skipped transitions.
- A composite (`shift_id`, `staff_id`) foreign key or equivalent transactional validation should ensure that the shift recorded on an order belongs to the assigned staff account.
- Accept an order only while a qualifying Staff shift is active; validate and lock that shift in the order-creation transaction so it cannot close between the availability check and order insertion. Enforce the one-open-shift-per-staff rule with a MySQL 5.7-compatible unique index on a generated nullable staff key for open shifts, backed by transactional validation.

## Required reports and traceability

The specification and roadmap require daily gross revenue, payment-method breakdown, peak sales hours, best-selling menu items, and order volume per staff shift. The adopted initial-design rule counts completed-sales revenue and best sellers only for `Completed` orders. Peak sales hours group by order creation time.

The proposed data needed is order creation/payment/completion timestamps, current status, total, payment method, shift/staff assignment, line quantity, and immutable item/price/modifier snapshots. A full status-transition or general admin audit log is not explicitly required and is not proposed as a separate table. Status filters for payment-method, peak-hour, and per-shift reports remain to be clarified; this does not require additional schema tables.

## Unresolved owner decisions

1. Review the proposed column-level schema in `PROPOSED_DATABASE_SCHEMA.md`; this is the remaining schema-design review before SQL drafting. The nine-table direction itself is approved.
2. Define status filters for payment-method, peak-hour, and per-shift reports. The `Completed`-only rule for completed-sales revenue and best sellers is adopted for the initial design.
3. Define actual modifier groups/options, prices, applicability, and required/optional selection counts when the production menu is supplied; do not invent these values.
4. Supply the permitted table labels and count before operational configuration; the initial design validates them using a configuration allowlist.
5. Define the minimum Admin resolution procedure for stale/conflicting shifts without adding unapproved cash reconciliation.
6. Define development account provisioning/secret handling and production seed-data separation before seeding; do not record credentials here.
7. Record owner/staff confirmation of the four core features and roles as called for by the roadmap.

## Document alignment and roadmap status

- `PROJECT_SPECIFICATION.md` identifies the nine-table direction as owner-approved while keeping column details, SQL, and database creation subject to review and separate approval.
- `PROJECT_ROADMAP.md` Phase 1, section 1.3 describes the owner-approved nine-table direction; column details, SQL, and database creation remain subject to review and separate approval.
- The roadmap Phase 3, section 3.3 calls for real categories/menu items and initial accounts in seed data. For development, use clearly labeled samples and development-only accounts; production menu/accounts must be supplied and handled separately.
- The roadmap risk register now says Admins resolve abandoned shifts and does not suggest automatic closure, aligning with the approved no-auto-close rule.
- Preserve the roadmap's phase order. Phase 1 is not complete; its ERD, endpoint/folder plan, prioritized backlog, and remaining owner decisions still need work. No Phase 3 environment completion is asserted here.
