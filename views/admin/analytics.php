<header class="admin-topbar">
  <div>
    <h1>Analytics</h1>
    <p>Monitor site traffic and article engagement using built-in daily visit and post-open tracking.</p>
  </div>
</header>

<section class="admin-card">
  <div class="page-header">
    <div>
      <h2>Traffic Summary</h2>
      <p class="muted">Site visits are counted once per session per day. Post opens count article page loads.</p>
    </div>
  </div>
  <div class="analytics-grid">
    <div class="metric"><span class="muted">Visits Today</span><strong><?= (int) ($siteVisits['today'] ?? 0) ?></strong></div>
    <div class="metric"><span class="muted">Visits Last 7 Days</span><strong><?= (int) ($siteVisits['week'] ?? 0) ?></strong></div>
    <div class="metric"><span class="muted">Visits Last 30 Days</span><strong><?= (int) ($siteVisits['month'] ?? 0) ?></strong></div>
    <div class="metric"><span class="muted">Post Opens Today</span><strong><?= (int) ($postViews['today'] ?? 0) ?></strong></div>
    <div class="metric"><span class="muted">Post Opens Last 7 Days</span><strong><?= (int) ($postViews['week'] ?? 0) ?></strong></div>
    <div class="metric"><span class="muted">Post Opens Last 30 Days</span><strong><?= (int) ($postViews['month'] ?? 0) ?></strong></div>
  </div>
</section>

<div class="admin-grid">
  <section class="admin-card">
    <div class="page-header">
      <div>
        <h2>Daily Site Visits</h2>
        <p class="muted">Most recent 30 tracked days.</p>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Visits</th></tr></thead>
        <tbody>
          <?php foreach ($recentSiteVisits as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) $row['metric_date']) ?></td>
              <td><?= (int) $row['visit_count'] ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($recentSiteVisits === []): ?>
            <tr><td colspan="2" class="muted">No visit data has been recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <aside class="admin-card">
    <div class="page-header">
      <div>
        <h2>Top Posts Today</h2>
        <p class="muted">Most-opened articles in the current day.</p>
      </div>
    </div>
    <div class="analytics-list">
      <?php foreach ($topPostsToday as $post): ?>
        <div class="analytics-item">
          <strong><?= htmlspecialchars((string) $post['title']) ?></strong>
          <div class="muted"><?= (int) $post['views'] ?> opens today</div>
        </div>
      <?php endforeach; ?>
      <?php if ($topPostsToday === []): ?>
        <p class="muted">No post opens have been recorded today.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>

<div class="admin-grid">
  <section class="admin-card">
    <div class="page-header">
      <div>
        <h2>Top Posts Last 7 Days</h2>
        <p class="muted">Articles with the most opens over the last week.</p>
      </div>
    </div>
    <div class="analytics-list">
      <?php foreach ($topPostsWeek as $post): ?>
        <div class="analytics-item">
          <strong><?= htmlspecialchars((string) $post['title']) ?></strong>
          <div class="muted"><?= (int) $post['views'] ?> opens in the last 7 days</div>
        </div>
      <?php endforeach; ?>
      <?php if ($topPostsWeek === []): ?>
        <p class="muted">No post opens have been recorded in the last 7 days.</p>
      <?php endif; ?>
    </div>
  </section>

  <aside class="admin-card">
    <div class="page-header">
      <div>
        <h2>Top Posts Last 30 Days</h2>
        <p class="muted">Articles with the most opens over the last month.</p>
      </div>
    </div>
    <div class="analytics-list">
      <?php foreach ($topPostsMonth as $post): ?>
        <div class="analytics-item">
          <strong><?= htmlspecialchars((string) $post['title']) ?></strong>
          <div class="muted"><?= (int) $post['views'] ?> opens in the last 30 days</div>
        </div>
      <?php endforeach; ?>
      <?php if ($topPostsMonth === []): ?>
        <p class="muted">No post opens have been recorded in the last 30 days.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>
