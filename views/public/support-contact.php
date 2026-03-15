<div class="card" style="max-width:720px; margin:0 auto;">
  <h1>Contact An Administrator</h1>
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="flash danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!$smtpEnabled): ?>
    <p class="muted">The site owner has SMTP notifications disabled, so support email is currently unavailable.</p>
  <?php else: ?>
    <p class="muted">Use this form if your account has been locked and you need an administrator to review it.</p>
  <?php endif; ?>
  <form method="post" action="/support/contact">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <label>Your email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
    <label>Message</label>
    <textarea name="message" style="min-height:160px;" required placeholder="Describe the lockout or issue you need help with."></textarea>
    <button type="submit" <?= !$smtpEnabled ? 'disabled' : '' ?>>Send message</button>
  </form>
</div>
