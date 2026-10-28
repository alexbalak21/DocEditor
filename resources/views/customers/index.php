<style>html,body{height:auto;overflow:auto}</style>
<?php require ROOT . '/resources/views/partials/bulk_io.php'; ?>

<div class="history-layout">
  <div class="history-topbar">
    <h1>Customers</h1>
    <div class="search-bar">
      <input type="text" id="search-input" placeholder="Search name, city, contact…" oninput="loadCustomers()">
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-ghost btn-sm" id="btn-export"><i class="fa-solid fa-download"></i> Export</button>
      <label class="btn btn-ghost btn-sm" style="cursor:pointer"><i class="fa-solid fa-upload"></i> Import
        <input type="file" id="btn-import" accept=".json" style="display:none">
      </label>
      <button class="btn btn-primary btn-sm" onclick="openForm()"><i class="fa-solid fa-plus"></i> New Customer</button>
    </div>
  </div>

  <!-- Add / Edit form -->
  <div class="item-form-card" id="cust-form-card" style="display:none">
    <h2 id="form-title"><i class="fa-solid fa-user"></i> New Customer</h2>
    <input type="hidden" id="edit-id">
    <div class="item-form-grid">
      <div class="field full"><label>Company / Name *</label><input id="fc-name" type="text" placeholder="Pureture Co."></div>
      <div class="field full"><label>Address</label><input id="fc-addr" type="text" placeholder="4F, 2121-3 Nambusunhwan-ro"></div>
      <div class="field full"><label>City / Country</label><input id="fc-city" type="text" placeholder="06725 Seocho-gu, Seoul — Republic of Korea"></div>
      <div class="field third"><label>Contact Person</label><input id="fc-contact" type="text" placeholder="Sohee Yoon"></div>
      <div class="field third"><label>Phone</label><input id="fc-phone" type="text" placeholder="+82-10-6681-1162"></div>
      <div class="field third"><label>VAT Number</label><input id="fc-vat" type="text" placeholder="Optional"></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:18px">
      <button class="btn btn-primary btn-sm" onclick="saveCustomer()" style="background:var(--ui-primary);color:white">
        <i class="fa-solid fa-floppy-disk"></i> Save
      </button>
      <button class="btn btn-ghost btn-sm" onclick="closeForm()" style="background:#eee;color:#1a1d23;border:none">Cancel</button>
    </div>
  </div>

  <div id="cust-list">
    <div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-users"></i></div><h2>Loading…</h2></div>
  </div>
</div>

<!-- Delete modal -->
<div id="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:10px;padding:28px 32px;max-width:380px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.18)">
    <h3 style="margin-bottom:10px;font-size:17px">Delete Customer</h3>
    <p style="color:#5a6070;font-size:14px;margin-bottom:22px" id="modal-body"></p>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button class="btn btn-ghost btn-sm" onclick="closeModal()" style="color:#1a1d23;background:#eee;border:none">Cancel</button>
      <button class="btn btn-danger btn-sm" id="modal-confirm">Delete</button>
    </div>
  </div>
</div>

<script>
async function loadCustomers() {
  const q = document.getElementById('search-input').value.trim();
  try {
    const json = await fetch(`${BASE}/api/customers?q=${encodeURIComponent(q)}`).then(r => r.json());
    if (!json.ok) throw new Error(json.error);
    renderList(json.customers);
  } catch(e) {
    document.getElementById('cust-list').innerHTML =
      `<div class="empty-state"><div class="empty-icon">⚠️</div><h2>Error</h2><p>${esc(e.message)}</p></div>`;
  }
}

