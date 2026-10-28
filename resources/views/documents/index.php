<style>html,body{height:auto;overflow:auto}</style>
<?php require ROOT . '/resources/views/partials/bulk_io.php'; ?>

<div class="history-layout">
  <div class="history-topbar">
    <h1>Documents</h1>
    <div class="search-bar">
      <input type="text" id="search-input" placeholder="Search number or client…" oninput="loadDocs()">
      <select id="filter-type" onchange="loadDocs()">
        <option value="">All types</option>
        <option value="INVOICE">Invoice</option>
        <option value="QUOTE">Quote</option>
        <option value="CREDIT NOTE">Credit Note</option>
        <option value="OTHER">Other</option>
      </select>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-secondary btn-sm" id="btn-export">⬇ Export</button>
      <label class="btn btn-secondary btn-sm" style="cursor:pointer">⬆ Import
        <input type="file" id="btn-import" accept=".json,application/json" style="display:none">
      </label>
      <button class="btn btn-secondary btn-sm" onclick="openNew('QUOTE')">+ Quote</button>
      <button class="btn btn-primary btn-sm" onclick="openNew('INVOICE')">+ Invoice</button>
    </div>
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

async function loadDocs() {
  const q    = document.getElementById('search-input').value.trim();
  const type = document.getElementById('filter-type').value;
  const url  = `${BASE}/api/documents?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}`;
  try {
    const json = await fetch(url).then(r => r.json());
    if (!json.ok) throw new Error(json.error);
    renderList(json.documents);
  } catch(e) {
    document.getElementById('doc-list').innerHTML =
      `<div class="empty-state"><div class="empty-icon">⚠️</div><h2>Error</h2><p>${esc(e.message)}</p></div>`;
  }
}

function renderList(docs) {
  const el = document.getElementById('doc-list');
  if (!docs.length) {
    el.innerHTML = `<div class="empty-state"><div class="empty-icon">📄</div><h2>No documents yet</h2><p>Create your first invoice or quote above.</p></div>`;
    return;
  }
  el.innerHTML = `<table class="doc-table">
    <thead><tr><th>Type</th><th>Number</th><th>Client</th><th>Date</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead>
    <tbody>${docs.map(d => `<tr>
      <td><span class="type-badge ${TYPE_CLASSES[d.type]||'type-other'}">${esc(d.type)}</span></td>
      <td style="font-weight:600">${esc(d.number||'—')}</td>
      <td>${esc(d.customer||'—')}</td>
      <td>${esc(d.date||'—')}</td>
      <td><span class="badge ${STATUS_CLASSES[d.status]||'badge-draft'}">${esc(d.status)}</span></td>
      <td style="color:#9098a8;font-size:12px">${esc((d.updated_at||'').slice(0,16))}</td>
      <td><div class="action-btns">
        <a href="${BASE}/editor?id=${d.id}" class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none">Edit</a>
        <a href="${BASE}/preview?id=${d.id}" target="_blank" class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none">Print</a>
        <button class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none" onclick="duplicateDoc(${d.id})">Copy</button>
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
loadDocs();
</script>
