# Database Setup for WampServer

The application connects to `db_kape_cup_pos` by default using a dedicated MySQL
account, not `root`. Import `database/schema.sql` with a MySQL administrator
connection first; the script creates and selects `db_kape_cup_pos` and creates
its tables. It does not create any accounts or menu data.

From PowerShell, start the MySQL command-line client (ensure the WAMP MySQL
`bin` directory is on `PATH`, or use the full path to that installation):

```powershell
mysql --user=root --password
```

Enter the MySQL administrator password when prompted. At the `mysql>`
prompt, import the schema (replace the project path if needed):

```sql
SOURCE C:/wamp/www/kape_cup_pos/database/schema.sql;
```

Alternatively, select `database/schema.sql` in phpMyAdmin's Import page. The
script explicitly selects `db_kape_cup_pos`.

## Create a least-privilege application account

Connect to the local MySQL server using phpMyAdmin or the MySQL command-line
client with an administrator account. Replace `your_database_name` with
`db_kape_cup_pos` (or the database configured as `DB_NAME`), and replace the
password placeholder with a unique, strong password. Do not save a real
password in this file or any source-controlled SQL file.

```sql
CREATE USER 'kape_cup_pos_app'@'localhost'
    IDENTIFIED BY 'replace-with-a-unique-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE
    ON `your_database_name`.*
    TO 'kape_cup_pos_app'@'localhost';
FLUSH PRIVILEGES;
```

These permissions cover the application's reads, inserts, updates, and
throttle-log cleanup. Use a separate administrator connection for schema
changes; the application account does not need `CREATE`, `ALTER`, `DROP`, or
account-management privileges.
If the application connects from a different MySQL host, create the user for
that specific host rather than broadening it to `%`.

## Configure PHP and Apache

Set these environment variables for **both** Apache's PHP and the CLI PHP used
to run setup scripts:

- `DB_HOST` — normally `localhost` for local WampServer.
- `DB_NAME` — normally `db_kape_cup_pos`, containing the imported schema.
- `DB_USERNAME` — `kape_cup_pos_app`.
- `DB_PASSWORD` — the strong password chosen when creating the MySQL user.

For Apache, configure the variables in the local WAMP/Apache VirtualHost or
other server-level environment configuration outside the repository and
outside the public web root. Restart Apache after changing its configuration.
Do not put the password in a tracked project file, `.htaccess`, a URL, or a
command that will be saved in shell history.

For local WampServer installations where Apache does not pass `SetEnv` values
to PHP, `config/db.php` also loads a protected PHP array outside the web root.
When this file exists, its string values override matching process environment
variables; values omitted from the file can still come from the environment.
Without the private file, the application reads environment variables and uses
`localhost`/`db_kape_cup_pos` only as host/name defaults. Missing or unsafe
database credentials fail closed.
This installation uses `C:\wamp\private\kape_cup_pos.php`; keep its ACL
restricted to the Windows account running Apache, Administrators, and SYSTEM.
It must return an array with `DB_HOST`, `DB_NAME`, `DB_USERNAME`,
`DB_PASSWORD`, and `APP_TIMEZONE` string values; `ORDER_TRACKING_SECRET` is
optional for legacy HMAC tracking. Set
`APP_PRIVATE_CONFIG` to use a different absolute path. Never place this file
inside the project or serve it from Apache.
For this installation, update its `DB_NAME` value to `db_kape_cup_pos` only
after the schema and copied data have been verified in that database.
The InfinityFree configuration path, PHP version, available PDO MySQL driver,
and ability to read an out-of-webroot private file have not been verified.
Configure a private file outside `htdocs` where the account permits it, or
use the provider's protected PHP environment settings; never upload the WAMP
private file or credentials with the application.
For InfinityFree, copy the exact MySQL hostname, database name, username, and
password from its control panel into the provider-supported private
configuration. Do not use WAMP's `localhost`, default database name, or
credentials unless the provider explicitly gives those values. If the host
does not permit either a private file outside `htdocs` or protected PHP
environment settings, do not place credentials in the deployed application;
stop and confirm a safe configuration option with the provider.

