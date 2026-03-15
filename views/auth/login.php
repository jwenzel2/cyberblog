<div class="card" style="max-width:720px; margin:0 auto;">
  <h1>Sign In</h1>
  <p class="muted">Use password + MFA or a registered passkey. Recovery codes are reserved for emergency access.</p>
  <?php if ($status): ?><div class="flash"><?= htmlspecialchars($status) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div class="two-col">
    <form method="post" action="/login/password">
      <label>Email</label>
      <input id="login-email" type="email" name="email" value="<?= htmlspecialchars($oldEmail) ?>" required>
      <label>Password</label>
      <input type="password" name="password" autocomplete="current-password" required>
      <button type="submit">Continue with password</button>
    </form>
    <div>
      <label>Email for passkey</label>
      <input id="passkey-email" type="email" value="<?= htmlspecialchars($oldEmail) ?>" required>
      <p class="muted">Passkey login should be used from a hostname rather than a raw IP address.</p>
      <button id="passkey-login" type="button">Use passkey</button>
      <p id="passkey-message" class="muted"></p>
    </div>
  </div>
  <p><a href="/login/recovery">Can't log in?</a></p>
  <p class="muted">Need administrator help? <a href="/support/contact?email=<?= urlencode($oldEmail) ?>">Contact an admin</a>.</p>
</div>
<script>
const decodeBase64Url = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=')), c => c.charCodeAt(0));
const encodeBase64Url = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/,'');

document.getElementById('passkey-login')?.addEventListener('click', async () => {
  const message = document.getElementById('passkey-message');
  const email = document.getElementById('passkey-email')?.value || '';
  try {
    if (!window.isSecureContext) {
      throw new Error('Passkeys require a secure context (HTTPS or localhost).');
    }
    if (!window.PublicKeyCredential) {
      throw new Error('This browser does not support WebAuthn passkeys.');
    }

    message.textContent = 'Waiting for browser passkey prompt...';
    const formData = new FormData();
    formData.set('email', email);
    const options = await fetch('/login/passkey/options', { method: 'POST', body: formData }).then(r => r.json());
    if (options.error) {
      throw new Error(options.error);
    }

    options.challenge = decodeBase64Url(options.challenge);
    options.allowCredentials = (options.allowCredentials || []).map(item => ({ ...item, id: decodeBase64Url(item.id) }));

    const credential = await navigator.credentials.get({ publicKey: options });
    if (!credential) {
      throw new Error('Browser did not return a credential.');
    }

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

    throw new Error(result.error || 'Passkey login failed.');
  } catch (error) {
    message.textContent = error?.message || String(error);
  }
});
</script>
