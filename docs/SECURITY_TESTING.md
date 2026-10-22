# Security Testing Record

## 2026-10-13 — Read-only source and schema review

**Environment:** M&E Kape Cup POS, PHP 8.0 and MySQL project.

**Scope reviewed:** Authentication and sessions; Admin and Staff authorization; database configuration and SQL access; customer ordering, checkout, order status and payment workflow; CSRF, output escaping and input validation; APIs; file-upload and diagnostic-file exposure; secrets and database schema. The review included `api/order_notifications.php`.

**Result:** No confirmed exploitable vulnerability was identified in the reviewed source code and schema. The result is a source-level review, not a penetration test or a certification that the deployed application is secure.

**Validation performed:** PHP syntax lint passed for 37 PHP files. A read-only unauthenticated request to the order-notifications endpoint returned HTTP 401. No source files or database contents were changed, and no real order was submitted.

**Deployment items not verified:**

- Whether the deployed webroot contains diagnostic scripts or exposes configuration, SQL, backup, or documentation files.
- Production HTTPS behavior, session cookie `Secure` attribute, reverse-proxy handling, and global security headers.
- Actual database-account privileges, deployed credentials, and whether the live schema matches the project schema.
- Authenticated notification behavior, order-submission behavior, and the operational manual payment-verification process.

**Recommended follow-up:** Verify the deployment-only items above in the target environment using authorized, non-destructive checks. Perform authenticated workflow testing only in a disposable test environment; do not use a live customer order for validation.

## 2026-10-18 — Final local verification

The HTTP and module observations in this dated record are historical and were
superseded by the read-only WAMP checks recorded on 2026-10-23 below.

**PHP lint:** All 41 PHP files outside `design/` passed `php -l`.

**Customer tracking:** With `mekape_order_tracking` cleared from browser
`localStorage`, `status.php` displayed the instruction to ask counter staff
using the order number. It does not reveal order data without a tracking token.

**Session cookie configuration:** PHP runtime checks reported
`mekape_customer_session` and `mekape_staff_session`, both with HttpOnly and
SameSite=Lax. The checks ran over local HTTP, so `Secure` was false as
expected; production HTTPS cookie behavior still requires verification.

**HTTP checks:** The compiled shared stylesheet and local coffee image returned
HTTP 200. The home page, menu, API menu, and Staff login returned HTTP 500
because the Apache environment did not have the required DB host, username,
and password variables configured. This prevented database-backed customer,
Staff, and Admin regression testing.

**Private paths:** Direct GET requests to `database/schema.sql`,
`docs/README.md`, `design/stitch/home.html`, `config/db.php`,
`includes/auth.php`, and `scripts/create_admin.php` now return HTTP 403 after
adding folder-level deny rules. Public CSS, local images, and `status.php`
still return HTTP 200. The API's HEAD request returned HTTP 405 because that
endpoint does not accept HEAD; its database-backed POST workflow remains
untested.

**Apache headers:** `AllowOverride all` was present for WAMP `www` during
configuration inspection and `authz_core_module` is loaded. The access
denials were verified live. However, `headers_module` was not loaded, so
global security headers and Staff/Admin frame-denial behavior failed live
verification.

**Phase 9 CSP review:** Executable inline scripts remain in `status.php`,
`menu.php`, `index.php`, `staff/orders.php`, `staff/dashboard.php`,
`checkout.php`, and `product_details.php`. `staff/orders.php` also contains an
inline JSON data block. Inline `<style>` blocks/style attributes remain across
customer, Staff, and Admin pages, so an enforcing CSP is not realistic yet.
Move executable scripts and styles to local files (or use carefully generated
nonces/hashes for inline blocks; style attributes need separate treatment),
then test the policy before enforcement. Fonts are self-hosted under
`assets/fonts/`; production PHP pages do not reference Google Fonts, the
Tailwind CDN, or `lh3.googleusercontent.com`.

**Not run:** Order placement, modifier validation, payment calculations,
rate-limit behavior, shift transitions, Admin operations, CSRF rejection,
and visual/offline comparison require database-backed pages and a configured
test account. They remain deployment acceptance checks, not passed tests.

## 2026-10-22 — Persistent order-reference source validation

These results record the source state validated on 2026-10-22 and do not
validate the subsequent tracking or backfill edits documented below.

**PASS:** PHP 8.0.26 syntax checks for all changed PHP files; `git diff --check`;
10,000 generated references matched the required format and normalized
correctly with no duplicate in that sample; the insertion helper wrote and
read back the same reference using an isolated in-memory SQLite test; the
status page rendered a normalized reference without a DB connection; and the
status API returned a safe `invalid_reference` JSON response for malformed
input without connecting to the DB.

**Not tested:** No MySQL/MariaDB server or application DB was connected.
MySQL 5.7 migration execution, existing-order backfill, unique-index collision
retry, DB-backed reference lookup/rate limiting, successful order submission
and duplicate-submit idempotency, receipt rendering/printing, Staff status
updates, and InfinityFree behavior remain NOT TESTED. No order or database
record was created or changed during this validation.

