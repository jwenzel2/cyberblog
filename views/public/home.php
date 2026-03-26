<div class="grid">
  <main>
    <?php foreach ($posts as $post): ?>
      <article class="card">
        <div class="muted"><?= htmlspecialchars($post['published_at'] ?: $post['created_at']) ?></div>
        <h2><a href="/post/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
        <?php if ($post['featured_image']): ?>
          <a href="/post/<?= urlencode($post['slug']) ?>">
            <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="<?= htmlspecialchars($post['featured_image_alt'] ?? '') ?>" style="width:100%; border-radius:12px;">
          </a>
        <?php endif; ?>
        <p><?= nl2br(htmlspecialchars(substr(strip_tags($post['body_html']), 0, 220) . '...')) ?></p>
      </article>
    <?php endforeach; ?>
    <div class="pagination">
      <?php if (($pagination['page'] ?? 1) > 1): ?>
        <a class="btn" href="/?page=<?= (int) $pagination['page'] - 1 ?>">Previous</a>
      <?php endif; ?>
      <span class="muted">Page <?= (int) ($pagination['page'] ?? 1) ?> of <?= (int) ($pagination['total_pages'] ?? 1) ?></span>
      <?php if (($pagination['page'] ?? 1) < ($pagination['total_pages'] ?? 1)): ?>
        <a class="btn" href="/?page=<?= (int) $pagination['page'] + 1 ?>">Next</a>
      <?php endif; ?>
      <form method="get" action="/" class="page-jump">
        <label for="home-page-jump" class="muted">Go to page</label>
        <input
          id="home-page-jump"
          type="number"
          name="page"
          min="1"
          max="<?= (int) ($pagination['total_pages'] ?? 1) ?>"
          value="<?= (int) ($pagination['page'] ?? 1) ?>"
        >
        <button type="submit">Go</button>
      </form>
    </div>
  </main>
  <aside class="card">
    <h3>Categories</h3>
    <?php
    $renderTree = static function (array $items) use (&$renderTree): void {
        echo '<ul class="tree">';
        foreach ($items as $item) {
            echo '<li><a href="/category/' . urlencode($item['slug']) . '">' . htmlspecialchars($item['name']) . '</a>';
            if (!empty($item['children'])) {
                $renderTree($item['children']);
            }
            echo '</li>';
        }
        echo '</ul>';
    };
    $renderTree($categories);
    ?>
  </aside>
</div>
