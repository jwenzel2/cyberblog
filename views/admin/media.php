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
    <div class="media-library-grid" id="media-library-grid">
      <?php foreach ($media as $asset): ?>
        <button
          type="button"
          class="media-library-card"
          data-media-id="<?= (int) $asset['id'] ?>"
          data-media-name="<?= htmlspecialchars($asset['original_name'], ENT_QUOTES) ?>"
          data-media-mime="<?= htmlspecialchars($asset['mime_type'], ENT_QUOTES) ?>"
          data-media-url="<?= htmlspecialchars($asset['public_url'], ENT_QUOTES) ?>"
        >
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
        </button>
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
        <h2>Media Actions</h2>
        <p class="muted">Select a media tile to delete it, or upload a new asset to the library.</p>
      </div>
    </div>
    <div id="selected-media-details" class="stack" style="margin-bottom:18px;">
      <div class="muted">No media selected.</div>
    </div>
    <form method="post" action="" id="delete-media-form" style="margin-bottom:18px;">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <button type="submit" id="delete-media-submit" disabled>Delete Selected Media</button>
    </form>
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

<script>
const mediaCards = Array.from(document.querySelectorAll('.media-library-card'));
const deleteMediaForm = document.getElementById('delete-media-form');
const deleteMediaSubmit = document.getElementById('delete-media-submit');
const selectedMediaDetails = document.getElementById('selected-media-details');

const selectMediaCard = (card) => {
  mediaCards.forEach((item) => item.classList.remove('is-selected'));
  card.classList.add('is-selected');

  const mediaId = card.dataset.mediaId;
  deleteMediaForm.action = `/admin/media/${mediaId}/delete?page=<?= (int) ($pagination['page'] ?? 1) ?>`;
  deleteMediaSubmit.disabled = false;
  selectedMediaDetails.innerHTML = `
    <strong>${card.dataset.mediaName || ''}</strong>
    <div class="muted">${card.dataset.mediaMime || ''}</div>
    <div class="muted">${card.dataset.mediaUrl || ''}</div>
  `;
};

mediaCards.forEach((card) => {
  card.addEventListener('click', () => selectMediaCard(card));
});

deleteMediaForm.addEventListener('submit', (event) => {
  if (!window.confirm('Delete the selected media item? Featured image references will be cleared automatically.')) {
    event.preventDefault();
  }
});
</script>
