// ── Full editor JavaScript — API URLs use REST routes via API.* constants ──

const CURRENCY_SYMBOLS = { EUR:'€', USD:'$', GBP:'£', CHF:'CHF ' };

let currentId = EDIT_ID;
let docData   = null;
let isDirty   = false;
let saveTimer = null;

// ── Section collapse ──────────────────────────────────────────
document.querySelectorAll('.form-section-header').forEach(hdr => {
  hdr.addEventListener('click', () => {
    const body   = document.getElementById('sec-' + hdr.dataset.section);
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    body.style.display = isOpen ? 'none' : 'block';
    hdr.classList.toggle('open', !isOpen);
  });
  const body = document.getElementById('sec-' + hdr.dataset.section);
  if (body && !body.classList.contains('open')) body.style.display = 'none';
});

// ── Collect form → JSON ───────────────────────────────────────
function collectDoc() {
  return {
    type: v('f-type'), number: v('f-number'), date: v('f-date'),
    due_date: v('f-due-date'),
    service_date_start: v('f-service-start'), service_date_end: v('f-service-end'),
    po_number: v('f-po'),
    quote_ref: v('f-quote-ref'), tracking: v('f-tracking'),
    currency: v('f-currency'), currency_symbol: v('f-currency-symbol'),
    issuer: {
      name: v('f-issuer-name'), address: v('f-issuer-addr'), city: v('f-issuer-city'),
      email: v('f-issuer-email'), legal: v('f-issuer-legal'), logo_base64: LOGO_DATA_URI,
    },
    customer: {
      name: v('f-cust-name'), address: v('f-cust-addr'), city: v('f-cust-city'),
      contact: v('f-cust-contact'), phone: v('f-cust-phone'), vat: v('f-cust-vat'),
    },
    items: collectItems(),
    vat_rate: parseFloat(v('f-vat-rate')) || 0,
    amount_paid: parseFloat(v('f-amount-paid')) || 0,
    show_amount_paid: document.getElementById('f-show-paid').checked,
    balance_label: v('f-balance-label') || 'TOTAL DUE',
    vat_mention: v('f-vat-mention'), notes: v('f-notes'), terms: v('f-terms'),
    bank: {
      label: v('f-bank-label'), beneficiary: v('f-bank-bene'), bank_name: v('f-bank-name'),
      bank_address: v('f-bank-addr'), iban: v('f-bank-iban'), bic: v('f-bank-bic'),
    },
    footer_thanks: v('f-footer-thanks'), footer_contact: v('f-footer-contact'),
  };
}

function v(id) { const el = document.getElementById(id); return el ? el.value.trim() : ''; }

function collectItems() {
  return Array.from(document.querySelectorAll('#items-tbody tr.item-row')).map(row => ({
    name:        row.querySelector('.item-name').value,
    description: row.querySelector('.item-desc').value,
    reference:   row.querySelector('.item-ref').value,
    unit_price:  parseFloat(row.querySelector('.item-price').value) || 0,
    qty:         parseFloat(row.querySelector('.item-qty').value)   || 0,
    is_free:     row._optRow ? row._optRow.querySelector('.item-free').checked : false,
  }));
}

// ── Add item row ──────────────────────────────────────────────
function addItemRow(item = {}) {
  const tbody = document.getElementById('items-tbody');
  const tr    = document.createElement('tr'); tr.className = 'item-row';
  tr.innerHTML = `
    <td style="min-width:130px">
      <input class="item-name" type="text" placeholder="Product / service name" value="${esc(item.name??'')}">
      <textarea class="item-desc-input item-desc" placeholder="Sub-description">${esc(item.description??'')}</textarea>
    </td>
    <td><input class="item-ref"   type="text"   placeholder="REF"  value="${esc(item.reference??'')}"></td>
    <td><input class="item-price" type="number" min="0" step="0.01" placeholder="0.00" value="${esc(String(item.unit_price??''))}"></td>
    <td><input class="item-qty"   type="number" min="0" step="1"    value="${esc(String(item.qty??1))}"></td>
    <td class="td-del"><button class="btn-del-row" title="Remove">✕</button></td>`;
  const trFree = document.createElement('tr'); trFree.className = 'item-row-opts';
  trFree.innerHTML = `
    <td colspan="4" style="border-top:none;padding:2px 6px 6px">
      <label style="display:flex;align-items:center;gap:6px;font-size:11px;color:#5a6070;cursor:pointer">
        <input class="item-free" type="checkbox" ${item.is_free?'checked':''}> Free / "offert"
      </label>
    </td><td style="border-top:none"></td>`;
  tbody.appendChild(tr); tbody.appendChild(trFree);
  tr._optRow = trFree;
  tr.querySelector('.btn-del-row').addEventListener('click', () => { tr.remove(); trFree.remove(); onFormChange(); });
  tr.querySelectorAll('input, textarea').forEach(el => el.addEventListener('input', onFormChange));
  trFree.querySelector('input').addEventListener('change', onFormChange);
}

