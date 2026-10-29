# Plan: modular document templates

Each document type becomes a **template pack**: a small folder that describes what the document looks like and what data it holds. The app reads the pack to build the form, validate the data, render the preview and print it. Adding a Delivery Note then means adding a folder, not changing code.

> Assumption: "Delivery Note, Delivery Note" in the original request was a typo, and the second one is something like Purchase Order or Packing Slip. Adjust if you meant something else.

## 1. The template pack

```
templates/
├─ invoice/
│  ├─ manifest.json     # data + form description
│  ├─ template.html     # layout with placeholders
│  ├─ style.css         # scoped print styles
│  └─ preview.png       # optional thumbnail
├─ quote/
├─ delivery-note/
└─ _blocks/             # shared field groups: issuer, customer, bank...
```

**Format choice:** JSON, validated with a JSON Schema. XML adds nothing here, and YAML is nicer to hand-edit but can be added later.

### manifest.json (abbreviated invoice)

```json
{
  "id": "invoice",
  "version": 3,
  "name": "Invoice",
  "extends": ["_blocks/issuer", "_blocks/customer"],
  "numbering": { "prefix": "INV", "pattern": "{prefix}-{date:YYYYMMDD}-{counter}" },
  "statuses": ["draft", "final", "paid"],
  "page": { "size": "A4", "margin": "12mm" },
  "sections": [
    { "id": "header", "label": "Document", "fields": [
      { "key": "number",    "type": "number", "label": "Number", "generate": true },
      { "key": "date",      "type": "date",   "label": "Date", "required": true },
      { "key": "due_date",  "type": "date",   "label": "Due date" },
      { "key": "po_number", "type": "text",   "label": "PO Number", "hint": "optional" },
      { "key": "quote_ref", "type": "text",   "label": "Quote ref.", "readonly": true }
    ]},
    { "id": "customer", "label": "Bill To", "block": "_blocks/customer", "picker": "customer" },
    { "id": "items", "label": "Items", "fields": [
      { "key": "items", "type": "items", "picker": "item", "columns": [
        { "key": "name",       "type": "text",   "label": "Item" },
        { "key": "qty",        "type": "number", "label": "Qty", "default": 1 },
        { "key": "unit_price", "type": "money",  "label": "Unit price" }
      ]},
      { "key": "vat_rate", "type": "number", "label": "VAT %", "default": 0 }
    ]}
  ],
  "calculations": "totals_with_vat",
  "conversions": []
}
```

### What differs for a Delivery Note

- No `unit_price` or VAT; `qty` only.
- Extra fields: delivery address, carrier, tracking, "received by" and a signature line.
- `numbering.prefix` is `DN`.
- `calculations: "none"`.
- `conversions` is empty. The Quote's manifest gets `"conversions": [{"to": "invoice", "map": {...}}, {"to": "delivery-note", "map": {...}}]`.

### template.html

```html
<section class="doc">
  <h1>{{ template.name | upper }}</h1>
  <div class="meta">No. {{ doc.number }} · {{ doc.date | date }}</div>
  {% if doc.po_number %}<div>PO: {{ doc.po_number }}</div>{% endif %}
  <table>{% for it in doc.items %}
    <tr><td>{{ it.name }}</td><td>{{ it.qty }}</td><td>{{ it.unit_price | money }}</td></tr>
  {% endfor %}</table>
  {% if totals %}<div class="total">{{ totals.total | money }}</div>{% endif %}
</section>
```

All CSS is scoped under `.doc`, so one template can't leak styles into the app.

## 2. Key design decisions

1. **One data object per document**, keyed by the manifest's field keys. The existing JSON `data` column already works this way, so the storage model barely changes.
2. **Declarative calculations, not code.** The manifest names a built-in calculator (`totals_with_vat`, `none`, later `totals_with_discount`). A formula language is a lot of work and a security risk. Add new calculators in code as needed.
3. **Reusable blocks.** Issuer, customer and bank are defined once in `_blocks/` and referenced, instead of copied into every template.
4. **Version pinning.** Each document stores `template_id` and `template_version`, so a later template edit never changes an old invoice. An optional "upgrade to latest version" action lets you opt in.
5. **One renderer.** The editor preview and the print page both use the server-rendered HTML. This also fixes today's problem of having two separate renderers (JS preview and PHP print).
6. **Server-side rendering with a sandboxed engine.** In Flask that's Jinja2's `SandboxedEnvironment` with autoescape. The preview is shown in an `<iframe sandbox srcdoc>`, refreshed with a short debounce while you type.