## 2026-10-22 — Eight-digit numeric tracking reference

These results record the source state validated on 2026-10-22 and do not
validate the subsequent tracking or backfill edits documented below.

**PASS:** PHP 8.0.26 syntax checks for all affected PHP files and
`git diff --check`; 10,000 generated values matched eight numeric digits;
normalization preserved a leading zero and accepted surrounding whitespace;
seven-digit and non-numeric values were rejected; an isolated SQLite insert
stored and returned the generated eight-digit reference; the status page
rendered an eight-digit reference with leading zero intact; and malformed API
input returned a safe validation response without a DB connection.

**Not tested:** No MySQL/MariaDB or application database was connected.
Migration 008, the reference-replacement backfill, unique-index collision
handling under MySQL, production rate limiting, end-to-end checkout, receipt
printing, and InfinityFree behavior were not tested. Existing reference numbers
were not changed in any database.

## 2026-10-23 — Controlled audit and approved repair validation

**Environment:** PHP 8.0.26, WAMP Apache, and a local MySQL 5.7.40 database.
The supplied source archive matched the workspace's non-Git files. Database
inspection was read-only; no migration, backfill, order, receipt, or payment
write was performed.

**Passed:** PHP syntax checks for all project PHP files; `git diff --check`;
the no-`pdo_mysql` configuration path returned a generic customer-facing
message; isolated tests confirmed the four-second poll-token throttle and
continued compatibility of eight-digit and 27-character tracking filters.
Backfill argument checks rejected apply mode without explicit order IDs and
rejected duplicate approved IDs before connecting to the database.

**Read-only WAMP HTTP observations:** The status page, checkout, menu API,
Staff login, logo, and stylesheet returned HTTP 200. Direct requests to
`.git`, `.mcp.json`, SQL schema, scripts, config, and documentation paths
returned HTTP 403. Configured security headers were present; `X-Powered-By`
still disclosed the PHP version.

**Not tested:** The backfill was not run in either mode, migration 008 was not
run, and no disposable database restore or write-capable runtime test was
performed. The inspected database had no `receipt_records` table or migration
ledger, so actual receipt snapshots and exact migration history remain
unverified. Database-backed throttling, checkout, staff status updates,
receipt creation, and historical receipt consistency remain untested.

**Manual hosting verification required:** InfinityFree's PHP version and
extensions, private configuration support, database connectivity, CLI access,
HTTPS/session-cookie behavior, upload permissions, `.htaccess` module support,
access denials, and security headers have not been tested.

## Application hardening and operations

### Database migrations

For an existing installation, back up the database and apply these migrations
in order using phpMyAdmin's Import action or the MySQL CLI instructions in
[Database setup for WampServer](DATABASE_SETUP.md):

1. `database/migrations/001_login_attempts.sql`
2. `database/migrations/002_order_submission_log.sql`
3. `database/migrations/003_inventory.sql`
4. `database/migrations/004_menu_item_images.sql`
5. `database/migrations/005_order_submission_idempotency.sql`
6. Follow the staged `006` → dry-run → unique-index → backfill → `NOT NULL`
   customer-reference procedure in
   [Database setup for WampServer](DATABASE_SETUP.md). Apply migration 008
   only if every retained reference is eight digits; it is not required before
   migration 009.

Migration 001 rolls back with `DROP TABLE login_attempts;`, and migration 002
with `DROP TABLE order_submission_log;`. Migration 004 can be rolled back with
`ALTER TABLE menu_items DROP COLUMN image_filename;` after preserving or
clearing references. Migration 005 rolls back with
`DROP TABLE order_submission_requests;`. Migration 003's rollback is recorded
in its SQL file. Stop application requests before rollback and run rollback
steps in reverse migration order. Do not re-import the base schema over
production data.
For order-reference migrations, retain reference values and indexes during
application rollback; follow the recovery instructions in
[Database setup for WampServer](DATABASE_SETUP.md) instead of dropping the
new columns.

### Runtime configuration

Configure Apache's PHP environment with `DB_HOST`, `DB_NAME`, `DB_USERNAME`,
`DB_PASSWORD`, and optionally `ORDER_TRACKING_SECRET`, `APP_TIMEZONE`, and
`APP_UPLOAD_DIR`. The tracking secret is needed only to preserve legacy
session-bound HMAC status links; new reference lookup does not depend on it.
`INITIAL_ADMIN_PASSWORD` is only for the CLI initial-admin setup script and
should not remain configured for the web server. If retaining legacy HMAC
links, generate the tracking secret locally with
`php -r "echo bin2hex(random_bytes(32));"` and store it only in protected
server configuration. Never log or display the secret. To verify Apache's environment, inspect the protected VirtualHost configuration
and server-side error log, then exercise the application normally; do not
create a public diagnostic PHP page.

Apache must support `mod_rewrite`, `mod_authz_core`, and `mod_headers`, with
`AllowOverride All` for the application directory, for all configured
`.htaccess` protections to apply. The current WAMP checks confirmed access
denials for the inspected private paths and observed the configured security
headers. `X-Powered-By` still exposed the PHP version and should be disabled in
the host's PHP configuration where permitted. Earlier checks recorded in the
2026-10-18 subsection predate this observation. Target-host behavior,
including InfinityFree, remains unverified.

