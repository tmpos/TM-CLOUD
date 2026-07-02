# Deploying TMPBase on Hostinger

This layout is prepared for Hostinger Web and Cloud hosting, where the domain
document root is normally `public_html` and cannot be changed.

## 1. Configure PHP

In hPanel, open the website dashboard and select **PHP Configuration**:

- Use PHP 8.2 or PHP 8.3.
- Enable `pdo`, `pdo_sqlite`, `sqlite3` and `fileinfo`.
- Set `memory_limit` to at least `128M`.
- Set `upload_max_filesize` and `post_max_size` according to the desired upload limit.

Do not use PHP 8.1 or older for a new production installation.

## 2. Upload the project

Upload the complete contents of this repository directly into:

```text
domains/your-domain.tld/public_html/
```

The root `.htaccess` keeps application code, dependencies, configuration,
uploads and SQLite databases inaccessible from HTTP. Do not upload only the
contents of the `public/` directory.

Expected structure:

```text
public_html/
  .htaccess
  app/
  config/
  database/
  public/
  storage/
  composer.json
  .env
```

Enable hidden files in Hostinger File Manager so `.htaccess` and `.env` are
uploaded.

## 3. Composer is optional

Hostinger Premium, Business and Cloud plans provide SSH and Composer 2. Connect
through SSH and optionally run:

```bash
cd domains/your-domain.tld/public_html
composer2 install --no-dev --optimize-autoloader
```

If the plan has no SSH access, no additional action is required. TMPBase
automatically uses its included Flight-compatible router when `vendor/` is
absent. Uploading the complete project through File Manager is sufficient.

Using Composer remains recommended when SSH is available because it loads the
official FlightPHP package.

## 4. Create the environment file

Duplicate `.env.example` as `.env` and edit it:

```dotenv
APP_NAME=TMPBase
APP_URL=https://your-domain.tld
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
MAX_UPLOAD_MB=10
RATE_LIMIT_PER_MINUTE=120
```

Do not add a trailing slash to `APP_URL`.

## 5. Set permissions

The PHP process must be able to create databases, backups and uploads inside
`storage/`. In Hostinger File Manager, use writable owner permissions for this
directory. Start with `755`; use `775` only if PHP cannot write with `755`.
Do not use `777`.

With SSH:

```bash
chmod -R 755 storage
```

## 6. Run the web installer

Visit:

```text
https://your-domain.tld
```

You will be redirected to:

```text
https://your-domain.tld/install
```

The installer:

- Checks PHP, PDO SQLite, Fileinfo and directory permissions.
- Detects the application URL.
- Creates the production `.env` file.
- Initializes `storage/tmpbase.sqlite`.
- Creates the first administrator.
- Creates `storage/installed.lock` and disables itself.

TMPBase has no default credentials. After installation, visiting `/install`
redirects to the login screen.

## 7. Verify protection

These URLs must return `403 Forbidden`:

```text
https://your-domain.tld/.env
https://your-domain.tld/composer.json
https://your-domain.tld/storage/tmpbase.sqlite
https://your-domain.tld/app/Core/Database.php
```

Also verify login, project creation, table creation, an API request, file
upload and a manual backup.

## Updating

Back up `storage/` before replacing application files. Do not overwrite
`.env` or delete `storage/`. If Composer is available, run:

```bash
composer2 install --no-dev --optimize-autoloader
```
