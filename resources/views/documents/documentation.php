<?php
/** User guide — rendered inside layouts/app.php at /documentation */
?>
<style>
html, body { height: auto; overflow: auto; background: #f0f2f5; }

.doc-wrap { display: flex; max-width: 1100px; margin: 0 auto; padding: 32px 24px 100px; gap: 36px; align-items: flex-start; }

/* Sidebar */
.doc-nav { width: 200px; flex-shrink: 0; position: sticky; top: 24px; max-height: calc(100vh - 48px); overflow-y: auto; }
.doc-nav-label { font-size: 10.5px; font-weight: 700; letter-spacing: .9px; text-transform: uppercase; color: var(--ui-text-muted); padding: 0 8px; margin-bottom: 10px; }
.doc-nav a { display: block; padding: 5px 10px; font-size: 13px; color: var(--ui-text-soft); text-decoration: none; border-left: 2px solid transparent; border-radius: 0 4px 4px 0; line-height: 1.4; transition: all .13s; margin-bottom: 1px; }
.doc-nav a:hover { color: var(--ui-primary); background: rgba(55,113,200,.07); border-left-color: var(--ui-primary); }
.doc-nav a.sub { padding-left: 22px; font-size: 12px; color: var(--ui-text-muted); }
.doc-nav hr { border: none; border-top: 1px solid var(--ui-border); margin: 8px 0; }

.doc-body { flex: 1; min-width: 0; }

/* Hero + quick links */
.doc-hero { background: linear-gradient(135deg, var(--ui-primary) 0%, #2254a8 100%); border-radius: 10px; padding: 32px 36px 30px; color: white; margin-bottom: 28px; }
.doc-hero h1 { font-size: 24px; font-weight: 800; margin: 0 0 8px; letter-spacing: -.3px; }
.doc-hero p  { font-size: 14px; opacity: .88; line-height: 1.65; margin: 0; max-width: 560px; }
.quick-links { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 28px; }
.ql-card { background: white; border-radius: 8px; padding: 16px; text-decoration: none; border: 1px solid var(--ui-border); transition: box-shadow .15s, border-color .15s; }
.ql-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.09); border-color: var(--ui-primary); }
.ql-card .ql-icon { font-size: 22px; margin-bottom: 6px; color: var(--ui-primary); }
.ql-card .ql-title { font-size: 13px; font-weight: 700; color: var(--ui-text); margin-bottom: 3px; }
.ql-card .ql-desc { font-size: 11.5px; color: var(--ui-text-muted); line-height: 1.4; }

/* Sections */
.doc-section { background: white; border-radius: 8px; border: 1px solid var(--ui-border); padding: 26px 30px; margin-bottom: 18px; scroll-margin-top: 20px; }
.doc-section h2 { font-size: 16px; font-weight: 700; color: var(--ui-text); margin: 0 0 18px; padding-bottom: 12px; border-bottom: 1px solid var(--ui-border); display: flex; align-items: center; gap: 9px; }
.doc-section h2 .sec-icon { font-size: 17px; color: var(--ui-primary); }
.doc-section h3 { font-size: 13.5px; font-weight: 700; color: var(--ui-text); margin: 24px 0 8px; scroll-margin-top: 20px; }
.doc-section h3:first-of-type { margin-top: 0; }
.doc-section p { font-size: 13.5px; line-height: 1.7; color: var(--ui-text-soft); margin: 0 0 10px; }
.doc-section p:last-child { margin-bottom: 0; }
.doc-section ul { margin: 8px 0 12px 18px; padding: 0; }
.doc-section ul li { font-size: 13.5px; color: var(--ui-text-soft); line-height: 1.7; margin-bottom: 3px; }
.doc-section a { color: var(--ui-primary); }

.steps { list-style: none; margin: 0; padding: 0; counter-reset: step; }
.steps li { counter-increment: step; display: flex; gap: 13px; align-items: flex-start; padding: 11px 0; border-bottom: 1px solid var(--ui-border); font-size: 13.5px; color: var(--ui-text-soft); line-height: 1.65; }
.steps li:last-child { border-bottom: none; padding-bottom: 0; }
.steps li::before { content: counter(step); display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; min-width: 22px; background: var(--ui-primary); color: white; border-radius: 50%; font-size: 11px; font-weight: 700; margin-top: 2px; }
.steps li > div { min-width: 0; }
.steps li strong { color: var(--ui-text); }

.ref-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 10px 0; }
.ref-table th { background: var(--ui-input-bg); text-align: left; padding: 7px 12px; font-weight: 600; font-size: 11.5px; color: var(--ui-text-soft); border: 1px solid var(--ui-border); }
.ref-table td { padding: 8px 12px; border: 1px solid var(--ui-border); vertical-align: top; color: var(--ui-text-soft); line-height: 1.5; }
.ref-table td:first-child { font-weight: 600; color: var(--ui-text); white-space: nowrap; }

