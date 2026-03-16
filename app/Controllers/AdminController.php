<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Category;
use App\Models\ImportRun;
use App\Models\Media;
use App\Models\PasskeyCredential;
use App\Models\Post;
use App\Models\Preference;
use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\MediaStorage;
use App\Services\RecoveryCodeService;
use App\Services\SlugService;
use App\Services\TotpService;
use App\Services\WebAuthnService;
use App\Services\WordPressImporter;
use RuntimeException;

final class AdminController
{
    public function dashboard(): void
    {
        $user = Auth::requireAdmin();
        $posts = Post::allForAdmin($user, 1, $this->articlesPerPage());
        $categories = Category::optionsWithDepth();
        $imports = ImportRun::all();
        $users = User::all();
        $preferences = Preference::all();
        $passkeys = PasskeyCredential::forUser((int) $user['id']);
        $recoveryCodes = RecoveryCode::forUser((int) $user['id']);

        View::render('admin/dashboard', [
            'title' => 'Dashboard',
            'user' => $user,
            'posts' => $posts['items'],
            'imports' => $imports,
            'stats' => [
                'preferences' => count($preferences),
                'posts' => (int) ($posts['total'] ?? count($posts['items'])),
                'categories' => count($categories),
                'media' => count(Media::all()),
                'users' => count($users),
                'imports' => count($imports),
                'passkeys' => count($passkeys),
                'recovery_codes' => count(array_filter($recoveryCodes, static fn(array $code): bool => !$code['used_at'])),
            ],
        ]);
    }

    public function posts(): void
    {
        $user = Auth::requireAdmin();
        $pagination = Post::allForAdmin($user, $this->page(), $this->articlesPerPage());

        View::render('admin/posts', [
            'title' => 'Posts',
            'posts' => $pagination['items'],
            'pagination' => $pagination,
            'flash' => Session::flash('status'),
            'user' => $user,
        ]);
    }

    public function createPost(): void
    {
        $user = Auth::requireAdmin();
        View::render('admin/post-form', [
            'title' => 'Create Post',
            'post' => null,
            'categories' => Category::tree(),
            'categoryOptions' => Category::optionsWithDepth(),
            'media' => Media::images(),
            'featuredMedia' => null,
            'user' => $user,
        ]);
    }

    public function storePost(): void
    {
        $user = Auth::requireAdmin();
        $this->verifyCsrf();
        $postId = Post::save($this->postPayload($user));
        Post::syncCategories($postId, $_POST['category_ids'] ?? []);
        Session::flash('status', 'Post created.');
        Response::redirect('/admin/posts');
    }

    public function editPost(string $id): void
    {
        $user = Auth::requireAdmin();
        $post = Post::find((int) $id);
        if (!$post) {
            Response::abort(404, 'Post not found.');
        }
        View::render('admin/post-form', [
            'title' => 'Edit Post',
            'post' => $post,
            'categories' => Category::tree(),
            'categoryOptions' => Category::optionsWithDepth(),
            'media' => Media::images(),
            'featuredMedia' => !empty($post['featured_media_id']) ? Media::find((int) $post['featured_media_id']) : null,
            'user' => $user,
        ]);
    }

    public function updatePost(string $id): void
    {
        $user = Auth::requireAdmin();
        $this->verifyCsrf();
        $post = Post::find((int) $id);
        if (!$post) {
            Response::abort(404, 'Post not found.');
        }
        Post::save($this->postPayload($user, $post), (int) $id);
        Post::syncCategories((int) $id, $_POST['category_ids'] ?? []);
        Session::flash('status', 'Post updated.');
        Response::redirect('/admin/posts');
    }

    public function deletePost(string $id): void
    {
        $user = Auth::requireAdmin();
        $this->verifyCsrf();
        $post = Post::find((int) $id);
        if (!$post) {
            Response::abort(404, 'Post not found.');
        }
        Post::delete((int) $id);
        Session::flash('status', 'Post deleted.');
        Response::redirect('/admin/posts');
    }

    public function categories(): void
    {
        Auth::requireAdmin();
        View::render('admin/categories', [
            'title' => 'Categories',
            'categories' => Category::tree(),
            'flatCategories' => Category::optionsWithDepth(),
            'flash' => Session::flash('status'),
        ]);
    }

