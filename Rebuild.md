# DocEditor: Rebuild Specification

A technology-neutral specification to rebuild DocEditor from scratch with any backend (Node/Express, Python/Flask, Go, ...), **PostgreSQL**, and a **Vue or React** front end. It describes behaviour, data, API contract and algorithms. Section 15 maps them to concrete stacks.

Reference implementation: the PHP app in this repository (see `README.md`). When this document is ambiguous, the PHP code is the source of truth.

---

## 1. Product summary

A private, login-only web app for a small company to create **invoices, quotes, proformas, credit notes and delivery notes**.

- Split-screen editor: form left, live A4 preview right, print to PDF via the browser.
- Catalogues of customers and items; documents are stored as one JSON object.
- Atomic automatic numbering, quote-to-invoice conversion, duplication.
- Bulk JSON import/export; JSON paste for AI-assistant workflows.
- Encryption of personal data at rest.
- Hardened auth: throttling, CSRF, long-lived "remembered devices" with rotating tokens.
- Logs page and notes page.

Single tenant. Users are created by an admin CLI; no registration. All users see all data.

## 2. Architecture to reproduce

```
Browser (SPA: Vue or React)  <--JSON/HTTPS-->  Backend (REST)  <-->  PostgreSQL
   - router, forms, preview                      - auth/session/CSRF
   - fetch wrapper (CSRF, 401)                   - services (numbering, import, convert)
                                                 - encryption layer
                                                 - template manifests (files)
```

Layers (keep them, they make testing easy): **routes -> controllers/handlers -> services -> repositories -> DB**. Cross-cutting: config loader, logger, encryption, auth middleware, CSRF middleware, error handler.

Two deployment choices:
- **Same origin** (recommended): the backend serves the built SPA and `/api/*`. Cookies and CSRF are simple (SameSite=Lax).
- Separate origins: needs CORS with credentials and SameSite=None; Secure. Avoid unless required.

Support a base path (sub-folder hosting) through a single `BASE_PATH` setting used by the router, API client and asset URLs.

## 3. Configuration (environment variables)

| Name | Default | Meaning |
|---|---|---|
| APP_NAME | DocEditor | |
| APP_KEY | required | 32 random bytes, base64. Encryption key. Fail at startup if missing/invalid |
| APP_ENV / APP_DEBUG | production / false | debug exposes error details only when true |
| DATABASE_URL (or DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD) | | PostgreSQL |
| BASE_PATH | "" | URL prefix |
| SESSION_LIFETIME | 120 | idle minutes |
| TRUSTED_PROXY | false | trust `X-Forwarded-For/Proto` |
| REMEMBER_DAYS | 180 | sliding expiry of a remembered device |
| REMEMBER_MAX_DAYS | 0 | absolute expiry, 0 = none |
| REMEMBER_ROTATE_MINUTES | 60 | token rotation interval |
| REMEMBER_GRACE_SECONDS | 60 | previous token still accepted |
| REMEMBER_BIND_IP | false | require same /24 (IPv4) or /48 (IPv6) |
| REMEMBER_DEVICE_CHECK_SECONDS | 30 | live-session device recheck interval |
| TEMPLATES_DIR | ./templates | manifest folder |

## 4. Data model (PostgreSQL DDL)

Use `timestamptz`, UTC in DB. Every `updated_at` is set by the application on update (or via a trigger).

