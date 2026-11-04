# DocEditor

A self-hosted **invoice / quote / proforma / credit-note / delivery-note editor** written in plain PHP (no framework, no Composer) on MySQL/MariaDB.
Fill a form on the left, see a live A4 preview on the right, print or save as PDF with Ctrl+P.

Built for NOVOCIB SAS; the issuer defaults live in `DocumentService::defaultTemplate()` and can be changed per document.

---

## Table of contents

1. [Features](#1-features)
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [Configuration (`.env`)](#4-configuration-env)
5. [Database and migrations](#5-database-and-migrations)
6. [Creating users and the encryption key](#6-creating-users-and-the-encryption-key)
7. [Using the app](#7-using-the-app)
8. [Document types and templates](#8-document-types-and-templates)
9. [Document JSON format (and the AI workflow)](#9-document-json-format-and-the-ai-workflow)
10. [Import / export](#10-import--export)
11. [Security](#11-security)
12. [Architecture](#12-architecture)
13. [Routes and API reference](#13-routes-and-api-reference)
14. [Database schema](#14-database-schema)
15. [Project layout](#15-project-layout)
16. [Deploying to a public server](#16-deploying-to-a-public-server)
17. [Backups](#17-backups)
18. [Troubleshooting](#18-troubleshooting)
19. [Known limitations](#19-known-limitations)

---

## 1. Features

- **Editor** with a split panel: collapsible form sections and a live A4 preview. Auto-save 8 seconds after the last change, manual save, status draft / final / paid.
- **Five document types**: Invoice, Quote, Proforma, Credit note, Delivery note. Each is driven by `templates/<id>/manifest.json` (which fields show, number prefix, default texts).
- **Automatic numbering** `PREFIX-YYYYMMDD-N` (e.g. `INV-20261104-1`), atomic, with a "generate number" button.
- **Customers and items catalogues** with search; pick a customer or an item from a modal while editing. Customers are created/updated automatically when a document is saved.
- **Quote to invoice conversion**, with a warning if the quote was already converted. **Duplicate** a document (number gets `-COPY`).
- **Documents list** with search (number/customer), type, status and date-range filters (dates shown as dd/mm/yyyy).
- **JSON everywhere**: download an empty model, export/import a document, paste JSON from an AI assistant, bulk export/import of documents, customers and items (duplicates skipped).
- **Encryption at rest** of customer data and document content (libsodium).
- **Secure login**: login-only (no registration), throttling, CSRF, "keep me signed in" remembered devices with rotating tokens, silent keep-alive, session list in `/profile`.
- **Logs** page (file + database), **Notes** page (text + pasted images), built-in **documentation** page at `/documentation`.
- Runs in a sub-folder (`http://localhost/DocEditor/`) or at the root (`php -S`).

## 2. Requirements

| Item | Needed |
|---|---|
| PHP | 8.1 or newer |
| PHP extensions | `pdo_mysql`, `sodium`, `mbstring`, `json`, `openssl` (usually default) |
| Database | MySQL 5.7+/8 or MariaDB 10.4+ (utf8mb4) |
| Web server | Apache (with `mod_rewrite`, `.htaccess` allowed) / nginx / PHP built-in server |
| Browser | any current browser; Font Awesome 6.7.2 loads from the cdnjs CDN (icons only) |

**No Composer.** Classes are autoloaded by a small PSR-4 loader in `bootstrap.php`, and `.env` is read by `app/Core/Env.php`. Nothing to install.

## 3. Installation

### 3.1 Quick start (built-in server)

```bash
cd DocEditor
cp .env.example .env
php database/generate_key.php          # prints a key: paste it as APP_KEY in .env
# create an empty database and user (see 3.4), edit DB_* in .env
php database/migrate.php               # creates all tables
php database/create_user.php           # asks for name, email, password
php -S localhost:8000 index.php        # or: php -S localhost:8000 -t public
```

Open <http://localhost:8000/> and sign in.

### 3.2 XAMPP / Apache in a sub-folder

1. Copy the folder to `htdocs/DocEditor`.
2. Follow steps 1-5 above (without `php -S`).
3. Open `http://localhost/DocEditor/`. The base path is detected automatically (`BASE_URL` in PHP, `BASE` in JS), so links work in a sub-folder or at the root.

### 3.3 Apache virtual host (recommended for a server)

Point `DocumentRoot` at the **`public/`** folder so `.env`, `app/` and `storage/` are not reachable at all:

```apache
<VirtualHost *:443>
    ServerName docs.example.com
    DocumentRoot /var/www/doceditor/public
    <Directory /var/www/doceditor/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

nginx equivalent:

```nginx
root /var/www/doceditor/public;
location / { try_files $uri /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.3-fpm.sock;
                    fastcgi_param SCRIPT_FILENAME $document_root/index.php; }
```

If you must keep the root `index.php` as entry point (XAMPP sub-folder, shared hosting), the root `.htaccess` denies `.env`, `app/`, `config/`, `database/`, `routes/`, `storage/`, `resources/`, `templates/` and any dotfile. Always verify: `curl -I https://host/DocEditor/.env` must return 403/404. (On nginx, add equivalent `deny` rules.)

### 3.4 Create the database

```sql
CREATE DATABASE doceditor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'doceditor_user'@'localhost' IDENTIFIED BY 'a-long-password';
GRANT ALL PRIVILEGES ON doceditor.* TO 'doceditor_user'@'localhost';
```

### 3.5 Folder permissions

`storage/` (sessions and logs) must be writable by the web-server user:

```bash
chown -R www-data:www-data storage && chmod -R 750 storage
```

## 4. Configuration (`.env`)

Copy `.env.example` to `.env`. Never commit it, never put it in a zip you share.

| Variable | Default | Meaning |
|---|---|---|
| `APP_NAME` | DocEditor | Display name |
| `APP_KEY` | (empty) | **Required.** 32-byte key, base64. Generate with `php database/generate_key.php`. Losing it means losing all encrypted data. |
| `APP_ENV` | production | `production` or `local` |
| `APP_DEBUG` | false | `true` shows errors in the browser. **Never on a public server.** |
| `DB_HOST` / `DB_PORT` | 127.0.0.1 / 3306 | Database server |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | | Credentials |
| `DB_CHARSET` | utf8mb4 | |
| `SESSION_LIFETIME` | 120 | Minutes of inactivity before the PHP session expires |
| `TRUSTED_PROXY` | false | `true` only behind a reverse proxy/CDN that sets `X-Forwarded-For` / `X-Forwarded-Proto` |
| `REMEMBER_DAYS` | 180 | Remembered device lifetime, pushed forward on every use (sliding) |
| `REMEMBER_MAX_DAYS` | 0 | Absolute lifetime of a device; 0 = unlimited |
| `REMEMBER_ROTATE_MINUTES` | 60 | Token replaced at least this often while in use |
| `REMEMBER_GRACE_SECONDS` | 60 | The previous token stays valid this long after rotation (parallel tabs) |
| `REMEMBER_BIND_IP` | false | `true` requires the same network (IPv4 /24, IPv6 /48) |
| `REMEMBER_DEVICE_CHECK_SECONDS` | 30 | How often a live session re-checks that its device is still valid |

`config/app.php` is informational; the app reads `.env` through `Env::get()`.

## 5. Database and migrations

`php database/migrate.php` runs every `database/migrations/*.sql` in order (it keeps no migration history): files use `CREATE TABLE IF NOT EXISTS` / guarded `ALTER`, so it is safe to re-run.

| # | File | Purpose |
|---|---|---|
| 001 | `001_create_tables.sql` | `documents`, `document_numbers`, `customers`, `items` |
| 002 | `002_sqlite_to_mysql_import.php` | One-off import from an old SQLite version (run manually) |
| 003 | `003_auth.sql` | `users`, `login_attempts` |
| 004 | `004_auth_devices.sql` | `auth_devices` (remembered devices) |
| 005 | `005_app_logs.sql` | `app_logs` |
| 006 | `006_encryption.sql` | widens columns to MEDIUMTEXT, drops indexes on now-encrypted columns |
| 007 | `007_notes.sql` | `notes` |
| 008 | `008_template_id.sql` | `documents.template_id` + backfill |

Phpmyadmin users can paste the `.sql` files one by one.

## 6. Creating users and the encryption key

```bash
php database/create_user.php                 # interactive
php database/create_user.php --sql           # prints an INSERT statement instead of writing
php database/generate_key.php                # new APP_KEY
php database/encrypt_existing.php            # encrypt rows saved before encryption was enabled
```

There is **no registration page**. Users are created only from the command line (or with the SQL printed by `--sql`). Passwords are bcrypt/`PASSWORD_DEFAULT` hashed and rehashed automatically when the algorithm improves. Passwords are changed in `/profile`, which also signs out every other session.

Encryption: after setting `APP_KEY` on an existing database, run `encrypt_existing.php` once. Values that are not encrypted yet are read as plain text, so the app keeps working during the transition.

## 7. Using the app

- **Documents** (`/documents`): list, filters, duplicate, delete, open. Buttons for JSON export/import.
- **New document**: choose a type; `GET /editor?type=QUOTE` opens a blank one. Click the wand next to the number to generate one.
- **Customer / item pickers**: buttons in the Bill-to and Line-items sections open a searchable modal; choosing fills the fields. (Creating a *new* customer/item from inside the picker is not built yet: add them in `/customers` / `/items`, or just type the customer in the form, which is saved automatically.)
- **Preview / print**: Ctrl+P prints only the A4 page. `/preview?id=N` is the print-only render.
- **Status**: draft / final / paid, shown in the list and top bar.
- **Convert**: on a quote, "Convert to invoice" creates a new draft invoice (customer, items, VAT, currency, notes, bank, PO carried over; new number; today's date; `quote_ref` = the quote number; paid amount reset).
- **Profile** (`/profile`): name, email, password, list of remembered devices (browser, OS, IP, last used) with revoke buttons.
- **Logs** (`/logs`): daily log files, level filter (DEBUG/INFO/WARNING/ERROR), download.
- **Notes** (`/notes`): short notes with pasted screenshots (max 10 images of 5 MB each).
- **Documentation** (`/documentation`): user guide inside the app.

## 8. Document types and templates

`templates/<id>/manifest.json` defines a type. Ids: `invoice`, `quote`, `proforma`, `credit-note`, `delivery-note`.

```json
{ "id":"invoice", "name":"Invoice", "icon":"fa-file-invoice-dollar", "color":"#3771c8",
  "numbering":{"prefix":"INV"},
  "statuses":["draft","final","paid"],
  "sections":{"header":{"label":"Document Header","open":true}, "...":{}},
  "features":{"show_prices":true,"show_totals":true,"show_bank":true,"show_vat":true,
              "show_quote_ref":true,"show_po":true,"show_due_date":true,
              "show_service_date":true,"show_tracking":true,
              "show_delivery":false,"show_signature":false},
  "defaults":{"balance_label":"TOTAL DUE","vat_mention":"","terms":"..."} }
```

| Type | Prefix | Visible features | Default balance label |
|---|---|---|---|
| Invoice | INV | prices, totals, bank, VAT, quote ref, PO, due date, service date, tracking | TOTAL DUE |
| Quote | QUO | prices, totals, bank, VAT | TOTAL |
| Proforma | PRO | like invoice without tracking; VAT mention "PROFORMA INVOICE - for information only" | TOTAL DUE |
| Credit note | CRN | prices, totals, bank, VAT, due date | TOTAL CREDIT |
| Delivery note | DLV | PO, tracking, delivery address/carrier/ETA, signature; **no prices, totals, bank or VAT** | (none) |

`GET /api/templates` lists manifests; `GET /api/templates/:id` returns one. The editor reads `features` and toggles CSS classes on `<body>` (`tpl-hide-prices`, `tpl-hide-totals`, `tpl-hide-bank`, `tpl-show-delivery`, ...). To add a type, add a folder with a `manifest.json`; the type string (upper case) maps to the folder id (`CREDIT NOTE` -> `credit-note`). Unknown types get a 3-letter prefix from their letters, or `DOC`.

## 9. Document JSON format (and the AI workflow)

Documents are stored as one JSON object. In the editor: **Model JSON** downloads an empty one, **Export JSON** saves the current one, **Import JSON** loads a file, **Paste JSON** accepts text copied from an AI assistant (it tolerates ```json fences and text around the object).

Workflow: download the model, give it to an AI assistant with the customer's request, paste the answer back.

```json
{
  "type": "INVOICE", "template_id": "invoice", "number": "INV-20261104-1",
  "date": "2026-11-04", "due_date": "", "service_date_start": "", "service_date_end": "",
  "po_number": "", "quote_ref": "", "tracking": "",
  "delivery_address": "", "carrier": "", "estimated_delivery": "", "received_by": "",
  "currency": "EUR", "currency_symbol": "€",
  "issuer":   {"name":"","address":"","city":"","email":"","legal":"","logo_base64":""},
  "customer": {"name":"","address":"","city":"","contact":"","phone":"","vat":""},
  "items": [{"name":"","description":"","reference":"","unit_price":0,"qty":1,"is_free":false}],
  "vat_rate": 0, "amount_paid": 0, "show_amount_paid": false,
  "balance_label": "TOTAL DUE", "vat_mention": "", "notes": "", "terms": "",
  "bank": {"label":"","beneficiary":"","bank_name":"","bank_address":"","iban":"","bic":""},
  "footer_thanks": "", "footer_contact": ""
}
```

Rules: dates are `YYYY-MM-DD`; `vat_rate` is a percent; `is_free` forces the line to 0; line total = `unit_price * qty`; VAT = subtotal * rate / 100; balance = total - `amount_paid` (when `show_amount_paid`).

## 10. Import / export

Every list page (`/documents`, `/customers`, `/items`) has **Export JSON** and **Import JSON** buttons.

- Documents: `GET /api/documents/export` returns `{format:"doceditor-export", version:1, exported_at, count, documents:[{id,status,created_at,updated_at,data}]}`. Import accepts that, or a bare array of document objects. Max 200 documents (500 customers or items) per request (the page sends batches). A document whose **type + number** already exists is skipped; creation dates are preserved; an old single `service_date` becomes `service_date_start`; missing customers are added to the customer list (existing ones are never overwritten).
- Customers: match by name (case-insensitive); items: match by `reference` (case-insensitive). Duplicates are skipped.
- Result shown as imported / skipped / failed with error lines.

## 11. Security

- **Authentication only**: every route except `/login` needs a session (`Router::guard`). Open `/api/*` without a session gives JSON 401.
- **Passwords**: `password_hash`, constant-time dummy verify for unknown emails (no user enumeration), rehash on login.
- **Login throttling** (`login_attempts`): 5 failures per email+IP and 20 per IP within 15 minutes -> blocked with a generic message. The post-login `next=` redirect only accepts paths inside the app.
- **Session**: cookie `doceditor_sid`, HttpOnly, SameSite=Lax, Secure on HTTPS, stored in `storage/sessions`, idle timeout `SESSION_LIFETIME`, id regenerated at login, `users.auth_version` bumped on password change to invalidate other sessions.
- **CSRF**: token in `_token` field or `X-CSRF-Token` header on every POST (except `/logout`). The page's `fetch` wrapper refreshes the token once on 419 and shows a re-login banner on 401.
- **Remembered devices** ("keep me signed in", migration 004): cookie `doceditor_remember = selector(24 hex).validator(64 hex)`. Only `sha256(validator)` is stored. The token rotates at least hourly with a short grace window for parallel tabs; reuse of a stale token revokes the device (theft check). Each device stores browser, OS, user agent, creation and last IP; the IP is enforced only with `REMEMBER_BIND_IP=true` (/24 or /48). Expiry slides forward on use; optional absolute limit; max 20 devices per user; revocable one by one or "all others" from `/profile`. A silent ping `GET /api/session/ping` every 4 minutes keeps the session alive and re-checks the device.
- **Encryption at rest**: libsodium `secretbox` (XSalsa20-Poly1305), random 24-byte nonce per value, stored as `base64(nonce || ciphertext)`. Encrypted: `documents.customer`, `documents.data`, all `customers` columns. Not encrypted: document type, number, date, status (needed for filters), items catalogue, notes. Consequence: searches on encrypted fields run in PHP after decryption (fine for thousands of rows, not millions).
- **Headers**: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `Permissions-Policy`, HSTS on HTTPS.
- **Logging** never records passwords, tokens, secrets, hashes or cookies (masked).
- **PDO** uses real prepared statements (`EMULATE_PREPARES=false`); all output is escaped.
- **Files**: Root `.htaccess` denies private folders and `.env`; `storage/.htaccess` denies everything. Serve from `public/` when you can.
- `TRUSTED_PROXY=true` only behind a proxy you control; otherwise a client could forge its IP.

## 12. Architecture

Plain MVC with layers: **Controller -> Service -> Repository -> Model**.

- `index.php` / `public/index.php` -> `bootstrap.php` (constants, autoload, `.env`, headers, error handler) -> `routes/web.php` -> `Router::dispatch`.
- `Router`: routes registered with `get/post(path, [Controller::class, 'method'], options)`. `:id` placeholders. **Registration order matters**: static routes (`/api/documents/export`) before `:id`. Options: `public` (no login), `csrf => false`.
- `Request` (query, JSON/form input, `isHttps`), `Response` (`ok`, `error`, `json`, redirects), `Session`, `Auth` (current user, login, remember restore), `Csrf`, `Database` (PDO singleton), `Env`, `Logger`, `Encryption`, `UserAgent` (browser/OS parsing), `helpers.php`.
- Views are plain PHP; layouts `app`, `editor`, `auth` capture the page into a layout via output buffering.
- JavaScript is vanilla: `editor_logic.php` collects the form into a JSON object (`collectDoc`), renders the preview client-side (`renderPreview`), debounces auto-save, and talks to `/api/*`. `document_render.php` is the server-side print renderer.

Request lifecycle of "save document": editor -> `POST /api/documents/:id` `{data, status}` -> `DocumentApiController::update` -> `DocumentService::update` (builds `Document`, validates date) -> `DocumentRepository::update` (encrypts `customer` + `data`) -> customer upsert -> `{ok:true,id}`.

## 13. Routes and API reference

All POST routes need CSRF. JSON responses are `{ok:true,...}` or `{ok:false,error:"..."}` with an HTTP status.

**Auth / account**

| Method | Path | Notes |
|---|---|---|
| GET/POST | `/login` | public; fields email, password, remember |
| POST | `/logout` | public, no CSRF |
| GET | `/profile` | page |
| POST | `/profile` | update name/email |
| POST | `/profile/password` | change password, invalidates other sessions |
| POST | `/profile/devices/revoke-others` | |
| POST | `/profile/devices/:id/revoke` | |
| GET | `/api/csrf` | fresh token |
| GET | `/api/session/ping` | keep-alive |

**Pages**: `GET /`, `/documents`, `/editor`, `/preview`, `/customers`, `/items`, `/documentation`, `/logs`, `/logs/download`, `/notes`.

**Documents**

| Method | Path | Body / query | Result |
|---|---|---|---|
| GET | `/api/documents` | `q,type,status,from,to` | `{documents:[summary]}` |
| GET | `/api/documents/default` | `type` | `{data}` blank document |
| GET | `/api/documents/export` | | export file object |
| GET | `/api/documents/:id` | | `{document}` |
| GET | `/api/numbers/next` | `type` | `{number,prefix,counter}` (consumes) |
| GET | `/api/numbers/peek` | `type` | same, without consuming |
| POST | `/api/documents` | `{data}` or `{type}` | `{id}` |
| POST | `/api/documents/:id` | `{data,status}` | `{id}` |
| POST | `/api/documents/import` | `{documents:[...]}` | `{imported,skipped,failed,errors}` |
| POST | `/api/documents/:id/delete` | | `{ok}` |
| POST | `/api/documents/:id/duplicate` | | `{id}` |
| POST | `/api/documents/:id/convert` | `{force?}` | `{id,number}`; 409 `already_converted` + `invoices[]` |

**Customers / Items** (same shape): `GET /api/customers|items` (`q`), `GET .../:id`, `POST` create, `POST .../:id` update, `POST .../:id/delete`, `POST .../import`.

**Templates / notes**: `GET /api/templates`, `GET /api/templates/:id`, `POST /api/notes`, `POST /api/notes/:id/delete`.

## 14. Database schema

- `documents(id, type, number, date DATE, status ENUM draft|final|paid, template_id, customer MEDIUMTEXT*, data MEDIUMTEXT*, created_at, updated_at)` (* encrypted)
- `document_numbers(id, prefix, date_key 'YYYYMMDD', counter)` unique `(prefix, date_key)`; incremented atomically with `INSERT ... ON DUPLICATE KEY UPDATE counter = LAST_INSERT_ID(counter+1)`.
- `customers(id, name, address, city, contact, phone, vat, created_at, updated_at)` all text columns encrypted.
- `items(id, reference, title, unit, price DECIMAL(10,2), description, created_at, updated_at)`
- `users(id, name, email unique, password_hash, auth_version, last_login_at, ...)`
- `login_attempts(id, ip, email, attempted_at)`
- `auth_devices(id, user_id FK, selector unique, token_hash, prev_hash, prev_valid_until, auth_version, browser, os, user_agent, ip_created, ip_last, created_at, last_used_at, rotated_at, expires_at, hard_expires_at)`
- `app_logs(id, level, event, ip, user_id, context JSON, logged_at)`
- `notes(id, user_id, url, title, body, images JSON, created_at)`

Full DDL: see `database/migrations/`.

## 15. Project layout

```
index.php                 entry point when served from the project root
public/index.php          entry point when public/ is the web root
bootstrap.php             constants, autoload, .env, headers, error handling
routes/web.php            every route
app/Core/                 Router Request Response Session Auth Csrf Database Env Logger Encryption UserAgent helpers
app/Controllers/          page controllers (+ Api/ for JSON)
app/Services/             business rules (Document, Customer, Item, Template, Auth, Remember, User)
app/Repositories/         SQL + encryption (Document, Customer, Item, User, AuthDevice, LoginAttempt)
app/Models/               value objects (Document, Customer, Item, User)
resources/views/          layouts/, partials/ (editor_body, editor_logic, document_render, topbar, user_menu ...), pages
assets/                   editor.css (UI tokens), document.css (A4 styles), logo.png
templates/<id>/           manifest.json per document type
database/                 migrations + CLI scripts
storage/                  sessions/ and logs/ (writable, never web-exposed)
```

## 16. Deploying to a public server

1. HTTPS (Let's Encrypt). 2. `APP_ENV=production`, `APP_DEBUG=false`. 3. Strong DB password, DB user limited to its database. 4. `APP_KEY` generated and **backed up elsewhere**. 5. Web root = `public/`. 6. `storage/` writable, not served. 7. `TRUSTED_PROXY=true` only behind your proxy. 8. Remove unused users; use long passwords. 9. Set up backups (next section). 10. Optionally restrict `/` by IP/VPN as an extra layer.

## 17. Backups

Back up **both** the database dump and `.env` (specifically `APP_KEY`). A dump without the key is unreadable. Test a restore on another machine.

```bash
mysqldump --single-transaction -u USER -p doceditor | gzip > doceditor-$(date +%F).sql.gz
```

## 18. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "requires the PHP sodium extension" | enable `extension=sodium` |
| Blank page / 500 | check `storage/logs/app-*.log` and the PHP error log; set `APP_DEBUG=true` temporarily on localhost only |
| Login loops back | `storage/sessions` not writable, or cookies blocked |
| 419 on save | session expired; the page refreshes the token once, then asks to log in |
| Preview empty | JS error; open the browser console. The editor script block must be present in `editor_body.php` |
| Icons missing | Font Awesome CDN blocked (cosmetic) |
| Garbled data after restore | wrong or missing `APP_KEY` |
| "Incorrect date value" | document date must be `YYYY-MM-DD`; invalid dates fall back to today |
| Links wrong in a sub-folder | open via the folder URL; `BASE_URL` is derived from `SCRIPT_NAME` |

## 19. Known limitations

- Customer/item "create new" from inside the editor pickers is not implemented.
- Search on encrypted data is done in PHP, not SQL.
- Single company issuer defaults are hard-coded in `DocumentService::defaultTemplate()`.
- No PDF generation server-side: use the browser's print to PDF.
- No automated test suite is included.