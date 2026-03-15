<div class="card" style="max-width:640px;">
  <h1>Preferences</h1>
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <form method="post" action="/admin/preferences">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <label>Articles per page</label>
    <input type="number" min="1" max="100" name="articles_per_page" value="<?= htmlspecialchars($preferences['articles_per_page'] ?? '10') ?>" required>
    <p class="muted">Applies to public listings and the admin posts table.</p>
    <h2>SMTP Notifications</h2>
    <label><input type="checkbox" name="smtp_enabled" value="1" <?= ($preferences['smtp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> Enable SMTP notifications</label>
    <label>SMTP host</label>
    <input name="smtp_host" value="<?= htmlspecialchars($preferences['smtp_host'] ?? '') ?>">
    <label>SMTP port</label>
    <input type="number" min="1" name="smtp_port" value="<?= htmlspecialchars($preferences['smtp_port'] ?? '587') ?>">
    <label>Encryption</label>
    <select name="smtp_encryption">
      <?php foreach (['none' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $value => $label): ?>
        <option value="<?= $value ?>" <?= ($preferences['smtp_encryption'] ?? 'tls') === $value ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <label>SMTP username</label>
    <input name="smtp_username" value="<?= htmlspecialchars($preferences['smtp_username'] ?? '') ?>">
    <label>SMTP password</label>
    <input type="password" name="smtp_password" placeholder="Leave blank to keep the current password">
    <label>From email</label>
    <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($preferences['smtp_from_email'] ?? '') ?>">
    <label>From name</label>
    <input name="smtp_from_name" value="<?= htmlspecialchars($preferences['smtp_from_name'] ?? 'CyberBlog') ?>">
    <p class="muted">If SMTP stays disabled, login and lockout notifications will not be sent.</p>
    <button type="submit">Save preferences</button>
  </form>
</div>
