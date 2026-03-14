<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\InstallerService;

$installed = is_file(app_path('.env')) && \App\Core\Env::get('APP_INSTALLED', '0') === '1';
if ($installed && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (\App\Core\Auth::check()) {
        \App\Core\Response::redirect('/admin/security/bootstrap');
    }

    \App\Core\Response::redirect('/login');
}

$service = new InstallerService();
$result = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$result, $errors] = $service->handle($_POST, $_FILES);
    if ($result && $errors === []) {
        \App\Core\Response::redirect('/admin/security/bootstrap');
    }
}

$checks = $service->checks();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CyberBlog Installer</title>
  <style>
    body { font-family: Consolas, monospace; background: #08111f; color: #d9e5f7; margin: 0; padding: 32px; }
    .wrap { max-width: 980px; margin: 0 auto; }
    .card { background: #0f1b2e; padding: 24px; border-radius: 16px; margin-bottom: 24px; border: 1px solid #1f3352; }
    input, button { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #28446e; background: #06101d; color: #d9e5f7; margin-bottom: 12px; }
    label { display: block; margin-top: 12px; font-weight: bold; }
    .ok { color: #61d095; }
    .bad { color: #ff7b7b; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>CyberBlog Installer</h1>
      <p>Validate PHP, initialize MariaDB, create the admin account, and optionally import a WordPress archive.</p>
      <?php foreach ($checks as $label => $status): ?>
        <div class="<?= $status ? 'ok' : 'bad' ?>"><?= htmlspecialchars($label) ?>: <?= $status ? 'OK' : 'Missing' ?></div>
      <?php endforeach; ?>
    </div>

    <?php if ($errors): ?>
      <div class="card bad">
        <h2>Errors</h2>
        <?php foreach ($errors as $error): ?>
          <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($result): ?>
      <div class="card ok">
        <h2>Installation complete</h2>
        <div><?= htmlspecialchars($result) ?></div>
        <p><a href="/" style="color:#9ad4ff;">Open CyberBlog</a></p>
      </div>
    <?php endif; ?>

    <form class="card" method="post" enctype="multipart/form-data">
      <input type="hidden" name="installer_form" value="1">
      <h2>Application</h2>
      <label>App URL</label>
      <input name="app_url" required value="<?= htmlspecialchars($_POST['app_url'] ?? 'http://localhost') ?>">
      <label>RP ID</label>
      <input name="rp_id" required value="<?= htmlspecialchars($_POST['rp_id'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?>">

      <h2>MariaDB</h2>
      <label>Host</label>
      <input name="db_host" required value="<?= htmlspecialchars($_POST['db_host'] ?? '127.0.0.1') ?>">
      <label>Port</label>
      <input name="db_port" required value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>">
      <label>Database</label>
      <input name="db_database" required value="<?= htmlspecialchars($_POST['db_database'] ?? 'cyberblog') ?>">
      <label>Username</label>
      <input name="db_username" required value="<?= htmlspecialchars($_POST['db_username'] ?? '') ?>">
      <label>Password</label>
      <input type="password" name="db_password" value="<?= htmlspecialchars($_POST['db_password'] ?? '') ?>">

      <h2>Admin</h2>
      <label>Email</label>
      <input type="email" name="admin_email" required value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">
      <label>Display name</label>
      <input name="admin_display_name" required value="<?= htmlspecialchars($_POST['admin_display_name'] ?? 'Admin') ?>">

      <h2>WordPress Import (optional)</h2>
      <p style="color:#8fa9c7; margin-top:0;">
        If you upload a large `.tar.gz`, make sure `post_max_size` and `upload_max_filesize` are larger than the archive.
      </p>
      <label>Archive (.tar.gz)</label>
      <input type="file" name="wordpress_archive" accept=".tar.gz,application/gzip">

      <button type="submit">Install CyberBlog</button>
    </form>
  </div>
</body>
</html>
