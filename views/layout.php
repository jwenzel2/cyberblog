<?php

use App\Core\Auth;
use App\Models\User;

$viewer = Auth::user();
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isAdminPage = str_starts_with($requestPath, '/admin');

$adminSections = [
    ['label' => 'Preferences', 'href' => '/admin/preferences'],
    ['label' => 'Posts', 'href' => '/admin/posts'],
    ['label' => 'Categories', 'href' => '/admin/categories'],
    ['label' => 'Media', 'href' => '/admin/media'],
    ['label' => 'Users', 'href' => '/admin/users'],
    ['label' => 'Import', 'href' => '/admin/imports'],
    ['label' => 'Security', 'href' => '/admin/security'],
];

$activeAdminHref = '/admin';
foreach ($adminSections as $section) {
    if ($requestPath === $section['href'] || str_starts_with($requestPath, $section['href'] . '/')) {
        $activeAdminHref = $section['href'];
        break;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'CyberBlog') ?></title>
  <style>
    :root {
      --bg: #07111d;
      --panel: #0f1d32;
      --panel-2: #132742;
      --line: #1f3c64;
      --text: #d9e5f7;
      --muted: #8fa9c7;
      --accent: #58d0a2;
      --accent-2: #f8c15c;
      --danger: #ff6d6d;
      --link: #8fd3ff;
      --admin-bg: #061019;
      --admin-shell: #0b1626;
      --admin-card: rgba(9, 21, 37, 0.9);
      --admin-sidebar: rgba(6, 15, 26, 0.94);
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: radial-gradient(circle at top, #123456, var(--bg) 40%); color: var(--text); }
    a { color: var(--link); text-decoration: none; }
    .wrap { max-width: 1180px; margin: 0 auto; padding: 24px; }
    .nav { display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; gap: 16px; }
    .brand { font: 700 24px/1.1 Consolas, monospace; text-transform: uppercase; letter-spacing: 0.12em; }
    .nav-links { display:flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .btn, button { border: 1px solid var(--line); background: var(--panel-2); color: var(--text); padding: 10px 14px; border-radius: 10px; cursor: pointer; font: inherit; }
    .grid { display:grid; grid-template-columns: 3fr 1fr; gap: 24px; }
    .split { display:grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items:start; }
    .card { background: rgba(12, 23, 40, 0.92); border: 1px solid var(--line); border-radius: 18px; padding: 20px; margin-bottom: 20px; box-shadow: 0 18px 40px rgba(0,0,0,.22); }
    .muted { color: var(--muted); }
    input, textarea, select { width:100%; border-radius: 10px; border: 1px solid var(--line); background: #091423; color: var(--text); padding: 10px; margin: 6px 0 14px; font: inherit; }
    textarea { min-height: 220px; }
    .flash { padding: 12px 14px; border: 1px solid var(--accent); color: var(--accent); border-radius: 10px; margin-bottom: 20px; background: rgba(16, 44, 39, 0.35); }
    .flash.danger { border-color: var(--danger); color: var(--danger); background: rgba(64, 18, 18, 0.35); }
    .danger { color: var(--danger); }
    .tree { list-style: none; padding-left: 16px; }
    .post-body img { max-width: 100%; border-radius: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align:left; padding: 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .tag { display:inline-block; padding: 4px 8px; border-radius: 999px; background: #11243b; border: 1px solid var(--line); color: var(--muted); margin-right: 6px; }
    .toolbar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
    .toolbar button { padding: 8px 10px; }
    .editor-surface { min-height: 360px; border: 1px solid var(--line); border-radius: 12px; background: #091423; padding: 14px; }
    .media-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .media-card { border: 1px solid var(--line); border-radius: 12px; padding: 8px; background: rgba(9, 20, 35, 0.75); }
    .media-card.is-selected { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent) inset; }
    .media-card img { width:100%; aspect-ratio:1 / 1; object-fit:cover; border-radius:8px; display:block; }
    .pagination { display:flex; gap:10px; align-items:center; margin-top:18px; flex-wrap:wrap; }
    .stack { display:flex; flex-direction:column; gap:12px; }
    .multi-select { position:relative; }
    .multi-select-panel { position:absolute; left:0; right:0; top:calc(100% + 8px); background: #091423; border:1px solid var(--line); border-radius:12px; padding:12px; max-height:280px; overflow:auto; z-index:10; display:none; }
    .multi-select.open .multi-select-panel { display:block; }
    .two-col { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .hidden { display:none !important; }
    .page-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom: 18px; }
    .page-header h1, .page-header h2 { margin: 0; }
    .page-header p { margin: 6px 0 0; }
    .stack-sm { display:flex; flex-direction:column; gap:8px; }
    .admin-body { margin: 0; min-height: 100vh; background:
      radial-gradient(circle at top left, rgba(34, 84, 106, 0.28), transparent 28%),
      radial-gradient(circle at top right, rgba(248, 193, 92, 0.14), transparent 22%),
      linear-gradient(180deg, #050c13 0%, var(--admin-bg) 100%);
    }
    .admin-shell { display:grid; grid-template-columns: 280px minmax(0, 1fr); min-height: 100vh; }
    .admin-sidebar { background: var(--admin-sidebar); border-right: 1px solid rgba(143, 211, 255, 0.12); padding: 28px 22px; position: sticky; top: 0; height: 100vh; }
    .admin-brand { display:block; font: 700 22px/1.1 Consolas, monospace; letter-spacing: 0.14em; text-transform: uppercase; color: #f3f7ff; margin-bottom: 12px; }
    .admin-kicker { color: var(--accent-2); text-transform: uppercase; letter-spacing: 0.12em; font-size: 12px; margin-bottom: 28px; display:block; }
    .admin-nav { display:flex; flex-direction:column; gap: 8px; }
    .admin-nav-link { display:flex; align-items:center; justify-content:space-between; padding: 12px 14px; border: 1px solid transparent; border-radius: 14px; color: var(--text); background: transparent; }
    .admin-nav-link.is-active { background: rgba(88, 208, 162, 0.12); border-color: rgba(88, 208, 162, 0.35); color: #f5fffb; }
    .admin-nav-link:hover { border-color: rgba(143, 211, 255, 0.18); background: rgba(19, 39, 66, 0.35); }
    .admin-sidebar-footer { margin-top: 28px; padding-top: 20px; border-top: 1px solid rgba(143, 211, 255, 0.12); }
    .admin-content { padding: 28px; }
    .admin-topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom: 24px; }
    .admin-topbar h1 { margin: 0; font-size: 32px; line-height: 1; }
    .admin-topbar p { margin: 8px 0 0; color: var(--muted); max-width: 720px; }
    .admin-surface { max-width: 1320px; }
    .admin-card { background: var(--admin-card); border: 1px solid rgba(143, 211, 255, 0.14); border-radius: 22px; padding: 22px; box-shadow: 0 20px 44px rgba(0, 0, 0, 0.28); margin-bottom: 22px; }
    .admin-metrics { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
    .metric { background: linear-gradient(180deg, rgba(15, 29, 50, 0.92), rgba(8, 19, 31, 0.92)); border: 1px solid rgba(143, 211, 255, 0.12); border-radius: 18px; padding: 18px; }
    .metric strong { display:block; font-size: 28px; margin-top: 6px; color: #f2f6ff; }
    .admin-grid { display:grid; grid-template-columns: minmax(0, 1.7fr) minmax(320px, 0.9fr); gap: 22px; align-items:start; }
    .admin-actions { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .admin-actions a { display:block; padding: 14px; border-radius: 16px; border: 1px solid rgba(143, 211, 255, 0.14); background: rgba(19, 39, 66, 0.34); color: var(--text); }
    .admin-actions strong { display:block; margin-bottom: 4px; }
    .admin-table-wrap { overflow-x:auto; }
    .media-thumb { max-width:110px; border-radius:12px; display:block; }
    .media-library-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .media-library-card { border: 1px solid rgba(143, 211, 255, 0.14); border-radius: 18px; background: rgba(19, 39, 66, 0.28); overflow: hidden; min-height: 0; }
    .media-library-preview { aspect-ratio: 1 / 1; display:flex; align-items:center; justify-content:center; background: rgba(7, 17, 29, 0.8); }
    .media-library-preview img { width:100%; height:100%; object-fit:cover; display:block; }
    .media-library-meta { padding: 12px; }
    .media-library-meta strong { display:block; margin-bottom: 6px; word-break: break-word; }
    .admin-form-grid { display:grid; grid-template-columns: minmax(0, 1.6fr) minmax(320px, 0.9fr); gap: 22px; align-items:start; }
    .admin-aside-stack { display:flex; flex-direction:column; gap: 22px; }
    .security-grid { display:grid; grid-template-columns: minmax(0, 1.25fr) minmax(320px, 0.95fr); gap: 22px; align-items:start; }
    .security-stat-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
    .security-stat { padding: 14px; border-radius: 16px; border: 1px solid rgba(143, 211, 255, 0.12); background: rgba(19, 39, 66, 0.32); }
    .security-stat strong { display:block; font-size: 24px; margin-top: 4px; }
    code { background: rgba(143, 211, 255, 0.08); padding: 2px 6px; border-radius: 6px; }
    hr { border: 0; border-top: 1px solid rgba(143, 211, 255, 0.12); margin: 20px 0; }
    @media (max-width: 1100px) {
      .admin-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .admin-grid, .admin-form-grid, .security-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 900px) {
      .grid, .split, .two-col { grid-template-columns: 1fr; }
      .media-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .admin-shell { grid-template-columns: 1fr; }
      .admin-sidebar { position: static; height: auto; border-right: 0; border-bottom: 1px solid rgba(143, 211, 255, 0.12); }
      .admin-nav { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .admin-content { padding: 20px; }
      .admin-topbar { flex-direction: column; }
    }
    @media (max-width: 640px) {
      .admin-metrics, .admin-actions, .security-stat-grid, .admin-nav, .media-library-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 900px) {
      .media-library-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
  </style>
</head>
<body class="<?= $isAdminPage ? 'admin-body' : '' ?>">
  <?php if ($isAdminPage && $viewer && User::hasRole($viewer, User::ROLE_ADMIN)): ?>
    <div class="admin-shell">
      <aside class="admin-sidebar">
        <a class="admin-brand" href="/admin">CyberBlog</a>
        <span class="admin-kicker">Administration Panel</span>
        <nav class="admin-nav" aria-label="Admin sections">
          <?php foreach ($adminSections as $section): ?>
            <a class="admin-nav-link<?= $activeAdminHref === $section['href'] ? ' is-active' : '' ?>" href="<?= htmlspecialchars($section['href']) ?>">
              <span><?= htmlspecialchars($section['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-footer">
          <div><strong><?= htmlspecialchars($viewer['display_name']) ?></strong></div>
          <div class="muted"><?= htmlspecialchars($viewer['email']) ?></div>
          <div class="muted" style="margin-top:10px;">Administrator access</div>
          <div class="nav-links" style="margin-top:14px;">
            <a class="btn" href="/">View site</a>
            <form method="post" action="/logout" style="display:inline">
              <button type="submit">Logout</button>
            </form>
          </div>
        </div>
      </aside>
      <main class="admin-content">
        <div class="admin-surface">
          <?php require $templateFile; ?>
        </div>
      </main>
    </div>
  <?php else: ?>
    <div class="wrap">
      <div class="nav">
        <a class="brand" href="/">CyberBlog</a>
        <div class="nav-links">
          <a href="/">Home</a>
          <?php if ($viewer && User::hasRole($viewer, User::ROLE_ADMIN)): ?>
            <a href="/admin">Admin</a>
            <form method="post" action="/logout" style="display:inline">
              <button type="submit">Logout</button>
            </form>
          <?php elseif ($viewer): ?>
            <form method="post" action="/logout" style="display:inline">
              <button type="submit">Logout</button>
            </form>
          <?php else: ?>
            <a href="/login">Login</a>
          <?php endif; ?>
        </div>
      </div>
      <?php require $templateFile; ?>
    </div>
  <?php endif; ?>
</body>
</html>
