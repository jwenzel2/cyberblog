<div class="grid">
  <section class="card">
    <h1>Nested Categories</h1>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php
    $renderTree = static function (array $items) use (&$renderTree): void {
        echo '<ul class="tree">';
        foreach ($items as $item) {
            echo '<li><strong>' . htmlspecialchars($item['name']) . '</strong> <span class="muted">/' . htmlspecialchars($item['slug']) . '</span>';
            if (!empty($item['description'])) {
                echo '<div class="muted">' . htmlspecialchars($item['description']) . '</div>';
            }
            if (!empty($item['children'])) {
                $renderTree($item['children']);
            }
            echo '</li>';
        }
        echo '</ul>';
    };
    $renderTree($categories);
    ?>
  </section>
  <aside class="card">
    <h2>Create Category</h2>
    <form method="post" action="/admin/categories">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <label>Name</label>
      <input name="name" required>
      <label>Slug</label>
      <input name="slug">
      <label>Description</label>
      <textarea name="description" style="min-height:120px;"></textarea>
      <label>Parent category</label>
      <select name="parent_id">
        <option value="">None</option>
        <?php foreach ($flatCategories as $category): ?>
          <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Create Category</button>
    </form>
  </aside>
</div>