wire('btn-add-item', 'click', () => { addItemRow(); onFormChange(); });

// ── Populate form ─────────────────────────────────────────────
// The type <select> only lists the 4 usual types; add any other value (e.g. PROFORMA) on the fly
function setType(t) {
  const sel = document.getElementById('f-type'); if (!sel) return;
  t = String(t || 'INVOICE').trim().toUpperCase();
  if (![...sel.options].some(o => o.value === t)) sel.add(new Option(t, t));
  sel.value = t;
}

function populateForm(d) {
  setType(d.type??'INVOICE'); set('f-number',d.number??''); set('f-date',d.date??'');
  set('f-due-date',d.due_date??'');
  set('f-service-start',d.service_date_start??d.service_date??''); set('f-service-end',d.service_date_end??'');
  set('f-po',d.po_number??'');
  set('f-quote-ref',d.quote_ref??''); set('f-tracking',d.tracking??'');
  set('f-currency',d.currency??'EUR'); set('f-currency-symbol',d.currency_symbol??'€');
  const iss = d.issuer??{};
  set('f-issuer-name',iss.name??''); set('f-issuer-addr',iss.address??'');
  set('f-issuer-city',iss.city??''); set('f-issuer-email',iss.email??'');
  set('f-issuer-legal',iss.legal??'');
  const cu = d.customer??{};
  set('f-cust-name',cu.name??''); set('f-cust-addr',cu.address??'');
  set('f-cust-city',cu.city??''); set('f-cust-contact',cu.contact??'');
  set('f-cust-phone',cu.phone??''); set('f-cust-vat',cu.vat??'');
  set('f-vat-rate',d.vat_rate??0); set('f-amount-paid',d.amount_paid??0);
  set('f-balance-label',d.balance_label??'TOTAL DUE');
  document.getElementById('f-show-paid').checked = !!d.show_amount_paid;
  set('f-vat-mention',d.vat_mention??''); set('f-notes',d.notes??''); set('f-terms',d.terms??'');
  set('f-footer-thanks',d.footer_thanks??''); set('f-footer-contact',d.footer_contact??'');
  const b = d.bank??{};
  set('f-bank-label',b.label??''); set('f-bank-bene',b.beneficiary??'');
  set('f-bank-name',b.bank_name??''); set('f-bank-addr',b.bank_address??'');
  set('f-bank-iban',b.iban??''); set('f-bank-bic',b.bic??'');
  document.getElementById('items-tbody').innerHTML = '';
  (Array.isArray(d.items)?d.items:[]).forEach(addItemRow);
  updateTopBar(d);
}
function set(id,val){const el=document.getElementById(id);if(el)el.value=val;}

// ── Currency auto-fill ────────────────────────────────────────
wire('f-currency', 'change', function() {
  document.getElementById('f-currency-symbol').value = CURRENCY_SYMBOLS[this.value]||this.value;
  onFormChange();
});

// ── Live preview ──────────────────────────────────────────────
// Service period label: "start – end", just "start", or "Until end"
function serviceDateLabel(d) {
  const s = d.service_date_start || d.service_date || '';   // service_date = legacy single field
  const e = d.service_date_end || '';
  if (s && e) return s + ' – ' + e;
  if (s) return s;
  if (e) return 'Until ' + e;
  return '';
}

