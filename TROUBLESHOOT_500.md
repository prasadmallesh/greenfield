# 500 Internal Server Error after `.htaccess`

## 1. Restore the site immediately

In **File Manager** (or FTP), **rename or delete** the file:

`public_html/.htaccess`

Example: rename to `htaccess.disabled`. Reload the site — the generic 500 should go away (you may see a directory listing or default page until step 2 is done).

## 2. Prefer this fix (no root `.htaccess` needed)

**cPanel → Domains** → your domain → **Document Root**

Set it to the **`public`** folder, e.g.:

`.../public_html/public`

Save. Put **no** `.htaccess` in `public_html` unless your host requires one.

The project already includes **`public/.htaccess`** for clean routing when the document root is `public/`.

## 3. If you must keep `.htaccess` in `public_html`

- Replace it with the latest **`root.htaccess.example`** from this repo (uses `[END]` to reduce loops).
- If it **still** returns 500, your host may disallow some rewrite rules — **stop using root `.htaccess`** and use step 2 only.

## 4. Check error logs

**cPanel → Metrics → Errors** (or **Raw Access / Error Log**) shows the exact Apache/LiteSpeed line (e.g. invalid `RewriteRule`, `RewriteBase`, or `Options`).
