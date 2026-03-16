<header class="admin-topbar">
  <div>
    <h1>Users</h1>
    <p>Manage administrators and editorial accounts, including role assignment and lockout recovery controls.</p>
  </div>
</header>

<div class="admin-grid">
  <section class="admin-card">
    <?php if ($flash): ?><div class="flash"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <div class="page-header">
      <div>
        <h2>User Directory</h2>
        <p class="muted">Review account status, login friction, and required security setup state.</p>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table>
        <thead><tr><th>User</th><th>Role</th><th>Security</th><th>Update</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($user['display_name']) ?></strong>
              <div class="muted"><?= htmlspecialchars($user['email']) ?></div>
            </td>
            <td><?= htmlspecialchars($user['role']) ?></td>
            <td>
              <div class="muted">Password: <?= !empty($user['password_hash']) ? 'set' : 'missing' ?></div>
              <div class="muted">TOTP: <?= !empty($user['totp_enabled']) ? 'enabled' : 'disabled' ?></div>
              <div class="muted">Failed attempts: <?= (int) ($user['failed_login_attempts'] ?? 0) ?></div>
              <div class="muted">Temporary lock: <?= !empty($user['lock_until']) ? htmlspecialchars((string) $user['lock_until']) . ' UTC' : 'none' ?></div>
              <div class="muted">Admin unlock required: <?= !empty($user['admin_unlock_required']) ? 'yes' : 'no' ?></div>
            </td>
            <td>
              <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/edit">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <input name="display_name" value="<?= htmlspecialchars($user['display_name']) ?>" required>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                <select name="role">
                  <?php foreach (['admin', 'editor', 'author'] as $role): ?>
                    <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="password" name="password" placeholder="New password (optional)">
                <label><input type="checkbox" name="must_setup_auth" value="1" <?= !empty($user['must_setup_auth']) ? 'checked' : '' ?>> Require setup</label>
                <label><input type="checkbox" name="reset_auth" value="1"> Reset TOTP and require re-enrollment</label>
                <button type="submit">Save User</button>
              </form>
              <form method="post" action="/admin/users/<?= (int) $user['id'] ?>/unlock">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                <button type="submit">Unlock Account</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <aside class="admin-card">
    <div class="page-header">
      <div>
        <h2>Create User</h2>
        <p class="muted">Provision a new account and require security setup on first access.</p>
      </div>
    </div>
    <form method="post" action="/admin/users">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <label>Display name</label>
      <input name="display_name" required>
      <label>Email</label>
      <input type="email" name="email" required>
      <label>Role</label>
      <select name="role">
        <option value="author">Author</option>
        <option value="editor">Editor</option>
        <option value="admin">Admin</option>
      </select>
      <label>Temporary password</label>
      <input type="password" name="password" required>
      <button type="submit">Create User</button>
    </form>
  </aside>
</div>
