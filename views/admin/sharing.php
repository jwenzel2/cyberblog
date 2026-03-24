<header class="admin-topbar">
  <div>
    <h1>Sharing</h1>
    <p>Control which social networks appear in the sharing block on public article pages.</p>
  </div>
</header>

<section class="admin-card" style="max-width:760px;">
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <form method="post" action="/admin/sharing">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <div class="page-header">
      <div>
        <h2>Article Sharing</h2>
        <p class="muted">Each sharing service is enabled by default, and you can turn individual networks on or off at any time.</p>
      </div>
    </div>
    <label>
      <input type="checkbox" name="sharing_facebook_enabled" value="1" <?= ($preferences['sharing_facebook_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
      Enable Facebook sharing
    </label>
    <label>
      <input type="checkbox" name="sharing_linkedin_enabled" value="1" <?= ($preferences['sharing_linkedin_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
      Enable LinkedIn sharing
    </label>
    <label>
      <input type="checkbox" name="sharing_x_enabled" value="1" <?= ($preferences['sharing_x_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
      Enable X sharing
    </label>
    <p class="muted">Only enabled services appear under the “Share this:” block on article pages.</p>
    <button type="submit">Save Sharing Settings</button>
  </form>
</section>
