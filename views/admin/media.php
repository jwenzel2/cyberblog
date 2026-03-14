<div class="grid">
  <section class="card">
    <h1>Media Library</h1>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <table>
      <thead><tr><th>Preview</th><th>Name</th><th>MIME</th><th>Path</th></tr></thead>
      <tbody>
      <?php foreach ($media as $asset): ?>
        <tr>
          <td><?php if (str_starts_with($asset['mime_type'], 'image/')): ?><img src="<?= htmlspecialchars($asset['public_url']) ?>" alt="" style="max-width:110px; border-radius:8px;"><?php endif; ?></td>
          <td><?= htmlspecialchars($asset['original_name']) ?></td>
          <td><?= htmlspecialchars($asset['mime_type']) ?></td>
          <td class="muted"><?= htmlspecialchars($asset['public_url']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <aside class="card">
    <h2>Upload Media</h2>
    <form method="post" action="/admin/media" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <input type="file" name="media_file" required>
      <button type="submit">Upload</button>
    </form>
  </aside>
</div>
