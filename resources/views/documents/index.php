<style>
html,body{height:auto;overflow:auto}
.filter-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px 10px;margin:-8px 0 18px;padding:12px 14px;background:#fff;border:1px solid var(--ui-border);border-radius:8px}
.filter-bar input,.filter-bar select{border:1px solid var(--ui-input-border);border-radius:5px;padding:7px 10px;font-size:13px;font-family:var(--font);background:#fff;color:var(--ui-text);outline:none}
.filter-bar input:focus,.filter-bar select:focus{border-color:var(--ui-focus)}
.filter-bar .f-search{width:230px}
.filter-bar .f-dates{display:inline-flex;align-items:center;gap:8px;font-size:12px;color:var(--ui-text-soft)}
.filter-bar .f-dates label{display:inline-flex;align-items:center;gap:6px}
.filter-bar .f-date{width:108px;text-align:center}
.filter-bar .f-date.bad{border-color:var(--ui-danger);background:#fdecea}
.filter-bar .f-presets button,.filter-bar .f-clear{border:1px solid var(--ui-input-border);background:#f4f6fa;color:var(--ui-text);border-radius:14px;padding:5px 11px;font-size:12px;font-family:var(--font);cursor:pointer}
.filter-bar .f-presets button:hover,.filter-bar .f-clear:hover{background:#e8effc;border-color:var(--ui-primary)}
.filter-bar .f-clear{border-radius:5px}
.filter-bar .f-count{margin-left:auto;font-size:12px;color:var(--ui-text-muted)}
</style>
<?php require ROOT . '/resources/views/partials/bulk_io.php'; ?>

<div class="history-layout">
  <div class="history-topbar">
    <h1>Documents</h1>
    <div style="display:flex;gap:8px">
      <button class="btn btn-secondary btn-sm" id="btn-export">⬇ Export</button>
      <label class="btn btn-secondary btn-sm" style="cursor:pointer">⬆ Import
        <input type="file" id="btn-import" accept=".json,application/json" style="display:none">
      </label>
      <button class="btn btn-secondary btn-sm" onclick="openNew('QUOTE')">+ Quote</button>
      <button class="btn btn-primary btn-sm" onclick="openNew('INVOICE')">+ Invoice</button>
    </div>
  </div>

  <div class="filter-bar" id="filter-bar">
    <input type="search" id="f-q" class="f-search" placeholder="Search number or client…" autocomplete="off">
    <select id="f-type">
      <option value="">All types</option>
      <option value="INVOICE">Invoice</option>
      <option value="QUOTE">Quote</option>
      <option value="CREDIT NOTE">Credit Note</option>
      <option value="OTHER">Other</option>
    </select>
    <select id="f-status">
      <option value="">All statuses</option>
      <option value="draft">Draft</option>
      <option value="final">Final</option>
      <option value="paid">Paid</option>
    </select>
    <span class="f-dates">
      <label>Date from <input type="text" id="f-from" class="f-date" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off"></label>
      <label>to <input type="text" id="f-to" class="f-date" placeholder="dd/mm/yyyy" inputmode="numeric" maxlength="10" autocomplete="off"></label>
    </span>
    <span class="f-presets">
      <button type="button" data-preset="month">This month</button>
      <button type="button" data-preset="lastmonth">Last month</button>
      <button type="button" data-preset="year">This year</button>
    </span>
    <button type="button" id="f-clear" class="f-clear" title="Reset all filters">✕ Clear</button>
    <span id="f-count" class="f-count"></span>
  </div>

  <div id="doc-list">
    <div class="empty-state"><div class="empty-icon">📄</div><h2>Loading…</h2></div>
  </div>
</div>

<!-- Confirm delete modal -->
<div id="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:10px;padding:28px 32px;max-width:380px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18)">
    <h3 style="margin-bottom:10px;font-size:17px">Confirm</h3>
    <p style="color:#5a6070;font-size:14px;margin-bottom:22px" id="modal-body"></p>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-ghost btn-sm" onclick="closeModal()" style="color:#1a1d23;background:#eee;border:none">Cancel</button>
      <button class="btn btn-danger btn-sm" id="modal-confirm">Delete</button>
    </div>
  </div>
</div>

<script>
const TYPE_CLASSES   = {INVOICE:'type-invoice',QUOTE:'type-quote','CREDIT NOTE':'type-credit',OTHER:'type-other'};
const STATUS_CLASSES = {draft:'badge-draft',final:'badge-final',paid:'badge-paid'};

function openNew(type) { window.location.href = `${BASE}/editor?type=${type}`; }

// ── dates: stored as yyyy-mm-dd, shown (and typed) as dd/mm/yyyy ──
function fmtDate(iso) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
  return m ? `${m[3]}/${m[2]}/${m[1]}` : (iso || '—');
}
function fmtDateTime(s) {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(s || '');
  return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : fmtDate(s);
}
/** "21/10/2026" -> "2026-10-21"; '' when incomplete; null when complete but not a real date */
function parseDate(txt) {
  txt = txt.trim();
  if (txt === '') return '';
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(txt);
  if (!m) return '';
  const d = new Date(+m[3], +m[2] - 1, +m[1]);
  if (d.getFullYear() !== +m[3] || d.getMonth() !== +m[2] - 1 || d.getDate() !== +m[1]) return null;
  return `${m[3]}-${m[2]}-${m[1]}`;
}
const pad = n => String(n).padStart(2, '0');
const toDmy = d => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;

const F = {
  q: document.getElementById('f-q'), type: document.getElementById('f-type'), status: document.getElementById('f-status'),
  from: document.getElementById('f-from'), to: document.getElementById('f-to'),
};
const FILTER_KEY = 'doceditor.docFilters';

function saveFilters() {
  try { sessionStorage.setItem(FILTER_KEY, JSON.stringify({q: F.q.value, type: F.type.value, status: F.status.value, from: F.from.value, to: F.to.value})); } catch (e) {}
}
function restoreFilters() {
  try {
    const s = JSON.parse(sessionStorage.getItem(FILTER_KEY) || 'null');
    if (s) for (const k of Object.keys(F)) if (typeof s[k] === 'string') F[k].value = s[k];
  } catch (e) {}
}
function maskDate(input) {            // typing 21102026 gives 21/10/2026
  const digits = input.value.replace(/\D/g, '').slice(0, 8);
  let out = digits.slice(0, 2);
  if (digits.length > 2) out += '/' + digits.slice(2, 4);
  if (digits.length > 4) out += '/' + digits.slice(4);
  input.value = out;
}

let loadTimer = null, loadSeq = 0;
function loadDocs(delay = 0) {
  clearTimeout(loadTimer);
  loadTimer = setTimeout(doLoad, delay);
}
async function doLoad() {
  const from = parseDate(F.from.value), to = parseDate(F.to.value);
  F.from.classList.toggle('bad', from === null);
  F.to.classList.toggle('bad', to === null);
  saveFilters();
  if (from === null || to === null) return;            // wait until the typed date is a real date

  const qs = new URLSearchParams({q: F.q.value.trim(), type: F.type.value, status: F.status.value, from, to});
  const seq = ++loadSeq;
  try {
    const json = await fetch(`${BASE}/api/documents?${qs}`).then(r => r.json());
    if (seq !== loadSeq) return;                         // a newer request is on its way
    if (!json.ok) throw new Error(json.error);
    const filtered = !!(F.q.value.trim() || F.type.value || F.status.value || from || to);
    document.getElementById('f-count').textContent = `${json.documents.length} document${json.documents.length === 1 ? '' : 's'}` + (filtered ? ' (filtered)' : '');
    renderList(json.documents, filtered);
  } catch(e) {
    document.getElementById('doc-list').innerHTML =
      `<div class="empty-state"><div class="empty-icon">⚠️</div><h2>Error</h2><p>${esc(e.message)}</p></div>`;
  }
}

function setPreset(p) {
  const n = new Date(), y = n.getFullYear(), m = n.getMonth();
  let a, b;
  if (p === 'month')     { a = new Date(y, m, 1);     b = new Date(y, m + 1, 0); }
  if (p === 'lastmonth') { a = new Date(y, m - 1, 1); b = new Date(y, m, 0); }
  if (p === 'year')      { a = new Date(y, 0, 1);     b = new Date(y, 11, 31); }
  F.from.value = toDmy(a); F.to.value = toDmy(b);
  loadDocs();
}

F.q.addEventListener('input', () => loadDocs(250));
F.type.addEventListener('change', () => loadDocs());
F.status.addEventListener('change', () => loadDocs());
[F.from, F.to].forEach(el => el.addEventListener('input', () => { maskDate(el); loadDocs(300); }));
document.querySelectorAll('[data-preset]').forEach(b => b.addEventListener('click', () => setPreset(b.dataset.preset)));
document.getElementById('f-clear').addEventListener('click', () => {
  F.q.value = ''; F.type.value = ''; F.status.value = ''; F.from.value = ''; F.to.value = '';
  loadDocs();
});

function renderList(docs, filtered = false) {
  const el = document.getElementById('doc-list');
  if (!docs.length) {
    el.innerHTML = filtered
      ? `<div class="empty-state"><div class="empty-icon">🔍</div><h2>No document matches</h2><p>Try other filters, or press “Clear”.</p></div>`
      : `<div class="empty-state"><div class="empty-icon">📄</div><h2>No documents yet</h2><p>Create your first invoice or quote above.</p></div>`;
    return;
  }
  el.innerHTML = `<table class="doc-table">
    <thead><tr><th>Type</th><th>Number</th><th>Client</th><th>Date</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead>
    <tbody>${docs.map(d => `<tr>
      <td><span class="type-badge ${TYPE_CLASSES[d.type]||'type-other'}">${esc(d.type)}</span></td>
      <td style="font-weight:600">${esc(d.number||'—')}</td>
      <td>${esc(d.customer||'—')}</td>
      <td>${esc(fmtDate(d.date))}</td>
      <td><span class="badge ${STATUS_CLASSES[d.status]||'badge-draft'}">${esc(d.status)}</span></td>
      <td style="color:#9098a8;font-size:12px">${esc(fmtDateTime(d.updated_at))}</td>
      <td><div class="action-btns">
        <a href="${BASE}/editor?id=${d.id}" class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none">Edit</a>
        <a href="${BASE}/preview?id=${d.id}" target="_blank" class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none">Print</a>
        <button class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none" onclick="duplicateDoc(${d.id})">Copy</button>
        ${d.type==='QUOTE'?`<button class="btn btn-primary btn-sm" title="Create an invoice from this quote" onclick="convertDoc(${d.id})">→ Invoice</button>`:''}
        <button class="btn btn-danger btn-sm" onclick="confirmDelete(${d.id},'${esc(d.number||'this document')}')">Del</button>
      </div></td>
    </tr>`).join('')}</tbody>
  </table>`;
}

let pendingDeleteId = null;
function confirmDelete(id, label) {
  pendingDeleteId = id;
  document.getElementById('modal-body').textContent = `Delete "${label}"? This cannot be undone.`;
  document.getElementById('modal-overlay').style.display = 'flex';
  document.getElementById('modal-confirm').onclick = doDelete;
}
function closeModal() { document.getElementById('modal-overlay').style.display='none'; pendingDeleteId=null; }
async function doDelete() {
  const id = pendingDeleteId; closeModal();
  const json = await fetch(`${BASE}/api/documents/${id}/delete`,{method:'POST'}).then(r=>r.json());
  if (!json.ok) { toast('Error: '+json.error,'error'); return; }
  toast('Deleted','success'); loadDocs();
}
async function convertDoc(id, force = false) {
  const json = await fetch(`${BASE}/api/documents/${id}/convert`, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({force})}).then(r=>r.json());
  if (json.code === 'already_converted') {
    if (confirm(`This quote was already converted (${json.invoices.join(', ')}).\n\nCreate another invoice anyway?`)) return convertDoc(id, true);
    return;
  }
  if (!json.ok) { toast(json.error || 'Conversion failed', 'error'); return; }
  toast('Invoice ' + json.number + ' created', 'success');
  setTimeout(() => { window.location.href = `${BASE}/editor?id=${json.id}`; }, 500);
}
async function duplicateDoc(id) {
  const json = await fetch(`${BASE}/api/documents/${id}/duplicate`,{method:'POST'}).then(r=>r.json());
  if (!json.ok) { toast('Error: '+json.error,'error'); return; }
  toast('Duplicated','success'); loadDocs();
}
function toast(msg,type=''){
  const c=document.getElementById('toast-container');
  const t=document.createElement('div'); t.className='toast'+(type?' toast-'+type:''); t.textContent=msg;
  c.appendChild(t); requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show')));
  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300)},3000);
}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
document.getElementById('modal-overlay').addEventListener('click',function(e){if(e.target===this)closeModal();});

document.getElementById('btn-export').addEventListener('click', async () => {
  try {
    const json = await fetch(`${BASE}/api/documents/export`).then(r => r.json());
    if (!json.ok) throw new Error(json.error);
    const {ok, ...payload} = json;
    bulkDownload('doceditor-documents-' + new Date().toISOString().slice(0, 10) + '.json', payload);
    toast(`Exported ${payload.count} document(s)`, 'success');
  } catch (e) { toast('Export failed: ' + e.message, 'error'); }
});
document.getElementById('btn-import').addEventListener('change', function () {
  bulkImport(this, {url: `${BASE}/api/documents/import`, key: 'documents', label: 'document(s)', batch: 25, single: true, done: loadDocs});
});
restoreFilters();
loadDocs();
</script>
