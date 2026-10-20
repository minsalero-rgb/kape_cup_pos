# Creating the First Admin Account on Windows

The first Admin account is created from the project root with
[`scripts/create_admin.php`](../scripts/create_admin.php). The script is
command-line only, requires the `staff` table to exist, and refuses to create a
second Admin account or reuse an existing username.

## Confirm the database configuration

Before creating an account, confirm that `db_kape_cup_pos` exists and the schema
has been imported there. Configure `DB_HOST`, `DB_NAME`, `DB_USERNAME`,
and `DB_PASSWORD` in the environment used by the CLI PHP process. The
application requires a dedicated MySQL account and a non-empty password; it
will not connect as `root`. See
[`DATABASE_SETUP.md`](DATABASE_SETUP.md) for setup instructions. Do not put
real database passwords or other credentials in project files.

If the database connection needs different settings, set the appropriate
environment variables in the PowerShell session before running the setup.
Supply any real database password securely through your environment or WAMP's
PHP/Apache configuration; do not type it into a command or save it in a file.

## Select and check WAMP's CLI PHP

Open PowerShell and change to the project root:

```powershell
Set-Location 'C:\wamp\www\kape_cup_pos'
$php = 'C:\wamp\bin\php\php8.0.26\php.exe'
```

The path above matches the PHP 8.0.26 currently configured for this WAMP
installation. If WAMP uses another PHP version, select that version's
`php.exe`; the CLI and Apache PHP may be configured separately.

Check which `php.ini` the CLI loads and confirm that PDO MySQL is enabled:

```powershell
& $php --ini
& $php --ri pdo_mysql
```

The second command should display the PDO MySQL driver information. If it
reports that the extension is unavailable, use the `php.ini` path shown by
`--ini` for this same CLI PHP version. Enable `pdo_mysql` there, then rerun the
check. Do not change a different PHP version's configuration.

## Set the initial password and run the script

The password must be at least 12 characters and no more than 72 UTF-8 bytes.
Enter it at the secure PowerShell prompt below; do not put a real password in a
command, project file, or source-controlled configuration. The script also
prompts for the Admin's full name and username.

```powershell
$securePassword = Read-Host 'Initial Admin password' -AsSecureString
$passwordPointer = [IntPtr]::Zero
try {
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $env:INITIAL_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    & $php '.\scripts\create_admin.php'
    if ($LASTEXITCODE -ne 0) {
        throw 'Admin account creation failed. Review the script output before retrying.'
    }
}
finally {
    Remove-Item Env:\INITIAL_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
    Remove-Variable securePassword, passwordPointer -ErrorAction SilentlyContinue
}
```

The password is passed to the script through the temporary
`INITIAL_ADMIN_PASSWORD` environment variable, which the script clears after
reading. The PowerShell block removes the variable from the parent shell and
clears its temporary secure-string buffer when the process ends.

## Reset a locked-out Admin password

If you cannot sign in to reset an Admin password from Staff Accounts, run
[`scripts/reset_admin_password.php`](../scripts/reset_admin_password.php) from
the project root using the same configured WAMP CLI PHP and database
environment. This CLI-only tool resets the password for an existing active
Admin account; it does not create an account or display a password. The new
password must be at least 12 characters and no more than 72 UTF-8 bytes.

Enter the password at a secure PowerShell prompt. Pass the Admin username as
the script's argument. Never put the password in a command, project file, or chat.

```powershell
$securePassword = Read-Host 'New Admin password' -AsSecureString
$passwordPointer = [IntPtr]::Zero
try {
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $env:ADMIN_RESET_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    & $php '.\scripts\reset_admin_password.php' 'Admin'
    if ($LASTEXITCODE -ne 0) {
        throw 'Admin password reset failed. Review the script output before retrying.'
    }
}
finally {
    Remove-Item Env:\ADMIN_RESET_PASSWORD -ErrorAction SilentlyContinue
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
    Remove-Variable securePassword, passwordPointer -ErrorAction SilentlyContinue
}
```
