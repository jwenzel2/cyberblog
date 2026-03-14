<div class="grid">
  <section class="card">
    <h1>WordPress Imports</h1>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <table>
      <thead><tr><th>Archive</th><th>Status</th><th>Posts</th><th>Media</th><th>Categories</th><th>Updated</th></tr></thead>
      <tbody>
      <?php foreach ($imports as $import): ?>
        <tr>
          <td><?= htmlspecialchars($import['archive_name']) ?></td>
          <td><?= htmlspecialchars($import['status']) ?></td>
          <td><?= (int) $import['imported_posts'] ?></td>
          <td><?= (int) $import['imported_media'] ?></td>
          <td><?= (int) $import['imported_categories'] ?></td>
          <td><?= htmlspecialchars($import['updated_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <aside class="card">
    <h2>Run Import</h2>
    <form method="post" action="/admin/imports" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <input type="file" name="wordpress_archive" accept=".tar.gz" required>
      <button type="submit">Import Archive</button>
    </form>
  </aside>
</div>
