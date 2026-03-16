<header class="admin-topbar">
  <div>
    <h1>Finish Security Setup</h1>
    <p>Complete passkey or authenticator enrollment and generate recovery codes before using the administration panel freely.</p>
  </div>
</header>

<section class="admin-card" style="max-width:920px;">
  <p class="muted">Use a real hostname for passkeys, not a raw IP address. Example: <code>https://cyberblog.lan</code> mapped in local DNS or your hosts file.</p>
  <?php if ($freshCodes): ?><div class="flash">Recovery codes: <?= htmlspecialchars($freshCodes) ?></div><?php endif; ?>
  <div class="security-stat-grid">
    <div class="security-stat"><span class="muted">Registered Passkeys</span><strong><?= count($passkeys) ?></strong></div>
    <div class="security-stat"><span class="muted">Authenticator App</span><strong><?= !empty($user['totp_enabled']) ? 'Enabled' : 'Disabled' ?></strong></div>
    <div class="security-stat"><span class="muted">Admin Account</span><strong><?= htmlspecialchars($user['display_name']) ?></strong></div>
  </div>
  <label>Passkey label</label>
  <input id="bootstrap-passkey-label" value="Primary passkey">
  <button type="button" id="bootstrap-register-passkey">Register First Passkey</button>
  <hr>
  <form method="post" action="/admin/security/totp/begin">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <button type="submit">Generate TOTP Secret</button>
  </form>
  <?php if ($pendingTotpSecret): ?>
    <p class="muted">Secret: <code><?= htmlspecialchars($pendingTotpSecret) ?></code></p>
    <p class="muted">OTPAuth URI: <code><?= htmlspecialchars((string) $totpUri) ?></code></p>
    <form method="post" action="/admin/security/totp/verify">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <input name="totp_code" placeholder="123456" required>
      <button type="submit">Verify Authenticator App</button>
    </form>
  <?php endif; ?>
  <hr>
  <form method="post" action="/admin/security/recovery/regenerate">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <button type="submit">Generate Recovery Codes</button>
  </form>
  <p id="bootstrap-status" class="muted"></p>
</section>
<script>
const decodeBase64Url = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=')), c => c.charCodeAt(0));
const encodeBase64Url = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/,'');

document.getElementById('bootstrap-register-passkey')?.addEventListener('click', async () => {
  const status = document.getElementById('bootstrap-status');
  try {
    if (!window.isSecureContext) {
      throw new Error('Passkeys require a secure context (HTTPS or localhost).');
    }

    if (!window.PublicKeyCredential) {
      throw new Error('This browser does not support WebAuthn passkeys.');
    }

    status.textContent = 'Preparing passkey registration...';
    const options = await fetch('/admin/security/passkeys/options', { method: 'POST' }).then(r => r.json());
    if (options.error) {
      throw new Error(options.error);
    }

    options.challenge = decodeBase64Url(options.challenge);
    options.user.id = decodeBase64Url(options.user.id);
    options.excludeCredentials = (options.excludeCredentials || []).map(item => ({ ...item, id: decodeBase64Url(item.id) }));
    const credential = await navigator.credentials.create({ publicKey: options });
    if (!credential) {
      throw new Error('Browser did not return a credential.');
    }

    const result = await fetch('/admin/security/passkeys/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        id: credential.id,
        rawId: encodeBase64Url(credential.rawId),
        type: credential.type,
        label: document.getElementById('bootstrap-passkey-label').value || 'Primary passkey',
        response: {
          clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
          attestationObject: encodeBase64Url(credential.response.attestationObject),
          transports: credential.response.getTransports ? credential.response.getTransports() : []
        }
      })
    }).then(r => r.json());

    if (result.redirect) {
      window.location.href = result.redirect;
      return;
    }

    throw new Error(result.error || 'Unable to register passkey.');
  } catch (error) {
    status.textContent = error?.message || String(error);
  }
});
</script>