Before deployment, verify that the host runs PHP 8.0 or newer and has
`pdo_mysql` and `mbstring` enabled for the web SAPI, supports the required
session behavior and HTTPS cookies, and permits the app's upload directory to
be configured safely. Confirm which Apache modules and `.htaccess` overrides
are supported, then test the private-path denials and response headers on the
host. InfinityFree's PHP version, extensions, configuration paths, CLI access,
and Apache behavior require manual verification; do not assume they match
WAMP.

For a Windows PowerShell session, set the host, database, and username before
running a CLI script. Supply the password at a secure prompt instead of typing
it into a command:

```powershell
$env:DB_HOST = 'localhost'
$env:DB_NAME = 'db_kape_cup_pos'
$env:DB_USERNAME = 'kape_cup_pos_app'
Set-Location 'C:\wamp\www\kape_cup_pos'
$securePassword = Read-Host 'MySQL application password' -AsSecureString
$passwordPointer = [IntPtr]::Zero
try {
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $env:DB_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    & 'C:\wamp\bin\php\php8.0.26\php.exe' -r 'require "config/db.php"; echo "Database connection OK", PHP_EOL;'
    if ($LASTEXITCODE -ne 0) {
        throw 'The CLI script failed. Review its output before retrying.'
    }
}
finally {
    Remove-Item Env:\DB_PASSWORD -ErrorAction SilentlyContinue
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
    Remove-Variable securePassword, passwordPointer -ErrorAction SilentlyContinue
}
```

Use the CLI `php.exe` that matches the PHP version installed in WampServer.
The CLI environment above applies only to that PowerShell process; configure
Apache separately. `config/db.php` rejects the `root` username and empty
passwords, so a missing environment setting fails closed.

## Environment variables

The web application uses these environment variables:

| Variable | Required | Purpose |
| --- | --- | --- |
| `DB_HOST` | No | MySQL host; defaults to `localhost`. |
| `DB_NAME` | No | Database name; defaults to `db_kape_cup_pos`. |
| `DB_USERNAME` | Yes | Dedicated application account; `root` is rejected. |
| `DB_PASSWORD` | Yes | Non-empty password for the application account. |
| `ORDER_TRACKING_SECRET` | No | Private HMAC key of at least 32 bytes for legacy session-bound status URLs. Reference-based tracking does not depend on it. |
| `APP_TIMEZONE` | No | PHP timezone identifier; defaults to `Asia/Manila`. |
| `APP_UPLOAD_DIR` | No | Absolute writable directory outside Apache's document root for uploaded menu images. Defaults to `C:\wamp\private\kape_cup_pos_uploads\menu`. |
| `INITIAL_ADMIN_PASSWORD` | CLI setup only | Temporary password input to `scripts/create_admin.php`; not an Apache runtime setting. |

To retain legacy HMAC status links, generate a strong order-tracking key locally using the WAMP PHP executable
(or another PHP CLI with `random_bytes()`):

```powershell
php -r "echo bin2hex(random_bytes(32));"
```

The command prints 64 hexadecimal characters (32 random bytes). Store the
result directly in the protected Apache/VirtualHost environment configuration;
do not paste it into source, `.htaccess`, a URL, a ticket, or this chat. Do not
configure `INITIAL_ADMIN_PASSWORD` in production Apache after initial Admin
setup.

Set the web variables for Apache, not only in a terminal. With WampServer,
place them in the appropriate Apache VirtualHost/server environment
configuration, protect that file with Windows ACLs, and restart Apache. To
verify Apache settings, inspect the protected VirtualHost configuration and
the protected server-side error log, then exercise the application normally.
The CLI connection check above validates only CLI PHP settings, not Apache's
environment. Do not create a public PHP diagnostic page or expose settings in
a browser response. Check `INITIAL_ADMIN_PASSWORD` only in the CLI environment
used for the one-time setup.

`APP_TIMEZONE` is validated against PHP's timezone identifiers. The app uses
`Asia/Manila` when it is absent or invalid, sets the PHP timezone, and sets the
MySQL connection timezone offset to match.

## Apply migrations to an existing database

Back up the database first. Select the existing application database
(`db_kape_cup_pos`, or the configured `DB_NAME`). Inspect the existing schema
and apply only migrations that have not already run, in the order below. These
migration files are not idempotent: do not re-run one if its table or column is
already present, and do not re-import `database/schema.sql` over an existing
database.

