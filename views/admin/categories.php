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
        <p class="muted">Select a category from the 4 x 4 grid, move between pages, or jump to a specific page before editing or deleting it.</p>
      </div>
    </div>
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <div class="category-grid" id="category-grid">
      <?php foreach ($categories as $category): ?>
        <button
          type="button"
          class="category-card"
          data-category-id="<?= (int) $category['id'] ?>"
          data-category-name="<?= htmlspecialchars($category['name'], ENT_QUOTES) ?>"
          data-category-slug="<?= htmlspecialchars($category['slug'], ENT_QUOTES) ?>"
          data-category-description="<?= htmlspecialchars((string) ($category['description'] ?? ''), ENT_QUOTES) ?>"
          data-category-parent-id="<?= (int) ($category['parent_id'] ?? 0) ?>"
          data-category-parent-name="<?= htmlspecialchars((string) ($category['parent_name'] ?? ''), ENT_QUOTES) ?>"
        >
          <span class="tag">Parent Category</span>
          <strong><?= htmlspecialchars($category['name']) ?></strong>
          <div class="muted"><?= htmlspecialchars($category['slug']) ?></div>
          <div class="muted" style="margin-top:10px;"><?= htmlspecialchars((string) ($category['parent_name'] ?? '')) ?></div>
          <div class="muted" style="margin-top:10px;"><?= htmlspecialchars((string) ($category['description'] ?: 'No description set.')) ?></div>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="pagination">
      <?php if (($pagination['page'] ?? 1) > 1): ?>
        <a class="btn" href="/admin/categories?page=<?= (int) $pagination['page'] - 1 ?>">Previous</a>
      <?php endif; ?>
      <span class="muted">Page <?= (int) ($pagination['page'] ?? 1) ?> of <?= (int) ($pagination['total_pages'] ?? 1) ?></span>
      <?php if (($pagination['page'] ?? 1) < ($pagination['total_pages'] ?? 1)): ?>
        <a class="btn" href="/admin/categories?page=<?= (int) $pagination['page'] + 1 ?>">Next</a>
      <?php endif; ?>
      <form method="get" action="/admin/categories" style="display:flex; align-items:center; gap:10px;">
        <label for="category-page-jump" class="muted">Go to page</label>
        <input
          id="category-page-jump"
          type="number"
          name="page"
          min="1"
          max="<?= (int) ($pagination['total_pages'] ?? 1) ?>"
          value="<?= (int) ($pagination['page'] ?? 1) ?>"
          style="width:90px; margin:0;"
        >
        <button type="submit">Go</button>
      </form>
    </div>
  </section>
  <aside class="admin-aside-stack">
    <section class="admin-card">
      <div class="page-header">
        <div>
          <h2>Edit Category</h2>
          <p class="muted">Choose a category tile to load it here.</p>
        </div>
      </div>
      <form method="post" action="" id="edit-category-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <label>Name</label>
        <input id="edit-category-name" name="name" required disabled>
        <label>Slug</label>
        <input id="edit-category-slug" name="slug" disabled>
        <label>Description</label>
        <textarea id="edit-category-description" name="description" style="min-height:120px;" disabled></textarea>
        <label>Parent category</label>
        <select id="edit-category-parent" name="parent_id" disabled>
          <option value="">None</option>
          <?php foreach ($flatCategories as $category): ?>
            <option value="<?= (int) $category['id'] ?>"><?= str_repeat('-- ', (int) ($category['depth'] ?? 0)) . htmlspecialchars($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" id="edit-category-submit" disabled>Edit Category</button>
      </form>
      <form method="post" action="" id="delete-category-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <button type="submit" id="delete-category-submit" disabled>Delete Category</button>
      </form>
    </section>

    <section class="admin-card">
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
    </section>
  </aside>
</div>

<script>
const categoryCards = Array.from(document.querySelectorAll('.category-card'));
const editCategoryForm = document.getElementById('edit-category-form');
const deleteCategoryForm = document.getElementById('delete-category-form');
const editCategoryName = document.getElementById('edit-category-name');
const editCategorySlug = document.getElementById('edit-category-slug');
const editCategoryDescription = document.getElementById('edit-category-description');
const editCategoryParent = document.getElementById('edit-category-parent');
const editCategorySubmit = document.getElementById('edit-category-submit');
const deleteCategorySubmit = document.getElementById('delete-category-submit');

const setCategoryFormEnabled = (enabled) => {
  [editCategoryName, editCategorySlug, editCategoryDescription, editCategoryParent, editCategorySubmit, deleteCategorySubmit]
    .forEach((field) => {
      field.disabled = !enabled;
    });
};

const selectCategory = (card) => {
  categoryCards.forEach((item) => item.classList.remove('is-selected'));
  card.classList.add('is-selected');

  const categoryId = card.dataset.categoryId;
  editCategoryForm.action = `/admin/categories/${categoryId}/edit`;
  deleteCategoryForm.action = `/admin/categories/${categoryId}/delete`;
  editCategoryName.value = card.dataset.categoryName || '';
  editCategorySlug.value = card.dataset.categorySlug || '';
  editCategoryDescription.value = card.dataset.categoryDescription || '';
  editCategoryParent.value = card.dataset.categoryParentId && card.dataset.categoryParentId !== '0'
    ? card.dataset.categoryParentId
    : '';

  Array.from(editCategoryParent.options).forEach((option) => {
    option.disabled = option.value === categoryId;
  });

  setCategoryFormEnabled(true);
};

categoryCards.forEach((card) => {
  card.addEventListener('click', () => selectCategory(card));
});

deleteCategoryForm.addEventListener('submit', (event) => {
  if (!window.confirm('Delete this category? Child categories will be detached and post assignments will be removed.')) {
    event.preventDefault();
  }
});
</script>
