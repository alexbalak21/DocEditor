# Frontend plan: Vue 3 SPA

Replaces the 8 server-rendered PHP pages, `editor_logic.php` (~700 lines of DOM-driven JS) and the two separate renderers with a Vue 3 single-page app that talks to the Flask API in `backend_plan.md`. The editor is built around the modular template system from `implement_template.md`.

## 1. Principles

- **State lives in data, not in the DOM.** One reactive `doc` object replaces `collectDoc()` / `populateForm()`. Totals, VAT and balance are `computed`.
- **One renderer.** The editor preview and the print page both display the HTML rendered by the backend, so they can never disagree.
- **The form is generated from the manifest.** A new template pack needs no new Vue code, only a new folder on the backend.
- **One page at a time.** Each page is finished and checked against its PHP twin before the next starts.
- **Reuse the existing CSS** (`editor.css`, `document.css`) so the look stays identical.

## 2. Stack

| Concern | Choice |
|---|---|
| Framework | Vue 3, Composition API, `<script setup>` |
| Build | Vite |
| State | Pinia |
| Routing | Vue Router |
| HTTP | `fetch` wrapper (or Axios) |
| Unit tests | Vitest + Vue Test Utils |
| End-to-end | Playwright |
| Language | Plain JavaScript with JSDoc (TypeScript is optional, see decisions) |

## 3. Project layout

```
frontend/
├─ src/
│  ├─ api/                 # client.js (CSRF, 401 handling), documents.js, customers.js, ...
│  ├─ stores/              # auth, documents, customers, items, templates, ui
│  ├─ router/              # routes + guards
│  ├─ views/               # LoginView, DocumentsView, EditorView, CustomersView,
│  │                       # ItemsView, ProfileView, PrintView, DocsView
│  ├─ components/
│  │  ├─ layout/           # AppLayout, TopBar, UserMenu
│  │  ├─ ui/               # Modal, ConfirmModal, Toast, Pagination, DateRange
│  │  ├─ form/             # DynamicForm, SectionCard, field components
│  │  └─ document/         # DocumentPreview, LineItemsTable, pickers
│  ├─ composables/         # useAutosave, useDirtyState, useTotals, useDebounce
│  ├─ utils/               # money.js, dates.js, calculators.js
│  └─ assets/css/          # existing CSS files
├─ tests/
├─ index.html
├─ vite.config.js          # dev proxy /api -> Flask
└─ package.json
```

## 4. Routes

| Path | View | Notes |
|---|---|---|
| `/login` | LoginView | Public |
| `/documents` | DocumentsView | Default page after login |
| `/documents/new` | TemplateChooser, then EditorView | Chooser lists templates from the API |
| `/documents/:id` | EditorView | |
| `/documents/:id/print` | PrintView | Bare layout, no app chrome |
| `/customers` | CustomersView | |
| `/items` | ItemsView | |
| `/profile` | ProfileView | Details, password, devices |
| `/docs` | DocsView | Static documentation |

A global guard redirects unauthenticated users to `/login` and remembers the target.

## 5. API client and auth

- Single `client.js`: base URL `/api/v1`, `credentials: 'same-origin'`, adds the `X-CSRF-Token` header on write requests, parses the `{error, code, details}` error body.
- A global **401 handler** clears the auth store and redirects to login.
- Auth store holds the current user and CSRF token, and loads `/auth/session` on startup.
- Errors surface through a shared toast component. Field-level `details` map onto form fields.

## 6. State (Pinia stores)

| Store | Holds |
|---|---|
| `auth` | user, CSRF token, login / logout |
| `templates` | list of templates and manifests, cached per slug and version |
| `documents` | list, filters (persisted in session storage), current document |
| `customers`, `items` | lists, search, used by pickers |
| `ui` | toasts, confirm modal, sidebar state |

## 7. The dynamic form (core of the app)

The manifest from `GET /templates/:slug` describes sections and fields. `DynamicForm.vue` loops over them and renders one component per field type from a **registry**:

| Manifest type | Component |
|---|---|
| `text`, `textarea` | TextField, TextAreaField |
| `number`, `money` | NumberField, MoneyField |
| `date` | DateField |
| `select`, `checkbox` | SelectField, CheckboxField |
| `number` with `generate: true` | NumberField with the generate button (`POST /numbers/next`) |
| `customer` (block + picker) | CustomerPicker |
| `items` | LineItemsTable with ItemPicker |
| `image` | ImageField |

Supporting behavior:

