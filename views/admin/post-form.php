<?php
$post = $post ?? ['id' => null, 'title' => '', 'slug' => '', 'body_html' => '', 'status' => 'draft', 'published_at' => '', 'featured_media_id' => '', 'category_ids' => [], 'author_id' => $user['id']];
$selectedCategories = array_map('intval', $post['category_ids'] ?? []);
$featuredId = (int) ($post['featured_media_id'] ?? 0);
$selectedAuthorId = (int) ($post['author_id'] ?? $user['id']);
$featuredAsset = $featuredMedia ?? null;
$categoryOptions = $categoryOptions ?? [];
foreach ($media as $asset) {
    if ((int) $asset['id'] === $featuredId) {
        $featuredAsset = $asset;
        break;
    }
}
?>
<header class="admin-topbar">
  <div>
    <h1><?= $post['id'] ? 'Edit Post' : 'Create Post' ?></h1>
    <p>Compose and publish content from the admin-only editorial surface, including category assignment and featured media selection.</p>
  </div>
</header>

<form method="post" action="<?= $post['id'] ? '/admin/posts/' . (int) $post['id'] . '/edit' : '/admin/posts/create' ?>">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
  <input type="hidden" name="id" value="<?= (int) ($post['id'] ?? 0) ?>">
  <input type="hidden" name="featured_media_id" id="featured_media_id" value="<?= $featuredId ?>">
  <div class="admin-form-grid">
    <section class="admin-card">
      <div class="page-header">
        <div>
          <h2>Post Content</h2>
          <p class="muted">Use the inline editor to draft or refine the article body.</p>
        </div>
      </div>
      <label>Title</label>
      <input name="title" required value="<?= htmlspecialchars($post['title']) ?>">
      <label>Slug</label>
      <input name="slug" value="<?= htmlspecialchars($post['slug']) ?>">
      <label>Body</label>
      <div class="toolbar">
        <button type="button" data-command="formatBlock" data-value="<p>">Paragraph</button>
        <button type="button" data-command="formatBlock" data-value="<h2>">H2</button>
        <button type="button" data-command="bold">Bold</button>
        <button type="button" data-command="italic">Italic</button>
        <button type="button" data-command="insertUnorderedList">Bullet List</button>
        <button type="button" data-command="insertOrderedList">Numbered List</button>
        <button type="button" data-command="formatBlock" data-value="<blockquote>">Quote</button>
        <button type="button" id="insert-link">Link</button>
        <button type="button" id="insert-code">Code</button>
        <button type="button" id="insert-image">Image</button>
      </div>
      <div id="editor-surface" class="editor-surface" contenteditable="true"><?= $post['body_html'] ?></div>
      <textarea id="body_html" name="body_html" class="hidden"><?= htmlspecialchars($post['body_html']) ?></textarea>
    </section>

    <aside class="admin-card">
      <div class="page-header">
        <div>
          <h2>Post Settings</h2>
          <p class="muted">Control publication, ownership, taxonomy, and featured media.</p>
        </div>
      </div>
      <label>Status</label>
      <select name="status">
        <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published</option>
      </select>
      <label>Published at (UTC)</label>
      <input name="published_at" placeholder="2026-03-14 18:30:00" value="<?= htmlspecialchars((string) $post['published_at']) ?>">
      <label>Author</label>
      <select name="author_id" <?= \App\Models\User::hasRole($user, \App\Models\User::ROLE_AUTHOR) ? 'disabled' : '' ?>>
        <?php foreach (($users ?? []) as $author): ?>
          <option value="<?= (int) $author['id'] ?>" <?= $selectedAuthorId === (int) $author['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars((string) ($author['display_name'] ?: $author['email'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (\App\Models\User::hasRole($user, \App\Models\User::ROLE_AUTHOR)): ?>
        <input type="hidden" name="author_id" value="<?= (int) $user['id'] ?>">
      <?php endif; ?>

      <label>Categories</label>
      <div class="multi-select" id="category-select">
        <div class="multi-select-panel">
          <input
            type="search"
            id="category-search"
            class="multi-select-search"
            placeholder="Search categories"
            autocomplete="off"
          >
          <div class="multi-select-list" id="category-list">
            <?php foreach ($categoryOptions as $option): ?>
                <label
                  class="multi-select-option"
                  data-category-option
                  data-category-name="<?= htmlspecialchars(strtolower((string) $option['name']), ENT_QUOTES) ?>"
                >
                <input type="checkbox" name="category_ids[]" value="<?= (int) $option['id'] ?>" <?= in_array((int) $option['id'], $selectedCategories, true) ? 'checked' : '' ?>>
                <span><?= str_repeat('-- ', (int) ($option['depth'] ?? 0)) . htmlspecialchars($option['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="multi-select-footer">
            <span class="muted" id="category-selection-summary"><?= count($selectedCategories) ?> selected</span>
            <button type="button" class="text-link" id="open-category-dialog">Add category</button>
          </div>
        </div>
      </div>

      <label>Featured media</label>
      <div class="stack">
        <div id="featured-preview" class="media-card<?= $featuredAsset ? ' is-selected' : '' ?>">
          <?php if ($featuredAsset): ?>
            <img src="<?= htmlspecialchars($featuredAsset['public_url']) ?>" alt="">
            <div><?= htmlspecialchars($featuredAsset['original_name']) ?></div>
          <?php else: ?>
            <div class="muted">No featured image selected.</div>
          <?php endif; ?>
        </div>
        <div class="two-col">
          <select id="media-sort">
            <option value="newest">Newest first</option>
            <option value="oldest">Oldest first</option>
            <option value="name_asc">Name A-Z</option>
            <option value="name_desc">Name Z-A</option>
          </select>
          <input type="file" id="media-upload" accept="image/*">
        </div>
        <div id="media-grid" class="media-grid"></div>
        <div class="pagination">
          <button type="button" id="media-prev">Previous</button>
          <span id="media-page-label" class="muted"></span>
          <button type="button" id="media-next">Next</button>
          <div class="page-jump">
            <label for="picker-page-jump" class="muted">Go to page</label>
            <input id="picker-page-jump" type="number" min="1" value="1">
            <button type="button" id="picker-page-go">Go</button>
          </div>
        </div>
      </div>
      <button type="submit">Save Post</button>
    </aside>
  </div>
</form>

<div class="dialog-backdrop hidden" id="category-dialog" aria-hidden="true">
  <div class="dialog-card">
    <div class="page-header">
      <div>
        <h2>Add Category</h2>
        <p class="muted">Create a new category and optionally place it under an existing parent.</p>
      </div>
    </div>
    <form id="category-dialog-form">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <label for="new-category-name">Category name</label>
      <input id="new-category-name" name="name" required>
      <label for="new-category-parent">Parent category</label>
      <select id="new-category-parent" name="parent_id">
        <option value="">No parent</option>
        <?php foreach ($categoryOptions as $option): ?>
          <option value="<?= (int) $option['id'] ?>"><?= str_repeat('-- ', (int) ($option['depth'] ?? 0)) . htmlspecialchars($option['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="dialog-actions">
        <button type="button" class="btn" id="cancel-category-dialog">Cancel</button>
        <button type="submit" id="submit-category-dialog">Create Category</button>
      </div>
    </form>
  </div>
</div>
<script>
const csrfToken = <?= json_encode(\App\Core\Csrf::token()) ?>;
let categoryOptions = <?= json_encode(array_map(
  static fn(array $option): array => [
    'id' => (int) $option['id'],
    'name' => (string) $option['name'],
    'depth' => (int) ($option['depth'] ?? 0),
  ],
  $categoryOptions
)) ?>;
const titleInput = document.querySelector('input[name="title"]');
const slugInput = document.querySelector('input[name="slug"]');
const toSlug = (text) => text.toLowerCase().trim().replace(/[^\w\s-]/g, '').replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
titleInput?.addEventListener('input', () => { slugInput.value = toSlug(titleInput.value); });

const editorSurface = document.getElementById('editor-surface');
const bodyField = document.getElementById('body_html');
const syncEditor = () => { bodyField.value = editorSurface.innerHTML.trim(); };
document.querySelectorAll('[data-command]').forEach((button) => {
  button.addEventListener('click', () => {
    document.execCommand(button.dataset.command, false, button.dataset.value || null);
    syncEditor();
  });
});
document.getElementById('insert-link')?.addEventListener('click', () => {
  const href = window.prompt('Enter link URL');
  if (href) {
    document.execCommand('createLink', false, href);
    syncEditor();
  }
});
document.getElementById('insert-code')?.addEventListener('click', () => {
  const snippet = window.prompt('Enter code snippet');
  if (snippet) {
    const safe = snippet.replace(/[&<>]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[char]));
    document.execCommand('insertHTML', false, `<pre><code>${safe}</code></pre>`);
    syncEditor();
  }
});
editorSurface.addEventListener('input', syncEditor);
document.querySelector('form')?.addEventListener('submit', syncEditor);

const categoryList = document.getElementById('category-list');
const categorySearch = document.getElementById('category-search');
const categorySummary = document.getElementById('category-selection-summary');
const categoryDialog = document.getElementById('category-dialog');
const categoryDialogForm = document.getElementById('category-dialog-form');
const categoryDialogName = document.getElementById('new-category-name');
const categoryDialogParent = document.getElementById('new-category-parent');
const categoryDialogSubmit = document.getElementById('submit-category-dialog');

const escapeHtml = (value) => String(value).replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));
const categoryLabel = (option) => `${'-- '.repeat(Number(option.depth || 0))}${option.name}`;
const selectedCategoryIds = () => Array.from(categoryList.querySelectorAll('input[name="category_ids[]"]:checked')).map((input) => String(input.value));
const updateCategorySummary = () => {
  const count = selectedCategoryIds().length;
  categorySummary.textContent = `${count} selected`;
};
const renderCategoryParentOptions = () => {
  categoryDialogParent.innerHTML = '<option value="">No parent</option>';
  categoryOptions.forEach((option) => {
    const node = document.createElement('option');
    node.value = String(option.id);
    node.textContent = categoryLabel(option);
    categoryDialogParent.appendChild(node);
  });
};
const renderCategoryList = (selectedIds = selectedCategoryIds()) => {
  categoryList.innerHTML = '';
  categoryOptions.forEach((option) => {
    const label = document.createElement('label');
    label.className = 'multi-select-option';
    label.setAttribute('data-category-option', '');
    label.dataset.categoryName = option.name.toLowerCase();
    label.innerHTML = `<input type="checkbox" name="category_ids[]" value="${option.id}"${selectedIds.includes(String(option.id)) ? ' checked' : ''}><span>${escapeHtml(categoryLabel(option))}</span>`;
    categoryList.appendChild(label);
  });
  bindCategorySelectionHandlers();
  filterCategories();
  updateCategorySummary();
};
const filterCategories = () => {
  const query = (categorySearch.value || '').trim().toLowerCase();
  categoryList.querySelectorAll('[data-category-option]').forEach((option) => {
    option.classList.toggle('is-hidden', query !== '' && !option.dataset.categoryName.includes(query));
  });
};
const bindCategorySelectionHandlers = () => {
  categoryList.querySelectorAll('input[name="category_ids[]"]').forEach((input) => {
    input.addEventListener('change', updateCategorySummary);
  });
};
const openCategoryDialog = () => {
  categoryDialog.classList.remove('hidden');
  categoryDialog.setAttribute('aria-hidden', 'false');
  categoryDialogForm.reset();
  renderCategoryParentOptions();
  window.setTimeout(() => categoryDialogName.focus(), 0);
};
const closeCategoryDialog = () => {
  categoryDialog.classList.add('hidden');
  categoryDialog.setAttribute('aria-hidden', 'true');
  categoryDialogSubmit.disabled = false;
};

bindCategorySelectionHandlers();
updateCategorySummary();
categorySearch?.addEventListener('input', filterCategories);
document.getElementById('open-category-dialog')?.addEventListener('click', openCategoryDialog);
document.getElementById('cancel-category-dialog')?.addEventListener('click', closeCategoryDialog);
categoryDialog?.addEventListener('click', (event) => {
  if (event.target === categoryDialog) {
    closeCategoryDialog();
  }
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && !categoryDialog.classList.contains('hidden')) {
    closeCategoryDialog();
  }
});
categoryDialogForm?.addEventListener('submit', async (event) => {
  event.preventDefault();
  categoryDialogSubmit.disabled = true;
  const formData = new FormData(categoryDialogForm);
  if (!formData.get('parent_id')) {
    formData.set('parent_id', '0');
  }

  try {
    const response = await fetch('/admin/categories', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: formData,
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.error || 'Unable to create category.');
    }

    categoryOptions = Array.isArray(payload.categoryOptions) ? payload.categoryOptions.map((option) => ({
      id: Number(option.id),
      name: String(option.name),
      depth: Number(option.depth || 0),
    })) : categoryOptions;
    const createdId = String(payload.category?.id || '');
    const selectedIds = selectedCategoryIds();
    if (createdId) {
      selectedIds.push(createdId);
    }
    renderCategoryParentOptions();
    renderCategoryList(selectedIds);
    closeCategoryDialog();
  } catch (error) {
    window.alert(error.message || 'Unable to create category.');
    categoryDialogSubmit.disabled = false;
  }
});

