<header class="admin-topbar">
  <div>
    <h1>Categories</h1>
    <p>Maintain the nested taxonomy used across the admin-controlled content model.</p>
  </div>
</header>

<div class="admin-grid">
  <section class="admin-card">
    <div class="page-header">
      <div>
        <h2>Existing Categories</h2>
        <p class="muted">Edit hierarchy, descriptions, and slugs without leaving the panel.</p>
      </div>
    </div>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <div class="stack">
      <?php foreach ($flatCategories as $category): ?>
        <form class="admin-card" method="post" action="/admin/categories/<?= (int) $category['id'] ?>/edit" style="margin:0;">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
          <div class="page-header">
            <div>
              <h2><?= str_repeat('-- ', (int) ($category['depth'] ?? 0)) . htmlspecialchars($category['name']) ?></h2>
              <p class="muted"><?= htmlspecialchars($category['slug']) ?></p>
            </div>
          </div>
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
  <aside class="admin-card">
    <div class="page-header">
      <div>
        <h2>Create Category</h2>
        <p class="muted">Add another branch to the content taxonomy.</p>
      </div>
    </div>
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
