<?php
if (!isset($stats) || !is_array($stats)) {
    $stats = [
        'documents' => 0,
        'customers' => 0,
        'items'     => 0,
        'notes'     => 0,
    ];
}
if (!isset($notes) || !is_array($notes)) {
    $notes = [];
}
if (!isset($logFiles) || !is_array($logFiles)) {
    $logFiles = [];
}
?>
<style>
/* ── Settings layout ──────────────────────────────── */
.settings-wrap { max-width: 860px; margin: 0 auto; padding: 28px 24px 80px; }

.settings-header { display: flex; align-items: center; gap: 14px; margin-bottom: 28px; }
.settings-header h1 { font-size: 20px; font-weight: 800; margin: 0; color: var(--ui-text); }

/* Stats bar */
.stats-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 28px; }
.stat-card {
  background: white; border: 1px solid var(--ui-border);
  border-radius: 8px; padding: 16px 18px;
  display: flex; flex-direction: column; gap: 4px;
}
.stat-card .stat-val { font-size: 26px; font-weight: 800; color: var(--ui-primary); }
.stat-card .stat-lbl { font-size: 12px; color: var(--ui-text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }

/* Tabs */
.tab-bar {
  display: flex; gap: 0; border-bottom: 2px solid var(--ui-border);
  margin-bottom: 24px;
}
.tab-btn {
  background: none; border: none; cursor: pointer;
  padding: 10px 20px; font-size: 13.5px; font-weight: 600;
  font-family: var(--font); color: var(--ui-text-muted);
  border-bottom: 2px solid transparent; margin-bottom: -2px;
  transition: color .15s, border-color .15s;
  display: flex; align-items: center; gap: 7px;
}
.tab-btn:hover { color: var(--ui-text); }
.tab-btn.active { color: var(--ui-primary); border-bottom-color: var(--ui-primary); }

.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* Cards */
.settings-card {
  background: white; border: 1px solid var(--ui-border);
  border-radius: 8px; padding: 22px 24px; margin-bottom: 18px;
}
.settings-card h2 { font-size: 14px; font-weight: 700; color: var(--ui-text); margin: 0 0 4px; }
.settings-card p  { font-size: 13px; color: var(--ui-text-muted); margin: 0 0 16px; line-height: 1.5; }

.card-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
.card-row .field { flex: 1; min-width: 200px; }
.card-row .field label { display: block; font-size: 12px; font-weight: 600; color: var(--ui-text-soft); margin-bottom: 5px; }
.card-row .field input {
  width: 100%; box-sizing: border-box;
  border: 1px solid var(--ui-input-border); border-radius: 5px;
  padding: 8px 11px; font-size: 13.5px; font-family: var(--font);
  background: var(--ui-input-bg); color: var(--ui-text);
  transition: border-color .15s; outline: none;
}
.card-row .field input:focus { border-color: var(--ui-focus); box-shadow: 0 0 0 2px rgba(55,113,200,.1); }
.key-hint { font-size: 11.5px; color: var(--ui-text-muted); margin-top: 6px; }

/* Log viewer (mini) */
.log-mini-toolbar { display: flex; gap: 8px; margin-bottom: 10px; align-items: center; }
.log-mini-toolbar select, .log-mini-toolbar input {
  border: 1px solid var(--ui-input-border); border-radius: 5px;
  padding: 6px 10px; font-size: 12.5px; font-family: var(--font);
  background: white; color: var(--ui-text); outline: none;
}
.log-mini-toolbar select:focus, .log-mini-toolbar input:focus { border-color: var(--ui-primary); }
.log-mini-toolbar input { flex: 1; }

.log-box {
  background: #1e2230; border-radius: 7px;
  max-height: 420px; overflow-y: auto; padding: 10px 14px;
  font-family: var(--font-mono); font-size: 12px; line-height: 1.6;
}
.log-box::-webkit-scrollbar { width: 5px; }
.log-box::-webkit-scrollbar-thumb { background: #44475a; border-radius: 3px; }
.log-line { color: #f8f8f2; border-bottom: 1px solid rgba(255,255,255,.04); padding: 2px 0; }
.log-line .lts  { color: #6272a4; }
.log-line .llvl { font-weight: 700; margin: 0 6px; }
.log-line.level-INFO    .llvl { color: #50fa7b; }
.log-line.level-WARNING .llvl { color: #ffb86c; }
.log-line.level-ERROR   .llvl { color: #ff5555; }
.log-line.level-DEBUG   .llvl { color: #6272a4; }
.log-line .lip  { color: #8be9fd; }
.log-line .levt { color: #ff79c6; }
.log-line .lctx { color: #f1fa8c; }
.log-empty-box { background: #1e2230; border-radius: 7px; padding: 32px; text-align: center; color: #6272a4; font-size: 13px; }

/* File pills */
.log-files { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.log-file-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: white; border: 1px solid var(--ui-border); border-radius: 20px;
  padding: 3px 12px; font-size: 12px; text-decoration: none;
  color: var(--ui-text-soft); cursor: pointer;
  transition: all .13s; white-space: nowrap;
}
.log-file-pill:hover, .log-file-pill.active { border-color: var(--ui-primary); color: var(--ui-primary); background: #eef4ff; }
.log-file-pill a { color: inherit; margin-left: 4px; opacity: .6; }
.log-file-pill a:hover { opacity: 1; }

/* Notes */
.note-card {
  background: white; border: 1px solid var(--ui-border);
  border-radius: 8px; margin-bottom: 14px; overflow: hidden;
}
.note-card-head {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 16px 10px; background: var(--ui-input-bg);
  border-bottom: 1px solid var(--ui-border);
}
.note-meta { flex: 1; min-width: 0; }
.note-user { font-weight: 700; font-size: 13px; color: var(--ui-text); }
.note-time { font-size: 12px; color: var(--ui-text-muted); }
.note-url  { font-size: 12px; color: var(--ui-primary); word-break: break-all; }
.note-title { font-size: 13.5px; font-weight: 700; padding: 10px 16px 0; }
.note-body  { padding: 8px 16px 12px; font-size: 13px; color: var(--ui-text-soft); white-space: pre-wrap; word-break: break-word; line-height: 1.65; }
.note-images { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 16px 14px; }
.note-img-thumb { width: 120px; height: 80px; object-fit: cover; border-radius: 4px; border: 1px solid var(--ui-border); cursor: zoom-in; transition: transform .15s; }
.note-img-thumb:hover { transform: scale(1.05); }
.note-del { background: none; border: none; color: var(--ui-text-muted); cursor: pointer; border-radius: 4px; padding: 3px 7px; font-size: 13px; flex-shrink: 0; }
.note-del:hover { background: #fce8e8; color: var(--ui-danger); }
.notes-empty { text-align: center; padding: 40px; color: var(--ui-text-muted); font-size: 13.5px; }
.notes-empty i { font-size: 32px; display: block; margin-bottom: 10px; opacity: .35; }

/* Lightbox */
#st-lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 9000; align-items: center; justify-content: center; cursor: zoom-out; }
#st-lightbox img { max-width: 95vw; max-height: 92vh; border-radius: 6px; }
#st-lightbox-close { position: absolute; top: 18px; right: 22px; background: none; border: none; color: white; font-size: 26px; cursor: pointer; }

/* Import result */
.import-result { border: 1px solid var(--ui-border); border-radius: 6px; overflow: hidden; margin-top: 14px; }
.import-result-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 14px; border-bottom: 1px solid var(--ui-border); font-size: 13px; }
.import-result-row:last-child { border-bottom: none; }
.import-result-row .ir-entity { font-weight: 600; color: var(--ui-text); }
.import-result-row .ir-stats { color: var(--ui-text-muted); }
.ir-ok  { color: var(--ui-success); }
.ir-err { color: var(--ui-danger); }
</style>

<div class="settings-wrap">

  <div class="settings-header">
    <h1><i class="fa-solid fa-gear" style="opacity:.6;margin-right:4px"></i> Settings</h1>
  </div>

  <!-- Stats bar -->
  <div class="stats-bar">
    <div class="stat-card">
      <div class="stat-val"><?= $stats['documents'] ?></div>
      <div class="stat-lbl"><i class="fa-solid fa-file-lines"></i> Documents</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $stats['customers'] ?></div>
      <div class="stat-lbl"><i class="fa-solid fa-users"></i> Customers</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $stats['items'] ?></div>
      <div class="stat-lbl"><i class="fa-solid fa-boxes-stacked"></i> Items</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $stats['notes'] ?></div>
      <div class="stat-lbl"><i class="fa-solid fa-comments"></i> Notes</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <button class="tab-btn active" data-tab="data"><i class="fa-solid fa-database"></i> Data</button>
    <button class="tab-btn" data-tab="logs"><i class="fa-solid fa-terminal"></i> Logs</button>
    <button class="tab-btn" data-tab="notes"><i class="fa-solid fa-comments"></i> Notes <span style="background:var(--ui-primary);color:white;font-size:10px;padding:1px 6px;border-radius:10px;margin-left:2px"><?= count($notes) ?></span></button>
  </div>

  <!-- ── Tab: Data ───────────────────────────────────────────── -->
  <div class="tab-pane active" id="tab-data">

    <!-- Plain export -->
    <div class="settings-card">
      <h2><i class="fa-solid fa-download" style="color:var(--ui-primary)"></i> Export all data</h2>
      <p>Downloads all documents, customers and items as a single JSON file. You can re-import it on any DocEditor instance.</p>
      <button class="btn btn-primary btn-sm" onclick="doExportPlain()"><i class="fa-solid fa-download"></i> Export JSON</button>
    </div>

    <!-- Encrypted export -->
    <div class="settings-card">
      <h2><i class="fa-solid fa-lock" style="color:#7c3aed"></i> Export encrypted data</h2>
      <p>Same export, but the entire payload is encrypted with a passphrase you choose. Only someone with that passphrase can import it. Uses XSalsa20-Poly1305 (libsodium).</p>
      <div class="card-row">
        <div class="field">
          <label><i class="fa-solid fa-key"></i> Passphrase</label>
          <input type="password" id="enc-export-key" placeholder="Enter a strong passphrase…" autocomplete="new-password">
        </div>
        <button class="btn btn-sm" style="background:#7c3aed;color:white;flex-shrink:0" onclick="doExportEncrypted()">
          <i class="fa-solid fa-lock"></i> Export Encrypted
        </button>
      </div>
      <p class="key-hint"><i class="fa-solid fa-triangle-exclamation" style="color:var(--ui-warning)"></i> Keep your passphrase safe — there is no recovery if you forget it.</p>
    </div>

    <!-- Import -->
    <div class="settings-card">
      <h2><i class="fa-solid fa-upload" style="color:var(--ui-success)"></i> Import data</h2>
      <p>Import a previously exported file (plain or encrypted). Existing documents, customers and items are skipped — nothing is overwritten.</p>
      <div class="card-row" style="margin-bottom:10px">
        <div class="field">
          <label><i class="fa-solid fa-file-code"></i> JSON file (plain or encrypted)</label>
          <input type="file" id="import-file" accept=".json,application/json" style="padding:6px 10px">
        </div>
      </div>
      <div class="card-row" id="import-key-row" style="display:none;margin-bottom:10px">
        <div class="field">
          <label><i class="fa-solid fa-key"></i> Passphrase (required for encrypted file)</label>
          <input type="password" id="import-key" placeholder="Enter the passphrase used when exporting…">
        </div>
      </div>
      <button class="btn btn-sm" style="background:var(--ui-success);color:white" onclick="doImport()"><i class="fa-solid fa-file-import"></i> Import</button>
      <div id="import-result" style="display:none" class="import-result"></div>
    </div>

  </div>

  <!-- ── Tab: Logs ───────────────────────────────────────────── -->
  <div class="tab-pane" id="tab-logs">
    <div class="settings-card" style="padding:18px 20px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <h2 style="margin:0"><i class="fa-solid fa-terminal"></i> Server Logs</h2>
        <a href="<?= BASE_URL ?>/logs" class="btn btn-sm" style="background:#eee;color:var(--ui-text);border:1px solid var(--ui-border)"><i class="fa-solid fa-arrow-up-right-from-square"></i> Full log viewer</a>
      </div>

      <?php if (!empty($logFiles)): ?>
      <div class="log-files" id="log-pills">
        <?php foreach ($logFiles as $i => $f): ?>
          <span class="log-file-pill <?= $i === 0 ? 'active' : '' ?>" data-file="<?= e(basename($f)) ?>"
                onclick="loadLogFile('<?= e(basename($f)) ?>', this)">
            <i class="fa-solid fa-file-lines"></i> <?= e(basename($f)) ?>
            <a href="<?= BASE_URL ?>/logs/download?file=<?= urlencode(basename($f)) ?>" title="Download" onclick="event.stopPropagation()">
              <i class="fa-solid fa-download"></i>
            </a>
          </span>
        <?php endforeach; ?>
      </div>

      <div class="log-mini-toolbar">
        <select id="log-level-filter" onchange="filterLogs()">
          <option value="">All levels</option>
          <option value="INFO">INFO</option>
          <option value="WARNING">WARNING</option>
          <option value="ERROR">ERROR</option>
          <option value="DEBUG">DEBUG</option>
        </select>
        <input type="text" id="log-search" placeholder="Search…" oninput="filterLogs()" style="max-width:220px">
        <label style="font-size:12px;color:var(--ui-text-muted);display:flex;align-items:center;gap:5px;cursor:pointer;margin-left:auto">
          <input type="checkbox" id="log-auto-refresh"> Auto-refresh (10s)
        </label>
      </div>
      <div class="log-box" id="log-box"><div style="color:#6272a4;text-align:center;padding:20px">Loading…</div></div>
      <?php else: ?>
      <div class="log-empty-box">No log files found yet. Logs appear after the first app event.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Tab: Notes ──────────────────────────────────────────── -->
  <div class="tab-pane" id="tab-notes">
    <?php if (empty($notes)): ?>
      <div class="notes-empty">
        <i class="fa-regular fa-comment-dots"></i>
        No notes yet. Users can write notes from the <i class="fa-solid fa-pen-to-square"></i> menu on any page.
      </div>
    <?php else: ?>
      <div id="notes-list">
      <?php foreach ($notes as $note): ?>
        <div class="note-card" id="snote-<?= (int)$note['id'] ?>">
          <div class="note-card-head">
            <div class="note-meta">
              <div class="note-user"><i class="fa-solid fa-circle-user" style="opacity:.4;margin-right:4px"></i><?= e($note['user_name'] ?: $note['user_email'] ?: 'Unknown') ?></div>
              <div class="note-time"><i class="fa-regular fa-clock" style="opacity:.4;margin-right:3px"></i><?= e(date('D d M Y · H:i', strtotime($note['created_at']))) ?></div>
              <?php if ($note['url'] !== ''): ?><div class="note-url"><i class="fa-solid fa-link" style="opacity:.5;margin-right:3px"></i><?= e($note['url']) ?></div><?php endif; ?>
            </div>
            <button class="note-del" onclick="deleteNote(<?= (int)$note['id'] ?>)" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
          </div>
          <?php if ($note['title'] !== ''): ?><div class="note-title"><?= e($note['title']) ?></div><?php endif; ?>
          <?php if (trim($note['body']) !== ''): ?><div class="note-body"><?= e($note['body']) ?></div><?php endif; ?>
          <?php if (!empty($note['images'])): ?>
            <div class="note-images">
              <?php foreach ($note['images'] as $idx => $img): ?>
                <img src="<?= e($img) ?>" class="note-img-thumb" alt="Screenshot <?= $idx+1 ?>" onclick="openLightbox(this.src)">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div><!-- /settings-wrap -->

<!-- Lightbox -->
<div id="st-lightbox" onclick="closeLightbox()">
  <button id="st-lightbox-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></button>
  <img id="st-lightbox-img" src="" alt="">
</div>

<script>
// ── Tabs ──────────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    if (btn.dataset.tab === 'logs' && !logLoaded) loadLogFile(firstLogFile);
  });
});

// ── Export: plain ─────────────────────────────────────────────
async function doExportPlain() {
  try {
    toast('Preparing export…');
    const res  = await fetch(`${BASE}/api/settings/export`, {method:'POST', headers:{'X-CSRF-Token': window.__csrf}});
    const json = await res.json();
    if (!json.ok) throw new Error(json.error);
    const date = new Date().toISOString().slice(0,10);
    dlJSON(`doceditor-backup-${date}.json`, json);
    const c = json.counts || {};
    toast(`Exported ${c.documents||0} docs, ${c.customers||0} customers, ${c.items||0} items`, 'success');
  } catch(e) { toast(e.message, 'error'); }
}

// ── Export: encrypted ─────────────────────────────────────────
async function doExportEncrypted() {
  const pass = document.getElementById('enc-export-key').value.trim();
  if (!pass) { toast('Enter a passphrase first', 'error'); document.getElementById('enc-export-key').focus(); return; }
  try {
    toast('Encrypting export…');
    const res  = await fetch(`${BASE}/api/settings/export/encrypted`, {
      method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':window.__csrf},
      body: JSON.stringify({passphrase: pass}),
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error);
    const date = new Date().toISOString().slice(0,10);
    dlJSON(`doceditor-encrypted-${date}.json`, json);
    document.getElementById('enc-export-key').value = '';
    toast('Encrypted export downloaded', 'success');
  } catch(e) { toast(e.message, 'error'); }
}

// ── Import ────────────────────────────────────────────────────
// When a file is chosen, peek at its format to show/hide passphrase field
document.getElementById('import-file').addEventListener('change', async function() {
  const file = this.files[0];
  if (!file) return;
  try {
    const text = await file.text();
    const obj  = JSON.parse(text);
    const isEnc = obj.format === 'doceditor-encrypted';
    document.getElementById('import-key-row').style.display = isEnc ? '' : 'none';
    document.getElementById('import-result').style.display = 'none';
  } catch { /* ignore parse errors here — caught on submit */ }
});

async function doImport() {
  const fileInput = document.getElementById('import-file');
  const file = fileInput.files[0];
  if (!file) { toast('Choose a file first', 'error'); return; }

  let payload;
  try {
    payload = JSON.parse(await file.text());
  } catch {
    toast('Not a valid JSON file', 'error');
    return;
  }

  const passphrase = document.getElementById('import-key').value.trim();
  const isEnc = payload.format === 'doceditor-encrypted';
  if (isEnc && !passphrase) {
    toast('Enter the passphrase for this encrypted file', 'error');
    document.getElementById('import-key').focus();
    return;
  }

  if (!confirm(`Import data from "${file.name}"?\n\nExisting records are skipped — nothing is overwritten.`)) return;

  try {
    toast('Importing…');
    const res  = await fetch(`${BASE}/api/settings/import`, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-Token':window.__csrf},
      body: JSON.stringify({payload, passphrase: passphrase || ''}),
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error);

    renderImportResult(json.results);
    toast(json.summary, 'success');
    fileInput.value = '';
    document.getElementById('import-key').value = '';
    document.getElementById('import-key-row').style.display = 'none';
  } catch(e) { toast(e.message, 'error'); }
}

function renderImportResult(results) {
  const el = document.getElementById('import-result');
  el.style.display = '';
  el.innerHTML = Object.entries(results).map(([entity, r]) => `
    <div class="import-result-row">
      <span class="ir-entity">${entity.charAt(0).toUpperCase()+entity.slice(1)}</span>
      <span class="ir-stats">
        <span class="ir-ok"><i class="fa-solid fa-check"></i> ${r.imported} imported</span>
        &nbsp;·&nbsp; ${r.skipped} skipped
        ${r.failed > 0 ? `&nbsp;·&nbsp;<span class="ir-err"><i class="fa-solid fa-xmark"></i> ${r.failed} failed</span>` : ''}
      </span>
    </div>
  `).join('');
}

// ── Log viewer ────────────────────────────────────────────────
let allLogLines = [];
let logLoaded   = false;
const firstLogFile = <?= !empty($logFiles) ? json_encode(basename($logFiles[0])) : 'null' ?>;

if (firstLogFile) {
  // Pre-load on first tab click
  document.querySelector('[data-tab="logs"]').addEventListener('click', () => {
    if (!logLoaded) loadLogFile(firstLogFile);
  }, {once: true});
}

async function loadLogFile(filename, pillEl = null) {
  logLoaded = true;
  document.querySelectorAll('.log-file-pill').forEach(p => p.classList.toggle('active', p.dataset.file === filename));
  const box = document.getElementById('log-box');
  box.innerHTML = '<div style="color:#6272a4;text-align:center;padding:20px"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>';

  try {
    const res  = await fetch(`${BASE}/logs/download?file=${encodeURIComponent(filename)}`, {headers:{'X-CSRF-Token':window.__csrf}});
    const text = await res.text();
    allLogLines = text.split('\n').filter(Boolean).reverse(); // newest first
    filterLogs();
  } catch(e) {
    box.innerHTML = `<div style="color:#ff5555;padding:20px">Failed to load: ${esc(e.message)}</div>`;
  }
}

function filterLogs() {
  const level  = document.getElementById('log-level-filter')?.value || '';
  const search = (document.getElementById('log-search')?.value || '').toLowerCase();
  const box    = document.getElementById('log-box');
  if (!box) return;

  let lines = allLogLines;
  if (level)  lines = lines.filter(l => l.includes(`[${level}]`));
  if (search) lines = lines.filter(l => l.toLowerCase().includes(search));
  lines = lines.slice(0, 300);

  if (!lines.length) {
    box.innerHTML = '<div style="color:#6272a4;text-align:center;padding:20px">No matching entries.</div>';
    return;
  }

  box.innerHTML = lines.map(raw => {
    let ts='', lv='', ip='', uid='', evt='', ctx='';
    const m = raw.match(/^\[([^\]]+)\] \[([A-Z]+)\] \[([^\]]*)\] \[uid:([^\]]*)\] (\S+)(.*)/);
    if (m) { [,ts,lv,ip,uid,evt,ctx] = m; }
    else evt = raw;
    return `<div class="log-line level-${esc(lv)||'INFO'}"><span class="lts">${esc(ts)}</span><span class="llvl">${esc(lv)}</span><span class="lip">${esc(ip)}</span><span class="levt" style="margin-left:8px">${esc(evt)}</span><span class="lctx">${esc(ctx.trim())}</span></div>`;
  }).join('');
}

let refreshTimer = null;
document.getElementById('log-auto-refresh')?.addEventListener('change', function() {
  clearInterval(refreshTimer);
  if (this.checked) {
    const active = document.querySelector('.log-file-pill.active');
    if (active) refreshTimer = setInterval(() => loadLogFile(active.dataset.file), 10000);
  }
});

// ── Notes ─────────────────────────────────────────────────────
async function deleteNote(id) {
  if (!confirm('Delete this note permanently?')) return;
  const res  = await fetch(`${BASE}/api/settings/notes/${id}/delete`, {method:'POST', headers:{'X-CSRF-Token':window.__csrf}});
  const json = await res.json();
  if (json.ok) { document.getElementById('snote-'+id)?.remove(); toast('Note deleted', 'success'); }
  else toast(json.error||'Failed', 'error');
}

function openLightbox(src) {
  document.getElementById('st-lightbox-img').src = src;
  document.getElementById('st-lightbox').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('st-lightbox').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

// ── Helpers ───────────────────────────────────────────────────
function dlJSON(filename, data) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], {type:'application/json'}));
  a.download = filename;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 2000);
}

function toast(msg, type='') {
  const c = document.getElementById('toast-container');
  if (!c) return;
  const t = document.createElement('div');
  t.className = 'toast' + (type ? ' toast-'+type : '');
  t.textContent = msg;
  c.appendChild(t);
  requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3500);
}

function esc(s) {
  return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function e(s) { return esc(s); }
</script>
