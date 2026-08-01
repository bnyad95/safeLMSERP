# SafeLMS ERP cPanel Deployment

This deployment uses the MySQL or MariaDB database created in cPanel. It does
not use the local SQLite file and does not load demo users or demo academic
data.

## Hosting requirements

- PHP 8.2 or newer
- MySQL 8.0+ or MariaDB 10.2+
- Composer 2
- PHP extensions: `pdo_mysql`, `ctype`, `dom`, `fileinfo`, `filter`, `iconv`,
  `json`, `mbstring`, `openssl`, `session`, `tokenizer`, and `xml`
- Apache `mod_rewrite`
- HTTPS certificate

The Student Rankings query uses SQL window functions, so older MySQL or
MariaDB releases are not supported.

## 1. Create the cPanel database

In **cPanel > MySQL Databases**:

1. Create a database, for example `account_safelms`.
2. Create a database user with a strong, unique password.
3. Add that user to the database and grant **ALL PRIVILEGES**.
4. Keep the complete database and user names. cPanel normally adds the account
   prefix automatically.

Do not import `database/database.sqlite` into MySQL.

## 2. Upload the application

On the development computer, create the upload ZIP:

```powershell
powershell -ExecutionPolicy Bypass -File deploy/build-cpanel-package.ps1
```

Upload and extract `dist/SafeLMS-cPanel.zip` in the application directory.
The archive includes `vendor` and compiled `public/build` assets, so it can also be
used when Node.js is unavailable on cPanel.

To create a private ZIP with the MySQL credentials already configured, run:

```powershell
powershell -ExecutionPolicy Bypass -File deploy/configure-cpanel-package.ps1 `
  -DatabaseName "CPANEL_DATABASE" `
  -DatabaseUsername "CPANEL_DATABASE_USER" `
  -DatabasePassword "DATABASE_PASSWORD" `
  -AppUrl "https://your-domain.example"
```

This creates `dist/SafeLMS-private-app.zip` with a fresh production
`APP_KEY`. Treat that archive as confidential because it contains the database
password. The generated `.env` is not retained in the project directory.

Recommended layout:

```text
/home/CPANEL_USER/safelms/          Laravel application
/home/CPANEL_USER/safelms/public/   Domain document root
```

Upload the project to `/home/CPANEL_USER/safelms`, then set the domain's
document root to `/home/CPANEL_USER/safelms/public`.

This is important: the domain must expose only the `public` directory. Never
set the document root to the application root because `.env`, source code, and
storage must remain private.

If cPanel does not let the primary domain use that document root, keep the
Laravel application outside `public_html`, copy only the contents of `public`
into `public_html`, and update the two paths in `public_html/index.php` to
point to the application:

```php
require __DIR__.'/../safelms/vendor/autoload.php';
$app = require_once __DIR__.'/../safelms/bootstrap/app.php';
```

## 3. Configure production

In the application directory:

```bash
cp .env.cpanel.example .env
```

Edit `.env` and replace:

- `APP_URL`
- `DB_HOST` (usually `localhost`, but use the value from cPanel)
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- SMTP settings when outbound email is ready (the template safely logs mail by default)

Use the full cPanel-prefixed database and username. Do not wrap a database
password in quotes unless it contains spaces; if it contains `#`, wrap it in
double quotes.

Generate the application key:

```bash
php artisan key:generate --force
```

Never copy the local `.env` to production.

## 4. Install and initialize

### Shared cPanel without Terminal

Use the private configured package created by
`deploy/configure-cpanel-package.ps1`. After extracting it and setting the
domain document root to `public`:

1. Open `https://your-domain.example/setup`.
2. Enter the one-time code from `dist/SafeLMS-INSTALLATION.txt`.
3. Enter the first Super Administrator's name, email, and password.
4. Select **Install and create administrator**.

The browser installer runs migrations, installs roles and permissions, creates
the administrator, signs in, and disables `/setup`. Do not delete
`storage/app/private/installed`.

