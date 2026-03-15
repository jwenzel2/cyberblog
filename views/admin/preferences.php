<div class="card" style="max-width:640px;">
  <h1>Preferences</h1>
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <form method="post" action="/admin/preferences">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <label>Articles per page</label>
    <input type="number" min="1" max="100" name="articles_per_page" value="<?= htmlspecialchars($preferences['articles_per_page'] ?? '10') ?>" required>
    <p class="muted">Applies to public listings and the admin posts table.</p>
    <button type="submit">Save preferences</button>
  </form>
</div>
