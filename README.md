# msoft — Greenfield (PHP 8.2)

New application code. **Same MySQL schema** as the legacy system; legacy code is **reference only**.

## Your project path (new code)

```
C:\Users\prasa\OneDrive\Company\Greenfield
```

Upload this entire folder (except `vendor/` — run `composer install` on the server, or upload `vendor` too) to the host; point the web root to **`public/`**.

## Legacy reference code path (do not delete by mistake)

The **reference-only** copy of the old app (for comparing flows, SQL, flags) is **not** inside this folder. On the machine where it was analyzed, it lived at:

```
C:\Users\prasa\Downloads\cdp-data-2026-05-06-01-15-17\home\greenfarmaccount\public_html\msoft
```

If you moved or renamed that download, search your PC for `library.php` inside an `msoft` folder, or open **`REFERENCE_LEGACY_PATH.txt`** in this repo and edit it to the correct path.

**Tip:** Keep the old `msoft` folder somewhere outside OneDrive sync if size is an issue, but bookmark the path so you never delete it until the greenfield app is live and verified.

## Setup

1. Install PHP 8.2+ and Composer locally (or on the server).
2. Copy **`.env.example`** to **`.env`** in this directory.
3. Set **`DB_PASSWORD`** and **`DB_HOST`**. The database is on **cPanel**, not your PC — see **`HOSTING_DATABASE.md`** (`localhost` only when PHP runs on the server; from your PC use the remote MySQL hostname + Remote MySQL IP).
4. Run:

   ```bash
   cd C:\Users\prasa\OneDrive\Company\Greenfield
   composer install
   ```

5. For local quick test (PHP built-in server):

   ```bash
   php -S localhost:8080 -t public
   ```

   Open http://localhost:8080/ — you should see a DB ping or a clear error if `.env` is missing.

## No customer communications (SMTP / SMS / WhatsApp)

- **`.env.example`** sets **`APP_DISABLE_OUTBOUND_COMMUNICATIONS=1`** by default.
- Keep **`APP_DISABLE_OUTBOUND_COMMUNICATIONS=1`** on staging and until you explicitly enable production mail.
- Any future mail/SMS/WhatsApp code must call **`App\Outbound\OutboundCommunicationsGuard::assertMaySend('smtp')`** (or similar) before sending; with the flag on, that will **throw** and prevent accidental sends to real customers whose data is in the DB.

Do **not** configure SMTP credentials on staging while testing with production-shaped data unless you intend to send mail.

## Security

- **Never commit `.env`** (it is in `.gitignore`).
- Rotate DB passwords if they were ever shared in chat.

## Composer

```bash
composer install
```
