# Backend plan: Flask REST API

Replaces the PHP backend (`Core/Router.php`, controllers, services, repositories) with a Flask API under `/api/v1`. It keeps the existing MySQL database and is designed from the start for modular document templates (see `implement_template.md`).

## 1. Principles

- **Keep MySQL and the existing tables.** No data migration. The old PHP app and the new API can run side by side on a *copy* of the database.
- **API first.** Build and test every endpoint against real data before any Vue page depends on it.
- **Thin routes, logic in services.** Routes parse input and return output. Business rules live in `services/`.
- **One validation layer.** Pydantic schemas validate every request body and serialize every response.
- **Rules are tested before they are trusted.** Numbering, VAT totals, convert-quote and import rules each get tests.

## 2. Stack

| Concern | Choice |
|---|---|
| Framework | Flask 3 (app factory + blueprints) |
| ORM | SQLAlchemy 2.x |
| Migrations | Flask-Migrate (Alembic) |
| Validation | Pydantic v2 |
| Rate limiting | Flask-Limiter |
| Passwords | `bcrypt` (reads existing PHP `$2y$` hashes) |
| Templates / rendering | Jinja2 `SandboxedEnvironment`, `nh3` for HTML sanitizing |
| Server | gunicorn behind nginx |
| Tests | pytest, test MySQL database |
| Lint / format | ruff |

## 3. Project layout

```
backend/
├─ app/
│  ├─ __init__.py            # create_app()
│  ├─ config.py              # settings from .env
│  ├─ extensions.py          # db, migrate, limiter
│  ├─ errors.py              # error handlers -> {error, code, details}
│  ├─ models/                # document, customer, item, user, auth_device,
│  │                         # login_attempt, document_number, template
│  ├─ schemas/               # Pydantic: requests, responses, manifest
│  ├─ api/v1/                # auth, documents, customers, items, numbers,
│  │                         # profile, templates, render, health
│  ├─ services/              # numbering, documents, conversion, import_export,
│  │                         # auth, templates, rendering, calculators
│  └─ utils/                 # money, dates, decorators
├─ templates/                # built-in template packs (invoice/, quote/, _blocks/)
├─ migrations/
├─ tests/
├─ .env.example
├─ requirements.txt
└─ wsgi.py
```

## 4. Database

- SQLAlchemy models **mirror the existing tables exactly**: `documents` (JSON `data` column), `customers`, `items`, `document_numbers`, `users`, `login_attempts`, `auth_devices`.
- Generate the Alembic baseline, then run `alembic stamp head` against the existing database so nothing is recreated.
- New tables and columns, added by real migrations:
  - `templates` (`slug`, `version`, `name`, `manifest` JSON, `html`, `css`, `is_builtin`)
  - `documents.template_id`, `documents.template_version` (backfilled from the document type)
  - optional `settings` table for issuer and bank defaults
- Open question: if the app becomes multi-user, add an `owner_id` to documents, customers and items. Decide before writing the models.

## 5. Authentication

Same-origin SPA and API (nginx routes `/api` to Flask), so the current session model carries over.

- HttpOnly, `SameSite=Lax`, `Secure` session cookie plus a CSRF token sent in an `X-CSRF-Token` header.
- Endpoints:
  - `POST /auth/login`, `POST /auth/logout`, `GET /auth/session`, `GET /auth/csrf`
  - `GET /auth/devices`, `DELETE /auth/devices/:id`, `POST /auth/devices/revoke-others`
  - `PUT /profile`, `PUT /profile/password`
- Remember-me through the existing `auth_devices` table (hashed token, device label, last seen).
- Login throttling with Flask-Limiter, plus the `login_attempts` table if identical behavior is wanted.
- `@login_required` decorator on everything except login, health and CSRF.
- Existing users log in unchanged: verify the PHP bcrypt hash with `bcrypt`, swapping the `$2y$` prefix for `$2b$`. Confirm the algorithm in `AuthService.php` first.

## 6. API surface

All paths are under `/api/v1`. Errors use real HTTP status codes and a body `{error, code, details}`.

| Resource | Endpoints |
|---|---|
| Health | `GET /health` |
| Customers | `GET /customers?q=`, `GET /customers/:id`, `POST`, `PUT /:id`, `DELETE /:id`, `GET /customers/export`, `POST /customers/import` |
| Items | same pattern as customers |
| Documents | `GET /documents` (filters: `q`, `type`, `status`, `date_from`, `date_to`), `GET /:id`, `POST`, `PUT /:id`, `DELETE /:id`, `POST /:id/duplicate`, `POST /:id/convert`, `GET /export`, `POST /import` |
| Numbers | `GET /numbers/peek`, `POST /numbers/next` (it changes state) |
| Templates | `GET /templates`, `GET /templates/:slug` (manifest + defaults) |
| Render | `POST /render` (data in, HTML out), `GET /documents/:id/render` |
| Auth / profile | see section 5 |

