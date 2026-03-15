<div class="card" style="max-width:620px; margin:0 auto;">
  <h1>Recovery Login</h1>
  <p class="muted">Recovery codes are emergency-only. Use them only when your normal password + MFA or passkey login is unavailable.</p>
  <?php if ($status): ?><div class="flash"><?= htmlspecialchars($status) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/login/recovery">
    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($oldEmail) ?>" required>
    <label>Recovery code</label>
    <input name="recovery_code" placeholder="AB12CD34-EF56" required>
    <button type="submit">Use recovery code</button>
  </form>
  <p><a href="/login">Back to sign in</a></p>
</div>
