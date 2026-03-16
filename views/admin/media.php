<header class="admin-topbar">
  <div>
    <h1>Media</h1>
    <p>Control the media library used by posts, imports, and featured content across the site.</p>
  </div>
</header>

<div class="admin-grid">
  <section class="admin-card">
    <div class="page-header">
      <div>
        <h2>Media Library</h2>
        <p class="muted">Browse uploaded assets in a 4 x 4 library grid and move through additional pages as the library fills up.</p>
      </div>
    </div>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <div class="media-library-grid">
      <?php foreach ($media as $asset): ?>
        <article class="media-library-card">
          <div class="media-library-preview">
            <?php if (str_starts_with($asset['mime_type'], 'image/')): ?>
              <img src="<?= htmlspecialchars($asset['public_url']) ?>" alt="">
            <?php else: ?>
              <span class="muted"><?= htmlspecialchars(strtoupper(pathinfo((string) $asset['original_name'], PATHINFO_EXTENSION) ?: 'FILE')) ?></span>
            <?php endif; ?>
          </div>
          <div class="media-library-meta">
            <strong><?= htmlspecialchars($asset['original_name']) ?></strong>
            <div class="muted"><?= htmlspecialchars($asset['mime_type']) ?></div>
            <div class="muted"><?= htmlspecialchars($asset['public_url']) ?></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="pagination">
      <?php if (($pagination['page'] ?? 1) > 1): ?>
        <a class="btn" href="/admin/media?page=<?= (int) $pagination['page'] - 1 ?>">Previous</a>
      <?php endif; ?>
      <span class="muted">Page <?= (int) ($pagination['page'] ?? 1) ?> of <?= (int) ($pagination['total_pages'] ?? 1) ?></span>
      <?php if (($pagination['page'] ?? 1) < ($pagination['total_pages'] ?? 1)): ?>
        <a class="btn" href="/admin/media?page=<?= (int) $pagination['page'] + 1 ?>">Next</a>
      <?php endif; ?>
      <form method="get" action="/admin/media" class="page-jump">
        <label for="media-page-jump" class="muted">Go to page</label>
        <input
          id="media-page-jump"
          type="number"
          name="page"
          min="1"
          max="<?= (int) ($pagination['total_pages'] ?? 1) ?>"
          value="<?= (int) ($pagination['page'] ?? 1) ?>"
        >
        <button type="submit">Go</button>
      </form>
    </div>
  </section>
  <aside class="admin-card">
    <div class="page-header">
      <div>
        <h2>Upload Media</h2>
        <p class="muted">Add a new asset to the library.</p>
      </div>
    </div>
    <form method="post" action="/admin/media" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <input type="file" name="media_file" required>
      <button type="submit">Upload</button>
    </form>
  </aside>
</div>