let mediaPage = 1;
let mediaTotalPages = 1;
const mediaGrid = document.getElementById('media-grid');
const mediaPageLabel = document.getElementById('media-page-label');
const mediaPageJump = document.getElementById('picker-page-jump');
const featuredInput = document.getElementById('featured_media_id');
const featuredPreview = document.getElementById('featured-preview');
const mediaSort = document.getElementById('media-sort');

const renderFeatured = (item) => {
  featuredInput.value = item?.id || '';
  if (!item) {
    featuredPreview.classList.remove('is-selected');
    featuredPreview.innerHTML = '<div class="muted">No featured image selected.</div>';
    return;
  }
  featuredPreview.classList.add('is-selected');
  featuredPreview.innerHTML = `<img src="${item.public_url}" alt=""><div>${item.original_name}</div>`;
};

const loadMedia = async () => {
  const response = await fetch(`/admin/media/picker?page=${mediaPage}&sort=${encodeURIComponent(mediaSort.value)}`);
  const payload = await response.json();
  mediaTotalPages = payload.total_pages || 1;
  mediaPageLabel.textContent = `Page ${payload.page} of ${mediaTotalPages}`;
  if (mediaPageJump) {
    mediaPageJump.max = String(mediaTotalPages);
    mediaPageJump.value = String(payload.page);
  }
  mediaGrid.innerHTML = '';
  payload.items.forEach((item) => {
    const card = document.createElement('button');
    card.type = 'button';
    card.className = 'media-card' + (String(item.id) === String(featuredInput.value) ? ' is-selected' : '');
    card.innerHTML = `<img src="${item.public_url}" alt=""><div>${item.original_name}</div>`;
    card.addEventListener('click', () => {
      mediaGrid.querySelectorAll('.media-card').forEach((node) => node.classList.remove('is-selected'));
      card.classList.add('is-selected');
      renderFeatured(item);
    });
    mediaGrid.appendChild(card);
  });
};

