<?php /** Defines BASE and makes every fetch() POST carry the CSRF token (retrying once if it went stale). */ ?>
<meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
<script>
const BASE = <?= json_encode(BASE_URL) ?>;
(function () {
  const meta        = document.querySelector('meta[name="csrf-token"]');
  const nativeFetch = window.fetch.bind(window);

  async function refreshToken() {
    try {
      const r = await nativeFetch(BASE + '/api/csrf', { credentials: 'same-origin' });
      if (!r.ok) return false;
      meta.content = (await r.json()).token;
      return true;
    } catch (e) { return false; }
  }

  function showSessionBanner() {
    if (document.getElementById('session-banner')) return;
    const b = document.createElement('div');
    b.id = 'session-banner';
    b.className = 'session-banner';
    b.innerHTML = 'Your session has expired. <a href="' + BASE + '/login" target="_blank" rel="noopener">Sign in again in a new tab</a>, then retry — your work on this page is kept.';
    document.body.appendChild(b);
  }

  // ── Keep-alive: renews the session (and the device key) in the background ──────────────────
  // Every 4 minutes while the tab is visible, and as soon as the tab wakes up (laptop opened,
  // tab focused, network back). If the PHP session was lost, the server restores it from the
  // remember-me cookie and hands back a fresh CSRF token, so nothing visible happens.
  let lastPing = Date.now(), pinging = false;
  async function ping() {
    if (pinging) return;
    pinging = true;
    try {
      const r = await nativeFetch(BASE + '/api/session/ping', { credentials: 'same-origin', cache: 'no-store' });
      if (r.status === 401) { showSessionBanner(); return; }
      if (r.ok) {
        const token = (await r.json()).token;
        meta.content = token;
        document.querySelectorAll('input[name="_token"]').forEach(i => { i.value = token; });
        const b = document.getElementById('session-banner'); if (b) b.remove();
        lastPing = Date.now();
      }
    } catch (e) { /* offline: try again on the next tick */ }
    finally { pinging = false; }
  }
  setInterval(() => { if (!document.hidden) ping(); }, 4 * 60 * 1000);
  const wake = () => { if (!document.hidden && Date.now() - lastPing > 60 * 1000) ping(); };
  document.addEventListener('visibilitychange', wake);
  window.addEventListener('focus', wake);
  window.addEventListener('online', wake);

  window.fetch = async function (input, init) {
    init = init || {};
    const method = (init.method || (input && input.method) || 'GET').toUpperCase();
    if (method === 'GET' || method === 'HEAD') return nativeFetch(input, init);

    const send = () => {
      const headers = new Headers(init.headers || {});
      headers.set('X-CSRF-Token', meta.content);
      return nativeFetch(input, Object.assign({}, init, { headers, credentials: 'same-origin' }));
    };

    let res = await send();
    if (res.status === 419 && await refreshToken()) res = await send();   // token went stale (e.g. re-login in another tab)
    if (res.status === 401) showSessionBanner();
    return res;
  };
})();
</script>
