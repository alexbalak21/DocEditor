<?php use App\Core\Csrf; ?>
<main class="auth-wrap">
  <form class="auth-card" method="post" action="<?= BASE_URL ?>/login">
    <?= Csrf::field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <div class="auth-brand">
      <svg width="26" height="26" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="3" y="2" width="14" height="16" rx="2" fill="rgba(55,113,200,0.15)" stroke="#3771c8" stroke-width="1.5"/>
        <line x1="6" y1="7" x2="14" y2="7" stroke="#3771c8" stroke-width="1.2"/>
        <line x1="6" y1="10" x2="14" y2="10" stroke="#3771c8" stroke-width="1.2"/>
        <line x1="6" y1="13" x2="11" y2="13" stroke="#3771c8" stroke-width="1.2"/>
      </svg>
      DocEditor
    </div>
    <h1>Sign in</h1>

    <?php if ($error): ?>
      <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="field">
      <label for="email">Email</label>
      <input id="email" type="email" name="email" value="<?= e($email) ?>" required autofocus
             autocomplete="username" maxlength="190">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" type="password" name="password" required autocomplete="current-password">
    </div>

    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#5a6070;margin:2px 0 14px;cursor:pointer">
      <input type="checkbox" name="remember" value="1" checked style="width:15px;height:15px">
      Keep me signed in on this device
    </label>

    <button type="submit" class="btn btn-primary auth-submit">
      <i class="fa-solid fa-right-to-bracket"></i> Sign in
    </button>
  </form>
</main>