Old to new mapping: `POST /:id` becomes `PUT`, `POST /:id/delete` becomes `DELETE`, `GET /numbers/next` becomes `POST`.

## 7. Business rules (each needs tests)

1. **Numbering.** Format comes from the template manifest (default `PREFIX-YYYYMMDD-counter`). The counter is atomic: `INSERT ... ON DUPLICATE KEY UPDATE counter = LAST_INSERT_ID(counter + 1)`. Add a concurrency test with parallel requests.
2. **Customer upsert** when a document is saved.
3. **Statuses:** `draft`, `final`, `paid`. Allowed transitions come from the manifest.
4. **Duplicate:** copies the document and appends `-COPY` to the number.
5. **Convert quote to invoice:** maps fields through the manifest's `conversions`, returns **409** if the quote was already converted (`JSON_EXTRACT` on `quote_ref`).
6. **Import:** skips existing type + number pairs, maps legacy `service_date` to `service_date_start`, validates every row, reports per-row errors.
7. **Totals and VAT:** one calculator module (`totals_with_vat`, `none`), money handled with `Decimal`, never floats. The frontend ports the same logic, and both are tested against the same fixtures.

## 8. Template engine (from `implement_template.md`)

- **Loader:** reads packs from `templates/`, validates `manifest.json` against a JSON Schema, resolves `extends` and `_blocks`, and checks that every placeholder in the HTML references a declared field.
- **Renderer:** Jinja2 `SandboxedEnvironment` with autoescape and a small filter set (`money`, `date`, `upper`). One renderer serves both the editor preview and print.
- **Version pinning:** each document stores `template_id` and `template_version`, so editing a template never changes old documents.
- **Calculators:** declarative names in the manifest, implemented in code.
- **Security:** sanitize any uploaded HTML with `nh3`, limit template size and complexity, allow only trusted users to edit templates.

## 9. Configuration and security

- All settings from `.env` (`DATABASE_URL`, `SECRET_KEY`, `SESSION_COOKIE_SECURE`, rate limits). `.env.example` committed, `.env` never.
- Security headers, strict CORS (none needed when same-origin), request size limits.
- Structured logging, no secrets or full document data in logs.
- Daily `mysqldump` backup and a tested restore.

## 10. Testing

- **Unit:** services (numbering, calculators, conversion, import).
- **API:** every endpoint through Flask's test client against a test MySQL database.
- **Auth:** wrong password, throttling, CSRF rejection, revoked device, existing PHP user logging in.
- **Concurrency:** numbering under parallel requests.
- **Golden files:** JSON fixtures recorded from the PHP app in Phase 0, compared with the new responses (minus the intentional REST changes). Rendering golden files per template.
- **Security:** XSS test with hostile field values, schema-violation tests.

## 11. Phases

| Phase | Work | Days | Done when |
|---|---|---|---|
| 0 Safety net | Record golden JSON from PHP, write the business-rules checklist, mysqldump, work on a DB copy | 1-2 | Fixtures folder and rules checklist exist |
| 1 Skeleton + DB | App factory, config, `/health`, models, Alembic baseline + stamp, Docker Compose | 2 | Flask reads real rows from the copied DB |
| 2 Auth | Login, logout, CSRF, throttling, devices, profile, password, `@login_required` | 2-3 | Auth tests pass, an existing PHP user can log in |
| 3 Customers + Items | CRUD, search, import / export | 2 | Responses match golden fixtures |
| 4 Documents | CRUD, filters, numbering, upsert, duplicate, convert, import / export, defaults | 4-5 | Rules checklist fully covered by tests |
| 5 Templates + render | Loader, validator, `/templates`, `/render`, calculators, per-template numbering, manifest-driven conversions | 3-4 | Invoice and quote packs render identically to the PHP print view |
| 6 Deploy | gunicorn, nginx, HTTPS, staging on a DB copy, backup / restore | 1-2 | Staging works end to end with the Vue app |

Total about **15-19 working days**, with tests written alongside each phase.

## 12. Risks

- **Numbering collisions** while both apps share `document_numbers`. Test on a copy of the database, never production.
- **Hidden PHP behavior** (date handling, rounding, defaults). The golden fixtures catch it.
- **Money errors.** Use `Decimal` everywhere and test the totals heavily, since mistakes mean wrong invoices.
- **Template security.** An edited template changes every render, so keep editing developer-only until the sanitizing and sandbox tests pass.

## 13. Decisions needed

1. Single-user or multi-user (owner column)?
2. Cookie session (recommended) or JWT?
3. Hosting: VPS, or shared hosting without a process manager?
4. Templates edited only by you in the repo, or by users in the app?
5. Server-side PDF (WeasyPrint) now, or browser "Save as PDF" first?


## 14 Decisions made

Single-user for now.
Jwt with refresh token & hardware blueprint
Shared hosting
Templates only added by me, no need a template editor.
Server-side pdf generation will be nice, I could even make the server send it in email.