1. Import `database/migrations/001_login_attempts.sql`.
2. Import `database/migrations/002_order_submission_log.sql`.
3. Import `database/migrations/003_inventory.sql`.
4. Import `database/migrations/004_menu_item_images.sql`.
5. Import `database/migrations/005_order_submission_idempotency.sql`.
6. For customer order references, follow the staged procedure below; do not
   import migrations 006 and 007 back-to-back.
7. After the order-reference backfill and uniqueness/nullability checks pass,
   import `database/migrations/009_receipt_records.sql` once to enable
   OR-numbered receipt records and the issue/removal audit. Migration 008 is
   optional only when every retained reference fits its eight-digit format;
   it is not a prerequisite for migration 009.

In phpMyAdmin, select the application database, open **Import**, and import
only the missing migration files. For the MySQL command-line client, run only
the `SOURCE` commands for migrations confirmed missing; do not paste the full
example blindly. Do not re-import `database/schema.sql` over an existing
production database. Start the MySQL command-line client from PowerShell
(ensure the WAMP MySQL `bin` directory is on `PATH`, or use the full path to
that installation):

```powershell
mysql --user=root --password --database=db_kape_cup_pos
```

Enter the database administrator password when prompted. At the `mysql>`
prompt, run the migration files in order (replace the project path if needed):

```sql
SOURCE C:/wamp/www/kape_cup_pos/database/migrations/001_login_attempts.sql;
SOURCE C:/wamp/www/kape_cup_pos/database/migrations/002_order_submission_log.sql;
SOURCE C:/wamp/www/kape_cup_pos/database/migrations/003_inventory.sql;
SOURCE C:/wamp/www/kape_cup_pos/database/migrations/004_menu_item_images.sql;
SOURCE C:/wamp/www/kape_cup_pos/database/migrations/005_order_submission_idempotency.sql;
```

### Add persistent order tracking references

This staged migration preserves internal `order_id` values and all existing
order history. Test it against a local/staging database first. For production,
do not proceed without an explicit change window, an independently verified
database export, and confirmation of the hosting MySQL/PHP versions and an
authorized way to run the PHP CLI backfill. Do not run it against production
from this project session.

1. Export the database using phpMyAdmin's **Export → SQL** or the hosting
   provider's supported backup tool. Verify that the export is non-empty and
   can be imported into a separate test database. A project ZIP is not a
   database backup. Keep the backup outside the public web root.
2. Put customer online checkout and Staff POS submissions into maintenance
   mode for the schema change. Confirm whether `orders.order_reference` and
   `order_submission_log.event_type` already exist, and inspect their exact
   definitions, indexes, table engines, order references, and any receipt
   records. There is no migration ledger; do not infer the state from filenames.
   If either 006 column exists, do not replay its `ALTER TABLE`; apply only a
   missing change after checking the live schema.
3. Apply `database/migrations/006_order_tracking_reference.sql`. It adds the
   reference column as nullable and gives lookup-throttle records a separate
   event type. If the first `ALTER TABLE` succeeds but the second fails, leave
   the nullable column in place, inspect both tables, and apply only the
   missing `event_type` change. Do not drop either column as recovery.
4. Deploy the application files while order submissions remain paused. The
   new application writes a reference for both customer checkout and Staff
   POS orders, while the nullable column permits the existing rows to be
   backfilled. Existing HMAC tracking remains available when its secret is
   configured; new reference-based tracking does not depend on it.
5. Configure the protected DB connection for CLI PHP. First run the read-only
   preview from the project directory:

   ```powershell
   & 'C:\wamp\bin\php\php8.0.26\php.exe' scripts\backfill_order_references.php
   ```

   The default is `--dry-run`; it performs no database writes and reports
   counts for valid eight-digit references, valid 27-character legacy
   references, malformed values, duplicates, receipt conflicts, and missing
   references. It also lists each proposed order ID and reference. It preserves
   valid existing references and refuses malformed values, duplicates, or
   inconsistent receipt snapshots. If an order with a missing reference has
   consistent issued receipt records and snapshots, that original saved
   reference is restored to the order rather than generating a replacement.
   Review the complete preview and resolve every conflict before proceeding.

