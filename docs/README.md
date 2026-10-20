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
- PHP compatible with the installed WAMP version, with `pdo_mysql` enabled for
  both the web server and CLI.
- A MySQL database initialized with the reviewed project schema.
- Apache with `mod_authz_core` enabled, `mod_headers` enabled, and
  `AllowOverride All` for this application directory so its access-control
  and security-header `.htaccess` rules are applied.
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
   order as described in
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
5. Generate and configure `ORDER_TRACKING_SECRET` for Apache. Generate a
   strong value locally with `php -r "echo bin2hex(random_bytes(32));"`; keep
   it private and never commit or publish it. Tracked order submission is
   unavailable until it is configured.
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

To populate the approved category names, run `database/seed_categories.sql`
after importing the schema. The clearly marked
`database/seed_menu_development_only.sql` contains illustrative items and
prices for development only; never import it into production.

The CLI PHP and Apache PHP may load different `php.ini` files. Verify that
`pdo_mysql` is enabled for both PHP configurations.

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
| `ORDER_TRACKING_SECRET` | Private random HMAC key; generate with the command above. |
| `APP_TIMEZONE` | Optional PHP timezone identifier; defaults to `Asia/Manila`. |
| `APP_UPLOAD_DIR` | Optional absolute writable directory outside Apache's document root for menu images; defaults to `C:\wamp\private\kape_cup_pos_uploads\menu`. |
| `INITIAL_ADMIN_PASSWORD` | CLI-only, temporary input for `scripts/create_admin.php`; not needed by Apache after account creation. |

In WAMP's Apache configuration, set web variables in the appropriate
VirtualHost/server environment, protect that configuration with filesystem
permissions, and restart Apache. To verify Apache's environment, temporarily
use a localhost-only PHP diagnostic that prints only whether each variable is
set (never its value), request it through Apache, and delete it immediately.
Verify `INITIAL_ADMIN_PASSWORD` in the CLI process used for initial setup
instead; do not configure or leave it set for the production web server.

## Apache and production deployment

Enable Apache's `headers_module` (`mod_headers`) and allow `.htaccess` overrides
for the application directory (`AllowOverride All`). Restart Apache and check
that response headers are present after configuration changes.

The preferred production setup is to set Apache's `DocumentRoot` to a
dedicated public directory containing only intended public assets and entry
points. Keep `config/`, `database/`, `docs/`, `includes/`, and `scripts/`
outside the web root. Do not rely on `.htaccess` as a substitute for keeping
private application files outside `DocumentRoot`.

Serve production over HTTPS only after installing and testing a valid
certificate. See the HTTPS and HSTS rollout instructions in
[Security testing and deployment](SECURITY_TESTING.md).

## Frontend CSS

Shared Admin and Staff shell styles live in `assets/css/app.css`; customer and
legacy page styles remain in `assets/css/style.css`.

## Application flows

- Customers browse menu items, choose configured modifiers, and add items to a
  session-backed cart.
- Checkout recalculates item and modifier prices from the database. The server
  accepts orders only while an active Staff account has an open shift.
- Staff manually verify payment and advance order status in sequence:
  `Pending` → `Paid` → `Preparing` → `Completed`. Orders cannot be cancelled
  or moved backward.
- Customers track orders using a server-session HMAC token stored when the
  order is submitted. The token is sent in the `X-Order-Tracking-Token`
  request header, not stored in browser storage or exposed in the URL.
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