## 3. Implementation phases

### T0: Spec (1 day)

Write the manifest JSON Schema v1:

- Field types: `text`, `textarea`, `date`, `number`, `money`, `select`, `checkbox`, `image`, `customer`, `items`
- Field options: `visible_if`, `required`, `default`, `readonly`
- Fix the placeholder syntax and the list of filters (`money`, `date`, `upper`)

### T1: Extract the current template (2 days)

Turn today's single template into `invoice/` and `quote/` packs, and check the output matches the current PHP print view pixel for pixel. This validates the format before any engine exists.

### T2: Data model (1 day)

- Add a `templates` table (`slug`, `version`, `name`, `manifest`, `html`, `css`, `is_builtin`).
- Add `template_id` / `template_version` columns on `documents`.
- Backfill existing documents to the invoice and quote packs by type.

### T3: Backend (3–4 days)

- **Loader and validator:** schema-validate the manifest, resolve `extends` and blocks, and check that every placeholder in the HTML references a declared field.
- **Endpoints:**
  - `GET /api/v1/templates`
  - `GET /api/v1/templates/:slug` (manifest plus defaults)
  - `POST /api/v1/render` (document data in, HTML out)
  - `GET /api/v1/documents/:id/render`
- Per-template numbering from the manifest.
- Server-side validation of document data against the manifest.
- Conversions (Quote → Invoice / Delivery Note) driven by the manifest's `map`.

### T4: Dynamic form in Vue (5–6 days)

- `DynamicForm.vue` loops over sections and renders one component per field type from a registry (`TextField`, `DateField`, `MoneyField`, `ItemsTable`, `CustomerPicker`, ...).
- The existing pickers, the number-generate button and the PO field become reusable field components.
- `visible_if` conditions, client-side validation from the manifest, and the calculator running on the client for live totals.
- "New document" opens a template chooser, replacing the current type dropdown.

### T5: Per-template behavior (2 days)

Statuses, numbering prefix and counter scope, and the "Convert to…" buttons come from the manifest, so the Quote's "Convert to Invoice" button is generated rather than hard-coded.

### T6: New templates (about 1 day each)

Delivery Note first, since it exercises the "no prices, extra fields, signature" case. Then Purchase Order, Credit Note, Proforma.

### T7: Template management UI (4–5 days, optional)

- List and preview templates
- Duplicate a built-in one
- Edit HTML, CSS and manifest in a code editor with live preview
- Import / export a pack as a zip
- Version history

## 4. Security and validation

- Strip `<script>`, event attributes and external resources from uploaded HTML (e.g. with `nh3`), and allow images only as data URIs or uploaded assets.
- Sandboxed Jinja2 with no attribute access to Python internals, and limits on file size and template complexity.
- Only trusted users may upload or edit templates, since an edited template changes how every document is rendered.
- Tests: golden-file rendering tests per template, schema-violation tests, and an XSS test with hostile field values.

## 5. Effort and timing

| Part | Days |
|---|---|
| T0–T2 spec, extraction, data model | ~4 |
| T3 backend | 3–4 |
| T4 dynamic form | 5–6 |
| T5 per-template behavior | 2 |
| T6 first extra template | 1 |
| **Core total** | **~15–17 days** |
| T7 template manager (optional) | 4–5 |

## 6. How it fits your roadmap

- **Do T0 and T1 now.** They're plain files and decisions, and extracting the current template into a pack is useful whatever you build next.
- **Build T2 to T5 inside the Flask + Vue rewrite**, not in the current PHP app. Doing it twice would be wasted effort. Fold it into Phases 4 and 6 of the migration plan, so the Vue editor is dynamic from the start rather than a hard-coded form you replace later.

## 7. Decisions needed

1. Do you want to build and edit templates yourself (developer-only packs in the repo), or should non-technical users upload and edit them in the app? This decides whether T7 is needed and how strict the security work is.
2. Should Quote and Invoice stay as separate packs that share blocks, or be one pack with a "mode"? Separate packs are recommended.
3. Is server-rendered HTML with a debounced preview acceptable, or do you need instant keystroke-level preview? The instant version needs the same template engine running in the browser too.
4. Which extra documents do you want first, and what fields does each need?