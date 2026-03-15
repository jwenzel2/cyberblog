<div class="card" style="max-width:540px; margin:0 auto;">
  <h1>MFA Verification</h1>
  <p class="muted">Enter the 6-digit authenticator code for <strong><?= htmlspecialchars($user['email']) ?></strong>.</p>
  <?php if ($error): ?><div class="flash danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/login/mfa">
    <label>Authentication code</label>
    <input name="totp_code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
    <button type="submit">Verify and continue</button>
  </form>
  <p><a href="/login">Back to login</a></p>
</div>
