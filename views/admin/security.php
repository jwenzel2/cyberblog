<div class="grid">
  <section class="card">
    <h1>Security</h1>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($freshCodes): ?><div class="flash">New recovery codes: <?= htmlspecialchars($freshCodes) ?></div><?php endif; ?>
    <p>Registered passkeys: <strong><?= count($passkeys) ?></strong></p>
    <p>Unused recovery codes: <strong><?= (int) $recoveryCount ?></strong></p>
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
  </section>
  <aside class="card">
    <h2>Add Passkey</h2>
    <label>Device label</label>
    <input id="passkey-label" value="Primary passkey">
    <button type="button" id="register-passkey">Register passkey</button>
    <p id="passkey-status" class="muted"></p>
    <hr style="border-color:#1f3c64; margin:20px 0;">
    <form method="post" action="/admin/security/recovery/regenerate">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <button type="submit">Regenerate Recovery Codes</button>
    </form>
  </aside>
</div>
<script>
const decodeBase64Url = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(value.length / 4) * 4, '=')), c => c.charCodeAt(0));
const encodeBase64Url = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/,'');

document.getElementById('register-passkey')?.addEventListener('click', async () => {
  const status = document.getElementById('passkey-status');
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

  status.textContent = result.error || 'Unable to register passkey.';
});
</script>
