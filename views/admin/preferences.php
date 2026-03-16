<header class="admin-topbar">
  <div>
    <h1>Preferences</h1>
    <p>Configure operational defaults for pagination and outbound notification delivery.</p>
  </div>
</header>

<section class="admin-card" style="max-width:760px;">
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="flash danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/admin/preferences">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <div class="page-header">
      <div>
        <h2>Publishing Defaults</h2>
        <p class="muted">These preferences shape public content listing and admin table pagination.</p>
      </div>
    </div>
    <label>Articles per page</label>
    <input type="number" min="1" max="100" name="articles_per_page" value="<?= htmlspecialchars($preferences['articles_per_page'] ?? '10') ?>" required>
    <p class="muted">Applies to public listings and the admin posts table.</p>
    <label>Site timezone</label>
    <select name="site_timezone">
      <?php $selectedTimezone = $preferences['site_timezone'] ?? (date_default_timezone_get() ?: 'UTC'); ?>
      <?php foreach ($timezones as $timezone): ?>
        <option value="<?= htmlspecialchars($timezone) ?>" <?= $selectedTimezone === $timezone ? 'selected' : '' ?>><?= htmlspecialchars($timezone) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="muted">Used when displaying lockout times and notification timestamps throughout the site.</p>
    <hr>
    <div class="page-header">
      <div>
        <h2>Search Engine Optimization</h2>
        <p class="muted">Control how Google and Bing verify, crawl, and summarize your public site.</p>
      </div>
    </div>
    <label>Site name</label>
    <input name="seo_site_name" value="<?= htmlspecialchars($preferences['seo_site_name'] ?? 'CyberBlog') ?>">
    <label>Default meta description</label>
    <textarea name="seo_default_description" style="min-height:120px;"><?= htmlspecialchars($preferences['seo_default_description'] ?? '') ?></textarea>
    <label><input type="checkbox" name="seo_allow_indexing" value="1" <?= ($preferences['seo_allow_indexing'] ?? '1') === '1' ? 'checked' : '' ?>>Allow Google and Bing to index the public site</label>
    <p class="muted">When disabled, the site emits <code>noindex, nofollow</code> and robots.txt disallows crawling.</p>
    <label>Google site verification</label>
    <input name="seo_google_site_verification" value="<?= htmlspecialchars($preferences['seo_google_site_verification'] ?? '') ?>" placeholder="Paste the token from Google Search Console">
    <label>Bing site verification</label>
    <input name="seo_bing_site_verification" value="<?= htmlspecialchars($preferences['seo_bing_site_verification'] ?? '') ?>" placeholder="Paste the token from Bing Webmaster Tools">
    <p class="muted">Verification tokens are emitted as meta tags on public pages. Sitemap: <code><?= htmlspecialchars(app_url('/sitemap.xml')) ?></code></p>
    <hr>
    <div class="page-header">
      <div>
        <h2>SMTP Notifications</h2>
        <p class="muted">Enable mail delivery for login and lockout notifications.</p>
      </div>
    </div>
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
    <label><input type="checkbox" name="smtp_enabled" value="1" <?= ($preferences['smtp_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>Enable SMTP notifications</label>
    <p class="muted">If SMTP stays disabled, login and lockout notifications will not be sent.</p>
    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
      <button type="submit" name="action" value="save">Save Preferences</button>
      <button type="submit" name="action" value="send_test_mail">Send Test Mail</button>
    </div>
  </form>
</section>
