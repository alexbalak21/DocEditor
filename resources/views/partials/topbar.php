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
];
?>
<header class="topbar">
  <a href="<?= BASE_URL ?>/documents" class="topbar-brand">
    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="3" y="2" width="14" height="16" rx="2" fill="rgba(255,255,255,0.25)" stroke="white" stroke-width="1.5"/>
      <line x1="6" y1="7" x2="14" y2="7" stroke="white" stroke-width="1.2"/>
      <line x1="6" y1="10" x2="14" y2="10" stroke="white" stroke-width="1.2"/>
      <line x1="6" y1="13" x2="11" y2="13" stroke="white" stroke-width="1.2"/>
    </svg>
    DocEditor
    <?php if (!empty($topbarExtra)): ?>
      <span><?= htmlspecialchars($topbarExtra) ?></span>
    <?php endif; ?>
  </a>

  <div class="topbar-divider"></div>

  <nav class="topbar-nav">
    <?php foreach ($nav as $path => $item): ?>
      <a href="<?= BASE_URL . $path ?>"<?= str_starts_with($uri, $path) ? ' class="active"' : '' ?>>
        <i class="fa-solid <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if (!empty($topbarActions)): ?>
    <div class="topbar-actions"><?= $topbarActions ?></div>
  <?php endif; ?>
  <?php require ROOT . '/resources/views/partials/user_menu.php'; ?>
</header>
