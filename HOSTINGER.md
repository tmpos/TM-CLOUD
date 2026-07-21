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

## 3. Composer is required for mail and online PDFs

Before installing dependencies, enable the PHP extensions `pdo_sqlite`,
`fileinfo`, `mbstring` and `gd` from the Hostinger PHP configuration panel.

Hostinger Premium, Business and Cloud plans provide SSH and Composer 2. Connect
through SSH and run:

```bash
cd domains/your-domain.tld/public_html
composer2 install --no-dev --optimize-autoloader
```

If the plan has no SSH access, execute `composer install --no-dev
--optimize-autoloader` locally in an environment with the same PHP version and
upload the complete generated `vendor/` directory. The fallback router can
serve basic routes without Composer, but server mail (PHPMailer) and online PDF
generation (mPDF) require their Composer dependencies.

## 4. Create the environment file

Duplicate `.env.example` as `.env` and edit it:

```dotenv
APP_NAME=TMPBase
APP_URL=https://your-domain.tld
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
MAX_UPLOAD_MB=10
PROJECT_STORAGE_MAX_MB=1024
RATE_LIMIT_PER_MINUTE=120
CORS_ALLOWED_ORIGINS=https://app.your-domain.tld
TRUSTED_PROXIES=
BACKUP_RETENTION_COUNT=20
BACKUP_RETENTION_DAYS=90
BACKUP_MAX_MB_PER_PROJECT=5120
MAIL_ENABLED=true
MAIL_HOST=smtp.your-provider.tld
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=mailer@your-domain.tld
MAIL_PASSWORD=store-this-only-on-the-server
MAIL_FROM_ADDRESS=mailer@your-domain.tld
MAIL_FROM_NAME=TMPBase
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
