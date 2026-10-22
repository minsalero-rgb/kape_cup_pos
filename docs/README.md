# M&E Kape Cup POS

A plain-PHP point-of-sale and customer self-ordering application for
M&E Kape Cup. It runs on WampServer with Apache and MySQL, uses PDO for
database access, and serves precompiled Tailwind CSS for the interface.

## UI prototype

Open [design/prototype.html](../design/prototype.html) directly in a browser to
explore the customer, staff, admin, and design-system screens. It is a
standalone visual prototype: all demonstration values are placeholders, and it
does not submit orders or connect to the POS database.

## Requirements

- WampServer with Apache and MySQL 5.7.40 (or a compatible MySQL release).
- PHP 8.0 or newer, with `pdo_mysql` and `mbstring` enabled for both the web
  server and CLI. PHP 8.0.26 was used for local validation.
- A MySQL database initialized with the reviewed project schema.
- Apache with `mod_rewrite`, `mod_authz_core`, and `mod_headers` enabled, and
  `AllowOverride All` for this application directory so its dotfile,
  access-control, and security-header `.htaccess` rules are applied.
- Node.js and npm only when rebuilding frontend CSS; production serving does
  not require Node.js.
- No Composer packages or PHP framework are required.

## Local setup

1. Place the project in WAMP's web root, for example
   `C:\wamp\www\kape_cup_pos`.
2. Review the project decisions and schema before initializing the database.
   `database/schema.sql` creates and selects `db_kape_cup_pos`, then creates its
   tables. Import it with an administrator connection as described in
   [Database setup for WampServer](DATABASE_SETUP.md).
3. For an existing database, inspect the installed schema and apply only
   migrations that have not already run: `001_login_attempts.sql`,
   `002_order_submission_log.sql`, `003_inventory.sql`,
   `004_menu_item_images.sql`, and `005_order_submission_idempotency.sql` in
   order. Existing databases must also follow the staged
   006/dry-run/unique-index/backfill/NOT-NULL order-reference procedure.
   Apply 008 only if every retained reference is eight digits, then apply
   `009_receipt_records.sql` as described in
   [Database setup for WampServer](DATABASE_SETUP.md).
   Migration files are not safe to re-run; do not apply a migration whose
   table or column is already present.
4. Create a dedicated, least-privilege MySQL application user and configure
   `DB_HOST`, `DB_NAME`, `DB_USERNAME`, and `DB_PASSWORD` for PHP. If Apache
   does not pass its environment settings to PHP, use the protected
   out-of-webroot config file described in
   [Database setup for WampServer](DATABASE_SETUP.md). `config/db.php`
   rejects the MySQL `root` account and empty passwords. Never store real
   credentials in source-controlled files.
5. Optionally keep `ORDER_TRACKING_SECRET` configured for old session-bound
   HMAC tracking links. New customer tracking uses the saved order reference
   and does not depend on this secret. Generate a strong value locally with
   `php -r "echo bin2hex(random_bytes(32));"`; keep it private and never
   commit or publish it.
6. Optionally set `APP_TIMEZONE` to a valid PHP timezone identifier. The
   default is `Asia/Manila`; the same timezone is applied to PHP and the
   MySQL connection.
7. Configure the customer ordering options in `config/order.php`. Dine-In stays
   disabled while `dine_in_table_labels` is empty; add only the café's approved
   table labels.
8. Create the first Admin account using the Windows instructions in
   [docs/INITIAL_ADMIN_SETUP.md](docs/INITIAL_ADMIN_SETUP.md). There is no
   built-in Admin username or password; choose them during setup.
9. Open the project through WAMP over HTTPS in production. For local development,
   for example:
   `http://localhost/kape_cup_pos/`.

Staff sign in at `staff/login.php`; Admins sign in at `admin/login.php`. Each
page accepts only accounts assigned to its matching role.

