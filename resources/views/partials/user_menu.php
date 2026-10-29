<?php
use App\Core\Auth;
use App\Core\Csrf;

$__u       = Auth::user();
$__compact = !empty($compactUserMenu);
?>
<?php
$__docsPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$__docsActive = str_ends_with(rtrim($__docsPath, '/'), '/documentation');
?>
<?php if ($__u): ?>
  <style>
    .topbar-docs-link { color: white; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 5px; opacity: .85; margin-right: 4px; transition: background .15s, opacity .15s; }
    .topbar-docs-link:hover { opacity: 1; background: rgba(255,255,255,.14); }
    .topbar-docs-link.active { opacity: 1; background: rgba(255,255,255,.2); }
  </style>
  <div class="topbar-user<?= !empty($afterActions) ? ' after-actions' : '' ?>">
    <a href="<?= BASE_URL ?>/documentation" class="topbar-docs-link<?= $__docsActive ? ' active' : '' ?>"
       title="Documentation"<?= $__compact ? ' target="_blank" rel="noopener"' : '' ?>>
      <i class="fa-solid fa-book-open"></i><?php if (!$__compact): ?> Docs<?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/profile" class="topbar-user-link" title="<?= e($__u->email) ?> — your profile">
      <i class="fa-solid fa-circle-user"></i><?php if (!$__compact): ?> <span><?= e($__u->name) ?></span><?php endif; ?>
    </a>
    <form method="post" action="<?= BASE_URL ?>/logout">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-ghost btn-sm" title="Log out">
        <i class="fa-solid fa-right-from-bracket"></i><?php if (!$__compact): ?> Log out<?php endif; ?>
      </button>
    </form>
  </div>
<?php endif; ?>
