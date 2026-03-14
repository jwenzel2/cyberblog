<div class="card" style="max-width:860px; margin:0 auto;">
  <h1>Finish Security Setup</h1>
  <p>Before using the admin area, register at least one passkey and save the generated recovery codes.</p>
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
  status.textContent = 'Preparing passkey registration...';
  const options = await fetch('/admin/security/passkeys/options', { method: 'POST' }).then(r => r.json());
  options.challenge = decodeBase64Url(options.challenge);
  options.user.id = decodeBase64Url(options.user.id);
  options.excludeCredentials = (options.excludeCredentials || []).map(item => ({ ...item, id: decodeBase64Url(item.id) }));
  const credential = await navigator.credentials.create({ publicKey: options });
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

  status.textContent = result.error || 'Unable to register passkey.';
});
</script>