6. After the dry run is conflict-free, add the unique index before applying
   data changes. Migration 007 contains two separate `ALTER TABLE` statements:
   run only its `ADD UNIQUE KEY` statement at this point, after confirming the
   named index is not already present and keeping order submissions paused.
   A unique index permits multiple `NULL` values, but rejects duplicate
   existing references. Do not execute the later `NOT NULL` statement yet.
   Rerun the dry-run after adding the index to confirm the planned repair.
7. Only after review and a verified backup, explicitly approve the exact
   missing order IDs listed by the latest dry run and apply the missing-only
   repair. Replace the example IDs with the complete, exact list from the
   preview:

   ```powershell
   & 'C:\wamp\bin\php\php8.0.26\php.exe' scripts\backfill_order_references.php --apply --approve-order-ids=12,15
   ```

   `--apply` without an explicit ID list is rejected. The supplied IDs must
   exactly match the currently missing-reference orders; the utility checks
   this again under transaction locks and revalidates receipt snapshots before
   any update. The apply operation uses an InnoDB transaction, keeps existing
   references, uses bounded random collision retries for genuinely missing
   values, and rolls back the batch on failure. It does not rewrite receipt
   snapshots, order IDs, totals, statuses, timestamps, or payment history.
   Keep checkout and POS paused until the report and postconditions pass. The
   utility is CLI-only and must not be exposed through Apache.
8. After the backfill, confirm no reference is missing or invalid, then run
   only the `MODIFY COLUMN ... NOT NULL` statement from migration 007 if it is
   not already in effect. Keep the column wide enough for every preserved
   reference.

   Migration 008 shrinks the column to eight characters. **Do not apply it**
   if any retained reference is not exactly eight digits or if an associated
   receipt/snapshot still stores a longer issued value. MySQL/MariaDB DDL may
   fail or truncate depending on SQL mode/version; verify the target behavior
   on a restored test database. Retaining `VARCHAR(27)` is compatible with new
   eight-digit references and avoids invalidating legacy references.
9. Verify before reopening checkout:

   ```sql
   SELECT COUNT(*) AS missing_reference_count
   FROM orders
   WHERE order_reference IS NULL OR TRIM(order_reference) = '';

   SELECT COUNT(*) AS duplicate_reference_groups
   FROM (
       SELECT order_reference
       FROM orders
       GROUP BY order_reference
       HAVING COUNT(*) > 1
   ) AS duplicate_groups;

   SELECT order_id
   FROM orders
   WHERE NOT (
       order_reference REGEXP '^[0-9]{8}$'
       OR order_reference REGEXP '^[A-Za-z0-9_-]{27}$'
   );

   SHOW INDEX FROM orders WHERE Key_name = 'uq_orders_order_reference';
   SHOW COLUMNS FROM orders LIKE 'order_reference';
   SHOW COLUMNS FROM order_submission_log LIKE 'event_type';
   ```

   The missing-reference and invalid-format queries must return zero rows; the
   duplicate query must return no rows; the unique index must be present; and
   the column must be `NOT NULL` with sufficient width for retained values.
   When sharing diagnostic output, do not publish reference values or customer
   data; report affected order IDs only.

An 8-digit reference has a much smaller search space than the previous
cryptographic-length value. A customer reference is a lookup credential, not
proof of identity. The status API counts initial reference lookups (including
successful lookups), limits them per session and IP, and uses a high-entropy,
session-scoped polling token for subsequent refreshes. Shared IPs can still
affect legitimate traffic, and distributed attempts remain a residual risk.
The reference is separate from `order_id`; never renumber or replace order
IDs. The backfill does not modify order items, statuses, dates, totals, or
payment data. Existing session-bound HMAC status links remain supported when
the tracking secret is configured.

### Add OR-numbered receipt records

The previous receipt page generated a printout directly from an order and did
not store separate receipt issues. Migration 009 creates a receipt record and
order/item snapshot when Staff or Admin enters an OR number already issued
through the business's authorized process. The application does not generate
OR numbers or assert that its print layout meets legal or BIR requirements.
Staff removal marks a receipt removed from the Staff list while retaining its
snapshot and required reason for the Admin audit.

1. Back up the database and verify the export can be restored to a separate
   local/staging database. A project backup is not a database backup.
2. Confirm that the order reference column is present, unique, non-NULL, and
   wide enough for all preserved references. Do not require migration 008 if
   an existing issued reference uses the supported legacy format.
