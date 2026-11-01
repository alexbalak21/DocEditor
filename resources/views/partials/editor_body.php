<?php
/**
 * Template-aware split-panel editor.
 * Expects: $editId (int|null), $newType (string), $logoDataUri (string)
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/document.css">
<style>
  html, body { height: 100%; overflow: hidden; }
  .app-body  { height: calc(100vh - var(--topbar-h)); }
  /* Template-feature hiding — JS adds/removes these classes on <body> */
  body.tpl-hide-prices .tpl-price-col,
  body.tpl-hide-prices .tpl-price-only { display: none !important; }
  body.tpl-hide-totals  #sec-totals   { display: none !important; }
  body.tpl-hide-bank    .form-section-bank { display: none !important; }
  body.tpl-hide-vat     #f-vat-mention-wrap,
  body.tpl-hide-vat     #f-vat-rate-wrap   { display: none !important; }
  body.tpl-hide-due-date   #f-due-date-wrap   { display: none !important; }
  body.tpl-hide-service    #f-service-wrap    { display: none !important; }
  body.tpl-hide-quote-ref  #f-quote-ref-wrap  { display: none !important; }
  body.tpl-hide-po         #f-po-wrap         { display: none !important; }
  body.tpl-hide-tracking   #f-tracking-wrap   { display: none !important; }
  body.tpl-show-delivery   #sec-delivery      { display: block !important; }
  .sec-delivery-hidden     { display: none; }
</style>

