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
use App\Models\User;
use App\Services\HtmlSanitizer;
use App\Services\MediaStorage;
use App\Services\RecoveryCodeService;
use App\Services\SlugService;
use App\Services\WebAuthnService;
use App\Services\WordPressImporter;
use RuntimeException;

final class AdminController
{
    public function dashboard(): void
    {
        $user = Auth::requireAdmin();

        View::render('admin/dashboard', [
            'title' => 'Dashboard',
            'user' => $user,
            'posts' => Post::allForAdmin(),
            'categories' => Category::tree(),
            'imports' => ImportRun::all(),
        ]);
    }

    public function posts(): void
    {
        Auth::requireAdmin();
        View::render('admin/posts', [
            'title' => 'Posts',
            'posts' => Post::allForAdmin(),
            'flash' => Session::flash('status'),
        ]);
    }

    public function createPost(): void
    {
        Auth::requireAdmin();
        View::render('admin/post-form', [
            'title' => 'Create Post',
            'post' => null,
            'categories' => Category::tree(),
            'media' => Media::all(),
        ]);
    }

    public function storePost(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $postId = Post::save($this->postPayload());
        Post::syncCategories($postId, $_POST['category_ids'] ?? []);
        Session::flash('status', 'Post created.');
        Response::redirect('/admin/posts');
    }

    public function editPost(string $id): void
    {
        Auth::requireAdmin();
        $post = Post::find((int) $id);
        if (!$post) {
            Response::abort(404, 'Post not found.');
        }

        View::render('admin/post-form', [
            'title' => 'Edit Post',
            'post' => $post,
            'categories' => Category::tree(),
            'media' => Media::all(),
        ]);
    }

    public function updatePost(string $id): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        Post::save($this->postPayload(), (int) $id);
        Post::syncCategories((int) $id, $_POST['category_ids'] ?? []);
        Session::flash('status', 'Post updated.');
        Response::redirect('/admin/posts');
    }

    public function deletePost(string $id): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
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
            'flatCategories' => Category::all(),
            'flash' => Session::flash('status'),
        ]);
    }

    public function storeCategory(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $baseSlug = SlugService::slugify((string) (trim((string) ($_POST['slug'] ?? '')) ?: $_POST['name']));
        Category::create([
            'name' => trim((string) $_POST['name']),
            'slug' => SlugService::unique('categories', $baseSlug),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'parent_id' => (int) ($_POST['parent_id'] ?? 0),
        ]);
        Session::flash('status', 'Category created.');
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

    public function uploadMedia(): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $stored = (new MediaStorage())->storeUpload($_FILES['media_file'] ?? []);
        Session::flash('status', $stored ? 'Media uploaded.' : 'No file uploaded.');
        Response::redirect('/admin/media');
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

    public function security(): void
    {
        $user = Auth::requireAdmin();
        View::render('admin/security', [
            'title' => 'Security',
            'user' => $user,
            'passkeys' => PasskeyCredential::forUser((int) $user['id']),
            'recoveryCount' => count(array_filter(\App\Models\RecoveryCode::forUser((int) $user['id']), static fn(array $code): bool => !$code['used_at'])),
            'freshCodes' => Session::flash('recovery_codes'),
            'flash' => Session::flash('status'),
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
            \App\Models\PasskeyCredential::create((new WebAuthnService())->verifyRegistration($payload, $user));
            if (count(PasskeyCredential::forUser((int) $user['id'])) === 1) {
                $codes = (new RecoveryCodeService())->regenerate((int) $user['id']);
                User::markAuthBootstrapComplete((int) $user['id']);
                Session::flash('recovery_codes', implode(', ', $codes));
            }
            Response::json(['redirect' => '/admin/security']);
        } catch (RuntimeException $e) {
            Response::json(['error' => $e->getMessage()], 422);
        }
    }

    public function deletePasskey(): void
    {
        $user = Auth::requireAdmin();
        $this->verifyCsrf();
        \App\Models\PasskeyCredential::deleteForUser((int) ($_POST['passkey_id'] ?? 0), (int) $user['id']);
        Session::flash('status', 'Passkey removed.');
        Response::redirect('/admin/security');
    }

    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::requireAdmin(true);
        $this->verifyCsrf();
        $codes = (new RecoveryCodeService())->regenerate((int) $user['id']);
        User::markAuthBootstrapComplete((int) $user['id']);
        Session::flash('recovery_codes', implode(', ', $codes));
        Session::flash('status', 'Recovery codes regenerated.');
        Response::redirect('/admin/security');
    }

    private function postPayload(): array
    {
        $baseSlug = SlugService::slugify((string) (trim((string) ($_POST['slug'] ?? '')) ?: $_POST['title']));
        $ignoreId = !empty($_POST['id']) ? (int) $_POST['id'] : null;

        return [
            'title' => trim((string) $_POST['title']),
            'slug' => SlugService::unique('posts', $baseSlug, $ignoreId),
            'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
            'body_html' => HtmlSanitizer::clean((string) ($_POST['body_html'] ?? '')),
            'status' => in_array($_POST['status'] ?? 'draft', ['draft', 'published'], true) ? $_POST['status'] : 'draft',
            'published_at' => trim((string) ($_POST['published_at'] ?? '')),
            'featured_media_id' => (int) ($_POST['featured_media_id'] ?? 0),
        ];
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::abort(419, 'Invalid CSRF token.');
        }
    }
}
