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
        <p class="muted">Browse uploaded assets and verify their public paths.</p>
      </div>
    </div>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <div class="admin-table-wrap">
      <table>
        <thead><tr><th>Preview</th><th>Name</th><th>MIME</th><th>Path</th></tr></thead>
        <tbody>
        <?php foreach ($media as $asset): ?>
          <tr>
            <td><?php if (str_starts_with($asset['mime_type'], 'image/')): ?><img class="media-thumb" src="<?= htmlspecialchars($asset['public_url']) ?>" alt=""><?php endif; ?></td>
            <td><?= htmlspecialchars($asset['original_name']) ?></td>
            <td><?= htmlspecialchars($asset['mime_type']) ?></td>
            <td class="muted"><?= htmlspecialchars($asset['public_url']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
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
