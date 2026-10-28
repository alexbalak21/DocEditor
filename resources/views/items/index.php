<style>html,body{height:auto;overflow:auto}</style>
<?php require ROOT . '/resources/views/partials/bulk_io.php'; ?>

<div class="history-layout">
  <div class="history-topbar">
    <h1>Items &amp; Services</h1>
    <div class="search-bar">
      <input type="text" id="search-input" placeholder="Search reference, title, description…" oninput="loadItems()">
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-secondary btn-sm" id="btn-export"><i class="fa-solid fa-download"></i> Export</button>
      <label class="btn btn-secondary btn-sm" style="cursor:pointer"><i class="fa-solid fa-upload"></i> Import
        <input type="file" id="btn-import" accept=".json" style="display:none">
      </label>
      <button class="btn btn-primary btn-sm" onclick="openForm()"><i class="fa-solid fa-plus"></i> New Item</button>
    </div>
  </div>

  <!-- Add / Edit form -->
  <div class="item-form-card" id="item-form-card" style="display:none">
    <h2 id="form-title"><i class="fa-solid fa-box"></i> New Item</h2>
    <input type="hidden" id="edit-id">
    <div class="item-form-grid">
      <div class="field third"><label>Reference *</label><input id="fi-ref"   type="text"   placeholder="S1200-03-NA"></div>
      <div class="field third"><label>Unit / Size</label><input id="fi-unit"  type="text"   placeholder="per sample, /hour…"></div>
      <div class="field full"> <label>Title *</label>    <input id="fi-title" type="text"   placeholder="HPLC-UV Analysis — Kinetics Study"></div>
      <div class="field third"><label>Unit Price (€)</label><input id="fi-price" type="number" min="0" step="0.01" placeholder="300.00"></div>
      <div class="field full"> <label>Description</label><textarea id="fi-desc" rows="3" placeholder="Optional details shown below the item name on the document…"></textarea></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px">
      <button class="btn btn-primary btn-sm" onclick="saveItem()" style="background:var(--ui-primary);color:white">
        <i class="fa-solid fa-floppy-disk"></i> Save Item
      </button>
      <button class="btn btn-ghost btn-sm" onclick="closeForm()" style="background:#eee;color:#1a1d23;border:none">Cancel</button>
    </div>
  </div>

  <div id="item-list">
    <div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-boxes-stacked"></i></div><h2>Loading…</h2></div>
  </div>
</div>

<!-- Delete modal -->
<div id="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:10px;padding:28px 32px;max-width:380px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18)">
    <h3 style="margin-bottom:10px;font-size:17px">Delete Item</h3>
    <p style="color:#5a6070;font-size:14px;margin-bottom:22px" id="modal-body"></p>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-ghost btn-sm" onclick="closeModal()" style="color:#1a1d23;background:#eee;border:none">Cancel</button>
      <button class="btn btn-danger btn-sm" id="modal-confirm">Delete</button>
    </div>
  </div>
</div>

<script>
async function loadItems() {
  const q = document.getElementById('search-input').value.trim();
  try {
    const json = await fetch(`${BASE}/api/items?q=${encodeURIComponent(q)}`).then(r => r.json());
    if (!json.ok) throw new Error(json.error);
    renderList(json.items);
  } catch(e) {
    document.getElementById('item-list').innerHTML =
      `<div class="empty-state"><div class="empty-icon">⚠️</div><h2>Error</h2><p>${esc(e.message)}</p></div>`;
  }
}

function renderList(items) {
  const el = document.getElementById('item-list');
  if (!items.length) {
    el.innerHTML = `<div class="empty-state">
      <div class="empty-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
      <h2>No items yet</h2>
      <p>Add your products and services here. They will be available to pick directly in the document editor.</p>
    </div>`;
    return;
  }
  el.innerHTML = `<table class="doc-table">
    <thead><tr><th>Reference</th><th>Title</th><th>Description</th><th>Unit / Size</th><th>Price</th><th>Actions</th></tr></thead>
    <tbody>${items.map(it => {
      const price = it.price !== '' && it.price !== null
        ? '€ ' + parseFloat(it.price).toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2})
        : '—';
      return `<tr>
        <td><span class="ref-code">${esc(it.reference||'—')}</span></td>
        <td style="font-weight:600">${esc(it.title)}</td>
        <td class="desc-cell" title="${esc(it.description||'')}">${esc(it.description||'—')}</td>
        <td style="color:var(--ui-text-muted);font-size:12px">${esc(it.unit||'—')}</td>
        <td style="font-weight:600">${esc(price)}</td>
        <td><div class="action-btns">
          <button class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none" onclick="editItem(${it.id})">
            <i class="fa-solid fa-pen"></i> Edit
          </button>
          <button class="btn btn-danger btn-sm" onclick="confirmDelete(${it.id},'${esc(it.title)}')">
            <i class="fa-solid fa-trash"></i> Delete
          </button>
        </div></td>
      </tr>`;
    }).join('')}</tbody></table>`;
}

