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
    <?php if (!empty($sharingEnabled)): ?>
      <?php
      $encodedShareUrl = rawurlencode((string) $shareUrl);
      $facebookShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . $encodedShareUrl;
      $linkedInShareUrl = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encodedShareUrl;
      ?>
      <section class="share-block" aria-label="Share this article">
        <strong>Share this:</strong>
        <div class="share-links">
          <a
            class="share-link"
            href="<?= htmlspecialchars($facebookShareUrl) ?>"
            target="_blank"
            rel="noopener noreferrer"
            onclick="window.open(this.href, 'share-facebook', 'width=640,height=520,menubar=no,toolbar=no,status=no'); return false;"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 22v-8h2.7l.4-3h-3.1V9.1c0-.9.3-1.6 1.6-1.6H17V4.8c-.3 0-1.4-.1-2.6-.1-2.6 0-4.4 1.6-4.4 4.5V11H7v3h3V22h3.5Z"/></svg>
            <span>Facebook</span>
          </a>
          <a
            class="share-link"
            href="<?= htmlspecialchars($linkedInShareUrl) ?>"
            target="_blank"
            rel="noopener noreferrer"
            onclick="window.open(this.href, 'share-linkedin', 'width=640,height=520,menubar=no,toolbar=no,status=no'); return false;"
          >
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.9 8.4A2.1 2.1 0 1 1 6.9 4a2.1 2.1 0 0 1 0 4.4ZM5 20V10h3.8v10H5Zm6 0V10h3.7v1.4h.1c.5-1 1.8-1.9 3.6-1.9 3.8 0 4.5 2.5 4.5 5.8V20H19v-4.1c0-1 0-2.4-1.5-2.4s-1.8 1.1-1.8 2.3V20H11Z"/></svg>
            <span>LinkedIn</span>
          </a>
        </div>
      </section>
    <?php endif; ?>
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