function renderList(items) {
  const el = document.getElementById('cust-list');
  if (!items.length) {
    el.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-users"></i></div>
      <h2>No customers yet</h2><p>Customers are also created automatically when you save a document with a Bill To name.</p></div>`;
    return;
  }
  el.innerHTML = `<table class="doc-table">
    <thead><tr><th>Name</th><th>City</th><th>Contact</th><th>Phone</th><th>VAT</th><th>Actions</th></tr></thead>
    <tbody>${items.map(c => `<tr>
      <td style="font-weight:600">${esc(c.name)}</td>
      <td>${esc(c.city||'—')}</td>
      <td>${esc(c.contact||'—')}</td>
      <td>${esc(c.phone||'—')}</td>
      <td><span style="font-family:monospace;font-size:12px">${esc(c.vat||'—')}</span></td>
      <td><div class="action-btns">
        <button class="btn btn-ghost btn-sm" style="background:#eee;color:#1a1d23;border:none" onclick="editCustomer(${c.id})"><i class="fa-solid fa-pen"></i> Edit</button>
        <button class="btn btn-danger btn-sm" onclick="confirmDelete(${c.id},'${esc(c.name)}')"><i class="fa-solid fa-trash"></i> Delete</button>
      </div></td>
    </tr>`).join('')}</tbody></table>`;
}

function openForm(id=null) {
  const card = document.getElementById('cust-form-card');
  card.style.display = 'block';
  document.getElementById('edit-id').value = id ?? '';
  document.getElementById('form-title').innerHTML = id
    ? '<i class="fa-solid fa-pen"></i> Edit Customer'
    : '<i class="fa-solid fa-user"></i> New Customer';
  if (!id) ['name','addr','city','contact','phone','vat'].forEach(f => { const el = document.getElementById('fc-'+f); if(el) el.value=''; });
  document.getElementById('fc-name').focus();
  card.scrollIntoView({ behavior: 'smooth' });
}
function closeForm() { document.getElementById('cust-form-card').style.display='none'; document.getElementById('edit-id').value=''; }

async function editCustomer(id) {
  const json = await fetch(`${BASE}/api/customers/${id}`).then(r=>r.json());
  if (!json.ok) { toast('Error: '+json.error,'error'); return; }
  const c = json.customer;
  openForm(id);
  set('fc-name',c.name); set('fc-addr',c.address); set('fc-city',c.city);
  set('fc-contact',c.contact); set('fc-phone',c.phone); set('fc-vat',c.vat);
}

async function saveCustomer() {
  const id   = document.getElementById('edit-id').value;
  const name = document.getElementById('fc-name').value.trim();
  if (!name) { toast('Name is required','error'); return; }
  const body = { name, address:v('fc-addr'), city:v('fc-city'), contact:v('fc-contact'), phone:v('fc-phone'), vat:v('fc-vat') };
  const url  = id ? `${BASE}/api/customers/${id}` : BASE+'/api/customers';
  const json = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(r=>r.json());
  if (!json.ok) { toast('Error: '+json.error,'error'); return; }
  toast(id ? 'Customer updated' : 'Customer created', 'success');
  closeForm(); loadCustomers();
}

let pendingId = null;
function confirmDelete(id, name) {
  pendingId = id;
  document.getElementById('modal-body').textContent = `Delete "${name}"? This cannot be undone.`;
  document.getElementById('modal-overlay').style.display = 'flex';
  document.getElementById('modal-confirm').onclick = doDelete;
}
function closeModal() { document.getElementById('modal-overlay').style.display='none'; pendingId=null; }
async function doDelete() {
  const id = pendingId; closeModal();
  const json = await fetch(`${BASE}/api/customers/${id}/delete`,{method:'POST'}).then(r=>r.json());
  if (!json.ok) { toast('Error: '+json.error,'error'); return; }
  toast('Deleted','success'); loadCustomers();
}
document.getElementById('modal-overlay').addEventListener('click',function(e){if(e.target===this)closeModal();});

// Export
document.getElementById('btn-export').addEventListener('click', async () => {
  const json = await fetch(BASE+'/api/customers').then(r=>r.json());
  if (!json.ok) { toast('Export failed','error'); return; }
  const clean = json.customers.map(({id,created_at,updated_at,...c})=>c);
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([JSON.stringify(clean,null,2)],{type:'application/json'}));
  a.download = 'customers.json'; a.click();
  toast(`Exported ${clean.length} customer(s)`,'success');
});

// Import (server-side, skips what already exists)
document.getElementById('btn-import').addEventListener('change', function () {
  bulkImport(this, {url: BASE+'/api/customers/import', key: 'customers', label: 'customer(s)', batch: 100, done: loadCustomers});
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
loadCustomers();
</script>
