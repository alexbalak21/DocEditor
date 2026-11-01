<?php
use App\Core\Auth;
use App\Core\Csrf;

$__u       = Auth::user();
$__compact = !empty($compactUserMenu);
?>
<?php
// Determine active nav item from current URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (BASE_URL !== '' && str_starts_with($uri, BASE_URL)) $uri = substr($uri, strlen(BASE_URL));
if ($uri === '' || $uri === '/') $uri = '/documents';
$nav = [
    '/documents'    => ['icon' => 'fa-file-lines',    'label' => 'Documents'],
    '/customers'    => ['icon' => 'fa-users',          'label' => 'Customers'],
    '/items'        => ['icon' => 'fa-boxes-stacked',  'label' => 'Items'],
    '/logs'         => ['icon' => 'fa-terminal',       'label' => 'Logs'],
    '/notes'        => ['icon' => 'fa-comments',       'label' => 'Notes'],
];
?>

<style>
/* ── User dropdown ───────────────────────────────── */
.user-menu-wrap {
  position: relative;
  display: flex;
  align-items: center;
  gap: 4px;
  margin-left: auto;
}
.user-menu-trigger {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 6px;
  padding: 5px 11px;
  color: white;
  font-size: 13px;
  font-family: var(--font);
  cursor: pointer;
  transition: background .15s;
  white-space: nowrap;
}
.user-menu-trigger:hover,
.user-menu-trigger[aria-expanded="true"] { background: rgba(255,255,255,.22); }
.user-menu-trigger .chevron {
  font-size: 10px;
  opacity: .7;
  transition: transform .2s;
}
.user-menu-trigger[aria-expanded="true"] .chevron { transform: rotate(180deg); }

.user-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  background: white;
  border: 1px solid var(--ui-border);
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,.13);
  min-width: 200px;
  z-index: 4000;
  overflow: hidden;
}
.user-dropdown.open { display: block; }

.dropdown-header {
  padding: 12px 16px 10px;
  border-bottom: 1px solid var(--ui-border);
  background: var(--ui-input-bg);
}
.dropdown-header .dd-name  { font-size: 13.5px; font-weight: 700; color: var(--ui-text); }
.dropdown-header .dd-email { font-size: 12px; color: var(--ui-text-muted); margin-top: 1px; }

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 9px 16px;
  font-size: 13px;
  color: var(--ui-text-soft);
  text-decoration: none;
  cursor: pointer;
  background: none;
  border: none;
  width: 100%;
  text-align: left;
  font-family: var(--font);
  transition: background .1s, color .1s;
}
.dropdown-item:hover { background: #f0f4fc; color: var(--ui-primary); }
.dropdown-item i { width: 16px; text-align: center; opacity: .7; }
.dropdown-item:hover i { opacity: 1; }
.dropdown-item.danger:hover { background: #fce8e8; color: var(--ui-danger); }
.dropdown-divider { border: none; border-top: 1px solid var(--ui-border); margin: 4px 0; }
.dropdown-item .dd-badge {
  margin-left: auto;
  background: var(--ui-primary);
  color: white;
  font-size: 10px;
  font-weight: 700;
  border-radius: 10px;
  padding: 1px 6px;
}

/* ── Note slide panel ────────────────────────────── */
.note-panel-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.35);
  z-index: 5000;
}
.note-panel-overlay.open { display: block; }

.note-panel {
  position: fixed;
  top: 0; right: 0; bottom: 0;
  width: 440px; max-width: 100vw;
  background: white;
  box-shadow: -4px 0 32px rgba(0,0,0,.15);
  z-index: 5001;
  display: flex; flex-direction: column;
  transform: translateX(100%);
  transition: transform .25s cubic-bezier(.4,0,.2,1);
}
.note-panel.open { transform: translateX(0); }

.note-panel-head {
  display: flex; align-items: center;
  padding: 16px 18px;
  border-bottom: 1px solid var(--ui-border);
  flex-shrink: 0;
}
.note-panel-head h2 {
  font-size: 15px; font-weight: 700;
  color: var(--ui-text); margin: 0; flex: 1;
}
.note-panel-close {
  background: none; border: none; padding: 4px 8px;
  color: var(--ui-text-muted); cursor: pointer; font-size: 16px;
  border-radius: 4px; transition: background .13s;
}
.note-panel-close:hover { background: var(--ui-input-bg); color: var(--ui-text); }