```sql
CREATE TYPE doc_status AS ENUM ('draft','final','paid');
CREATE TYPE log_level  AS ENUM ('DEBUG','INFO','WARNING','ERROR');

CREATE TABLE users (
  id            serial PRIMARY KEY,
  name          varchar(255) NOT NULL DEFAULT '',
  email         varchar(190) NOT NULL,
  password_hash varchar(255) NOT NULL,
  auth_version  integer NOT NULL DEFAULT 1,
  last_login_at timestamptz,
  created_at    timestamptz NOT NULL DEFAULT now(),
  updated_at    timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX uq_users_email ON users (lower(email));

CREATE TABLE login_attempts (
  id bigserial PRIMARY KEY, ip varchar(45) NOT NULL, email varchar(190) NOT NULL DEFAULT '',
  attempted_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON login_attempts (ip, attempted_at);
CREATE INDEX ON login_attempts (ip, email, attempted_at);

CREATE TABLE auth_devices (
  id serial PRIMARY KEY,
  user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  selector char(24) NOT NULL UNIQUE,
  token_hash char(64) NOT NULL,
  prev_hash char(64), prev_valid_until timestamptz,
  auth_version integer NOT NULL DEFAULT 1,
  browser varchar(40) NOT NULL DEFAULT '', os varchar(40) NOT NULL DEFAULT '',
  user_agent varchar(255) NOT NULL DEFAULT '',
  ip_created varchar(45) NOT NULL DEFAULT '', ip_last varchar(45) NOT NULL DEFAULT '',
  created_at timestamptz NOT NULL DEFAULT now(),
  last_used_at timestamptz NOT NULL DEFAULT now(),
  rotated_at timestamptz,
  expires_at timestamptz NOT NULL,
  hard_expires_at timestamptz
);
CREATE INDEX ON auth_devices (user_id);
CREATE INDEX ON auth_devices (expires_at);

CREATE TABLE documents (
  id          serial PRIMARY KEY,
  type        varchar(50)  NOT NULL DEFAULT 'INVOICE',
  number      varchar(100) NOT NULL DEFAULT '',
  date        date         NOT NULL,
  status      doc_status   NOT NULL DEFAULT 'draft',
  template_id varchar(50)  NOT NULL DEFAULT 'invoice',
  customer    text NOT NULL DEFAULT '',     -- ENCRYPTED display name
  data        text NOT NULL,                -- ENCRYPTED JSON string
  created_at  timestamptz NOT NULL DEFAULT now(),
  updated_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON documents (type);
CREATE INDEX ON documents (status);
CREATE INDEX ON documents (date);
CREATE INDEX ON documents (type, number);
CREATE INDEX ON documents (template_id);

CREATE TABLE document_numbers (
  id serial PRIMARY KEY, prefix varchar(10) NOT NULL, date_key varchar(8) NOT NULL,
  counter integer NOT NULL DEFAULT 1, UNIQUE (prefix, date_key)
);

CREATE TABLE customers (          -- every text column ENCRYPTED
  id serial PRIMARY KEY,
  name text NOT NULL DEFAULT '', address text NOT NULL DEFAULT '', city text NOT NULL DEFAULT '',
  contact text NOT NULL DEFAULT '', phone text NOT NULL DEFAULT '', vat text NOT NULL DEFAULT '',
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE items (
  id serial PRIMARY KEY,
  reference varchar(100) NOT NULL DEFAULT '', title varchar(255) NOT NULL DEFAULT '',
  unit varchar(100) NOT NULL DEFAULT '', price numeric(10,2) NOT NULL DEFAULT 0,
  description text NOT NULL DEFAULT '',
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON items (lower(reference));
CREATE INDEX ON items (lower(title));

CREATE TABLE app_logs (
  id bigserial PRIMARY KEY, level log_level NOT NULL, event varchar(100) NOT NULL,
  ip varchar(45), user_id integer, context jsonb, logged_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ON app_logs (logged_at);

CREATE TABLE notes (
  id serial PRIMARY KEY, user_id integer NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  url varchar(500) NOT NULL DEFAULT '', title varchar(255) NOT NULL DEFAULT '',
  body text NOT NULL DEFAULT '', images jsonb NOT NULL DEFAULT '[]',
  created_at timestamptz NOT NULL DEFAULT now()
);
```

Notes: encrypted columns cannot be indexed or searched in SQL; filter after decrypting in the app. Sessions are stored by your framework's session store (Redis, Postgres table, signed cookie), not in the schema above.

### 4.1 Optional improvement
Instead of encrypting `documents.data` entirely, you may use `jsonb` and encrypt only PII fields; but you lose parity with the PHP format. Keep parity if you must import PHP exports (exports are decrypted JSON, so either works).

## 5. Document JSON schema

`documents.data` holds this object. All keys optional on input; fill defaults (section 7.3).

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

Computation (used by preview and print, do it on the client; also expose in a shared util):
```
line_total = is_free ? 0 : unit_price * qty
subtotal   = sum(line_total)
vat        = subtotal * vat_rate / 100
total      = subtotal + vat
balance    = total - (show_amount_paid ? amount_paid : 0)
```
Money display: 2 decimals, thousands separated by a space, decimal point (`1 234.50`), then the currency symbol. Use a decimal library or integer cents if you compute server-side.

The summary columns (`type`, `number`, `date`, `customer`, `status`, `template_id`) are **derived from `data` on every save**: `type = upper(trim(data.type))` (default INVOICE), `number = trim(data.number)`, `date = valid(data.date) else today`, `customer = trim(data.customer.name)`. A valid date matches `YYYY-MM-DD`, is a real calendar date; otherwise ignore it.

## 6. Templates (document types)

Each type is a folder `templates/<id>/manifest.json`. Load all at startup (or per request, cached).

Manifest fields: `id, version, name, description, icon (Font Awesome class), color, numbering.prefix, statuses[], sections{key:{label,open}}, features{...booleans}, defaults{balance_label, vat_mention, terms}`.

Shipped manifests:

| id | prefix | features true | defaults |
|---|---|---|---|
| invoice | INV | show_prices, show_totals, show_bank, show_vat, show_quote_ref, show_po, show_due_date, show_service_date, show_tracking | label TOTAL DUE; terms "PAYMENT IMMEDIATE UPON RECEIPT, by credit card or wire transfer." |
| quote | QUO | show_prices, show_totals, show_bank, show_vat | label TOTAL; terms "This quote is valid for 30 days from its date. Prices are in the currency shown and exclude taxes unless stated." |
| proforma | PRO | like invoice minus tracking | label TOTAL DUE; vat_mention "PROFORMA INVOICE - for information only. This is not a tax invoice."; terms "PAYMENT IN ADVANCE, by credit card or wire transfer. Goods are shipped after receipt of payment." |
| credit-note | CRN | show_prices, show_totals, show_bank, show_vat, show_due_date | label TOTAL CREDIT; terms "This credit note will be deducted from your next invoice or refunded on request." |
| delivery-note | DLV | show_po, show_tracking, show_delivery, show_signature | label ""; terms "Please check the goods on receipt and report any damage or shortage within 48 hours." |