Staff enter an OR number already issued through the business's authorized
process before saving/printing a receipt. Staff removal is a soft removal with
a required reason; the Admin receipt audit retains the original snapshot.

To populate the approved category names, run `database/seed_categories.sql`
after importing the schema. The clearly marked
`database/seed_menu_development_only.sql` contains illustrative items and
prices for development only; never import it into production.

The CLI PHP and Apache PHP may load different `php.ini` files. Verify that
`pdo_mysql` is enabled for both PHP configurations.

## Production package contents

The deployment archive has two separate top-level areas. Put the root PHP
entry points and `.htaccess`, plus the runtime `admin/`, `api/`, `assets/`,
`config/`, `includes/`, and `staff/` trees under `webroot/`; keep all
subdirectory `.htaccess` files. Put only migrations `001` through `007` and
`009` under `database-migrations/`, outside `webroot/`. Migration 008 is
intentionally excluded because it narrows the reference column and can
invalidate issued legacy references.

Create the archive from the reviewed working tree, not a Git archive, so
approved uncommitted repairs are included. Exclude `.git/`, `.vscode/`,
`.mcp.json`, schema and seed files, `docs/`, `design/`, `scripts/`, development
reports, local backups, and private configuration files. The standalone
`offline-pos/` prototype/build toolchain is not used by the authenticated
server-side POS workflow and is excluded; retain it in the development
repository. Upload only the contents of `webroot/` to the application's web
directory. Never upload `database-migrations/`; keep those files with the
authorized operator and apply only migrations confirmed missing from the
target schema.

Never include the private database configuration or credentials in the
archive. Before transfer, verify the archive file list and SHA-256 hash, and
test access-denial rules on the target host. `.htaccess` protections depend on
the hosting provider's Apache modules and `AllowOverride` settings; they do not
replace keeping sensitive configuration and backups outside the web root.

## Environment variables

Configure these values in the Apache/PHP environment used by the web
application. Do not rely on variables set only in a PowerShell or terminal
session:

| Variable | Purpose |
| --- | --- |
| `DB_HOST` | MySQL server hostname; defaults to `localhost`. |
| `DB_NAME` | Application database; defaults to `db_kape_cup_pos`. |
| `DB_USERNAME` | Dedicated application database account; `root` is rejected. |
| `DB_PASSWORD` | Non-empty password for that database account. |
| `ORDER_TRACKING_SECRET` | Optional private HMAC key for legacy session-bound tracking links; generate with the command above. |
| `APP_TIMEZONE` | Optional PHP timezone identifier; defaults to `Asia/Manila`. |
| `APP_UPLOAD_DIR` | Optional absolute writable directory outside Apache's document root for menu images; defaults to `C:\wamp\private\kape_cup_pos_uploads\menu`. |
| `INITIAL_ADMIN_PASSWORD` | CLI-only, temporary input for `scripts/create_admin.php`; not needed by Apache after account creation. |

In WAMP's Apache configuration, set web variables in the appropriate
VirtualHost/server environment, protect that configuration with filesystem
permissions, and restart Apache. Do not create a public PHP diagnostic page.
Verify settings through the protected Apache configuration and server-side
error log; the CLI connection check in
[Database setup for WampServer](DATABASE_SETUP.md) verifies only the CLI
environment. Verify `INITIAL_ADMIN_PASSWORD` in the CLI process used for
initial setup instead; do not configure or leave it set for the production
web server.

## Apache and production deployment

Enable Apache's `headers_module` (`mod_headers`) and allow `.htaccess` overrides
for the application directory (`AllowOverride All`). Restart Apache and verify
access restrictions and response headers on the target host. The local WAMP
check observed the configured headers, but `X-Powered-By` still exposed the
PHP version; configure `expose_php=Off` where the host permits it and verify
the response again.

