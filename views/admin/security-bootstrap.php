<div class="card" style="max-width:860px; margin:0 auto;">
  <h1>Finish Security Setup</h1>
  <p>Before using the admin area freely, keep a password on the account, then configure either an authenticator app or a passkey, and save recovery codes for emergencies only.</p>
  <p class="muted">Use a real hostname for passkeys, not a raw IP address. Example: `https://cyberblog.lan` mapped to your server in local DNS or your hosts file.</p>
  <?php if ($freshCodes): ?><div class="flash">Recovery codes: <?= htmlspecialchars($freshCodes) ?></div><?php endif; ?>
  <p>Registered passkeys: <strong><?= count($passkeys) ?></strong></p>
  <p>Authenticator app: <strong><?= !empty($user['totp_enabled']) ? 'Enabled' : 'Disabled' ?></strong></p>
  <label>Passkey label</label>
  <input id="bootstrap-passkey-label" value="Primary passkey">
  <button type="button" id="bootstrap-register-passkey">Register first passkey</button>
  <hr style="border-color:#1f3c64; margin:20px 0;">
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
      <button type="submit">Verify authenticator app</button>
    </form>
  <?php endif; ?>
  <hr style="border-color:#1f3c64; margin:20px 0;">
  <form method="post" action="/admin/security/recovery/regenerate">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <button type="submit">Generate Recovery Codes</button>
  </form>
  <p id="bootstrap-status" class="muted"></p>
</div>
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
