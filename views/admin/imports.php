<header class="admin-topbar">
  <div>
    <h1>Import</h1>
    <p>Run WordPress archive migrations and inspect the history of completed or failed import runs.</p>
  </div>
</header>

<div class="admin-grid">
  <section class="admin-card">
    <div class="page-header">
      <div>
        <h2>Import History</h2>
        <p class="muted">Recent archive runs with content and media counters.</p>
      </div>
    </div>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <div class="admin-table-wrap">
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
    </div>
  </section>
  <aside class="admin-card">
    <div class="page-header">
      <div>
        <h2>Run Import</h2>
        <p class="muted">Upload a `.tar.gz` archive produced from WordPress export tooling.</p>
      </div>
    </div>
    <form method="post" action="/admin/imports" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <input type="file" name="wordpress_archive" accept=".tar.gz" required>
      <button type="submit">Import Archive</button>
    </form>
  </aside>
</div>
