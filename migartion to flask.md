# Migration plan: PHP → Flask REST API + Vue 3

## 0. Strategy

- **Keep MySQL and the existing tables and columns.** Flask connects to your real database, so there is no data migration and both apps can run side by side while testing.
- **Move the backend first, then the frontend.** Build and test the Flask API against the existing data before the Vue pages exist.
- **Rebuild one page at a time.** Each Vue page is finished and tested before the next one starts.
- **Cut over only when the Vue app matches the PHP app.** The old app stays untouched until then; it is your rollback.

## 1. What exists today

| Area | Current PHP | Becomes |
|---|---|---|
| Routing | `Core/Router.php`, `routes/web.php` | Flask blueprints |
| Controllers / Services / Repositories | 3 API controllers, 6 services, 6 repositories | Blueprint routes, service modules, SQLAlchemy models |
| Auth | Sessions, CSRF, remember-me devices, login-attempt throttling, profile / password / revoke devices | Flask auth layer (see section 3) |
| Data | `documents` (JSON `data` column), `customers`, `items`, `document_numbers`, `users`, `login_attempts`, `auth_devices` | Same tables, as SQLAlchemy models |
| UI | 8 server-rendered pages, `editor_logic.php` (~700 lines of JS), `editor.css`, `document.css` | Vue pages and components |
| Rendering | Two renderers: JS live preview in the editor and PHP `document_render.php` for print | One `DocumentView.vue` component used by both |
| Bulk I/O | JSON export / import for documents, customers, items | Same endpoints, same file format |

## 2. Target architecture

```
invoice-app/
├─ backend/
│  ├─ app/
│  │  ├─ __init__.py          # create_app() factory
│  │  ├─ config.py            # from .env
│  │  ├─ extensions.py        # db, migrate, limiter, csrf
│  │  ├─ models/              # document, customer, item, user, auth_device, ...
│  │  ├─ schemas/             # Pydantic validation + serialization
│  │  ├─ api/v1/              # auth, documents, customers, items, numbers, profile
│  │  └─ services/            # numbering, conversion, import_export, auth
│  ├─ migrations/             # Alembic
│  ├─ tests/
│  └─ wsgi.py
├─ frontend/
│  └─ src/                    # api/, stores/, router/, views/, components/, composables/, assets/
├─ docker-compose.yml
└─ nginx.conf
```

**Stack**

- Backend: Flask 3, SQLAlchemy 2, Flask-Migrate (Alembic), Pydantic v2, Flask-Limiter, pytest, gunicorn
- Frontend: Vue 3 (Composition API), Vite, Pinia, Vue Router, Axios or `fetch`, Vitest, Playwright
- Styling: reuse the existing CSS files so the look stays identical

## 3. Key design decisions

1. **Auth.** Serve the SPA and the API from the same origin (nginx routes `/api` to Flask). Use a HttpOnly, SameSite=Lax session cookie plus a CSRF header. This maps almost 1:1 onto the current setup and keeps the "devices" list and "revoke device" features simple. JWT would work but adds token storage and revocation problems you don't have today.
2. **Passwords.** PHP `password_hash` produces bcrypt hashes. Verify them in Python with `bcrypt` (swap the `$2y$` prefix for `$2b$`) so nobody has to reset a password. Confirm the algorithm in `AuthService.php` first.
3. **REST cleanup.** The current API uses `POST /:id/delete` and `POST /:id` for updates. The new one uses proper verbs under `/api/v1`, and errors use real HTTP status codes with a body like `{error, code, details}`:

| Old | New |
|---|---|
| `POST /api/documents/:id` | `PUT /api/v1/documents/:id` |
| `POST /api/documents/:id/delete` | `DELETE /api/v1/documents/:id` |
| `POST /api/documents/:id/duplicate` | `POST /api/v1/documents/:id/duplicate` |
| `POST /api/documents/:id/convert` | `POST /api/v1/documents/:id/convert` (quote → invoice) |
| `GET /api/numbers/next` | `POST /api/v1/numbers/next` (it changes state) |
| `GET /api/numbers/peek` | `GET /api/v1/numbers/peek` |
| `GET /api/documents/export`, `POST /import` | same paths under `/v1` |
| customers and items | same pattern |

4. **Document `data` JSON.** Keep it as a JSON column, validated by a Pydantic schema. Normalizing line items into tables can come later; doing it now adds weeks and risks invoice data.
5. **Print and PDF.** Phase 1 keeps today's behavior: a `/documents/:id/print` Vue route using `@media print` and the browser's "Save as PDF". Optional later: server-side PDF with WeasyPrint.
6. **Issuer and bank defaults.** Hard-coded in `defaultTemplate()` today. Optionally move them to a `settings` table or the profile page.

## 4. Phases

### Phase 0: Safety net (1–2 days)

- Run the PHP app locally and record **golden JSON responses** for every API endpoint (list, show, defaults, next number, import / export).
- Write down the business rules:
  - numbering format `PREFIX-YYYYMMDD-counter`
  - customer upsert on save
  - import skips duplicates by type + number
  - convert-quote rules
  - statuses `draft` / `final` / `paid`
- Take a mysqldump backup and work on a copy of the database.
- **Done when:** you have a fixtures folder and a rules checklist.

### Phase 1: Flask skeleton + database (2 days)

1. App factory, config from `.env`, `/api/v1/health`.
2. SQLAlchemy models mirroring the existing tables exactly.
3. Alembic baseline: generate it, then run `alembic stamp head` against the existing database so nothing is recreated.
4. Docker Compose with Flask, MySQL and (later) nginx.

