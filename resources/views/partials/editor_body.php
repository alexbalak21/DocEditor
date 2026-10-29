<?php
/**
 * This partial is the full split-panel editor.
 * Expects: $editId (int|null), $newType (string), $logoDataUri (string)
 * API routes use the new REST paths: /api/documents, /api/customers, /api/items
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/document.css">
<style>
  html, body { height: 100%; overflow: hidden; }
  .app-body  { height: calc(100vh - var(--topbar-h)); }
</style>

<div class="app-body">

  <!-- LEFT: form panel -->
  <aside class="form-panel">
    <div class="form-panel-scroll" id="form-scroll">

      <div class="form-section">
        <div class="form-section-header open" data-section="header">Document Header <span class="chevron">▼</span></div>
        <div class="form-section-body open" id="sec-header">
          <div class="field"><label>Document Type</label>
            <select id="f-type">
              <option value="INVOICE">Invoice</option><option value="QUOTE">Quote</option>
              <option value="CREDIT NOTE">Credit Note</option><option value="OTHER">Other</option>
            </select></div>
          <div class="field-row col2">
            <div class="field"><label>Number</label>
              <div class="input-with-btn">
                <input id="f-number" type="text" placeholder="INV-20261006-1">
                <button type="button" id="btn-gen-number" title="Generate next document number"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
              </div>
            </div>
            <div class="field"><label>Date</label><input id="f-date" type="date"></div>
          </div>
          <div class="field-row col2">
            <div class="field"><label>Due Date</label><input id="f-due-date" type="date"></div>
            <div class="field"><label>Tracking #</label><input id="f-tracking" type="text"></div>
          </div>
          <div class="field-row col2">
            <div class="field"><label>Service Date — Start</label><input id="f-service-start" type="date"></div>
            <div class="field"><label>Service Date — End</label><input id="f-service-end" type="date"></div>
          </div>
          <div class="field-row col2">
            <div class="field"><label>Quote Ref.</label><input id="f-quote-ref" type="text"></div>
            <div class="field"><label>PO Number <span style="color:var(--ui-text-muted);font-weight:400">(optional)</span></label><input id="f-po" type="text" placeholder="Customer purchase order"></div>
          </div>
          <div class="field-row col2">
            <div class="field"><label>Currency</label>
              <select id="f-currency"><option value="EUR">EUR</option><option value="USD">USD</option><option value="GBP">GBP</option><option value="CHF">CHF</option></select></div>
            <div class="field"><label>Symbol</label><input id="f-currency-symbol" type="text" maxlength="4" placeholder="€"></div>
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-header open" data-section="customer">
          <span>Bill To</span>
          <span style="display:flex;align-items:center;gap:10px;margin-left:auto">
            <button type="button" id="btn-pick-customer" class="btn-pick-customer" title="Search existing customers"><i class="fa-solid fa-address-book"></i> Pick customer</button>
            <span class="chevron">▼</span>
          </span>
        </div>
        <div class="form-section-body open" id="sec-customer">
          <div class="field"><label>Company / Customer Name</label><input id="f-cust-name" type="text" placeholder="Pureture"></div>
          <div class="field"><label>Address</label><input id="f-cust-addr" type="text"></div>
          <div class="field"><label>City / Country</label><input id="f-cust-city" type="text"></div>
          <div class="field-row col2">
            <div class="field"><label>Contact</label><input id="f-cust-contact" type="text"></div>
            <div class="field"><label>Phone</label><input id="f-cust-phone" type="text"></div>
          </div>
          <div class="field"><label>VAT Number</label><input id="f-cust-vat" type="text"></div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-header open" data-section="items">Line Items <span class="chevron">▼</span></div>
        <div class="form-section-body open" id="sec-items">
          <table class="items-form-table">
            <thead><tr><th>Name / Description</th><th class="th-ref">Ref.</th><th class="th-price">Unit Price</th><th class="th-qty">Qty</th><th class="th-del"></th></tr></thead>
            <tbody id="items-tbody"></tbody>
          </table>
          <button class="btn btn-primary btn-sm btn-add-row" id="btn-add-item"><i class="fa-solid fa-plus"></i> Add line</button>
          <button class="btn btn-secondary btn-sm btn-add-row" id="btn-pick-item" style="margin-left:6px" title="Add a line from your saved items"><i class="fa-solid fa-boxes-stacked"></i> Pick item</button>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-header" data-section="totals">Totals &amp; VAT <span class="chevron">▼</span></div>
        <div class="form-section-body" id="sec-totals">
          <div class="field-row col2">
            <div class="field"><label>VAT Rate (%)</label><input id="f-vat-rate" type="number" min="0" max="100" step="0.1" value="0"></div>
            <div class="field"><label>Balance Label</label><input id="f-balance-label" type="text" placeholder="TOTAL DUE"></div>
          </div>
          <label class="field-check"><input type="checkbox" id="f-show-paid"> Show "Amount Paid" row</label>
          <div class="field"><label>Amount Paid</label><input id="f-amount-paid" type="number" min="0" step="0.01" value="0"></div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-header" data-section="text">Notes &amp; Terms <span class="chevron">▼</span></div>
        <div class="form-section-body" id="sec-text">
          <div class="field"><label>VAT Mention</label><textarea id="f-vat-mention" rows="2"></textarea></div>
          <div class="field"><label>Notes</label><textarea id="f-notes" rows="3"></textarea></div>
          <div class="field"><label>Terms &amp; Conditions</label><textarea id="f-terms" rows="4"></textarea></div>
          <div class="field"><label>Footer — Thanks</label><input id="f-footer-thanks" type="text"></div>
          <div class="field"><label>Footer — Contact</label><textarea id="f-footer-contact" rows="2"></textarea></div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-header" data-section="bank">Bank Details <span class="chevron">▼</span></div>
        <div class="form-section-body" id="sec-bank">
          <div class="field"><label>Section Label</label><input id="f-bank-label" type="text"></div>
          <div class="field"><label>Beneficiary</label><input id="f-bank-bene" type="text"></div>
          <div class="field"><label>Bank Name</label><input id="f-bank-name" type="text"></div>
          <div class="field"><label>Bank Address</label><input id="f-bank-addr" type="text"></div>
          <div class="field"><label>IBAN</label><input id="f-bank-iban" type="text"></div>
          <div class="field"><label>BIC / SWIFT</label><input id="f-bank-bic" type="text"></div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-header" data-section="issuer">Issuer <span class="chevron">▼</span></div>
        <div class="form-section-body" id="sec-issuer">
          <div class="field"><label>Company Name</label><input id="f-issuer-name" type="text"></div>
          <div class="field"><label>Address</label><input id="f-issuer-addr" type="text"></div>
          <div class="field"><label>City / Country</label><input id="f-issuer-city" type="text"></div>
          <div class="field"><label>Email</label><input id="f-issuer-email" type="email"></div>
          <div class="field"><label>Legal Info</label><textarea id="f-issuer-legal" rows="3"></textarea></div>
        </div>
      </div>

    </div>
    <div class="autosave-status">
      <div class="autosave-dot" id="autosave-dot"></div>
      <span id="autosave-label">Not saved yet</span>
    </div>
  </aside>

  <!-- RIGHT: live preview -->
  <main class="preview-panel" id="preview-panel">
    <div id="preview-doc"></div>
  </main>
</div>

<!-- Customer picker modal -->
<div id="customer-modal" class="cust-modal-overlay" style="display:none" onclick="if(event.target===this)closeCustomerPicker()">
  <div class="cust-modal">
    <div class="cust-modal-header"><h3><i class="fa-solid fa-users"></i> Pick a Customer</h3>
      <button class="cust-modal-close" onclick="closeCustomerPicker()"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="cust-modal-search"><input type="text" id="customer-search" placeholder="Search name, city…" oninput="searchCustomers(this.value)" autocomplete="off"></div>
    <div class="cust-modal-list" id="customer-modal-list"><div class="cust-empty">Loading…</div></div>
    <div class="cust-modal-footer">
      <a href="<?= BASE_URL ?>/customers" target="_blank" class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none"><i class="fa-solid fa-gear"></i> Manage customers</a>
    </div>
  </div>
</div>

<!-- Item picker modal -->
<div id="item-modal" class="cust-modal-overlay" style="display:none" onclick="if(event.target===this)closeItemPicker()">
  <div class="cust-modal">
    <div class="cust-modal-header"><h3><i class="fa-solid fa-boxes-stacked"></i> Pick an Item</h3>
      <button class="cust-modal-close" onclick="closeItemPicker()"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="cust-modal-search"><input type="text" id="item-search" placeholder="Search reference, title…" oninput="searchItems(this.value)" autocomplete="off"></div>
    <div class="cust-modal-list" id="item-modal-list"><div class="cust-empty">Loading…</div></div>
    <div class="cust-modal-footer">
      <a href="<?= BASE_URL ?>/items" target="_blank" class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none"><i class="fa-solid fa-gear"></i> Manage items</a>
    </div>
  </div>
</div>

<!-- Paste JSON modal -->
<div id="paste-modal" class="cust-modal-overlay" style="display:none" onclick="if(event.target===this)closePasteModal()">
  <div class="cust-modal paste-modal">
    <div class="cust-modal-header"><h3><i class="fa-solid fa-paste"></i> Paste JSON</h3>
      <button class="cust-modal-close" onclick="closePasteModal()"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="paste-body">
      <textarea id="paste-json" class="paste-area" spellcheck="false" placeholder="Paste the JSON your AI assistant generated here…"></textarea>
      <div id="paste-error" class="paste-error"></div>
    </div>
    <div class="cust-modal-footer paste-footer">
      <span class="paste-hint">Replaces the current form · Ctrl+Enter to load</span>
      <span style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" onclick="closePasteModal()">Cancel</button>
        <button class="btn btn-primary btn-sm" id="btn-paste-load"><i class="fa-solid fa-file-import"></i> Load into editor</button>
      </span>
    </div>
  </div>
</div>

<script>
// ── Constants injected from PHP ───────────────────────────────
const EDIT_ID      = <?= $editId ? (int)$editId : 'null' ?>;
const NEW_TYPE     = <?= json_encode($newType) ?>;
const LOGO_DATA_URI= <?= json_encode($logoDataUri) ?>;

// ── All API calls now use new REST routes ─────────────────────
const API = {
  docList:      (q,type) => `${BASE}/api/documents?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}`,
  docGet:       (id)     => `${BASE}/api/documents/${id}`,
  docSave:      ()       => `${BASE}/api/documents`,
  docUpdate:    (id)     => `${BASE}/api/documents/${id}`,
  docDefault:   (type)   => `${BASE}/api/documents/default?type=${encodeURIComponent(type)}`,
  nextNumber:   (type)   => `${BASE}/api/numbers/next?type=${encodeURIComponent(type)}`,
  customers:    (q)      => `${BASE}/api/customers?q=${encodeURIComponent(q)}`,
  customerGet:  (id)     => `${BASE}/api/customers/${id}`,
  items:        (q)      => `${BASE}/api/items?q=${encodeURIComponent(q)}`,
  itemGet:      (id)     => `${BASE}/api/items/${id}`,
};

<?php
// Inline the full JS logic from the old editor.php — it's identical except API URLs.
// We copy the relevant sections from the old file verbatim and replace api.php?action=X with API.X.
// The complete JS is in editor_logic.js which is inlined here via PHP include for maintainability.
include ROOT . '/resources/views/partials/editor_logic.php';
?>
</script>
