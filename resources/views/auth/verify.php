<?php use App\Core\Csrf; ?>
<main class="auth-wrap">
  <form class="auth-card" method="post" action="<?= BASE_URL ?>/login/verify" id="verify-form">
    <?= Csrf::field() ?>

    <div class="auth-brand">
      <svg width="26" height="26" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="3" y="2" width="14" height="16" rx="2" fill="rgba(55,113,200,0.15)" stroke="#3771c8" stroke-width="1.5"/>
        <line x1="6" y1="7" x2="14" y2="7" stroke="#3771c8" stroke-width="1.2"/>
        <line x1="6" y1="10" x2="14" y2="10" stroke="#3771c8" stroke-width="1.2"/>
        <line x1="6" y1="13" x2="11" y2="13" stroke="#3771c8" stroke-width="1.2"/>
      </svg>
      DocEditor
    </div>

    <div style="text-align:center;margin-bottom:20px">
      <div style="display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;background:#eef4ff;border-radius:50%;margin-bottom:12px">
        <i class="fa-solid fa-envelope-open-text" style="font-size:22px;color:var(--ui-primary)"></i>
      </div>
      <h1 style="font-size:18px;margin:0 0 6px">Check your email</h1>
      <p style="font-size:13px;color:#5a6070;margin:0;line-height:1.5">
        We sent a 6-digit verification code to your email address.<br>
        It expires in <?= (int) \App\Services\TwoFactorService::lifetimeMinutes() ?> minutes.
      </p>
    </div>

    <?php if ($error): ?>
      <div class="alert <?= str_contains($error, 'new code') ? 'alert-info' : 'alert-error' ?>" role="alert">
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <div class="field">
      <label for="code" style="text-align:center;display:block">Verification code</label>
      <input
        id="code"
        type="text"
        name="code"
        inputmode="numeric"
        pattern="[0-9 ]{6,7}"
        maxlength="7"
        autocomplete="one-time-code"
        autofocus
        required
        placeholder="000 000"
        style="text-align:center;font-size:26px;font-weight:700;letter-spacing:6px;font-family:var(--font-mono);padding:14px"
      >
    </div>

    <button type="submit" class="btn btn-primary auth-submit" style="margin-top:4px">
      <i class="fa-solid fa-check"></i> Verify
    </button>

    <div style="text-align:center;margin-top:14px;display:flex;gap:16px;justify-content:center">
      <form method="post" action="<?= BASE_URL ?>/login/resend" style="margin:0">
        <?= Csrf::field() ?>
        <button type="submit" class="btn-link" style="background:none;border:none;color:var(--ui-primary);font-size:13px;cursor:pointer;text-decoration:underline;font-family:var(--font)">
          <i class="fa-solid fa-rotate-right"></i> Send new code
        </button>
      </form>
      <span style="color:#ddd">|</span>
      <a href="<?= BASE_URL ?>/login" style="font-size:13px;color:#5a6070;text-decoration:none">
        <i class="fa-solid fa-arrow-left"></i> Back to sign in
      </a>
    </div>
  </form>
</main>

<style>
  .alert-info { background:#eef4ff; color:var(--ui-primary); border-left-color:var(--ui-primary); }

  /* Auto-format: strip spaces on submit, format display as user types */
  #code { display:block; width:100%; box-sizing:border-box; }
</style>

<script>
const codeInput = document.getElementById('code');
const verifyForm = document.getElementById('verify-form');
const verifyButton = verifyForm.querySelector('button[type="submit"]');
let verificationSubmitted = false;

// Format as user types: insert space after 3 digits
codeInput.addEventListener('input', function() {
  let val = this.value.replace(/\D/g, '').slice(0, 6);
  this.value = val.length > 3 ? val.slice(0,3) + ' ' + val.slice(3) : val;

  if (val.length === 6 && !verificationSubmitted) {
    verificationSubmitted = true;
    codeInput.readOnly = true;
    verifyButton.disabled = true;
    verifyButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
    verifyForm.requestSubmit();
  }
});

// Strip space before submit
verifyForm.addEventListener('submit', function() {
  codeInput.value = codeInput.value.replace(/\s/g, '');
});
</script>