Type to template id: lowercase, spaces to `-` (`CREDIT NOTE` -> `credit-note`; `QUOTE` -> `quote`). Fallback: `invoice`. Unknown type prefix: first 3 letters A-Z of the type, or `DOC`.

How features are used: the editor hides/shows form fields and preview blocks:
`show_prices` (unit price/amount columns), `show_totals` (subtotal/VAT/total block), `show_bank` (bank section), `show_vat` (VAT field), `show_quote_ref`, `show_po`, `show_due_date`, `show_service_date`, `show_tracking`, `show_delivery` (delivery address, carrier, estimated delivery), `show_signature` (signature box + "received by"). In a SPA, compute `features` from the loaded manifest and bind with `v-if`/conditional rendering.

## 7. Backend business rules

### 7.1 Numbering
Format `PREFIX-YYYYMMDD-N` where the date is the **server's current date** and N restarts at 1 each day per prefix.
- `next`: atomic increment, returns the number (consumed even if the document is never saved).
- `peek`: returns what `next` would give, without writing.

PostgreSQL:
```sql
INSERT INTO document_numbers (prefix, date_key, counter) VALUES ($1,$2,1)
ON CONFLICT (prefix, date_key) DO UPDATE SET counter = document_numbers.counter + 1
RETURNING counter;
```
Number uniqueness is **not** enforced by the DB (the PHP app allows duplicates); import dedupes by `(type, number)`.

### 7.2 Save
- `POST /api/documents`: body `{data}` or `{type}` (then use the default document). Creates with status `draft`. Returns the id.
- `POST /api/documents/:id`: body `{data, status}`; status from `draft|final|paid` (default `draft`). Re-derive summary columns. Update `updated_at`.
- After each save: **upsert customer** by case-insensitive name if `customer.name` is non-empty. Existing: overwrite address, city, contact, phone, vat with the trimmed values. New: insert.

### 7.3 Default document
`GET /api/documents/default?type=X` returns the object of section 5 with: `type`=X, `template_id` from type, today's date, empty strings, `currency` EUR / `€`, `items` [], `vat_rate` 0, `balance_label`/`vat_mention`/`terms` from the manifest defaults (fallbacks "TOTAL DUE", "", the invoice terms), issuer and bank defaults from **your company settings** (in the PHP app they are hard-coded; do better: a settings file or table), `footer_thanks` "Thank you for your business!", `footer_contact` "If you have any questions, please contact us.\n• <email>".

### 7.4 List and filters
`GET /api/documents?q&type&status&from&to` returns summaries `{id,type,number,date,status,customer,created_at,updated_at}` sorted `date DESC, updated_at DESC`.
- `type` exact match; `status` only if one of the three; `from`/`to` only if `YYYY-MM-DD` (inclusive, on `date`).
- `q`: case-insensitive substring of **number or customer** (customer is encrypted, so filter in the app after decrypting).

### 7.5 Duplicate
Copy of the document, `data.number = number + "-COPY"`, status `draft`, same date, new id. Returns `{id}`.

### 7.6 Convert quote to invoice
`POST /api/documents/:id/convert {force?}`
1. 404-ish error "Document not found" if missing; error "Only a quote can be converted to an invoice" if `type != QUOTE`.
2. If not `force` and the quote has a number: find invoices whose `data.quote_ref == quote.number`. If any: respond **409** `{ok:false, code:"already_converted", error:"This quote was already converted", invoices:[numbers]}`. The UI asks the user, and retries with `force:true`.
3. New number via `next('INVOICE')`. New document = copy of the quote data with: `type=INVOICE`, `number=new`, `date=today`, `due_date=""`, `quote_ref=quote.number`, `po_number` kept, `tracking=""`, `amount_paid=0`, `show_amount_paid=false`; status `draft`; same customer. Quote untouched.
4. Return `{id, number}`.

### 7.7 Bulk import (documents)
Body `{documents:[...]}`, max **200** per call (400 "Too many documents..." beyond; 400 "No documents provided" if empty). For each entry, in order, catch errors per entry:
1. Entry is `{data, status?, created_at?, updated_at?}` or a bare document object (use `entry.data` if it is an object, else the entry).
2. Must be an object with `items`, `customer` or `type`; else error "not a document".
3. `type` = upper(trim) or INVOICE; `number` = trim.
4. Legacy: if `service_date` set and `service_date_start` empty -> copy it; delete `service_date`; default `service_date_start`, `service_date_end`, `po_number` to "".
5. If `number != ""` and a document with same `(type, number)` exists -> **skipped**.
6. `created_at` = valid datetime or now; `updated_at` = valid datetime or created_at; `status` valid else draft; `date` = valid(data.date) else date part of created_at.
7. Insert preserving the timestamps; add the customer to the customer list **only if missing** (never overwrite).
8. Result `{imported, skipped, failed, errors:["INV-1: message"]}`.

