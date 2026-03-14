<?php

use App\Core\Auth;
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
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="nav">
      <a class="brand" href="/">CyberBlog</a>
      <div class="nav-links">
        <a href="/">Home</a>
        <?php if (Auth::check()): ?>
          <a href="/admin">Dashboard</a>
          <a href="/admin/posts">Posts</a>
          <a href="/admin/categories">Categories</a>
          <a href="/admin/media">Media</a>
          <a href="/admin/imports">Imports</a>
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