The runtime expects the application root, including `config/` and `includes/`,
to remain together under its web directory. Do not move those runtime folders
outside the document root without making and reviewing corresponding code-path
changes. The production package excludes development and database files;
database credentials and private configuration must remain outside the web
directory. `.htaccess` rules are additional access controls, not a substitute
for keeping secrets and backups private.

Serve production over HTTPS only after installing and testing a valid
certificate. See the HTTPS and HSTS rollout instructions in
[Security testing and deployment](SECURITY_TESTING.md).

InfinityFree has not been verified. Before uploading, confirm its PHP version,
`pdo_mysql`/`mbstring` availability, supported environment or private-file
configuration, database connection details, HTTPS/session-cookie behavior,
Apache `.htaccess` support, upload-directory permissions, and response
headers. Do not upload credentials or continue if the host offers no safe
private configuration method.

## Frontend CSS

All live application styles, including the customer storefront, login pages,
Admin and Staff shell, and POS setup state, are served from
`assets/css/style.css`. Inter, Merriweather, and the used Material Symbols
icons are self-hosted under `assets/fonts/`; the live PHP pages do not need a
font CDN.

## Application flows

- Customers browse menu items, choose configured modifiers, and add items to a
  session-backed cart.
- Checkout recalculates item and modifier prices from the database. The server
  accepts orders only while an active Staff account has an open shift.
- Staff manually verify payment and advance order status in sequence:
  `Pending` → `Paid` → `Preparing` → `Completed`. Orders cannot be cancelled
  or moved backward.
- New orders receive a persistent, random eight-digit customer tracking
  reference saved on the order. Existing issued references are preserved,
  including recognized 27-character legacy references. Customers can enter a
  reference on the Order Status page from a new session or device. Existing
  session-bound HMAC tracking links remain supported when
  `ORDER_TRACKING_SECRET` is configured.
- Customer and Staff checkout submission keys are session-scoped; retrying the
  same request returns its original order rather than creating another one.
  Existing databases need migration `005_order_submission_idempotency.sql`.
- Staff sign in through `staff/login.php`; Admins sign in through
  `admin/login.php`. Account role determines the available pages and actions.

There is no payment gateway or automatic payment confirmation. Prices and
totals are computed on the server; values from the browser are not trusted.

## Staff POS

The authenticated POS at `staff/offline_pos.php` reads its menu, orders, and
inventory from MySQL. Cart contents are held in the Staff PHP session until
checkout; completed sales, order items, stock deductions, and inventory
adjustments are written to the database. Kitchen Queue, Sales & Reports, and
Stock & Items use database-backed pages. `staff/dashboard.php` keeps routing
legacy page links to the corresponding live pages.

Migration `003_inventory.sql` initializes existing menu items to zero stock.
Enter confirmed stock counts from Stock & Items before selling those items.
Migration `004_menu_item_images.sql` adds an optional image filename to each
menu item. Uploaded menu images are stored outside the public web root under
`C:\wamp\private\kape_cup_pos_uploads\menu` by default. Set `APP_UPLOAD_DIR` to
an absolute writable directory outside the web root if this default is not
suitable; grant write access only to the Apache service account and
administrators. For existing databases, follow the backup and migration steps
in [Database setup for WampServer](DATABASE_SETUP.md) before uploading images.
Customer and Staff carts remain session-backed; the POS does not use seeded
browser data or `localStorage`. The old standalone React preview entry point is
retired and no longer loads the browser-only POS.

## Project documentation

- [Project decisions and open questions](docs/DECISIONS.md)
- [Project specification](docs/PROJECT_SPECIFICATION.md)
- [Project roadmap](docs/PROJECT_ROADMAP.md)
- [First Admin setup for Windows](docs/INITIAL_ADMIN_SETUP.md)
- [Database setup for WampServer](docs/DATABASE_SETUP.md)
- [Database schema](database/schema.sql)

The exact live menu, modifier options, prices, and Dine-In table labels must
come from café-approved data; do not treat prototype content as production
configuration.