.doc-section .tip, .doc-section .warn, .doc-section .note { border-radius: 0 6px 6px 0; padding: 11px 15px; font-size: 13px; line-height: 1.65; color: var(--ui-text-soft); margin: 14px 0; }
.doc-section .tip  { background: #eef4ff; border-left: 3px solid var(--ui-primary); }
.doc-section .warn { background: #fff8ec; border-left: 3px solid var(--ui-warning); }
.doc-section .note { background: #f3f4f6; border-left: 3px solid var(--ui-border); }
.doc-section .tip strong  { color: var(--ui-primary); }
.doc-section .warn strong { color: var(--ui-warning); }
.doc-section .note strong { color: var(--ui-text); }

.doc-section pre { background: #1e2230; color: #c9d1e0; border-radius: 7px; padding: 16px 18px; font-size: 12px; line-height: 1.7; overflow-x: auto; margin: 12px 0; font-family: var(--font-mono); }
.doc-section code { background: var(--ui-input-bg); border: 1px solid var(--ui-border); border-radius: 3px; padding: 1px 5px; font-size: 12px; font-family: var(--font-mono); color: var(--ui-text); }
.doc-section pre code { background: none; border: none; padding: 0; color: inherit; font-size: inherit; }
.doc-section kbd { background: #edf0f5; border: 1px solid #c8cdd8; border-bottom-width: 2px; border-radius: 4px; padding: 1px 6px; font-size: 12px; font-family: var(--font-mono); color: var(--ui-text); }

.file-tree { font-size: 13px; font-family: var(--font-mono); line-height: 1.95; overflow-x: auto; }
.file-tree .dir  { color: var(--ui-text); font-weight: 600; }
.file-tree .file { color: var(--ui-text-soft); }
.file-tree .cm   { color: var(--ui-text-muted); font-style: italic; font-family: var(--font); font-size: 12px; }
.file-tree div { white-space: pre; }

.act { display: inline-flex; align-items: center; gap: 4px; background: #eee; border-radius: 4px; padding: 1px 8px; font-size: 12px; font-weight: 600; color: var(--ui-text); white-space: nowrap; }
.act.primary { background: var(--ui-primary); color: white; }
.act.danger  { background: var(--ui-danger);  color: white; }

@media (max-width: 860px) {
  .doc-wrap { flex-direction: column; gap: 18px; }
  .doc-nav { position: static; width: 100%; max-height: none; }
  .quick-links { grid-template-columns: 1fr 1fr; }
  .doc-section { padding: 20px 18px; }
}
</style>

<div class="doc-wrap">

  <nav class="doc-nav" aria-label="Contents">
    <div class="doc-nav-label">Contents</div>
    <a href="#overview">Overview</a>
    <a href="#getting-started">Getting started</a>
    <a href="#signin">Signing in</a>
    <a href="#signin-devices" class="sub">Staying signed in</a>
    <a href="#signin-profile" class="sub">Your profile</a>
    <hr>
    <a href="#documents">Documents page</a>
    <a href="#doc-filters" class="sub">Search &amp; filters</a>
    <a href="#doc-actions" class="sub">Row actions</a>
    <hr>
    <a href="#editor">The editor</a>
    <a href="#doc-header" class="sub">Document header</a>
    <a href="#bill-to" class="sub">Bill To</a>
    <a href="#line-items" class="sub">Line items</a>
    <a href="#totals" class="sub">Totals &amp; VAT</a>
    <a href="#notes-terms" class="sub">Notes &amp; terms</a>
    <a href="#bank" class="sub">Bank &amp; issuer</a>
    <a href="#saving" class="sub">Saving &amp; printing</a>
    <hr>
    <a href="#customers">Customers</a>
    <a href="#items">Items catalogue</a>
    <hr>
    <a href="#json">JSON &amp; AI</a>
    <a href="#json-doc" class="sub">Document JSON</a>
    <a href="#json-ai" class="sub">AI workflow</a>
    <a href="#json-bulk" class="sub">Backup &amp; migration</a>
    <hr>
    <a href="#admin">Setup &amp; configuration</a>
    <a href="#security" class="sub">Security</a>
    <a href="#files" class="sub">File structure</a>
    <a href="#troubleshooting">Troubleshooting</a>
  </nav>

  <div class="doc-body">

    <div class="doc-hero">
      <h1><i class="fa-regular fa-file-lines"></i> DocEditor — User Guide</h1>
      <p>Create invoices, quotes and any other document with a live A4 preview, keep your customers and products in catalogues, and use JSON to back up your data or let an AI assistant fill documents for you.</p>
    </div>

    <div class="quick-links">
      <a class="ql-card" href="#editor"><div class="ql-icon"><i class="fa-solid fa-pen-to-square"></i></div><div class="ql-title">Create a document</div><div class="ql-desc">Fill the form, watch the preview, save and print</div></a>
      <a class="ql-card" href="#customers"><div class="ql-icon"><i class="fa-solid fa-users"></i></div><div class="ql-title">Customers &amp; items</div><div class="ql-desc">Pick them instead of retyping</div></a>
      <a class="ql-card" href="#json"><div class="ql-icon"><i class="fa-solid fa-code"></i></div><div class="ql-title">JSON &amp; AI</div><div class="ql-desc">Export a template, let AI fill it, import it back</div></a>
      <a class="ql-card" href="#signin"><div class="ql-icon"><i class="fa-solid fa-shield-halved"></i></div><div class="ql-title">Signing in</div><div class="ql-desc">Staying signed in, devices, profile</div></a>
    </div>

    <!-- Overview -->
    <div class="doc-section" id="overview">
      <h2><span class="sec-icon"><i class="fa-solid fa-map"></i></span> Overview</h2>
      <p>DocEditor is a self-hosted PHP application with a MySQL database. Documents are stored as JSON, so adding a field never needs a database change. Everything sits behind a login.</p>
      <table class="ref-table">
        <thead><tr><th>Page</th><th>Address</th><th>What it does</th></tr></thead>
        <tbody>
          <tr><td>Documents</td><td><code>/documents</code></td><td>History of everything you created: search, filter by type, status and date, duplicate, delete, export and import.</td></tr>
          <tr><td>Editor</td><td><code>/editor</code></td><td>Form on the left, live A4 preview on the right. Auto-saves.</td></tr>
          <tr><td>Customers</td><td><code>/customers</code></td><td>Address book. Add, edit, delete, export and import.</td></tr>
          <tr><td>Items</td><td><code>/items</code></td><td>Catalogue of products and services (reference, title, unit, price, description).</td></tr>
          <tr><td>Profile</td><td><code>/profile</code></td><td>Your name, email, password and the devices that stay signed in.</td></tr>
          <tr><td>Docs</td><td><code>/documentation</code></td><td>This guide (link at the top right of every page).</td></tr>
        </tbody>
      </table>
      <div class="note"><strong>Shared data:</strong> every user who can sign in sees the same documents, customers and items — accounts are not separate workspaces.</div>
    </div>

    <!-- Getting started -->
    <div class="doc-section" id="getting-started">
      <h2><span class="sec-icon"><i class="fa-solid fa-rocket"></i></span> Getting started</h2>
      <p>Already running? Sign in and click <span class="act primary">+ Invoice</span> or <span class="act">+ Quote</span> on the Documents page. First installation:</p>
      <ol class="steps">
        <li><div><strong>Requirements:</strong> PHP 8.1+ with <code>pdo_mysql</code>, and a MySQL / MariaDB database.</div></li>
        <li><div><strong>Configure:</strong> copy <code>.env.example</code> to <code>.env</code> and fill in the database name, user and password (see <a href="#admin">Setup &amp; configuration</a>).</div></li>
        <li><div><strong>Create the tables:</strong> <code>php database/migrate.php</code> (or import the files of <code>database/migrations/</code> in phpMyAdmin).</div></li>
        <li><div><strong>Create your user:</strong> <code>php database/create_user.php</code>. There is no registration page.</div></li>
        <li><div><strong>Start:</strong> <code>php -S localhost:8000</code> from the project folder and open <code>http://localhost:8000</code>, or put the folder in Apache's <code>htdocs</code> and open <code>http://localhost/DocEditor/</code>.</div></li>
      </ol>
    </div>

    <!-- Signing in -->
    <div class="doc-section" id="signin">
      <h2><span class="sec-icon"><i class="fa-solid fa-shield-halved"></i></span> Signing in</h2>
      <p>Sign in with your email and password. Every other page needs it. Five wrong passwords for the same email from the same address (or twenty from one address) lock sign-in for about 15 minutes.</p>

      <h3 id="signin-devices">Staying signed in</h3>
      <p>The sign-in page has <strong>Keep me signed in on this device</strong> (ticked by default). A remembered browser is signed back in silently when its session ends — browser restarted, two hours idle, laptop asleep — so you do not have to type your password again. A small background check every few minutes (and when you come back to the tab) keeps it fresh and replaces the browser's secret key regularly.</p>
      <ul>
        <li>A device stays signed in for 180 days after its <em>last use</em>, so as long as you use the app it never expires.</li>
        <li>A copied cookie used from another browser or operating system is rejected, and that device is signed out.</li>
        <li>On a shared or public computer, untick the box: you are then signed out when you close the browser or after two hours without activity.</li>
        <li><span class="act"><i class="fa-solid fa-right-from-bracket"></i> Log out</span> (top right) signs out this browser only.</li>
      </ul>

      <h3 id="signin-profile">Your profile</h3>
      <p>Click your name at the top right.</p>
      <ul>
        <li><strong>Account details:</strong> change your name or sign-in email (the email change asks for your current password).</li>
        <li><strong>Change password:</strong> 10 to 72 characters. It signs out <em>every other</em> device.</li>
        <li><strong>Devices that stay signed in:</strong> browser, system, last IP address and last activity of each one, with a <span class="act danger">Sign out</span> button, and <span class="act">Sign out all other devices</span>. If you see a device you do not recognise, sign it out and change your password.</li>
      </ul>
      <div class="note"><strong>Forgotten password:</strong> there is no email reset. Run <code>php database/create_user.php</code> with your email address on the server — it offers to reset the password.</div>
    </div>

    <!-- Documents page -->
    <div class="doc-section" id="documents">
      <h2><span class="sec-icon"><i class="fa-solid fa-list"></i></span> Documents page</h2>
      <p>Your history, sorted by document date, newest first. Dates are shown as <code>dd/mm/yyyy</code>.</p>

      <h3 id="doc-filters">Search &amp; filters</h3>
      <table class="ref-table">
        <thead><tr><th>Filter</th><th>How it works</th></tr></thead>
        <tbody>
          <tr><td>Search</td><td>Matches the document number or the client name while you type.</td></tr>
          <tr><td>Type / Status</td><td>Invoice, Quote, Credit Note, Other — and Draft, Final, Paid.</td></tr>
          <tr><td>Date from / to</td><td>Type the dates as <code>dd/mm/yyyy</code> (the slashes are added for you). Both ends are included. A date that does not exist, like 31/02/2026, turns red and is ignored.</td></tr>
          <tr><td>This month · Last month · This year</td><td>Fill the date range in one click.</td></tr>
          <tr><td>✕ Clear</td><td>Resets every filter.</td></tr>
        </tbody>
      </table>
      <p>The counter on the right shows how many documents match. Your filters are remembered while you move around the app.</p>

      <h3 id="doc-actions">Row actions</h3>
      <table class="ref-table">
        <thead><tr><th>Button</th><th>What it does</th></tr></thead>
        <tbody>
          <tr><td><span class="act">Edit</span></td><td>Opens the document in the editor.</td></tr>
          <tr><td><span class="act">Print</span></td><td>Opens the clean A4 view in a new tab. Use <kbd>Ctrl</kbd>+<kbd>P</kbd> → <em>Save as PDF</em>.</td></tr>
          <tr><td><span class="act">Copy</span></td><td>Duplicates the document as a draft; its number gets <em>-COPY</em> appended.</td></tr>
          <tr><td><span class="act danger">Del</span></td><td>Asks for confirmation, then deletes it permanently.</td></tr>
          <tr><td><span class="act">⬇ Export</span> <span class="act">⬆ Import</span></td><td>Back up or move every document as one JSON file — see <a href="#json-bulk">Backup &amp; migration</a>.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Editor -->
    <div class="doc-section" id="editor">
      <h2><span class="sec-icon"><i class="fa-solid fa-pen-to-square"></i></span> The editor</h2>
      <p>The <strong>form</strong> (left) has collapsible sections; the <strong>preview</strong> (right) is an A4 page that updates as you type. The number and type of the document are shown at the top.</p>

      <h3 id="doc-header">Document header</h3>
      <table class="ref-table">
        <thead><tr><th>Field</th><th>Notes</th></tr></thead>
        <tbody>
          <tr><td>Type</td><td>Invoice, Quote, Credit Note, Other, or <em>Custom…</em> (type your own title, e.g. <code>DELIVERY NOTE</code>, <code>PROFORMA</code>). It sets the big heading on the document.</td></tr>
          <tr><td>Number</td><td>Free text. Suggested format <code>INV-20261006-1</code> (prefix, date, counter). Prefixes: INV, QUO, CRN, DOC, PRO, DLV.</td></tr>
          <tr><td>Date · Due Date</td><td>The document date, and an optional due date row.</td></tr>
          <tr><td>Service Date — Start / End</td><td>Optional period of the service or delivery; shown on the document when filled in.</td></tr>
          <tr><td>PO Number</td><td>Optional customer purchase order number.</td></tr>
          <tr><td>Quote Ref. · Tracking #</td><td>Optional: related quote, shipment tracking number.</td></tr>
          <tr><td>Currency · Symbol</td><td>EUR, USD, GBP, CHF; the symbol can be overridden.</td></tr>
        </tbody>
      </table>
      <p>Empty optional fields are simply not printed.</p>

      <h3 id="bill-to">Bill To</h3>
      <p>Company, address, city/country, contact, phone and VAT number. Click <span class="act"><i class="fa-solid fa-users"></i> Pick customer</span> to search your <a href="#customers">address book</a> (search by name, city or contact, click a row to fill everything). <kbd>Esc</kbd> closes the picker.</p>

      <h3 id="line-items">Line items</h3>
      <p><span class="act primary">+ Add line</span> adds an empty row; <span class="act"><i class="fa-solid fa-boxes-stacked"></i> Pick item</span> adds one from your <a href="#items">items catalogue</a> with name, reference, description and price already filled (quantity 1).</p>
      <table class="ref-table">
        <thead><tr><th>Field</th><th>Notes</th></tr></thead>
        <tbody>
          <tr><td>Name · Description</td><td>Bold main label and an optional smaller line below it.</td></tr>
          <tr><td>Ref.</td><td>Your catalogue reference, e.g. <code>K0709-04-2</code>.</td></tr>
          <tr><td>Unit price · Qty</td><td>Amount = price × quantity, excluding VAT.</td></tr>
          <tr><td>Free ("offert")</td><td>Prints <em>offert</em> instead of a price and is left out of the subtotal.</td></tr>
        </tbody>
      </table>

      <h3 id="totals">Totals &amp; VAT</h3>
      <table class="ref-table">
        <thead><tr><th>Field</th><th>Notes</th></tr></thead>
        <tbody>
          <tr><td>VAT rate (%)</td><td>Applied to the subtotal. 0 for export / VAT-exempt.</td></tr>
          <tr><td>Balance label</td><td>Label of the last line, e.g. <code>TOTAL DUE</code>.</td></tr>
          <tr><td>Show "Amount Paid"</td><td>Adds a paid row; balance = total − amount paid.</td></tr>
        </tbody>
      </table>

      <h3 id="notes-terms">Notes &amp; terms</h3>
      <p><strong>VAT mention</strong> (italic line under the totals), <strong>Notes</strong>, <strong>Terms &amp; Conditions</strong> (below the bank details) and the two footer texts (thank-you line, contact line).</p>

      <h3 id="bank">Bank details &amp; issuer</h3>
      <p><strong>Bank Details:</strong> section label, beneficiary, bank name and address, IBAN, BIC/SWIFT — edit them per document. <strong>Issuer:</strong> your company name, address, email and legal information. The defaults for new documents come from <code>defaultTemplate()</code> in <code>app/Services/DocumentService.php</code>; the logo is <code>assets/logo.png</code> (replace the file to change it everywhere).</p>

      <h3 id="saving">Saving &amp; printing</h3>
      <ol class="steps">
        <li><div><strong>Auto-save</strong> runs 8 seconds after your last change. The dot at the bottom of the form shows the state: orange = saving, green = saved, red = error.</div></li>
        <li><div><span class="act primary">Save</span> saves immediately.</div></li>
        <li><div><span class="act"><i class="fa-solid fa-print"></i> Print</span> saves and opens the clean A4 view. In the print dialog set margins to <em>None</em>, tick <em>Background graphics</em>, and choose <em>Save as PDF</em>.</div></li>
      </ol>
      <div class="tip"><strong>Tip:</strong> saving a customer name also adds or updates that customer in the address book automatically.</div>
    </div>

    <!-- Customers -->
    <div class="doc-section" id="customers">
      <h2><span class="sec-icon"><i class="fa-solid fa-users"></i></span> Customers</h2>
      <p>Name, address, city/country, contact, phone, VAT number. Customers are created automatically from every document you save, and you can manage them on <a href="<?= BASE_URL ?>/customers">/customers</a>.</p>
      <ul>
        <li>Search by name, city or contact; <span class="act primary">+ New Customer</span>, <span class="act">Edit</span>, <span class="act danger">Delete</span> (existing documents keep their own copy of the address).</li>
        <li><span class="act">⬇ Export</span> downloads a clean JSON array. <span class="act">⬆ Import</span> reads it back and <strong>skips names that already exist</strong>, so importing twice never duplicates.</li>
      </ul>
      <div class="warn"><strong>Note:</strong> saving a document updates the address-book entry of that name with the document's values. Older documents are not changed.</div>
    </div>

    <!-- Items -->
    <div class="doc-section" id="items">
      <h2><span class="sec-icon"><i class="fa-solid fa-boxes-stacked"></i></span> Items catalogue</h2>
      <p>Your products and services on <a href="<?= BASE_URL ?>/items">/items</a>: <strong>reference</strong> and <strong>title</strong> (required), <strong>unit / size</strong>, <strong>price</strong> and <strong>description</strong>. Use <span class="act"><i class="fa-solid fa-boxes-stacked"></i> Pick item</span> in the editor to add them to a document.</p>
      <ul>
        <li>Search by reference, title or description.</li>
        <li><span class="act">⬇ Export</span> / <span class="act">⬆ Import</span> use a JSON array. Import <strong>skips references that already exist</strong>.</li>
      </ul>
      <pre><code>[
  { "reference": "K0709-04-2", "title": "PRECICE® PRPP-S Assay Kit",
    "unit": "10 mL of reaction mixture", "price": "515.00", "description": "" }
]</code></pre>
    </div>

    <!-- JSON -->
    <div class="doc-section" id="json">
      <h2><span class="sec-icon"><i class="fa-solid fa-code"></i></span> JSON &amp; AI</h2>
      <p>Every document is JSON, which makes it easy to back up, move between installations or have an AI assistant (Claude, ChatGPT, Copilot…) write it for you.</p>

      <h3 id="json-doc">Document JSON — editor buttons</h3>
      <table class="ref-table">
        <thead><tr><th>Button</th><th>What it does</th></tr></thead>
        <tbody>
          <tr><td><span class="act"><i class="fa-solid fa-cube"></i> Model JSON</span></td><td>Downloads an annotated template that explains every field — give it to an AI assistant.</td></tr>
          <tr><td><span class="act"><i class="fa-solid fa-download"></i> Export JSON</span></td><td>Downloads the document you are editing, even when fields are empty.</td></tr>
          <tr><td><span class="act"><i class="fa-solid fa-upload"></i> Import JSON</span></td><td>Loads a <code>.json</code> file into the form. Review, then <span class="act primary">Save</span>.</td></tr>
          <tr><td><span class="act"><i class="fa-solid fa-paste"></i> Paste JSON</span></td><td>Same as Import, but you paste the text the AI gave you (text around the JSON and <code>```json</code> fences are ignored).</td></tr>
        </tbody>
      </table>
      <pre><code>{
  "type": "INVOICE", "number": "INV-20261006-1", "date": "2026-10-06",
  "due_date": "", "service_date_start": "", "service_date_end": "",
  "po_number": "", "quote_ref": "", "tracking": "",
  "currency": "EUR", "currency_symbol": "€",
  "issuer":   { "name": "", "address": "", "city": "", "email": "", "legal": "" },
  "customer": { "name": "", "address": "", "city": "", "contact": "", "phone": "", "vat": "" },
  "items": [
    { "name": "HPLC-UV Analysis", "description": "", "reference": "S1200-03-NA",
      "unit_price": 300, "qty": 2, "is_free": false }
  ],
  "vat_rate": 0, "amount_paid": 0, "show_amount_paid": false, "balance_label": "TOTAL DUE",
  "vat_mention": "", "notes": "", "terms": "",
  "bank": { "label": "", "beneficiary": "", "bank_name": "", "bank_address": "", "iban": "", "bic": "" },
  "footer_thanks": "", "footer_contact": ""
}</code></pre>

      <h3 id="json-ai">AI workflow</h3>
      <ol class="steps">
        <li><div>In the editor click <span class="act"><i class="fa-solid fa-cube"></i> Model JSON</span> and attach the file to your AI assistant.</div></li>
        <li><div>Describe the document: <em>"Invoice for Pureture: 2 × HPLC-UV Analysis at €300, no VAT, due on receipt."</em></div></li>
        <li><div>Copy the JSON it returns and click <span class="act"><i class="fa-solid fa-paste"></i> Paste JSON</span> (or save it as a file and use Import JSON).</div></li>
        <li><div>Check the preview, <span class="act primary">Save</span>, <span class="act"><i class="fa-solid fa-print"></i> Print</span>.</div></li>
      </ol>

      <h3 id="json-bulk">Backup &amp; migration of all documents</h3>
      <p>On the Documents page <span class="act">⬇ Export</span> downloads <em>every</em> document in one file (<code>doceditor-documents-YYYY-MM-DD.json</code>) with its status and creation / update dates. <span class="act">⬆ Import</span> reads it, one batch at a time.</p>
      <ul>
        <li>Documents whose <strong>type and number already exist are skipped</strong>, so you can import the same file again safely.</li>
        <li>Customers found in the imported documents are added to the address book (existing ones are not overwritten).</li>
        <li>Files from the previous (SQLite) version work too: its single <code>service_date</code> becomes <em>Service Date — Start</em>.</li>
        <li>Import also accepts a bare list of documents or a single document.</li>
      </ul>
      <div class="tip"><strong>Backup routine:</strong> export documents, customers and items from time to time. For a full backup also dump the MySQL database (phpMyAdmin → Export).</div>
    </div>

    <!-- Admin -->
    <div class="doc-section" id="admin">
      <h2><span class="sec-icon"><i class="fa-solid fa-gear"></i></span> Setup &amp; configuration</h2>
      <p>Settings live in the <code>.env</code> file at the project root (never upload it to a public place; the <code>.htaccess</code> blocks it from the web).</p>
      <table class="ref-table">
        <thead><tr><th>Variable</th><th>Meaning</th></tr></thead>
        <tbody>
          <tr><td>APP_ENV / APP_DEBUG</td><td>On a public server use <code>production</code> and <code>false</code> — debug mode prints error details in the browser.</td></tr>
          <tr><td>DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD</td><td>MySQL connection.</td></tr>
          <tr><td>SESSION_LIFETIME</td><td>Minutes without activity before a plain session ends (default 120). Remembered devices restore it silently.</td></tr>
          <tr><td>REMEMBER_DAYS</td><td>Days a remembered device stays valid after its last use (default 180).</td></tr>
          <tr><td>REMEMBER_MAX_DAYS</td><td>Absolute lifetime in days, however often it is used (0 = none).</td></tr>
          <tr><td>REMEMBER_ROTATE_MINUTES</td><td>The device key is replaced at least this often while the app is open (default 60).</td></tr>
          <tr><td>REMEMBER_BIND_IP</td><td><code>true</code> requires the same network (IPv4 /24, IPv6 /48) — stricter, but people moving between Wi-Fi and 4G must sign in again.</td></tr>
          <tr><td>TRUSTED_PROXY</td><td><code>true</code> only behind a reverse proxy / CDN such as Cloudflare, so the real visitor IP is used.</td></tr>
        </tbody>
      </table>
      <p><strong>Commands</strong> (run in the project folder):</p>
      <ul>
        <li><code>php database/migrate.php</code> — creates / updates the tables.</li>
        <li><code>php database/create_user.php</code> — adds a user or resets a password (<code>--sql</code> prints an INSERT to paste into phpMyAdmin).</li>
      </ul>

      <h3 id="security">Security in short</h3>
      <ul>
        <li>Passwords are hashed (bcrypt). Sessions are HttpOnly, SameSite=Lax and Secure on HTTPS, and regenerated on sign-in.</li>
        <li>Every form and every background request carries a CSRF token; after a long pause the page renews it by itself.</li>
        <li>Sign-in attempts are rate limited; the "next page" redirect after login only goes inside the app.</li>
        <li>Remembered devices use a rotating secret, stored only as a hash, with theft detection and a browser/OS check (see <a href="#signin-devices">Staying signed in</a>).</li>
        <li>On a public server turn on <strong>HTTPS</strong> and check that <code>/.env</code> and <code>/app/Core/Env.php</code> answer 403/404 in the browser.</li>
      </ul>

      <h3 id="files">File structure</h3>
      <div class="file-tree">
<div><span class="dir">project/</span></div>
<div>├── <span class="file">index.php</span>  <span class="cm">— entry point (so you don't need /public)</span></div>
<div>├── <span class="file">.env</span>  <span class="cm">— your settings (secret)</span></div>
<div>├── <span class="dir">app/</span></div>
<div>│   ├── <span class="dir">Core/</span>  <span class="cm">— router, request/response, session, auth, CSRF, database</span></div>
<div>│   ├── <span class="dir">Controllers/</span> <span class="cm">(+ Api/)</span>  <span class="cm">— receive requests, return pages or JSON</span></div>
<div>│   ├── <span class="dir">Services/</span>  <span class="cm">— business rules (documents, numbering, auth, remembered devices…)</span></div>
<div>│   ├── <span class="dir">Repositories/</span>  <span class="cm">— all SQL lives here</span></div>
<div>│   └── <span class="dir">Models/</span>  <span class="cm">— plain data objects</span></div>
<div>├── <span class="dir">resources/views/</span>  <span class="cm">— pages, layouts, partials (editor, print template)</span></div>
<div>├── <span class="dir">routes/web.php</span>  <span class="cm">— every URL of the app</span></div>
<div>├── <span class="dir">assets/</span>  <span class="cm">— editor.css, document.css, logo.png</span></div>
<div>├── <span class="dir">database/</span>  <span class="cm">— migrations, migrate.php, create_user.php</span></div>
<div>└── <span class="dir">storage/</span>  <span class="cm">— PHP sessions (protected from the web)</span></div>
      </div>
    </div>

    <!-- Troubleshooting -->
    <div class="doc-section" id="troubleshooting">
      <h2><span class="sec-icon"><i class="fa-solid fa-life-ring"></i></span> Troubleshooting</h2>
      <table class="ref-table">
        <thead><tr><th>Symptom</th><th>What to do</th></tr></thead>
        <tbody>
          <tr><td>“Database connection failed”</td><td>Check the DB_* values in <code>.env</code>, then run <code>php database/migrate.php</code>.</td></tr>
          <tr><td>“Page expired” (419)</td><td>Cookies are blocked, or the session ended. Go back to the sign-in page; a remembered device signs in again by itself.</td></tr>
          <tr><td>Banner “Your session has expired”</td><td>Open the sign-in link in a new tab and sign in; the work in the page is kept, then retry.</td></tr>
          <tr><td>Styles or API 404 under <code>http://localhost/DocEditor/</code></td><td>Make sure the root <code>.htaccess</code> is uploaded and Apache's <code>mod_rewrite</code> is on (<code>AllowOverride All</code>).</td></tr>
          <tr><td>Asked to sign in again on a phone</td><td>Switching to "desktop site" changes the system the browser reports, which a remembered device treats as a different browser.</td></tr>
          <tr><td>Locked out after wrong passwords</td><td>Wait about 15 minutes, or reset the password with <code>php database/create_user.php</code>.</td></tr>
        </tbody>
      </table>
    </div>

  </div><!-- /doc-body -->
</div><!-- /doc-wrap -->