function renderPreview(d) {
  const sym = esc(d.currency_symbol||'€');
  const type = esc(d.type||'INVOICE');
  let extraRows='';
  const svc = serviceDateLabel(d);
  if(d.quote_ref)    extraRows+=`<tr><td>Quote:</td><td>${esc(d.quote_ref)}</td></tr>`;
  if(d.po_number)    extraRows+=`<tr><td>PO:</td><td>${esc(d.po_number)}</td></tr>`;
  if(d.due_date)     extraRows+=`<tr><td>Due Date:</td><td>${esc(d.due_date)}</td></tr>`;
  if(svc)            extraRows+=`<tr><td>Service Date:</td><td>${esc(svc)}</td></tr>`;
  if(d.tracking)     extraRows+=`<tr><td>Tracking:</td><td>${esc(d.tracking)}</td></tr>`;
  let subtotal=0;
  (d.items||[]).forEach(it=>{if(!it.is_free)subtotal+=(parseFloat(it.unit_price)||0)*(parseFloat(it.qty)||0);});
  const vatRate=parseFloat(d.vat_rate)||0, vatAmt=subtotal*vatRate/100;
  const total=subtotal+vatAmt, amtPaid=parseFloat(d.amount_paid)||0, balance=total-amtPaid;
  let itemRows=(d.items||[]).map(it=>{
    const free=!!it.is_free;
    const up=free?'offert':(sym+' '+fmt(parseFloat(it.unit_price)||0));
    const am=free?'offert':(sym+' '+fmt((parseFloat(it.unit_price)||0)*(parseFloat(it.qty)||0)));
    return`<tr><td>${esc(it.name||'')}${it.description?`<div class="item-desc">${escNl(it.description)}</div>`:''}</td>
      <td class="ref">${esc(it.reference||'')}</td><td class="price center">${up}</td>
      <td class="qty center">${esc(String(it.qty??''))}</td><td class="amount right">${am}</td></tr>`;
  }).join('')||'<tr><td colspan="5" style="color:#aaa;text-align:center;padding:14px">No items yet</td></tr>';
  let totalRows=`<tr><td>Subtotal (excl. VAT)</td><td class="right">${sym} ${fmt(subtotal)}</td></tr>
    <tr><td>VAT${vatRate>0?` (${vatRate}%)`:''}</td><td class="right">${sym} ${fmt(vatAmt)}</td></tr>`;
  if(d.show_amount_paid&&amtPaid>0) totalRows+=`<tr><td>Amount Paid</td><td class="right">${sym} ${fmt(amtPaid)}</td></tr>`;
  totalRows+=`<tr class="grand-total"><td>${esc(d.balance_label||'TOTAL DUE')} (${esc(d.currency||'EUR')})</td><td class="right">${sym} ${fmt(balance)}</td></tr>`;
  const cu=d.customer||{}, iss=d.issuer||{}, bank=d.bank||{};
  const parts=[]; if(cu.contact)parts.push(cu.contact); if(cu.phone)parts.push('TEL: '+cu.phone); if(cu.vat)parts.push('VAT: '+cu.vat);
  const contactLine=parts.length?`<div class="customer-contact">${esc(parts.join(' — '))}</div>`:'';
  const logoHtml=LOGO_DATA_URI?`<img class="logo" src="${LOGO_DATA_URI}" alt="Logo">`:'';
  const bankFields=[['beneficiary','Beneficiary'],['bank_name','Bank name'],['bank_address','Bank address'],['iban','IBAN'],['bic','BIC / SWIFT']];
  let bankRows=bankFields.map(([k,l])=>bank[k]?`<tr><td class="bank-label">${l}:</td><td>${k==='iban'?`<strong>${esc(bank[k])}</strong>`:esc(bank[k])}</td></tr>`:'').join('');
  document.getElementById('preview-doc').innerHTML=`
<div class="page">
  <header class="doc-header">
    <div class="company">${logoHtml}<h2>${esc(iss.name||'')}</h2>${iss.address?`<p>${esc(iss.address)}</p>`:''}${iss.city?`<p>${esc(iss.city)}</p>`:''}${iss.email?`<p>${esc(iss.email)}</p>`:''}${iss.legal?`<div class="company-legal">${escNl(iss.legal)}</div>`:''}</div>
    <div class="invoice-title"><h1>${type}</h1>
      <table class="invoice-info"><tbody><tr><th>No.</th><th>Date</th></tr><tr><td>${esc(d.number||'')}</td><td>${esc(d.date||'')}</td></tr></tbody></table>
      ${extraRows?`<table class="invoice-info extra-info"><tbody>${extraRows}</tbody></table>`:''}
    </div>
  </header>
  <div class="content">
    <section class="bill-to"><div class="section-title">Bill To</div>
      <div class="customer"><strong>${esc(cu.name||'')}</strong><br>${cu.address?esc(cu.address)+'<br>':''}${cu.city?esc(cu.city):''}${contactLine}</div>
    </section>
    <table class="items"><thead><tr><th>Name</th><th class="ref">Ref.</th><th class="price">Unit Price (${sym})</th><th class="qty">Qty</th><th class="amount">Amount (${sym})</th></tr></thead>
      <tbody>${itemRows}</tbody></table>
    <div class="totals"><table><tbody>${totalRows}</tbody></table></div>
    <div class="spacer"></div>
    ${d.vat_mention?`<div class="vat-mention">${escNl(d.vat_mention)}</div>`:''}
    ${d.notes?`<div class="notes-block"><strong>Notes</strong>${escNl(d.notes)}</div>`:''}
    ${bankRows?`<div class="bank-details"><strong>${esc(bank.label||'Bank Details')}</strong><table class="bank-info"><tbody>${bankRows}</tbody></table></div>`:''}
    ${d.terms?`<div class="terms-block"><strong>Terms &amp; Conditions</strong><div>${escNl(d.terms)}</div></div>`:''}
  </div>
  <footer class="doc-footer">${d.footer_thanks?`<div class="thanks">${esc(d.footer_thanks)}</div>`:''}${d.footer_contact?`<div class="contact">${escNl(d.footer_contact)}</div>`:''}</footer>
</div>`;
}

