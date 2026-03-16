<header class="admin-topbar">
  <div>
    <h1>Security</h1>
    <p>Operate your admin account with passkeys, TOTP, and recovery material so the control panel stays accessible and hardened.</p>
  </div>
</header>

<div class="security-stat-grid">
  <div class="security-stat"><span class="muted">Registered Passkeys</span><strong><?= count($passkeys) ?></strong></div>
  <div class="security-stat"><span class="muted">Unused Recovery Codes</span><strong><?= (int) $recoveryCount ?></strong></div>
  <div class="security-stat"><span class="muted">Authenticator App</span><strong><?= !empty($user['totp_enabled']) ? 'Enabled' : 'Disabled' ?></strong></div>
</div>

<div class="security-grid">
  <section class="admin-card">
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($freshCodes): ?><div class="flash">New recovery codes: <?= htmlspecialchars($freshCodes) ?></div><?php endif; ?>
    <div class="page-header">
      <div>
        <h2>Passkey Inventory</h2>
        <p class="muted">Review enrolled devices attached to the administrator account.</p>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table>
        <thead><tr><th>Label</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($passkeys as $passkey): ?>
          <tr>
            <td><?= htmlspecialchars($passkey['label']) ?></td>
            <td><?= htmlspecialchars($passkey['created_at']) ?></td>
            <td>
              <form method="post" action="/admin/security/passkeys/delete">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <input type="hidden" name="passkey_id" value="<?= (int) $passkey['id'] ?>">
                <button type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <aside class="admin-aside-stack">
    <section class="admin-card">
      <div class="page-header">
        <div>
          <h2>Add Passkey</h2>
          <p class="muted">Passkeys should be used from a hostname, not a raw IP address.</p>
        </div>
      </div>
      <label>Device label</label>
      <input id="passkey-label" value="Primary passkey">
      <button type="button" id="register-passkey">Register Passkey</button>
      <p id="passkey-status" class="muted"></p>
    </section>

    <section class="admin-card">
      <div class="page-header">
        <div>
          <h2>Authenticator App</h2>
          <p class="muted">Use a TOTP factor in addition to your password when passkeys are not available.</p>
        </div>
      </div>
      <?php if (empty($user['totp_enabled'])): ?>
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
      <?php else: ?>
        <form method="post" action="/admin/security/totp/disable">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
          <button type="submit">Disable Authenticator App</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="admin-card">
      <div class="page-header">
        <div>
          <h2>Recovery Codes</h2>
          <p class="muted">Generate emergency access codes and store them offline.</p>
        </div>
      </div>
      <form method="post" action="/admin/security/recovery/regenerate">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <button type="submit">Regenerate Recovery Codes</button>
      </form>
    </section>
  </aside>
</div>
<script>
const decodeBase64Url = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=')), c => c.charCodeAt(0));
const encodeBase64Url = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/,'');

document.getElementById('register-passkey')?.addEventListener('click', async () => {
  const status = document.getElementById('passkey-status');
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
        label: document.getElementById('passkey-label').value || 'Passkey',
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