document.getElementById('media-prev')?.addEventListener('click', async () => {
  mediaPage = Math.max(1, mediaPage - 1);
  await loadMedia();
});
document.getElementById('media-next')?.addEventListener('click', async () => {
  mediaPage = Math.min(mediaTotalPages, mediaPage + 1);
  await loadMedia();
});
document.getElementById('picker-page-go')?.addEventListener('click', async () => {
  const requestedPage = Number(mediaPageJump?.value || '1');
  mediaPage = Math.min(mediaTotalPages, Math.max(1, requestedPage || 1));
  await loadMedia();
});
mediaSort?.addEventListener('change', async () => {
  mediaPage = 1;
  await loadMedia();
});
document.getElementById('media-upload')?.addEventListener('change', async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  const formData = new FormData();
  formData.set('_csrf', csrfToken);
  formData.set('media_file', file);
  const response = await fetch('/admin/media/picker/upload', { method: 'POST', body: formData });
  const payload = await response.json();
  if (payload.item) {
    renderFeatured(payload.item);
    mediaPage = 1;
    await loadMedia();
  }
});
document.getElementById('insert-image')?.addEventListener('click', () => {
  const image = featuredPreview.querySelector('img');
  if (!image) {
    window.alert('Select a featured image first, then insert it into the body if desired.');
    return;
  }
  document.execCommand('insertHTML', false, `<figure><img src="${image.getAttribute('src')}" alt=""><figcaption></figcaption></figure>`);
  syncEditor();
});
loadMedia();
</script>
