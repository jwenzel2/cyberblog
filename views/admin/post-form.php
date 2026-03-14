<?php $post = $post ?? ['id' => null, 'title' => '', 'slug' => '', 'excerpt' => '', 'body_html' => '', 'status' => 'draft', 'published_at' => '', 'featured_media_id' => '', 'category_ids' => []]; ?>
<div class="card">
  <h1><?= $post['id'] ? 'Edit Post' : 'Create Post' ?></h1>
  <form method="post" action="<?= $post['id'] ? '/admin/posts/' . (int) $post['id'] . '/edit' : '/admin/posts/create' ?>">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="id" value="<?= (int) ($post['id'] ?? 0) ?>">
    <label>Title</label>
    <input name="title" required value="<?= htmlspecialchars($post['title']) ?>">
    <label>Slug</label>
    <input name="slug" value="<?= htmlspecialchars($post['slug']) ?>">
    <label>Excerpt</label>
    <textarea name="excerpt" style="min-height:120px;"><?= htmlspecialchars($post['excerpt']) ?></textarea>
    <label>Body HTML</label>
    <textarea name="body_html"><?= htmlspecialchars($post['body_html']) ?></textarea>
    <label>Status</label>
    <select name="status">
      <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
      <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Published</option>
    </select>
    <label>Published at (UTC)</label>
    <input name="published_at" placeholder="2026-03-14 18:30:00" value="<?= htmlspecialchars((string) $post['published_at']) ?>">
    <label>Featured media</label>
    <select name="featured_media_id">
      <option value="">None</option>
      <?php foreach ($media as $asset): ?>
        <option value="<?= (int) $asset['id'] ?>" <?= (int) $asset['id'] === (int) ($post['featured_media_id'] ?? 0) ? 'selected' : '' ?>>
          <?= htmlspecialchars($asset['original_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label>Categories</label>
    <?php
    $renderCheckboxes = static function (array $items, array $selected) use (&$renderCheckboxes): void {
        echo '<ul class="tree">';
        foreach ($items as $item) {
            echo '<li><label><input type="checkbox" name="category_ids[]" value="' . (int) $item['id'] . '" ' . (in_array((int) $item['id'], array_map('intval', $selected), true) ? 'checked' : '') . '> ' . htmlspecialchars($item['name']) . '</label>';
            if (!empty($item['children'])) {
                $renderCheckboxes($item['children'], $selected);
            }
            echo '</li>';
        }
        echo '</ul>';
    };
    $renderCheckboxes($categories, $post['category_ids'] ?? []);
    ?>
    <button type="submit">Save Post</button>
  </form>
</div>
