<div class="grid">
  <main>
    <?php if (!empty($breadcrumbs)): ?>
      <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
          <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <li><?php if (isset($crumb['url'])): ?><a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['name']) ?></a><?php else: ?><span aria-current="page"><?= htmlspecialchars($crumb['name']) ?></span><?php endif; ?></li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
    <div class="card">
      <h1><?= htmlspecialchars($category['name']) ?></h1>
      <p class="muted"><?= htmlspecialchars($category['description'] ?? '') ?></p>
    </div>
    <?php foreach ($posts as $post): ?>
      <article class="card">
        <div class="muted"><?= htmlspecialchars($post['published_at'] ?: $post['created_at']) ?></div>
        <h2><a href="/post/<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
        <p><?= nl2br(htmlspecialchars(substr(strip_tags($post['body_html']), 0, 220) . '...')) ?></p>
      </article>
    <?php endforeach; ?>
    <div class="pagination">
      <?php if (($pagination['page'] ?? 1) > 1): ?>
        <a class="btn" href="/category/<?= urlencode($category['slug']) ?>?page=<?= (int) $pagination['page'] - 1 ?>">Previous</a>
      <?php endif; ?>
      <span class="muted">Page <?= (int) ($pagination['page'] ?? 1) ?> of <?= (int) ($pagination['total_pages'] ?? 1) ?></span>
      <?php if (($pagination['page'] ?? 1) < ($pagination['total_pages'] ?? 1)): ?>
        <a class="btn" href="/category/<?= urlencode($category['slug']) ?>?page=<?= (int) $pagination['page'] + 1 ?>">Next</a>
      <?php endif; ?>
      <form method="get" action="/category/<?= urlencode($category['slug']) ?>" class="page-jump">
        <label for="category-public-page-jump" class="muted">Go to page</label>
        <input
          id="category-public-page-jump"
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
    <h3>Category Tree</h3>
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
