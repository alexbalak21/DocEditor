<style>
.notes-wrap { max-width: 900px; margin: 0 auto; padding: 28px 24px 80px; }
.notes-toolbar {
  display: flex; align-items: center; gap: 12px; margin-bottom: 24px;
}
.notes-toolbar h1 { font-size: 18px; font-weight: 700; margin: 0; margin-right: auto; }

.note-card {
  background: white;
  border: 1px solid var(--ui-border);
  border-radius: 8px;
  margin-bottom: 18px;
  overflow: hidden;
}
.note-card-head {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 14px 18px 10px;
  border-bottom: 1px solid var(--ui-border);
  background: var(--ui-input-bg);
}
.note-meta { flex: 1; min-width: 0; }
.note-user { font-weight: 700; font-size: 13.5px; color: var(--ui-text); }
.note-time { font-size: 12px; color: var(--ui-text-muted); margin-top: 2px; }
.note-url  {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; color: var(--ui-primary);
  text-decoration: none; margin-top: 4px;
  word-break: break-all;
}
.note-url:hover { text-decoration: underline; }

.note-title {
  font-size: 14px; font-weight: 700; color: var(--ui-text);
  padding: 12px 18px 0;
}
.note-body {
  padding: 10px 18px 14px;
  font-size: 13.5px; color: var(--ui-text-soft); line-height: 1.7;
  white-space: pre-wrap; word-break: break-word;
}
.note-images {
  display: flex; flex-wrap: wrap; gap: 10px;
  padding: 0 18px 16px;
}
.note-img-thumb {
  width: 160px; height: 110px;
  object-fit: cover;
  border-radius: 5px;
  border: 1px solid var(--ui-border);
  cursor: zoom-in;
  transition: transform .15s, box-shadow .15s;
}
.note-img-thumb:hover { transform: scale(1.03); box-shadow: 0 4px 12px rgba(0,0,0,.15); }

.note-del {
  background: none; border: none; padding: 4px 8px; cursor: pointer;
  color: var(--ui-text-muted); border-radius: 4px; font-size: 13px;
  transition: background .13s, color .13s; flex-shrink: 0;
}
.note-del:hover { background: #fce8e8; color: var(--ui-danger); }

/* Empty state */
.notes-empty {
  text-align: center; padding: 64px 20px;
  color: var(--ui-text-muted); font-size: 14px;
}
.notes-empty i { font-size: 36px; display: block; margin-bottom: 14px; opacity: .4; }

/* Lightbox */
#note-lightbox {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.85); z-index: 9000;
  align-items: center; justify-content: center;
  cursor: zoom-out;
}
#note-lightbox img {
  max-width: 95vw; max-height: 92vh;
  border-radius: 6px; box-shadow: 0 8px 40px rgba(0,0,0,.5);
}
#note-lightbox-close {
  position: absolute; top: 18px; right: 22px;
  background: none; border: none; color: white;
  font-size: 28px; cursor: pointer; opacity: .8; line-height: 1;
}
#note-lightbox-close:hover { opacity: 1; }
</style>

<div class="notes-wrap">
  <div class="notes-toolbar">
    <h1><i class="fa-solid fa-comments"></i> Feedback Notes</h1>
    <span style="font-size:13px;color:var(--ui-text-muted)"><?= count($notes) ?> note<?= count($notes) !== 1 ? 's' : '' ?></span>
  </div>

  <?php if (empty($notes)): ?>
    <div class="notes-empty">
      <i class="fa-regular fa-comment-dots"></i>
      No notes yet. Users can write notes from the user menu on any page.
    </div>
  <?php else: ?>
    <?php foreach ($notes as $note): ?>
      <div class="note-card" id="note-<?= (int)$note['id'] ?>">
        <div class="note-card-head">
          <div class="note-meta">
            <div class="note-user">
              <i class="fa-solid fa-circle-user" style="opacity:.5"></i>
              <?= e($note['user_name'] ?: $note['user_email'] ?: 'Unknown') ?>
            </div>
            <div class="note-time">
              <i class="fa-regular fa-clock" style="opacity:.5"></i>
              <?= e(date('D d M Y · H:i', strtotime($note['created_at']))) ?>
            </div>
            <?php if ($note['url'] !== ''): ?>
              <a href="<?= e($note['url']) ?>" class="note-url" target="_blank" rel="noopener">
                <i class="fa-solid fa-link"></i><?= e($note['url']) ?>
              </a>
            <?php endif; ?>
          </div>
          <button class="note-del" title="Delete note" onclick="deleteNote(<?= (int)$note['id'] ?>)">
            <i class="fa-solid fa-trash-can"></i>
          </button>
        </div>

        <?php if ($note['title'] !== ''): ?>
          <div class="note-title"><?= e($note['title']) ?></div>
        <?php endif; ?>

        <?php if (trim($note['body']) !== ''): ?>
          <div class="note-body"><?= e($note['body']) ?></div>
        <?php endif; ?>

        <?php if (!empty($note['images'])): ?>
          <div class="note-images">
            <?php foreach ($note['images'] as $idx => $img): ?>
              <img src="<?= e($img) ?>" class="note-img-thumb"
                   alt="Screenshot <?= $idx + 1 ?>"
                   onclick="openLightbox(this.src)">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Lightbox -->
<div id="note-lightbox" onclick="closeLightbox()">
  <button id="note-lightbox-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></button>
  <img id="note-lightbox-img" src="" alt="Screenshot">
</div>

<!-- Toast container (if not already in layout) -->
<div class="toast-container" id="notes-toast" style="position:fixed;bottom:24px;right:24px;z-index:8000;display:flex;flex-direction:column;gap:8px"></div>

<script>
function openLightbox(src) {
  document.getElementById('note-lightbox-img').src = src;
  document.getElementById('note-lightbox').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('note-lightbox').style.display = 'none';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

async function deleteNote(id) {
  if (!confirm('Delete this note permanently?')) return;
  const res = await fetch(`<?= BASE_URL ?>/api/notes/${id}/delete`, {
    method: 'POST',
    headers: { 'X-CSRF-Token': window.__csrf || '' },
  });
  const json = await res.json();
  if (json.ok) {
    document.getElementById('note-' + id)?.remove();
    showToast('Note deleted');
  }
}

function showToast(msg) {
  const c = document.getElementById('notes-toast');
  const t = document.createElement('div');
  t.className = 'toast toast-success';
  t.textContent = msg;
  c.appendChild(t);
  requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3000);
}
</script>