function fmt(n){return Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).replace(',', ' ');}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function escNl(s){return esc(s).replace(/\n/g,'<br>');}

function onFormChange() {
  const d=collectDoc(); docData=d; renderPreview(d); updateTopBar(d); isDirty=true; scheduleAutoSave();
}

function updateTopBar(d) {
  const badge=document.getElementById('topbar-type-badge');
  if(badge){badge.textContent=d.type||'INVOICE';badge.className='topbar-doc-type';}
  const numEl=document.getElementById('topbar-docnum-center');
  if(numEl)numEl.textContent=d.number?`— ${d.number}`:'';
}

document.querySelectorAll('#form-scroll input,#form-scroll select,#form-scroll textarea').forEach(el=>{
  el.addEventListener('input',onFormChange); el.addEventListener('change',onFormChange);
});

// ── Auto-save ─────────────────────────────────────────────────
function scheduleAutoSave(){clearTimeout(saveTimer);saveTimer=setTimeout(saveDocument,8000);setAutosaveStatus('saving','Unsaved changes…');}

async function saveDocument(force=false) {
  if(!isDirty&&!force)return;
  const d=collectDoc(); setAutosaveStatus('saving','Saving…');
  try {
    let url, body;
    if(currentId){url=API.docUpdate(currentId);body=JSON.stringify({data:d,status:'draft'});}
    else{url=API.docSave();body=JSON.stringify({data:d});}
    const json=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body}).then(r=>r.json());
    if(!json.ok)throw new Error(json.error||'Save failed');
    if(!currentId&&json.id){currentId=json.id;history.replaceState(null,'',`${BASE}/editor?id=${currentId}`);}
    isDirty=false; setAutosaveStatus('saved','Saved '+new Date().toLocaleTimeString());
  } catch(e){setAutosaveStatus('error','Save failed: '+e.message);toast('Save failed: '+e.message,'error');}
}

function setAutosaveStatus(state,text){
  document.getElementById('autosave-dot').className='autosave-dot '+state;
  document.getElementById('autosave-label').textContent=text;
}

function wire(id, evt, fn) {
  const el = document.getElementById(id);
  if (el) el.addEventListener(evt, fn);
}

