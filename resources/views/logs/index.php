<style>
.logs-wrap {
  max-width: 1200px;
  margin: 0 auto;
  padding: 28px 24px 80px;
}
.logs-toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}
.logs-toolbar h1 {
  font-size: 18px;
  font-weight: 700;
  color: var(--ui-text);
  margin: 0;
  margin-right: auto;
}
.logs-toolbar select,
.logs-toolbar input[type=text] {
  height: 34px;
  border: 1px solid var(--ui-input-border);
  border-radius: 5px;
  background: white;
  padding: 0 10px;
  font-size: 13px;
  font-family: var(--font);
  color: var(--ui-text);
  outline: none;
}
.logs-toolbar select:focus,
.logs-toolbar input[type=text]:focus {
  border-color: var(--ui-focus);
  box-shadow: 0 0 0 2px rgba(55,113,200,.1);
}
.logs-toolbar input[type=text] { width: 200px; }

/* Stats bar */
.logs-stats {
  font-size: 12px;
  color: var(--ui-text-muted);
  margin-bottom: 10px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.logs-stats strong { color: var(--ui-text-soft); }

/* Log table */
.log-table-wrap {
  background: #1e2230;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.log-table-head {
  display: grid;
  grid-template-columns: 155px 80px 120px 60px 1fr;
  gap: 0;
  background: #13161f;
  padding: 8px 14px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .6px;
  text-transform: uppercase;
  color: #6272a4;
  font-family: var(--font-mono);
}
.log-lines { max-height: 75vh; overflow-y: auto; }
.log-lines::-webkit-scrollbar { width: 6px; }
.log-lines::-webkit-scrollbar-thumb { background: #44475a; border-radius: 3px; }
.log-line {
  display: grid;
  grid-template-columns: 155px 80px 120px 60px 1fr;
  gap: 0;
  padding: 5px 14px;
  font-size: 12px;
  font-family: var(--font-mono);
  color: #f8f8f2;
  border-top: 1px solid #282c3d;
  line-height: 1.5;
  align-items: start;
}
.log-line:first-child { border-top: none; }
.log-line:hover { background: rgba(255,255,255,.03); }

/* Level colours */
.log-line.level-INFO    { --lc: #50fa7b; }
.log-line.level-WARNING { --lc: #ffb86c; }
.log-line.level-ERROR   { --lc: #ff5555; }
.log-line.level-DEBUG   { --lc: #6272a4; }

.log-ts    { color: #6272a4; font-size: 11px; }
.log-level { color: var(--lc, #f8f8f2); font-weight: 700; font-size: 11px; }
.log-ip    { color: #8be9fd; font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.log-uid   { color: #bd93f9; font-size: 11px; }
.log-body  { color: #f8f8f2; word-break: break-all; }
.log-event { color: #ff79c6; }
.log-ctx   { color: #f1fa8c; font-size: 11px; margin-left: 8px; }

.log-empty {
  text-align: center;
  padding: 48px 20px;
  color: #6272a4;
  font-family: var(--font-mono);
  font-size: 13px;
}

/* File pill list */
.log-files {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.log-file-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: white;
  border: 1px solid var(--ui-border);
  border-radius: 20px;
  padding: 3px 12px;
  font-size: 12px;
  text-decoration: none;
  color: var(--ui-text-soft);
  transition: all .13s;
}
.log-file-pill:hover { border-color: var(--ui-primary); color: var(--ui-primary); }
.log-file-pill.active { background: var(--ui-primary); border-color: var(--ui-primary); color: white; }
.log-file-pill .pill-dl { opacity: .6; margin-left: 2px; }
.log-file-pill .pill-dl:hover { opacity: 1; }

/* Live refresh toggle */
.refresh-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--ui-text-muted);
  cursor: pointer;
  user-select: none;
}
.refresh-badge input { cursor: pointer; }
</style>

<div class="logs-wrap">

  <div class="logs-toolbar">
    <h1><i class="fa-solid fa-terminal"></i> Server Logs</h1>

    <!-- Level filter -->
    <form method="get" id="log-form" style="display:contents">
      <?php if ($activeFile): ?>
        <input type="hidden" name="file" value="<?= e(basename($activeFile)) ?>">
      <?php endif; ?>
      <select name="level" onchange="document.getElementById('log-form').submit()">
        <option value="" <?= $level === '' ? 'selected' : '' ?>>All levels</option>
        <option value="INFO"    <?= $level === 'INFO'    ? 'selected' : '' ?>>INFO</option>
        <option value="WARNING" <?= $level === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
        <option value="ERROR"   <?= $level === 'ERROR'   ? 'selected' : '' ?>>ERROR</option>
        <option value="DEBUG"   <?= $level === 'DEBUG'   ? 'selected' : '' ?>>DEBUG</option>
      </select>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search…" id="log-search">
      <button class="btn btn-ghost btn-sm" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
      <?php if ($level !== '' || $search !== ''): ?>
        <a href="?<?= $activeFile ? 'file=' . urlencode(basename($activeFile)) : '' ?>" class="btn btn-ghost btn-sm">
          <i class="fa-solid fa-xmark"></i> Clear
        </a>
      <?php endif; ?>
    </form>

    <label class="refresh-badge" title="Auto-refresh every 10 seconds">
      <input type="checkbox" id="auto-refresh"> Auto-refresh
    </label>
  </div>

  <!-- File selector pills -->
  <?php if (!empty($files)): ?>
  <div class="log-files">
    <?php foreach ($files as $f): ?>
      <?php $isActive = ($activeFile && basename($f) === basename($activeFile)); ?>
      <a href="?file=<?= urlencode(basename($f)) ?>&level=<?= urlencode($level) ?>&q=<?= urlencode($search) ?>"
         class="log-file-pill <?= $isActive ? 'active' : '' ?>">
        <i class="fa-solid fa-file-lines"></i>
        <?= e(basename($f)) ?>
        <a href="?action=download&file=<?= urlencode(basename($f)) ?>"
           class="pill-dl" title="Download this log file" onclick="event.stopPropagation()">
          <i class="fa-solid fa-download"></i>
        </a>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <?php if ($activeFile): ?>
  <div class="logs-stats">
    <span><i class="fa-solid fa-file-lines"></i> <strong><?= e(basename($activeFile)) ?></strong></span>
    <span><?= $total ?> line(s) matched<?= $total > $perPage ? " — showing latest {$perPage}" : '' ?></span>
    <?php if ($level !== '' || $search !== ''): ?>
      <span><i class="fa-solid fa-filter"></i> Filtered</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Log output -->
  <div class="log-table-wrap">
    <?php if (!empty($lines)): ?>
    <div class="log-table-head">
      <span>Timestamp</span>
      <span>Level</span>
      <span>IP</span>
      <span>UID</span>
      <span>Event / Context</span>
    </div>
    <div class="log-lines" id="log-lines">
      <?php foreach ($lines as $raw): ?>
        <?php
          // Parse: [2026-10-06 14:23:01] [INFO] [1.2.3.4] [uid:3] event.name {"key":"val"}
          $ts = $level_str = $ip = $uid = $event = $ctx = '';
          if (preg_match(
              '/^\[([^\]]+)\] \[([A-Z]+)\] \[([^\]]*)\] \[uid:([^\]]*)\] (\S+)(.*)$/',
              $raw, $m
          )) {
              [, $ts, $level_str, $ip, $uid, $event, $ctx] = $m;
          } else {
              $event = $raw;
          }
          $levelClass = 'level-' . htmlspecialchars($level_str ?: 'INFO');
        ?>
        <div class="log-line <?= $levelClass ?>">
          <span class="log-ts"><?= e($ts) ?></span>
          <span class="log-level"><?= e($level_str) ?></span>
          <span class="log-ip"><?= e($ip) ?></span>
          <span class="log-uid"><?= $uid !== '' ? e($uid) : '—' ?></span>
          <span class="log-body">
            <span class="log-event"><?= e($event) ?></span>
            <?php if (trim($ctx) !== ''): ?>
              <span class="log-ctx"><?= e(trim($ctx)) ?></span>
            <?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <div class="log-empty">
        <?= empty($files) ? '<i class="fa-solid fa-circle-info"></i> No log files found yet. Logs appear after the first app event.' : '<i class="fa-solid fa-circle-info"></i> No matching entries.' ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
// ── Auto-refresh ──
let refreshTimer = null;
document.getElementById('auto-refresh').addEventListener('change', function () {
  clearInterval(refreshTimer);
  if (this.checked) {
    refreshTimer = setInterval(() => location.reload(), 10_000);
  }
});

// ── Search on Enter ──
document.getElementById('log-search').addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('log-form').submit();
});
</script>
