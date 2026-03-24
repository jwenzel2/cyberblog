<header class="admin-topbar">
  <div>
    <h1>Sharing</h1>
    <p>Control whether public articles expose social share links for Facebook and LinkedIn.</p>
  </div>
</header>

<section class="admin-card" style="max-width:760px;">
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <form method="post" action="/admin/sharing">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <div class="page-header">
      <div>
        <h2>Article Sharing</h2>
        <p class="muted">Enabled by default. When active, published article pages show Facebook and LinkedIn share actions.</p>
      </div>
    </div>
    <label>
      <input type="checkbox" name="sharing_enabled" value="1" <?= ($preferences['sharing_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
      Enable article sharing links on public posts
    </label>
    <p class="muted">Disabling this removes the “Share this:” block from article pages.</p>
    <button type="submit">Save Sharing Settings</button>
  </form>
</section>