**Done when:** Flask boots and reads real rows from the copied DB.

### Phase 2: Auth (2–3 days)

1. Login, logout, session ping, CSRF token endpoint.
2. Login throttling with Flask-Limiter, plus the existing `login_attempts` table if you want identical behavior.
3. Remember-me and device tracking: `auth_devices`, list, revoke one, revoke all others.
4. Profile update and password change.
5. A `@login_required` decorator for all other endpoints.

**Done when:** pytest covers wrong password, throttling, CSRF rejection, revoked device, and an existing PHP user logging in.

### Phase 3: Customers and Items API (2 days)

The simplest resources, so build them first to establish the pattern.

- List with `q` search, show, create, update, delete, JSON import / export (same file format).

**Done when:** responses match the golden fixtures, apart from the intentional REST changes.

### Phase 4: Documents API (4–5 days)

1. List with filters (search, type, status, date range), show, create, update, delete.
2. Pydantic schema for the `data` blob, including `po_number`, `quote_ref`, service dates, `show_amount_paid`.
3. **Numbering:** atomic counter using MySQL `INSERT … ON DUPLICATE KEY UPDATE counter = LAST_INSERT_ID(counter + 1)`. Add a concurrency test.
4. **Customer upsert** on save.
5. **Duplicate** (appends `-COPY`) and **convert quote → invoice**, including the "already converted" 409 check via `JSON_EXTRACT` on `quote_ref`.
6. **Import / export**, including the legacy `service_date` → `service_date_start` mapping and skipping existing type + number pairs.
7. **Default template** endpoint.

**Done when:** the whole business-rules checklist is covered by tests and old exports import cleanly.

### Phase 5: Vue shell (1–2 days)

1. Vite + Vue 3 + Pinia + Router; dev proxy `/api` → Flask.
2. API client with CSRF header handling and a global 401 → login redirect.
3. Auth store, route guards, app layout (topbar, user menu), toast and confirm-modal components.
4. Port the shared CSS.

**Done when:** you can log in from the SPA and see an empty layout.

### Phase 6: Pages, in this order (8–10 days)

1. **Login**
2. **Customers:** table, search, create / edit modal, import / export
3. **Items:** same
4. **Documents list:** filters remembered in session storage, date presets, Copy, Delete, **→ Invoice**, export / import
5. **Profile:** details, password, devices
6. **`DocumentView.vue`:** the single renderer ported from `document_render.php`; takes a document object and renders A4 with `document.css`
7. **Editor** (the big one, 4–5 days):
   - Replace `collectDoc()` / `populateForm()` and DOM-reading code with one reactive `doc` object and `v-model`.
   - Totals, VAT and balance become `computed`.
   - Components: `LineItemsTable`, `CustomerPicker`, `ItemPicker`, `NumberField` (generate button), `CollapsibleSection`, and a live-preview pane reusing `DocumentView`.
   - Port autosave and dirty state, "Convert to Invoice" (quotes only) and the PO field.
8. **Print route** (`/documents/:id/print`) and the **Documentation** page (static content)

**Done when:** each page matches its PHP twin side by side.

### Phase 7: Tests (about 3 days, alongside Phases 4–6)

- **Backend:** pytest against a test MySQL: services, endpoints, numbering concurrency, auth.
- **Frontend:** Vitest for totals / VAT calculations and stores.
- **End to end (Playwright):** login → create quote → convert to invoice → print; pick customer; pick item; import / export round trip.
- **Visual check:** compare printed output of 5–10 real documents between PHP and Vue.

### Phase 8: Deployment and cutover (1–2 days)

1. Build the Vue app; nginx serves `dist/` and proxies `/api` to gunicorn. Add HTTPS, security headers, gzip.
2. Deploy on a staging subdomain against a copy of the production DB.
3. Run a week of real use, then take a final backup and switch the domain.
4. Keep the PHP app available but stopped for a few weeks as a rollback.

**Done when:** production runs on Flask + Vue with a working backup and restore.

### Phase 9: Cleanup (optional)

Remove the PHP code, add server-side PDF, normalize line items into tables, add a settings table, publish an OpenAPI spec (`flask-smorest` or `apispec`).

## 5. Estimated effort

| Phase | Days |
|---|---|
| 0 Safety net | 1–2 |
| 1 Skeleton + DB | 2 |
| 2 Auth | 2–3 |
| 3 Customers / Items | 2 |
| 4 Documents API | 4–5 |
| 5 Vue shell | 1–2 |
| 6 Pages | 8–10 |
| 7 Tests (parallel) | ~3 |
| 8 Deploy | 1–2 |
| **Total** | **~25–30 working days** |

Roughly 5–6 weeks full-time, or 2–3 months part-time.

## 6. Main risks

- **Editor rewrite:** the largest chunk, and the old code reads state from the DOM. Build `DocumentView` and the totals logic first and test them heavily, since errors there mean wrong invoices.
- **Numbering collisions** during side-by-side testing, because both apps share `document_numbers`. Test against a copy of the DB, not production.
- **Hidden PHP behavior** (date handling, rounding, default values). The golden fixtures from Phase 0 catch these.
- **Print layout drift** across browsers. Compare real printed documents.

## 7. Open questions

1. Single-user or multi-user? If multi-user, documents may need an owner column.
2. JWT or the cookie-session approach recommended above?
3. Hosting: VPS or shared hosting? Flask needs a process manager, which shared hosting often lacks.
4. TypeScript or plain JavaScript for Vue?
5. Server-side PDF generation, or is browser "Save as PDF" enough?