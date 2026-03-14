<div class="card" style="max-width:720px; margin:0 auto;">
  <h1>Admin Login</h1>
  <p class="muted">Use a registered passkey. Recovery codes remain available for emergency access.</p>
  <?php if ($error): ?><div class="flash danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if (!$admin): ?>
    <p>No admin account exists yet. Run the installer first.</p>
  <?php else: ?>
    <p>Admin: <strong><?= htmlspecialchars($admin['email']) ?></strong></p>
    <button id="passkey-login" type="button">Login with passkey</button>
    <p id="passkey-message" class="muted"></p>

    <hr style="border-color:#1f3c64; margin:24px 0;">

    <form method="post" action="/login/recovery">
      <label>Recovery code</label>
      <input name="recovery_code" placeholder="AB12CD34-EF56" required>
      <button type="submit">Use recovery code</button>
    </form>
  <?php endif; ?>
</div>
<script>
const decodeBase64Url = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=')), c => c.charCodeAt(0));
const encodeBase64Url = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/,'');

document.getElementById('passkey-login')?.addEventListener('click', async () => {
  const message = document.getElementById('passkey-message');
  message.textContent = 'Waiting for browser passkey prompt...';

  const options = await fetch('/login/passkey/options', { method: 'POST' }).then(r => r.json());
  if (options.error) {
    message.textContent = options.error;
    return;
  }

  options.challenge = decodeBase64Url(options.challenge);
  options.allowCredentials = (options.allowCredentials || []).map(item => ({ ...item, id: decodeBase64Url(item.id) }));

  const credential = await navigator.credentials.get({ publicKey: options });
  const payload = {
    id: credential.id,
    rawId: encodeBase64Url(credential.rawId),
    type: credential.type,
    response: {
      clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
      authenticatorData: encodeBase64Url(credential.response.authenticatorData),
      signature: encodeBase64Url(credential.response.signature),
      userHandle: credential.response.userHandle ? encodeBase64Url(credential.response.userHandle) : null
    }
  };

  const result = await fetch('/login/passkey/verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  }).then(r => r.json());

  if (result.redirect) {
    window.location.href = result.redirect;
    return;
  }

  message.textContent = result.error || 'Passkey login failed.';
});
</script>