Immediately after installation, set `INSTALLER_ENABLED=false` in `.env`, delete
the uploaded installation ZIP, and delete the local private ZIP and installation
code file. If credentials were shared through chat, screenshots, or email,
rotate the database password in cPanel and update `.env` before adding real data.

The web server must be able to write to `storage` and `bootstrap/cache`. In
cPanel File Manager, set those directories to `755` first. If the installer
reports a permissions problem, use `775`.

#### Main domain locked to public_html

When cPanel does not allow changing the main domain document root:

```text
/home/CPANEL_USER/safelms_app/   Private Laravel application
/home/CPANEL_USER/public_html/   Public web files only
```

1. Extract `SafeLMS-private-app.zip` inside `safelms_app`.
2. Extract `SafeLMS-public_html.zip` inside `public_html`.
3. Confirm `public_html/index.php` and `public_html/.htaccess` exist.
4. Remove any default `index.html` from `public_html`.
5. Open `/setup` and use the generated one-time installation code.

Build the adjusted public package locally with:

```powershell
powershell -ExecutionPolicy Bypass -File deploy/build-shared-cpanel-public.ps1
```

Never extract the complete private application directly into `public_html`.

### cPanel with Terminal

Run these commands from the application directory:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder --force
php artisan safelms:create-super-admin admin@your-domain.example
php artisan storage:link
php artisan optimize
```

The Super Administrator command asks for the password without displaying it.
Do not run `php artisan db:seed` without the class name on production because
the default seeder creates demonstration accounts and data.

The frontend is already compiled into `public/build`; Node.js is not required
on cPanel. Rebuild locally with `npm ci && npm run build` before uploading a
new frontend release.

## 5. Permissions

The cPanel account must be able to write to:

```bash
chmod -R 775 storage bootstrap/cache
```

On hosts that use the same account for PHP and File Manager, `755` directories
and `644` files may be sufficient. Never use `777`.

If `php artisan storage:link` is blocked by the host, create a symbolic link in
cPanel File Manager from `public/storage` to `storage/app/public`, or ask the
host to enable symlinks.

## 6. Cron and queues

The default cPanel package can use `QUEUE_CONNECTION=sync` when no worker is
available. For better response times, use `QUEUE_CONNECTION=database` and add a
cPanel cron entry that drains queued work once per minute:

```cron
* * * * * cd /home/CPANEL_USER/safelms && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

Add this cron job for scheduled performance warnings and other scheduled tasks:

```cron
* * * * * cd /home/CPANEL_USER/safelms && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP CLI path shown by cPanel. It may differ from
`/usr/local/bin/php`.

## 7. Verify the deployment

Run:

```bash
php artisan about
php artisan migrate:status
php artisan route:list --except-vendor
```

Then check:

- `https://your-domain.example/up` returns a successful response.
- Login works with the Super Administrator account.
- A file upload and download works.
- `storage/logs/laravel.log` has no new errors.
- `APP_DEBUG=false` is shown by `php artisan about`.

## Updating later

Back up the MySQL database and `storage/app` first, then:

```bash
php artisan down
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan up
```

### Shared cPanel without Terminal

1. Back up the production database and the private application's `.env` and
   `storage/app` directories.
2. Extract the new private package over the existing private application. The
   package does not contain `.env`, so the production settings remain in place.
3. Extract the new public package over `public_html`.
4. In cPanel **Cron Jobs**, add this command temporarily (replace the account
   path and PHP binary when cPanel shows different values):

```bash
cd /home/CPANEL_USER/safelms_app && /usr/local/bin/php artisan migrate --force && /usr/local/bin/php artisan files:migrate-protected && /usr/local/bin/php artisan optimize
```

Set it to run once at the next available minute. After it runs, confirm the
site opens and inspect `storage/logs/laravel.log`, then delete this temporary
cron entry. Keep the normal scheduler and queue-worker cron entries described
above.

Do not run `migrate:fresh` on production. It deletes all cPanel database data.