wire('btn-save',  'click', async () => { isDirty=true; await saveDocument(true); toast('Document saved','success'); });
wire('btn-print', 'click', async () => {
  if(isDirty){isDirty=true;await saveDocument(true);}
  if(!currentId){toast('Please save first','error');return;}
  window.open(`${BASE}/preview?id=${currentId}`,'_blank');
});
wire('btn-model', 'click', () => {
  const model = {
    _instructions: [
      "This is a reference model for DocEditor. Fill the _value fields and import into the editor.",
      "Fields marked _note are for understanding only — remove them before importing.",
      "All fields are optional except 'type'. Unknown keys are ignored on import.",
      "To import: editor → Import JSON → select your filled file."
    ],
    type:            { _note: "INVOICE | QUOTE | CREDIT NOTE | OTHER | any custom string e.g. PROFORMA", _value: "INVOICE" },
    number:          { _note: "Reference number. Suggested format: INV-YYYYMMDD-N.", _value: "" },
    date:            { _note: "Document date, ISO format YYYY-MM-DD.", _value: new Date().toISOString().slice(0,10) },
    due_date:        { _note: "Payment due date. Leave empty if not applicable.", _value: "" },
    service_date_start: { _note: "First day of the service period, YYYY-MM-DD. Leave empty if not applicable.", _value: "" },
    service_date_end:   { _note: "Last day of the service period, YYYY-MM-DD. Leave empty for a single-day service.", _value: "" },
    po_number:          { _note: "Customer purchase order number. Optional — leave empty if there is none.", _value: "" },
    quote_ref:       { _note: "Related quote number. Leave empty if not applicable.", _value: "" },
    tracking:        { _note: "Shipment tracking number. Leave empty if not applicable.", _value: "" },
    currency:        { _note: "ISO code: EUR | USD | GBP | CHF or any other.", _value: "EUR" },
    currency_symbol: { _note: "Symbol next to amounts.", _value: "€" },
    issuer: {
      _note:   "Your company details — shown in the document header.",
      name:    "NOVOCIB SAS",
      address: "BD de Chatillon, Quai Jean Voisin",
      city:    "62200 Boulogne-sur-Mer — France",
      email:   "contact@novocib.com",
      legal:   "SAS, société par actions simplifiée — Share capital: 260 158,00 €"
    },
    customer: {
      _note:   "The client / bill-to details.",
      name:    "", address: "", city: "", contact: "", phone: "", vat: ""
    },
    items: {
      _note: "Array of line items. is_free=true marks a complimentary item (shown as 'offert', excluded from subtotal).",
      _value: [
        { name: "HPLC-UV Analysis", description: "3 concentrations, triplicate", reference: "S1200-03-NA", unit_price: 300, qty: 2, is_free: false },
        { name: "Complimentary Consultation", description: "Protocol review", reference: "", unit_price: 150, qty: 1, is_free: true }
      ]
    },
    vat_rate:         { _note: "VAT % on subtotal. 0 for tax-exempt / export.", _value: 0 },
    vat_mention:      { _note: "Italic line below totals, e.g. VAT exemption reason.", _value: "" },
    show_amount_paid: { _note: "true = show Amount Paid row and balance.", _value: false },
    amount_paid:      { _note: "Amount already received.", _value: 0 },
    balance_label:    { _note: "Grand total row label.", _value: "TOTAL DUE" },
    notes:            { _note: "Free-text block below VAT mention.", _value: "" },
    terms:            { _note: "Terms & conditions.", _value: "PAYMENT IMMEDIATE UPON RECEIPT, by credit card or wire transfer." },
    bank: {
      _note: "Bank details.",
      label: "Bank Details EUR - Banque Populaire, France",
      beneficiary: "SAS NOVOCIB",
      bank_name: "BANQUE POPULAIRE AUVERGNE RHÔNE ALPES (BPAURA)",
      bank_address: "215 Avenue Jean Jaurès, 69007 Lyon, France",
      iban: "FR76 1680 7004 0081 0876 0421 151",
      bic: "CCBPFRPPGRE"
    },
    footer_thanks:  { _note: "Centred thank-you line in the footer.", _value: "Thank you for your business!" },
    footer_contact: { _note: "Small contact line(s).", _value: "If you have any questions, please contact us.\n• contact@novocib.com" },
    _ai_prompt_suggestions: [
      "Fill this model for customer [NAME] with the following services: ...",
      "Change the type to QUOTE and remove the bank details section.",
      "Add 3 line items for HPLC analysis at €300 each, VAT not applicable.",
      "Mark as partially paid: amount_paid = 500, show_amount_paid = true."
    ]
  };
  const d = docData ? { ...docData } : collectDoc();
  const name = [d.type||'INVOICE', 'model'].join('_').replace(/\s+/g,'-');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([JSON.stringify(model,null,2)],{type:'application/json'}));
  a.download = name + '.json'; a.click();
  toast('Model JSON downloaded','success');
});

// ── Export JSON ───────────────────────────────────────────────
wire('btn-export', 'click',()=>{
  const d=docData?{...docData}:collectDoc();
  const exp={...d,issuer:{...d.issuer,logo_base64:''}};
  const name=[exp.type,exp.number].filter(Boolean).join('_').replace(/\s+/g,'-')||'document';
  const a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob([JSON.stringify(exp,null,2)],{type:'application/json'}));
  a.download=name+'.json'; a.click(); toast('Exported '+name+'.json','success');
});