    public function storeCategory(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $baseSlug = SlugService::slugify((string) (trim((string) ($_POST['slug'] ?? '')) ?: $_POST['name']));

        try {
            Category::create([
                'name' => trim((string) $_POST['name']),
                'slug' => SlugService::unique('categories', $baseSlug),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'parent_id' => (int) ($_POST['parent_id'] ?? 0),
            ]);
            Session::flash('status', 'Category created.');
        } catch (RuntimeException $e) {
            Session::flash('status', $e->getMessage());
        }

        Response::redirect('/admin/categories');
    }

    public function updateCategory(string $id): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $category = Category::find((int) $id);
        if (!$category) {
            Response::abort(404, 'Category not found.');
        }

        $baseSlug = SlugService::slugify((string) (trim((string) ($_POST['slug'] ?? '')) ?: $_POST['name']));
        try {
            Category::update((int) $id, [
                'name' => trim((string) $_POST['name']),
                'slug' => SlugService::unique('categories', $baseSlug, (int) $id),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'parent_id' => (int) ($_POST['parent_id'] ?? 0),
            ]);
            Session::flash('status', 'Category updated.');
        } catch (RuntimeException $e) {
            Session::flash('status', $e->getMessage());
        }

        Response::redirect('/admin/categories');
    }

    public function media(): void
    {
        Auth::requireAdmin();
        View::render('admin/media', [
            'title' => 'Media',
            'media' => Media::all(),
            'flash' => Session::flash('status'),
        ]);
    }

    public function mediaPicker(): void
    {
        Auth::requireAdmin();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 12;
        $sort = (string) ($_GET['sort'] ?? 'newest');
        $offset = ($page - 1) * $limit;

        Response::json([
            'items' => Media::images($sort, $limit, $offset),
            'page' => $page,
            'total_pages' => max(1, (int) ceil(Media::imageCount() / $limit)),
            'sort' => $sort,
        ]);
    }

    public function uploadMedia(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $stored = (new MediaStorage())->storeUpload($_FILES['media_file'] ?? []);
        Session::flash('status', $stored ? 'Media uploaded.' : 'No file uploaded.');
        Response::redirect('/admin/media');
    }

    public function uploadMediaFromPicker(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $stored = (new MediaStorage())->storeUpload($_FILES['media_file'] ?? []);
        if (!$stored) {
            Response::json(['error' => 'No file uploaded.'], 422);
        }
        Response::json(['item' => $stored]);
    }

    public function imports(): void
    {
        Auth::requireAdmin();
        View::render('admin/imports', [
            'title' => 'Imports',
            'imports' => ImportRun::all(),
            'flash' => Session::flash('status'),
        ]);
    }

    public function runImport(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();

        if (($_FILES['wordpress_archive']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('status', 'Choose a .tar.gz archive first.');
            Response::redirect('/admin/imports');
        }

        $tmpPath = app_path('storage/tmp/' . basename((string) $_FILES['wordpress_archive']['name']));
        move_uploaded_file($_FILES['wordpress_archive']['tmp_name'], $tmpPath);
        try {
            (new WordPressImporter())->importArchive($tmpPath);
            Session::flash('status', 'Import completed.');
        } catch (\Throwable $e) {
            Session::flash('status', 'Import failed: ' . $e->getMessage());
        }
        Response::redirect('/admin/imports');
    }

    public function users(): void
    {
        Auth::requireAdmin();
        View::render('admin/users', [
            'title' => 'Users',
            'users' => User::all(),
            'flash' => Session::flash('status'),
        ]);
    }

    public function storeUser(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $password = trim((string) ($_POST['password'] ?? ''));

        User::createFromArray([
            'email' => trim((string) $_POST['email']),
            'display_name' => trim((string) $_POST['display_name']),
            'role' => (string) ($_POST['role'] ?? User::ROLE_AUTHOR),
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'must_setup_auth' => 1,
        ]);

        Session::flash('status', 'User created.');
        Response::redirect('/admin/users');
    }

    public function updateUser(string $id): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $user = User::find((int) $id);
        if (!$user) {
            Response::abort(404, 'User not found.');
        }

        User::updateProfile((int) $id, [
            'email' => trim((string) $_POST['email']),
            'display_name' => trim((string) $_POST['display_name']),
            'role' => (string) ($_POST['role'] ?? User::ROLE_AUTHOR),
            'must_setup_auth' => !empty($_POST['must_setup_auth']),
        ]);

        $password = trim((string) ($_POST['password'] ?? ''));
        if ($password !== '') {
            User::setPassword((int) $id, $password);
        }

        if (!empty($_POST['reset_auth'])) {
            User::setTotpSecret((int) $id, null, false);
            User::requireAuthSetup((int) $id);
        }

        Session::flash('status', 'User updated.');
        Response::redirect('/admin/users');
    }

    public function unlockUser(string $id): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $user = User::find((int) $id);
        if (!$user) {
            Response::abort(404, 'User not found.');
        }

        User::unlock((int) $id);
        Session::flash('status', 'User unlocked.');
        Response::redirect('/admin/users');
    }

    public function preferences(): void
    {
        Auth::requireAdmin();
        View::render('admin/preferences', [
            'title' => 'Preferences',
            'flash' => Session::flash('status'),
            'preferences' => Preference::all(),
        ]);
    }

    public function updatePreferences(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        Preference::set('articles_per_page', (string) max(1, min(100, (int) ($_POST['articles_per_page'] ?? 10))));
        Preference::set('smtp_enabled', !empty($_POST['smtp_enabled']) ? '1' : '0');
        Preference::set('smtp_host', trim((string) ($_POST['smtp_host'] ?? '')));
        Preference::set('smtp_port', (string) max(1, (int) ($_POST['smtp_port'] ?? 587)));
        Preference::set('smtp_username', trim((string) ($_POST['smtp_username'] ?? '')));
        if (array_key_exists('smtp_password', $_POST) && trim((string) $_POST['smtp_password']) !== '') {
            Preference::set('smtp_password', (string) $_POST['smtp_password']);
        }
        Preference::set('smtp_encryption', in_array($_POST['smtp_encryption'] ?? 'tls', ['none', 'tls', 'ssl'], true) ? (string) $_POST['smtp_encryption'] : 'tls');
        Preference::set('smtp_from_email', trim((string) ($_POST['smtp_from_email'] ?? '')));
        Preference::set('smtp_from_name', trim((string) ($_POST['smtp_from_name'] ?? 'CyberBlog')));
        Session::flash('status', 'Preferences updated.');
        Response::redirect('/admin/preferences');
    }

    public function security(): void
    {
        $user = Auth::requireAdmin();
        View::render('admin/security', [
            'title' => 'Security',
            'user' => $user,
            'passkeys' => PasskeyCredential::forUser((int) $user['id']),
            'recoveryCount' => count(array_filter(RecoveryCode::forUser((int) $user['id']), static fn(array $code): bool => !$code['used_at'])),
            'freshCodes' => Session::flash('recovery_codes'),
            'flash' => Session::flash('status'),
            'pendingTotpSecret' => Session::get('totp.pending_secret'),
            'totpUri' => Session::get('totp.pending_uri'),
        ]);
    }

    public function securityBootstrap(): void
    {
        $user = Auth::requireAdmin(true);
        View::render('admin/security-bootstrap', [
            'title' => 'Security Bootstrap',
            'user' => $user,
            'passkeys' => PasskeyCredential::forUser((int) $user['id']),
            'freshCodes' => Session::flash('recovery_codes'),
            'pendingTotpSecret' => Session::get('totp.pending_secret'),
            'totpUri' => Session::get('totp.pending_uri'),
        ]);
    }

    public function registrationOptions(): void
    {
        $user = Auth::requireAdmin(true);
        Response::json((new WebAuthnService())->registrationOptions($user, PasskeyCredential::forUser((int) $user['id'])));
    }

    public function registerPasskey(): void
    {
        $user = Auth::requireAdmin(true);
        $payload = json_decode(file_get_contents('php://input') ?: '{}', true);

        try {
            PasskeyCredential::create((new WebAuthnService())->verifyRegistration($payload, $user));
            $this->refreshBootstrapState($user);
            Response::json(['redirect' => '/admin/security']);
        } catch (RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function deletePasskey(): void
    {
        $user = Auth::requireAdmin();
        $this->verifyCsrf();
        PasskeyCredential::deleteForUser((int) ($_POST['passkey_id'] ?? 0), (int) $user['id']);
        Session::flash('status', 'Passkey removed.');
        Response::redirect('/admin/security');
    }

    public function beginTotp(): void
    {
        $user = Auth::requireAdmin(true);
        $this->verifyCsrf();
        $service = new TotpService();
        $secret = $service->generateSecret();
        Session::put('totp.pending_secret', $secret);
        Session::put('totp.pending_uri', $service->otpauthUri((string) $user['email'], $secret));
        Session::flash('status', 'Scan the secret in your authenticator app, then verify a code.');
        Response::redirect('/admin/security');
    }

    public function verifyTotp(): void
    {
        $user = Auth::requireAdmin(true);
        $this->verifyCsrf();
        $secret = (string) Session::get('totp.pending_secret', '');
        if ($secret === '') {
            Session::flash('status', 'Start authenticator setup first.');
            Response::redirect('/admin/security');
        }

        if (!(new TotpService())->verifyCode($secret, trim((string) ($_POST['totp_code'] ?? '')))) {
            Session::flash('status', 'The verification code was invalid.');
            Response::redirect('/admin/security');
        }

        User::setTotpSecret((int) $user['id'], $secret, true);
        Session::forget('totp.pending_secret');
        Session::forget('totp.pending_uri');
        $this->refreshBootstrapState(User::find((int) $user['id']) ?: $user);
        Session::flash('status', 'Authenticator app enabled.');
        Response::redirect('/admin/security');
    }

    public function disableTotp(): void
    {
        $user = Auth::requireAdmin();
        $this->verifyCsrf();
        User::setTotpSecret((int) $user['id'], null, false);
        User::requireAuthSetup((int) $user['id']);
        Session::forget('totp.pending_secret');
        Session::forget('totp.pending_uri');
        Session::flash('status', 'Authenticator app removed.');
        Response::redirect('/admin/security');
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::requireAdmin(true);
        $this->verifyCsrf();
        $codes = (new RecoveryCodeService())->regenerate((int) $user['id']);
        Session::flash('recovery_codes', implode(', ', $codes));
        $this->refreshBootstrapState($user, $codes);
        Session::flash('status', 'Recovery codes regenerated.');
        Response::redirect('/admin/security');
    }

    private function postPayload(array $user, ?array $existingPost = null): array
    {
        $baseSlug = SlugService::slugify((string) (trim((string) ($_POST['slug'] ?? '')) ?: $_POST['title']));
        $ignoreId = !empty($_POST['id']) ? (int) $_POST['id'] : null;

        return [
            'title' => trim((string) $_POST['title']),
            'slug' => SlugService::unique('posts', $baseSlug, $ignoreId),
            'excerpt' => '',
            'body_html' => HtmlSanitizer::clean((string) ($_POST['body_html'] ?? '')),
            'status' => in_array($_POST['status'] ?? 'draft', ['draft', 'published'], true) ? $_POST['status'] : 'draft',
            'published_at' => trim((string) ($_POST['published_at'] ?? '')),
            'featured_media_id' => (int) ($_POST['featured_media_id'] ?? 0),
            'author_id' => User::hasRole($user, User::ROLE_AUTHOR) ? (int) $user['id'] : (int) ($_POST['author_id'] ?? ($existingPost['author_id'] ?? $user['id'])),
        ];
    }

    private function page(): int
    {
        return max(1, (int) ($_GET['page'] ?? 1));
    }

    private function articlesPerPage(): int
    {
        return max(1, min(100, (int) Preference::get('articles_per_page', '10')));
    }

    private function refreshBootstrapState(array $user, array $codes = []): void
    {
        $hasPassword = !empty($user['password_hash']);
        $hasTotp = !empty($user['totp_enabled']);
        $hasPasskey = PasskeyCredential::forUser((int) $user['id']) !== [];
        $hasRecovery = $codes !== [] || count(array_filter(RecoveryCode::forUser((int) $user['id']), static fn(array $code): bool => !$code['used_at'])) > 0;

        if ($hasPassword && ($hasTotp || $hasPasskey) && $hasRecovery) {
            User::markAuthBootstrapComplete((int) $user['id']);
            return;
        }

        User::requireAuthSetup((int) $user['id']);
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
            Response::abort(419, 'Invalid CSRF token.');
        }
    }
}