.note-panel-body { flex: 1; overflow-y: auto; padding: 18px; display: flex; flex-direction: column; gap: 14px; }

.note-panel-url {
  background: var(--ui-input-bg);
  border: 1px solid var(--ui-border);
  border-radius: 5px;
  padding: 7px 11px;
  font-size: 12px;
  color: var(--ui-text-muted);
  font-family: var(--font-mono);
  word-break: break-all;
}
.note-field label {
  display: block;
  font-size: 12px; font-weight: 600;
  color: var(--ui-text-soft); margin-bottom: 5px;
}
.note-field input[type=text],
.note-field textarea {
  width: 100%; box-sizing: border-box;
  border: 1px solid var(--ui-input-border);
  border-radius: 5px;
  padding: 8px 11px;
  font-size: 13.5px;
  font-family: var(--font);
  color: var(--ui-text);
  background: var(--ui-input-bg);
  resize: vertical;
  transition: border-color .15s, box-shadow .15s;
}
.note-field input[type=text]:focus,
.note-field textarea:focus {
  outline: none;
  border-color: var(--ui-focus);
  box-shadow: 0 0 0 2px rgba(55,113,200,.1);
}
.note-field textarea { min-height: 120px; }

/* Image drop zone */
.note-drop-zone {
  border: 2px dashed var(--ui-border);
  border-radius: 6px;
  padding: 18px;
  text-align: center;
  color: var(--ui-text-muted);
  font-size: 13px;
  cursor: pointer;
  transition: border-color .15s, background .15s;
}
.note-drop-zone:hover,
.note-drop-zone.drag-over { border-color: var(--ui-primary); background: #eef4ff; color: var(--ui-primary); }
.note-drop-zone i { font-size: 22px; display: block; margin-bottom: 6px; }

.note-thumbs { display: flex; flex-wrap: wrap; gap: 8px; }
.note-thumb-wrap { position: relative; }
.note-thumb-wrap img {
  width: 88px; height: 66px; object-fit: cover;
  border-radius: 4px; border: 1px solid var(--ui-border); display: block;
}
.note-thumb-del {
  position: absolute; top: -5px; right: -5px;
  background: var(--ui-danger); color: white;
  border: none; border-radius: 50%;
  width: 18px; height: 18px; font-size: 10px;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  line-height: 1;
}

.note-panel-foot {
  padding: 14px 18px;
  border-top: 1px solid var(--ui-border);
  display: flex; gap: 10px; flex-shrink: 0;
}
.note-submit {
  flex: 1;
  background: var(--ui-primary); color: white;
  border: none; border-radius: 5px;
  padding: 9px 16px; font-size: 13.5px; font-weight: 600;
  cursor: pointer; font-family: var(--font);
  transition: background .15s;
}
.note-submit:hover { background: var(--ui-primary-hover); }
.note-submit:disabled { opacity: .5; cursor: not-allowed; }
.note-cancel {
  background: var(--ui-input-bg); color: var(--ui-text);
  border: 1px solid var(--ui-border); border-radius: 5px;
  padding: 9px 14px; font-size: 13.5px; cursor: pointer;
  font-family: var(--font); transition: background .13s;
}
.note-cancel:hover { background: #e8eaf0; }

/* Toast (for note panel) */
.note-toast {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: #1a7a4a; color: white;
  padding: 10px 20px; border-radius: 6px;
  font-size: 13.5px; font-weight: 600;
  z-index: 6000; opacity: 0;
  transition: opacity .25s;
  pointer-events: none;
}
.note-toast.show { opacity: 1; }
</style>

<?php if ($__u): ?>
  <div class="user-menu-wrap">
    <button class="user-menu-trigger" id="user-menu-btn" aria-expanded="false" onclick="toggleUserMenu()">
      <i class="fa-solid fa-circle-user"></i>
      <?php if (!$__compact): ?>
        <span><?= e($__u->name) ?></span>
      <?php endif; ?>
      <i class="fa-solid fa-chevron-down chevron"></i>
    </button>

    <div class="user-dropdown" id="user-dropdown" role="menu">
      <div class="dropdown-header">
        <div class="dd-name"><?= e($__u->name) ?></div>
        <div class="dd-email"><?= e($__u->email) ?></div>
      </div>

      <a href="<?= BASE_URL ?>/profile" class="dropdown-item">
        <i class="fa-solid fa-user-pen"></i> My Profile
      </a>
      <a href="<?= BASE_URL ?>/documentation" class="dropdown-item">
        <i class="fa-solid fa-book-open"></i> Documentation
      </a>
      <a href="<?= BASE_URL ?>/logs" class="dropdown-item">
        <i class="fa-solid fa-terminal"></i> Logs
      </a>
      <a href="<?= BASE_URL ?>/notes" class="dropdown-item">
        <i class="fa-solid fa-comments"></i> All Notes
        <span class="dd-badge">Admin</span>
      </a>

      <div class="dropdown-divider"></div>

      <button class="dropdown-item" onclick="openNotePanel()">
        <i class="fa-solid fa-pen-to-square"></i> Write a note
      </button>

      <div class="dropdown-divider"></div>

      <form method="post" action="<?= BASE_URL ?>/logout" style="margin:0">
        <?= Csrf::field() ?>
        <button type="submit" class="dropdown-item danger">
          <i class="fa-solid fa-right-from-bracket"></i> Log out
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($__u): ?>
<!-- ── Note slide-in panel ──────────────────────── -->
<div class="note-panel-overlay" id="note-overlay" onclick="closeNotePanel()"></div>
<aside class="note-panel" id="note-panel" role="dialog" aria-label="Write a note">
  <div class="note-panel-head">
    <h2><i class="fa-solid fa-pen-to-square" style="opacity:.6;margin-right:6px"></i>Write a note</h2>
    <button class="note-panel-close" onclick="closeNotePanel()"><i class="fa-solid fa-xmark"></i></button>
  </div>

  <div class="note-panel-body">
    <!-- Current page -->
    <div>
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--ui-text-muted);margin-bottom:5px">
        <i class="fa-solid fa-link"></i> Page
      </div>
      <div class="note-panel-url" id="note-current-url"></div>
    </div>

    <!-- Title -->
    <div class="note-field">
      <label for="note-title"><i class="fa-solid fa-heading"></i> Title <span style="font-weight:400;opacity:.6">(optional)</span></label>
      <input type="text" id="note-title" placeholder="Short summary of the issue or idea…" maxlength="255">
    </div>

    <!-- Body -->
    <div class="note-field">
      <label for="note-body"><i class="fa-solid fa-align-left"></i> Note</label>
      <textarea id="note-body" placeholder="Describe the issue, idea, or improvement…"></textarea>
    </div>

    <!-- Images -->
    <div class="note-field">
      <label><i class="fa-solid fa-image"></i> Screenshots <span style="font-weight:400;opacity:.6">(paste, drag & drop, or click)</span></label>
      <div class="note-drop-zone" id="note-drop-zone" onclick="document.getElementById('note-file-input').click()">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        Drag & drop or click to add screenshots<br>
        <span style="font-size:11px;opacity:.7">Or just paste with Ctrl+V / ⌘+V anywhere in this panel</span>
      </div>
      <input type="file" id="note-file-input" accept="image/*" multiple style="display:none">
      <div class="note-thumbs" id="note-thumbs" style="margin-top:10px"></div>
    </div>
  </div>

  <div class="note-panel-foot">
    <button class="note-cancel" onclick="closeNotePanel()">Cancel</button>
    <button class="note-submit" id="note-submit-btn" onclick="submitNote()">
      <i class="fa-solid fa-paper-plane"></i> Send note
    </button>
  </div>
</aside>

<div class="note-toast" id="note-toast"></div>

<script>
(function () {
  // ── Dropdown ──────────────────────────────────────────
  window.toggleUserMenu = function () {
    const btn = document.getElementById('user-menu-btn');
    const dd  = document.getElementById('user-dropdown');
    const open = dd.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
  };
  document.addEventListener('click', function (e) {
    const wrap = document.querySelector('.user-menu-wrap');
    if (wrap && !wrap.contains(e.target)) {
      document.getElementById('user-dropdown')?.classList.remove('open');
      document.getElementById('user-menu-btn')?.setAttribute('aria-expanded', 'false');
    }
  });

  // ── Note panel ────────────────────────────────────────
  let noteImages = []; // array of base64 data URIs

  window.openNotePanel = function () {
    document.getElementById('user-dropdown')?.classList.remove('open');
    document.getElementById('note-current-url').textContent = window.location.href;
    document.getElementById('note-overlay').classList.add('open');
    document.getElementById('note-panel').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('note-title')?.focus(), 280);
  };

  window.closeNotePanel = function () {
    document.getElementById('note-overlay').classList.remove('open');
    document.getElementById('note-panel').classList.remove('open');
    document.body.style.overflow = '';
  };

  // Close on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeNotePanel();
  });

  // ── Image handling ──────────────────────────────────
  function addImage(file) {
    if (!file.type.startsWith('image/')) return;
    if (noteImages.length >= 10) { notePanelToast('Maximum 10 images', true); return; }
    const reader = new FileReader();
    reader.onload = function (ev) {
      noteImages.push(ev.target.result);
      renderThumbs();
    };
    reader.readAsDataURL(file);
  }

  function renderThumbs() {
    const container = document.getElementById('note-thumbs');
    container.innerHTML = '';
    noteImages.forEach((src, i) => {
      const wrap = document.createElement('div');
      wrap.className = 'note-thumb-wrap';
      const img = document.createElement('img');
      img.src = src; img.alt = 'Screenshot ' + (i + 1);
      const del = document.createElement('button');
      del.className = 'note-thumb-del';
      del.title = 'Remove';
      del.innerHTML = '<i class="fa-solid fa-xmark"></i>';
      del.onclick = function (e) { e.stopPropagation(); noteImages.splice(i, 1); renderThumbs(); };
      wrap.appendChild(img); wrap.appendChild(del);
      container.appendChild(wrap);
    });
  }

  // File input
  document.getElementById('note-file-input').addEventListener('change', function () {
    Array.from(this.files).forEach(addImage);
    this.value = '';
  });

  // Drag & drop on drop zone
  const dropZone = document.getElementById('note-drop-zone');
  dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
  dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('drag-over');
    Array.from(e.dataTransfer.files).forEach(addImage);
  });

  // Paste anywhere in the panel
  document.getElementById('note-panel').addEventListener('paste', function (e) {
    const items = e.clipboardData?.items || [];
    let added = false;
    for (const item of items) {
      if (item.type.startsWith('image/')) {
        addImage(item.getAsFile());
        added = true;
      }
    }
    if (added) e.preventDefault();
  });

  // ── Submit ───────────────────────────────────────────
  window.submitNote = async function () {
    const body  = document.getElementById('note-body').value.trim();
    const title = document.getElementById('note-title').value.trim();
    if (body === '' && noteImages.length === 0) {
      notePanelToast('Please write something or add a screenshot.', true);
      return;
    }
    const btn = document.getElementById('note-submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending…';

    try {
      const res = await fetch('<?= BASE_URL ?>/api/notes', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.__csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({
          url:    window.location.href,
          title:  title,
          body:   body,
          images: noteImages,
        }),
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Server error');

      // Reset form
      document.getElementById('note-title').value = '';
      document.getElementById('note-body').value  = '';
      noteImages = [];
      renderThumbs();

      closeNotePanel();
      notePanelToast('Note sent — thank you!');
    } catch (err) {
      notePanelToast('Failed to send: ' + err.message, true);
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send note';
    }
  };

  // ── Toast ────────────────────────────────────────────
  function notePanelToast(msg, isError = false) {
    const t = document.getElementById('note-toast');
    t.textContent = msg;
    t.style.background = isError ? '#c0392b' : '#1a7a4a';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
  }
})();
</script>
<?php endif; ?>