The front end sends files in batches of up to 200 and sums the results.

### 7.8 Export
`GET /api/documents/export` -> `{format:"doceditor-export", version:1, exported_at:ISO8601, count, documents:[{id,status,created_at,updated_at,data}]}` sorted `created_at ASC, id ASC`, decrypted.

### 7.9 Customers
Fields: name (required), address, city, contact, phone, vat. List sorted by name case-insensitively; search `q` = substring of name, city or contact (in-app, encrypted). Create, update, delete, get by id. Import: body `{customers:[...]}` or a bare array, max **500** per request ("Too many rows in one request (max 500)", "Nothing to import"); skip when a customer with the same name (case-insensitive) exists; return `{imported, skipped, failed, errors}`. Export file from the UI: array of customer objects.

### 7.10 Items
Fields: reference, title, unit, price (decimal 2), description. Search `q` over reference/title/description (case-insensitive contains; SQL `ILIKE`). Sorted by title. Import (`{items:[...]}` or bare array, max 500 per request) skips when the same `reference` exists (case-insensitive). Validate lengths: reference <= 100, title <= 255, unit <= 100.

### 7.11 Notes
`POST /api/notes {url,title,body,images[]}` for the logged-in user. Truncate url to 500, title to 255. Reject with 422 "Note is empty." if no body and no images. Keep at most 10 images, each must be a string starting `data:image/` and <= 5 MB, silently drop others. `POST /api/notes/:id/delete`. The `/notes` page lists newest first.

### 7.12 Logs
`Logger.log(level, event, context)` writes (a) a line `[ISO ts] [LEVEL] [ip] [uid:N] event {json}` to a daily file `logs/app-YYYY-MM-DD.log` and (b) a row in `app_logs`. Mask keys matching password, token, secret, hash, cookie, authorization in `context`. Page `/logs`: choose a file (default latest), tail the last 2000 lines, optional `level` filter, and a `/logs/download` endpoint streaming the raw file (validate the file name against `^app-\d{4}-\d{2}-\d{2}\.log$`, never accept paths). Events logged: login success/failure/blocked, logout, password change, device revoke, import, unhandled exception.

## 8. Authentication and security

### 8.1 Login
`POST /login {email, password, remember, next?}`.
1. Throttle: count failures in `login_attempts` in the last **15 min**; block if >= **5** for (ip,email) or >= **20** for ip. Respond with "Too many failed attempts. Please wait a few minutes and try again." (the PHP app re-renders the login page; an API-style rebuild should use HTTP 429).
2. Look up the user by lowercase email. If not found, still verify against a constant dummy hash (timing equality).
3. Verify password (bcrypt or argon2id). Failure: insert attempt row, generic "Invalid email or password".
4. Success: delete that email+ip attempts, regenerate the session id, store `user_id` and `auth_version`, set `last_login_at`, rehash the password if the algorithm cost is outdated. If `remember`: create a device (8.3).
5. Redirect to `next` only if it is an app-internal absolute path (starts with a single `/`, not `//`, no scheme, no backslash); else `/documents`.

### 8.2 Session
Cookie `doceditor_sid`: HttpOnly, SameSite=Lax, Secure when HTTPS, path = base path. Idle timeout `SESSION_LIFETIME`. Each request must check `session.auth_version == users.auth_version`; password change increments it and so logs out every other session. Logout destroys the session and, if present, revokes the current device and clears its cookie.

### 8.3 Remembered devices
Cookie `doceditor_remember = selector(24 hex) "." validator(64 hex)`; HttpOnly, SameSite=Lax, Secure on HTTPS, expiry = device expiry.

- Create: random selector and validator; store `sha256(validator)` only; capture browser and OS from the user agent, the user agent (<=255), `ip_created = ip_last = ip`; `auth_version` of the user; `expires_at = now + REMEMBER_DAYS`; `hard_expires_at = now + REMEMBER_MAX_DAYS` if > 0. Cap 20 devices per user (delete the oldest).
- Restore (when a request has no valid session but has the cookie): find by selector; reject and clear cookie if expired, hard-expired, `auth_version` mismatch, or (when `REMEMBER_BIND_IP`) the IP is not in the same /24 (v4) / /48 (v6) as `ip_last`. Compare `sha256(validator)` in constant time to `token_hash`. If it matches `prev_hash` and `now < prev_valid_until` -> accept (parallel tab), do not rotate. If it matches **neither** -> treat as theft: delete the device and clear the cookie. If it matches: start a session (regenerate id), set `last_used_at`, `ip_last`, push `expires_at` forward, and **rotate** if `rotated_at` older than `REMEMBER_ROTATE_MINUTES`: `prev_hash = token_hash`, `prev_valid_until = now + GRACE`, new validator, new cookie.
- Live check: every `REMEMBER_DEVICE_CHECK_SECONDS` a session that came from a device re-verifies the device row still exists (so "revoke" takes effect quickly).
- Keep-alive: `GET /api/session/ping` returns `{ok:true, token}` with a fresh CSRF token (or 401). The SPA calls it every **4 minutes**. It touches the session, which restores/rotates tokens in the background so users never see a login screen.
- Profile page lists devices (browser, OS, IP, created, last used, "this device" marker) with `revoke` and `revoke all others`.

