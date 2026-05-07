# Upload to cPanel (domain already points to server)

Yes — upload this project to your hosting account.

## 1. Document root (important)

Point the domain (or subdomain) **document root** to the **`public`** folder inside Greenfield, **not** the project root.

Examples:

- If you upload to `~/public_html/msoft-new/`, set document root to `~/public_html/msoft-new/public/`
- Wrong: document root = `~/public_html/msoft-new/` (would expose `src/`, `composer.json`, and risk `.env` exposure)

In cPanel: **Domains** → your domain → **Document Root** (or **Subdomains** → document root).

### If everything is already inside `public_html` (your case)

If `bootstrap.php`, `public/`, `src/`, and `vendor/` sit **directly in** `public_html/`:

**Option A (best):** cPanel → **Domains** → your domain → set **Document Root** to:

`public_html/public`

(or the full path your host shows, ending in `/public`).

**Option B (fallback only):** Leave document root as `public_html`, then add **`.htaccess`** routing into `public/`.

1. Copy **`root.htaccess.example`** to **`public_html/.htaccess`**.
2. If you see **500 Internal Server Error**, **delete or rename `.htaccess` immediately** to bring the site back, then use **Option A** (document root → `public`) instead. Root rewrites are fragile on some hosts (LiteSpeed, `RewriteBase`, subfolders).
3. If the site is in a **subfolder**, edit **`RewriteBase`** inside that file (see comments in `root.htaccess.example`).

Option A is strongly preferred over Option B.

## 2. What to upload

Upload **everything** under `Greenfield` **except** you can skip `vendor` and run Composer on the server if SSH is available:

- Upload: `public/`, `src/`, `config/`, `bootstrap.php`, `composer.json`, `composer.lock`
- Either upload **`vendor/`** as well, **or** SSH into the account and run:

  ```bash
  cd ~/path/to/Greenfield
  composer install --no-dev --optimize-autoloader
  ```

## 3. `.env` on the server

On the server, create **`.env`** next to `bootstrap.php` (same level as on your PC) with at least:

```env
APP_ENV=production
APP_DISABLE_OUTBOUND_COMMUNICATIONS=1

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_password
```

`DB_HOST=localhost` is normal when PHP and MySQL are both on cPanel.

Do **not** commit `.env` to git; uploading via FTP/SFTP to the server only is fine.

## 4. PHP version

In cPanel **MultiPHP** (or **Select PHP Version**), set the domain to **PHP 8.2+**.

## 4b. PHP extensions (required if you see “could not find driver”)

The app uses **PDO + MySQL**. Enable the **`pdo_mysql`** extension for that PHP version:

**cPanel → Select PHP Version → Extensions →** check **`pdo_mysql`** → Save.

Details: **`ENABLE_PHP_MYSQL.md`**.

## 5. HTTPS

Use cPanel **SSL/TLS** so the site loads over HTTPS.

## 6. Quick check

Open `https://your-domain/` — you should see the “Greenfield app is running” page and a successful DB line if `.env` is correct.
