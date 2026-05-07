# “could not find driver” / PDO cannot use MySQL

That message means PHP’s **PDO MySQL driver** is not loaded. The app needs the **`pdo_mysql`** extension.

## cPanel (typical)

1. Log in to **cPanel**.
2. Open **Select PHP Version** (sometimes **MultiPHP INI Editor** or **PHP Extensions** depending on the skin).
3. Choose **PHP 8.2** (or newer) for this domain.
4. Click **Extensions** (or a checkbox list).
5. Enable:
   - **`pdo_mysql`** (required for this project)
   - Optionally **`mysqli`** if other old scripts need it — not required for Greenfield’s PDO-only bootstrap.
6. **Save**, then wait a minute and reload the site.

## LiteSpeed / CloudLinux

Same idea: find **PHP Selector** / **Extensions** for the account and turn on **`pdo_mysql`**.

## Verify (SSH optional)

```bash
php -m | findstr /i pdo_mysql
```

You should see `pdo_mysql` in the list.

## Still failing?

- Confirm you edited PHP for the **same domain** that serves the site (not only CLI PHP).
- Some hosts need a **full account** restart or ticket to enable extensions — contact support with: “Please enable **pdo_mysql** for PHP 8.2 on this account.”
