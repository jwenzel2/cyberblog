<div class="card">
  <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
    <h1>Posts</h1>
    <a class="btn" href="/admin/posts/create">Create Post</a>
  </div>
  <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <table>
    <thead><tr><th>Title</th><th>Status</th><th>Author</th><th>Published</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $post): ?>
        <tr>
          <td><?= htmlspecialchars($post['title']) ?></td>
          <td><?= htmlspecialchars($post['status']) ?></td>
          <td><?= htmlspecialchars($post['author_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($post['published_at'] ?: '-') ?></td>
          <td style="white-space:nowrap;">
            <a class="btn" href="/admin/posts/<?= (int) $post['id'] ?>/edit">Edit</a>
            <form method="post" action="/admin/posts/<?= (int) $post['id'] ?>/delete" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
              <button type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="pagination">
    <?php if (($pagination['page'] ?? 1) > 1): ?>
      <a class="btn" href="/admin/posts?page=<?= (int) $pagination['page'] - 1 ?>">Previous</a>
    <?php endif; ?>
    <span class="muted">Page <?= (int) ($pagination['page'] ?? 1) ?> of <?= (int) ($pagination['total_pages'] ?? 1) ?></span>
    <?php if (($pagination['page'] ?? 1) < ($pagination['total_pages'] ?? 1)): ?>
      <a class="btn" href="/admin/posts?page=<?= (int) $pagination['page'] + 1 ?>">Next</a>
    <?php endif; ?>
  </div>
</div>