3. Confirm `receipt_records` does not already exist, then import
   `database/migrations/009_receipt_records.sql` once. Its
   `order_reference VARCHAR(27)` column preserves current eight-digit and
   legacy references. It creates only the new receipt table and does not
   backfill receipt records for existing orders.
   Existing orders and payment/status history remain unchanged; Staff can
   issue a receipt for a paid order when an authorized OR number is available.
4. Verify the table and constraints:

   ```sql
   SHOW TABLES LIKE 'receipt_records';
   SHOW CREATE TABLE receipt_records;
   ```

   Confirm the unique OR-number key and restrictive order/staff foreign keys.
   Exercise issue, print, Staff removal, and Admin audit in staging before
   production deployment.

#### Receipt migration recovery and rollback

If table creation fails, inspect `SHOW TABLES` and `SHOW CREATE TABLE` before
retrying. If the table exists, do not re-run the non-idempotent migration.
If application code must be rolled back, retain the table and its data while
restoring the prior code, then verify the restored code does not query it.
Dropping the table destroys OR-number associations, snapshots, and removal
reasons; there is no automatic rollback statement. Consider a separately
approved rollback only after exporting and verifying the receipt data. Never
delete orders, order items, or payment records as part of receipt cleanup.

#### Recovery and rollback

If the dry run reports malformed, duplicate, or receipt-inconsistent data,
do not use `--apply` and do not run migrations 007 or 008. Investigate only in
staging with the DBA and obtain approval before changing any issued reference.
If an apply operation fails, its transaction rolls back; verify the data and
rerun a dry run before retrying. Never change an internal order ID or rewrite
receipt snapshots to make a migration succeed. If migration 007 or 008 fails
partway through, inspect the index and exact column type, then apply only a
verified missing statement. Do not reduce the column width while legacy
references remain.

If application code must be rolled back after migration, keep the reference
values and unique index. First pause order submission, restore the previous
application files, then make the column nullable so the previous insert code
can continue to operate:

```sql
ALTER TABLE orders MODIFY order_reference VARCHAR(27) NULL DEFAULT NULL;
```

Do not drop `order_reference`, its unique index, or `event_type`; doing so can
discard customer tracking history or mix lookup attempts into submission
throttling. Re-enable checkout only after the rollback is verified.

Verify the migration tables and inventory column exist before deploying the
application code:

```sql
SHOW TABLES LIKE 'login_attempts';
SHOW TABLES LIKE 'order_submission_log';
SHOW TABLES LIKE 'inventory_stock_logs';
SHOW COLUMNS FROM menu_items LIKE 'stock_quantity';
SHOW COLUMNS FROM menu_items LIKE 'image_filename';
SHOW TABLES LIKE 'order_submission_requests';
```

The inventory migration initializes existing items with `stock_quantity = 0`.
Record verified on-hand quantities in Stock & Items before accepting sales;
initial quantities are intentionally not guessed.
The menu-image migration only adds a nullable filename column; it does not
modify menu content or create image files. Uploaded files are kept outside the
public web root at `C:\wamp\private\kape_cup_pos_uploads\menu` by default.
Configure `APP_UPLOAD_DIR` to another absolute writable path if needed, and
grant write access only to the Apache service account and administrators.
The PHP upload limits must allow files larger than 5 MB for the application's
5 MB image limit (for example, `upload_max_filesize = 6M` and
`post_max_size = 7M`); restart Apache after changing its `php.ini`.

To roll back, first put the application in maintenance mode or otherwise stop
requests that use the tables. This deletes the stored throttle history. Run
the rollback statements in reverse migration order from the selected database:

```sql
DROP TABLE order_submission_log;
DROP TABLE login_attempts;
```

To roll back migration 004, first ensure every `image_filename` value is backed
up or cleared, and then run `ALTER TABLE menu_items DROP COLUMN image_filename;`.
This leaves uploaded files untouched; remove them only after verifying that
the application no longer references them. These are the rollback lines
recorded in the migration files. Take and verify a backup before any
production migration or rollback.

## Seed menu data

After importing the schema, run `database/seed_categories.sql` to insert the
eight approved categories. `database/seed_menu_development_only.sql` is
strictly for development and contains illustrative names and prices, not the
actual café menu. Never import that sample menu into production.
