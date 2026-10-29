# DocEditor — Invoice & Quote Editor

Refactored to a clean MVC architecture with MySQL, PSR-4 autoloading,
and a single front-controller entry point.

---

## Architecture

```
doceditor/
├── app/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── DocumentApiController.php   — JSON API for documents
│   │   │   ├── CustomerApiController.php   — JSON API for customers
│   │   │   └── ItemApiController.php       — JSON API for items
│   │   ├── DocumentController.php          — serves HTML pages
│   │   ├── CustomerController.php
│   │   └── ItemController.php
│   ├── Core/
│   │   ├── Database.php    — PDO MySQL singleton
│   │   ├── Env.php         — .env loader (no-composer fallback)
│   │   ├── Request.php     — GET/POST/JSON body wrapper
│   │   ├── Response.php    — json(), view(), error() helpers
│   │   └── Router.php      — front-controller router
│   ├── Models/
│   │   ├── Document.php    — value object + fromRow() + toArray()
│   │   ├── Customer.php
│   │   └── Item.php
│   ├── Repositories/
│   │   ├── DocumentRepository.php  — all SQL for documents
│   │   ├── CustomerRepository.php
│   │   └── ItemRepository.php
│   └── Services/
│       ├── DocumentService.php  — business logic, auto-upsert customer,
│       │                          document numbering
│       ├── CustomerService.php
│       └── ItemService.php
├── config/
│   └── app.php             — central config (reads .env via Env::get())
├── database/
│   ├── migrate.php         — CLI migration runner
│   └── migrations/
│       ├── 001_create_tables.sql               — MySQL schema
│       └── 002_sqlite_to_mysql_import.php      — one-time SQLite → MySQL
├── public/
│   ├── index.php           — single entry point (all HTTP requests)
│   ├── .htaccess           — mod_rewrite → index.php
│   └── assets/             — CSS, logo.png (copy from old app)
├── resources/
│   └── views/
│       ├── layouts/
│       │   ├── app.php     — shared page shell (topbar + toast)
│       │   └── editor.php  — editor shell (full-height, extra toolbar buttons)
│       ├── partials/
│       │   ├── topbar.php           — auto-highlights active nav link
│       │   ├── editor_body.php      — split-panel editor HTML
│       │   ├── editor_logic.php     — all editor JavaScript
│       │   └── document_render.php  — A4 invoice HTML (PHP template)
│       ├── documents/
│       │   ├── index.php        — document history page
│       │   ├── editor.php       — editor page (includes partials)
│       │   ├── preview.php      — print-ready A4 (no layout)
│       │   └── documentation.php
│       ├── customers/
│       │   └── index.php
│       └── items/
│           └── index.php
├── routes/
│   └── web.php             — all routes in one file
├── .env                    — credentials (never commit)
├── .env.example            — safe template to commit
├── .gitignore
├── bootstrap.php           — loads .env, autoloader, error handling
└── composer.json           — only vlucas/phpdotenv
```

---

## Request lifecycle

```
Browser → public/index.php
            └── bootstrap.php       (load .env, autoloader)
            └── routes/web.php      (register routes on Router)
            └── Router::dispatch()
                  └── matches URI → Controller::action()
                        └── Service  (business logic)
                              └── Repository  (SQL / PDO)
                                    └── Model  (value object)
                        └── Response::json() or Response::view()
                              └── resources/views/...php  (rendered)
```

---

## REST API routes

| Method | Path | Action |
|--------|------|--------|
| GET  | `/api/documents`               | List documents (search: `?q=`, `?type=`) |
| GET  | `/api/documents/default`       | Default template (`?type=INVOICE`) |
| GET  | `/api/documents/:id`           | Get one document |
| POST | `/api/documents`               | Create document |
| POST | `/api/documents/:id`           | Update document |
| POST | `/api/documents/:id/delete`    | Delete document |
| POST | `/api/documents/:id/duplicate` | Duplicate document |
| GET  | `/api/numbers/next`            | Consume next number (`?type=INVOICE`) |
| GET  | `/api/numbers/peek`            | Peek next number without consuming |
| GET  | `/api/customers`               | List customers (`?q=`) |
| GET  | `/api/customers/:id`           | Get one customer |
| POST | `/api/customers`               | Create customer |
| POST | `/api/customers/:id`           | Update customer |
| POST | `/api/customers/:id/delete`    | Delete customer |
| GET  | `/api/items`                   | List items (`?q=`) |
| GET  | `/api/items/:id`               | Get one item |
| POST | `/api/items`                   | Create item |
| POST | `/api/items/:id`               | Update item |
| POST | `/api/items/:id/delete`        | Delete item |

