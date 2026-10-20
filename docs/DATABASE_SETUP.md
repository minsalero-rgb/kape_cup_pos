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
This installation uses `C:\wamp\private\kape_cup_pos.php`; keep its ACL
restricted to the Windows account running Apache, Administrators, and SYSTEM.
It must return an array with `DB_HOST`, `DB_NAME`, `DB_USERNAME`,
`DB_PASSWORD`, `ORDER_TRACKING_SECRET`, and `APP_TIMEZONE` string values. Set
`APP_PRIVATE_CONFIG` to use a different absolute path. Never place this file
inside the project or serve it from Apache.
For this installation, update its `DB_NAME` value to `db_kape_cup_pos` only
after the schema and copied data have been verified in that database.

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
| `ORDER_TRACKING_SECRET` | Yes for tracked order submission | Private HMAC key of at least 32 bytes. |
| `APP_TIMEZONE` | No | PHP timezone identifier; defaults to `Asia/Manila`. |
| `APP_UPLOAD_DIR` | No | Absolute writable directory outside Apache's document root for uploaded menu images. Defaults to `C:\wamp\private\kape_cup_pos_uploads\menu`. |
| `INITIAL_ADMIN_PASSWORD` | CLI setup only | Temporary password input to `scripts/create_admin.php`; not an Apache runtime setting. |

Generate a strong order-tracking key locally using the WAMP PHP executable
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
check what PHP receives from Apache, temporarily request a localhost-only
diagnostic PHP script that prints only `set`/`missing` for the variable names
(never their values), then delete the script. Check `INITIAL_ADMIN_PASSWORD`
only in the CLI environment used for the one-time setup.

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