function openForm(id=null) {
  const card = document.getElementById('item-form-card');
  card.style.display = 'block';
  document.getElementById('edit-id').value = id ?? '';
  document.getElementById('form-title').innerHTML = id
    ? '<i class="fa-solid fa-pen"></i> Edit Item'
    : '<i class="fa-solid fa-box"></i> New Item';
  if (!id) ['ref','title','unit','price','desc'].forEach(f => {
    const el = document.getElementById('fi-'+f); if(el) el.value='';
  });
  document.getElementById('fi-ref').focus();
  card.scrollIntoView({ behavior: 'smooth' });
}
function closeForm() { document.getElementById('item-form-card').style.display='none'; document.getElementById('edit-id').value=''; }

async function editItem(id) {
  const json = await fetch(`${BASE}/api/items/${id}`).then(r=>r.json());
  if (!json.ok) { toast('Error: '+json.error,'error'); return; }
  const it = json.item;
  openForm(id);
  set('fi-ref',it.reference); set('fi-title',it.title); set('fi-unit',it.unit);
  set('fi-price',it.price);   set('fi-desc',it.description);
}

async function saveItem() {
  const id  = document.getElementById('edit-id').value;
  const ref = v('fi-ref'), title = v('fi-title');
  if (!ref)   { toast('Reference is required','error'); return; }
  if (!title) { toast('Title is required','error');     return; }
  const body = { reference:ref, title, unit:v('fi-unit'), price:v('fi-price'), description:v('fi-desc') };
  const url  = id ? `${BASE}/api/items/${id}` : BASE+'/api/items';
  const json = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(r=>r.json());
  if (!json.ok) { toast('Error: '+json.error,'error'); return; }
  toast(id ? 'Item updated' : 'Item created','success');
  closeForm(); loadItems();
}

let pendingId = null;
function confirmDelete(id, title) {
  pendingId = id;
  document.getElementById('modal-body').textContent = `Delete "${title}"? This cannot be undone.`;
  document.getElementById('modal-overlay').style.display = 'flex';
  document.getElementById('modal-confirm').onclick = doDelete;
}
function closeModal() { document.getElementById('modal-overlay').style.display='none'; pendingId=null; }
async function doDelete() {
  const id = pendingId; closeModal();
  const json = await fetch(`${BASE}/api/items/${id}/delete`,{method:'POST'}).then(r=>r.json());
  if (!json.ok) { toast('Error: '+json.error,'error'); return; }
  toast('Deleted','success'); loadItems();
}
document.getElementById('modal-overlay').addEventListener('click',function(e){if(e.target===this)closeModal();});

// Export
document.getElementById('btn-export').addEventListener('click', async () => {
  const json = await fetch(BASE+'/api/items').then(r=>r.json());
  if (!json.ok) { toast('Export failed','error'); return; }
  const clean = json.items.map(({id,created_at,updated_at,...it})=>it);
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([JSON.stringify(clean,null,2)],{type:'application/json'}));
  a.download='items.json'; a.click();
  toast(`Exported ${clean.length} item(s)`,'success');
});

// Import (server-side, skips what already exists)
document.getElementById('btn-import').addEventListener('change', function () {
  bulkImport(this, {url: BASE+'/api/items/import', key: 'items', label: 'item(s)', batch: 100, done: loadItems});
});

function v(id){const el=document.getElementById(id);return el?el.value.trim():'';}
function set(id,val){const el=document.getElementById(id);if(el)el.value=val??'';}
function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function toast(msg,type=''){
  const c=document.getElementById('toast-container');
  const t=document.createElement('div');t.className='toast'+(type?' toast-'+type:'');t.textContent=msg;
  c.appendChild(t);requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show')));
  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300)},3000);
}
loadItems();
</script>