---

## Setup

### Requirements
- PHP 8.1+  with `pdo_mysql` extension
- MySQL 8.0+ (or MariaDB 10.5+)
- Apache with `mod_rewrite` enabled, OR Nginx with rewrite config below

### 1. Clone / copy files

```bash
cp -r doceditor/ /var/www/html/doceditor
cd /var/www/html/doceditor
```

### 2. Copy assets from the old app

```bash
cp path/to/old/assets/editor.css    public/assets/
cp path/to/old/assets/document.css  public/assets/
cp path/to/old/assets/logo.png      public/assets/
```

### 3. Configure environment

```bash
cp .env.example .env
nano .env
```

Fill in your MySQL credentials:
```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=doceditor
DB_USERNAME=doceditor_user
DB_PASSWORD=your_strong_password
```

### 4. Install dependencies (optional but recommended)

```bash
composer install
```

If Composer is not available, the built-in `Env` class and PSR-4 fallback
loader in `bootstrap.php` handle everything with zero dependencies.

### 5. Create MySQL user and database

```sql
CREATE USER 'doceditor_user'@'localhost' IDENTIFIED BY 'your_strong_password';
CREATE DATABASE doceditor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON doceditor.* TO 'doceditor_user'@'localhost';
FLUSH PRIVILEGES;
```

### 6. Run migrations

```bash
php database/migrate.php
```

### 7. (Optional) Import data from old SQLite app

```bash
php database/migrations/002_sqlite_to_mysql_import.php /path/to/old/db/documents.sqlite
```

### 8. Point your web server at `public/`

**Apache** — set `DocumentRoot` to the `public/` directory.
The `.htaccess` handles rewrites.

**Nginx:**
```nginx
server {
    root /var/www/html/doceditor/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
}
```

**PHP built-in server (local dev):**
```bash
php -S localhost:8000 -t public/
```

### 9. Open the app

```
http://localhost:8000/documents
```

---

## Key design decisions

**No framework.** 4 core classes (`Router`, `Response`, `Request`, `Database`)
replace what Laravel would provide. Total framework code: ~250 lines.

**Repository pattern.** All SQL lives in `Repositories/`. Controllers and
Services never touch PDO directly. Swapping MySQL for another DB means
editing only the Repository files.

**Models as value objects.** Models have no methods that touch the database —
they are plain data containers. `fromRow()` maps a DB row; `toArray()` serialises
for JSON. All mutation goes through Services → Repositories.

**Services own business logic.** Auto-upserting a customer when saving a
document, generating document numbers, stripping logo data from exports — all
of this lives in `DocumentService`, not in the controller.

**Single entry point.** Every HTTP request hits `public/index.php`.
No more `api.php?action=X` — URLs are clean REST paths.

**Views are plain PHP.** No templating engine. Layouts use output buffering
(`Response::view()` captures the view into `$content`, then includes the layout).

---

## Authentication

Login / logout only — **there is no registration page.** Users are created by you.

| What | How |
|---|---|
| Sign in | `/login` (email + password). Every other page and every `/api/...` route requires it. |
| Sign out | "Log out" button in the top bar (POST + CSRF). |
| Profile | `/profile` — change name, email and password. Changing the email or the password asks for the current password. |
| Create a user / reset a forgotten password | `php database/create_user.php` (see below) |

**Passwords** are stored with `password_hash()` (bcrypt), 10–72 characters. Changing a password signs out every
*other* device (`users.auth_version`).

**Protection built in:** sessions are HttpOnly + SameSite=Lax (+ Secure on HTTPS), regenerated on login; CSRF token on every
POST (forms and `fetch()`); login throttling (5 failed attempts per email+IP or 20 per IP → locked for ~15 min);
generic "Invalid email or password" message with constant-time-style checks; open-redirect-safe `next=`; idle timeout
(`SESSION_LIFETIME`, default 120 min); security headers (X-Frame-Options, nosniff, Referrer-Policy, HSTS on HTTPS).

