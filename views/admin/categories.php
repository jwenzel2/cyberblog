<div class="grid">
  <section class="card">
    <h1>Nested Categories</h1>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <div class="stack">
      <?php foreach ($flatCategories as $category): ?>
        <form class="card" method="post" action="/admin/categories/<?= (int) $category['id'] ?>/edit" style="margin:0;">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
          <label>Name</label>
          <input name="name" value="<?= htmlspecialchars($category['name']) ?>" required>
          <label>Slug</label>
          <input name="slug" value="<?= htmlspecialchars($category['slug']) ?>">
          <label>Description</label>
          <textarea name="description" style="min-height:100px;"><?= htmlspecialchars((string) ($category['description'] ?? '')) ?></textarea>
          <label>Parent category</label>
          <select name="parent_id">
            <option value="">None</option>
            <?php foreach ($flatCategories as $parent): ?>
              <?php if ((int) $parent['id'] === (int) $category['id']) { continue; } ?>
              <option value="<?= (int) $parent['id'] ?>" <?= (int) ($category['parent_id'] ?? 0) === (int) $parent['id'] ? 'selected' : '' ?>>
                <?= str_repeat('-- ', (int) ($parent['depth'] ?? 0)) . htmlspecialchars($parent['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Update Category</button>
        </form>
      <?php endforeach; ?>
    </div>
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
          <option value="<?= (int) $category['id'] ?>"><?= str_repeat('-- ', (int) ($category['depth'] ?? 0)) . htmlspecialchars($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Create Category</button>
    </form>
  </aside>
</div>