The runtime expects `config/` and `includes/` to remain with the PHP
application root; moving them outside the document root requires corresponding
code-path changes. Keep private credentials and backups outside the webroot,
exclude development files from the deployment package, and treat `.htaccess`
as an additional control rather than the only protection.

### Brute-force, order-flood, and password controls

- Staff login failures are associated with SHA-256 username/IP keys. Five
  failures for a username in 15 minutes or 20 failures from an IP in 15
  minutes cause login requests to be refused with HTTP 429 until the window
  expires. The raw identifiers are not stored.
- Order submission is limited to 30 globally open `Pending` orders, 3 orders
  per customer session per 10 minutes, and 10 orders per source IP per 10
  minutes. Session IDs and IPs are stored only as SHA-256 keys. A rejected
  request receives a friendly rate-limit response.
- New passwords require at least 12 Unicode characters and no more than 72
  bytes. Staff and Admins change their own password at
  `staff/change_password.php`; Admins can reset another account's password
  from Staff Accounts, but must use Change password for their own account.

These controls depend on the matching database migrations being applied. Test
lockouts, throttles, password changes/resets, and order transitions only in a
disposable environment.

### HTTPS and HSTS rollout

1. Obtain a valid certificate for the production hostname and configure the
   Apache TLS VirtualHost (`mod_ssl`, port 443, certificate chain and private
   key). Protect the private-key file with restrictive filesystem ACLs.
2. Test the HTTPS site, login, customer session cookies, order submission,
   APIs, static CSS/images, and all redirects. Ensure no page or asset falls
   back to HTTP and that PHP detects HTTPS so session cookies receive `Secure`.
3. Only after HTTPS is stable, enable an HTTP-to-HTTPS redirect in the Apache
   VirtualHost and verify it does not create redirect loops or break health
   checks.
4. Verify the HTTPS responses first, then uncomment the existing
   `Strict-Transport-Security` header in the root `.htaccess`. Enable
   `includeSubDomains` only if every subdomain is permanently HTTPS-capable.
   HSTS is cached by browsers; do not enable it on a hostname whose HTTPS
   readiness is uncertain.
5. Confirm the `Strict-Transport-Security` header is present on HTTPS
   responses. Do not send HSTS over plain HTTP.

Keep the current CSP in report-only mode until inline scripts/styles have
been moved to same-origin files or protected with nonces/hashes and required
font origins have been addressed.

### Frontend styles

Customer, Admin, Staff, and offline POS pages use the shared
`assets/css/style.css`. Review its deployed response after any style changes.
Node.js is not required on the production web server for CSS.

## Content Security Policy

The root `.htaccess` configures a `Content-Security-Policy-Report-Only` policy
as a starting point. It was observed in local WAMP responses, but it will not
be sent on hosts where `mod_headers` or the required overrides are unavailable.
Phase 9 removed the Tailwind CDN and remote Google image hotlinks, but an
enforcing policy is not yet ready:

- Executable inline scripts remain in `checkout.php`, `index.php`, `menu.php`,
  `product_details.php`, `status.php`, `staff/dashboard.php`, and
  `staff/orders.php`. Move them to same-origin JavaScript files or add a
  securely generated per-response nonce to both the script elements and CSP.
- Inline style blocks remain. Externalize them or use hashes/nonces before
  removing `style-src 'unsafe-inline'`.
- Domine, Plus Jakarta Sans, Inter, Merriweather, and Material Symbols are
  still requested from Google Fonts. Either self-host these fonts or allow
  the required Google Fonts stylesheet and font origins explicitly.

Review browser reports, address these remaining sources, and test all
application flows before switching to an enforcing `Content-Security-Policy`.

## 2026-10-23 — Reference-preserving backfill and tracking hardening

The backfill utility defaults to a read-only preview. It plans repairs only
for missing references, preserves valid existing references, and stops when
it finds malformed values, duplicates, or receipt/snapshot conflicts.
`--apply` is explicitly required to write and requires a unique reference
index. When an order's missing reference can be recovered from consistent
issued receipt records, the script restores the saved value rather than
generating a replacement. Migration 008 must not be used when longer legacy
references remain.

The public tracking API counts valid initial lookups by both IP address and
session. A successful lookup returns a short-lived, session-bound polling
capability; the customer reference is not sent again during polling. This
reduces repeated guesses from a single session but does not make an eight-digit
reference a strong identity credential. Rate-limit behavior under shared
hosting concurrency, shared NATs, and distributed traffic remains unverified.

No production migration, backfill, or deployment is authorized by this
documentation. The final repair report must distinguish local static, syntax,
HTTP, database, and workflow validation and identify anything not tested.

The database connection failure handler logs only a validated SQLSTATE and
numeric PDO driver code, not the exception message. A CLI-only forced
connection failure using dummy settings returned the generic visitor message
and emitted no test host, username, database name, or password.