### 8.4 CSRF
Synchronizer token stored in the session. All non-GET requests must send it in header `X-CSRF-Token` (SPA) or `_token` (forms), compared in constant time. `GET /api/csrf` returns `{token}`. Failure: HTTP 419 `{ok:false, error:"CSRF token mismatch"}`. `/logout` is exempt. The SPA fetch wrapper: add the header; on 419 fetch a new token and retry once; on 401 show a "session expired, sign in again" banner linking to `/login?next=<current path>`.

### 8.5 Other
- Every route except `/login` (and static assets) requires a session; API paths answer JSON 401, pages redirect to `/login?next=`.
- Headers on all responses: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, `Strict-Transport-Security: max-age=15552000` over HTTPS. Add a CSP in the rebuild if you can (the PHP app does not).
- Client IP: the socket address; only when `TRUSTED_PROXY=true` use the first `X-Forwarded-For`, and `X-Forwarded-Proto` for HTTPS.
- Parameterised SQL only. Escape output (frameworks do this by default; never `v-html` user content without sanitising).
- Production error handler: log details, return a generic message (`{ok:false,error:"Server error"}` for API, an error page otherwise).
- Password rules: minimum 10 and maximum 72 characters (bcrypt limit); the new password must differ from the current one; changing the login email also requires the current password.
- Profile: update name/email (email unique); change password needs the current password.

### 8.6 Encryption at rest
Algorithm: **XSalsa20-Poly1305 secretbox** (libsodium `crypto_secretbox`), key = base64-decoded `APP_KEY` (32 bytes).
- `encrypt(plain)`: `nonce = random(24)`, `box = secretbox(plain, nonce, key)`, store `base64(nonce || box)`. Empty string stays empty.
- `decrypt(value)`: base64-decode; if it fails or is shorter than 24+16 bytes, or authentication fails, **return the value unchanged** (treat as plaintext, to allow gradual migration). Otherwise return the plaintext.
- Encrypted fields: `documents.customer`, `documents.data` (the JSON string), `customers.name|address|city|contact|phone|vat`.
Libraries: Node `libsodium-wrappers` or `tweetnacl` (`nacl.secretbox`), Python `PyNaCl` (`nacl.secret.SecretBox`), Go `golang.org/x/crypto/nacl/secretbox`. These are byte-compatible, so a rebuilt app can read data from the PHP app with the same `APP_KEY`. If you do not need that, AES-256-GCM is an acceptable alternative.
Provide scripts: `generate-key` (print `base64(random(32))`) and `encrypt-existing` (encrypt plaintext rows).

## 9. REST API contract

Base: `<BASE_PATH>/api`. JSON in/out, `Content-Type: application/json`. Success `{ok:true, ...}`; failure `{ok:false, error:"message"}` with status 400 (validation), 401, 404, 409, 419, 422, 429, 500.

| Method | Path | Request | Response |
|---|---|---|---|
| GET | /csrf | | `{ok,token}` |
| GET | /session/ping | | `{ok,token}` (fresh CSRF token; 401 if signed out) |
| GET | /templates | | `{ok,templates:[manifest...]}` |
| GET | /templates/:id | | `{ok,template}` or 404 |
| GET | /documents | q,type,status,from,to | `{ok,documents:[summary]}` |
| GET | /documents/default | type | `{ok,data}` |
| GET | /documents/export | | export object (with `ok`) |
| GET | /documents/:id | | `{ok,document:{id,type,number,date,status,customer,created_at,updated_at,data}}` |
| GET | /numbers/next, /numbers/peek | type | `{ok,number,prefix,counter}` |
| POST | /documents | `{data}` or `{type}` | `{ok,id}` |
| POST | /documents/:id | `{data,status}` | `{ok,id}` (400 "No data provided") |
| POST | /documents/import | `{documents}` | `{ok,imported,skipped,failed,errors}` |
| POST | /documents/:id/delete | | `{ok}` |
| POST | /documents/:id/duplicate | | `{ok,id}` |
| POST | /documents/:id/convert | `{force?}` | `{ok,id,number}` / 409 |
| GET | /customers, /items | q | `{ok,customers:[...]}` / `{ok,items:[...]}` |
| GET | /customers/:id, /items/:id | | `{ok,customer}` / `{ok,item}` |
| POST | /customers, /items | fields | `{ok,id}` |
| POST | /customers/:id, /items/:id | fields | `{ok}` |
| POST | /customers/:id/delete, /items/:id/delete | | `{ok}` |
| POST | /customers/import, /items/import | `{customers:[...]}` / `{items:[...]}` | `{ok,imported,skipped,failed,errors}` |
| POST | /notes | `{url,title,body,images}` | `{ok,id}` |
| POST | /notes/:id/delete | | `{ok}` |

