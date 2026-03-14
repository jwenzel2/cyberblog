<div class="card">
  <h1>Dashboard</h1>
  <p>Signed in as <strong><?= htmlspecialchars($user['display_name']) ?></strong> (<?= htmlspecialchars($user['email']) ?>).</p>
  <p class="muted">Use the admin navigation to manage posts, nested categories, media, imports, and passkeys.</p>
</div>

<div class="grid">
  <section class="card">
    <h2>Recent Posts</h2>
    <table>
      <thead><tr><th>Title</th><th>Status</th><th>Updated</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($posts, 0, 8) as $post): ?>
        <tr>
          <td><a href="/admin/posts/<?= (int) $post['id'] ?>/edit"><?= htmlspecialchars($post['title']) ?></a></td>
          <td><?= htmlspecialchars($post['status']) ?></td>
          <td><?= htmlspecialchars($post['updated_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <aside class="card">
    <h2>Recent Imports</h2>
    <?php foreach (array_slice($imports, 0, 5) as $import): ?>
      <div style="margin-bottom:12px;">
        <div><?= htmlspecialchars($import['archive_name']) ?></div>
        <div class="muted"><?= htmlspecialchars($import['status']) ?> · <?= htmlspecialchars($import['updated_at']) ?></div>
      </div>
    <?php endforeach; ?>
  </aside>
</div>
