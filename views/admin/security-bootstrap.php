<div class="card" style="max-width:860px; margin:0 auto;">
  <h1>Finish Security Setup</h1>
  <p>Before using the admin area, register at least one passkey and save the generated recovery codes.</p>
  <p class="muted">Use a real hostname for passkeys, not a raw IP address. Example: `https://cyberblog.lan` mapped to your server in local DNS or your hosts file.</p>
  <?php if ($freshCodes): ?><div class="flash">Recovery codes: <?= htmlspecialchars($freshCodes) ?></div><?php endif; ?>
  <p>Registered passkeys: <strong><?= count($passkeys) ?></strong></p>
  <label>Passkey label</label>
  <input id="bootstrap-passkey-label" value="Primary passkey">
  <button type="button" id="bootstrap-register-passkey">Register first passkey</button>
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