You may use proper verbs (PUT/DELETE) in the rebuild; if so, keep the same payloads. If you want to import data exported by the PHP app, only the **JSON file formats** (sections 5 and 7.7-7.10) must stay identical.

Auth/page routes (server-rendered in PHP; in a SPA, the pages are client routes and only these need server endpoints): `POST /login`, `POST /logout`, `GET /me` (current user, new), `POST /profile`, `POST /profile/password`, `POST /profile/devices/revoke-others`, `POST /profile/devices/:id/revoke`, `GET /profile/devices`, `GET /logs?file&level`, `GET /logs/download?file`.

Route-ordering pitfall (applies to PHP and Express): register static routes (`/documents/export`, `/documents/default`, `/documents/import`) before `/documents/:id`.

## 10. Front-end specification (Vue or React)

### 10.1 Pages / routes

| Path | Page |
|---|---|
| /login | Login (email, password, "Keep me signed in on this device") |
| / -> /documents | Documents list |
| /editor?type=T or /editor?id=N | Editor |
| /preview?id=N | Print-only render |
| /customers, /items | Catalogue pages |
| /documentation | Static user guide |
| /logs, /notes, /profile | Admin pages |

Top bar (all pages except login/editor variant): brand "DocEditor", nav Documents / Customers / Items / Logs / Notes, right side: **Documentation** link, user menu (name, Profile, Logout). Active link highlighted. Editor uses a compact top bar with document actions.

### 10.2 Design tokens (from `assets/editor.css`)
Primary `#3771c8`; neutral greys; rounded 6-8px controls; Font Awesome icons (6.x, or any icon set); system font stack for the UI; the A4 preview uses its own stylesheet (`document.css`): white page 210mm x 297mm, 15-20mm padding, 10pt text, tables with light borders, a header with logo and issuer block, a customer block, items table, totals box aligned right, bank/footer at the bottom. For printing: `@page { size: A4; margin: 0 }`, hide everything except `.page`, remove shadows and zoom.

### 10.3 Documents list
- Toolbar: search box, type select (All + templates), status select, date range **from/to as dd/mm/yyyy text inputs** (parse to ISO before calling the API; validate real dates), "Clear", "New" dropdown (one entry per template), Export JSON, Import JSON.
- Table columns: Number, Type (coloured badge from manifest colour), Customer, Date (dd/mm/yyyy), Status badge, Updated. Row actions: Open, Duplicate, Delete (confirm). Debounce search 250 ms.
- Empty state text when no results.

### 10.4 Editor (the core)
Layout: left form panel (about 45%), right preview (A4 scaled to fit, scrollable). Mobile: tabs Form / Preview.

Top bar: back, document type + number, status select (draft/final/paid), autosave indicator (`Saved` / `Unsaved changes…` / `Saving…` / `Error`), buttons: Save, Print (calls `window.print()`), Convert to invoice (only if type is QUOTE and the document is saved), Model JSON (download blank data for the type), Export JSON (download current), Import JSON (file input), Paste JSON (modal), Duplicate.

Form sections (collapsible, defaults from manifest `sections[].open`):
1. **Header**: type select, number (+ wand button calling `/numbers/next`, fills the field), date, due date, service date start/end, PO number, quote reference, tracking, currency code and symbol, delivery address/carrier/estimated delivery/received by (delivery note).
2. **Customer**: name, address, city, contact, phone, VAT; button "Choose customer" opens a searchable modal listing `/api/customers` (click selects and fills the fields).
3. **Line items**: rows with name, description (textarea), reference, unit price, qty, free checkbox, delete; "Add line"; button "Choose item" opens a searchable modal over `/api/items` (selecting appends a row: name=title, description, reference, unit_price=price, qty=1, unit is shown in the description if you wish). Drag to reorder is optional.
4. **Totals & VAT**: VAT rate %, VAT mention, show amount paid + amount paid, balance label.
5. **Notes & terms**: notes, terms.
6. **Bank**: label, beneficiary, bank name, bank address, IBAN, BIC.
7. **Issuer**: name, address, city, email, legal text, logo upload (read as data URI into `logo_base64`; limit 1 MB, PNG/JPEG).
Plus footer thanks and footer contact.

