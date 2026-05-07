# Database is on the server (cPanel), not on your PC

MySQL was created **only on hosting**. That means:

## A) Run the app **on the server** (recommended)

After you upload Greenfield and point the site to `public/`, PHP runs **next to** MySQL on the same machine. Then in `.env` use:

```env
DB_HOST=localhost
```

(or `127.0.0.1` — many cPanel setups treat them the same on-server.)

Your `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` stay as cPanel gave them.

## B) Run `php -S` **on your Windows PC** but DB stays on the server

`localhost` in `.env` means “MySQL on **this** computer” — your PC, where the database **does not** exist.

1. In **cPanel → MySQL® Databases** (or similar), find the **MySQL hostname** shown for remote connections. It is **not** always the same as your website URL; copy exactly what cPanel shows.
2. Open **cPanel → Remote MySQL®** (or “Access Hosts”) and **add your current public IP** (search “what is my IP” from the same network you use for development).
3. In `.env` on your PC set:

   ```env
   DB_HOST=the-exact-hostname-from-cpanel.example.com
   ```

4. Keep port `3306` unless your host says otherwise.

Some hosts block remote MySQL entirely; then you must test on the server (A) or use an SSH tunnel (advanced).

## Summary

| Where PHP runs | Typical `DB_HOST`        |
|----------------|---------------------------|
| cPanel server  | `localhost`               |
| Your home PC   | Hostname from cPanel + Remote MySQL IP allowed |
