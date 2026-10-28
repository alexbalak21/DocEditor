<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'DocEditor') ?> — DocEditor</title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/favicon.svg">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/editor.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <?php if (!empty($extraCss)): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($extraCss) ?>">
  <?php endif; ?>
  <?php require ROOT . '/resources/views/partials/csrf_js.php'; ?>
</head>
<body>

<?php require ROOT . '/resources/views/partials/topbar.php'; ?>

<div class="page-body">
  <?= $content ?>
</div>

<div class="toast-container" id="toast-container"></div>
</body>
</html>