Behaviour:
- State is a single document object; the preview is a pure function of it. Re-render on every change.
- Loading: with `id`, `GET /documents/:id` -> set state (merge over the default so missing keys exist) and status; without `id`, `GET /documents/default?type=T`. Then load the manifest of the type and apply `features`. Changing the type select reloads the manifest, applies new defaults only to fields the user has not edited (PHP app: replaces `balance_label`, `vat_mention`, `terms` defaults when they equal the previous type's default).
- **Save**: new document -> `POST /documents` then navigate to `/editor?id=N` (replace history); existing -> `POST /documents/:id`. If the number is empty on first save, auto-generate it.
- **Autosave**: debounce **8000 ms** after the last change; only for already-saved documents or after the first manual save; show state.
- Warn on leaving with unsaved changes.
- Paste/Import JSON: parse **loosely**: trim; strip a Markdown code fence (```json ... ```); if parsing fails, extract from the first `{` to the last `}` and retry; accept `{data:{...}}` or `{document:{...}}` wrappers (unwrap); merge over defaults; show a toast on error "Invalid JSON".
- Convert: `POST /documents/:id/convert`; on 409 show a confirm "Already converted to INV-... Create another?" then retry with `force:true`; on success navigate to the new document.
- Toast messages for save/errors; confirm dialogs for destructive actions.

Preview renderer (replicate `renderPreview`): header (logo if any, issuer name/address/city/email, document title = type name upper-case + number, date), customer block "Bill to", reference lines (PO, quote ref, due date, service date range, tracking) as available by features, items table (Reference/Description/Qty/Unit price/Amount; hide price columns when `show_prices` is false), totals (Subtotal, VAT at rate, Total, Amount paid, Balance with `balance_label`), VAT mention, notes, terms, bank block, delivery block and signature box (delivery note), footer (thanks, contact, legal text). Escape all text and convert newlines to `<br>`.

### 10.5 Customers / Items pages
Table with search, "New" form (modal or inline), edit, delete, Export JSON, Import JSON. Import: choose file, parse, post, then show "imported / skipped / failed" with the errors list.

### 10.6 Login / profile / logs / notes
- Login: centred card; shows throttling/invalid messages; remember checkbox (checked by default is acceptable).
- Profile: two forms (details, password) and a devices table with revoke buttons.
- Logs: file select, level select, auto-scrolling monospace output coloured by level, Download button.
- Notes: list (newest first, show images as thumbnails), form with title, text and paste-image (clipboard `paste` event -> data URI), delete.

### 10.7 Global client behaviours
- `apiFetch` wrapper (CSRF, 419 retry, 401 banner, JSON errors -> thrown `Error(message)`).
- Keep-alive ping every 4 minutes while the tab is visible; also ping on `visibilitychange` back to visible.
- Date helpers: `isoToFr('2026-10-21') -> '21/10/2026'`, `frToIso`.
- Accessible labels, keyboard focus in modals, Esc closes modals.

### 10.8 Suggested front-end structure
Vue 3 + Vite + Pinia + vue-router, or React + Vite + React Router + Zustand/Context.
```
src/
  api/client.ts            apiFetch, csrf, endpoints
  stores/ (auth, templates, editor)
  utils/ (money, dates, docMath, parseJsonLoose, download)
  components/ (TopBar, Modal, Toast, StatusBadge, CustomerPicker, ItemPicker, BulkIO)
  editor/ (EditorPage, FormPanel, sections/*, PreviewPage, DocumentPreview, ItemRow)
  pages/ (Login, Documents, Customers, Items, Logs, Notes, Profile, Documentation)
  styles/ (tokens.css, editor.css, document.css)
```

## 11. Printing
Use the browser: the preview component renders the same markup in a `.page` container; a print stylesheet hides the editor UI. For server-side PDFs (optional improvement): render `/preview?id=N` with headless Chromium (Playwright/Puppeteer) to `page.pdf({format:'A4', printBackground:true})`.

## 12. Admin CLI (replace the PHP scripts)
- `migrate`: apply SQL migrations in order with a `schema_migrations` table (an improvement over the PHP app).
- `create-user [name] [email]`: prompts for a password (hidden), min 10 chars, hashes (argon2id/bcrypt), inserts; if the user exists, offers a password reset (and bumps `auth_version`). `--sql` prints an INSERT.
- `generate-key`, `encrypt-existing`.
- `import-sqlite` optional (one-off legacy migration).
- `purge-expired`: delete expired devices and login attempts older than 1 day (cron). The PHP app does this opportunistically.

## 13. Security checklist for the rebuild
- [ ] Passwords hashed; constant-time compares; generic login errors.
- [ ] Throttling by (ip,email) and ip; log blocks.
- [ ] Session regenerated on login; cookie flags; idle timeout; `auth_version`.
- [ ] CSRF on every state-changing route; JSON only.
- [ ] Remember tokens hashed, rotated, theft detection, revocation UI.
- [ ] Encryption layer used by repositories only; key never logged; startup check.
- [ ] Parameterised SQL; no string-built queries.
- [ ] Log masking; no secrets in responses.
- [ ] Security headers (+ CSP).
- [ ] File-name validation on log download; size limits on bodies (documents 5 MB, notes 60 MB, imports 20 MB).
- [ ] Open redirect protection on `next`.
- [ ] `.env`/secrets outside the web root and out of git.

## 14. Test plan

Unit: money/total math (including `is_free`, VAT 0/20/5.5, rounding to 2 decimals), date validation, number format, `parseJsonLoose` (fences, prose around, wrapper keys), encryption round-trip and plaintext pass-through, `typeToPrefix`, IP network comparison, `next` redirect validator.

Integration (API with a test DB):
1. Login wrong password x5 -> 429; correct password works; `next=//evil.com` ignored.
2. CSRF missing -> 419; ping without session -> 401.
3. Create doc, read it back, check DB columns hold ciphertext (not the customer name).
4. Save with invalid date -> falls back to today; filters by type/status/date/q.
5. Concurrent `numbers/next` x50 -> 50 distinct counters.
6. Save auto-creates and updates customer.
7. Convert quote: first OK, second 409 with invoice list, `force` OK; non-quote -> error.
8. Import: new, duplicate (skipped), legacy `service_date`, bad entry (failed), >200 rejected; timestamps preserved.
9. Remember flow: restore after session loss, rotation, grace, replay of an old token revokes the device, revoke from profile ends access within the check interval, password change logs out other sessions.
10. Notes limits; log download path traversal attempts rejected.

End-to-end (Playwright): create quote -> add customer + items via modals -> preview totals -> save -> convert -> print preview -> export JSON -> import JSON in a clean DB -> same totals.

Acceptance: a JSON export from the PHP app imports with zero failures; totals in the preview match the PHP app for the same document.

## 15. Stack mappings

### 15.1 Node.js + Express + TypeScript
- Router: `express.Router`; JSON body limit `express.json({limit:'20mb'})`.
- Session: `express-session` + `connect-pg-simple` (cookie name `doceditor_sid`, `rolling:true`, `maxAge` idle, `cookie:{httpOnly:true,sameSite:'lax',secure:'auto'}`); set `app.set('trust proxy', cfg.trustedProxy)`.
- CSRF: custom middleware (token in `req.session.csrf`, compare with `crypto.timingSafeEqual`), or `csrf-csrf`.
- DB: `pg` + a query builder (Kysely/Knex/Drizzle) or raw SQL; migrations with `node-pg-migrate`.
- Hashing: `argon2` or `bcrypt`. Crypto: `libsodium-wrappers` (`crypto_secretbox_easy`). Random: `crypto.randomBytes`.
- Validation: `zod`. Logging: `pino` + a Postgres transport for `app_logs`. UA parsing: `ua-parser-js`.
- Layout: `src/{config,middleware,routes,controllers,services,repositories,crypto,templates}`.
- Transaction for number increment: the single `INSERT ... ON CONFLICT ... RETURNING` is atomic.

### 15.2 Python + Flask
- Flask + `Flask-Session` (or itsdangerous signed cookie + server table), `Flask-WTF` CSRF or custom, `SQLAlchemy 2` + `psycopg`, `Alembic` migrations, `argon2-cffi`, `PyNaCl` (`nacl.secret.SecretBox`), `user-agents`, `pydantic`, `structlog`/`logging` with a DB handler.
- Blueprints per area; `before_request` for auth and CSRF; `errorhandler` for JSON errors.
- Serve the built SPA from `static/` with a catch-all returning `index.html` (after `/api/*`).
- Run with gunicorn behind nginx.

### 15.3 Go
- `chi` router (or stdlib `net/http` mux), `pgx/v5` + `sqlc` (or `database/sql`), `golang-migrate`, `alexedwards/scs` sessions with `pgxstore`, `gorilla/csrf` or custom, `golang.org/x/crypto/argon2|bcrypt`, `golang.org/x/crypto/nacl/secretbox`, `slog`, `ua-parser/uap-go`, `embed` for the SPA and manifests.
- Layers: `internal/{http,service,repo,crypto,config}`; middleware chain: recover, logging, security headers, session, auth, csrf.
- Use `context.Context` everywhere; a single binary + `.env`.

### 15.4 Front-end specifics
- **Vue 3**: `<script setup>`, `ref/reactive` document, `computed` totals and preview, `v-model` on inputs, `watchDebounced` (VueUse) for autosave, `vue-router` guards using the auth store, `<Teleport>` for modals.
- **React**: `useReducer` or Zustand for the document, `useMemo` for totals, `useDeferredValue` for the preview, debounce hook for autosave, `react-router` loaders, portals for modals.
- Either: the preview component is shared between the editor and `/preview`.

## 16. Delivery plan (suggested order)

1. Project skeleton, config, DB connection, migrations, logger.
2. Auth: users table, CLI, login/logout, session, CSRF, security headers.
3. Encryption layer + repositories for customers/items/documents.
4. Templates loader + numbering + default document.
5. Documents API (CRUD, list/filter, duplicate, convert, import/export).
6. Customers/items API with import/export.
7. SPA shell: router, apiFetch, login, layout, documents list.
8. Editor form + preview + autosave + pickers + JSON tools + print stylesheet.
9. Customers/items pages, bulk IO component.
10. Remembered devices + keep-alive + profile page.
11. Logs and notes, documentation page.
12. Hardening (checklist 13), tests (section 14), packaging (Docker: app + postgres, volume for logs, env file).

## 17. Known gaps worth fixing in the rebuild
- "Create new customer/item" directly inside the picker modals (the PHP app lacks it).
- Company issuer/bank defaults should be a settings screen, not code.
- Unique `(type, number)` constraint plus friendly error.
- Server-side search on encrypted data is O(n); consider a blind index (HMAC of the lowercase name) for exact lookups.
- Multi-user roles, audit trail per document, server-side PDF, email sending.