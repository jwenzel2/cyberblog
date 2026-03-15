<?php

use App\Core\Auth;
use App\Models\User;

$viewer = Auth::user();
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
      --danger: #ff6d6d;
      --link: #8fd3ff;
    }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: radial-gradient(circle at top, #123456, var(--bg) 40%); color: var(--text); }
    a { color: var(--link); text-decoration: none; }
    .wrap { max-width: 1180px; margin: 0 auto; padding: 24px; }
    .nav { display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; gap: 16px; }
    .brand { font: 700 24px/1.1 Consolas, monospace; text-transform: uppercase; letter-spacing: 0.12em; }
    .nav-links { display:flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    .btn, button { border: 1px solid var(--line); background: var(--panel-2); color: var(--text); padding: 10px 14px; border-radius: 10px; cursor: pointer; }
    .grid { display:grid; grid-template-columns: 3fr 1fr; gap: 24px; }
    .split { display:grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items:start; }
    .card { background: rgba(12, 23, 40, 0.92); border: 1px solid var(--line); border-radius: 18px; padding: 20px; margin-bottom: 20px; box-shadow: 0 18px 40px rgba(0,0,0,.22); }
    .muted { color: var(--muted); }
    input, textarea, select { width:100%; border-radius: 10px; border: 1px solid var(--line); background: #091423; color: var(--text); padding: 10px; margin: 6px 0 14px; }
    textarea { min-height: 220px; }
    .flash { padding: 12px 14px; border: 1px solid var(--accent); color: var(--accent); border-radius: 10px; margin-bottom: 20px; }
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
    @media (max-width: 900px) { .grid, .split, .two-col { grid-template-columns: 1fr; } .media-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="nav">
      <a class="brand" href="/">CyberBlog</a>
      <div class="nav-links">
        <a href="/">Home</a>
        <?php if ($viewer): ?>
          <a href="/admin">Dashboard</a>
          <a href="/admin/posts">Posts</a>
          <?php if (User::hasRole($viewer, User::ROLE_ADMIN, User::ROLE_EDITOR)): ?>
            <a href="/admin/categories">Categories</a>
            <a href="/admin/media">Media</a>
          <?php endif; ?>
          <?php if (User::hasRole($viewer, User::ROLE_ADMIN)): ?>
            <a href="/admin/users">Users</a>
            <a href="/admin/preferences">Preferences</a>
            <a href="/admin/imports">Imports</a>
          <?php endif; ?>
          <a href="/admin/security">Security</a>
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
</body>
</html>