// ── Import JSON (file or pasted text) ─────────────────────────
// AI chats usually wrap JSON in ```json fences or add a sentence around it — tolerate both.
function parseJsonLoose(text) {
  const raw = String(text).trim();
  if (!raw) throw new Error('nothing to import, the text is empty');
  const candidates = [raw];
  const fence = raw.match(/```(?:json)?\s*([\s\S]*?)```/i);
  if (fence) candidates.push(fence[1].trim());
  const a = raw.indexOf('{'), b = raw.lastIndexOf('}');
  if (a !== -1 && b > a) candidates.push(raw.slice(a, b + 1));
  let firstErr = null;
  for (const c of candidates) {
    try { return JSON.parse(c); } catch (e) { firstErr = firstErr || e; }
  }
  throw firstErr;
}

// Shared by "Import JSON" (file) and "Paste JSON" (text): parse → unwrap Model JSON → fill form
function importDocText(text) {
  const d = unwrapModelJson(parseJsonLoose(text));
  if (!d || typeof d !== 'object' || Array.isArray(d)) throw new Error('expected a JSON object describing one document');
  populateForm(d);
  onFormChange();   // refresh docData, live preview, top bar and schedule the auto-save
}

wire('btn-import', 'change', function () {
  const file = this.files[0]; if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    try { importDocText(e.target.result); toast('JSON imported', 'success'); }
    catch (err) { toast('Import failed: ' + err.message, 'error'); }
    this.value = '';
  };
  reader.readAsText(file);
});

