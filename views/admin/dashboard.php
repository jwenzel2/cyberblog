<header class="admin-topbar">
  <div>
    <h1>Administration Panel</h1>
    <p>Operate CyberBlog from one admin-only workspace. Review content, accounts, imports, and your security posture without leaving the control panel.</p>
  </div>
  <div class="stack-sm">
    <span class="tag">Signed in as <?= htmlspecialchars($user['display_name']) ?></span>
    <span class="muted"><?= htmlspecialchars($user['email']) ?></span>
  </div>
</header>

<section class="admin-metrics">
  <div class="metric"><span class="muted">Preferences</span><strong><?= (int) $stats['preferences'] ?></strong></div>
  <div class="metric"><span class="muted">Posts</span><strong><?= (int) $stats['posts'] ?></strong></div>
  <div class="metric"><span class="muted">Categories</span><strong><?= (int) $stats['categories'] ?></strong></div>
  <div class="metric"><span class="muted">Media</span><strong><?= (int) $stats['media'] ?></strong></div>
  <div class="metric"><span class="muted">Users</span><strong><?= (int) $stats['users'] ?></strong></div>
  <div class="metric"><span class="muted">Imports</span><strong><?= (int) $stats['imports'] ?></strong></div>
  <div class="metric"><span class="muted">Passkeys</span><strong><?= (int) $stats['passkeys'] ?></strong></div>
  <div class="metric"><span class="muted">Recovery Codes</span><strong><?= (int) $stats['recovery_codes'] ?></strong></div>
</section>

<div class="admin-grid">
  <section class="admin-card">
    <div class="page-header">
      <div>
        <h2>Admin Sections</h2>
        <p class="muted">Jump directly into the seven management areas requested for the panel.</p>
      </div>
    </div>
    <div class="admin-actions">
      <a href="/admin/preferences"><strong>Preferences</strong><span class="muted">Site-wide defaults and SMTP delivery settings.</span></a>
      <a href="/admin/posts"><strong>Posts</strong><span class="muted">Publish, edit, and organize article output.</span></a>
      <a href="/admin/categories"><strong>Categories</strong><span class="muted">Manage nested taxonomy for content grouping.</span></a>
      <a href="/admin/media"><strong>Media</strong><span class="muted">Upload and review the media library.</span></a>
      <a href="/admin/users"><strong>Users</strong><span class="muted">Provision accounts, roles, and lockout recovery.</span></a>
      <a href="/admin/imports"><strong>Import</strong><span class="muted">Run WordPress migrations and inspect import history.</span></a>
      <a href="/admin/security"><strong>Security</strong><span class="muted">Maintain passkeys, TOTP, and recovery readiness.</span></a>
    </div>
  </section>

  <aside class="admin-card">
    <div class="page-header">
      <div>
        <h2>Quick Actions</h2>
        <p class="muted">Shortcuts for common administration tasks.</p>
      </div>
    </div>
    <div class="stack-sm">
      <a class="btn" href="/admin/posts/create">Create Post</a>
      <a class="btn" href="/admin/media">Upload Media</a>
      <a class="btn" href="/admin/users">Manage Users</a>
      <a class="btn" href="/admin/imports">Run Import</a>
    </div>
  </aside>
</div>

<div class="admin-grid">
  <section class="admin-card">
    <div class="page-header">
      <div>
        <h2>Recent Posts</h2>
        <p class="muted">Latest post activity across the admin-managed publishing queue.</p>
      </div>
      <a class="btn" href="/admin/posts">All Posts</a>
    </div>
    <div class="admin-table-wrap">
      <table>
        <thead><tr><th>Title</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($posts, 0, 8) as $post): ?>
          <tr>
            <td><a href="/admin/posts/<?= (int) $post['id'] ?>/edit"><?= htmlspecialchars($post['title']) ?></a></td>
            <td><?= htmlspecialchars($post['status']) ?></td>
            <td><?= htmlspecialchars($post['updated_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <aside class="admin-card">
    <div class="page-header">
      <div>
        <h2>Recent Imports</h2>
        <p class="muted">Latest WordPress archive processing activity.</p>
      </div>
    </div>
    <?php if ($imports !== []): ?>
      <?php foreach (array_slice($imports, 0, 5) as $import): ?>
        <div style="margin-bottom:14px;">
          <div><strong><?= htmlspecialchars($import['archive_name']) ?></strong></div>
          <div class="muted"><?= htmlspecialchars($import['status']) ?> · <?= htmlspecialchars($import['updated_at']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="muted">No import history has been recorded yet.</p>
    <?php endif; ?>
  </aside>
</div>