- `visible_if`, `required`, `default` and `readonly` are applied from the manifest.
- Client-side validation mirrors the manifest. The server stays the authority.
- Sections are collapsible (`CollapsibleSection`).
- Unknown field types render a visible warning instead of failing silently.

## 8. The editor

Layout: form on one side, live preview on the other.

- **State:** a single reactive `doc` object, filled from the API or from the template defaults for a new document.
- **Totals:** a `useTotals(doc, manifest.calculations)` composable using `utils/calculators.js` (`totals_with_vat`, `none`). The logic is a port of the backend calculator and is tested against the same fixtures.
- **Preview:** `DocumentPreview.vue` posts `doc` to `/render` with a short debounce (about 300 ms) and shows the returned HTML in `<iframe sandbox srcdoc>`. Keep the last good render visible while a new one loads.
- **Autosave and dirty state:** `useAutosave` and `useDirtyState`, with a "leave without saving?" guard on navigation.
- **Actions:** save, duplicate, delete, print, and **Convert to...** buttons generated from the manifest's `conversions` (replaces the hard-coded "Convert to Invoice").
- **Status:** options come from the manifest.
- **Version pinning:** show which template version a document uses, with an optional "upgrade" action.

## 9. Other pages

- **Login:** username and password, remember-me, throttling and error messages from the API.
- **Documents list:** table, search, type / status / date-range filters with presets, filters remembered in session storage, row actions (open, Copy, Delete, convert), JSON export and import with a per-row result report.
- **Customers and Items:** table with search, create / edit modal, delete with confirmation, JSON import / export (same file format as today).
- **Profile:** details form, password change, device list with revoke one and revoke all others.
- **Print view:** fetches `/documents/:id/render`, shows the sandboxed HTML on a bare page, uses `@media print` and A4 sizing, and the browser's "Save as PDF". Server-side PDF can come later.
- **Docs page:** static content.
- **Template chooser:** cards with name and preview thumbnail from `GET /templates`.

## 10. Styling

- Port `editor.css` and `document.css` as global styles first, so the look stays identical.
- Template styles come from the pack's own `style.css`, scoped under `.doc`, and are shown only inside the preview iframe.
- Responsive basics for the list pages. The editor stays desktop-first.

## 11. Testing

- **Vitest:** `calculators.js` (totals, VAT, rounding, edge cases), stores, the API client (CSRF, 401), field components, `DynamicForm` with a sample manifest.
- **Playwright end-to-end:** login, create quote, convert to invoice, print, pick customer, pick item, import / export round trip, a Delivery Note created from a new pack.
- **Visual check:** compare printed output of 5-10 real documents between PHP and Vue.

## 12. Phases

| Phase | Work | Days | Done when |
|---|---|---|---|
| 1 Shell | Vite, Router, Pinia, dev proxy, API client, auth store, guards, layout, toast and confirm modal, shared CSS | 1-2 | You can log in and see an empty layout |
| 2 Login + simple lists | Login, Customers, Items (establishes the pattern) | 2-3 | Match the PHP pages |
| 3 Documents list | Filters, presets, Copy, Delete, convert, import / export | 2 | Match the PHP list page |
| 4 Profile | Details, password, devices | 1 | Device revoke works |
| 5 Dynamic form + editor | Field registry, DynamicForm, LineItemsTable, pickers, totals, preview iframe, autosave, conversions | 6-8 | A real invoice and quote can be created and saved |
| 6 Print + docs | Print route, Documentation page | 1 | Printed output matches PHP |
| 7 Template chooser + new templates | Chooser, Delivery Note pack tested end to end | 1-2 | A new pack works with no Vue changes |
| 8 Tests + polish | Playwright flows, visual comparison, accessibility pass | 3 (parallel) | Checklist passes |

Total about **17-22 working days**, with tests written alongside.

## 13. Risks

- **Editor rewrite** is the biggest chunk, and the old code reads state from the DOM. Build the calculators and the field components first and test them heavily, since errors mean wrong invoices.
- **Calculator drift** between frontend and backend. Share fixtures and test both against them.
- **Preview latency** if the server render is slow. Debounce, cancel stale requests, keep the last good render visible.
- **Print layout drift** across browsers. Compare real printed documents.
- **Manifest edge cases.** Validate manifests at load time on the backend so the form never has to guess.

## 14. Decisions needed

1. TypeScript or plain JavaScript? Plain JS with JSDoc is lighter, TypeScript catches more mistakes.
2. Is a debounced server-rendered preview good enough, or do you need instant keystroke-level preview (the template engine would then also run in the browser)?
3. Should non-technical users edit templates in the app (a template manager UI), or are templates developer-only for now?
4. Which extra document types first, and what fields does each need?