// ── Paste JSON modal ──────────────────────────────────────────
function openPasteModal() {
  document.getElementById('paste-modal').style.display = 'flex';
  document.getElementById('paste-error').textContent = '';
  const ta = document.getElementById('paste-json'); ta.focus(); ta.select();
}
function closePasteModal() { document.getElementById('paste-modal').style.display = 'none'; }
function loadPastedJson() {
  const ta = document.getElementById('paste-json'), errEl = document.getElementById('paste-error');
  try {
    importDocText(ta.value);
    ta.value = ''; errEl.textContent = '';
    closePasteModal();
    toast('JSON loaded into the editor', 'success');
  } catch (e) {
    errEl.textContent = 'Could not load: ' + e.message;
  }
}
wire('btn-paste', 'click', openPasteModal);
wire('btn-paste-load', 'click', loadPastedJson);
wire('paste-json', 'keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); loadPastedJson(); } });

function unwrapModelJson(obj) {
  if(Array.isArray(obj))return obj.map(unwrapModelJson);
  if(obj&&typeof obj==='object'){
    if('_value'in obj)return unwrapModelJson(obj._value);
    const out={};
    for(const[k,val]of Object.entries(obj)){
      if(['_note','_instructions','_ai_prompt_suggestions'].includes(k))continue;
      out[k]=unwrapModelJson(val);
    }
    return out;
  }
  return obj;
}

// ── Generate next document number ─────────────────────────────
wire('btn-gen-number', 'click', async function () {
  const btn  = this;
  const type = v('f-type') || 'INVOICE';
  btn.disabled = true;
  try {
    const res  = await fetch(API.nextNumber(type), { cache: 'no-store' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Request failed');
    set('f-number', json.number);
    onFormChange();
    toast('Generated ' + json.number, 'success');
  } catch (e) {
    toast('Failed to generate number: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
  }
});

// ── Customer picker ───────────────────────────────────────────
let custTimer=null;
wire('btn-pick-customer', 'click',(e)=>{
  e.stopPropagation();   // button lives in the collapsible header
  document.getElementById('customer-modal').style.display='flex';
  const inp=document.getElementById('customer-search'); inp.value=''; inp.focus(); searchCustomers('');
});
function closeCustomerPicker(){document.getElementById('customer-modal').style.display='none';}
function searchCustomers(q){
  clearTimeout(custTimer);
  custTimer=setTimeout(async()=>{
    const list=document.getElementById('customer-modal-list');
    list.innerHTML='<div class="cust-empty">Searching…</div>';
    let json;
    try{json=await fetch(API.customers(q)).then(r=>r.json());}catch(e){json={ok:false,error:e.message};}
    if(!json.ok){list.innerHTML=`<div class="cust-empty">Error: ${esc(json.error)}</div>`;return;}
    const items=json.customers;
    if(!items.length){list.innerHTML='<div class="cust-empty">No customers found.</div>';return;}
    list.innerHTML=items.map(c=>`<div class="cust-row" onclick="selectCustomer(${c.id})">
      <div class="cust-row-name">${esc(c.name)}</div>
      <div class="cust-row-detail">${esc([c.city,c.contact,c.phone].filter(Boolean).join(' · '))}</div>
    </div>`).join('');
  },180);
}
async function selectCustomer(id){
  const json=await fetch(API.customerGet(id)).then(r=>r.json());
  if(!json.ok){toast('Error: '+json.error,'error');return;}
  const c=json.customer;
  set('f-cust-name',c.name||''); set('f-cust-addr',c.address||'');
  set('f-cust-city',c.city||''); set('f-cust-contact',c.contact||'');
  set('f-cust-phone',c.phone||''); set('f-cust-vat',c.vat||'');
  closeCustomerPicker(); onFormChange(); toast('Customer loaded: '+c.name,'success');
}

// ── Item picker ───────────────────────────────────────────────
let itemTimer=null;
wire('btn-pick-item', 'click',()=>{
  document.getElementById('item-modal').style.display='flex';
  const inp=document.getElementById('item-search'); inp.value=''; inp.focus(); searchItems('');
});
function closeItemPicker(){document.getElementById('item-modal').style.display='none';}
function searchItems(q){
  clearTimeout(itemTimer);
  itemTimer=setTimeout(async()=>{
    const list=document.getElementById('item-modal-list');
    list.innerHTML='<div class="cust-empty">Searching…</div>';
    let json;
    try{json=await fetch(API.items(q)).then(r=>r.json());}catch(e){json={ok:false,error:e.message};}
    if(!json.ok){list.innerHTML=`<div class="cust-empty">Error: ${esc(json.error)}</div>`;return;}
    const items=json.items;
    if(!items.length){list.innerHTML='<div class="cust-empty">No items found. <a href="'+BASE+'/items" target="_blank">Add some →</a></div>';return;}
    list.innerHTML=items.map(it=>{
      const price=it.price!==''&&!isNaN(parseFloat(it.price))?(v('f-currency-symbol')||'€')+' '+parseFloat(it.price).toLocaleString('fr-FR',{minimumFractionDigits:2}):'';
      const detail=[it.reference,it.unit,price].filter(Boolean).join(' · ');
      return`<div class="cust-row" onclick="selectItem(${it.id})">
        <div class="cust-row-name">${esc(it.title)}</div>
        <div class="cust-row-detail">${esc(detail)}${it.description?' — '+esc(it.description.substring(0,60)):''}</div>
      </div>`;
    }).join('');
  },180);
}
async function selectItem(id){
  const json=await fetch(API.itemGet(id)).then(r=>r.json());
  if(!json.ok){toast('Error: '+json.error,'error');return;}
  const it=json.item;
  addItemRow({name:it.title||'',description:it.description||'',reference:it.reference||'',unit_price:it.price||0,qty:1,is_free:false});
  closeItemPicker(); onFormChange(); toast('Item added: '+it.title,'success');
}

document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeCustomerPicker();closeItemPicker();closePasteModal();}});

function toast(msg,type=''){
  const c=document.getElementById('toast-container');
  const t=document.createElement('div'); t.className='toast'+(type?' toast-'+type:''); t.textContent=msg;
  c.appendChild(t); requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show')));
  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300)},3000);
}

// ── Init ──────────────────────────────────────────────────────
async function init(){
  if(EDIT_ID){
    try{
      const json=await fetch(API.docGet(EDIT_ID)).then(r=>r.json());
      if(!json.ok)throw new Error(json.error);
      docData=json.document.data; populateForm(docData); renderPreview(docData);
      setAutosaveStatus('saved','Loaded from database');
    }catch(e){toast('Failed to load: '+e.message,'error');}
  } else {
    try{
      const json=await fetch(API.docDefault(NEW_TYPE)).then(r=>r.json());
      docData=json.ok ? json.data : {};
    }catch{ docData={}; }
    if(!docData) docData={};
    docData.type=NEW_TYPE;
    populateForm(docData); renderPreview(docData);
    setAutosaveStatus('','New document — not saved yet');
  }
}
init();