<div class="app-body">

  <!-- LEFT: form panel -->
  <aside class="form-panel">
    <div class="form-panel-scroll" id="form-scroll">

      <!-- Document Header -->
      <div class="form-section">
        <div class="form-section-header open" data-section="header">Document Header <i class="fa-solid fa-chevron-down chevron"></i></div>
        <div class="form-section-body open" id="sec-header">
          <div class="field">
            <label>Document Type</label>
            <select id="f-type">
              <option value="INVOICE">Invoice</option>
              <option value="QUOTE">Quote</option>
              <option value="DELIVERY NOTE">Delivery Note</option>
              <option value="PROFORMA">Proforma</option>
              <option value="CREDIT NOTE">Credit Note</option>
              <option value="OTHER">Other</option>
            </select>
          </div>
          <div class="field-row col2">
            <div class="field">
              <label>Number</label>
              <div class="input-with-btn">
                <input id="f-number" type="text" placeholder="INV-20261006-1">
                <button type="button" id="btn-gen-number" title="Generate next document number"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
              </div>
            </div>
            <div class="field"><label>Date</label><input id="f-date" type="date"></div>
          </div>
          <div class="field-row col2" id="f-due-date-wrap">
            <div class="field"><label>Due Date</label><input id="f-due-date" type="date"></div>
            <div class="field" id="f-tracking-wrap"><label>Tracking #</label><input id="f-tracking" type="text"></div>
          </div>
          <div class="field-row col2" id="f-service-wrap">
            <div class="field"><label>Service Date — Start</label><input id="f-service-start" type="date"></div>
            <div class="field"><label>Service Date — End</label><input id="f-service-end" type="date"></div>
          </div>
          <div class="field-row col2">
            <div class="field" id="f-quote-ref-wrap"><label>Quote Ref.</label><input id="f-quote-ref" type="text"></div>
            <div class="field" id="f-po-wrap"><label>PO Number <span style="color:var(--ui-text-muted);font-weight:400">(optional)</span></label><input id="f-po" type="text" placeholder="Customer purchase order"></div>
          </div>
          <div class="field-row col2">
            <div class="field"><label>Currency</label>
              <select id="f-currency"><option value="EUR">EUR</option><option value="USD">USD</option><option value="GBP">GBP</option><option value="CHF">CHF</option></select>
            </div>
            <div class="field"><label>Symbol</label><input id="f-currency-symbol" type="text" maxlength="4" placeholder="€"></div>
          </div>
        </div>
      </div>

      <!-- Bill To / Deliver To -->
      <div class="form-section">
        <div class="form-section-header open" data-section="customer">
          <span id="customer-section-label">Bill To</span>
          <span style="display:flex;align-items:center;gap:10px;margin-left:auto">
            <button type="button" id="btn-pick-customer" class="btn-pick-customer" title="Search existing customers"><i class="fa-solid fa-address-book"></i> Pick customer</button>
            <i class="fa-solid fa-chevron-down chevron"></i>
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

      <!-- Delivery Details (Delivery Note only) -->
      <div class="form-section sec-delivery-hidden" id="sec-delivery-section">
        <div class="form-section-header open" data-section="delivery">Delivery Details <i class="fa-solid fa-chevron-down chevron"></i></div>
        <div class="form-section-body open" id="sec-delivery">
          <div class="field"><label>Delivery Address <span style="color:var(--ui-text-muted);font-weight:400">(if different from Bill To)</span></label><textarea id="f-delivery-address" rows="3" placeholder="Leave blank to use customer address"></textarea></div>
          <div class="field-row col2">
            <div class="field"><label>Carrier / Shipping Method</label><input id="f-carrier" type="text" placeholder="FedEx, DHL, Hand delivery…"></div>
            <div class="field"><label>Estimated Delivery</label><input id="f-estimated-delivery" type="date"></div>
          </div>
          <div class="field"><label>Received By <span style="color:var(--ui-text-muted);font-weight:400">(leave for recipient to sign)</span></label><input id="f-received-by" type="text" placeholder="Recipient name"></div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="form-section">
        <div class="form-section-header open" data-section="items">Line Items <i class="fa-solid fa-chevron-down chevron"></i></div>
        <div class="form-section-body open" id="sec-items">
          <table class="items-form-table">
            <thead>
              <tr>
                <th>Name / Description</th>
                <th class="th-ref">Ref.</th>
                <th class="th-price tpl-price-col">Unit Price</th>
                <th class="th-qty">Qty</th>
                <th class="th-del"></th>
              </tr>
            </thead>
            <tbody id="items-tbody"></tbody>
          </table>
          <button class="btn btn-primary btn-sm btn-add-row" id="btn-add-item"><i class="fa-solid fa-plus"></i> Add line</button>
          <button class="btn btn-secondary btn-sm btn-add-row" id="btn-pick-item" style="margin-left:6px" title="Add from saved items"><i class="fa-solid fa-boxes-stacked"></i> Pick item</button>
        </div>
      </div>

      <!-- Totals & VAT -->
      <div class="form-section">
        <div class="form-section-header" data-section="totals">Totals &amp; VAT <i class="fa-solid fa-chevron-down chevron"></i></div>
        <div class="form-section-body" id="sec-totals">
          <div class="field-row col2" id="f-vat-rate-wrap">
            <div class="field"><label>VAT Rate (%)</label><input id="f-vat-rate" type="number" min="0" max="100" step="0.1" value="0"></div>
            <div class="field"><label>Balance Label</label><input id="f-balance-label" type="text" placeholder="TOTAL DUE"></div>
          </div>
          <label class="field-check"><input type="checkbox" id="f-show-paid"> Show "Amount Paid" row</label>
          <div class="field"><label>Amount Paid</label><input id="f-amount-paid" type="number" min="0" step="0.01" value="0"></div>
        </div>
      </div>

      <!-- Notes & Terms -->
      <div class="form-section">
        <div class="form-section-header" data-section="text">Notes &amp; Terms <i class="fa-solid fa-chevron-down chevron"></i></div>
        <div class="form-section-body" id="sec-text">
          <div class="field" id="f-vat-mention-wrap"><label>VAT Mention</label><textarea id="f-vat-mention" rows="2"></textarea></div>
          <div class="field"><label>Notes</label><textarea id="f-notes" rows="3"></textarea></div>
          <div class="field"><label>Terms &amp; Conditions</label><textarea id="f-terms" rows="4"></textarea></div>
          <div class="field"><label>Footer — Thanks</label><input id="f-footer-thanks" type="text"></div>
          <div class="field"><label>Footer — Contact</label><textarea id="f-footer-contact" rows="2"></textarea></div>
        </div>
      </div>

      <!-- Bank Details -->
      <div class="form-section form-section-bank">
        <div class="form-section-header" data-section="bank">Bank Details <i class="fa-solid fa-chevron-down chevron"></i></div>
        <div class="form-section-body" id="sec-bank">
          <div class="field"><label>Section Label</label><input id="f-bank-label" type="text"></div>
          <div class="field"><label>Beneficiary</label><input id="f-bank-bene" type="text"></div>
          <div class="field"><label>Bank Name</label><input id="f-bank-name" type="text"></div>
          <div class="field"><label>Bank Address</label><input id="f-bank-addr" type="text"></div>
          <div class="field"><label>IBAN</label><input id="f-bank-iban" type="text"></div>
          <div class="field"><label>BIC / SWIFT</label><input id="f-bank-bic" type="text"></div>
        </div>
      </div>

      <!-- Issuer -->
      <div class="form-section">
        <div class="form-section-header" data-section="issuer">Issuer <i class="fa-solid fa-chevron-down chevron"></i></div>
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
    <div class="cust-modal-search"><input type="text" id="item-search" placeholder="Search items…" oninput="searchItems(this.value)" autocomplete="off"></div>
    <div class="cust-modal-list" id="item-modal-list"><div class="cust-empty">Loading…</div></div>
  </div>
</div>

<!-- Paste JSON modal -->
<div id="paste-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:3000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:10px;padding:28px;max-width:580px;width:95%;box-shadow:0 12px 40px rgba(0,0,0,.2)">
    <h3 style="margin:0 0 12px;font-size:16px"><i class="fa-solid fa-paste"></i> Paste JSON</h3>
    <p style="font-size:13px;color:var(--ui-text-soft);margin-bottom:12px">Paste a JSON document (plain or from an AI chat — fences and surrounding text are handled automatically).</p>
    <textarea id="paste-json" rows="12" style="width:100%;box-sizing:border-box;border:1px solid var(--ui-border);border-radius:5px;padding:10px;font-family:var(--font-mono);font-size:12px;resize:vertical" placeholder='{ "type": "INVOICE", "number": "INV-...", ... }'></textarea>
    <p id="paste-error" style="color:var(--ui-danger);font-size:12px;margin:6px 0 0;min-height:16px"></p>
    <div style="display:flex;gap:10px;margin-top:14px;justify-content:flex-end">
      <button class="btn btn-ghost btn-sm" onclick="closePasteModal()" style="background:#eee;border:none">Cancel</button>
      <button class="btn btn-primary btn-sm" id="btn-paste-load"><i class="fa-solid fa-file-import"></i> Load into editor</button>
    </div>
  </div>
</div>

<div class="toast-container" id="toast-container"></div>