### Staying signed in (remember-me devices)

The sign-in page has **“Keep me signed in on this device”** (ticked by default). A remembered browser is signed back in
silently whenever its PHP session is gone (browser restarted, idle timeout, laptop asleep), so people who use the app
**never have to type their password again**, and the secret that does this is replaced all the time.

| Mechanism | What it does |
|---|---|
| **Cookie = `selector.validator`** | Two random values. Only `sha256(validator)` is stored in `auth_devices`, so a database leak signs nobody in. HttpOnly, SameSite=Lax, Secure on HTTPS. |
| **Rotation** | The validator is replaced every time the cookie restores a session, and at least every `REMEMBER_ROTATE_MINUTES` while a page is open (keep-alive ping every 4 min + when the tab wakes up). |
| **Theft detection** | A validator that was already replaced and is used again after a 60 s grace period (two tabs racing) means the cookie was copied → that device is revoked immediately; the thief **and** the owner must sign in again. |
| **Fingerprint** | Browser family + OS recorded at sign-in must match on every use (versions ignored, so browser updates are fine). The IP is stored and shown in the device list; it is only *enforced* with `REMEMBER_BIND_IP=true` (same /24 or /48), because people legitimately move between Wi-Fi and 4G. |
| **Sliding expiry** | Every use pushes the expiry `REMEMBER_DAYS` (default 180) ahead. `REMEMBER_MAX_DAYS` adds an absolute lifetime if you want one (0 = none). |
| **Revocation** | `/profile` lists every remembered device (browser, OS, IP, last active) with **Sign out** buttons and **Sign out all other devices**. A revoked device's live session ends within 30 s. Changing the password signs out all other devices. Logging out removes this browser's device. |

Limits worth knowing: a cookie stolen from a browser together with its User-Agent (malware on the machine) can't be told
apart from the owner until the owner's next refresh trips the theft check; no e-mail alert is sent on a new device (the app has
no mail setup); a phone switching to “Request desktop site” changes the OS in its User-Agent and will have to sign in again.

> All users share the same data (documents, customers, items) — accounts are not separate workspaces.

### Creating users

```bash
php database/create_user.php                          # asks name, email, password (hidden)
php database/create_user.php "Jane Doe" jane@site.com # asks only for the password
php database/create_user.php --sql                    # no DB needed: prints an INSERT to paste into phpMyAdmin
```
Run it again with an existing email to **reset that user's password**.

---

## Deploying to a web server

1. **Upload** the project (everything except your local `.env`).
2. In your hosting panel create a **MySQL database + user**.
3. Create **`.env`** on the server (copy `.env.example`) and fill in the DB credentials.
   Keep `APP_ENV=production` and **`APP_DEBUG=false`** — debug mode prints error details in the browser.
4. **Create the tables:** `php database/migrate.php` (SSH) *or* import
   `database/migrations/001_create_tables.sql`, `003_auth.sql` then `004_auth_devices.sql` in phpMyAdmin (Import tab, with your database selected).
5. **Create your user:** `php database/create_user.php` (SSH) *or* run `php database/create_user.php --sql` on your computer
   and paste the generated `INSERT` into phpMyAdmin's SQL tab.
6. Make sure **`storage/sessions/`** is writable by the web server (if it isn't, PHP's default session folder is used).
7. Turn on **HTTPS** and uncomment the 3 redirect lines in `.htaccess`.
   Behind Cloudflare / a reverse proxy set `TRUSTED_PROXY=true` in `.env` (otherwise leave it `false`).
8. **Check that secrets are not downloadable** (both must answer 403 or 404):
   ```bash
   curl -I https://your-site.com/DocEditor/.env
   curl -I https://your-site.com/DocEditor/app/Core/Env.php
   ```

If your host lets you choose the **document root**, point it at `public/` (and move `assets/` into `public/assets/`) —
then nothing but `public/` is reachable from the web at all. Otherwise the root `.htaccess` does the blocking.
