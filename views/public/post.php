<div class="grid">
  <article class="card post-body">
    <div class="muted"><?= htmlspecialchars($post['published_at'] ?: $post['created_at']) ?></div>
    <h1><?= htmlspecialchars($post['title']) ?></h1>
    <p>
      <?php foreach ($post['categories'] as $category): ?>
        <a class="tag" href="/category/<?= urlencode($category['slug']) ?>"><?= htmlspecialchars($category['name']) ?></a>
      <?php endforeach; ?>
    </p>
    <?php if ($post['featured_image']): ?>
      <img src="<?= htmlspecialchars($post['featured_image']) ?>" alt="" style="width:100%; margin-bottom:16px;">
    <?php endif; ?>
    <?= $post['body_html'] ?>
  </article>
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
